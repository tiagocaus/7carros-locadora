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
        $siteConfig = (new SiteConfig())->buscarPorChave() ?? [];
        $segCarroObrigatorio = !empty($siteConfig['seguro_carro_obrigatorio']);
        $segTercObrigatorio = !empty($siteConfig['seguro_terceiros_obrigatorio']);
        $segCarro = $segCarroObrigatorio || !empty($input['seguro_carro']);
        $segTerc  = $segTercObrigatorio || !empty($input['seguro_terceiros']);

        $precos = (new GrupoPrecoFilial())->buscarPorGrupoFilial($grupoId, $filialId);
        if (!$precos) {
            return ['total' => 0.0, 'breakdown' => ['erro' => 'sem_preco_grupo_filial']];
        }

        $calculoPlano = (new GrupoPrecoPeriodoService())->calcularValorDiaria($grupoId, $filialId, $plano, $dias);
        $valorPlanoDia = (float) ($calculoPlano['valor'] ?? 0);
        $subtotalPlano = $valorPlanoDia * $dias;

        $valorSegCarroDia = (float) ($precos['valor_seguro_carro'] ?? 0);
        $valorSegTercDia = (float) ($precos['valor_seguro_terceiros'] ?? 0);
        $subtotalSegCarro = $segCarro ? $valorSegCarroDia * $dias : 0.0;
        $subtotalSegTerc = $segTerc ? $valorSegTercDia * $dias : 0.0;
        $subtotalSeguros = 0.0;
        if ($segCarro) {
            $subtotalSeguros += $subtotalSegCarro;
        }
        if ($segTerc) {
            $subtotalSeguros += $subtotalSegTerc;
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

        $totalOriginal = round($subtotalPlano + $subtotalSeguros + $subtotalServicos, 2);
        $promocao = null;
        $codigoPromocional = PromocaoAplicacaoService::normalizarCodigo($input['promocao_codigo'] ?? '');
        if ($codigoPromocional !== '') {
            $promocao = (new PromocaoAplicacaoService())->validarECalcular(
                $codigoPromocional,
                $filialId,
                $dias,
                $totalOriginal,
                'SITE',
                $grupoId
            );
        }
        $desconto = (float) ($promocao['valor_desconto'] ?? 0);
        $total = round(max(0, $totalOriginal - $desconto), 2);

        return [
            'total' => $total,
            'total_original' => $totalOriginal,
            'promocao' => $promocao,
            'breakdown' => [
                'plano' => [
                    'valor_dia' => $valorPlanoDia,
                    'dias' => $dias,
                    'subtotal' => round($subtotalPlano, 2),
                    'origem' => $calculoPlano['origem'] ?? 'preco_base',
                ],
                'seguros' => round($subtotalSeguros, 2),
                'seguros_detalhe' => [
                    'carro' => [
                        'selecionado' => $segCarro,
                        'obrigatorio' => $segCarroObrigatorio,
                        'valor_dia' => round($valorSegCarroDia, 2),
                        'subtotal' => round($subtotalSegCarro, 2),
                    ],
                    'terceiros' => [
                        'selecionado' => $segTerc,
                        'obrigatorio' => $segTercObrigatorio,
                        'valor_dia' => round($valorSegTercDia, 2),
                        'subtotal' => round($subtotalSegTerc, 2),
                    ],
                ],
                'servicos' => $servicosDetalhe,
                'subtotal_servicos' => round($subtotalServicos, 2),
                'desconto' => round($desconto, 2),
            ],
        ];
    }
}
