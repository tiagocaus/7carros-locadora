<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados de contratos para contratos_veiculos
 *
 * Migra 12.137 contratos com veículo para a nova tabela normalizada.
 * Extrai valores do JSON (suportando camelCase legado).
 * Contratos sem veículo (355) são ignorados.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar todos os contratos com id_veiculo
        $contratos = $this->db()
            ->table('contratos')
            ->select([
                'id', 'chave', 'id_veiculo', 'id_grupo', 'plano', 'valores',
                'data_ini', 'status', 'data_fim',
                'seguro_carro', 'seguro_carro_valor', 'cobertura_carro_valor',
                'seguro_terceiros', 'seguro_terceiros_valor', 'cobertura_terceiros_valor',
                'odometro_ini', 'odometro_fim', 'combustivel_ini', 'combustivel_fim'
            ])
            ->whereRaw('id_veiculo IS NOT NULL')
            ->get();

        $migrados = 0;
        $erros = 0;

        foreach ($contratos as $contrato) {
            // Verificar se já existe (evitar duplicatas)
            $existe = $this->db()
                ->table('contratos_veiculos')
                ->where('id_contrato', '=', $contrato['id'])
                ->where('id_veiculo', '=', $contrato['id_veiculo'])
                ->whereRaw('data_saida IS NULL OR data_saida = (SELECT data_fim FROM contratos WHERE id = ' . $contrato['id'] . ')')
                ->first();

            if ($existe) {
                continue;
            }

            // Decodificar JSON de valores (snapshot da época)
            $valores = json_decode($contrato['valores'] ?? '{}', true) ?? [];

            // Garantir que plano não seja nulo
            $plano = $contrato['plano'] ?? 'KL';
            if (empty($plano)) {
                $plano = 'KL';
            }

            $dados = [
                'chave' => $contrato['chave'],
                'id_contrato' => $contrato['id'],
                'id_veiculo' => $contrato['id_veiculo'],
                'id_grupo' => $contrato['id_grupo'],

                // Período: data_entrada = data_ini do contrato
                'data_entrada' => $contrato['data_ini'],
                // data_saida = data_fim apenas se contrato fechado
                'data_saida' => $contrato['status'] === 'F' ? $contrato['data_fim'] : null,

                'plano' => $plano,

                // Valores do JSON (tratando ambos os formatos: snake_case e camelCase legado)
                'valor_plano_diaria' => $valores['valor_plano_diaria'] ?? $valores['diariaValor'] ?? 0,
                'valor_plano_km_livre' => $valores['valor_plano_km_livre'] ?? $valores['kmlivreValor'] ?? 0,
                'valor_plano_km_controlado' => $valores['valor_plano_km_controlado'] ?? $valores['kmcontroladoValor'] ?? 0,
                'km_franquia' => $valores['km_franquia'] ?? $valores['kmcontroladoFranquia'] ?? null,
                'valor_km_excedente' => $valores['valor_km_excedente'] ?? $valores['kmValor'] ?? 0,
                'minutos_tolerancia' => $valores['minutos_tolerancia'] ?? $valores['minutoTolerancia'] ?? 0,
                'valor_tolerancia' => $valores['valor_tolerancia'] ?? $valores['valorTolerancia'] ?? 0,
                'valor_km_retorno' => $valores['valor_km_retorno'] ?? $valores['valorKmRetorno'] ?? 0,
                'valor_condutor_adicional' => $valores['valor_condutor_adicional'] ?? $valores['valorCondutorAdicional'] ?? 0,

                // Seguro (nomes iguais à tabela grupos)
                'seguro_carro' => ($contrato['seguro_carro'] ?? 'N') === 'S' ? 1 : 0,
                'valor_seguro_carro' => $contrato['seguro_carro_valor'] ?? 0,
                'cobertura_carro' => $contrato['cobertura_carro_valor'] ?? 0,
                'seguro_terceiros' => ($contrato['seguro_terceiros'] ?? 'N') === 'S' ? 1 : 0,
                'valor_seguro_terceiros' => $contrato['seguro_terceiros_valor'] ?? 0,
                'cobertura_terceiros' => $contrato['cobertura_terceiros_valor'] ?? 0,

                // Odômetro/Combustível
                'odometro_entrada' => $contrato['odometro_ini'] ?? 0,
                'odometro_saida' => $contrato['odometro_fim'],
                'combustivel_entrada' => $contrato['combustivel_ini'],
                'combustivel_saida' => $contrato['combustivel_fim'],
            ];

            try {
                $this->db()->table('contratos_veiculos')->insert($dados);
                $migrados++;
            } catch (\Exception $e) {
                $erros++;
                error_log("Erro migração contrato {$contrato['id']}: " . $e->getMessage());
            }
        }

        echo "Contratos migrados para contratos_veiculos: {$migrados}\n";
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não é possível reverter - dados já migrados
        // Usar TRUNCATE se necessário rollback manual
    }
};
