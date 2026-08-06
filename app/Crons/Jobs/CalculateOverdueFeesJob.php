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
    private const MAX_BATCHES_PER_TENANT = 20;

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

        $chaves = $this->carregarChavesComReceitasPendentes($mysqli);
        $totalElegiveis = 0;
        $totalAtualizados = 0;
        $erros = [];

        foreach ($chaves as $chave) {
            $this->setContextoTenant($chave);
            $hoje = today();

            $statsTenant = $this->consultarElegiveis($mysqli, $chave, $hoje);
            $totalElegiveis += $statsTenant['total'];

            if ($statsTenant['total'] === 0) {
                continue;
            }

            $this->log("Tenant {$chave}: {$statsTenant['total']} lancamento(s) vencido(s) elegivel(is)");

            $resultadoTenant = $this->atualizarEncargosTenant($mysqli, $chave, $hoje);
            $totalAtualizados += $resultadoTenant['atualizados'];
            $erros = array_merge($erros, $resultadoTenant['erros']);
        }

        $mysqli->close();

        $this->limparContextoTenant();

        $this->log("Lancamentos vencidos elegiveis: {$totalElegiveis}");
        $this->log("Lancamentos atualizados: {$totalAtualizados}");

        $falhas = array_values(array_filter($erros, static function (array $erro): bool {
            return ($erro['tipo'] ?? '') !== 'gateway_pago';
        }));
        $totalBloqueadosGateway = count($erros) - count($falhas);
        $message = "{$totalAtualizados} lancamento(s) vencido(s) atualizado(s)";
        if ($totalBloqueadosGateway > 0) {
            $message .= "; {$totalBloqueadosGateway} bloqueado(s) por cobranca ja paga no gateway";
        }

        return [
            'success' => empty($falhas),
            'status' => empty($falhas)
                ? self::STATUS_SUCCESS
                : ($totalAtualizados > 0 ? self::STATUS_PARTIAL : self::STATUS_FAILED),
            'message' => $message,
            'data' => [
                'elegiveis' => $totalElegiveis,
                'atualizados' => $totalAtualizados,
                'bloqueados_por_gateway' => $totalBloqueadosGateway,
                'falhas' => count($falhas),
                'erros' => $erros,
                'batch_size' => self::BATCH_SIZE,
                'max_batches_per_tenant' => self::MAX_BATCHES_PER_TENANT,
            ],
        ];
    }

    private function carregarChavesComReceitasPendentes(mysqli $mysqli): array
    {
        $sql = "
            SELECT DISTINCT f.chave
            FROM financeiro f
            INNER JOIN formas_pagamento fp
                ON fp.id = f.id_forma_pagamento
                AND fp.chave = f.chave
            WHERE f.tipo = 'R'
                AND f.pago = 'N'
                AND f.data_venci IS NOT NULL
                AND f.data_venci <> '0000-00-00'
                AND COALESCE(f.valor_subtotal, 0) > 0
                AND (COALESCE(fp.multa, 0) > 0 OR COALESCE(fp.juros_por_dia, 0) > 0)
            ORDER BY f.chave ASC
        ";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new \RuntimeException('Erro ao consultar tenants com lancamentos pendentes: ' . $mysqli->error);
        }

        $chaves = [];
        while ($row = $result->fetch_assoc()) {
            $chaves[] = (string) ($row['chave'] ?? '');
        }
        $result->free();

        return array_values(array_filter(array_unique($chaves)));
    }

    private function consultarElegiveis(mysqli $mysqli, string $chave, string $hoje): array
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM financeiro f
            INNER JOIN formas_pagamento fp
                ON fp.id = f.id_forma_pagamento
                AND fp.chave = f.chave
            WHERE f.tipo = 'R'
                AND f.chave = ?
                AND f.pago = 'N'
                AND f.data_venci IS NOT NULL
                AND f.data_venci <> '0000-00-00'
                AND f.data_venci < ?
                AND COALESCE(f.valor_subtotal, 0) > 0
                AND (COALESCE(fp.multa, 0) > 0 OR COALESCE(fp.juros_por_dia, 0) > 0)
                AND (
                    ABS(COALESCE(f.multa, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)) > 0.009
                    OR ABS(COALESCE(f.juros, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(?, f.data_venci), 2)) > 0.009
                    OR ABS(COALESCE(f.valor_total, 0) - (
                        f.valor_subtotal
                        + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                        + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(?, f.data_venci), 2)
                        - COALESCE(f.desconto, 0)
                    )) > 0.009
                )
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar consulta de lancamentos elegiveis: ' . $mysqli->error);
        }

        $stmt->bind_param('ssss', $chave, $hoje, $hoje, $hoje);

        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('Erro ao consultar lancamentos elegiveis: ' . $erro);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc() ?: ['total' => 0];
        $result->free();
        $stmt->close();

        return [
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    private function atualizarEncargosTenant(mysqli $mysqli, string $chave, string $hoje): array
    {
        $totalAtualizados = 0;
        $erros = [];
        $idsIgnorados = [];

        for ($batch = 1; $batch <= self::MAX_BATCHES_PER_TENANT; $batch++) {
            $resultadoBatch = $this->atualizarEncargos($mysqli, $chave, $hoje, $idsIgnorados);
            $totalAtualizados += $resultadoBatch['atualizados'];
            $erros = array_merge($erros, $resultadoBatch['erros']);

            foreach ($resultadoBatch['erros'] as $erro) {
                $idErro = (int) ($erro['id_financeiro'] ?? 0);
                if ($idErro > 0) {
                    $idsIgnorados[$idErro] = true;
                }
            }

            if (!$resultadoBatch['has_more']) {
                break;
            }

            if ($resultadoBatch['processados'] === 0) {
                $this->log("Tenant {$chave}: batch {$batch} nao processou lancamentos; interrompendo para evitar repeticao", 'WARNING');
                break;
            }
        }

        return [
            'atualizados' => $totalAtualizados,
            'erros' => $erros,
        ];
    }

    private function atualizarEncargos(mysqli $mysqli, string $chave, string $hoje, array $idsIgnorados = []): array
    {
        $limit = self::BATCH_SIZE;
        $idsIgnorados = array_values(array_filter(array_map('intval', array_keys($idsIgnorados))));
        $filtroIdsIgnorados = '';
        if (!empty($idsIgnorados)) {
            $filtroIdsIgnorados = ' AND f.id NOT IN (' . implode(',', $idsIgnorados) . ')';
        }

        $sql = "
            SELECT
                f.id,
                f.chave,
                ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2) AS nova_multa,
                ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(?, f.data_venci), 2) AS novo_juros,
                (
                    f.valor_subtotal
                    + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                    + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(?, f.data_venci), 2)
                    - COALESCE(f.desconto, 0)
                ) AS novo_valor_total
            FROM financeiro f
            INNER JOIN formas_pagamento fp
                ON fp.id = f.id_forma_pagamento
                AND fp.chave = f.chave
            WHERE f.tipo = 'R'
                AND f.chave = ?
                AND f.pago = 'N'
                AND f.data_venci IS NOT NULL
                AND f.data_venci <> '0000-00-00'
                AND f.data_venci < ?
                AND COALESCE(f.valor_subtotal, 0) > 0
                AND (COALESCE(fp.multa, 0) > 0 OR COALESCE(fp.juros_por_dia, 0) > 0)
                AND (
                    ABS(COALESCE(f.multa, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)) > 0.009
                    OR ABS(COALESCE(f.juros, 0) - ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(?, f.data_venci), 2)) > 0.009
                    OR ABS(COALESCE(f.valor_total, 0) - (
                        f.valor_subtotal
                        + ROUND(f.valor_subtotal * (COALESCE(fp.multa, 0) / 100), 2)
                        + ROUND(f.valor_subtotal * (COALESCE(fp.juros_por_dia, 0) / 100) * DATEDIFF(?, f.data_venci), 2)
                        - COALESCE(f.desconto, 0)
                    )) > 0.009
                )
                {$filtroIdsIgnorados}
            ORDER BY f.data_venci ASC, f.id ASC
            LIMIT {$limit}
        ";

        $selectStmt = $mysqli->prepare($sql);
        if (!$selectStmt) {
            throw new \RuntimeException('Erro ao preparar consulta de juros e multa: ' . $mysqli->error);
        }

        $selectStmt->bind_param('ssssss', $hoje, $hoje, $chave, $hoje, $hoje, $hoje);

        if (!$selectStmt->execute()) {
            $erro = $selectStmt->error;
            $selectStmt->close();
            throw new \RuntimeException('Erro ao consultar juros e multa: ' . $erro);
        }

        $result = $selectStmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        $selectStmt->close();

        if (empty($rows)) {
            return ['atualizados' => 0, 'erros' => [], 'processados' => 0, 'has_more' => false];
        }

        $stmt = $mysqli->prepare("
            UPDATE financeiro
            SET multa = ?, juros = ?, valor_total = ?, updated_at = ?
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

                $agora = \App\Helpers\DateHelper::systemNow();
                $stmt->bind_param('dddsis', $multa, $juros, $valorTotal, $agora, $id, $chave);
                if (!$stmt->execute()) {
                    throw new \RuntimeException($stmt->error);
                }

                $atualizados += $stmt->affected_rows > 0 ? 1 : 0;
            } catch (\Throwable $e) {
                $tipoErro = $this->isCobrancaPagaNoGateway($e) ? 'gateway_pago' : 'erro';
                $erros[] = [
                    'id_financeiro' => $id,
                    'chave' => $chave,
                    'tipo' => $tipoErro,
                    'erro' => $e->getMessage(),
                ];
                $nivel = $tipoErro === 'gateway_pago' ? 'WARNING' : 'ERROR';
                $this->log("Lancamento #{$id} bloqueado: {$e->getMessage()}", $nivel);
            }
        }

        $stmt->close();

        return [
            'atualizados' => $atualizados,
            'erros' => $erros,
            'processados' => count($rows),
            'has_more' => count($rows) === self::BATCH_SIZE,
        ];
    }

    private function isCobrancaPagaNoGateway(\Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());
        $message = strtr($message, [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return str_contains($message, 'cobranca antiga ja consta como paga no gateway')
            || (str_contains($message, 'gateway') && str_contains($message, 'consta como paga'));
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
