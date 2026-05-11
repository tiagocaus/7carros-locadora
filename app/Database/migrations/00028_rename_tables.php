<?php

/**
 * Migration 00028: Renomear Tabelas para Padrão snake_case
 *
 * Renomeia 5 tabelas para seguir o padrão de nomenclatura snake_case:
 *
 * | DE                    | PARA                    |
 * |-----------------------|-------------------------|
 * | formas                | formas_pagamento        |
 * | planodecontas         | planos_contas           |
 * | taxaseservicos        | taxas_servicos          |
 * | qa_perguntaresposta   | qa_perguntas_respostas  |
 * | codigo_indicacao      | codigos_indicacao       |
 *
 * IMPORTANTE: Após executar esta migration, é necessário atualizar:
 * - Models (propriedade $table)
 * - Controllers (referências às tabelas)
 * - Views (formulários e listagens)
 * - JavaScript (chamadas AJAX)
 * - Queries SQL em todo o código
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das tabelas
     */
    private array $tableRenames = [
        'formas'              => 'formas_pagamento',
        'planodecontas'       => 'planos_contas',
        'taxaseservicos'      => 'taxas_servicos',
        'qa_perguntaresposta' => 'qa_perguntas_respostas',
        'codigo_indicacao'    => 'codigos_indicacao',
    ];

    public function up(): void
    {
        foreach ($this->tableRenames as $oldName => $newName) {
            // Só renomeia se a tabela antiga existe e a nova não existe
            if ($this->tableExists($oldName) && !$this->tableExists($newName)) {
                $this->renameTable($oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->tableRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->tableExists($newName) && !$this->tableExists($oldName)) {
                $this->renameTable($newName, $oldName);
            }
        }
    }
};
