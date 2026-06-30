<?php

namespace App\Crons\Jobs;

use App\Models\SerproTransacao;
use App\Services\Gateways\InterGateway;
use App\Services\SerproSaldoService;

/**
 * Reconcilia recargas PIX pendentes com o Banco Inter.
 */
class SerproPixReconcileJob extends BaseJob
{
    protected string $name = 'SerproPixReconcile';
    protected string $description = 'Confirma recargas PIX pendentes consultando cobrancas Banco Inter';

    protected function handle(): array
    {
        $this->log('Iniciando reconciliacao de recargas PIX Banco Inter...');

        $transacaoModel = new SerproTransacao();
        $pendentes = $transacaoModel->listarRecargasPixPendentesParaReconciliacao(50);

        if (empty($pendentes)) {
            $this->log('Nenhuma recarga PIX pendente para reconciliar.');
            return [
                'success' => true,
                'message' => 'Nenhuma recarga PIX pendente',
                'data' => [
                    'verificadas' => 0,
                    'confirmadas' => 0,
                    'erros' => 0,
                ],
            ];
        }

        $gateway = $this->criarInterGateway();
        $saldoService = new SerproSaldoService();
        $verificadas = 0;
        $confirmadas = 0;
        $erros = 0;
        $detalhes = [];

        foreach ($pendentes as $transacao) {
            $verificadas++;
            $id = (int) $transacao['id'];
            $chave = (string) $transacao['chave'];
            $codigoSolicitacao = (string) $transacao['external_id'];

            try {
                $status = $gateway->getCobrancaV3ChargeStatus($codigoSolicitacao);

                if (empty($status['success'])) {
                    $erros++;
                    $mensagem = $status['message'] ?? 'Falha ao consultar cobranca no Banco Inter';
                    $this->log("Recarga {$id} ({$chave}): {$mensagem}", 'WARNING');
                    $detalhes[] = [
                        'id' => $id,
                        'chave' => $chave,
                        'status' => 'erro',
                        'message' => $mensagem,
                    ];
                    continue;
                }

                $gatewayStatus = (string) ($status['gateway_status'] ?? $status['status'] ?? 'pending');
                if (($status['status'] ?? '') !== 'paid') {
                    $this->log("Recarga {$id} ({$chave}): ainda pendente no Inter ({$gatewayStatus}).");
                    $detalhes[] = [
                        'id' => $id,
                        'chave' => $chave,
                        'status' => $status['status'] ?? 'pending',
                        'gateway_status' => $gatewayStatus,
                    ];
                    continue;
                }

                $saldos = $saldoService->confirmarRecarga($id, $chave);
                $confirmadas++;
                $this->log("Recarga {$id} ({$chave}) confirmada. Saldo: {$saldos['saldo_anterior']} -> {$saldos['saldo_posterior']}.");
                $detalhes[] = [
                    'id' => $id,
                    'chave' => $chave,
                    'status' => 'confirmado',
                    'saldo_anterior' => $saldos['saldo_anterior'],
                    'saldo_posterior' => $saldos['saldo_posterior'],
                    'paid_at' => $status['paid_at'] ?? null,
                ];
            } catch (\Throwable $e) {
                $erros++;
                $this->log("Recarga {$id} ({$chave}): " . $e->getMessage(), 'ERROR');
                $detalhes[] = [
                    'id' => $id,
                    'chave' => $chave,
                    'status' => 'erro',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $mensagem = "Reconciliacao PIX finalizada. {$verificadas} verificadas, {$confirmadas} confirmadas, {$erros} erro(s).";
        $this->log($mensagem);

        return [
            'success' => $erros === 0,
            'message' => $mensagem,
            'data' => [
                'verificadas' => $verificadas,
                'confirmadas' => $confirmadas,
                'erros' => $erros,
                'detalhes' => $detalhes,
            ],
        ];
    }

    private function criarInterGateway(): InterGateway
    {
        $certPath = env('INTER_CERT_PATH', '');
        $keyPath = env('INTER_KEY_PATH', '');
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);

        if ($certPath && !str_starts_with($certPath, '/')) {
            $certPath = $root . '/' . $certPath;
        }
        if ($keyPath && !str_starts_with($keyPath, '/')) {
            $keyPath = $root . '/' . $keyPath;
        }

        return new InterGateway(
            [
                'client_id' => env('INTER_CLIENT_ID', ''),
                'client_secret' => env('INTER_CLIENT_SECRET', ''),
                'certificate_path' => $certPath,
                'private_key_path' => $keyPath,
                'pix_key' => env('INTER_PIX_KEY', ''),
                'conta_corrente' => env('INTER_CONTA_CORRENTE', ''),
            ],
            env('INTER_AMBIENTE', 'sandbox') === 'sandbox'
        );
    }
}
