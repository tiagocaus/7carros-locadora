<?php

/**
 * Migration: Renomear tabela legada `site` para `_site_legacy`
 *
 * Mantem os dados acessiveis para consulta mas marca claramente
 * como deprecated. Executar somente apos validar a migracao 00299.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("RENAME TABLE `site` TO `_site_legacy`");
    }

    public function down(): void
    {
        $this->execute("RENAME TABLE `_site_legacy` TO `site`");
    }
};
