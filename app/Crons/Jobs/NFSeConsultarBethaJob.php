<?php

namespace App\Crons\Jobs;

use App\Models\NFSe as NFSeModel;
use App\Services\NFSe\NFSeService;

/**
 * Consulta protocolos Betha e reconcilia a situacao fiscal no ADN.
 */
class NFSeConsultarBethaJob extends BaseJob
{
    protected string $name = 'NFSe Consulta Betha';
    protected string $description = 'Consulta protocolos Betha e eventos fiscais no ADN';

    private const MAX_POR_EXECUCAO = 20;

    protected function handle(): array
    {
        $this->log('Iniciando consulta de protocolos Betha...');

        $nfseModel = new NFSeModel();
        $pendentes = $nfseModel->buscarBethaProcessando(self::MAX_POR_EXECUCAO);
        $cancelamentosPendentes = $nfseModel->buscarBethaCancelamentosProcessando(self::MAX_POR_EXECUCAO);
        $situacoesFiscaisPendentes = $nfseModel->buscarBethaSituacaoFiscalPendente(self::MAX_POR_EXECUCAO);

        if (empty($pendentes) && empty($cancelamentosPendentes) && empty($situacoesFiscaisPendentes)) {
            $this->log('Nenhuma NFS-e Betha pendente de consulta encontrada.');
            return [
                'success' => true,
                'message' => 'Nenhuma Betha pendente',
                'data' => ['consultadas' => 0, 'cancelamentos_consultados' => 0, 'situacoes_fiscais_consultadas' => 0],
            ];
        }

        $totalConsultadas = 0;
        $totalCancelamentosConsultados = 0;
        $totalSituacoesFiscaisConsultadas = 0;
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

        foreach ($cancelamentosPendentes as $nfse) {
            $chave = $nfse['chave'];
            $idNfse = (int) $nfse['id'];
            $_SESSION['chave'] = $chave;

            try {
                $service = new NFSeService();
                $resultado = $service->consultarBethaCancelamentoProcessando($idNfse, $chave);

                if ($resultado['sucesso'] ?? false) {
                    $totalCancelamentosConsultados++;
                    $this->log("Cancelamento Betha #{$idNfse} (tenant {$chave}): " . ($resultado['mensagem'] ?? 'consultado'));
                } else {
                    $totalErros++;
                    $this->log("Cancelamento Betha #{$idNfse} (tenant {$chave}): falha - " . ($resultado['mensagem'] ?? 'erro desconhecido'), 'WARNING');
                }
            } catch (\Exception $e) {
                $totalErros++;
                $this->log("Cancelamento Betha #{$idNfse} (tenant {$chave}): excecao - " . $e->getMessage(), 'ERROR');
            }
        }

        foreach ($situacoesFiscaisPendentes as $nfse) {
            $chave = $nfse['chave'];
            $idNfse = (int) $nfse['id'];
            $_SESSION['chave'] = $chave;

            try {
                $service = new NFSeService();
                $resultado = $service->consultarSituacaoFiscalBetha($idNfse, $chave);

                if ($resultado['sucesso'] ?? false) {
                    $totalSituacoesFiscaisConsultadas++;
                    $this->log("Situação fiscal Betha #{$idNfse} (tenant {$chave}): " . ($resultado['mensagem'] ?? 'consultada'));
                } else {
                    $totalErros++;
                    $this->log("Situação fiscal Betha #{$idNfse} (tenant {$chave}): falha - " . ($resultado['mensagem'] ?? 'erro desconhecido'), 'WARNING');
                }
            } catch (\Exception $e) {
                $totalErros++;
                $this->log("Situação fiscal Betha #{$idNfse} (tenant {$chave}): exceção - " . $e->getMessage(), 'ERROR');
            }
        }

        $mensagem = "Consulta Betha finalizada. {$totalConsultadas} emissões, {$totalCancelamentosConsultados} cancelamentos e {$totalSituacoesFiscaisConsultadas} situações fiscais consultados, {$totalErros} erros.";
        $this->log($mensagem);

        return [
            'success' => $totalErros === 0,
            'message' => $mensagem,
            'data' => [
                'consultadas' => $totalConsultadas,
                'cancelamentos_consultados' => $totalCancelamentosConsultados,
                'situacoes_fiscais_consultadas' => $totalSituacoesFiscaisConsultadas,
                'erros' => $totalErros,
            ],
        ];
    }
}
