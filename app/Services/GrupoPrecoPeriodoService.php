<?php

namespace App\Services;

use App\Models\GrupoPrecoDiaFilial;
use App\Models\GrupoPrecoFilial;

/**
 * Calcula o valor da diaria de um grupo considerando a escala progressiva por dias.
 */
class GrupoPrecoPeriodoService
{
    public function calcularValorDiaria(int $grupoId, int $filialId, string $plano, int $dias): array
    {
        $dias = max(1, $dias);
        $tipoPlano = $this->tipoPlanoPorCodigo($plano);

        $precos = (new GrupoPrecoFilial())->buscarPorGrupoFilial($grupoId, $filialId);
        if (!$precos) {
            return [
                'valor' => 0.0,
                'origem' => 'sem_preco_grupo_filial',
                'tipo_plano' => $tipoPlano,
                'campo_base' => $this->campoBasePorTipoPlano($tipoPlano),
            ];
        }

        $valorProgressivo = (new GrupoPrecoDiaFilial())->calcularValor($grupoId, $filialId, $tipoPlano, $dias);
        if ($valorProgressivo !== null) {
            return [
                'valor' => $valorProgressivo,
                'origem' => 'preco_dias',
                'tipo_plano' => $tipoPlano,
                'campo_base' => $this->campoBasePorTipoPlano($tipoPlano),
            ];
        }

        $campoBase = $this->campoBasePorTipoPlano($tipoPlano);

        return [
            'valor' => (float) ($precos[$campoBase] ?? 0),
            'origem' => 'preco_base',
            'tipo_plano' => $tipoPlano,
            'campo_base' => $campoBase,
        ];
    }

    /**
     * Calcula o preco do plano para todas as diarias faturadas do periodo.
     * A data de devolucao nao e faturada: para N dias sao considerados
     * dataInicio ate dataInicio + (N - 1) dias.
     */
    public function calcularValorPeriodo(
        int $grupoId,
        int $filialId,
        string $plano,
        int $dias,
        string $dataInicio,
        string $chave,
        ?TemporadaService $temporadaService = null
    ): array {
        $dias = max(1, $dias);
        $calculoBase = $this->calcularValorDiaria($grupoId, $filialId, $plano, $dias);
        $valorBaseDia = (float) ($calculoBase['valor'] ?? 0);

        if ($valorBaseDia <= 0 || $dataInicio === '') {
            return array_merge($calculoBase, [
                'valor_base_dia' => $valorBaseDia,
                'valor_dia' => $valorBaseDia,
                'subtotal' => round($valorBaseDia * $dias, 2),
                'dias' => $dias,
                'tem_ajuste' => false,
                'temporadas' => [],
            ]);
        }

        $inicio = new \DateTimeImmutable($dataInicio);
        $fimInclusivo = $inicio->modify('+' . ($dias - 1) . ' days');
        $periodo = ($temporadaService ?? new TemporadaService($chave))->calcularPeriodo(
            $valorBaseDia,
            $grupoId,
            $inicio->format('Y-m-d'),
            $fimInclusivo->format('Y-m-d')
        );

        $temporadas = [];
        foreach ($periodo['detalhes'] as $detalhe) {
            if (empty($detalhe['temporada'])) {
                continue;
            }
            $idTemporada = (int) $detalhe['temporada']['id'];
            if (!isset($temporadas[$idTemporada])) {
                $temporadas[$idTemporada] = [
                    'id' => $idTemporada,
                    'nome' => (string) $detalhe['temporada']['nome'],
                    'ajuste_percentual' => (float) $detalhe['ajuste_percentual'],
                    'dias_aplicados' => 0,
                ];
            }
            $temporadas[$idTemporada]['dias_aplicados']++;
        }

        $subtotal = (float) $periodo['valor_total'];
        return array_merge($calculoBase, [
            'valor_base_dia' => $valorBaseDia,
            'valor_dia' => round($subtotal / $dias, 2),
            'subtotal' => round($subtotal, 2),
            'dias' => $dias,
            'tem_ajuste' => (bool) $periodo['tem_ajuste'],
            'temporadas' => array_values($temporadas),
        ]);
    }

    public function tipoPlanoPorCodigo(string $plano): string
    {
        return match (strtoupper($plano)) {
            'KL', 'KML' => 'km_livre',
            'KMC' => 'km_controlado',
            default => 'diaria',
        };
    }

    private function campoBasePorTipoPlano(string $tipoPlano): string
    {
        return match ($tipoPlano) {
            'km_livre' => 'valor_plano_km_livre',
            'km_controlado' => 'valor_plano_km_controlado',
            default => 'valor_plano_km_pago',
        };
    }
}
