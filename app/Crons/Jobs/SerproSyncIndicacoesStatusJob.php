<?php

namespace App\Crons\Jobs;

use App\Models\SerproIndicacao;
use App\Services\SerproIndicacaoStatusService;

/**
 * Sincroniza status de indicacoes de condutor enviadas ao sistema de consultas online.
 */
class SerproSyncIndicacoesStatusJob extends BaseJob
{
    protected string $name = 'SerproSyncIndicacoesStatus';
    protected string $description = 'Sincroniza status de indicacoes de condutor pendentes';

    protected function handle(): array
    {
        $model = new SerproIndicacao();
        $indicacoes = $model->listarParaSincronizarStatus(100);

        if (empty($indicacoes)) {
            $this->log('Nenhuma indicacao pendente de sincronizacao.');
            return [
                'success' => true,
                'message' => 'Nenhuma indicacao pendente de sincronizacao',
                'data' => ['processadas' => 0],
            ];
        }

        $service = new SerproIndicacaoStatusService();
        $processadas = 0;
        $atualizadas = 0;
        $erros = 0;
        $porStatus = [];

        foreach ($indicacoes as $indicacao) {
            $chave = (string) ($indicacao['chave'] ?? '');

            if ($chave === '') {
                $erros++;
                $this->log("Indicacao {$indicacao['id']}: chave do tenant ausente", 'WARNING');
                continue;
            }

            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }

            $_SESSION['chave'] = $chave;

            try {
                $resultado = $service->sincronizar($indicacao);
                $processadas++;

                if ($resultado['success']) {
                    $status = (string) ($resultado['data']['status_local'] ?? $indicacao['status_serpro']);
                    $porStatus[$status] = ($porStatus[$status] ?? 0) + 1;
                    $atualizadas++;
                    $this->log("Tenant {$chave}, indicacao {$indicacao['id']}: status {$status}");
                } else {
                    $erros++;
                    $this->log(
                        "Tenant {$chave}, indicacao {$indicacao['id']}: " . ($resultado['message'] ?? 'erro desconhecido'),
                        'WARNING'
                    );
                }

                usleep(100000);
            } catch (\Exception $e) {
                $erros++;
                $this->log("Tenant {$chave}, indicacao {$indicacao['id']}: " . $e->getMessage(), 'WARNING');
            } finally {
                unset($_SESSION['chave']);
            }
        }

        $mensagem = "Sincronizacao de indicacoes finalizada. {$processadas} processadas, {$atualizadas} atualizadas, {$erros} erro(s).";
        $this->log($mensagem);

        return [
            'success' => $erros === 0,
            'message' => $mensagem,
            'data' => [
                'processadas' => $processadas,
                'atualizadas' => $atualizadas,
                'erros' => $erros,
                'por_status' => $porStatus,
            ],
        ];
    }
}
