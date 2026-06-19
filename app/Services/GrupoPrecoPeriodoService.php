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
