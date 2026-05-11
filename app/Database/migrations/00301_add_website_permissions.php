<?php

/**
 * Migration: Adicionar permissoes do modulo Website
 *
 * Cria website.visualizar, website.configurar e website.deploy.
 * Nota: website.editar ja existe (migration 00005).
 * Atribui automaticamente aos roles Proprietario e Gerente.
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $permissions = [
        [
            'key'         => 'website.visualizar',
            'name'        => 'Visualizar Website',
            'description' => 'Ver configurações do website',
            'module'      => 'website',
        ],
        [
            'key'         => 'website.configurar',
            'name'        => 'Configurar Website',
            'description' => 'Alterar flags, manutenção, overbooking e configurações gerais',
            'module'      => 'website',
        ],
        [
            'key'         => 'website.deploy',
            'name'        => 'Publicar Website',
            'description' => 'Executar deploy do site para o FTP',
            'module'      => 'website',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            // Inserir permissao (com check de duplicata)
            $existing = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }

            // Atribuir a Proprietario e Gerente
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
