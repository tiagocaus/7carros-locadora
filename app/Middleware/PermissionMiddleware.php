<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware de Permissões
 *
 * Verifica se o usuário tem a permissão necessária para acessar a rota
 *
 * Uso nas rotas:
 *   $router->get('/clientes', [Controller::class, 'index'], ['auth', 'permission:clientes.visualizar']);
 */
class PermissionMiddleware
{
    /**
     * Executa o middleware
     *
     * @param Request $request
     * @param string $permission A permissão requerida (ex: 'clientes.visualizar')
     * @return bool
     */
    public function handle(Request $request, string $permission): bool
    {
        // Verifica se o usuário tem a permissão
        if (!Auth::can($permission)) {
            // Mensagem personalizada baseada na permissão
            $message = $this->getMessage($permission);

            // Retorna erro 403
            Response::forbidden($message);
        }

        return true;
    }

    /**
     * Gera uma mensagem amigável baseada na permissão
     */
    private function getMessage(string $permission): string
    {
        // Separa módulo e ação
        $parts = explode('.', $permission);
        $module = $parts[0] ?? '';
        $action = $parts[1] ?? '';

        // Nomes dos módulos em português
        $modules = [
            'dashboard' => 'o Dashboard',
            'clientes' => 'Clientes',
            'funcionarios' => 'Funcionários',
            'veiculos' => 'Veículos',
            'locacoes' => 'Locações',
            'contratos' => 'Contratos',
            'reservas' => 'Reservas',
            'financeiro' => 'Financeiro',
            'relatorios' => 'Relatórios',
            'configuracoes' => 'Configurações',
            'roles' => 'Funções',
            'manutencao' => 'Manutenções',
            'multas' => 'Multas',
            'checklist' => 'Checklists',
        ];

        // Nomes das ações em português
        $actions = [
            'visualizar' => 'visualizar',
            'criar' => 'criar',
            'editar' => 'editar',
            'excluir' => 'excluir',
            'gerenciar' => 'gerenciar',
        ];

        $moduleName = $modules[$module] ?? $module;
        $actionName = $actions[$action] ?? $action;

        return "Você não tem permissão para {$actionName} {$moduleName}.";
    }
}
