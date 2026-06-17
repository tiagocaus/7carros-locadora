<?php

use App\Database\Migration;

/**
 * Migration 00375: remover colunas legadas de função/permissões em funcionarios.
 *
 * O padrão atual é RBAC: funcionarios.id_role -> funcionarios_roles ->
 * funcionarios_role_permissions -> permissions. Antes do DROP, funcionários
 * que ainda dependem dos campos antigos recebem uma role migrada por usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('funcionarios')) {
            return;
        }

        if ($this->columnExists('funcionarios', 'funcao') || $this->columnExists('funcionarios', 'permissoes')) {
            $this->migrarFuncionariosSemRole();
        }

        $this->dropColumnIfExists('funcionarios', 'permissoes');
        $this->dropColumnIfExists('funcionarios', 'funcao');
    }

    public function down(): void
    {
        if (!$this->tableExists('funcionarios')) {
            return;
        }

        if (!$this->columnExists('funcionarios', 'funcao')) {
            $this->execute("ALTER TABLE funcionarios ADD COLUMN funcao VARCHAR(150) NULL DEFAULT NULL AFTER foto");
        }

        if (!$this->columnExists('funcionarios', 'permissoes')) {
            $this->execute("ALTER TABLE funcionarios ADD COLUMN permissoes LONGTEXT NULL DEFAULT NULL AFTER funcao");
        }
    }

    private function migrarFuncionariosSemRole(): void
    {
        $cols = [
            'id',
            'chave',
            'usuario',
            $this->columnExists('funcionarios', 'funcao') ? 'funcao' : "'' AS funcao",
            $this->columnExists('funcionarios', 'permissoes') ? 'permissoes' : "'' AS permissoes",
        ];

        $stmt = $this->pdo->query(
            'SELECT ' . implode(', ', $cols) . '
             FROM funcionarios
             WHERE id_role IS NULL OR id_role = 0'
        );

        $funcionarios = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($funcionarios)) {
            return;
        }

        $permissionMap = $this->getPermissionMap();

        foreach ($funcionarios as $funcionario) {
            $legacyPermissions = $this->mapearPermissoesLegadas((string) ($funcionario['permissoes'] ?? ''), $permissionMap);

            if (empty($legacyPermissions)) {
                $roleId = $this->buscarRoleAtendente();
            } else {
                $roleId = $this->criarRoleMigrada($funcionario);
                $this->sincronizarPermissoesRole($roleId, $legacyPermissions);
            }

            if ($roleId > 0) {
                $update = $this->pdo->prepare('UPDATE funcionarios SET id_role = ? WHERE id = ?');
                $update->execute([$roleId, (int) $funcionario['id']]);
            }
        }
    }

    private function criarRoleMigrada(array $funcionario): int
    {
        $base = trim((string) ($funcionario['funcao'] ?? ''));
        if ($base === '') {
            $base = trim((string) ($funcionario['usuario'] ?? 'Funcionario'));
        }

        $base = str_replace('_', ' ', $base);
        $base = mb_convert_case($base, MB_CASE_TITLE, 'UTF-8');
        $name = mb_substr('Migrado - ' . $base . ' #' . (int) $funcionario['id'], 0, 120, 'UTF-8');

        $stmt = $this->pdo->prepare(
            'INSERT INTO funcionarios_roles (chave, name, description, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            (string) $funcionario['chave'],
            $name,
            'Role criada automaticamente ao remover funcionarios.funcao/permissoes legados.',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function sincronizarPermissoesRole(int $roleId, array $permissionKeys): void
    {
        $selectPermission = $this->pdo->prepare('SELECT id FROM permissions WHERE `key` = ? LIMIT 1');
        $exists = $this->pdo->prepare(
            'SELECT id FROM funcionarios_role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO funcionarios_role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())'
        );

        foreach ($permissionKeys as $permissionKey) {
            $selectPermission->execute([$permissionKey]);
            $permissionId = (int) ($selectPermission->fetchColumn() ?: 0);
            if ($permissionId <= 0) {
                continue;
            }

            $exists->execute([$roleId, $permissionId]);
            if ($exists->fetchColumn()) {
                continue;
            }

            $insert->execute([$roleId, $permissionId]);
        }
    }

    private function buscarRoleAtendente(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM funcionarios_roles WHERE chave = '0' AND name = 'Atendente' LIMIT 1"
        );
        $stmt->execute();

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function mapearPermissoesLegadas(string $permissoes, array $permissionMap): array
    {
        $legacyPermissions = array_filter(array_map('trim', explode(',', $permissoes)));
        $mapped = [];

        foreach ($legacyPermissions as $legacyPermission) {
            if (isset($permissionMap[$legacyPermission])) {
                $mapped[] = $permissionMap[$legacyPermission];
            }
        }

        return array_values(array_unique($mapped));
    }

    private function getPermissionMap(): array
    {
        return [
            'inicio' => 'dashboard.visualizar',
            'locacoesVer' => 'locacoes.visualizar',
            'locacoesAdicionar' => 'locacoes.criar',
            'locacoesEditar' => 'locacoes.editar',
            'locacoesApagar' => 'locacoes.excluir',
            'contratosVer' => 'contratos.visualizar',
            'contratosAdicionar' => 'contratos.criar',
            'contratosEditar' => 'contratos.editar',
            'contratosApagar' => 'contratos.excluir',
            'veiculosVer' => 'veiculos.visualizar',
            'veiculosAdicionar' => 'veiculos.criar',
            'veiculosEditar' => 'veiculos.editar',
            'veiculosApagar' => 'veiculos.excluir',
            'clientesVer' => 'clientes.visualizar',
            'clientesAdicionar' => 'clientes.criar',
            'clientesEditar' => 'clientes.editar',
            'clientesApagar' => 'clientes.excluir',
            'funcionariosVer' => 'funcionarios.visualizar',
            'funcionariosAdicionar' => 'funcionarios.criar',
            'funcionariosEditar' => 'funcionarios.editar',
            'funcionariosApagar' => 'funcionarios.excluir',
            'empresasVer' => 'empresas.visualizar',
            'empresasAdicionar' => 'empresas.criar',
            'empresasEditar' => 'empresas.editar',
            'empresasApagar' => 'empresas.excluir',
            'fornecedoresVer' => 'fornecedores.visualizar',
            'fornecedoresAdicionar' => 'fornecedores.criar',
            'fornecedoresEditar' => 'fornecedores.editar',
            'fornecedoresApagar' => 'fornecedores.excluir',
            'acessoriosAdicionar' => 'acessorios.criar',
            'acessoriosEditar' => 'acessorios.editar',
            'acessoriosApagar' => 'acessorios.excluir',
            'gruposVer' => 'grupos.visualizar',
            'gruposAdicionar' => 'grupos.criar',
            'gruposEditar' => 'grupos.editar',
            'gruposApagar' => 'grupos.excluir',
            'taxaseservicosVer' => 'taxas_servicos.visualizar',
            'taxaseservicosAdicionar' => 'taxas_servicos.criar',
            'taxaseservicosEditar' => 'taxas_servicos.editar',
            'taxaseservicosApagar' => 'taxas_servicos.excluir',
            'oficinasVer' => 'oficinas.visualizar',
            'oficinasAdicionar' => 'oficinas.criar',
            'oficinasEditar' => 'oficinas.editar',
            'oficinasApagar' => 'oficinas.excluir',
            'localizarVer' => 'localizar.visualizar',
            'agendaVer' => 'agenda.visualizar',
            'agendaAdicionar' => 'agenda.criar',
            'agendaEditar' => 'agenda.editar',
            'agendaApagar' => 'agenda.excluir',
            'websiteEditar' => 'website.editar',
            'logsVer' => 'logs.visualizar',
            'appvistoriaVer' => 'app_vistoria.visualizar',
            'financeiroVer' => 'financeiro.visualizar',
            'financeiroAdicionar' => 'financeiro.criar',
            'financeiroEditar' => 'financeiro.editar',
            'financeiroApagar' => 'financeiro.excluir',
            'multasVer' => 'multas.visualizar',
            'multasAdicionar' => 'multas.criar',
            'multasEditar' => 'multas.editar',
            'multasApagar' => 'multas.excluir',
            'promocoesVer' => 'promocoes.visualizar',
            'promocoesAdicionar' => 'promocoes.criar',
            'promocoesEditar' => 'promocoes.editar',
            'promocoesApagar' => 'promocoes.excluir',
            'relatoriosVer' => 'relatorios.visualizar',
            'manutencoesVer' => 'manutencoes.visualizar',
            'manutencoesAdicionar' => 'manutencoes.criar',
            'manutencoesEditar' => 'manutencoes.editar',
            'manutencoesApagar' => 'manutencoes.excluir',
            'manutencoesPlanosVer' => 'manutencoes_planos.visualizar',
            'manutencoesPlanosAdicionar' => 'manutencoes_planos.criar',
            'manutencoesPlanosEditar' => 'manutencoes_planos.editar',
            'manutencoesPlanosApagar' => 'manutencoes_planos.excluir',
            'formasVer' => 'formas.visualizar',
            'formasAdicionar' => 'formas.criar',
            'formasEditar' => 'formas.editar',
            'formasApagar' => 'formas.excluir',
            'checklistsVer' => 'checklists.visualizar',
            'checklistsApagar' => 'checklists.excluir',
            'checklistsModelosVer' => 'checklists_modelos.visualizar',
            'checklistsModelosAdicionar' => 'checklists_modelos.criar',
            'checklistsModelosEditar' => 'checklists_modelos.editar',
            'checklistsModelosApagar' => 'checklists_modelos.excluir',
            'contasVer' => 'contas.visualizar',
            'contasAdicionar' => 'contas.criar',
            'contasEditar' => 'contas.editar',
            'contasApagar' => 'contas.excluir',
            'cartaoVer' => 'cartao.visualizar',
            'documentosVer' => 'documentos.visualizar',
            'documentosAdicionar' => 'documentos.criar',
            'documentosEditar' => 'documentos.editar',
            'documentosApagar' => 'documentos.excluir',
            'estoqueVer' => 'estoque.visualizar',
            'estoqueAdicionar' => 'estoque.criar',
            'estoqueEditar' => 'estoque.editar',
            'estoqueApagar' => 'estoque.excluir',
            'acessoAdicionar' => 'acesso.criar',
            'notificacoesVer' => 'notificacoes.visualizar',
            'notificacoesApagar' => 'notificacoes.excluir',
            'whatsappEditar' => 'whatsapp.editar',
            'configuracoesEditar' => 'configuracoes.editar',
            'promissoriasVer' => 'promissorias.visualizar',
            'promissoriasAdicionar' => 'promissorias.criar',
            'promissoriasEditar' => 'promissorias.editar',
            'promissoriasApagar' => 'promissorias.excluir',
        ];
    }
};
