<?php

use App\Database\Migration;

/**
 * Migration: Migrar histórico JSON de substituições para contratos_veiculos
 *
 * Migra 877+ contratos com histórico de substituições de veículos.
 * Busca id_veiculo pela placa quando necessário.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar contratos com histórico preenchido
        $contratos = $this->db()
            ->table('contratos')
            ->select(['id', 'chave', 'historico'])
            ->whereRaw('historico IS NOT NULL')
            ->whereRaw("historico != ''")
            ->whereRaw("historico != '[]'")
            ->get();

        $migrados = 0;
        $erros = 0;

        foreach ($contratos as $contrato) {
            $historico = json_decode($contrato['historico'], true);

            if (!is_array($historico) || empty($historico)) {
                continue;
            }

            foreach ($historico as $item) {
                // Extrair id_veiculo da placa se necessário
                $id_veiculo = $item['id_veiculo'] ?? null;

                if (!$id_veiculo && isset($item['devolucao']['placa'])) {
                    $veiculo = $this->db()
                        ->table('veiculos')
                        ->select(['id'])
                        ->where('placa', '=', $item['devolucao']['placa'])
                        ->first();
                    $id_veiculo = $veiculo['id'] ?? null;
                }

                if (!$id_veiculo) {
                    continue;
                }

                // Verificar se já existe (evitar duplicatas)
                $data_sub = $item['data_substituicao'] ?? null;
                if (!$data_sub) {
                    continue;
                }

                $existe = $this->db()
                    ->table('contratos_veiculos')
                    ->where('id_contrato', '=', $contrato['id'])
                    ->where('id_veiculo', '=', $id_veiculo)
                    ->where('data_saida', '=', $data_sub)
                    ->first();

                if ($existe) {
                    continue;
                }

                // Extrair dados da devolução
                $devolucao = $item['devolucao'] ?? [];

                // Determinar data de entrada
                $data_entrada = $devolucao['dataDe'] ?? null;
                if (!$data_entrada) {
                    // Buscar data_ini do contrato como fallback
                    $contratoData = $this->db()
                        ->table('contratos')
                        ->select(['data_ini'])
                        ->where('id', '=', $contrato['id'])
                        ->first();
                    $data_entrada = $contratoData['data_ini'] ?? $data_sub;
                }

                $dados = [
                    'chave' => $contrato['chave'],
                    'id_contrato' => $contrato['id'],
                    'id_veiculo' => $id_veiculo,
                    'id_grupo' => null,
                    'plano' => 'KL', // Plano padrão para histórico
                    'data_entrada' => $data_entrada,
                    'data_saida' => $data_sub,
                    'motivo_saida' => $devolucao['obs'] ?? 'Substituição',
                    'acao_valores' => $item['substituto']['acaoValores'] ?? 'manter',

                    // Odômetro
                    'odometro_entrada' => (int) str_replace(['.', ','], '', $devolucao['odometroIni'] ?? '0'),
                    'odometro_saida' => (int) str_replace(['.', ','], '', $devolucao['odometroFim'] ?? '0'),
                ];

                try {
                    $this->db()->table('contratos_veiculos')->insert($dados);
                    $migrados++;
                } catch (\Exception $e) {
                    $erros++;
                    error_log("Erro migração histórico contrato {$contrato['id']}: " . $e->getMessage());
                }
            }
        }

        echo "Históricos de substituição migrados: {$migrados}\n";
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não é possível reverter - dados já migrados
    }
};
