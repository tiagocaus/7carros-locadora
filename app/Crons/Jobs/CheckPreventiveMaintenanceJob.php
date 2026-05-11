<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Services\AuditLogService;
use mysqli;

class CheckPreventiveMaintenanceJob extends BaseJob
{
    protected string $name = 'CheckPreventiveMaintenance';
    protected string $description = 'Verifica e gera OS de manutenção preventiva';

    /**
     * Margem em km para alertar (padrão: 500)
     */
    private int $margemKm;
    private array $notificationSkipLogs = [];

    public function __construct()
    {
        $this->margemKm = (int) Database::env('MANUTENCAO_MARGEM_KM', 500);
    }

    /**
     * Executa a verificação de manutenções preventivas
     *
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    protected function handle(): array
    {
        $inicioExecucao = microtime(true);

        if (Database::env('MANUTENCAO_CRON_ENABLED', 'true') !== 'true') {
            $this->log(t('modules.manutencao.cron.disabled'));
            return [
                'success' => true,
                'message' => t('modules.manutencao.cron.disabled'),
                'data' => []
            ];
        }

        // Cria conexão mysqli
        $mysqli = new mysqli(
            Database::env('DB_HOST'),
            Database::env('DB_USERNAME'),
            Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'),
            (int) Database::env('DB_PORT', '3306')
        );
        $mysqli->set_charset('utf8mb4');

        $qb = new QueryBuilder($mysqli);
        $qb->withoutChave();

        // Carrega TODOS os planos de manutenção de TODOS os tenants (1 query)
        // Indexados por ID para acesso O(1) via veiculos.id_plano_manutencao
        $planos = $this->carregarPlanos($qb);

        // Busca chaves distintas com veículos que têm plano
        $chaves = $this->carregarChavesComVeiculos($qb);

        $osGeradas = 0;
        $veiculosProcessados = 0;
        $tenantsProcessados = 0;
        $osCriadas = [];
        $erros = [];

        // Processa por tenant (chave)
        foreach ($chaves as $chave) {
            // Define contexto de sessão para AuditLogService
            $this->setContextoTenant($chave);
            $tenantsProcessados++;

            $this->log(t('modules.manutencao.cron.processing_tenant', ['chave' => $chave]));

            // Carrega veículos do tenant atual
            $veiculos = $this->carregarVeiculosPorTenant($qb, $chave);

            // Contador de OS geradas neste tenant
            $osGeradasNoTenant = 0;

            foreach ($veiculos as $veiculo) {
                $veiculosProcessados++;

                // Cada veículo pode usar um plano diferente!
                // O id_plano_manutencao define qual template de intervalos usar
                $planoId = $veiculo['id_plano_manutencao'];
                if (!isset($planos[$planoId])) {
                    // Veículo sem plano válido vinculado
                    continue;
                }

                // Template com INTERVALOS (ex: óleo a cada 10.000 km)
                $planoIntervalos = json_decode($planos[$planoId], true);
                // PRÓXIMA manutenção em km absoluto (ex: óleo em 20.000 km)
                $planoVeiculo = json_decode($veiculo['plano_manutencao_array'], true);
                $odometroAtual = $this->parseKm($veiculo['odometro']);

                // Verifica itens pendentes
                $itensPendentes = $this->verificarItensPendentes(
                    $odometroAtual,
                    $planoVeiculo,
                    $planoIntervalos
                );

                if (!empty($itensPendentes)) {
                    try {
                        // Gera OS
                        $osInfo = $this->gerarOS($qb, $veiculo, $itensPendentes);

                        // Atualiza próximos km no JSON
                        $this->atualizarPlanoVeiculo($qb, $veiculo, $itensPendentes, $planoIntervalos);

                        $osCriadas[] = $osInfo;
                        $osGeradas++;
                        $osGeradasNoTenant++;
                    } catch (\Exception $e) {
                        $erros[] = [
                            'tenant' => $chave,
                            'placa' => $veiculo['placa'],
                            'erro' => $e->getMessage(),
                        ];
                        $this->log("Erro ao processar veículo {$veiculo['placa']}: {$e->getMessage()}", 'ERROR');
                    }
                }
            }

            // Notifica usuários do tenant se houve OS criadas
            if ($osGeradasNoTenant > 0) {
                $this->notificarTenant($qb, $chave);
            }
        }

        // Limpa contexto de sessão
        $this->limparContextoTenant();

        $this->log(t('modules.manutencao.cron.finished', [
            'tenants' => $tenantsProcessados,
            'veiculos' => $veiculosProcessados,
            'os' => $osGeradas
        ]));

        // Calcula tempo de execução
        $tempoExecucao = microtime(true) - $inicioExecucao;

        // Envia email de resumo para APP_COMPANY_EMAIL
        $this->enviarResumoExecucao($tenantsProcessados, $veiculosProcessados, $osGeradas, $erros, $tempoExecucao);

        return [
            'success' => true,
            'message' => t('modules.manutencao.cron.result', [
                'tenants' => $tenantsProcessados,
                'veiculos' => $veiculosProcessados,
                'os' => $osGeradas
            ]),
            'data' => [
                'tenants_processados' => $tenantsProcessados,
                'veiculos_processados' => $veiculosProcessados,
                'os_geradas' => $osGeradas,
                'os_lista' => $osCriadas,
            ]
        ];
    }

    /**
     * Define contexto de sessão para o tenant atual
     * Necessário para AuditLogService funcionar
     */
    private function setContextoTenant(string $chave): void
    {
        $_SESSION['chave'] = $chave;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_name'] = 'Sistema';
    }

    /**
     * Limpa contexto de sessão após processamento
     */
    private function limparContextoTenant(): void
    {
        unset($_SESSION['chave'], $_SESSION['user_id'], $_SESSION['user_name']);
    }

    /**
     * Carrega todos os planos de manutenção de todos os tenants (1 query)
     * Retorna array indexado por ID para acesso O(1)
     *
     * Um tenant pode ter múltiplos planos (Padrão, Premium, Econômico...)
     * Cada veículo escolhe qual plano usar via id_plano_manutencao
     *
     * @return array [id => json_intervalos, ...]
     */
    private function carregarPlanos(QueryBuilder $qb): array
    {
        $planos = [];
        $rows = $qb->table('manutencoes_plano')->select(['id', 'array'])->whereRaw("status = 'A'")->get();
        foreach ($rows as $row) {
            // Indexa por ID para busca rápida: $planos[$veiculo['id_plano_manutencao']]
            $planos[$row['id']] = $row['array'];
        }
        return $planos;
    }

    /**
     * Busca chaves distintas que têm veículos com plano de manutenção
     */
    private function carregarChavesComVeiculos(QueryBuilder $qb): array
    {
        $rows = $qb->withoutChave()
            ->table('veiculos')
            ->select(['chave'])
            ->distinct()
            ->whereNotNull('plano_manutencao_array')
            ->whereRaw("plano_manutencao_array != ''")
            ->orderBy('chave')
            ->get();
        return array_column($rows, 'chave');
    }

    /**
     * Carrega veículos de um tenant específico
     */
    private function carregarVeiculosPorTenant(QueryBuilder $qb, string $chave): array
    {
        return $qb->withoutChave()
            ->table('veiculos')
            ->select(['id', 'chave', 'id_matriz_filial', 'placa', 'odometro', 'id_plano_manutencao', 'plano_manutencao_array'])
            ->where('chave', '=', $chave)
            ->whereNotNull('plano_manutencao_array')
            ->whereRaw("plano_manutencao_array != ''")
            ->get();
    }

    /**
     * Verifica quais itens estão dentro da margem de manutenção
     */
    private function verificarItensPendentes(int $odometroAtual, array $planoVeiculo, array $planoIntervalos): array
    {
        $pendentes = [];

        foreach ($planoVeiculo as $item => $kmProxima) {
            // Verifica se o item está ativado no plano (intervalo > 0)
            $intervalo = $this->parseKm($planoIntervalos[$item] ?? '0');
            if ($intervalo <= 0) {
                continue;
            }

            $kmProximaInt = $this->parseKm($kmProxima);
            $diferenca = $kmProximaInt - $odometroAtual;

            // Dentro da margem (até X km antes) ou já passou
            if ($diferenca <= $this->margemKm) {
                $pendentes[$item] = [
                    'km_proxima' => $kmProximaInt,
                    'km_atual' => $odometroAtual,
                    'diferenca' => $diferenca,
                    'intervalo' => $intervalo,
                    'label' => $this->getLabel($item),
                ];
            }
        }

        return $pendentes;
    }

    /**
     * Busca o label traduzido de um item de manutenção
     */
    private function getLabel(string $itemKey): string
    {
        return t("modules.manutencao.items.{$itemKey}");
    }

    /**
     * Gera a Ordem de Serviço
     *
     * @return array ['id' => int, 'codigo' => string, 'placa' => string]
     */
    private function gerarOS(QueryBuilder $qb, array $veiculo, array $itensPendentes): array
    {
        $servicos = [];
        $itensParaLog = [];

        foreach ($itensPendentes as $item => $dados) {
            $servicos[] = ["0", $dados['label'], "1", "0.00", "0.00"];

            // Monta array detalhado para o log
            $itensParaLog[] = [
                'item' => $item,
                'descricao' => $dados['label'],
                'km_prevista' => $dados['km_proxima'],
                'km_atual' => $dados['km_atual'],
                'diferenca_km' => $dados['diferenca'],
                'proximo_intervalo' => $dados['intervalo'],
            ];
        }

        // Padrão existente: MA + 5 dígitos + id_filial
        $codigo = "MA" . rand(10000, 99999) . $veiculo['id_matriz_filial'];

        $qb->table('manutencoes')->insert([
            'chave' => $veiculo['chave'],
            'os' => $codigo,
            'id_matriz_filial' => $veiculo['id_matriz_filial'],
            'id_veiculo' => $veiculo['id'],
            'odo_enviado' => $veiculo['odometro'],
            'motivo' => t('modules.manutencao.os.reason'),
            'array_servicos' => json_encode($servicos, JSON_UNESCAPED_UNICODE),
            'status' => 'C',
        ]);

        $osId = $qb->getLastInsertId();

        // Log no console do CRON (padrão BaseJob)
        $this->log(t('modules.manutencao.cron.os_generated', [
            'codigo' => $codigo,
            'placa' => $veiculo['placa']
        ]));

        // Log de auditoria via AuditLogService
        // Contexto de sessão já definido em setContextoTenant()
        AuditLogService::registrarComCampos(
            t('modules.manutencao.audit.os_created', [
                'placa' => $veiculo['placa'],
                'codigo' => $codigo
            ]),
            [
                AuditLogService::campo('Itens de Manutenção', null, $itensParaLog),
            ]
        );

        return [
            'id' => $osId,
            'codigo' => $codigo,
            'placa' => $veiculo['placa'],
        ];
    }

    /**
     * Atualiza o JSON do veículo com os próximos km
     */
    private function atualizarPlanoVeiculo(QueryBuilder $qb, array $veiculo, array $itensPendentes, array $planoIntervalos): void
    {
        $planoAtual = json_decode($veiculo['plano_manutencao_array'], true);
        $camposAlterados = [];

        foreach ($itensPendentes as $item => $dados) {
            $kmAnterior = $planoAtual[$item] ?? 0;
            $novoKm = $dados['km_proxima'] + $dados['intervalo'];
            $planoAtual[$item] = number_format($novoKm, 0, '', '.');

            $camposAlterados[] = AuditLogService::campo(
                $item,
                number_format((float) str_replace('.', '', $kmAnterior), 0, '', '.') . ' km',
                number_format($novoKm, 0, '', '.') . ' km',
                'Próxima Manutenção'
            );
        }

        $qb->table('veiculos')
            ->withoutChave()
            ->where('id', '=', $veiculo['id'])
            ->update(['plano_manutencao_array' => json_encode($planoAtual)]);

        // Log de auditoria
        if (!empty($camposAlterados)) {
            AuditLogService::registrarComCampos(
                "Sistema, atualizou plano de manutenção do veículo [{$veiculo['placa']}]",
                $camposAlterados
            );
        }
    }

    /**
     * Busca funcionários do tenant que possuem uma permissão específica
     *
     * @return array Lista de funcionários com id, nome, email, telefone
     */
    private function buscarUsuariosComPermissao(QueryBuilder $qb, string $chave, string $permission): array
    {
        return $qb->withoutChave()
            ->table('funcionarios', 'f')
            ->select(['f.id', 'f.nome', 'f.email', 'f.tel_cel as telefone', 'f.id_matriz_filial'])
            ->distinct()
            ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->innerJoin('funcionarios_role_permissions', 'rp', 'r.id', '=', 'rp.role_id')
            ->innerJoin('permissions', 'p', 'rp.permission_id', '=', 'p.id')
            ->whereRaw('f.chave = ? AND f.status = ? AND p.`key` = ?', [$chave, 'A', $permission])
            ->get();
    }

    /**
     * Notifica usuários do tenant sobre manutenções preventivas criadas
     * Envia para todos os funcionários com permissão 'notificacoes.manutencoes_preventivas'
     */
    private function notificarTenant(QueryBuilder $qb, string $chave): void
    {
        $usuarios = $this->buscarUsuariosComPermissao(
            $qb,
            $chave,
            'notificacoes.manutencoes_preventivas'
        );

        if (empty($usuarios)) {
            $this->log("Nenhum usuário com permissão de notificação no tenant {$chave}");
            return;
        }

        foreach ($usuarios as $usuario) {
            $idFilial = $usuario['id_matriz_filial'] ?? null;

            // Email
            if (!empty($usuario['email'])) {
                $this->queueNotification('email', [
                    'to' => $usuario['email'],
                    'to_name' => $usuario['nome'],
                    'subject' => t('modules.manutencao.cron_notifications.email_subject'),
                    'body' => t('modules.manutencao.cron_notifications.email_body'),
                    'id_matriz_filial' => $idFilial,
                ]);
            }

            // WhatsApp
            if (!empty($usuario['telefone']) && $idFilial) {
                $this->queueNotification('whatsapp', [
                    'to' => $usuario['telefone'],
                    'message' => t('modules.manutencao.cron_notifications.whatsapp_body'),
                    'id_matriz_filial' => $idFilial,
                ]);
            }

            // SMS
            if (!empty($usuario['telefone']) && $idFilial) {
                $this->queueNotification('sms', [
                    'to' => $usuario['telefone'],
                    'message' => t('modules.manutencao.cron_notifications.sms_body'),
                    'id_matriz_filial' => $idFilial,
                ]);
            }
        }

        $this->log("Notificações enviadas para " . count($usuarios) . " usuário(s) do tenant {$chave}");
    }

    /**
     * Enfileira notificacao sem derrubar o cron por falta de contato/configuracao.
     */
    private function queueNotification(string $type, array $payload): void
    {
        try {
            queue_message($type, $payload);
        } catch (\InvalidArgumentException $e) {
            $key = $type . ':' . ($payload['id_matriz_filial'] ?? 'sem-filial') . ':' . $e->getMessage();
            if (!isset($this->notificationSkipLogs[$key])) {
                $this->log("Notificacao {$type} nao enfileirada: " . $e->getMessage(), 'WARNING');
                $this->notificationSkipLogs[$key] = true;
            }
        }
    }

    /**
     * Envia email de resumo da execução do CRON para APP_COMPANY_EMAIL
     */
    private function enviarResumoExecucao(
        int $tenantsProcessados,
        int $veiculosProcessados,
        int $osGeradas,
        array $erros,
        float $tempoExecucao
    ): void {
        $emailDestino = Database::env('APP_COMPANY_EMAIL');
        if (empty($emailDestino)) {
            $this->log('APP_COMPANY_EMAIL não configurado, resumo não enviado', 'WARNING');
            return;
        }

        $temErros = !empty($erros);
        $assunto = $temErros
            ? '[ERRO] Resumo CRON - ' . date('d/m/Y H:i')
            : 'Resumo CRON - ' . date('d/m/Y H:i');

        // Monta corpo do email em HTML
        $corpo = $this->montarCorpoResumo($tenantsProcessados, $veiculosProcessados, $osGeradas, $erros, $tempoExecucao);

        // Envia email diretamente (sem fila, pois é email interno)
        $emailService = new \App\Services\EmailService();
        $resultado = $emailService->send([
            'to' => $emailDestino,
            'to_name' => '7Carros Admin',
            'subject' => $assunto,
            'body' => $corpo,
        ]);

        if ($resultado['success']) {
            $this->log("Email de resumo enviado para {$emailDestino}");
        } else {
            $this->log("Falha ao enviar email de resumo: {$resultado['message']}", 'ERROR');
        }
    }

    /**
     * Monta o corpo HTML do email de resumo (simplificado)
     */
    private function montarCorpoResumo(
        int $tenantsProcessados,
        int $veiculosProcessados,
        int $osGeradas,
        array $erros,
        float $tempoExecucao
    ): string {
        $temErros = !empty($erros);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f3f4f6; color: #1f2937; }
            .container { max-width: 500px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; }
            .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
            .header .date { margin-top: 5px; font-size: 13px; opacity: 0.9; }
            .content { background: white; padding: 20px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .section { margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border-left: 4px solid #3b82f6; }
            .section-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
            .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-item { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
            .stat-item:last-child { border-bottom: none; }
            .stat-label { color: #6b7280; }
            .stat-value { font-weight: 600; color: #1f2937; }
            .error-section { background: #fef2f2; border-left-color: #dc2626; }
            .error-section .section-title { color: #dc2626; }
            .error-item { background: white; padding: 10px; border-radius: 6px; margin-top: 8px; font-size: 12px; }
            .error-item .error-location { color: #6b7280; margin-bottom: 4px; }
            .error-item .error-message { color: #dc2626; font-family: monospace; }
            .footer { text-align: center; margin-top: 15px; font-size: 11px; color: #9ca3af; }
        </style></head><body><div class="container">';

        // Cabeçalho
        $html .= '<div class="header">';
        $html .= '<h1>📊 Resumo de Execução - CRON Jobs</h1>';
        $html .= '<div class="date">' . date('d/m/Y H:i:s') . '</div>';
        $html .= '</div>';

        // Conteúdo
        $html .= '<div class="content">';

        // Seção: Manutenção Preventiva
        $html .= '<div class="section">';
        $html .= '<div class="section-title">🔧 Manutenção Preventiva</div>';
        $html .= '<div class="stat-item"><span class="stat-label">Tenants processados</span><span class="stat-value">' . number_format($tenantsProcessados, 0, ',', '.') . '</span></div>';
        $html .= '<div class="stat-item"><span class="stat-label">Veículos analisados</span><span class="stat-value">' . number_format($veiculosProcessados, 0, ',', '.') . '</span></div>';
        $html .= '<div class="stat-item"><span class="stat-label">OS criadas</span><span class="stat-value">' . number_format($osGeradas, 0, ',', '.') . '</span></div>';
        $html .= '<div class="stat-item"><span class="stat-label">Tempo de execução</span><span class="stat-value">' . number_format($tempoExecucao, 2, ',', '.') . 's</span></div>';
        $html .= '</div>';

        // Seção: Erros (apenas se houver)
        if ($temErros) {
            $html .= '<div class="section error-section">';
            $html .= '<div class="section-title">⚠️ Erros Encontrados (' . count($erros) . ')</div>';
            foreach ($erros as $erro) {
                $html .= '<div class="error-item">';
                $html .= '<div class="error-location">Tenant: ' . htmlspecialchars(substr($erro['tenant'], 0, 12)) . '... | Placa: ' . htmlspecialchars($erro['placa']) . '</div>';
                $html .= '<div class="error-message">' . htmlspecialchars($erro['erro']) . '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Footer
        $html .= '<div class="footer">7Carros Locadora - Sistema de Gestão</div>';

        $html .= '</div></div></body></html>';

        return $html;
    }

    /**
     * Converte string de km para inteiro
     * "10.000" -> 10000
     * "10000" -> 10000
     */
    private function parseKm(string|int $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }
        return (int) str_replace(['.', ','], '', $valor);
    }
}
