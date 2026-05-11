<?php

namespace App\Crons\Jobs;

use App\Models\NFSeConfiguracao;
use App\Models\NFSe as NFSeModel;
use App\Services\NFSe\NFSeService;

/**
 * Job de emissao automatica de NFS-e
 *
 * Busca lancamentos financeiros pagos que ainda nao possuem NFS-e
 * e emite automaticamente para tenants com emissao_auto = 'S'.
 *
 * Limite: 50 emissoes por execucao
 * Frequencia: a cada 5 minutos
 */
class NFSeEmitirAutoJob extends BaseJob
{
    protected string $name = 'NFSe Emissao Automatica';
    protected string $description = 'Emite NFS-e automaticamente para pagamentos confirmados';

    private const MAX_POR_EXECUCAO = 50;

    protected function handle(): array
    {
        $this->log('Iniciando emissao automatica de NFS-e...');

        $configModel = new NFSeConfiguracao();
        $configs = $configModel->listarAtivasParaCron();

        if (empty($configs)) {
            $this->log('Nenhum tenant com emissao automatica ativa.');
            return [
                'success' => true,
                'message' => 'Nenhum tenant com emissao automatica',
                'data' => ['emitidas' => 0],
            ];
        }

        $this->log(count($configs) . ' tenant(s) com emissao automatica.');

        $totalEmitidas = 0;
        $totalErros = 0;

        foreach ($configs as $config) {
            if ($totalEmitidas >= self::MAX_POR_EXECUCAO) {
                $this->log('Limite de emissoes por execucao atingido (' . self::MAX_POR_EXECUCAO . ').');
                break;
            }

            $chave = $config['chave'];
            $idMatrizFilial = (int) $config['id_matriz_filial'];

            // Definir sessao do tenant
            $_SESSION['chave'] = $chave;

            try {
                $nfseModel = new NFSeModel();
                $financeiros = $nfseModel->buscarFinanceirosParaEmissaoAuto($chave, $idMatrizFilial);

                if (empty($financeiros)) {
                    continue;
                }

                $this->log("Tenant {$chave}: " . count($financeiros) . " financeiro(s) pendentes.");

                $service = new NFSeService();

                foreach ($financeiros as $fin) {
                    if ($totalEmitidas >= self::MAX_POR_EXECUCAO) {
                        break;
                    }

                    try {
                        $resultado = $service->emitir((int) $fin['id'], $chave);

                        if ($resultado['sucesso'] ?? false) {
                            $totalEmitidas++;
                            $this->log("Tenant {$chave}: NFS-e emitida para financeiro #{$fin['id']}");
                        } else {
                            $totalErros++;
                            $msg = $resultado['mensagem'] ?? 'Erro desconhecido';
                            $this->log("Tenant {$chave}: Erro financeiro #{$fin['id']}: {$msg}", 'WARNING');
                        }
                    } catch (\Exception $e) {
                        $totalErros++;
                        $this->log("Tenant {$chave}: Excecao financeiro #{$fin['id']}: " . $e->getMessage(), 'ERROR');
                    }
                }
            } catch (\Exception $e) {
                $totalErros++;
                $this->log("Tenant {$chave}: ERRO - " . $e->getMessage(), 'ERROR');
            }
        }

        $mensagem = "Emissao automatica finalizada. {$totalEmitidas} emitidas, {$totalErros} erros.";
        $this->log($mensagem);

        return [
            'success' => $totalErros === 0,
            'message' => $mensagem,
            'data' => [
                'emitidas' => $totalEmitidas,
                'erros' => $totalErros,
            ],
        ];
    }
}
