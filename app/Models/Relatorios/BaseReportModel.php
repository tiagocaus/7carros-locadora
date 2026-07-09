<?php

namespace App\Models\Relatorios;

use App\Core\Auth;
use App\Models\Model;

/**
 * Model base para relatórios
 *
 * Fornece helpers para queries de agregação comuns em relatórios.
 */
abstract class BaseReportModel extends Model
{
    /**
     * Calcula percentual seguro (evita divisão por zero)
     */
    protected function pct(float $part, float $total, int $decimals = 2): float
    {
        if ($total == 0) {
            return 0.0;
        }
        return round(($part / $total) * 100, $decimals);
    }

    /**
     * Calcula diferença em dias entre duas datas
     */
    protected function daysBetween(string $start, string $end): int
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);

        if ($startTs === false || $endTs === false) {
            return 0;
        }

        return max(0, (int) ceil(($endTs - $startTs) / 86400));
    }

    /**
     * Divisão segura (evita divisão por zero)
     */
    protected function safeDivide(float $numerator, float $denominator, int $decimals = 2): float
    {
        if ($denominator == 0) {
            return 0.0;
        }
        return round($numerator / $denominator, $decimals);
    }

    /**
     * Retorna lançamentos financeiros normalizados por veículo.
     *
     * Prioriza financeiro_itens.id_veiculo e usa financeiro.id_veiculo apenas
     * como fallback quando a fatura não possui nenhum item vinculado a veículo.
     */
    protected function financeiroVeiculoRows(
        string $tipo,
        string $dataInicio,
        string $dataFim,
        array $options = []
    ): array {
        $tipo = strtoupper($tipo) === 'D' ? 'D' : 'R';
        $chave = Auth::chave();
        if (empty($chave)) {
            return [];
        }

        $dateField = $options['date_field'] ?? 'data_venci';
        $dateColumn = in_array($dateField, ['data_venci', 'data_criada', 'data_pago'], true)
            ? 'f.' . $dateField
            : 'f.data_venci';

        [$filialSql, $filialParams] = $this->buildFinanceiroVeiculoFilialSql(
            $options['filial_where'] ?? '',
            $options['filial_params'] ?? [],
            $options['filial_id'] ?? ''
        );

        $extraWhere = $options['extra_where'] ?? [];
        $extraParams = $options['extra_params'] ?? [];
        $extraSql = '';
        if (!empty($extraWhere)) {
            $extraSql = ' AND ' . implode(' AND ', $extraWhere);
        }

        $itemWhere = "
            f.chave = ?
            AND f.tipo = ?
            AND {$dateColumn} BETWEEN ? AND ?
            {$filialSql}
            {$extraSql}
        ";
        $itemParams = array_merge([$chave, $tipo, $dataInicio, $dataFim], $filialParams, $extraParams);

        $fallbackWhere = $itemWhere;
        $fallbackParams = $itemParams;

        $veiculoId = (string) ($options['veiculo_id'] ?? '');
        if ($veiculoId !== '') {
            $itemWhere .= ' AND fi.id_veiculo = ?';
            $itemParams[] = (int) $veiculoId;
            $fallbackWhere .= ' AND f.id_veiculo = ?';
            $fallbackParams[] = (int) $veiculoId;
        }

        $grupoId = (string) ($options['grupo_id'] ?? '');
        if ($grupoId !== '') {
            $itemWhere .= ' AND v.id_grupo = ?';
            $itemParams[] = (int) $grupoId;
            $fallbackWhere .= ' AND v.id_grupo = ?';
            $fallbackParams[] = (int) $grupoId;
        }

        $sql = "
            SELECT *
            FROM (
                SELECT
                    f.id AS id_financeiro,
                    f.tipo,
                    f.data_venci,
                    f.data_criada,
                    f.pago,
                    COALESCE(NULLIF(fi.descricao, ''), f.descricao, CONCAT('Financeiro #', f.id)) AS descricao,
                    CASE
                        WHEN COALESCE(f.valor_subtotal, 0) > 0
                            THEN fi.valor * (COALESCE(f.valor_total, f.valor_subtotal) / f.valor_subtotal)
                        ELSE fi.valor
                    END AS valor,
                    v.id AS id_veiculo,
                    v.id_grupo,
                    v.placa,
                    v.marca,
                    v.modelo,
                    g.nome AS grupo_nome
                FROM financeiro f
                INNER JOIN financeiro_itens fi
                    ON fi.id_financeiro = f.id
                    AND fi.chave = f.chave
                    AND fi.id_veiculo IS NOT NULL
                    AND fi.id_veiculo > 0
                INNER JOIN veiculos v
                    ON v.id = fi.id_veiculo
                    AND v.chave = f.chave
                LEFT JOIN grupos g
                    ON g.id = v.id_grupo
                    AND g.chave = f.chave
                WHERE {$itemWhere}

                UNION ALL

                SELECT
                    f.id AS id_financeiro,
                    f.tipo,
                    f.data_venci,
                    f.data_criada,
                    f.pago,
                    COALESCE(NULLIF(f.descricao, ''), CONCAT('Financeiro #', f.id)) AS descricao,
                    f.valor_total AS valor,
                    v.id AS id_veiculo,
                    v.id_grupo,
                    v.placa,
                    v.marca,
                    v.modelo,
                    g.nome AS grupo_nome
                FROM financeiro f
                INNER JOIN veiculos v
                    ON v.id = f.id_veiculo
                    AND v.chave = f.chave
                LEFT JOIN grupos g
                    ON g.id = v.id_grupo
                    AND g.chave = f.chave
                WHERE {$fallbackWhere}
                    AND f.id_veiculo IS NOT NULL
                    AND f.id_veiculo > 0
                    AND NOT EXISTS (
                        SELECT 1
                        FROM financeiro_itens fi2
                        WHERE fi2.id_financeiro = f.id
                            AND fi2.chave = f.chave
                            AND fi2.id_veiculo IS NOT NULL
                            AND fi2.id_veiculo > 0
                    )
            ) base
            ORDER BY data_venci ASC, id_financeiro ASC, placa ASC
        ";

        return $this->fetchReportRows($sql, array_merge($itemParams, $fallbackParams));
    }

    protected function financeiroVeiculoSomas(
        string $tipo,
        string $dataInicio,
        string $dataFim,
        array $options = []
    ): array {
        $out = [];
        foreach ($this->financeiroVeiculoRows($tipo, $dataInicio, $dataFim, $options) as $row) {
            $idVeiculo = (int) $row['id_veiculo'];
            $out[$idVeiculo] = ($out[$idVeiculo] ?? 0.0) + (float) $row['valor'];
        }

        return $out;
    }

    protected function financeiroVeiculoSomasPorGrupo(
        string $tipo,
        string $dataInicio,
        string $dataFim,
        array $options = []
    ): array {
        $out = [];
        foreach ($this->financeiroVeiculoRows($tipo, $dataInicio, $dataFim, $options) as $row) {
            $idGrupo = (int) ($row['id_grupo'] ?? 0);
            $out[$idGrupo] = ($out[$idGrupo] ?? 0.0) + (float) $row['valor'];
        }

        return $out;
    }

    private function buildFinanceiroVeiculoFilialSql(string $filialWhere, array $filialParams, string $filialId): array
    {
        if ($filialId !== '') {
            return [' AND f.id_matriz_filial = ?', [(int) $filialId]];
        }

        $filialWhere = trim($filialWhere);
        if ($filialWhere === '' || $filialWhere === '1=1') {
            return ['', []];
        }

        if (!str_contains($filialWhere, '.')) {
            $filialWhere = str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere);
        }

        return [' AND ' . $filialWhere, $filialParams];
    }

    protected function fetchReportRows(string $sql, array $params): array
    {
        $stmt = $this->getMysqli()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar consulta do relatório: ' . $this->getMysqli()->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($this->reportParamTypes($params), ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }

    private function reportParamTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
        }

        return $types;
    }
}
