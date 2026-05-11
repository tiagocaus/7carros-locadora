<?php

use App\Database\Migration;

/**
 * Migration: Adicionar colunas is_system e parent_id na tabela roles
 *
 * - is_system: indica se a role é do sistema (não pode ser editada/excluída)
 * - parent_id: referência à role de sistema quando uma cópia customizada é criada
 *
 * Nota: SQL explícito via stmt() para evitar ambiguidade com QueryBuilder::$this->db()
 * em deploys onde o método execute() aparecia erroneamente como db()->execute().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adicionar coluna is_system
        $this->stmt(
            'ALTER TABLE roles
             ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER chave'
        );

        // Adicionar coluna parent_id
        $this->stmt(
            'ALTER TABLE roles
             ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER is_system'
        );

        // Adicionar índices
        $this->stmt(
            'ALTER TABLE roles
             ADD INDEX idx_roles_is_system (is_system)'
        );

        $this->stmt(
            'ALTER TABLE roles
             ADD INDEX idx_roles_parent_id (parent_id)'
        );

        // Ajustar índice UNIQUE de name para permitir mesmo nome em chaves diferentes
        $this->stmt('ALTER TABLE roles DROP INDEX name');
        $this->stmt('ALTER TABLE roles ADD UNIQUE INDEX uk_roles_chave_name (chave, name)');
    }

    public function down(): void
    {
        // Reverter índice UNIQUE
        $this->stmt('ALTER TABLE roles DROP INDEX uk_roles_chave_name');
        $this->stmt('ALTER TABLE roles ADD UNIQUE INDEX name (name)');
        // Remover índices
        $this->stmt('ALTER TABLE roles DROP INDEX idx_roles_parent_id');
        $this->stmt('ALTER TABLE roles DROP INDEX idx_roles_is_system');

        // Remover colunas
        $this->stmt('ALTER TABLE roles DROP COLUMN parent_id');
        $this->stmt('ALTER TABLE roles DROP COLUMN is_system');
    }

    /** Executa DDL/DML direto pelo PDO da Migration (equivale ao execute() pai). */
    private function stmt(string $sql): void
    {
        $this->pdo->exec($sql);
    }
};
