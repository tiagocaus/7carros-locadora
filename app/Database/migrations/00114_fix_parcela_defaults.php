<?php

/**
 * Migracao: Corrigir defaults de parcela e total_parcelas
 *
 * - Atualiza registros existentes de NULL para 0
 * - Altera default das colunas de NULL para 0
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extrair valores do formato legado "X de Y" (parcela X de Y total)
        //    SET avalia esquerda->direita: total_parcelas usa o valor original de parcela
        $this->execute("
            UPDATE financeiro
            SET total_parcelas = CAST(SUBSTRING_INDEX(parcela, ' de ', -1) AS UNSIGNED),
                parcela = SUBSTRING_INDEX(parcela, ' de ', 1)
            WHERE parcela LIKE '% de %'
        ");

        // 2. Normalizar valores nao numericos restantes para 0
        $this->execute("UPDATE financeiro SET parcela = '0' WHERE parcela IS NULL OR parcela = '' OR parcela NOT REGEXP '^[0-9]+$'");
        $this->execute("UPDATE financeiro SET total_parcelas = 0 WHERE total_parcelas IS NULL");

        // 3. Alterar tipo + default das colunas
        $this->execute("ALTER TABLE financeiro MODIFY parcela INT UNSIGNED NOT NULL DEFAULT 0");
        $this->execute("ALTER TABLE financeiro MODIFY total_parcelas INT UNSIGNED NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        // Reverter para NULL como default
        $this->execute("ALTER TABLE financeiro MODIFY parcela INT UNSIGNED NULL DEFAULT NULL");
        $this->execute("ALTER TABLE financeiro MODIFY total_parcelas INT UNSIGNED NULL DEFAULT NULL");
    }
};
