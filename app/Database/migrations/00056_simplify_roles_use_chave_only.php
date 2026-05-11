<?php

use App\Database\Migration;

/**
 * Migration: Simplificar roles para usar apenas a coluna 'chave'
 *
 * - Remove colunas is_system e parent_id (desnecessárias)
 * - Muda chave = 'system' para chave = '0' (padrão do sistema)
 * - Lógica simplificada: chave = '0' = sistema, chave = 'tenant' = customizada
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Resolver conflitos: roles legadas com chave='0' cujo name colide
        //    (case/accent-insensitive via utf8mb4_unicode_ci) com roles de sistema (chave='system').
        //    Sem isso, o UPDATE abaixo viola o uk_roles_chave_name.
        //    Migra funcionarios/role_permissions para a role de sistema e exclui a legada.
        $stmt = $this->pdo->query(
            "SELECT old.id AS old_id, sys.id AS sys_id
               FROM roles old
               JOIN roles sys ON sys.name = old.name AND sys.chave = 'system'
              WHERE old.chave = '0' AND old.id <> sys.id"
        );
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($conflicts as $row) {
            $oldId = (int) $row['old_id'];
            $sysId = (int) $row['sys_id'];

            $upd = $this->pdo->prepare("UPDATE funcionarios SET id_role = ? WHERE id_role = ?");
            $upd->execute([$sysId, $oldId]);

            $delPerm = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $delPerm->execute([$oldId]);

            $delRole = $this->pdo->prepare("DELETE FROM roles WHERE id = ?");
            $delRole->execute([$oldId]);
        }

        // 2. Alterar chave das roles de sistema de 'system' para '0'
        $this->execute(
            "UPDATE roles SET chave = '0' WHERE chave = 'system'"
        );

        // 3. Remover índices das colunas que serão excluídas
        $this->execute("ALTER TABLE roles DROP INDEX idx_roles_is_system");
        $this->execute("ALTER TABLE roles DROP INDEX idx_roles_parent_id");

        // 4. Remover colunas desnecessárias
        $this->execute("ALTER TABLE roles DROP COLUMN is_system");
        $this->execute("ALTER TABLE roles DROP COLUMN parent_id");
    }

    public function down(): void
    {
        // 1. Adicionar colunas de volta
        $this->execute(
            "ALTER TABLE roles
             ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER chave"
        );

        $this->execute(
            "ALTER TABLE roles
             ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER is_system"
        );

        // 2. Adicionar índices
        $this->execute(
            "ALTER TABLE roles
             ADD INDEX idx_roles_is_system (is_system)"
        );

        $this->execute(
            "ALTER TABLE roles
             ADD INDEX idx_roles_parent_id (parent_id)"
        );

        // 3. Marcar roles com chave = '0' como sistema
        $this->execute(
            "UPDATE roles SET is_system = 1 WHERE chave = '0'"
        );

        // 4. Alterar chave de volta para 'system'
        $this->execute(
            "UPDATE roles SET chave = 'system' WHERE chave = '0'"
        );
    }
};
