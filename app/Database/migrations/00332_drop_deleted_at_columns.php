<?php

/**
 * Migration 00332: Remove colunas deleted_at (projeto nao usa soft-delete)
 *
 * O projeto nao implementa soft-delete. Em PROD ZERO registros estao soft-deletados
 * em qualquer das 4 tabelas. Todas as referencias a `deleted_at` no codigo
 * (Auth.php, Models/Funcionario.php, Models/Role.php, etc.) foram removidas.
 *
 * Tabelas afetadas:
 *   - configuracoes
 *   - funcionarios
 *   - funcionarios_roles
 *   - matrizes_filiais
 *
 * Idempotente: dropColumnIfExists pula se ja foi removida.
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $tabelas = [
        'configuracoes',
        'funcionarios',
        'funcionarios_roles',
        'matrizes_filiais',
    ];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            $this->dropColumnIfExists($tabela, 'deleted_at');
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $tabela) {
            $this->addColumnIfNotExists($tabela, 'deleted_at', 'DATETIME', [
                'null' => true,
                'default' => null,
            ]);
        }
    }
};
