<?php

namespace App\Models;

/**
 * Historico de leituras de odometro durante contratos.
 */
class ContratoOdometro extends Model
{
    public function ultimaPorContratoVeiculo(int $contratoVeiculoId): ?array
    {
        return $this->qb
            ->table('contratos_odometros')
            ->where('id_contrato_veiculo', '=', $contratoVeiculoId)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->first();
    }

    public function ultimaAntesData(int $contratoVeiculoId, string $data): ?array
    {
        return $this->qb
            ->table('contratos_odometros')
            ->where('id_contrato_veiculo', '=', $contratoVeiculoId)
            ->where('data', '<', $data)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->first();
    }

    public function buscarPorData(int $contratoVeiculoId, string $data): ?array
    {
        return $this->qb
            ->table('contratos_odometros')
            ->where('id_contrato_veiculo', '=', $contratoVeiculoId)
            ->where('data', '=', $data)
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

    public function registrarLeitura(array $dados): array
    {
        $contratoVeiculoId = (int) $dados['id_contrato_veiculo'];
        $data = $dados['data'] ?? today();
        $odometro = (int) $dados['odometro'];
        $odometroSaida = (int) ($dados['odometro_saida'] ?? 0);

        $this->qb->beginTransaction();

        try {
            $leituraAnterior = $this->ultimaAntesData($contratoVeiculoId, $data);
            $baseDiferenca = max($odometroSaida, (int) ($leituraAnterior['odometro'] ?? 0));
            $diferenca = max(0, $odometro - $baseDiferenca);
            $existente = $this->buscarPorData($contratoVeiculoId, $data);

            $payload = [
                'odometro' => $odometro,
                'diferenca' => $diferenca,
                'obs' => $dados['obs'] ?? null,
                'id_funcionario' => $dados['id_funcionario'] ?? null,
            ];

            if ($existente) {
                $this->qb
                    ->table('contratos_odometros')
                    ->where('id', '=', (int) $existente['id'])
                    ->update($payload);
                $id = (int) $existente['id'];
            } else {
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
                    ]);
            }

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
            ];
        } catch (\Throwable $e) {
            $this->qb->rollback();
            throw $e;
        }
    }
}
