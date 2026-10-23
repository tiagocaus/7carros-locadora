<?php

use App\Database\Migration;

/**
 * Corrige taxas de locacoes criadas pelo fluxo que calculava o total da
 * locacao com a formula completa, mas persistia quantidade x valor_unitario
 * no detalhe da taxa.
 *
 * Registros finalizados e o legado anterior ao fluxo afetado sao preservados.
 */
return new class extends Migration
{
    private const FLUXO_AFETADO_DESDE = '2026-06-19 00:00:00';

    public function up(): void
    {
        foreach (['locacoes', 'locacoes_veiculos', 'locacoes_taxaseservicos'] as $tabela) {
            if (!$this->tableExists($tabela)) {
                throw new RuntimeException("Tabela {$tabela} nao encontrada");
            }
        }

        $qb = $this->db();
        $qb->beginTransaction();

        try {
            $taxas = $qb
                ->table('locacoes_taxaseservicos', 'lts')
                ->withoutChave()
                ->select([
                    'lts.id',
                    'lts.id_locacao',
                    'lts.base_calculo',
                    'lts.tipo_valor',
                    'lts.quantidade',
                    'lts.valor_unitario',
                    'lts.valor_total',
                    'l.dias',
                ])
                ->join('locacoes', 'l', 'l.id', '=', 'lts.id_locacao')
                ->whereRaw('l.chave = lts.chave')
                ->whereIn('l.status', ['P', 'R', 'A'])
                ->where('lts.created_at', '>=', self::FLUXO_AFETADO_DESDE)
                ->whereRaw("(
                    (lts.tipo_valor = 'POR' AND lts.valor_unitario > 0)
                    OR (lts.tipo_valor = 'MON' AND lts.base_calculo = 'PER' AND l.dias > 1)
                )")
                ->whereRaw('ABS(lts.valor_total - (lts.valor_unitario * lts.quantidade)) < 0.009')
                ->orderBy('lts.id', 'ASC')
                ->get();

            $corrigidas = 0;
            foreach ($taxas as $taxa) {
                $veiculo = $qb
                    ->table('locacoes_veiculos')
                    ->withoutChave()
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
                    ->where('id_locacao', '=', (int) $taxa['id_locacao'])
                    ->whereNull('data_entrada')
                    ->orderBy('data_saida', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                if (!$veiculo) {
                    continue;
                }

                $dias = max(1, (int) $taxa['dias']);
                $valorDiario = $this->calcularValorDiario($veiculo);
                $valorTotalVeiculo = $valorDiario * $dias;
                $valorTotalTaxa = $this->calcularValorTotalTaxa($taxa, $dias, $valorTotalVeiculo);

                if (abs($valorTotalTaxa - (float) $taxa['valor_total']) <= 0.009) {
                    continue;
                }

                $afetadas = $qb
                    ->table('locacoes_taxaseservicos')
                    ->withoutChave()
                    ->where('id', '=', (int) $taxa['id'])
                    ->whereRaw('ABS(valor_total - (valor_unitario * quantidade)) < 0.009')
                    ->update(['valor_total' => $valorTotalTaxa]);

                $corrigidas += $afetadas;
            }

            $qb->commit();
            echo "Taxas de locacoes corrigidas: {$corrigidas}\n";
        } catch (Throwable $e) {
            $qb->rollback();
            throw $e;
        }
    }

    public function down(): void
    {
        // Correcao de dados intencionalmente irreversivel: o rollback nao deve
        // restaurar totais que nao representam a formula contratada.
    }

    private function calcularValorDiario(array $veiculo): float
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

    private function calcularValorTotalTaxa(array $taxa, int $dias, float $valorTotalVeiculo): float
    {
        $valor = (float) ($taxa['valor_unitario'] ?? 0);
        $quantidade = max(1, (int) ($taxa['quantidade'] ?? 1));
        $baseCalculo = $taxa['base_calculo'] ?? 'FIX';

        if (($taxa['tipo_valor'] ?? 'MON') === 'POR') {
            $base = $baseCalculo === 'VLT'
                ? $valorTotalVeiculo
                : $valorTotalVeiculo / $dias;
            $valor = $base * ($valor / 100);
        }

        if ($baseCalculo === 'PER') {
            $valor *= $dias;
        }

        return round($valor * $quantidade, 2);
    }
};
