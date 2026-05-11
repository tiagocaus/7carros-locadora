<?php

use App\Database\Migration;

/**
 * Migration: Adicionar colunas id_contrato e data_devolucao em financeiro
 *
 * Permite vincular registros financeiros diretamente a contratos,
 * especialmente para bloqueio/caução.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar se coluna id_contrato já existe
        // Nota: id_veiculo foi removida na 00108 e so volta na 00287, entao nao usamos AFTER `id_veiculo` aqui
        if (!$this->columnExists('financeiro', 'id_contrato')) {
            $this->alter('financeiro', function ($table) {
                $table->addColumn('`id_contrato` INT UNSIGNED NULL COMMENT "Vinculo com contrato de origem"');
            });
        }

        // Verificar se coluna data_devolucao já existe
        if (!$this->columnExists('financeiro', 'data_devolucao')) {
            $this->alter('financeiro', function ($table) {
                $table->addColumn('`data_devolucao` DATE NULL COMMENT "Data prevista para devolução (bloqueio/caução)" AFTER `data_pago`');
            });
        }

        // Verificar se índice já existe
        if (!$this->indexExists('financeiro', 'idx_financeiro_contrato')) {
            $this->alter('financeiro', function ($table) {
                $table->index(['chave', 'id_contrato'], 'idx_financeiro_contrato');
            });
        }

        // Verificar se FK já existe
        if (!$this->foreignKeyExists('financeiro', 'fk_financeiro_id_contrato')) {
            $this->alter('financeiro', function ($table) {
                $table->foreign('id_contrato')
                    ->on('contratos')
                    ->references('id')
                    ->onDelete('SET NULL')
                    ->onUpdate('CASCADE');
            });
        }
    }

    public function down(): void
    {
        // Remover FK primeiro
        if ($this->foreignKeyExists('financeiro', 'fk_financeiro_id_contrato')) {
            $this->db()->rawQuery('ALTER TABLE financeiro DROP FOREIGN KEY fk_financeiro_id_contrato');
        }

        // Remover índice
        if ($this->indexExists('financeiro', 'idx_financeiro_contrato')) {
            $this->db()->rawQuery('ALTER TABLE financeiro DROP INDEX idx_financeiro_contrato');
        }

        // Remover colunas
        if ($this->columnExists('financeiro', 'data_devolucao')) {
            $this->alter('financeiro', function ($table) {
                $table->dropColumn('data_devolucao');
            });
        }

        if ($this->columnExists('financeiro', 'id_contrato')) {
            $this->alter('financeiro', function ($table) {
                $table->dropColumn('id_contrato');
            });
        }
    }
};
