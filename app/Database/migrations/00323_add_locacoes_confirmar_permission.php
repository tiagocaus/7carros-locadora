<?php

/**
 * Migration: adiciona permissao locacoes.confirmar.
 *
 * Usada pelo painel admin para autorizar a confirmacao de pedidos de reserva
 * (status P) que vieram do site publico quando site_config.reserva_requer_confirmacao=1.
 * Atribuida por padrao a Proprietario e Gerente.
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $permissions = [
        [
            'key'         => 'locacoes.confirmar',
            'name'        => 'Confirmar reserva',
            'description' => 'Confirmar pedidos de reserva vindos do site (status Pendente)',
            'module'      => 'locacoes',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            $existing = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }

            $roleNames = ['Proprietário', 'Gerente'];
            foreach ($roleNames as $roleName) {
                $roles = $this->db()
                    ->table('funcionarios_roles')
                    ->select(['id'])
                    ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                    ->get();

                foreach ($roles as $role) {
                    $permRecord = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$permission['key']])
                        ->first();

                    if ($permRecord) {
                        $stmt = $this->pdo->prepare(
                            "INSERT IGNORE INTO funcionarios_role_permissions (role_id, permission_id, created_at)
                             VALUES (?, ?, NOW())"
                        );
                        $stmt->execute([$role['id'], $permRecord['id']]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->permissions as $permission) {
            $permRecord = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if ($permRecord) {
                $stmt = $this->pdo->prepare("DELETE FROM funcionarios_role_permissions WHERE permission_id = ?");
                $stmt->execute([$permRecord['id']]);

                $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$permRecord['id']]);
            }
        }
    }
};
