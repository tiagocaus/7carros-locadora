<?php

namespace App\Services;

use App\Models\GrupoPrecoFilial;
use App\Models\TaxaServicoValorFilial;
use App\Models\SiteConfig;

/**
 * Calcula o total de uma reserva feita pelo site publico SERVER-SIDE.
 *
 * Ignora qualquer valor enviado pelo cliente — o JS pode enviar o que quiser,
 * o que manda sao os precos cadastrados por filial + taxas + seguros.
 *
 * Uso:
 *   $svc = new WebsiteReservaCalcService();
 *   $total = $svc->calcular([
 *       'filial_id' => 14,
 *       'grupo_id' => 1,
 *       'plano' => 'KML',         // KML | KMC | DIA
 *       'dias' => 2,
 *       'servicos' => [2, 5],      // ids de taxaseservicos
 *       'seguro_carro' => false,
 *       'seguro_terceiros' => false,
 *   ]);
 */
class WebsiteReservaCalcService
{
    /**
     * @return array{total: float, breakdown: array}
     */
    public function calcular(array $input): array
    {
        $filialId = (int) ($input['filial_id'] ?? 0);
        $grupoId  = (int) ($input['grupo_id']  ?? 0);
        $plano    = (string) ($input['plano']  ?? '');
        $dias     = max(1, (int) ($input['dias'] ?? 1));
        $servicos = is_array($input['servicos'] ?? null) ? $input['servicos'] : [];
        $segCarro = !empty($input['seguro_carro']);
        $segTerc  = !empty($input['seguro_terceiros']);

        $precos = (new GrupoPrecoFilial())->buscarPorGrupoFilial($grupoId, $filialId);
        if (!$precos) {
            return ['total' => 0.0, 'breakdown' => ['erro' => 'sem_preco_grupo_filial']];
        }

        $calculoPlano = (new GrupoPrecoPeriodoService())->calcularValorDiaria($grupoId, $filialId, $plano, $dias);
        $valorPlanoDia = (float) ($calculoPlano['valor'] ?? 0);
        $subtotalPlano = $valorPlanoDia * $dias;

        $subtotalSeguros = 0.0;
        if ($segCarro) {
            $subtotalSeguros += ((float) ($precos['valor_seguro_carro'] ?? 0)) * $dias;
        }
        if ($segTerc) {
            $subtotalSeguros += ((float) ($precos['valor_seguro_terceiros'] ?? 0)) * $dias;
        }

        // Servicos adicionais: mistura MON/POR + FIX/PER/VLT
        $configModel = new SiteConfig();
        $subtotalServicos = 0.0;
        $servicosDetalhe = [];
        if (!empty($servicos)) {
            $ids = array_map('intval', $servicos);
            $linhas = $configModel->queryTable('taxaseservicos')
                ->select(['id', 'tipo_valor', 'base_calculo', 'valor'])
                ->whereIn('id', $ids)
                ->get();

            $valoresFilialMON = (new TaxaServicoValorFilial())->listarPorFilial($filialId);
            $valoresFilialMONMap = [];
            foreach ($valoresFilialMON as $v) {
                $valoresFilialMONMap[(int) $v['id_taxaservico']] = (float) $v['valor'];
            }

            foreach ($linhas as $s) {
                $idTaxa = (int) $s['id'];
                $tipo   = (string) ($s['tipo_valor'] ?? 'MON');
                $base   = (string) ($s['base_calculo'] ?? 'PER');

                if ($tipo === 'POR') {
                    $pct = (float) ($s['valor'] ?? 0);
                    $baseValor = ($base === 'FIX') ? $valorPlanoDia : $subtotalPlano;
                    $t = $baseValor * ($pct / 100);
                } else { // MON
                    $valor = $valoresFilialMONMap[$idTaxa] ?? (float) ($s['valor'] ?? 0);
                    // PER multiplica por dias; FIX e VLT cobram valor unico (VLT so faz sentido com POR)
                    $t = ($base === 'PER') ? $valor * $dias : $valor;
                }
                $subtotalServicos += $t;
                $servicosDetalhe[] = ['id' => $idTaxa, 'tipo' => $tipo, 'base' => $base, 'total' => round($t, 2)];
            }
        }

        $total = $subtotalPlano + $subtotalSeguros + $subtotalServicos;

        return [
            'total' => round($total, 2),
            'breakdown' => [
                'plano' => [
                    'valor_dia' => $valorPlanoDia,
                    'dias' => $dias,
                    'subtotal' => round($subtotalPlano, 2),
                    'origem' => $calculoPlano['origem'] ?? 'preco_base',
                ],
                'seguros' => round($subtotalSeguros, 2),
                'servicos' => $servicosDetalhe,
                'subtotal_servicos' => round($subtotalServicos, 2),
            ],
        ];
    }
}
