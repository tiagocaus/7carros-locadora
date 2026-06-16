<?php

use App\Database\Migration;

/**
 * Migration: Criar roles padrão do sistema
 *
 * Roles de sistema usam chave = 'system' e is_system = 1
 * Elas são visíveis para todos os tenants e não podem ser editadas/excluídas diretamente
 */
return new class extends Migration
{
    /**
     * Definição das roles de sistema com suas permissões
     */
    private function getSystemRoles(): array
    {
        return [
            [
                'name' => 'Proprietário',
                'description' => 'Acesso total ao sistema. Possui todas as permissões disponíveis.',
                'permissions' => ['*'] // Todas as permissões
            ],
            [
                'name' => 'Gerente',
                'description' => 'Gestão operacional completa da locadora.',
                'permissions' => [
                    'dashboard.visualizar',
                    'locacoes.*',
                    'contratos.*',
                    'veiculos.*',
                    'clientes.*',
                    'funcionarios.*',
                    'financeiro.*',
                    'relatorios.visualizar',
                    'matrizes_filiais.*',
                    'grupos.*',
                    'formas.*',
                    'taxas_servicos.*',
                    'acessorios.*',
                    'manutencoes.*',
                    'manutencoes_planos.*',
                    'multas.*',
                    'promocoes.*',
                    'oficinas.*',
                    'fornecedores.*',
                    'agenda.*',
                    'checklists.*',
                    'checklists_modelos.*',
                    'contas.*',
                    'documentos.*',
                    'estoque.*',
                    'promissorias.*',
                    'notificacoes.*',
                    'localizar.visualizar',
                ]
            ],
            [
                'name' => 'Coordenador Administrativo',
                'description' => 'Coordenação de equipes e processos administrativos.',
                'permissions' => [
                    'dashboard.visualizar',
                    'locacoes.*',
                    'contratos.visualizar',
                    'contratos.criar',
                    'contratos.editar',
                    'clientes.*',
                    'funcionarios.visualizar',
                    'funcionarios.criar',
                    'funcionarios.editar',
                    'financeiro.visualizar',
                    'financeiro.criar',
                    'financeiro.editar',
                    'relatorios.visualizar',
                    'matrizes_filiais.visualizar',
                    'veiculos.visualizar',
                    'grupos.visualizar',
                    'agenda.*',
                    'contas.visualizar',
                    'documentos.*',
                    'notificacoes.visualizar',
                ]
            ],
            [
                'name' => 'Assistente Administrativo',
                'description' => 'Suporte às atividades administrativas do dia a dia.',
                'permissions' => [
                    'dashboard.visualizar',
                    'locacoes.visualizar',
                    'locacoes.criar',
                    'locacoes.editar',
                    'contratos.visualizar',
                    'contratos.criar',
                    'clientes.visualizar',
                    'clientes.criar',
                    'clientes.editar',
                    'veiculos.visualizar',
                    'financeiro.visualizar',
                    'financeiro.criar',
                    'agenda.visualizar',
                    'agenda.criar',
                    'documentos.visualizar',
                    'notificacoes.visualizar',
                ]
            ],
            [
                'name' => 'Atendente',
                'description' => 'Atendimento ao cliente e operações básicas de locação.',
                'permissions' => [
                    'dashboard.visualizar',
                    'locacoes.visualizar',
                    'locacoes.criar',
                    'locacoes.editar',
                    'locacoes.devolucao',
                    'contratos.visualizar',
                    'contratos.criar',
                    'contratos.editar',
                    'contratos.devolver',
                    'contratos.substituir',
                    'clientes.visualizar',
                    'clientes.criar',
                    'clientes.editar',
                    'funcionarios.visualizar',
                    'veiculos.visualizar',
                    'matrizes_filiais.visualizar',
                    'agenda.visualizar',
                    'agenda.criar',
                    'grupos.visualizar',
                    'taxas_servicos.visualizar',
                    'acessorios.visualizar',
                ]
            ],
            [
                'name' => 'Vistoriador',
                'description' => 'Inspeção e vistoria de veículos na entrada e saída.',
                'permissions' => [
                    'dashboard.visualizar',
                    'veiculos.visualizar',
                    'veiculos.editar',
                    'locacoes.visualizar',
                    'checklists.visualizar',
                    'checklists_modelos.visualizar',
                    'app_vistoria.visualizar',
                ]
            ],
            [
                'name' => 'Manobrador',
                'description' => 'Movimentação de veículos no pátio da locadora.',
                'permissions' => [
                    'dashboard.visualizar',
                    'veiculos.visualizar',
                    'locacoes.visualizar',
                    'localizar.visualizar',
                ]
            ],
            [
                'name' => 'Serviços Gerais',
                'description' => 'Apoio operacional e serviços de suporte.',
                'permissions' => [
                    'dashboard.visualizar',
                ]
            ],
        ];
    }

    public function up(): void
    {
        $systemRoles = $this->getSystemRoles();

        foreach ($systemRoles as $roleData) {
            // Verificar se já existe
            $existing = $this->db()->table('roles')->select(['id'])->whereRaw('chave = ? AND name = ?', ['system', $roleData['name']])->get();

            if (!empty($existing)) {
                continue;
            }

            // Criar role de sistema
            $roleId = $this->db()->table('roles')->insert([
                'chave' => 'system',
                'is_system' => 1,
                'parent_id' => null,
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Atribuir permissões
            $this->assignPermissions($roleId, $roleData['permissions']);
        }
    }

    /**
     * Atribui permissões a uma role
     */
    private function assignPermissions(int $roleId, array $permissionPatterns): void
    {
        // Buscar todas as permissões do sistema usando query direta
        $stmt = $this->pdo->query("SELECT id, `key` FROM permissions");
        $allPermissions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($allPermissions as $permission) {
            $shouldAssign = false;
            $permKey = $permission['key'];

            foreach ($permissionPatterns as $pattern) {
                if ($pattern === '*') {
                    // Todas as permissões
                    $shouldAssign = true;
                    break;
                } elseif (str_ends_with($pattern, '.*')) {
                    // Padrão de módulo (ex: locacoes.*)
                    $module = str_replace('.*', '', $pattern);
                    if (str_starts_with($permKey, $module . '.')) {
                        $shouldAssign = true;
                        break;
                    }
                } elseif ($permKey === $pattern) {
                    // Permissão exata
                    $shouldAssign = true;
                    break;
                }
            }

            if ($shouldAssign) {
                // Verificar se já existe
                $existing = $this->db()->table('role_permissions')->select(['id'])->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permission['id']])->get();

                if (empty($existing)) {
                    $this->db()->table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permission['id'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Buscar todas as roles de sistema
        $systemRoles = $this->db()->table('roles')->select(['id'])->whereRaw('chave = ? AND is_system = 1', ['system'])->get();

        foreach ($systemRoles as $role) {
            // Remover permissões
            $this->db()->table('role_permissions')->whereRaw('role_id = ?', [$role['id']])->delete();

            // Remover role
            $this->db()->table('roles')->whereRaw('id = ?', [$role['id']])->delete();
        }
    }
};
