<?php

use App\Database\Migration;

/**
 * Migration: Migrar bloqueio/caução para financeiro
 *
 * Migra ~14.145 registros (7.078 entradas + 7.067 saídas).
 * Trata datas 0000-00-00 e contas NULL.
 *
 * Planos de conta:
 * - 117: Bloqueio/Caução entrada (hierarquia 1.1.5.01)
 * - 118: Bloqueio/Caução saída (hierarquia 1.1.5.02)
 */

return new class extends Migration
{
    public function up(): void
    {
        // Buscar contratos com bloqueio
        $contratos = $this->db()
            ->table('contratos')
            ->select([
                'id',
                'chave',
                'codigo',
                'id_cliente',
                'id_veiculo',
                'id_conta_bloqueio',
                'bloqueio_valor',
                'bloqueio_prazo_devolucao',
                'bloqueio_data_devolucao',
                'data_fim',
                'created_at'
            ])
            ->whereRaw('bloqueio_valor > 0')
            ->get();

        $migrados_entrada = 0;
        $migrados_saida = 0;
        $erros = 0;

        foreach ($contratos as $contrato) {
            // Verificar se já existe entrada
            $existe_entrada = $this->db()
                ->table('financeiro')
                ->where('id_contrato', '=', $contrato['id'])
                ->where('id_plano_de_conta', '=', 117) // Bloqueio/Caução entrada
                ->first();

            if (!$existe_entrada) {
                // Calcular data de devolução prevista
                $data_devolucao = null;
                if ($contrato['bloqueio_prazo_devolucao'] && $contrato['data_fim']) {
                    $data_devolucao = date('Y-m-d', strtotime($contrato['data_fim'] . ' + ' . $contrato['bloqueio_prazo_devolucao'] . ' days'));
                }

                // Tratar data 0000-00-00 como não devolvido
                $bloqueio_devolvido = $contrato['bloqueio_data_devolucao']
                    && $contrato['bloqueio_data_devolucao'] !== '0000-00-00'
                    && $contrato['bloqueio_data_devolucao'] !== '0000-00-00 00:00:00';

                $dados_entrada = [
                    'chave' => $contrato['chave'],
                    'id_contrato' => $contrato['id'],
                    'id_cliente' => $contrato['id_cliente'],
                    'id_veiculo' => $contrato['id_veiculo'],
                    'id_conta' => $contrato['id_conta_bloqueio'], // Pode ser NULL
                    'id_plano_de_conta' => 117, // Bloqueio/Caução entrada (hierarquia 1.1.5.01)
                    'descricao' => 'Bloqueio/Caução - Contrato ' . ($contrato['codigo'] ?? $contrato['id']),
                    'tipo' => 'R', // Receita
                    'pago' => $bloqueio_devolvido ? 'S' : 'N',
                    'data_criada' => date('Y-m-d', strtotime($contrato['created_at'])),
                    'data_venci' => date('Y-m-d', strtotime($contrato['created_at'])),
                    'data_devolucao' => $data_devolucao,
                    'valor' => $contrato['bloqueio_valor'],
                ];

                try {
                    $this->db()->table('financeiro')->insert($dados_entrada);
                    $migrados_entrada++;
                } catch (\Exception $e) {
                    $erros++;
                    error_log("Erro migração entrada bloqueio contrato {$contrato['id']}: " . $e->getMessage());
                }
            }

            // Se já foi devolvido, criar registro de saída
            $bloqueio_devolvido = $contrato['bloqueio_data_devolucao']
                && $contrato['bloqueio_data_devolucao'] !== '0000-00-00'
                && $contrato['bloqueio_data_devolucao'] !== '0000-00-00 00:00:00';

            if ($bloqueio_devolvido) {
                $existe_saida = $this->db()
                    ->table('financeiro')
                    ->where('id_contrato', '=', $contrato['id'])
                    ->where('id_plano_de_conta', '=', 118) // Bloqueio/Caução saída
                    ->first();

                if (!$existe_saida) {
                    $dados_saida = [
                        'chave' => $contrato['chave'],
                        'id_contrato' => $contrato['id'],
                        'id_cliente' => $contrato['id_cliente'],
                        'id_veiculo' => $contrato['id_veiculo'],
                        'id_conta' => $contrato['id_conta_bloqueio'], // Pode ser NULL
                        'id_plano_de_conta' => 118, // Bloqueio/Caução saída (hierarquia 1.1.5.02)
                        'descricao' => 'Devolução Bloqueio/Caução - Contrato ' . ($contrato['codigo'] ?? $contrato['id']),
                        'tipo' => 'D', // Despesa
                        'pago' => 'S',
                        'data_criada' => $contrato['bloqueio_data_devolucao'],
                        'data_venci' => $contrato['bloqueio_data_devolucao'],
                        'data_pago' => $contrato['bloqueio_data_devolucao'],
                        'valor' => $contrato['bloqueio_valor'],
                    ];

                    try {
                        $this->db()->table('financeiro')->insert($dados_saida);
                        $migrados_saida++;
                    } catch (\Exception $e) {
                        $erros++;
                        error_log("Erro migração saída bloqueio contrato {$contrato['id']}: " . $e->getMessage());
                    }
                }
            }
        }

        echo "Bloqueios entrada migrados: {$migrados_entrada}\n";
        echo "Bloqueios saída migrados: {$migrados_saida}\n";
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não é possível reverter - dados já migrados
        // Para rollback manual:
        // DELETE FROM financeiro WHERE id_plano_de_conta IN (117, 118) AND id_contrato IS NOT NULL;
    }
};
