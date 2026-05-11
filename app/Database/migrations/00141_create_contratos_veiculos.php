<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela contratos_veiculos
 *
 * Relacionamento N:N entre contratos e veículos, com histórico de substituições.
 * Permite múltiplos veículos por contrato e rastreamento de quem estava com
 * o veículo em determinada data (para multas).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('contratos_veiculos', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->addColumn('`id_contrato` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_veiculo` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_grupo` INT UNSIGNED NULL COMMENT "Grupo do veículo no momento da inclusão"');

            // Período de uso do veículo neste contrato (histórico de substituições)
            $table->addColumn('`data_entrada` DATETIME NOT NULL COMMENT "Quando veículo entrou no contrato"');
            $table->addColumn('`data_saida` DATETIME NULL COMMENT "Quando saiu (substituição ou fim). NULL = veículo atual"');

            // Valores (snapshot do momento da inclusão - nomes iguais à tabela grupos)
            $table->addColumn('`plano` VARCHAR(3) NOT NULL COMMENT "KMC=Km Controlado, KL=Km Livre, DI=Diária"');
            $table->addColumn('`valor_plano_diaria` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`valor_plano_km_livre` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`valor_plano_km_controlado` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`km_franquia` INT UNSIGNED NULL COMMENT "Franquia de km por período"');
            $table->addColumn('`valor_km_excedente` DECIMAL(10,2) DEFAULT 0.00 COMMENT "Valor por km excedente"');
            $table->addColumn('`minutos_tolerancia` INT UNSIGNED DEFAULT 0 COMMENT "Minutos de tolerância na devolução"');
            $table->addColumn('`valor_tolerancia` DECIMAL(10,2) DEFAULT 0.00 COMMENT "Valor cobrado por atraso"');
            $table->addColumn('`valor_km_retorno` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`valor_condutor_adicional` DECIMAL(10,2) DEFAULT 0.00');

            // Seguros (nomes iguais à tabela grupos)
            $table->addColumn('`seguro_carro` TINYINT(1) DEFAULT 0');
            $table->addColumn('`valor_seguro_carro` DECIMAL(12,2) DEFAULT 0.00');
            $table->addColumn('`cobertura_carro` DECIMAL(12,2) DEFAULT 0.00');
            $table->addColumn('`seguro_terceiros` TINYINT(1) DEFAULT 0');
            $table->addColumn('`valor_seguro_terceiros` DECIMAL(12,2) DEFAULT 0.00');
            $table->addColumn('`cobertura_terceiros` DECIMAL(12,2) DEFAULT 0.00');

            // Odômetro e Combustível
            $table->addColumn('`odometro_entrada` INT UNSIGNED DEFAULT 0');
            $table->addColumn('`odometro_saida` INT UNSIGNED NULL');
            $table->addColumn('`combustivel_entrada` INT NULL');
            $table->addColumn('`combustivel_saida` INT NULL');

            // Substituição
            $table->addColumn('`motivo_saida` VARCHAR(255) NULL COMMENT "Motivo da substituição"');
            $table->addColumn('`acao_valores` VARCHAR(20) NULL COMMENT "manter, atualizar"');

            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->addColumn('`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('chave', 'idx_cv_chave');
            $table->index(['chave', 'id_contrato'], 'idx_cv_contrato');
            $table->index(['chave', 'id_veiculo'], 'idx_cv_veiculo');
            $table->index(['chave', 'id_contrato', 'data_saida'], 'idx_cv_ativo');
            $table->index(['id_veiculo', 'data_entrada', 'data_saida'], 'idx_cv_veiculo_periodo');

            // Foreign keys
            $table->foreign('id_contrato')
                ->on('contratos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_veiculo')
                ->on('veiculos')
                ->references('id')
                ->onDelete('RESTRICT')
                ->onUpdate('CASCADE');

            $table->foreign('id_grupo')
                ->on('grupos')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('contratos_veiculos');
    }
};
