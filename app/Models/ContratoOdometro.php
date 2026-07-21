<?php

namespace App\Models;

/**
 * Historico de leituras de odometro durante contratos.
 */
class ContratoOdometro extends Model
{
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('contratos_odometros')
            ->where('id', '=', $id)
            ->first();
    }

    public function ultimaPorContratoVeiculo(int $contratoVeiculoId): ?array
    {
        return $this->qb
            ->table('contratos_odometros')
            ->where('id_contrato_veiculo', '=', $contratoVeiculoId)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->first();
    }

    public function listarPorContrato(int $contratoId): array
    {
        return $this->qb
            ->table('contratos_odometros', 'co')
            ->select([
                'co.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
            ])
            ->leftJoin('contratos_veiculos', 'cv', 'co.id_contrato_veiculo', '=', 'cv.id')
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->where('co.id_contrato', '=', $contratoId)
            ->orderByDesc('co.data')
            ->orderByDesc('co.id')
            ->get();
    }

    public function listarPorContratoVeiculo(int $contratoVeiculoId, bool $bloquearParaAtualizacao = false): array
    {
        $query = $this->qb
            ->table('contratos_odometros')
            ->where('id_contrato_veiculo', '=', $contratoVeiculoId)
            ->orderBy('data')
            ->orderBy('id');

        if ($bloquearParaAtualizacao) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function listarUltimosPorContratoVeiculo(int $contratoVeiculoId, int $limite = 5): array
    {
        return $this->qb
            ->table('contratos_odometros')
            ->where('id_contrato_veiculo', '=', $contratoVeiculoId)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(max(1, min(20, $limite)))
            ->get();
    }

    public function registrarLeitura(array $dados): array
    {
        $contratoVeiculoId = (int) $dados['id_contrato_veiculo'];
        $data = $dados['data'] ?? today();
        $odometro = (int) $dados['odometro'];
        $odometroSaida = (int) ($dados['odometro_saida'] ?? 0);
        $createdAt = $dados['created_at'] ?? now();

        $this->qb->beginTransaction();

        try {
            $leituraAnterior = $this->ultimaPorContratoVeiculo($contratoVeiculoId);
            $baseDiferenca = max($odometroSaida, (int) ($leituraAnterior['odometro'] ?? 0));
            $diferenca = max(0, $odometro - $baseDiferenca);

            $id = $this->qb
                ->table('contratos_odometros')
                ->insert([
                    'chave' => $dados['chave'],
                    'id_contrato' => (int) $dados['id_contrato'],
                    'id_contrato_veiculo' => $contratoVeiculoId,
                    'data' => $data,
                    'odometro' => $odometro,
                    'diferenca' => $diferenca,
                    'obs' => $dados['obs'] ?? null,
                    'id_funcionario' => $dados['id_funcionario'] ?? null,
                    'created_at' => $createdAt,
                ]);

            $this->qb
                ->table('veiculos')
                ->where('id', '=', (int) $dados['id_veiculo'])
                ->update(['odometro' => $odometro]);

            $this->qb->commit();

            return [
                'id' => $id,
                'data' => $data,
                'odometro' => $odometro,
                'diferenca' => $diferenca,
                'created_at' => $createdAt,
            ];
        } catch (\Throwable $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Corrige uma leitura e recompõe toda a sequência de diferenças do veículo.
     *
     * @return array{success: bool, alterado?: bool, error?: string, reference?: int, antigo?: array, novo?: array, historico?: array, odometro_veiculo?: int}
     */
    public function editarLeitura(int $id, array $dados): array
    {
        $this->qb->beginTransaction();

        try {
            $historicoOriginal = $this->listarPorContratoVeiculo(
                (int) $dados['id_contrato_veiculo'],
                true
            );
            $leitura = array_values(array_filter(
                $historicoOriginal,
                static fn(array $item): bool => (int) $item['id'] === $id
            ))[0] ?? null;
            if (!$leitura
                || (int) $leitura['id_contrato'] !== (int) $dados['id_contrato']
                || (int) $leitura['id_contrato_veiculo'] !== (int) $dados['id_contrato_veiculo']) {
                $this->qb->rollback();
                return ['success' => false, 'error' => 'not_found'];
            }

            $data = (string) $dados['data'];
            $odometro = (int) $dados['odometro'];
            $obs = $dados['obs'] ?? null;
            $obsAntiga = trim((string) ($leitura['obs'] ?? ''));
            $obsAntiga = $obsAntiga !== '' ? $obsAntiga : null;
            $alterado = (string) $leitura['data'] !== $data
                || (int) $leitura['odometro'] !== $odometro
                || $obsAntiga !== $obs;

            if (!$alterado) {
                $veiculo = $this->qb
                    ->table('veiculos')
                    ->where('id', '=', (int) $dados['id_veiculo'])
                    ->first();
                $this->qb->commit();

                return [
                    'success' => true,
                    'alterado' => false,
                    'antigo' => $leitura,
                    'novo' => $leitura,
                    'historico' => array_slice(array_reverse($historicoOriginal), 0, 5),
                    'odometro_veiculo' => (int) ($veiculo['odometro'] ?? 0),
                ];
            }

            $historicoNovo = array_map(static function (array $item) use ($id, $data, $odometro, $obs, $dados): array {
                if ((int) $item['id'] === $id) {
                    $item['data'] = $data;
                    $item['odometro'] = $odometro;
                    $item['obs'] = $obs;
                    $item['id_funcionario'] = $dados['id_funcionario'] ?? null;
                }
                return $item;
            }, $historicoOriginal);

            usort($historicoNovo, static function (array $a, array $b): int {
                $porData = strcmp((string) $a['data'], (string) $b['data']);
                return $porData !== 0 ? $porData : ((int) $a['id'] <=> (int) $b['id']);
            });

            $anterior = (int) $dados['odometro_saida'];
            foreach ($historicoNovo as $item) {
                $valor = (int) $item['odometro'];
                if ($valor < $anterior) {
                    $this->qb->rollback();
                    return [
                        'success' => false,
                        'error' => (int) $item['id'] === $id ? 'lower_than_previous' : 'higher_than_next',
                        'reference' => (int) $item['id'] === $id ? $anterior : $valor,
                    ];
                }
                $anterior = $valor;
            }

            $ultimaOriginal = end($historicoOriginal) ?: null;
            $ultimaNova = end($historicoNovo) ?: null;

            $this->qb
                ->table('contratos_odometros')
                ->where('id', '=', $id)
                ->update([
                    'data' => $data,
                    'odometro' => $odometro,
                    'obs' => $obs,
                    'id_funcionario' => $dados['id_funcionario'] ?? null,
                ]);

            $base = (int) $dados['odometro_saida'];
            foreach ($historicoNovo as &$item) {
                $diferenca = max(0, (int) $item['odometro'] - $base);
                $item['diferenca'] = $diferenca;
                $this->qb
                    ->table('contratos_odometros')
                    ->where('id', '=', (int) $item['id'])
                    ->update(['diferenca' => $diferenca]);
                $base = (int) $item['odometro'];
            }
            unset($item);

            $veiculo = $this->qb
                ->table('veiculos')
                ->where('id', '=', (int) $dados['id_veiculo'])
                ->first();
            $odometroVeiculo = (int) ($veiculo['odometro'] ?? 0);
            $odometroUltimoOriginal = (int) ($ultimaOriginal['odometro'] ?? 0);
            $odometroUltimoNovo = (int) ($ultimaNova['odometro'] ?? $dados['odometro_saida']);
            $novoOdometroVeiculo = $odometroVeiculo === $odometroUltimoOriginal
                ? $odometroUltimoNovo
                : max($odometroVeiculo, $odometroUltimoNovo);

            if ($veiculo && $novoOdometroVeiculo !== $odometroVeiculo) {
                $this->qb
                    ->table('veiculos')
                    ->where('id', '=', (int) $dados['id_veiculo'])
                    ->update(['odometro' => $novoOdometroVeiculo]);
            }

            $this->qb->commit();

            $historicoDesc = array_reverse($historicoNovo);
            return [
                'success' => true,
                'alterado' => true,
                'antigo' => $leitura,
                'novo' => array_values(array_filter(
                    $historicoNovo,
                    static fn(array $item): bool => (int) $item['id'] === $id
                ))[0],
                'historico' => array_slice($historicoDesc, 0, 5),
                'odometro_veiculo' => $novoOdometroVeiculo,
            ];
        } catch (\Throwable $e) {
            $this->qb->rollback();
            throw $e;
        }
    }
}
