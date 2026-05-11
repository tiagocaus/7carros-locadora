<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela locacoes_taxaseservicos
 *
 * Taxas e serviços vinculados à locação (ex-colunas opcoes, opcoes_texto e array_outros).
 * Mantém snapshot dos valores no momento da criação da locação.
 * Espelha a estrutura de contratos_taxaseservicos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('locacoes_taxaseservicos', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->addColumn('`id_locacao` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_taxa` INT UNSIGNED NULL COMMENT "FK para taxaseservicos"');

            // Snapshot do momento (para histórico)
            $table->addColumn('`base_calculo` VARCHAR(3) NOT NULL DEFAULT \'FIX\' COMMENT "FIX, PER, VLT"');
            $table->addColumn('`tipo_valor` VARCHAR(3) NOT NULL DEFAULT \'MON\' COMMENT "MON, POR"');
            $table->addColumn('`nome` VARCHAR(100) NOT NULL');
            $table->addColumn('`quantidade` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT "Dias ou quantidade"');
            $table->addColumn('`valor_unitario` DECIMAL(10,2) NOT NULL');
            $table->addColumn('`valor_total` DECIMAL(10,2) NOT NULL');

            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices
            $table->index('chave', 'idx_lts_chave');
            $table->index(['chave', 'id_locacao'], 'idx_lts_locacao');

            // Foreign keys
            $table->foreign('id_locacao')
                ->on('locacoes')
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
        $this->drop('locacoes_taxaseservicos');
    }
};
