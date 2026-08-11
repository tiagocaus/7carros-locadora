<?php

namespace App\Services;

use App\Helpers\DateHelper;

/**
 * Calcula o encerramento proporcional de contratos sem acessar o banco.
 *
 * A persistencia e a conciliacao financeira permanecem nos Models. Isso permite
 * usar exatamente a mesma regra no preview e na confirmacao da devolucao.
 */
class ContratoEncerramentoService
{
    public const PLANO_CONTA_CREDITO = '3.4.1.23';
    public const PLANO_CONTA_RECEITA = '4.1.1.03';

    /**
     * @param array<int,array<string,mixed>> $veiculos
     * @param array<int,array<string,mixed>> $taxas
     * @param array<int,array<string,mixed>> $devolucoes
     * @param array<int,array<string,mixed>> $taxasExtras
     */
    public function calcular(
        array $contrato,
        array $veiculos,
        array $taxas,
        array $devolucoes,
        array $taxasExtras,
        float $principalLancado
    ): array {
        $devolucoesPorId = [];
        foreach ($devolucoes as $devolucao) {
            $id = (int) ($devolucao['id_contrato_veiculo'] ?? 0);
            if ($id > 0) {
                $devolucoesPorId[$id] = $devolucao;
            }
        }

        $veiculosProjetados = [];
        $ativosRestantes = 0;
        foreach ($veiculos as $veiculo) {
            $id = (int) ($veiculo['id'] ?? 0);
            if (isset($devolucoesPorId[$id])) {
                $veiculo['data_entrada'] = $devolucoesPorId[$id]['data_entrada'] ?? $veiculo['data_entrada'] ?? null;
                $veiculo['odometro_entrada'] = $devolucoesPorId[$id]['odometro_entrada'] ?? $veiculo['odometro_entrada'] ?? null;
                $veiculo['combustivel_entrada'] = $devolucoesPorId[$id]['combustivel_entrada'] ?? $veiculo['combustivel_entrada'] ?? null;
            }
            if (empty($veiculo['data_entrada'])) {
                $ativosRestantes++;
            }
            $veiculosProjetados[] = $veiculo;
        }

        $contagem = (string) ($contrato['contagem'] ?? 'dia');
        $baseDias = $this->baseDias($contagem);
        $detalhesVeiculos = [];
        $detalhesVeiculosDevolucao = [];
        $totalVeiculos = 0.0;
        $totalSeguros = 0.0;
        $totalKm = 0.0;
        $totalCombustivel = 0.0;
        $dataEncerramento = null;

        foreach ($veiculosProjetados as $veiculo) {
            if (empty($veiculo['data_entrada']) || empty($veiculo['data_saida'])) {
                continue;
            }

            $periodo = $this->calcularPeriodo(
                (string) $veiculo['data_saida'],
                (string) $veiculo['data_entrada'],
                $contagem
            );
            $tarifa = $this->valorPlano($veiculo);
            $seguroPeriodo = $this->valorSeguros($veiculo);
            $valorPlano = $this->valorPorPeriodo($tarifa, $periodo, $baseDias);
            $valorSeguro = $this->valorPorPeriodo($seguroPeriodo, $periodo, $baseDias);
            $km = $this->calcularKm($veiculo, $periodo['dias_equivalentes'], $baseDias);
            $combustivel = $this->calcularCombustivel($veiculo);
            $cobrancaNestaDevolucao = isset($devolucoesPorId[(int) ($veiculo['id'] ?? 0)]);

            $totalVeiculos += $valorPlano;
            $totalSeguros += $valorSeguro;
            if ($cobrancaNestaDevolucao) {
                $totalKm += $km['valor'];
                $totalCombustivel += $combustivel['valor'];
            }

            if ($dataEncerramento === null || (string) $veiculo['data_entrada'] > $dataEncerramento) {
                $dataEncerramento = (string) $veiculo['data_entrada'];
            }

            $detalheVeiculo = [
                'id_contrato_veiculo' => (int) ($veiculo['id'] ?? 0),
                'id_veiculo' => (int) ($veiculo['id_veiculo'] ?? 0),
                'placa' => $veiculo['veiculo_placa'] ?? null,
                'plano' => $veiculo['plano'] ?? null,
                'data_saida' => $veiculo['data_saida'],
                'data_entrada' => $veiculo['data_entrada'],
                'ciclos_completos' => $periodo['ciclos_completos'],
                'dias_restantes' => $periodo['dias_restantes'],
                'dias_equivalentes' => $periodo['dias_equivalentes'],
                'tarifa_periodo' => $tarifa,
                'valor_diaria' => round($tarifa / $baseDias, 2),
                'valor_plano' => $valorPlano,
                'valor_seguros' => $valorSeguro,
                'cobranca_nesta_devolucao' => $cobrancaNestaDevolucao,
                'km' => $km,
                'combustivel' => $combustivel,
            ];
            $detalhesVeiculos[] = $detalheVeiculo;
            if ($cobrancaNestaDevolucao) {
                $detalhesVeiculosDevolucao[] = $detalheVeiculo;
            }
        }

        $totalVeiculos = round($totalVeiculos, 2);
        $totalSeguros = round($totalSeguros, 2);
        $totalLocacao = round($totalVeiculos + $totalSeguros, 2);
        $encerramentoFinal = $ativosRestantes === 0 && !empty($veiculosProjetados);

        $taxasContrato = array_values(array_filter(
            $taxas,
            static fn(array $taxa): bool => ($taxa['origem'] ?? 'contrato') !== 'devolucao'
        ));
        $taxasDevolucaoPersistidas = array_values(array_filter(
            $taxas,
            static fn(array $taxa): bool => ($taxa['origem'] ?? 'contrato') === 'devolucao'
        ));

        $periodoContrato = $encerramentoFinal && !empty($contrato['data_ini']) && $dataEncerramento
            ? $this->calcularPeriodo((string) $contrato['data_ini'], $dataEncerramento, $contagem)
            : ['ciclos_completos' => 0, 'dias_restantes' => 0, 'dias_equivalentes' => 0];

        $totalTaxasContrato = 0.0;
        $detalhesTaxas = [];
        if ($encerramentoFinal) {
            foreach ($taxasContrato as $taxa) {
                $valor = round($this->calcularTaxa(
                    $taxa,
                    (int) $periodoContrato['dias_equivalentes'],
                    $totalLocacao
                ), 2);
                $totalTaxasContrato += $valor;
                $detalhesTaxas[] = [
                    'id' => (int) ($taxa['id'] ?? 0),
                    'nome' => $taxa['nome'] ?? '',
                    'base_calculo' => $taxa['base_calculo'] ?? 'FIX',
                    'tipo_valor' => $taxa['tipo_valor'] ?? 'MON',
                    'valor_original' => round((float) ($taxa['valor_total'] ?? 0), 2),
                    'valor_final' => $valor,
                ];
            }
        }
        $totalTaxasContrato = round($totalTaxasContrato, 2);

        $totalTaxasDevolucao = 0.0;
        if ($encerramentoFinal) {
            foreach ($taxasDevolucaoPersistidas as $taxa) {
                $totalTaxasDevolucao += (float) ($taxa['valor_total'] ?? 0);
            }
        }
        foreach ($taxasExtras as $taxa) {
            $totalTaxasDevolucao += (float) ($taxa['valor_total'] ?? 0);
        }
        $totalTaxasDevolucao = round($totalTaxasDevolucao, 2);

        $totalAdicionais = round($totalKm + $totalCombustivel + $totalTaxasDevolucao, 2);
        $descontoOriginal = round((float) ($contrato['valor_desconto'] ?? 0), 2);
        $aluguelOriginal = max(
            0.0,
            round((float) ($contrato['total_pagar'] ?? 0) + $descontoOriginal - $this->somarTaxasOriginais($taxasContrato), 2)
        );
        $proporcaoDesconto = $aluguelOriginal > 0
            ? min(1.0, $totalLocacao / $aluguelOriginal)
            : 0.0;
        $descontoAplicado = $encerramentoFinal
            ? round($descontoOriginal * $proporcaoDesconto, 2)
            : 0.0;
        $totalFinal = $encerramentoFinal
            ? round(max(0, $totalLocacao + $totalTaxasContrato + $totalAdicionais - $descontoAplicado), 2)
            : $totalAdicionais;
        $diferenca = $encerramentoFinal
            ? round($totalFinal - $principalLancado, 2)
            : round($totalAdicionais, 2);

        return [
            'encerramento_final' => $encerramentoFinal,
            'ativos_restantes' => $ativosRestantes,
            'data_encerramento' => $dataEncerramento,
            'contagem' => $contagem,
            'base_dias' => $baseDias,
            'periodo_contrato' => $periodoContrato,
            'veiculos' => $detalhesVeiculosDevolucao,
            'veiculos_historico_calculo' => $detalhesVeiculos,
            'taxas' => $detalhesTaxas,
            'total_original' => round((float) ($contrato['total_pagar'] ?? 0), 2),
            'total_veiculos' => $totalVeiculos,
            'total_seguros' => $totalSeguros,
            'total_locacao' => $totalLocacao,
            'total_taxas_contrato' => $totalTaxasContrato,
            'total_km' => round($totalKm, 2),
            'total_combustivel' => round($totalCombustivel, 2),
            'total_taxas_devolucao' => $totalTaxasDevolucao,
            'total_adicionais_devolucao' => $totalAdicionais,
            'desconto_original' => $descontoOriginal,
            'desconto_aplicado' => $descontoAplicado,
            'total_final' => $totalFinal,
            'principal_lancado' => round($principalLancado, 2),
            'diferenca' => $diferenca,
            'ajuste_tipo' => $diferenca > 0.009 ? 'R' : ($diferenca < -0.009 ? 'D' : 'N'),
            'ajuste_valor' => abs($diferenca) > 0.009 ? abs($diferenca) : 0.0,
        ];
    }

    public function baseDias(string $contagem): int
    {
        return match ($contagem) {
            'semana' => 7,
            'mes' => 30,
            'ano' => 365,
            default => 1,
        };
    }

    /** @return array{ciclos_completos:int,dias_restantes:int,dias_equivalentes:int} */
    public function calcularPeriodo(string $dataSaida, string $dataEntrada, string $contagem): array
    {
        $saida = DateHelper::parseOperationalDateTime($dataSaida);
        $entrada = DateHelper::parseOperationalDateTime($dataEntrada);
        if ($entrada <= $saida) {
            return ['ciclos_completos' => 0, 'dias_restantes' => 0, 'dias_equivalentes' => 0];
        }

        $segundos = $entrada->getTimestamp() - $saida->getTimestamp();
        $diasCompletos = max(0, intdiv($segundos, 86400));
        $baseDias = $this->baseDias($contagem);

        if ($contagem === 'dia') {
            return [
                'ciclos_completos' => $diasCompletos,
                'dias_restantes' => 0,
                'dias_equivalentes' => $diasCompletos,
            ];
        }
        if ($contagem === 'semana') {
            $ciclos = intdiv($diasCompletos, 7);
            $restantes = $diasCompletos % 7;
            return [
                'ciclos_completos' => $ciclos,
                'dias_restantes' => $restantes,
                'dias_equivalentes' => ($ciclos * 7) + $restantes,
            ];
        }

        $ciclos = 0;
        $cursor = $saida;
        while ($ciclos < 10000) {
            $proximo = DateHelper::addOperationalCalendarPeriod($cursor, $contagem);
            if ($proximo > $entrada) {
                break;
            }
            $cursor = $proximo;
            $ciclos++;
        }
        $diasRestantes = max(0, intdiv($entrada->getTimestamp() - $cursor->getTimestamp(), 86400));

        return [
            'ciclos_completos' => $ciclos,
            'dias_restantes' => $diasRestantes,
            'dias_equivalentes' => ($ciclos * $baseDias) + $diasRestantes,
        ];
    }

    private function valorPorPeriodo(float $valor, array $periodo, int $baseDias): float
    {
        return round(
            ($valor * (int) $periodo['ciclos_completos'])
            + (($valor / $baseDias) * (int) $periodo['dias_restantes']),
            2
        );
    }

    private function valorPlano(array $veiculo): float
    {
        return match ($veiculo['plano'] ?? 'KL') {
            'KMC' => (float) ($veiculo['valor_plano_km_controlado'] ?? 0),
            'KP' => (float) ($veiculo['valor_plano_km_pago'] ?? 0),
            default => (float) ($veiculo['valor_plano_km_livre'] ?? 0),
        };
    }

    private function valorSeguros(array $veiculo): float
    {
        return round(
            (($veiculo['seguro_carro'] ?? false) ? (float) ($veiculo['valor_seguro_carro'] ?? 0) : 0)
            + (($veiculo['seguro_terceiros'] ?? false) ? (float) ($veiculo['valor_seguro_terceiros'] ?? 0) : 0),
            2
        );
    }

    private function calcularKm(array $veiculo, int $diasEquivalentes, int $baseDias): array
    {
        $rodados = max(0, (int) ($veiculo['odometro_entrada'] ?? 0) - (int) ($veiculo['odometro_saida'] ?? 0));
        $valorKm = (float) ($veiculo['valor_km_excedente'] ?? 0);
        $franquia = 0;
        $excedente = 0;
        if (($veiculo['plano'] ?? '') === 'KMC') {
            $franquia = (int) ceil(((int) ($veiculo['km_franquia'] ?? 0) / $baseDias) * $diasEquivalentes);
            $excedente = max(0, $rodados - $franquia);
        } elseif (($veiculo['plano'] ?? '') === 'KP') {
            $excedente = $rodados;
        }

        return [
            'rodados' => $rodados,
            'franquia' => $franquia,
            'excedente' => $excedente,
            'valor_unitario' => $valorKm,
            'valor' => round($excedente * $valorKm, 2),
        ];
    }

    private function calcularCombustivel(array $veiculo): array
    {
        $saida = (int) ($veiculo['combustivel_saida'] ?? 0);
        $entradaInformada = array_key_exists('combustivel_entrada', $veiculo)
            && $veiculo['combustivel_entrada'] !== null
            && $veiculo['combustivel_entrada'] !== '';
        $entrada = $entradaInformada ? (int) $veiculo['combustivel_entrada'] : $saida;
        $fracoes = max(0, $saida - $entrada);
        $valorFracao = (float) ($veiculo['veiculo_valor_por_fracao'] ?? $veiculo['valor_por_fracao'] ?? 0);

        return [
            'fracoes' => $fracoes,
            'valor_unitario' => $valorFracao,
            'valor' => round($fracoes * $valorFracao, 2),
        ];
    }

    private function somarTaxasOriginais(array $taxas): float
    {
        return round(array_reduce(
            $taxas,
            static fn(float $total, array $taxa): float => $total + (float) ($taxa['valor_total'] ?? 0),
            0.0
        ), 2);
    }

    private function calcularTaxa(array $taxa, int $qtdPeriodos, float $valorTotalVeiculos): float
    {
        $valor = (float) ($taxa['valor_unitario'] ?? 0);
        $baseCalculo = $taxa['base_calculo'] ?? 'FIX';
        $tipoValor = $taxa['tipo_valor'] ?? 'MON';
        $quantidade = (int) ($taxa['quantidade'] ?? 1);

        if ($tipoValor === 'POR') {
            $valorBase = $baseCalculo === 'VLT'
                ? $valorTotalVeiculos * ($valor / 100)
                : (($qtdPeriodos > 0 ? $valorTotalVeiculos / $qtdPeriodos : 0) * ($valor / 100));
        } else {
            $valorBase = $valor;
        }

        return $baseCalculo === 'PER'
            ? $valorBase * $quantidade * $qtdPeriodos
            : $valorBase * $quantidade;
    }
}
