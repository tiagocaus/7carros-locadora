<?php

namespace App\Crons\Jobs;

use App\Models\NFSe as NFSeModel;
use App\Services\NFSe\NFSeService;

/**
 * Job de envio automatico de email com NFS-e
 *
 * Busca NFS-e autorizadas que ainda nao tiveram email enviado
 * e envia PDF por email ao tomador.
 *
 * Limite: 30 envios por execucao
 * Frequencia: a cada 5 minutos
 */
class NFSeEnviarEmailJob extends BaseJob
{
    protected string $name = 'NFSe Envio de Email';
    protected string $description = 'Envia PDF de NFS-e autorizada por email ao tomador';

    private const MAX_POR_EXECUCAO = 30;

    protected function handle(): array
    {
        $this->log('Iniciando envio automatico de emails de NFS-e...');

        $nfseModel = new NFSeModel();
        $pendentes = $nfseModel->buscarPendentesEmail();

        if (empty($pendentes)) {
            $this->log('Nenhuma NFS-e pendente de envio de email.');
            return [
                'success' => true,
                'message' => 'Nenhum email pendente',
                'data' => ['enviados' => 0],
            ];
        }

        $this->log(count($pendentes) . ' NFS-e pendente(s) de envio de email.');

        $totalEnviados = 0;
        $totalErros = 0;

        foreach ($pendentes as $nfse) {
            if ($totalEnviados >= self::MAX_POR_EXECUCAO) {
                $this->log('Limite de envios por execucao atingido (' . self::MAX_POR_EXECUCAO . ').');
                break;
            }

            $chave = $nfse['chave'];
            $idNfse = (int) $nfse['id'];

            // Definir sessao do tenant
            $_SESSION['chave'] = $chave;

            try {
                $service = new NFSeService();
                $resultado = $service->enviarPorEmail($idNfse, $chave);

                if ($resultado['sucesso'] ?? false) {
                    $totalEnviados++;
                    $this->log("NFS-e #{$idNfse} (tenant {$chave}): email enviado para {$nfse['tomador_email']}");
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

        $mensagem = "Envio de emails finalizado. {$totalEnviados} enviados, {$totalErros} erros.";
        $this->log($mensagem);

        return [
            'success' => $totalErros === 0,
            'message' => $mensagem,
            'data' => [
                'enviados' => $totalEnviados,
                'erros' => $totalErros,
            ],
        ];
    }
}
