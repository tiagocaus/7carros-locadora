<?php

use App\Database\Migration;

/**
 * Migration: Popular tabela role_permissions
 *
 * Associa permissões às roles baseado nas permissões existentes
 * dos usuários de cada função.
 *
 * Converte permissões do formato legado (camelCase CSV) para
 * o novo formato (dot notation).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mapeamento de permissões legadas → novas
        $permissionMap = $this->getPermissionMap();

        // Buscar todas as roles (withoutChave após get() é resetado; reforçar em cada DML — ver querybuilder.md)
        $roles = $this->db()->withoutChave()->table('roles')->select(['id', 'name'])->get();

        foreach ($roles as $role) {
            $roleId = $role['id'];
            $roleName = $role['name'];

            // Buscar um funcionário representativo desta função (com mais permissões)
            $user = $this->db()
                ->withoutChave()
                ->table('funcionarios')
                ->select(['permissoes'])
                ->whereRaw('LOWER(TRIM(funcao)) = ? AND permissoes IS NOT NULL AND permissoes != \'\'', [strtolower($roleName)])
                ->first();

            // Se não encontrou usuário com essa função, pular
            if (!$user || empty($user['permissoes'])) {
                continue;
            }

            // Converter permissões CSV para array
            $legacyPermissions = array_map('trim', explode(',', $user['permissoes']));

            // Converter para novo formato
            $newPermissions = [];
            foreach ($legacyPermissions as $legacyPerm) {
                if (isset($permissionMap[$legacyPerm])) {
                    $newPermissions[] = $permissionMap[$legacyPerm];
                }
            }

            // Remover duplicatas
            $newPermissions = array_unique($newPermissions);

            // Inserir na tabela role_permissions
            foreach ($newPermissions as $permissionKey) {
                // Buscar ID da permissão
                $permission = $this->db()
                    ->withoutChave()
                    ->table('permissions')
                    ->select(['id'])
                    ->whereRaw('`key` = ?', [$permissionKey])
                    ->first();

                if ($permission) {
                    $permissionId = (int) $permission['id'];

                    // Verificar se já existe (evitar duplicatas); first() é mais estável aqui que exists() após cadeias com get/first
                    $alreadyLinked = $this->db()
                        ->withoutChave()
                        ->table('role_permissions')
                        ->select(['id'])
                        ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
                        ->first();

                    if ($alreadyLinked === null) {
                        $this->db()->withoutChave()->table('role_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $this->db()->withoutChave()->table('role_permissions')->whereRaw('1=1')->delete();
    }

    /**
     * Mapeamento de permissões: formato legado → formato novo
     */
    private function getPermissionMap(): array
    {
        return [
            // Dashboard
            'inicio' => 'dashboard.visualizar',

            // Locações
            'locacoesVer' => 'locacoes.visualizar',
            'locacoesAdicionar' => 'locacoes.criar',
            'locacoesEditar' => 'locacoes.editar',
            'locacoesApagar' => 'locacoes.excluir',

            // Contratos
            'contratosVer' => 'contratos.visualizar',
            'contratosAdicionar' => 'contratos.criar',
            'contratosEditar' => 'contratos.editar',
            'contratosApagar' => 'contratos.excluir',

            // Veículos
            'veiculosVer' => 'veiculos.visualizar',
            'veiculosAdicionar' => 'veiculos.criar',
            'veiculosEditar' => 'veiculos.editar',
            'veiculosApagar' => 'veiculos.excluir',

            // Clientes
            'clientesVer' => 'clientes.visualizar',
            'clientesAdicionar' => 'clientes.criar',
            'clientesEditar' => 'clientes.editar',
            'clientesApagar' => 'clientes.excluir',

            // Funcionários
            'funcionariosVer' => 'funcionarios.visualizar',
            'funcionariosAdicionar' => 'funcionarios.criar',
            'funcionariosEditar' => 'funcionarios.editar',
            'funcionariosApagar' => 'funcionarios.excluir',

            // Empresas
            'empresasVer' => 'empresas.visualizar',
            'empresasAdicionar' => 'empresas.criar',
            'empresasEditar' => 'empresas.editar',
            'empresasApagar' => 'empresas.excluir',

            // Fornecedores
            'fornecedoresVer' => 'fornecedores.visualizar',
            'fornecedoresAdicionar' => 'fornecedores.criar',
            'fornecedoresEditar' => 'fornecedores.editar',
            'fornecedoresApagar' => 'fornecedores.excluir',

            // Acessórios
            'acessoriosAdicionar' => 'acessorios.criar',
            'acessoriosEditar' => 'acessorios.editar',
            'acessoriosApagar' => 'acessorios.excluir',

            // Grupos
            'gruposVer' => 'grupos.visualizar',
            'gruposAdicionar' => 'grupos.criar',
            'gruposEditar' => 'grupos.editar',
            'gruposApagar' => 'grupos.excluir',

            // Taxas e Serviços
            'taxaseservicosVer' => 'taxas_servicos.visualizar',
            'taxaseservicosAdicionar' => 'taxas_servicos.criar',
            'taxaseservicosEditar' => 'taxas_servicos.editar',
            'taxaseservicosApagar' => 'taxas_servicos.excluir',

            // Oficinas
            'oficinasVer' => 'oficinas.visualizar',
            'oficinasAdicionar' => 'oficinas.criar',
            'oficinasEditar' => 'oficinas.editar',
            'oficinasApagar' => 'oficinas.excluir',

            // Localizar
            'localizarVer' => 'localizar.visualizar',

            // Agenda
            'agendaVer' => 'agenda.visualizar',
            'agendaAdicionar' => 'agenda.criar',
            'agendaEditar' => 'agenda.editar',
            'agendaApagar' => 'agenda.excluir',

            // Website
            'websiteEditar' => 'website.editar',

            // Logs
            'logsVer' => 'logs.visualizar',

            // App Vistoria
            'appvistoriaVer' => 'app_vistoria.visualizar',

            // Financeiro
            'financeiroVer' => 'financeiro.visualizar',
            'financeiroAdicionar' => 'financeiro.criar',
            'financeiroEditar' => 'financeiro.editar',
            'financeiroApagar' => 'financeiro.excluir',

            // Multas
            'multasVer' => 'multas.visualizar',
            'multasAdicionar' => 'multas.criar',
            'multasEditar' => 'multas.editar',
            'multasApagar' => 'multas.excluir',

            // Promoções
            'promocoesVer' => 'promocoes.visualizar',
            'promocoesAdicionar' => 'promocoes.criar',
            'promocoesEditar' => 'promocoes.editar',
            'promocoesApagar' => 'promocoes.excluir',

            // Relatórios
            'relatoriosVer' => 'relatorios.visualizar',

            // Manutenções
            'manutencoesVer' => 'manutencoes.visualizar',
            'manutencoesAdicionar' => 'manutencoes.criar',
            'manutencoesEditar' => 'manutencoes.editar',
            'manutencoesApagar' => 'manutencoes.excluir',

            // Planos de Manutenção
            'manutencoesPlanosVer' => 'manutencoes_planos.visualizar',
            'manutencoesPlanosAdicionar' => 'manutencoes_planos.criar',
            'manutencoesPlanosEditar' => 'manutencoes_planos.editar',
            'manutencoesPlanosApagar' => 'manutencoes_planos.excluir',

            // Formas de Pagamento
            'formasVer' => 'formas.visualizar',
            'formasAdicionar' => 'formas.criar',
            'formasEditar' => 'formas.editar',
            'formasApagar' => 'formas.excluir',

            // Checklists
            'checklistsVer' => 'checklists.visualizar',
            'checklistsApagar' => 'checklists.excluir',

            // Modelos de Checklist
            'checklistsModelosVer' => 'checklists_modelos.visualizar',
            'checklistsModelosAdicionar' => 'checklists_modelos.criar',
            'checklistsModelosEditar' => 'checklists_modelos.editar',
            'checklistsModelosApagar' => 'checklists_modelos.excluir',

            // Contas Bancárias
            'contasVer' => 'contas.visualizar',
            'contasAdicionar' => 'contas.criar',
            'contasEditar' => 'contas.editar',
            'contasApagar' => 'contas.excluir',

            // Cartão
            'cartaoVer' => 'cartao.visualizar',

            // Documentos
            'documentosVer' => 'documentos.visualizar',
            'documentosAdicionar' => 'documentos.criar',
            'documentosEditar' => 'documentos.editar',
            'documentosApagar' => 'documentos.excluir',

            // Estoque
            'estoqueVer' => 'estoque.visualizar',
            'estoqueAdicionar' => 'estoque.criar',
            'estoqueEditar' => 'estoque.editar',
            'estoqueApagar' => 'estoque.excluir',

            // Acesso
            'acessoAdicionar' => 'acesso.criar',

            // Notificações
            'notificacoesVer' => 'notificacoes.visualizar',
            'notificacoesApagar' => 'notificacoes.excluir',

            // WhatsApp
            'whatsappEditar' => 'whatsapp.editar',

            // Configurações
            'configuracoesEditar' => 'configuracoes.editar',

            // Promissórias
            'promissoriasVer' => 'promissorias.visualizar',
            'promissoriasAdicionar' => 'promissorias.criar',
            'promissoriasEditar' => 'promissorias.editar',
            'promissoriasApagar' => 'promissorias.excluir',
        ];
    }
};
