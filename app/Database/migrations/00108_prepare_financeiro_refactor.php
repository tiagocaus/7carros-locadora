<?php

/**
 * Migration 00108: Preparar Refatoracao do Financeiro
 *
 * Reestrutura a tabela financeiro para o modelo Fatura + Itens:
 * 1. Cria backup da tabela (financeiro_backup_refactor)
 * 2. Renomeia valor_original -> valor_principal (IMUTAVEL)
 * 3. Cria coluna desconto
 * 4. Cria coluna valor_total (calculado)
 * 5. Remove coluna valor (ja contem juros+multa embutidos)
 * 6. Remove coluna id_veiculo (sera movida para financeiro_itens)
 *
 * Impacto: ~420k registros
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Criar backup completo da tabela
        if (!$this->tableExists('financeiro_backup_refactor')) {
            $this->execute("CREATE TABLE financeiro_backup_refactor AS SELECT * FROM financeiro");
        }

        // 2. Renomear valor_original -> valor_principal
        if ($this->columnExists('financeiro', 'valor_original') && !$this->columnExists('financeiro', 'valor_principal')) {
            $this->renameColumnPreservingType('financeiro', 'valor_original', 'valor_principal');
        }

        // 3. Criar coluna desconto
        $this->addColumnIfNotExists('financeiro', 'desconto', 'DECIMAL(15,2)', [
            'null' => false,
            'default' => 0,
            'after' => 'multa'
        ]);

        // 4. Criar coluna valor_total
        $this->addColumnIfNotExists('financeiro', 'valor_total', 'DECIMAL(15,2)', [
            'null' => true,
            'after' => 'desconto'
        ]);

        // 5. Calcular valor_total inicial para todos os registros
        // Formula: valor_principal + juros + multa - desconto
        // Nota: Os itens serao adicionados apos a migration 00110
        if ($this->columnExists('financeiro', 'valor_total')) {
            $this->execute("
                UPDATE financeiro
                SET valor_total = COALESCE(valor_principal, 0) + COALESCE(juros, 0) + COALESCE(multa, 0) - COALESCE(desconto, 0)
            ");
        }

        // 6. Remover coluna valor (dados preservados no backup)
        $this->dropColumnIfExists('financeiro', 'valor');

        // 7. Remover coluna id_veiculo (sera movida para financeiro_itens)
        // Primeiro remove a FK se existir
        $this->dropForeignKeyIfExists('financeiro', 'fk_financeiro_id_veiculo');
        $this->dropForeignKeyIfExists('financeiro', 'fk_financeiro_veiculo');
        // Remove o indice se existir
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_id_veiculo');
        $this->dropIndexIfExists('financeiro', 'id_veiculo');
        // Remove a coluna
        $this->dropColumnIfExists('financeiro', 'id_veiculo');

        // 8. Adicionar indice para valor_total
        $this->addIndexIfNotExists('financeiro', ['chave', 'valor_total'], 'idx_fin_chave_valor_total');
    }

    public function down(): void
    {
        // Restaurar do backup se existir
        if ($this->tableExists('financeiro_backup_refactor')) {
            // Remover tabela atual
            $this->execute("DROP TABLE IF EXISTS financeiro");

            // Restaurar do backup
            $this->execute("CREATE TABLE financeiro LIKE financeiro_backup_refactor");
            $this->execute("INSERT INTO financeiro SELECT * FROM financeiro_backup_refactor");

            // Recriar indices principais
            $this->addIndexIfNotExists('financeiro', 'chave', 'idx_financeiro_chave');
            $this->addIndexIfNotExists('financeiro', ['chave', 'id_cliente'], 'idx_financeiro_chave_cliente');
            $this->addIndexIfNotExists('financeiro', ['chave', 'pago'], 'idx_financeiro_chave_pago');

            // Nota: FKs serao recriadas manualmente se necessario
        } else {
            // Rollback manual se backup nao existir

            // Recriar id_veiculo
            $this->addColumnIfNotExists('financeiro', 'id_veiculo', 'INT', [
                'unsigned' => true,
                'null' => true,
                'after' => 'id_cliente'
            ]);

            // Recriar valor
            $this->addColumnIfNotExists('financeiro', 'valor', 'DECIMAL(10,2)', [
                'null' => false,
                'default' => 0,
                'after' => 'juros'
            ]);

            // Renomear valor_principal de volta para valor_original
            if ($this->columnExists('financeiro', 'valor_principal') && !$this->columnExists('financeiro', 'valor_original')) {
                $this->renameColumnPreservingType('financeiro', 'valor_principal', 'valor_original');
            }

            // Remover novas colunas
            $this->dropColumnIfExists('financeiro', 'valor_total');
            $this->dropColumnIfExists('financeiro', 'desconto');

            // Remover indice novo
            $this->dropIndexIfExists('financeiro', 'idx_fin_chave_valor_total');
        }
    }
};
