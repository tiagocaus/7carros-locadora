<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela locacoes_veiculos
 *
 * Relacionamento N:N entre locações e veículos, com histórico de substituições.
 * Permite rastrear qual veículo estava com a locação em determinada data (para multas).
 * Espelha a estrutura de contratos_veiculos com campos adicionais de devolução.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('locacoes_veiculos', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->addColumn('`id_locacao` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_veiculo` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_grupo` INT UNSIGNED NULL COMMENT "Grupo do veículo no momento da inclusão"');

            // Período de uso do veículo nesta locação (histórico de substituições)
            $table->addColumn('`data_entrada` DATETIME NOT NULL COMMENT "Quando veículo entrou na locação"');
            $table->addColumn('`data_saida` DATETIME NULL COMMENT "Quando saiu (substituição ou fim). NULL = veículo atual"');

            // Valores (snapshot do momento da inclusão - nomes iguais a contratos_veiculos)
            $table->addColumn('`plano` VARCHAR(3) NOT NULL COMMENT "KMC=Km Controlado, KL=Km Livre, KP=Km Pago"');
            $table->addColumn('`valor_plano_km_pago` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`valor_plano_km_livre` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`valor_plano_km_controlado` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`km_franquia` INT UNSIGNED NULL COMMENT "Franquia de km por período"');
            $table->addColumn('`valor_km_excedente` DECIMAL(10,2) DEFAULT 0.00 COMMENT "Valor por km excedente"');
            $table->addColumn('`minutos_tolerancia` INT UNSIGNED DEFAULT 0 COMMENT "Minutos de tolerância na devolução"');
            $table->addColumn('`valor_tolerancia` DECIMAL(10,2) DEFAULT 0.00 COMMENT "Valor cobrado por atraso"');
            $table->addColumn('`valor_km_retorno` DECIMAL(10,2) DEFAULT 0.00');
            $table->addColumn('`valor_condutor_adicional` DECIMAL(10,2) DEFAULT 0.00');

            // Seguros (nomes iguais a contratos_veiculos)
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

            // Dados calculados na devolução (específico de locações)
            $table->addColumn('`odometro_usado` INT UNSIGNED NULL COMMENT "KM rodados (odometro_saida - odometro_entrada)"');
            $table->addColumn('`km_excedente` INT UNSIGNED NULL COMMENT "KM excedentes além da franquia"');
            $table->addColumn('`combustivel_usado` INT NULL COMMENT "Diferença de combustível"');
            $table->addColumn('`combustivel_valor` DECIMAL(10,2) NULL COMMENT "Valor cobrado por combustível"');

            // Substituição
            $table->addColumn('`motivo_saida` VARCHAR(255) NULL COMMENT "Motivo da substituição"');
            $table->addColumn('`acao_valores` VARCHAR(20) NULL COMMENT "manter, atualizar"');

            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->addColumn('`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('chave', 'idx_lv_chave');
            $table->index(['chave', 'id_locacao'], 'idx_lv_locacao');
            $table->index(['chave', 'id_veiculo'], 'idx_lv_veiculo');
            $table->index(['chave', 'id_locacao', 'data_saida'], 'idx_lv_ativo');
            $table->index(['id_veiculo', 'data_entrada', 'data_saida'], 'idx_lv_veiculo_periodo');

            // Foreign keys
            $table->foreign('id_locacao')
                ->on('locacoes')
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
        $this->drop('locacoes_veiculos');
    }
};
