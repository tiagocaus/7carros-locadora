<?php

namespace App\Models\Relatorios;

use App\Classes\QueryBuilder;
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
}
