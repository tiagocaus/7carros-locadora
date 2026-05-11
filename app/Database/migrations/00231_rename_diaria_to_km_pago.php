<?php

/**
 * Migration 00231: Renomear diaria para km_pago
 *
 * Altera o plano "Diária" para "Km Pago":
 * - Renomeia coluna valor_plano_diaria para valor_plano_km_pago (tabelas: grupos, contratos_veiculos)
 * - Atualiza código do plano de DI/KC para KP (tabela: contratos_veiculos)
 * - Atualiza tipo de plano de 'diaria' para 'km_pago' (tabela: grupos_precos_dias)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renomear coluna na tabela grupos
        if ($this->columnExists('grupos', 'valor_plano_diaria')) {
            $this->renameColumnPreservingType('grupos', 'valor_plano_diaria', 'valor_plano_km_pago');
        }

        // 2. Renomear coluna na tabela contratos_veiculos
        if ($this->columnExists('contratos_veiculos', 'valor_plano_diaria')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'valor_plano_diaria', 'valor_plano_km_pago');
        }

        // 3. Atualizar código do plano de DI para KP na tabela contratos_veiculos
        $this->execute("UPDATE contratos_veiculos SET plano = 'KP' WHERE plano = 'DI'");

        // 4. Atualizar código do plano de KC para KP (corrigir inconsistência)
        $this->execute("UPDATE contratos_veiculos SET plano = 'KP' WHERE plano = 'KC'");

        // 5. Atualizar tipo de plano na tabela grupos_precos_dias
        if ($this->tableExists('grupos_precos_dias') && $this->columnExists('grupos_precos_dias', 'tipo_plano')) {
            $this->execute("UPDATE grupos_precos_dias SET tipo_plano = 'km_pago' WHERE tipo_plano = 'diaria'");
        }

        // 6. Atualizar comentário da coluna valor_plano_km_pago na tabela grupos
        if ($this->columnExists('grupos', 'valor_plano_km_pago')) {
            $this->execute("ALTER TABLE grupos MODIFY COLUMN valor_plano_km_pago DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor do plano Km Pago'");
        }

        // 7. Atualizar comentário da coluna plano na tabela contratos_veiculos
        $this->execute("ALTER TABLE contratos_veiculos MODIFY COLUMN plano VARCHAR(3) NOT NULL COMMENT 'KMC=Km Controlado, KL=Km Livre, KP=Km Pago'");
    }

    public function down(): void
    {
        // 1. Reverter renomeação na tabela grupos
        if ($this->columnExists('grupos', 'valor_plano_km_pago')) {
            $this->renameColumnPreservingType('grupos', 'valor_plano_km_pago', 'valor_plano_diaria');
        }

        // 2. Reverter renomeação na tabela contratos_veiculos
        if ($this->columnExists('contratos_veiculos', 'valor_plano_km_pago')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'valor_plano_km_pago', 'valor_plano_diaria');
        }

        // 3. Reverter código do plano de KP para DI
        $this->execute("UPDATE contratos_veiculos SET plano = 'DI' WHERE plano = 'KP'");

        // 4. Reverter tipo de plano na tabela grupos_precos_dias
        if ($this->tableExists('grupos_precos_dias') && $this->columnExists('grupos_precos_dias', 'tipo_plano')) {
            $this->execute("UPDATE grupos_precos_dias SET tipo_plano = 'diaria' WHERE tipo_plano = 'km_pago'");
        }

        // 5. Reverter comentário da coluna valor_plano_diaria na tabela grupos
        if ($this->columnExists('grupos', 'valor_plano_diaria')) {
            $this->execute("ALTER TABLE grupos MODIFY COLUMN valor_plano_diaria DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor da diária no plano Diária'");
        }

        // 6. Reverter comentário da coluna plano na tabela contratos_veiculos
        $this->execute("ALTER TABLE contratos_veiculos MODIFY COLUMN plano VARCHAR(3) NOT NULL COMMENT 'KMC=Km Controlado, KL=Km Livre, DI=Diária'");
    }
};
