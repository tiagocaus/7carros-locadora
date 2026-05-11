<?php

/**
 * Migration 00078: Renomear tabelas do sistema RBAC
 *
 * Renomeia:
 * - roles → funcionarios_roles
 * - role_permissions → funcionarios_role_permissions
 *
 * Foreign Keys atualizadas:
 * - fk_funcionarios_role_id (funcionarios.id_role → funcionarios_roles.id)
 * - fk_funcionarios_role_permissions_role_id (funcionarios_role_permissions.role_id → funcionarios_roles.id)
 * - fk_funcionarios_role_permissions_permission_id (funcionarios_role_permissions.permission_id → permissions.id)
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $tableRenames = [
        'roles' => 'funcionarios_roles',
        'role_permissions' => 'funcionarios_role_permissions',
    ];

    public function up(): void
    {
        // 1. Remover FKs existentes
        $this->dropForeignKeyIfExists('funcionarios', 'fk_funcionarios_role_id');
        $this->dropForeignKeyIfExists('role_permissions', 'fk_role_permissions_role_id');
        $this->dropForeignKeyIfExists('role_permissions', 'fk_role_permissions_permission_id');

        // 2. Renomear tabelas
        foreach ($this->tableRenames as $oldName => $newName) {
            if ($this->tableExists($oldName) && !$this->tableExists($newName)) {
                $this->renameTable($oldName, $newName);
            }
        }

        // 3. Recriar FKs com novos nomes
        // FK: funcionarios.id_role → funcionarios_roles.id
        if (!$this->foreignKeyExists('funcionarios', 'fk_funcionarios_role_id')) {
            $this->addForeignKey(
                'funcionarios',
                'id_role',
                'funcionarios_roles',
                'id',
                'SET NULL',
                'CASCADE',
                'fk_funcionarios_role_id'
            );
        }

        // FK: funcionarios_role_permissions.role_id → funcionarios_roles.id
        if (!$this->foreignKeyExists('funcionarios_role_permissions', 'fk_funcionarios_role_permissions_role_id')) {
            $this->addForeignKey(
                'funcionarios_role_permissions',
                'role_id',
                'funcionarios_roles',
                'id',
                'CASCADE',
                'CASCADE',
                'fk_funcionarios_role_permissions_role_id'
            );
        }

        // FK: funcionarios_role_permissions.permission_id → permissions.id
        if (!$this->foreignKeyExists('funcionarios_role_permissions', 'fk_funcionarios_role_permissions_permission_id')) {
            $this->addForeignKey(
                'funcionarios_role_permissions',
                'permission_id',
                'permissions',
                'id',
                'CASCADE',
                'CASCADE',
                'fk_funcionarios_role_permissions_permission_id'
            );
        }
    }

    public function down(): void
    {
        // 1. Remover FKs
        $this->dropForeignKeyIfExists('funcionarios', 'fk_funcionarios_role_id');
        $this->dropForeignKeyIfExists('funcionarios_role_permissions', 'fk_funcionarios_role_permissions_role_id');
        $this->dropForeignKeyIfExists('funcionarios_role_permissions', 'fk_funcionarios_role_permissions_permission_id');

        // 2. Renomear tabelas de volta
        $reverseRenames = array_flip($this->tableRenames);
        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->tableExists($newName) && !$this->tableExists($oldName)) {
                $this->renameTable($newName, $oldName);
            }
        }

        // 3. Recriar FKs originais
        if (!$this->foreignKeyExists('funcionarios', 'fk_funcionarios_role_id')) {
            $this->addForeignKey(
                'funcionarios',
                'id_role',
                'roles',
                'id',
                'SET NULL',
                'CASCADE',
                'fk_funcionarios_role_id'
            );
        }

        if (!$this->foreignKeyExists('role_permissions', 'fk_role_permissions_role_id')) {
            $this->addForeignKey(
                'role_permissions',
                'role_id',
                'roles',
                'id',
                'CASCADE',
                'CASCADE',
                'fk_role_permissions_role_id'
            );
        }

        if (!$this->foreignKeyExists('role_permissions', 'fk_role_permissions_permission_id')) {
            $this->addForeignKey(
                'role_permissions',
                'permission_id',
                'permissions',
                'id',
                'CASCADE',
                'CASCADE',
                'fk_role_permissions_permission_id'
            );
        }
    }
};
