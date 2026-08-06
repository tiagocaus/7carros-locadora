<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Helpers\CodigoHelper;
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
                    $mysqli->begin_transaction();

                    try {
                        // Gera OS
                        $osInfo = $this->gerarOS($qb, $veiculo, $itensPendentes);

                        // Atualiza próximos km no JSON
                        $this->atualizarPlanoVeiculo($qb, $veiculo, $itensPendentes, $planoIntervalos);

                        $mysqli->commit();

                        $osCriadas[] = $osInfo;
                        $osGeradas++;
                        $osGeradasNoTenant++;
                    } catch (\Throwable $e) {
                        $mysqli->rollback();

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

        return [
            'success' => empty($erros),
            'status' => empty($erros)
                ? self::STATUS_SUCCESS
                : ($veiculosProcessados > count($erros) ? self::STATUS_PARTIAL : self::STATUS_FAILED),
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
                'erros' => $erros,
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
        return $qb
            ->table('veiculos')
            ->withChave($chave)
            ->select(['id', 'chave', 'id_matriz_filial', 'placa', 'odometro', 'id_plano_manutencao', 'plano_manutencao_array'])
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
        $ordem = 1;

        foreach ($itensPendentes as $item => $dados) {
            $servicos[] = [(string) $ordem, $dados['label'], "1", "0.00", "0.00"];

            // Monta array detalhado para o log
            $itensParaLog[] = [
                'item' => $item,
                'descricao' => $dados['label'],
                'km_prevista' => $dados['km_proxima'],
                'km_atual' => $dados['km_atual'],
                'diferenca_km' => $dados['diferenca'],
                'proximo_intervalo' => $dados['intervalo'],
            ];

            $ordem++;
        }

        $codigo = $this->gerarCodigoOs($qb, (string) $veiculo['chave']);

        $osId = $qb->table('manutencoes')->insert([
            'chave' => $veiculo['chave'],
            'os' => $codigo,
            'id_matriz_filial' => $veiculo['id_matriz_filial'],
            'id_veiculo' => $veiculo['id'],
            'odo_enviado' => $veiculo['odometro'],
            'motivo' => t('modules.manutencao.os.reason'),
            'array_servicos' => json_encode($servicos, JSON_UNESCAPED_UNICODE),
            'status' => 'C',
        ]);

        $this->criarItensOS($qb, $osId, (string) $veiculo['chave'], $itensPendentes);

        // Log no console do CRON (padrão BaseJob)
        $this->log(t('modules.manutencao.cron.os_generated', [
            'codigo' => $codigo,
            'código' => $codigo,
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

    private function criarItensOS(QueryBuilder $qb, int $osId, string $chave, array $itensPendentes): void
    {
        $ordem = 1;

        foreach ($itensPendentes as $dados) {
            $qb->table('manutencoes_itens')->insert([
                'chave' => $chave,
                'id_manutencao' => $osId,
                'id_estoque' => null,
                'descricao' => $dados['label'],
                'quantidade' => 1,
                'valor_unitario' => 0,
                'desconto' => 0,
                'valor_total' => 0,
                'pago' => 'N',
                'ordem' => $ordem,
            ]);

            $ordem++;
        }
    }

    private function gerarCodigoOs(QueryBuilder $qb, string $chave): string
    {
        for ($tentativa = 0; $tentativa < 20; $tentativa++) {
            $codigo = CodigoHelper::gerarComPrefixo('MA');

            $existente = $qb
                ->table('manutencoes')
                ->withChave($chave)
                ->where('os', '=', $codigo)
                ->first();

            if (!$existente) {
                return $codigo;
            }
        }

        throw new \RuntimeException('Nao foi possivel gerar um codigo de OS unico');
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
            ->withChave((string) $veiculo['chave'])
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
        return $qb
            ->table('funcionarios', 'f')
            ->withChave($chave)
            ->select(['f.id', 'f.nome', 'f.email', 'f.tel_cel as telefone', 'f.id_matriz_filial'])
            ->distinct()
            ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->innerJoin('funcionarios_role_permissions', 'rp', 'r.id', '=', 'rp.role_id')
            ->innerJoin('permissions', 'p', 'rp.permission_id', '=', 'p.id')
            ->whereRaw('f.status = ? AND p.`key` = ?', ['A', $permission])
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
