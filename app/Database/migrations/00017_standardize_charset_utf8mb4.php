<?php

/**
 * Migration 00017: Padronizar Charset para UTF8MB4
 *
 * Converte todas as tabelas que usam charset antigo (latin1, utf8_general_ci)
 * para utf8mb4_unicode_ci para suporte completo a Unicode e emojis.
 *
 * Tabelas afetadas:
 * - latin1_swedish_ci: acessorios, feriados, fornecedores, grupos, locacoes,
 *                      oficinas, sistema_gravacoes, taxaseservicos, veiculos
 * - utf8_general_ci: changelog, moedas, promissorias, qa_perguntaresposta
 *
 * IMPORTANTE: Esta migration pode demorar alguns minutos em tabelas grandes
 * como locacoes (87k registros) e veiculos (7k registros).
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Tabelas com latin1_swedish_ci
     */
    private array $latin1Tables = [
        'acessorios',
        'feriados',
        'fornecedores',
        'grupos',
        'locacoes',
        'oficinas',
        'sistema_gravacoes',
        'taxaseservicos',
        'veiculos',
    ];

    /**
     * Tabelas com utf8_general_ci
     */
    private array $utf8GeneralTables = [
        'changelog',
        'moedas',
        'promissorias',
        'qa_perguntaresposta',
    ];

    public function up(): void
    {
        // Converte tabelas latin1 para utf8mb4
        foreach ($this->latin1Tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }
        }

        // Converte tabelas utf8_general_ci para utf8mb4_unicode_ci
        foreach ($this->utf8GeneralTables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }
        }
    }

    public function down(): void
    {
        // Reverte tabelas para latin1 (apenas as que eram latin1 originalmente)
        foreach ($this->latin1Tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci"
                );
            }
        }

        // Reverte tabelas para utf8_general_ci (apenas as que eram utf8_general_ci)
        foreach ($this->utf8GeneralTables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci"
                );
            }
        }
    }
};
