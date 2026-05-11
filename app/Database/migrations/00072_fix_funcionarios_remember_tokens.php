<?php

/**
 * Migration 00072: Corrigir funcionarios_remember_tokens
 *
 * Esta migration:
 * 1. Altera usuario_id de bigint(20) para int(100) unsigned
 * 2. Reordena as colunas para: id, chave, usuario_id, token, expira_em, created_at, updated_at
 * 3. Cria FK usuario_id → funcionarios.id
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'funcionarios_remember_tokens';

        if (!$this->tableExists($table)) {
            return;
        }

        // 1. Alterar tipo de usuario_id para int(100) unsigned
        // Primeiro, limpar valores órfãos
        $this->execute("
            UPDATE `{$table}` t
            LEFT JOIN `funcionarios` f ON t.`usuario_id` = f.`id`
            SET t.`usuario_id` = NULL
            WHERE t.`usuario_id` IS NOT NULL
            AND f.`id` IS NULL
        ");

        // 2. Reordenar colunas e alterar tipo
        // Ordem desejada: id, chave, usuario_id, token, expira_em, created_at, updated_at

        // Modificar usuario_id para int(100) unsigned e posicionar após chave
        $this->execute("
            ALTER TABLE `{$table}`
            MODIFY COLUMN `usuario_id` INT(100) UNSIGNED NULL AFTER `chave`
        ");

        // Modificar token para ficar após usuario_id
        $this->execute("
            ALTER TABLE `{$table}`
            MODIFY COLUMN `token` VARCHAR(64) NOT NULL AFTER `usuario_id`
        ");

        // Modificar expira_em para ficar após token
        $this->execute("
            ALTER TABLE `{$table}`
            MODIFY COLUMN `expira_em` TIMESTAMP NOT NULL AFTER `token`
        ");

        // Modificar created_at para ficar após expira_em
        $this->execute("
            ALTER TABLE `{$table}`
            MODIFY COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `expira_em`
        ");

        // Modificar updated_at para ficar após created_at
        $this->execute("
            ALTER TABLE `{$table}`
            MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`
        ");

        // 3. Criar FK
        $fkName = "fk_{$table}_usuario_id";
        if (!$this->foreignKeyExists($table, $fkName)) {
            $this->addForeignKey(
                $table,
                'usuario_id',
                'funcionarios',
                'id',
                'CASCADE',  // ON DELETE - quando funcionário é deletado, remove tokens
                'CASCADE',  // ON UPDATE
                $fkName
            );
        }
    }

    public function down(): void
    {
        $table = 'funcionarios_remember_tokens';

        if (!$this->tableExists($table)) {
            return;
        }

        // Remove FK
        $fkName = "fk_{$table}_usuario_id";
        $this->dropForeignKeyIfExists($table, $fkName);

        // Reverte tipo para bigint
        $this->execute("
            ALTER TABLE `{$table}`
            MODIFY COLUMN `usuario_id` BIGINT(20) UNSIGNED NULL
        ");

        // Nota: Não reverte a ordem das colunas (não é crítico)
    }
};
