<?php

use App\Database\Migration;

/**
 * Reclassifica ajustes pontuais de substituicao que foram gravados como taxas
 * contratuais e remove esses valores da base recorrente de contratos ativos.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contratos', 'contratos_veiculos', 'contratos_taxaseservicos'] as $tabela) {
            if (!$this->tableExists($tabela)) {
                throw new RuntimeException("Tabela {$tabela} nao encontrada");
            }
        }

        $qb = $this->db();
        $qb->beginTransaction();

        try {
            $ajustes = $qb
                ->withoutChave()
                ->table('contratos_taxaseservicos')
                ->select(['id', 'id_contrato', 'chave'])
                ->where('origem', '=', 'contrato')
                ->whereRaw("(
                    nome LIKE 'Diferenca combustivel - Substituicao [%]'
                    OR nome LIKE 'Km excedente - Substituicao [%]'
                    OR nome LIKE 'Km rodados - Substituicao [%]'
                )")
                ->orderBy('id', 'ASC')
                ->get();

            $contratosAfetados = [];
            $reclassificados = 0;

            foreach ($ajustes as $ajuste) {
                $idContrato = (int) $ajuste['id_contrato'];
                $contratosAfetados[$idContrato] = $idContrato;

                $reclassificados += $qb
                    ->withoutChave()
                    ->table('contratos_taxaseservicos')
                    ->where('id', '=', (int) $ajuste['id'])
                    ->where('origem', '=', 'contrato')
                    ->update(['origem' => 'devolucao']);
            }

            $totaisCorrigidos = 0;
            foreach ($contratosAfetados as $idContrato) {
                $contrato = $qb
                    ->withoutChave()
                    ->table('contratos')
                    ->select(['id', 'chave', 'status', 'dias', 'contagem', 'valor_desconto'])
                    ->where('id', '=', $idContrato)
                    ->first();

                if (!$contrato || ($contrato['status'] ?? '') !== 'A') {
                    continue;
                }

                $veiculos = $qb
                    ->withoutChave()
                    ->table('contratos_veiculos')
                    ->select([
                        'plano',
                        'valor_plano_km_pago',
                        'valor_plano_km_livre',
                        'valor_plano_km_controlado',
                        'seguro_carro',
                        'valor_seguro_carro',
                        'seguro_terceiros',
                        'valor_seguro_terceiros',
                    ])
                    ->where('id_contrato', '=', (int) $contrato['id'])
                    ->whereNull('data_entrada')
                    ->get();

                $valorPorPeriodo = 0.0;
                foreach ($veiculos as $veiculo) {
                    $valorPorPeriodo += $this->calcularValorVeiculo($veiculo);
                }

                $quantidadePeriodos = max(1, (int) ($contrato['dias'] ?? 1));
                $totalVeiculos = round($valorPorPeriodo * $quantidadePeriodos, 2);
                $multiplicadores = ['dia' => 1, 'semana' => 7, 'mes' => 30, 'ano' => 365];
                $diasReais = $quantidadePeriodos * ($multiplicadores[$contrato['contagem'] ?? 'dia'] ?? 1);

                $taxas = $qb
                    ->withoutChave()
                    ->table('contratos_taxaseservicos')
                    ->select(['base_calculo', 'tipo_valor', 'quantidade', 'valor_unitario'])
                    ->where('id_contrato', '=', (int) $contrato['id'])
                    ->where('origem', '=', 'contrato')
                    ->get();

                $totalTaxas = 0.0;
                foreach ($taxas as $taxa) {
                    $totalTaxas += $this->calcularValorTaxa($taxa, $diasReais, $totalVeiculos);
                }

                $totalFatura = round($totalVeiculos + $totalTaxas, 2);
                $totalPagar = round($totalFatura - (float) ($contrato['valor_desconto'] ?? 0), 2);

                $totaisCorrigidos += $qb
                    ->withoutChave()
                    ->table('contratos')
                    ->where('id', '=', (int) $contrato['id'])
                    ->update([
                        'total_fatura' => $totalFatura,
                        'total_pagar' => $totalPagar,
                    ]);
            }

            $qb->commit();
            echo "Ajustes de substituicao reclassificados: {$reclassificados}\n";
            echo "Contratos ativos com totais corrigidos: {$totaisCorrigidos}\n";
        } catch (Throwable $e) {
            $qb->rollback();
            throw $e;
        }
    }

    public function down(): void
    {
        // Correcao de dados intencionalmente irreversivel. Reclassificar os
        // ajustes novamente como recorrentes restauraria o defeito corrigido.
    }

    private function calcularValorVeiculo(array $veiculo): float
    {
        $valorPlano = match ($veiculo['plano'] ?? 'KL') {
            'KL' => (float) ($veiculo['valor_plano_km_livre'] ?? 0),
            'KMC' => (float) ($veiculo['valor_plano_km_controlado'] ?? 0),
            default => (float) ($veiculo['valor_plano_km_pago'] ?? 0),
        };
        $seguroCarro = !empty($veiculo['seguro_carro'])
            ? (float) ($veiculo['valor_seguro_carro'] ?? 0)
            : 0.0;
        $seguroTerceiros = !empty($veiculo['seguro_terceiros'])
            ? (float) ($veiculo['valor_seguro_terceiros'] ?? 0)
            : 0.0;

        return $valorPlano + $seguroCarro + $seguroTerceiros;
    }

    private function calcularValorTaxa(array $taxa, int $diasReais, float $totalVeiculos): float
    {
        $valor = (float) ($taxa['valor_unitario'] ?? 0);
        $quantidade = (int) ($taxa['quantidade'] ?? 1);
        $baseCalculo = $taxa['base_calculo'] ?? 'FIX';

        if (($taxa['tipo_valor'] ?? 'MON') === 'POR') {
            $base = $baseCalculo === 'VLT'
                ? $totalVeiculos
                : ($diasReais > 0 ? $totalVeiculos / $diasReais : 0);
            $valor = $base * ($valor / 100);
        }

        if ($baseCalculo === 'PER') {
            $valor *= $diasReais;
        }

        return round($valor * $quantidade, 2);
    }
};
