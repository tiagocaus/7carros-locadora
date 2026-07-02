<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\Auth;
use App\Models\Funcionario;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Permission;
use App\Views\Template;

/**
 * Controller para gerenciamento de acesso de suporte
 *
 * Cria usuario temporario para equipe de suporte com todas as permissoes
 */
class ConcederAcessoController
{
    private Funcionario $funcionarioModel;
    private Role $roleModel;
    private RolePermission $rolePermissionModel;
    private Permission $permissionModel;

    public function __construct()
    {
        $this->funcionarioModel = new Funcionario();
        $this->roleModel = new Role();
        $this->rolePermissionModel = new RolePermission();
        $this->permissionModel = new Permission();
    }

    private function authorize(): bool
    {
        if (!Auth::can('suporte.gerenciar') && !Auth::can('configuracoes.editar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para gerenciar acesso de suporte'
            ], 403);
            return false;
        }

        return true;
    }

    /**
     * Exibe a pagina de conceder acesso
     */
    public function view(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $html = Template::render('pages.conceder-acesso.index');
        Response::html($html);
    }

    /**
     * Retorna o status atual (se existe usuario de suporte)
     */
    public function status(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $usuarioSuporte = $this->funcionarioModel->buscarUsuarioSuporte();

        if ($usuarioSuporte) {
            Response::json([
                'success' => true,
                'existe' => true,
                'usuario' => $usuarioSuporte['usuario'],
                'criado_em' => $usuarioSuporte['created_at']
            ]);
        } else {
            Response::json([
                'success' => true,
                'existe' => false
            ]);
        }
    }

    /**
     * Cria o usuario de suporte com todas as permissoes
     */
    public function criar(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $chave = Auth::chave();

        // Verifica se ja existe usuario de suporte
        $usuarioExistente = $this->funcionarioModel->buscarUsuarioSuporte();
        if ($usuarioExistente) {
            Response::json([
                'success' => false,
                'message' => 'Ja existe um usuario de suporte ativo'
            ], 400);
            return;
        }

        // Gera numero aleatorio de 7 digitos
        $numero = str_pad(random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        $nomeUsuario = 'suporte' . $numero;
        $senha = $numero;

        $this->roleModel->beginTransaction();

        try {
            // Cria ou reaproveita a role reservada de suporte com todas as permissoes
            $roleSuporte = $this->roleModel->buscarRoleSuporte($chave);
            $roleId = $roleSuporte
                ? (int) $roleSuporte['id']
                : $this->roleModel->criar(
                    $chave,
                    Role::supportRoleName(),
                    Role::supportRoleDescription()
                );

            // Busca todas as permissoes do sistema
            $todasPermissoes = $this->permissionModel->listarTodas();
            $permissionIds = array_column($todasPermissoes, 'id');

            // Atribui todas as permissoes a role
            $this->rolePermissionModel->sincronizar($roleId, $permissionIds);

            // Cria o usuario de suporte herdando o plano do tenant
            $this->funcionarioModel->criar([
                'chave' => $chave,
                'nome' => Role::supportRoleName(),
                'usuario' => $nomeUsuario,
                'email' => $nomeUsuario . '@suporte.7carros.com',
                'senha' => $senha,
                'status' => 'A',
                'id_role' => $roleId,
                'plano' => $this->funcionarioModel->getPlanoTenant()
            ]);

            $this->roleModel->commit();

            Response::json([
                'success' => true,
                'message' => 'Usuario de suporte criado com sucesso',
                'usuario' => $nomeUsuario
            ]);

        } catch (\Exception $e) {
            $this->roleModel->rollback();
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar usuario de suporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui o usuario de suporte e sua role
     */
    public function excluir(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $chave = Auth::chave();

        $usuarioSuporte = $this->funcionarioModel->buscarUsuarioSuporte();
        if (!$usuarioSuporte) {
            Response::json([
                'success' => false,
                'message' => 'Nao existe usuario de suporte para excluir'
            ], 400);
            return;
        }

        $this->roleModel->beginTransaction();

        try {
            // Busca a role reservada de suporte do tenant
            $roleSuporte = $this->roleModel->buscarRoleSuporte($chave);

            // Exclui o funcionario (hard delete para usuario de suporte)
            $this->funcionarioModel->excluirPermanente($usuarioSuporte['id']);

            // Exclui a role de suporte se existir
            if ($roleSuporte) {
                // Remove permissoes da role
                $this->rolePermissionModel->deletarPorRole($roleSuporte['id']);
                // Remove a role
                $this->roleModel->deletar($roleSuporte['id']);
            }

            $this->roleModel->commit();

            Response::json([
                'success' => true,
                'message' => 'Usuario de suporte excluido com sucesso'
            ]);

        } catch (\Exception $e) {
            $this->roleModel->rollback();
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir usuario de suporte: ' . $e->getMessage()
            ], 500);
        }
    }
}
