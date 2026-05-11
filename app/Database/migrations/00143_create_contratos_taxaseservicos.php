<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela contratos_taxaseservicos
 *
 * Taxas e serviços vinculados ao contrato (ex-colunas opcoes e opcoes_texto).
 * Mantém snapshot dos valores no momento da criação do contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('contratos_taxaseservicos', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->addColumn('`id_contrato` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_taxa` INT UNSIGNED NULL COMMENT "FK para taxaseservicos"');

            // Snapshot do momento (para histórico)
            $table->addColumn('`nome` VARCHAR(100) NOT NULL');
            $table->addColumn('`calculo` VARCHAR(3) NULL COMMENT "FIX, DIA, etc"');
            $table->addColumn('`quantidade` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT "Dias ou quantidade"');
            $table->addColumn('`valor_unitario` DECIMAL(10,2) NOT NULL');
            $table->addColumn('`valor_total` DECIMAL(10,2) NOT NULL');

            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices
            $table->index('chave', 'idx_cts_chave');
            $table->index(['chave', 'id_contrato'], 'idx_cts_contrato');

            // Foreign keys
            $table->foreign('id_contrato')
                ->on('contratos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_taxa')
                ->on('taxaseservicos')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('contratos_taxaseservicos');
    }
};
