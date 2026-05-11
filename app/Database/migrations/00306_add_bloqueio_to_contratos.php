<?php

/**
 * Migration 00306: Adicionar Bloqueio (Pre-autorizacao) a Contratos
 *
 * 1. Cria tabela contratos_bloqueios (mesma estrutura de locacoes_bloqueios)
 * 2. Adiciona coluna id_bloqueio_ativo em contratos
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Criar tabela contratos_bloqueios (authorization holds)
        $this->create('contratos_bloqueios', function ($table) {
            $table->id();
            $table->addColumn('`chave` VARCHAR(20) NOT NULL');
            $table->addColumn('`id_contrato` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_cliente` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_cartao` INT UNSIGNED NOT NULL COMMENT "FK clientes_cartoes"');
            $table->addColumn('`id_gateway` INT UNSIGNED NOT NULL COMMENT "FK gateways_pagamento"');
            $table->addColumn('`gateway_code` VARCHAR(50) NOT NULL COMMENT "stripe, square"');
            $table->addColumn('`external_id` VARCHAR(255) NULL COMMENT "pi_xxx ou payment_id"');
            $table->addColumn('`valor` DECIMAL(10,2) NOT NULL');
            $table->addColumn('`moeda` VARCHAR(3) NOT NULL DEFAULT \'BRL\'');
            $table->addColumn('`status` ENUM(\'pending\',\'authorized\',\'captured\',\'released\',\'expired\',\'failed\') NOT NULL DEFAULT \'pending\'');
            $table->addColumn('`autorizado_em` DATETIME NULL');
            $table->addColumn('`capturado_em` DATETIME NULL');
            $table->addColumn('`liberado_em` DATETIME NULL');
            $table->addColumn('`expira_em` DATETIME NULL COMMENT "Data/hora que o hold expira"');
            $table->addColumn('`valor_capturado` DECIMAL(10,2) NULL COMMENT "Captura parcial"');
            $table->addColumn('`payload` JSON NULL COMMENT "Resposta raw do gateway"');
            $table->addColumn('`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
            $table->addColumn('`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

            $table->index('id_contrato', 'idx_ctr_bloq_contrato');
            $table->index('status', 'idx_ctr_bloq_status');
            $table->index('external_id', 'idx_ctr_bloq_external');
            $table->index(['chave', 'id_contrato'], 'idx_ctr_bloq_chave_contrato');

            $table->foreign('id_contrato')
                ->on('contratos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_cartao')
                ->on('clientes_cartoes')
                ->references('id')
                ->onDelete('RESTRICT')
                ->onUpdate('CASCADE');

            $table->foreign('id_gateway')
                ->on('gateways_pagamento')
                ->references('id')
                ->onDelete('RESTRICT')
                ->onUpdate('CASCADE');
        });

        echo "Tabela contratos_bloqueios criada\n";

        // 2. Adicionar coluna id_bloqueio_ativo em contratos
        $this->addColumnIfNotExists('contratos', 'id_bloqueio_ativo', 'INT UNSIGNED', [
            'nullable' => true,
            'after' => 'total_pagar',
        ]);

        echo "Coluna id_bloqueio_ativo adicionada em contratos\n";
    }

    public function down(): void
    {
        $this->dropColumnIfExists('contratos', 'id_bloqueio_ativo');
        $this->drop('contratos_bloqueios');
    }
};
