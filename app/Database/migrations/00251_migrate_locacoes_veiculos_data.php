<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados de veículos de locacoes para locacoes_veiculos
 *
 * Migra ~90k registros com veículo para a nova tabela normalizada.
 * Processa em batches de 2000 para não estourar memória.
 * Converte nomes de colunas e formatos (S/N → 1/0, DI → KP).
 */
return new class extends Migration
{
    public function up(): void
    {
        $batchSize = 2000;
        $offset = 0;
        $migrados = 0;
        $erros = 0;

        do {
            $locacoes = $this->db()
                ->table('locacoes')
                ->select([
                    'id', 'chave', 'status', 'id_veiculo', 'id_grupo', 'plano',
                    'data_saida', 'data_chegada',
                    'diaria_valor', 'km_livre_valor', 'km_valor',
                    'km_controlado_valor', 'km_controlado_franquia',
                    'minuto_tolerancia', 'valor_tolerancia',
                    'valor_km_retorno', 'valor_condutor_adicional',
                    'seguro_carro', 'seguro_carro_valor', 'cobertura_carro_valor',
                    'seguro_terceiros', 'seguro_terceiros_valor', 'cobertura_terceiros_valor',
                    'odometro_ini', 'odometro_fim', 'odometro_usado',
                    'combustivel_ini', 'combustivel_fim', 'combustivel_usado', 'combustivel_valor',
                    'kmlExcedente'
                ])
                ->whereRaw('id_veiculo IS NOT NULL AND id_veiculo > 0')
                ->orderBy('id', 'ASC')
                ->limit($batchSize)
                ->offset($offset)
                ->get();

            foreach ($locacoes as $loc) {
                // Verificar se já existe (idempotência)
                $existe = $this->db()
                    ->table('locacoes_veiculos')
                    ->where('id_locacao', '=', $loc['id'])
                    ->where('id_veiculo', '=', $loc['id_veiculo'])
                    ->first();

                if ($existe) {
                    continue;
                }

                // Mapear plano: DI/DIA → KP
                $plano = $loc['plano'] ?? 'KL';
                if (in_array($plano, ['DI', 'DIA', ''], true)) {
                    $plano = 'KP';
                }

                $dados = [
                    'chave' => $loc['chave'],
                    'id_locacao' => $loc['id'],
                    'id_veiculo' => $loc['id_veiculo'],
                    'id_grupo' => $loc['id_grupo'] ?: null,

                    // data_saida da locação = quando veículo saiu da garagem = data_entrada no uso
                    'data_entrada' => $loc['data_saida'],
                    // data_chegada = quando devolveu (só se fechado)
                    'data_saida' => $loc['status'] === 'F' ? $loc['data_chegada'] : null,

                    'plano' => $plano,

                    // Mapeamento de valores
                    'valor_plano_km_pago' => (float) ($loc['diaria_valor'] ?? 0),
                    'valor_plano_km_livre' => (float) ($loc['km_livre_valor'] ?? 0),
                    'valor_plano_km_controlado' => (float) ($loc['km_controlado_valor'] ?? 0),
                    'km_franquia' => $loc['km_controlado_franquia'] ?: null,
                    'valor_km_excedente' => (float) ($loc['km_valor'] ?? 0),
                    'minutos_tolerancia' => max(0, (int) ($loc['minuto_tolerancia'] ?? 0)),
                    'valor_tolerancia' => (float) ($loc['valor_tolerancia'] ?? 0),
                    'valor_km_retorno' => (float) ($loc['valor_km_retorno'] ?? 0),
                    'valor_condutor_adicional' => (float) ($loc['valor_condutor_adicional'] ?? 0),

                    // Seguros: S/N → 1/0
                    'seguro_carro' => ($loc['seguro_carro'] ?? 'N') === 'S' ? 1 : 0,
                    'valor_seguro_carro' => (float) ($loc['seguro_carro_valor'] ?? 0),
                    'cobertura_carro' => (float) ($loc['cobertura_carro_valor'] ?? 0),
                    'seguro_terceiros' => ($loc['seguro_terceiros'] ?? 'N') === 'S' ? 1 : 0,
                    'valor_seguro_terceiros' => (float) ($loc['seguro_terceiros_valor'] ?? 0),
                    'cobertura_terceiros' => (float) ($loc['cobertura_terceiros_valor'] ?? 0),

                    // Odômetro e combustível (UNSIGNED: garantir >= 0)
                    'odometro_entrada' => max(0, (int) ($loc['odometro_ini'] ?? 0)),
                    'odometro_saida' => $loc['odometro_fim'] ? max(0, (int) $loc['odometro_fim']) : null,
                    'combustivel_entrada' => $loc['combustivel_ini'] !== null ? (int) $loc['combustivel_ini'] : null,
                    'combustivel_saida' => $loc['combustivel_fim'] !== null ? (int) $loc['combustivel_fim'] : null,

                    // Dados calculados na devolução (UNSIGNED: valores negativos legado → NULL)
                    'odometro_usado' => $loc['odometro_usado'] && (int) $loc['odometro_usado'] >= 0
                        ? (int) $loc['odometro_usado'] : null,
                    'km_excedente' => $loc['kmlExcedente'] && (int) $loc['kmlExcedente'] >= 0
                        ? (int) $loc['kmlExcedente'] : null,
                    'combustivel_usado' => $loc['combustivel_usado'] !== null && $loc['combustivel_usado'] !== ''
                        ? (int) $loc['combustivel_usado']
                        : null,
                    'combustivel_valor' => $loc['combustivel_valor'] ? (float) $loc['combustivel_valor'] : null,
                ];

                try {
                    $this->db()->table('locacoes_veiculos')->insert($dados);
                    $migrados++;
                } catch (\Exception $e) {
                    $erros++;
                    error_log("Erro migração locação veículo {$loc['id']}: " . $e->getMessage());
                }
            }

            $offset += $batchSize;
            echo "Processados: {$offset} (migrados: {$migrados})\n";

        } while (count($locacoes) === $batchSize);

        echo "Total migrados para locacoes_veiculos: {$migrados}\n";
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não reverter automaticamente - dados permanecem na tabela original
    }
};
