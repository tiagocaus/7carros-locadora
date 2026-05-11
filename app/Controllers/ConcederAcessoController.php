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

    /**
     * Exibe a pagina de conceder acesso
     */
    public function view(): void
    {
        $html = Template::render('pages.conceder-acesso.index');
        Response::html($html);
    }

    /**
     * Retorna o status atual (se existe usuario de suporte)
     */
    public function status(): void
    {
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
            // Cria role "Suporte 7Carros" com todas as permissoes
            $roleId = $this->roleModel->criar(
                $chave,
                'Suporte 7Carros',
                'Funcao temporaria para acesso do suporte tecnico'
            );

            // Busca todas as permissoes do sistema
            $todasPermissoes = $this->permissionModel->listarTodas();
            $permissionIds = array_column($todasPermissoes, 'id');

            // Atribui todas as permissoes a role
            $this->rolePermissionModel->sincronizar($roleId, $permissionIds);

            // Cria o usuario de suporte herdando o plano do tenant
            $this->funcionarioModel->criar([
                'chave' => $chave,
                'nome' => 'Suporte 7Carros',
                'usuario' => $nomeUsuario,
                'email' => $nomeUsuario . '@suporte.7carros.com',
                'senha' => $senha,
                'status' => 'A',
                'funcao' => 'Suporte Tecnico',
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
            // Busca a role "Suporte 7Carros" do tenant
            $roleSuporte = $this->roleModel->buscarPorNome('Suporte 7Carros', $chave);

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
