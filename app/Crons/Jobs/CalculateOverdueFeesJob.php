<?php

namespace App\Crons\Jobs;

use App\Core\Database;
use App\Services\PagamentoLinkSyncService;
use mysqli;

/**
 * CRON Job: Calcula juros e multa de receitas vencidas.
 */
class CalculateOverdueFeesJob extends BaseJob
{
    private const BATCH_SIZE = 5000;

    protected string $name = 'CalculateOverdueFees';
    protected string $description = 'Calcula juros e multa de lancamentos financeiros vencidos';

    protected function handle(): array
    {
        $mysqli = new mysqli(
            Database::env('DB_HOST'),
            Database::env('DB_USERNAME'),
            Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'),
            (int) Database::env('DB_PORT', '3306')
        );
        $mysqli->set_charset('utf8mb4');

        if ($mysqli->connect_error) {
            throw new \RuntimeException('Erro ao conectar no banco: ' . $mysqli->connect_error);
        }

        $statsAntes = $this->consultarElegiveis($mysqli);
        $this->log("Lancamentos vencidos elegiveis: {$statsAntes['total']}");

        if ($statsAntes['total'] === 0) {
            $mysqli->close();

            return [
                'success' => true,
                'message' => 'Nenhum lancamento vencido para atualizar',
                'data' => [
                    'elegiveis' => 0,
                    'atualizados' => 0,
                    'batch_size' => self::BATCH_SIZE,
                ],
            ];
        }

        $resultadoAtualizacao = $this->atualizarEncargos($mysqli);
        $mysqli->close();

        $this->limparContextoTenant();

        $this->log("Lancamentos atualizados: {$resultadoAtualizacao['atualizados']}");

        return [
            'success' => empty($resultadoAtualizacao['erros']),
            'message' => "{$resultadoAtualizacao['atualizados']} lancamento(s) vencido(s) atualizado(s)",
            'data' => [
                'elegiveis' => $statsAntes['total'],
                'atualizados' => $resultadoAtualizacao['atualizados'],
                'bloqueados_por_gateway' => count($resultadoAtualizacao['erros']),
                'erros' => $resultadoAtualizacao['erros'],
                'batch_size' => self::BATCH_SIZE,
            ],
        ];
    }

    private function consultarElegiveis(mysqli $mysqli): array
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM financeiro f
            INNER JOIN formas_pagamento fp
                ON fp.id = f.id_forma_pagamento
                AND fp.chave = f.chave
            WHERE f.tipo = 'R'
                AND f.pago = 'N'
                AND f.data_venci IS NOT NULL
                AND f.data_venci <> '0000-00-00'
                AND f.data_venci < CURDATE()
                AND COALESCE(f.valor_subtotal, 0) > 0
                AND (COALESCE(fp.multa, 0) > 0 OR COALESCE(fp.juros_por_dia, 0) > 0)
                AND (
                    ABS(COALESCE(f.multa, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)) > 0.009
                    OR ABS(COALESCE(f.juros, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2)) > 0.009
                    OR ABS(COALESCE(f.valor_total, 0) - (
                        f.valor_subtotal
                        + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                        + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2)
                        - COALESCE(f.desconto, 0)
                    )) > 0.009
                )
        ";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new \RuntimeException('Erro ao consultar lancamentos elegiveis: ' . $mysqli->error);
        }

        $row = $result->fetch_assoc() ?: ['total' => 0];
        $result->free();

        return [
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    private function atualizarEncargos(mysqli $mysqli): array
    {
        $limit = self::BATCH_SIZE;
        $sql = "
            SELECT
                f.id,
                f.chave,
                ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2) AS nova_multa,
                ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2) AS novo_juros,
                (
                    f.valor_subtotal
                    + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                    + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2)
                    - COALESCE(f.desconto, 0)
                ) AS novo_valor_total
            FROM financeiro f
            INNER JOIN formas_pagamento fp
                ON fp.id = f.id_forma_pagamento
                AND fp.chave = f.chave
            WHERE f.tipo = 'R'
                AND f.pago = 'N'
                AND f.data_venci IS NOT NULL
                AND f.data_venci <> '0000-00-00'
                AND f.data_venci < CURDATE()
                AND COALESCE(f.valor_subtotal, 0) > 0
                AND (COALESCE(fp.multa, 0) > 0 OR COALESCE(fp.juros_por_dia, 0) > 0)
                AND (
                    ABS(COALESCE(f.multa, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)) > 0.009
                    OR ABS(COALESCE(f.juros, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2)) > 0.009
                    OR ABS(COALESCE(f.valor_total, 0) - (
                        f.valor_subtotal
                        + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                        + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2)
                        - COALESCE(f.desconto, 0)
                    )) > 0.009
                )
            ORDER BY f.data_venci ASC, f.id ASC
            LIMIT {$limit}
        ";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new \RuntimeException('Erro ao consultar juros e multa: ' . $mysqli->error);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();

        if (empty($rows)) {
            return ['atualizados' => 0, 'erros' => []];
        }

        $stmt = $mysqli->prepare("
            UPDATE financeiro
            SET multa = ?, juros = ?, valor_total = ?, updated_at = NOW()
            WHERE id = ? AND chave = ?
        ");

        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar atualizacao de juros e multa: ' . $mysqli->error);
        }

        $syncService = new PagamentoLinkSyncService();
        $atualizados = 0;
        $erros = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $chave = (string) $row['chave'];
            $multa = (float) $row['nova_multa'];
            $juros = (float) $row['novo_juros'];
            $valorTotal = (float) $row['novo_valor_total'];

            try {
                $this->setContextoTenant($chave);
                $syncService->invalidarLinksPendentes($id, $chave);

                $stmt->bind_param('dddis', $multa, $juros, $valorTotal, $id, $chave);
                if (!$stmt->execute()) {
                    throw new \RuntimeException($stmt->error);
                }

                $atualizados += $stmt->affected_rows > 0 ? 1 : 0;
            } catch (\Throwable $e) {
                $erros[] = [
                    'id_financeiro' => $id,
                    'chave' => $chave,
                    'erro' => $e->getMessage(),
                ];
                $this->log("Lancamento #{$id} bloqueado: {$e->getMessage()}", 'ERROR');
            }
        }

        $stmt->close();

        return ['atualizados' => $atualizados, 'erros' => $erros];
    }

    private function setContextoTenant(string $chave): void
    {
        $_SESSION['chave'] = $chave;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_name'] = 'Sistema';
    }

    private function limparContextoTenant(): void
    {
        unset($_SESSION['chave'], $_SESSION['user_id'], $_SESSION['user_name']);
    }
}
