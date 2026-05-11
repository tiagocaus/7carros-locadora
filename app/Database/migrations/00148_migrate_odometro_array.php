<?php

use App\Database\Migration;

/**
 * Migration: Migrar odometro_array para contratos_odometros
 *
 * Migra ~9.486 registros de odômetro do JSON para tabela normalizada.
 * Depende da migração 00146 (contratos_veiculos deve estar populada).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar contratos com odometro_array preenchido
        $contratos = $this->db()
            ->table('contratos')
            ->select(['id', 'chave', 'id_veiculo', 'odometro_array'])
            ->whereRaw('odometro_array IS NOT NULL')
            ->whereRaw("odometro_array != ''")
            ->whereRaw("odometro_array != '[]'")
            ->get();

        $migrados = 0;
        $erros = 0;
        $nao_encontrados = 0;

        foreach ($contratos as $contrato) {
            $odometros = json_decode($contrato['odometro_array'], true);

            if (!is_array($odometros) || empty($odometros)) {
                continue;
            }

            // Buscar o id do contratos_veiculos correspondente
            // Pegar o mais recente (sem data_saida ou com maior data_entrada)
            $contrato_veiculo = $this->db()
                ->table('contratos_veiculos')
                ->select(['id'])
                ->where('id_contrato', '=', $contrato['id'])
                ->where('id_veiculo', '=', $contrato['id_veiculo'])
                ->orderBy('data_entrada', 'DESC')
                ->first();

            if (!$contrato_veiculo) {
                $nao_encontrados++;
                error_log("Contrato {$contrato['id']}: Veículo {$contrato['id_veiculo']} não encontrado em contratos_veiculos");
                continue;
            }

            foreach ($odometros as $item) {
                // Estrutura: {"data": "2020-11-23", "odometro": 38503, "diferenca": 8643}
                $data = $item['data'] ?? null;
                $odometro = $item['odometro'] ?? null;
                $diferenca = $item['diferenca'] ?? null;

                if (!$data || !$odometro) {
                    continue;
                }

                // Verificar se já existe (evitar duplicatas)
                $existe = $this->db()
                    ->table('contratos_odometros')
                    ->where('id_contrato', '=', $contrato['id'])
                    ->where('id_contrato_veiculo', '=', $contrato_veiculo['id'])
                    ->where('data', '=', $data)
                    ->first();

                if ($existe) {
                    continue;
                }

                $dados = [
                    'chave' => $contrato['chave'],
                    'id_contrato' => $contrato['id'],
                    'id_contrato_veiculo' => $contrato_veiculo['id'],
                    'data' => $data,
                    'odometro' => (int) $odometro,
                    'diferenca' => $diferenca ? (int) $diferenca : null,
                    'obs' => $item['obs'] ?? null,
                ];

                try {
                    $this->db()->table('contratos_odometros')->insert($dados);
                    $migrados++;
                } catch (\Exception $e) {
                    $erros++;
                    error_log("Erro migração odômetro contrato {$contrato['id']}: " . $e->getMessage());
                }
            }
        }

        echo "Registros de odômetro migrados: {$migrados}\n";
        if ($nao_encontrados > 0) {
            echo "Contratos não encontrados em contratos_veiculos: {$nao_encontrados}\n";
        }
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não é possível reverter - dados já migrados
    }
};
