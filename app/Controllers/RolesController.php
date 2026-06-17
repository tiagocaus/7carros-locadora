<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Views\Template;
use App\Services\AuditLogService;

/**
 * Controller de Roles (Funções)
 *
 * Gerencia operações CRUD de funções/roles de funcionários
 */
class RolesController
{
    private Role $roleModel;
    private Permission $permissionModel;
    private RolePermission $rolePermissionModel;

    public function __construct()
    {
        $this->roleModel = new Role();
        $this->permissionModel = new Permission();
        $this->rolePermissionModel = new RolePermission();
    }

    /**
     * Lista todas as roles
     *
     * Retorna:
     * - Roles de sistema (chave = '0') que NÃO foram customizadas pelo tenant
     * - Roles do tenant (customizadas ou criadas por ele)
     *
     * GET /api/roles
     */
    public function index(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar funções'
                ], 403);
                return;
            }

            $chave = Auth::chave();
            $roles = $this->roleModel->listar($chave);

            Response::json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar funções: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todas as permissões agrupadas por módulo
     *
     * GET /api/permissions
     */
    public function permissions(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar permissões'
                ], 403);
                return;
            }

            $grouped = $this->permissionModel->listarAgrupadasPorModulo();

            Response::json([
                'success' => true,
                'data' => $grouped
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar permissões: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista permissões de uma role específica
     *
     * GET /api/roles/{id}/permissions
     */
    public function rolePermissions(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar permissões'
                ], 403);
                return;
            }

            $chave = Auth::chave();
            $role = $this->roleModel->buscarPorId($id, $chave);

            if (!$role || Role::isSupportRole($role)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.not_found')
                ], 404);
                return;
            }

            $permissions = $this->permissionModel->listarPorRole($id);

            Response::json([
                'success' => true,
                'data' => $permissions
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar permissões da role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe a página de gerenciamento de roles (CRUD completo)
     *
     * GET /pages/roles/gerenciar
     */
    public function gerenciar(Request $request): void
    {
        // Verificar permissão
        if (!Auth::can('roles.visualizar')) {
            Response::forbidden('Você não tem permissão para visualizar funções');
            return;
        }

        $html = Template::render('pages.roles.gerenciar');
        Response::html($html);
    }

    /**
     * Exibe o formulário de adicionar/editar role
     *
     * GET /pages/roles/adicionar
     * GET /pages/roles/adicionar?id={id}
     */
    public function adicionar(Request $request): void
    {
        $id = $request->query('id');
        $role = null;

        // Se tem ID, é modo edição
        if ($id) {
            // Verificar permissão de edição
            if (!Auth::can('roles.editar')) {
                Response::forbidden('Você não tem permissão para editar funções');
                return;
            }

            try {
                $chave = Auth::chave();

                // Buscar role (do tenant OU de sistema chave='0')
                $role = $this->roleModel->buscarPorId((int) $id, $chave);

                if (!$role || Role::isSupportRole($role)) {
                    Response::notFound(t('modules.roles.messages.not_found'));
                    return;
                }

                // Marcar se é role de sistema
                $role['is_system'] = ($role['chave'] === '0');

                // Verificar se é customização (role do tenant que tem mesmo nome em chave='0')
                $role['is_customization'] = false;
                if ($role['chave'] !== '0') {
                    $systemRole = $this->roleModel->buscarRoleSistema($role['name']);
                    $role['is_customization'] = !empty($systemRole);
                }

                // Buscar permissões da role
                $role['permissions'] = $this->rolePermissionModel->listarIdsPorRole((int) $id);
            } catch (\Exception $e) {
                Response::serverError('Erro ao carregar função');
                return;
            }
        } else {
            // Modo criação - verificar permissão
            if (!Auth::can('roles.criar')) {
                Response::forbidden('Você não tem permissão para criar funções');
                return;
            }
        }

        $html = Template::render('pages.roles.adicionar', ['role' => $role]);
        Response::html($html);
    }

    /**
     * Cria uma nova role
     *
     * POST /roles/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar funções'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            $name = trim($request->input('name', ''));
            $description = trim($request->input('description', ''));
            $permissionsInput = $request->input('permissions', []);

            // Validação
            if (empty($name)) {
                Response::json([
                    'success' => false,
                    'message' => 'O nome da função é obrigatório'
                ], 400);
                return;
            }

            if (Role::isSupportRoleName($name)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.reserved_name')
                ], 400);
                return;
            }

            // Verificar se já existe uma role com este nome
            $existing = $this->roleModel->buscarPorNome($name, $chave);

            if ($existing) {
                Response::json([
                    'success' => false,
                    'message' => 'Já existe uma função com este nome'
                ], 400);
                return;
            }

            // Iniciar transação
            $this->roleModel->beginTransaction();

            try {
                // Inserir nova role
                $id = $this->roleModel->criar($chave, $name, $description);

                // Inserir permissões da role
                if (!empty($permissionsInput) && is_array($permissionsInput)) {
                    $this->rolePermissionModel->sincronizar($id, $permissionsInput);
                }

                $this->roleModel->commit();

                // Log de auditoria
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou função [{$name}]"
                );

                Response::json([
                    'success' => true,
                    'message' => 'Função criada com sucesso',
                    'data' => [
                        'id' => $id,
                        'name' => $name,
                        'description' => $description
                    ]
                ]);
            } catch (\Exception $e) {
                $this->roleModel->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar função: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma role existente
     *
     * Se a role for de sistema (chave = '0'), cria uma cópia customizada
     * para o tenant ao invés de editar a original.
     *
     * POST /roles/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar funções'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Verificar se a role existe (do tenant OU de sistema)
            $role = $this->roleModel->buscarPorId($id, $chave);

            if (!$role || Role::isSupportRole($role)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.not_found')
                ], 404);
                return;
            }

            $name = trim($request->input('name', ''));
            $description = trim($request->input('description', ''));
            $permissionsInput = $request->input('permissions', []);

            // Validação
            if (empty($name)) {
                Response::json([
                    'success' => false,
                    'message' => 'O nome da função é obrigatório'
                ], 400);
                return;
            }

            if (Role::isSupportRoleName($name)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.reserved_name')
                ], 400);
                return;
            }

            // Se for role de sistema (chave = '0'), criar cópia customizada
            if ($role['chave'] === '0') {
                $this->createCustomizedRole($role, $description, $permissionsInput, $chave);
                return;
            }

            // Verificar se é customização (tem mesmo nome em chave='0')
            $isCustomization = $this->roleModel->buscarRoleSistema($role['name']);

            // Se for customização, não pode mudar o nome
            if ($isCustomization && $name !== $role['name']) {
                Response::json([
                    'success' => false,
                    'message' => 'Não é possível alterar o nome de uma função personalizada do sistema'
                ], 400);
                return;
            }

            // Verificar se já existe outra role com este nome (apenas se não for customização)
            if (!$isCustomization) {
                $existing = $this->roleModel->buscarPorNomeExcluindoId($name, $chave, $id);

                if ($existing) {
                    Response::json([
                        'success' => false,
                        'message' => 'Já existe outra função com este nome'
                    ], 400);
                    return;
                }
            }

            // Iniciar transação
            $this->roleModel->beginTransaction();

            try {
                // Atualizar role (nome só se não for customização)
                if ($isCustomization) {
                    $this->roleModel->atualizarDescricao($id, $description);
                } else {
                    $this->roleModel->atualizar($id, $name, $description);
                }

                // Sincronizar permissões
                if (is_array($permissionsInput)) {
                    $this->rolePermissionModel->sincronizar($id, $permissionsInput);
                }

                $this->roleModel->commit();

                // Log de auditoria
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou função [{$role['name']}]"
                );

                Response::json([
                    'success' => true,
                    'message' => 'Função atualizada com sucesso'
                ]);
            } catch (\Exception $e) {
                $this->roleModel->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar função: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma cópia customizada de uma role de sistema
     *
     * O nome é mantido igual ao da role de sistema (identificação pelo nome)
     */
    private function createCustomizedRole(array $systemRole, string $description, array $permissions, string $chave): void
    {
        // Verificar se já existe uma customização (role com mesmo nome no tenant)
        $existingCustom = $this->roleModel->existeCustomizacao($systemRole['name'], $chave);

        if ($existingCustom) {
            Response::json([
                'success' => false,
                'message' => 'Esta função já foi personalizada. Edite a versão personalizada existente.'
            ], 400);
            return;
        }

        $this->roleModel->beginTransaction();

        try {
            // Criar cópia customizada (mesmo nome da role de sistema)
            $newId = $this->roleModel->criar($chave, $systemRole['name'], $description);

            // Inserir permissões
            if (!empty($permissions) && is_array($permissions)) {
                $this->rolePermissionModel->sincronizar($newId, $permissions);
            }

            // Atualizar funcionários que usavam a role de sistema para usar a customizada
            $this->roleModel->migrarFuncionarios($systemRole['id'], $newId, $chave);

            $this->roleModel->commit();

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", personalizou função de sistema [{$systemRole['name']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Função personalizada criada com sucesso',
                'data' => [
                    'id' => $newId,
                    'name' => $systemRole['name'],
                    'is_customized' => true
                ]
            ]);
        } catch (\Exception $e) {
            $this->roleModel->rollback();
            throw $e;
        }
    }

    /**
     * Exclui uma role
     *
     * Roles de sistema (chave = '0') não podem ser excluídas.
     * Roles customizadas podem ser excluídas - se tiverem mesmo nome em chave='0',
     * os funcionários são migrados de volta para a role de sistema.
     *
     * POST /roles/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir funções'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Verificar se a role existe
            $role = $this->roleModel->buscarPorIdSemRestricao($id);

            if (!$role || Role::isSupportRole($role)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.not_found')
                ], 404);
                return;
            }

            // Verificar se é role de sistema (chave = '0')
            if ($role['chave'] === '0') {
                Response::json([
                    'success' => false,
                    'message' => 'Funções de sistema não podem ser excluídas'
                ], 403);
                return;
            }

            // Verificar se é do tenant correto
            if ($role['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir esta função'
                ], 403);
                return;
            }

            // Verificar se é customização (tem mesmo nome em chave='0')
            $systemRole = $this->roleModel->buscarRoleSistema($role['name']);

            $this->roleModel->beginTransaction();

            try {
                // Se for customização, migrar funcionários de volta para a role de sistema
                if ($systemRole) {
                    $this->roleModel->migrarFuncionarios($id, $systemRole['id'], $chave);
                } else {
                    // Role própria do tenant - verificar se há funcionários usando
                    $funcionariosCount = $this->roleModel->contarFuncionarios($id, $chave);

                    if ($funcionariosCount > 0) {
                        $this->roleModel->rollback();
                        Response::json([
                            'success' => false,
                            'message' => 'Não é possível excluir esta função pois há ' . $funcionariosCount . ' funcionário(s) vinculado(s)'
                        ], 400);
                        return;
                    }
                }

                // Remover permissões da role
                $this->rolePermissionModel->deletarPorRole($id);

                // Excluir role
                $this->roleModel->deletar($id);

                $this->roleModel->commit();

                // Log de auditoria
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu função [{$role['name']}]"
                );

                $message = $systemRole
                    ? 'Função personalizada excluída. Os funcionários foram migrados para a função padrão do sistema.'
                    : 'Função excluída com sucesso';

                Response::json([
                    'success' => true,
                    'message' => $message
                ]);
            } catch (\Exception $e) {
                $this->roleModel->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir função: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaura uma role de sistema (exclui a versão customizada)
     *
     * POST /roles/{id}/restaurar
     */
    public function restore(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('roles.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para restaurar funções'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Verificar se a role customizada existe
            $customRole = $this->roleModel->buscarPorId($id, $chave);

            if (!$customRole || $customRole['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.not_found')
                ], 404);
                return;
            }

            if (Role::isSupportRole($customRole)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.roles.messages.not_found')
                ], 404);
                return;
            }

            // Verificar se é customização (tem mesmo nome em chave='0')
            $systemRole = $this->roleModel->buscarRoleSistema($customRole['name']);

            if (!$systemRole) {
                Response::json([
                    'success' => false,
                    'message' => 'Esta não é uma função personalizada do sistema'
                ], 400);
                return;
            }

            $this->roleModel->beginTransaction();

            try {
                // Migrar funcionários de volta para a role de sistema
                $this->roleModel->migrarFuncionarios($id, $systemRole['id'], $chave);

                // Remover permissões da role customizada
                $this->rolePermissionModel->deletarPorRole($id);

                // Excluir role customizada
                $this->roleModel->deletar($id);

                $this->roleModel->commit();

                Response::json([
                    'success' => true,
                    'message' => 'Função restaurada para o padrão do sistema'
                ]);
            } catch (\Exception $e) {
                $this->roleModel->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao restaurar função: ' . $e->getMessage()
            ], 500);
        }
    }
}
