<?php

namespace App\Services;

use App\Models\GrupoPrecoFilial;
use App\Models\SiteConfig;
use App\Models\TaxaServico;

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
 *       'data_inicio' => '2026-12-20',
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
        $dataInicio = (string) ($input['data_inicio'] ?? '');
        $servicosSolicitados = is_array($input['servicos'] ?? null) ? $input['servicos'] : [];
        $siteConfig = (new SiteConfig())->buscarPorChave() ?? [];
        $segCarroObrigatorio = !empty($siteConfig['seguro_carro_obrigatorio']);
        $segTercObrigatorio = !empty($siteConfig['seguro_terceiros_obrigatorio']);
        $segCarro = $segCarroObrigatorio || !empty($input['seguro_carro']);
        $segTerc  = $segTercObrigatorio || !empty($input['seguro_terceiros']);

        $precos = (new GrupoPrecoFilial())->buscarPorGrupoFilial($grupoId, $filialId);
        if (!$precos) {
            return ['total' => 0.0, 'breakdown' => ['erro' => 'sem_preco_grupo_filial']];
        }

        if ($dataInicio !== '' && !$this->dataValida($dataInicio)) {
            throw new \InvalidArgumentException('Data inicial invalida para o calculo da reserva.');
        }

        $chave = (string) ($input['chave'] ?? ($_SESSION['chave'] ?? ''));
        $precoService = new GrupoPrecoPeriodoService();
        $calculoPlano = $dataInicio !== '' && $chave !== ''
            ? $precoService->calcularValorPeriodo($grupoId, $filialId, $plano, $dias, $dataInicio, $chave)
            : $precoService->calcularValorDiaria($grupoId, $filialId, $plano, $dias);
        $valorPlanoDia = (float) ($calculoPlano['valor_dia'] ?? $calculoPlano['valor'] ?? 0);
        $subtotalPlano = (float) ($calculoPlano['subtotal'] ?? ($valorPlanoDia * $dias));

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
        $subtotalServicos = 0.0;
        $servicosDetalhe = [];
        $idsSolicitados = array_values(array_unique(array_filter(
            array_map('intval', $servicosSolicitados),
            static fn(int $id): bool => $id > 0
        )));
        $idsSolicitadosMap = array_fill_keys($idsSolicitados, true);
        $linhasDisponiveis = (new TaxaServico())->listarParaWebsitePorFilial($filialId);

        foreach ($linhasDisponiveis as $s) {
            $idTaxa = (int) $s['id'];
            $obrigatorio = ($s['aplicar'] ?? 'N') === 'S';
            if (!$obrigatorio && !isset($idsSolicitadosMap[$idTaxa])) {
                continue;
            }

            $tipo = (string) ($s['tipo_valor'] ?? 'MON');
            $base = (string) ($s['base_calculo'] ?? 'PER');
            $valorUnitario = (float) ($s['valor'] ?? 0);

            if ($tipo === 'POR') {
                $pct = $valorUnitario;
                $baseValor = ($base === 'FIX') ? $valorPlanoDia : $subtotalPlano;
                $t = $baseValor * ($pct / 100);
            } else { // MON
                // PER multiplica por dias; FIX e VLT cobram valor unico (VLT so faz sentido com POR)
                $t = ($base === 'PER') ? $valorUnitario * $dias : $valorUnitario;
            }
            $subtotalServicos += $t;
            $servicosDetalhe[] = [
                'id' => $idTaxa,
                'nome' => (string) ($s['nome'] ?? ''),
                'tipo' => $tipo,
                'base' => $base,
                'valor_unitario' => round($valorUnitario, 2),
                'total' => round($t, 2),
                'obrigatorio' => $obrigatorio,
            ];
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
                    'valor_base_dia' => round((float) ($calculoPlano['valor_base_dia'] ?? $valorPlanoDia), 2),
                    'tem_ajuste_temporada' => (bool) ($calculoPlano['tem_ajuste'] ?? false),
                    'temporadas' => $calculoPlano['temporadas'] ?? [],
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

    private function dataValida(string $data): bool
    {
        $objeto = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        return $objeto !== false && $objeto->format('Y-m-d') === $data;
    }
}
