<?php

namespace App\Crons\Jobs;

use App\Models\NFSe as NFSeModel;
use App\Services\NFSe\NFSeService;

/**
 * Consulta protocolos Betha que seguem em processamento.
 */
class NFSeConsultarBethaJob extends BaseJob
{
    protected string $name = 'NFSe Consulta Betha';
    protected string $description = 'Consulta status de DPS Betha em processamento';

    private const MAX_POR_EXECUCAO = 20;

    protected function handle(): array
    {
        $this->log('Iniciando consulta de protocolos Betha...');

        $nfseModel = new NFSeModel();
        $pendentes = $nfseModel->buscarBethaProcessando(self::MAX_POR_EXECUCAO);

        if (empty($pendentes)) {
            $this->log('Nenhuma NFS-e Betha em processamento encontrada.');
            return [
                'success' => true,
                'message' => 'Nenhuma Betha pendente',
                'data' => ['consultadas' => 0],
            ];
        }

        $totalConsultadas = 0;
        $totalErros = 0;

        foreach ($pendentes as $nfse) {
            $chave = $nfse['chave'];
            $idNfse = (int) $nfse['id'];
            $_SESSION['chave'] = $chave;

            try {
                $service = new NFSeService();
                $resultado = $service->consultarBethaProcessando($idNfse, $chave);

                if ($resultado['sucesso'] ?? false) {
                    $totalConsultadas++;
                    $this->log("NFS-e Betha #{$idNfse} (tenant {$chave}): " . ($resultado['mensagem'] ?? 'consultada'));
                } else {
                    $totalErros++;
                    $this->log("NFS-e Betha #{$idNfse} (tenant {$chave}): falha - " . ($resultado['mensagem'] ?? 'erro desconhecido'), 'WARNING');
                }
            } catch (\Exception $e) {
                $totalErros++;
                $this->log("NFS-e Betha #{$idNfse} (tenant {$chave}): excecao - " . $e->getMessage(), 'ERROR');
            }
        }

        $mensagem = "Consulta Betha finalizada. {$totalConsultadas} consultadas, {$totalErros} erros.";
        $this->log($mensagem);

        return [
            'success' => $totalErros === 0,
            'message' => $mensagem,
            'data' => [
                'consultadas' => $totalConsultadas,
                'erros' => $totalErros,
            ],
        ];
    }
}
