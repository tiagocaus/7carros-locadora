<?php

use App\Database\Migration;

/**
 * Migration 00396: Remover tabela legada formas_gateway.
 *
 * Os dados dessa tabela foram migrados para gateways_pagamento pela migration
 * 00203_migrate_formas_gateway_data.php. Os vinculos atuais usam
 * formas_pagamento_gateways e gateways_filiais.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->drop('formas_gateway');
    }

    public function down(): void
    {
        if ($this->tableExists('formas_gateway')) {
            return;
        }

        $this->create('formas_gateway', function ($table) {
            $table->addColumn('`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
            $table->string('chave', 45);
            $table->addColumn('`id_matriz_filial` INT(10) UNSIGNED NULL');
            $table->string('nome', 100)->nullable();
            $table->addColumn('`array` MEDIUMTEXT NULL');
            $table->string('status', 1)->nullable();
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->nullable();

            $table->index('chave', 'idx_formas_gateway_chave');
            $table->index('id_matriz_filial', 'fk_formas_gateway_id_matriz_filial');

            $table->foreign('id_matriz_filial')
                ->on('matrizes_filiais')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }
};
