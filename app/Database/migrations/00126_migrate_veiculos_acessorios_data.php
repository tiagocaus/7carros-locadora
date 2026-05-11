<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados de veiculos.acessorios para tabela pivot
 *
 * Converte o formato antigo (IDs separados por vírgula) para relação N:N.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a coluna existe
        if (!$this->columnExists('veiculos', 'acessorios')) {
            return;
        }

        // Buscar veículos com acessórios
        $veiculos = $this->db()
            ->table('veiculos')
            ->select(['id', 'chave', 'acessorios'])
            ->whereRaw('acessorios IS NOT NULL AND acessorios != ""')
            ->get();

        foreach ($veiculos as $veiculo) {
            $acessorioIds = array_filter(
                array_map('trim', explode(',', $veiculo['acessorios']))
            );

            foreach ($acessorioIds as $acessorioId) {
                if (!empty($acessorioId) && is_numeric($acessorioId)) {
                    // Verificar se o acessório existe
                    $existe = $this->db()
                        ->table('veiculos_acessorios')
                        ->where('id', '=', (int) $acessorioId)
                        ->first();

                    if ($existe) {
                        // Verificar se já não existe o vínculo
                        $vinculoExiste = $this->db()
                            ->table('veiculos_acessorios_vinculados')
                            ->where('id_veiculo', '=', $veiculo['id'])
                            ->where('id_acessorio', '=', (int) $acessorioId)
                            ->first();

                        if (!$vinculoExiste) {
                            $this->db()
                                ->table('veiculos_acessorios_vinculados')
                                ->insert([
                                    'id_veiculo' => $veiculo['id'],
                                    'id_acessorio' => (int) $acessorioId,
                                    'chave' => $veiculo['chave']
                                ]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Não é possível reverter - dados já migrados
    }
};
