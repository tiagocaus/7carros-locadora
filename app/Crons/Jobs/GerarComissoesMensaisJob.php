<?php

namespace App\Crons\Jobs;

use App\Services\ComissaoInvestidorService;
use App\Core\Database;

/**
 * Job CRON para geracao de comissoes mensais de investidores
 *
 * Deve ser executado no 1o dia de cada mes para gerar comissoes
 * do tipo fixo_locadora_mensal e fixo_investidor_mensal.
 */
class GerarComissoesMensaisJob extends BaseJob
{
    protected string $name = 'GerarComissoesMensais';
    protected string $description = 'Gera comissoes mensais para veiculos de investidores';

    /**
     * Executa a geracao de comissoes mensais
     *
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    protected function handle(): array
    {
        // Verificar se esta habilitado
        if (Database::env('COMISSOES_CRON_ENABLED', 'true') !== 'true') {
            $this->log('Job de comissoes mensais desabilitado via COMISSOES_CRON_ENABLED');
            return [
                'success' => true,
                'message' => 'Job desabilitado',
                'data' => []
            ];
        }

        // Determinar mes de referencia (mes anterior ao atual)
        // Ex: Se executado em 01/02/2024, gera comissoes de 01/2024
        $mesReferencia = date('Y-m', strtotime('first day of last month'));

        // Verificar se ja foi executado este mes (protecao extra)
        $mesAtual = date('Y-m');
        $ultimaExecucao = $this->getUltimaExecucao();

        if ($ultimaExecucao === $mesAtual) {
            $this->log("Comissoes do mes {$mesReferencia} ja foram geradas neste periodo");
            return [
                'success' => true,
                'message' => 'Comissoes ja geradas para este periodo',
                'data' => ['mes_referencia' => $mesReferencia]
            ];
        }

        $this->log("Iniciando geracao de comissoes mensais para {$mesReferencia}");

        try {
            $service = new ComissaoInvestidorService();
            $resultado = $service->gerarComissoesMensais($mesReferencia);

            // Registrar execucao
            $this->registrarExecucao($mesAtual);

            // Log detalhado
            $this->log("Veiculos analisados: {$resultado['total_veiculos']}");
            $this->log("Comissoes geradas: {$resultado['comissoes_geradas']}");
            $this->log("Comissoes ignoradas (ja existentes): {$resultado['comissoes_ignoradas']}");

            if (!empty($resultado['erros'])) {
                foreach ($resultado['erros'] as $erro) {
                    $this->log($erro, 'ERROR');
                }
            }

            // Enviar email de resumo
            $this->enviarResumo($resultado);

            $mensagem = sprintf(
                'Comissoes mensais geradas: %d de %d veiculos processados',
                $resultado['comissoes_geradas'],
                $resultado['total_veiculos']
            );

            return [
                'success' => empty($resultado['erros']),
                'message' => $mensagem,
                'data' => $resultado
            ];

        } catch (\Exception $e) {
            $this->log('Erro ao gerar comissoes: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Retorna o mes da ultima execucao bem-sucedida
     */
    private function getUltimaExecucao(): ?string
    {
        $stateFile = dirname(__DIR__, 3) . '/storage/cron/comissoes-mensais-state.json';

        if (!file_exists($stateFile)) {
            return null;
        }

        $state = json_decode(file_get_contents($stateFile), true);
        return $state['ultima_execucao'] ?? null;
    }

    /**
     * Registra a execucao do job
     */
    private function registrarExecucao(string $mesExecucao): void
    {
        $stateDir = dirname(__DIR__, 3) . '/storage/cron';
        $stateFile = $stateDir . '/comissoes-mensais-state.json';

        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }

        $state = [
            'ultima_execucao' => $mesExecucao,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    /**
     * Envia email de resumo da execucao
     */
    private function enviarResumo(array $resultado): void
    {
        $emailDestino = Database::env('APP_COMPANY_EMAIL');
        if (empty($emailDestino)) {
            $this->log('APP_COMPANY_EMAIL nao configurado, resumo nao enviado', 'WARNING');
            return;
        }

        $temErros = !empty($resultado['erros']);
        $assunto = $temErros
            ? '[ATENCAO] Comissoes Mensais - ' . $resultado['mes_referencia']
            : 'Comissoes Mensais Geradas - ' . $resultado['mes_referencia'];

        $corpo = $this->montarCorpoEmail($resultado);

        try {
            $emailService = new \App\Services\EmailService();
            $envio = $emailService->send([
                'to' => $emailDestino,
                'to_name' => '7Carros Admin',
                'subject' => $assunto,
                'body' => $corpo,
            ]);

            if ($envio['success']) {
                $this->log("Email de resumo enviado para {$emailDestino}");
            } else {
                $this->log("Falha ao enviar email: {$envio['message']}", 'WARNING');
            }
        } catch (\Exception $e) {
            $this->log("Erro ao enviar email: {$e->getMessage()}", 'WARNING');
        }
    }

    /**
     * Monta o corpo HTML do email
     */
    private function montarCorpoEmail(array $resultado): string
    {
        $temErros = !empty($resultado['erros']);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f3f4f6; color: #1f2937; }
            .container { max-width: 500px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; }
            .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
            .header .date { margin-top: 5px; font-size: 13px; opacity: 0.9; }
            .content { background: white; padding: 20px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .section { margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border-left: 4px solid #10b981; }
            .stat-item { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
            .stat-item:last-child { border-bottom: none; }
            .stat-label { color: #6b7280; }
            .stat-value { font-weight: 600; color: #1f2937; }
            .error-section { background: #fef2f2; border-left-color: #dc2626; }
            .error-item { background: white; padding: 8px; border-radius: 6px; margin-top: 8px; font-size: 12px; color: #dc2626; }
            .footer { text-align: center; margin-top: 15px; font-size: 11px; color: #9ca3af; }
        </style></head><body><div class="container">';

        $html .= '<div class="header">';
        $html .= '<h1>💰 Comissoes Mensais de Investidores</h1>';
        $html .= '<div class="date">Referencia: ' . $resultado['mes_referencia'] . '</div>';
        $html .= '</div>';

        $html .= '<div class="content">';
        $html .= '<div class="section">';
        $html .= '<div class="stat-item"><span class="stat-label">Veiculos analisados</span><span class="stat-value">' . $resultado['total_veiculos'] . '</span></div>';
        $html .= '<div class="stat-item"><span class="stat-label">Comissoes geradas</span><span class="stat-value">' . $resultado['comissoes_geradas'] . '</span></div>';
        $html .= '<div class="stat-item"><span class="stat-label">Ja existentes (ignoradas)</span><span class="stat-value">' . $resultado['comissoes_ignoradas'] . '</span></div>';
        $html .= '</div>';

        if ($temErros) {
            $html .= '<div class="section error-section">';
            $html .= '<strong>Erros (' . count($resultado['erros']) . '):</strong>';
            foreach ($resultado['erros'] as $erro) {
                $html .= '<div class="error-item">' . htmlspecialchars($erro) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="footer">7Carros Locadora - Sistema de Gestao</div>';
        $html .= '</div></div></body></html>';

        return $html;
    }
}
