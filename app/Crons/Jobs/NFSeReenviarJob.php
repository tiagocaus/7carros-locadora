<?php

namespace App\Crons\Jobs;

use App\Models\NFSe as NFSeModel;
use App\Services\NFSe\NFSeService;
use App\Services\NFSe\NFSeErros;

/**
 * Job de reenvio automatico de NFS-e rejeitadas
 *
 * Busca NFS-e com status 'rejeitada' cujo erro seja recuperavel
 * e reenvia automaticamente (max 3 tentativas).
 *
 * Limite: 20 reenvios por execucao
 * Frequencia: a cada 5 minutos
 */
class NFSeReenviarJob extends BaseJob
{
    protected string $name = 'NFSe Reenvio Automatico';
    protected string $description = 'Reenvia NFS-e rejeitadas com erro recuperavel';

    private const MAX_POR_EXECUCAO = 20;

    protected function handle(): array
    {
        $this->log('Iniciando reenvio automatico de NFS-e...');

        $nfseModel = new NFSeModel();
        $rejeitadas = $nfseModel->buscarRejeitadasRecuperaveis();

        if (empty($rejeitadas)) {
            $this->log('Nenhuma NFS-e rejeitada recuperavel encontrada.');
            return [
                'success' => true,
                'message' => 'Nenhuma NFS-e para reenviar',
                'data' => ['reenviadas' => 0],
            ];
        }

        $this->log(count($rejeitadas) . ' NFS-e rejeitada(s) recuperavel(is).');

        $totalReenviadas = 0;
        $totalErros = 0;

        foreach ($rejeitadas as $nfse) {
            if ($totalReenviadas >= self::MAX_POR_EXECUCAO) {
                $this->log('Limite de reenvios por execucao atingido (' . self::MAX_POR_EXECUCAO . ').');
                break;
            }

            $chave = $nfse['chave'];
            $idNfse = (int) $nfse['id'];

            // Definir sessao do tenant
            $_SESSION['chave'] = $chave;

            try {
                $service = new NFSeService();
                $resultado = $service->reenviar($idNfse, $chave);

                if ($resultado['sucesso'] ?? false) {
                    $totalReenviadas++;
                    $this->log("NFS-e #{$idNfse} (tenant {$chave}): reenviada com sucesso");
                } else {
                    $totalErros++;
                    $msg = $resultado['mensagem'] ?? 'Erro desconhecido';
                    $this->log("NFS-e #{$idNfse} (tenant {$chave}): falha - {$msg}", 'WARNING');
                }
            } catch (\Exception $e) {
                $totalErros++;
                $this->log("NFS-e #{$idNfse} (tenant {$chave}): excecao - " . $e->getMessage(), 'ERROR');
            }
        }

        $mensagem = "Reenvio finalizado. {$totalReenviadas} reenviadas, {$totalErros} erros.";
        $this->log($mensagem);

        return [
            'success' => $totalErros === 0,
            'message' => $mensagem,
            'data' => [
                'reenviadas' => $totalReenviadas,
                'erros' => $totalErros,
            ],
        ];
    }
}
