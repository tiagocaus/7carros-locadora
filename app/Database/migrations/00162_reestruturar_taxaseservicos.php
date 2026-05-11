<?php

/**
 * Migration 00162: Reestruturar tabela taxaseservicos
 *
 * Simplifica a estrutura de calculo de taxas:
 * - Renomeia 'aplicacao' para 'base_calculo' (FIX, PER, VLT)
 * - Renomeia 'valor_em' para 'tipo_valor' (MON, POR)
 * - Remove coluna 'calculo' (redundante)
 * - Migra dados existentes para novos valores
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renomear colunas usando execute() para SQL raw
        $this->execute("
            ALTER TABLE taxaseservicos
            CHANGE COLUMN aplicacao base_calculo VARCHAR(3) NOT NULL DEFAULT 'FIX'
            COMMENT 'FIX=Fixo, PER=Por Periodo, VLT=Valor Total'
        ");

        $this->execute("
            ALTER TABLE taxaseservicos
            CHANGE COLUMN valor_em tipo_valor VARCHAR(3) DEFAULT 'MON'
            COMMENT 'MON=Moeda, POR=Porcentagem'
        ");

        // 2. Migrar dados existentes
        // DIA -> PER (por periodo)
        $this->execute("
            UPDATE taxaseservicos
            SET base_calculo = 'PER'
            WHERE base_calculo = 'DIA'
        ");

        // VLT ja esta correto, manter
        // Qualquer outro valor -> FIX
        $this->execute("
            UPDATE taxaseservicos
            SET base_calculo = 'FIX'
            WHERE base_calculo NOT IN ('FIX', 'PER', 'VLT')
        ");

        // MON ja esta correto, manter
        // MOT (montante) -> MON (moeda)
        $this->execute("
            UPDATE taxaseservicos
            SET tipo_valor = 'MON'
            WHERE tipo_valor = 'MOT' OR tipo_valor IS NULL
        ");

        // 3. Remover coluna redundante 'calculo' (se existir)
        $this->dropColumnIfExists('taxaseservicos', 'calculo');
    }

    public function down(): void
    {
        // 1. Recriar coluna 'calculo'
        $this->addColumnIfNotExists('taxaseservicos', 'calculo', 'VARCHAR(3)', [
            'default' => 'FIX',
            'after' => 'base_calculo'
        ]);

        // 2. Renomear colunas de volta
        $this->execute("
            ALTER TABLE taxaseservicos
            CHANGE COLUMN base_calculo aplicacao VARCHAR(5) NOT NULL DEFAULT 'VLT'
            COMMENT '[DIA] Diaria, [VLT] no valor total. Base para calculo'
        ");

        $this->execute("
            ALTER TABLE taxaseservicos
            CHANGE COLUMN tipo_valor valor_em VARCHAR(3) DEFAULT 'MON'
            COMMENT 'e porcentagem? [MOT] Montante, [POR] Porcentagem'
        ");

        // 3. Reverter dados
        $this->execute("
            UPDATE taxaseservicos
            SET aplicacao = 'DIA'
            WHERE aplicacao = 'PER'
        ");
    }
};
