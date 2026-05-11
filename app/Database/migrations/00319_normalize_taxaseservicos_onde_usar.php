<?php

use App\Database\Migration;

/**
 * Migration: Normalizar taxaseservicos.onde_usar
 *
 * Dados legacy guardam 'SIT' no lugar de 'SITE', alem de strings com virgula
 * inicial/final/dupla. Esta migration canonicaliza para combinacoes de
 * SIS, SITE, APP (nessa ordem), usando FIND_IN_SET para detectar tokens.
 *
 * Sem rollback seguro — apos o UPDATE, o valor original nao eh recuperavel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('taxaseservicos')) {
            return;
        }

        $this->execute("
            UPDATE taxaseservicos
            SET onde_usar = TRIM(BOTH ',' FROM CONCAT_WS(',',
                IF(FIND_IN_SET('SIS', onde_usar), 'SIS', NULL),
                IF(FIND_IN_SET('SITE', onde_usar) OR FIND_IN_SET('SIT', onde_usar), 'SITE', NULL),
                IF(FIND_IN_SET('APP', onde_usar), 'APP', NULL)
            ))
            WHERE onde_usar LIKE '%SIT%'
               OR onde_usar LIKE ',%'
               OR onde_usar LIKE '%,'
               OR onde_usar LIKE '%,,%'
        ");
    }

    public function down(): void
    {
        // Normalizacao de dados legacy — sem rollback seguro.
    }
};
