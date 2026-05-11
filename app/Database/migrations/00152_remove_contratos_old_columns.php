<?php

use App\Database\Migration;

/**
 * Migration: Remover colunas antigas da tabela contratos
 *
 * Remove 31 colunas que foram migradas para as novas tabelas normalizadas:
 * - contratos_veiculos (veículo, grupo, plano, valores, seguros, odômetro, combustível)
 * - contratos_odometros (odometro_array)
 * - contratos_taxaseservicos (opcoes, opcoes_texto)
 * - financeiro (bloqueio, depósito)
 *
 * IMPORTANTE: Esta migração é destrutiva e não pode ser revertida automaticamente.
 * Certifique-se de que os dados foram migrados corretamente antes de executar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover FKs que apontam para colunas a serem removidas
        $fks_to_drop = [
            'fk_contratos_id_veiculo',
            'fk_contratos_id_grupo',
            'fk_contratos_id_conta_bloqueio',
            'fk_contratos_id_conta_deposito',
            'fk_contratos_id_financeiro_deposito',
        ];

        foreach ($fks_to_drop as $fk) {
            if ($this->foreignKeyExists('contratos', $fk)) {
                $this->execute("ALTER TABLE `contratos` DROP FOREIGN KEY `{$fk}`");
                echo "FK removida: {$fk}\n";
            }
        }

        // 2. Remover índices associados às colunas (se existirem)
        $indexes_to_drop = [
            'idx_contratos_id_veiculo',
            'idx_contratos_id_grupo',
            'idx_contratos_id_conta_bloqueio',
            'idx_contratos_id_conta_deposito',
            'idx_contratos_id_financeiro_deposito',
        ];

        foreach ($indexes_to_drop as $index) {
            if ($this->indexExists('contratos', $index)) {
                $this->execute("ALTER TABLE `contratos` DROP INDEX `{$index}`");
                echo "Índice removido: {$index}\n";
            }
        }

        // 3. Remover colunas antigas (31 colunas)
        $columns_to_drop = [
            // Veículo/Grupo/Plano (migradas para contratos_veiculos)
            'id_veiculo',
            'id_grupo',
            'plano',
            'valores',

            // Seguros (migradas para contratos_veiculos)
            'seguro_carro',
            'seguro_carro_valor',
            'cobertura_carro_valor',
            'seguro_terceiros',
            'seguro_terceiros_valor',
            'cobertura_terceiros_valor',

            // Odômetro/Combustível (migradas para contratos_veiculos e contratos_odometros)
            'odometro_ini',
            'odometro_fim',
            'odometro_array',
            'combustivel_ini',
            'combustivel_fim',

            // Bloqueio/Caução (migradas para financeiro)
            'id_conta_bloqueio',
            'bloqueio_tipo',
            'bloqueio_valor',
            'bloqueio_prazo_devolucao',
            'bloqueio_data_devolucao',

            // Depósito (migradas para financeiro)
            'id_conta_deposito',
            'deposito_tipo',
            'deposito_valor',
            'id_financeiro_deposito',

            // Taxas/Serviços (migradas para contratos_taxaseservicos)
            'opcoes',
            'opcoes_texto',

            // Outras colunas obsoletas
            'array_veiculos',      // Não utilizado (0 registros)
            'historico',           // Migrado para contratos_veiculos
            'valor_faturas_paga',  // Calculado via query no financeiro
            'status_checklist',    // Não necessário
            'array_outros',        // Dados de abastecimento - não utilizado
        ];

        $removed = 0;
        $skipped = 0;

        foreach ($columns_to_drop as $column) {
            if ($this->columnExists('contratos', $column)) {
                $this->execute("ALTER TABLE `contratos` DROP COLUMN `{$column}`");
                $removed++;
                echo "Coluna removida: {$column}\n";
            } else {
                $skipped++;
            }
        }

        echo "\n=== Resumo ===\n";
        echo "Colunas removidas: {$removed}\n";
        echo "Colunas não encontradas (já removidas): {$skipped}\n";
    }

    public function down(): void
    {
        // Esta migração é destrutiva e não pode ser revertida automaticamente.
        // Para reverter, restaure o backup do banco de dados.
        echo "AVISO: Esta migração não pode ser revertida automaticamente.\n";
        echo "Para reverter, restaure o backup do banco de dados.\n";
    }

};
