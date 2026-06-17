<?php

namespace App\Crons\Jobs;

use App\Core\Database;
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

        $atualizados = $this->atualizarEncargos($mysqli);
        $mysqli->close();

        $this->log("Lancamentos atualizados: {$atualizados}");

        return [
            'success' => true,
            'message' => "{$atualizados} lancamento(s) vencido(s) atualizado(s)",
            'data' => [
                'elegiveis' => $statsAntes['total'],
                'atualizados' => $atualizados,
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

    private function atualizarEncargos(mysqli $mysqli): int
    {
        $limit = self::BATCH_SIZE;
        $sql = "
            UPDATE financeiro f
            INNER JOIN formas_pagamento fp
                ON fp.id = f.id_forma_pagamento
                AND fp.chave = f.chave
            INNER JOIN (
                SELECT f2.id
                FROM financeiro f2
                INNER JOIN formas_pagamento fp2
                    ON fp2.id = f2.id_forma_pagamento
                    AND fp2.chave = f2.chave
                WHERE f2.tipo = 'R'
                    AND f2.pago = 'N'
                    AND f2.data_venci IS NOT NULL
                    AND f2.data_venci <> '0000-00-00'
                    AND f2.data_venci < CURDATE()
                    AND COALESCE(f2.valor_subtotal, 0) > 0
                    AND (COALESCE(fp2.multa, 0) > 0 OR COALESCE(fp2.juros_por_dia, 0) > 0)
                    AND (
                        ABS(COALESCE(f2.multa, 0) - ROUND(f2.valor_subtotal * (COALESCE(fp2.multa, 0) / 100), 2)) > 0.009
                        OR ABS(COALESCE(f2.juros, 0) - ROUND(f2.valor_subtotal * (COALESCE(fp2.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f2.data_venci), 2)) > 0.009
                        OR ABS(COALESCE(f2.valor_total, 0) - (
                            f2.valor_subtotal
                            + ROUND(f2.valor_subtotal * (COALESCE(fp2.multa, 0) / 100), 2)
                            + ROUND(f2.valor_subtotal * (COALESCE(fp2.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f2.data_venci), 2)
                            - COALESCE(f2.desconto, 0)
                        )) > 0.009
                    )
                ORDER BY f2.data_venci ASC, f2.id ASC
                LIMIT {$limit}
            ) elegiveis ON elegiveis.id = f.id
            SET
                f.multa = ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2),
                f.juros = ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2),
                f.valor_total = (
                    f.valor_subtotal
                    + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                    + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(CURDATE(), f.data_venci), 2)
                    - COALESCE(f.desconto, 0)
                ),
                f.updated_at = NOW()
        ";

        if (!$mysqli->query($sql)) {
            throw new \RuntimeException('Erro ao atualizar juros e multa: ' . $mysqli->error);
        }

        return $mysqli->affected_rows;
    }
}
