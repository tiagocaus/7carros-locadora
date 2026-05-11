<?php

namespace App\Crons\Jobs;

use App\Models\Gravacao;

/**
 * Job para limpar gravacoes de tela antigas
 *
 * Remove gravacoes com mais de 30 dias para liberar espaco em disco
 */
class CleanupOldRecordingsJob extends BaseJob
{
    protected string $name = 'Cleanup Old Recordings';
    protected string $description = 'Remove gravacoes de tela com mais de 30 dias';

    private const UPLOAD_DIR = '/storage/uploads/';
    private const RETENTION_DAYS = 30;

    /**
     * Executa a limpeza de gravacoes antigas
     */
    protected function handle(): array
    {
        $this->log("Iniciando limpeza de gravacoes antigas (retencao: " . self::RETENTION_DAYS . " dias)");

        $model = new Gravacao();
        $gravacoesAntigas = $model->listarAntigas(self::RETENTION_DAYS);

        $totalEncontradas = count($gravacoesAntigas);
        $this->log("Encontradas {$totalEncontradas} gravacoes para remover");

        if ($totalEncontradas === 0) {
            return [
                'success' => true,
                'message' => 'Nenhuma gravacao antiga encontrada',
                'data' => [
                    'encontradas' => 0,
                    'removidas' => 0,
                    'erros' => 0,
                ],
            ];
        }

        $removidas = 0;
        $erros = 0;

        foreach ($gravacoesAntigas as $gravacao) {
            try {
                // Deletar arquivo fisico
                $filepath = $this->getFilePath($gravacao['chave'], $gravacao['arquivo']);

                if (file_exists($filepath)) {
                    if (@unlink($filepath)) {
                        $this->log("Arquivo removido: {$gravacao['arquivo']} (tenant: {$gravacao['chave']})");
                    } else {
                        $this->log("Falha ao remover arquivo: {$gravacao['arquivo']}", 'WARNING');
                    }
                } else {
                    $this->log("Arquivo nao encontrado: {$gravacao['arquivo']}", 'WARNING');
                }

                // Deletar registro do banco
                if ($model->deletarPorIdSemChave($gravacao['id'])) {
                    $removidas++;
                    $this->log("Registro removido: ID {$gravacao['id']}");
                } else {
                    $erros++;
                    $this->log("Falha ao remover registro: ID {$gravacao['id']}", 'ERROR');
                }

            } catch (\Exception $e) {
                $erros++;
                $this->log("Erro ao processar gravacao ID {$gravacao['id']}: " . $e->getMessage(), 'ERROR');
            }
        }

        $this->log("Limpeza concluida: {$removidas} removidas, {$erros} erros");

        return [
            'success' => $erros === 0,
            'message' => "Removidas {$removidas} de {$totalEncontradas} gravacoes" . ($erros > 0 ? " ({$erros} erros)" : ''),
            'data' => [
                'encontradas' => $totalEncontradas,
                'removidas' => $removidas,
                'erros' => $erros,
            ],
        ];
    }

    /**
     * Obtem caminho completo do arquivo
     */
    private function getFilePath(string $chave, string $arquivo): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/..' . self::UPLOAD_DIR . $chave . '/' . $arquivo;
    }
}
