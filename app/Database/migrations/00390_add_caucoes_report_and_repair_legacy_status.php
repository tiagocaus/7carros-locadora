<?php

/**
 * Migration 00390: Relatorio de caucoes e reparo de status legado.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    private const PERMISSION = [
        'key' => 'relatorios.financeiro.caucoes',
        'name' => 'Relatorio de Caucoes',
        'description' => 'Visualizar relatorio de caucoes e registrar devolucoes',
        'module' => 'relatorios',
    ];

    private const ROLE_NAMES = [
        'Proprietario',
        'Proprietário',
        'Gerente',
        'Coordenador Administrativo',
    ];

    public function up(): void
    {
        $this->ensurePermission();
        $this->repairLegacyLocacaoCaucoes();
        $this->flushCache();
    }

    public function down(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION['key']])
            ->first();

        if ($permission) {
            $this->db()
                ->table('funcionarios_role_permissions')
                ->whereRaw('permission_id = ?', [(int) $permission['id']])
                ->delete();

            $this->db()
                ->table('permissions')
                ->whereRaw('id = ?', [(int) $permission['id']])
                ->delete();
        }

        $this->flushCache();
    }

    private function ensurePermission(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION['key']])
            ->first();

        $permissionId = $permission
            ? (int) $permission['id']
            : (int) $this->db()->table('permissions')->insert(self::PERMISSION);

        $placeholders = implode(', ', array_fill(0, count(self::ROLE_NAMES), '?'));
        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw("name IN ({$placeholders})", self::ROLE_NAMES)
            ->get();

        foreach ($roles as $role) {
            $exists = $this->db()
                ->table('funcionarios_role_permissions')
                ->select(['id'])
                ->whereRaw('role_id = ? AND permission_id = ?', [(int) $role['id'], $permissionId])
                ->first();

            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => (int) $role['id'],
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function repairLegacyLocacaoCaucoes(): void
    {
        if (!$this->tableExists('locacoes_caucoes') || !$this->tableExists('locacoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE IF NOT EXISTS locacoes_caucoes_00390_backup AS
            SELECT lc.*, NOW() AS backup_at, CAST('legacy_devolvida_aberta_sem_financeiro' AS CHAR(80)) AS backup_reason
            FROM locacoes_caucoes lc
            WHERE 1 = 0
        ");

        $this->execute("
            INSERT INTO locacoes_caucoes_00390_backup
            SELECT lc.*, NOW() AS backup_at, 'legacy_devolvida_aberta_sem_financeiro' AS backup_reason
            FROM locacoes_caucoes lc
            INNER JOIN locacoes l
                ON l.id = lc.id_locacao
                AND l.chave = lc.chave
            WHERE lc.status = 'devolvida'
              AND lc.id_financeiro_devolucao IS NULL
              AND l.status IN ('R', 'A')
              AND NOT EXISTS (
                  SELECT 1
                  FROM locacoes_caucoes_00390_backup b
                  WHERE b.id = lc.id
              )
        ");

        $this->execute("
            UPDATE locacoes_caucoes lc
            INNER JOIN locacoes l
                ON l.id = lc.id_locacao
                AND l.chave = lc.chave
            SET lc.status = 'ativa',
                lc.data_devolucao = NULL,
                lc.updated_at = NOW()
            WHERE lc.status = 'devolvida'
              AND lc.id_financeiro_devolucao IS NULL
              AND l.status IN ('R', 'A')
        ");
    }

    private function flushCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache indisponivel nao deve impedir migration de permissao/reparo.
        }
    }
};
