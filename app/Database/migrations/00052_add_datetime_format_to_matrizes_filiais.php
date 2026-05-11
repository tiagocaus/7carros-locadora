<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna datetime_format à tabela matrizes_filiais
 *
 * Separa o formato de data em dois campos:
 * - date_format: Para campos que usam apenas data (ex: financeiro)
 * - datetime_format: Para campos que usam data e hora (ex: contratos, locações)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adicionar coluna datetime_format
        $this->table('matrizes_filiais', function ($table) {
            $table->string('datetime_format', 20)->default('d/m/Y H:i:s')->after('date_format');
        });

        // Atualizar registros existentes: mover formato com hora para datetime_format
        // e definir date_format como apenas data
        $this->execute("
            UPDATE matrizes_filiais
            SET datetime_format = date_format,
                date_format = CASE
                    WHEN date_format LIKE 'd/%' THEN 'd/m/Y'
                    WHEN date_format LIKE 'm/%' THEN 'm/d/Y'
                    WHEN date_format LIKE 'Y-%' THEN 'Y-m-d'
                    ELSE 'd/m/Y'
                END
            WHERE date_format LIKE '%H:%'
        ");

        // Alterar valor padrão de date_format para apenas data
        $this->execute("
            ALTER TABLE matrizes_filiais
            ALTER COLUMN date_format SET DEFAULT 'd/m/Y'
        ");
    }

    public function down(): void
    {
        // Reverter: mesclar datetime_format de volta para date_format
        $this->execute("
            UPDATE matrizes_filiais
            SET date_format = datetime_format
        ");

        // Remover coluna datetime_format
        $this->table('matrizes_filiais', function ($table) {
            $table->dropColumn('datetime_format');
        });

        // Restaurar valor padrão original
        $this->execute("
            ALTER TABLE matrizes_filiais
            ALTER COLUMN date_format SET DEFAULT 'd/m/Y H:i:s'
        ");
    }
};
