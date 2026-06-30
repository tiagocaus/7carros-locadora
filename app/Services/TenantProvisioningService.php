<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Permission;

/**
 * Service para provisionamento de tenants via WHMCS
 *
 * Gerencia o ciclo de vida completo: criar, suspender, reativar,
 * mudar plano, atualizar senha e terminar.
 *
 * Usa withoutChave() em todas as queries pois opera em contexto
 * cross-tenant sem sessão (exceção legítima documentada no CLAUDE.md).
 */
class TenantProvisioningService
{
    private QueryBuilder $qb;
    private Role $roleModel;
    private RolePermission $rolePermissionModel;
    private Permission $permissionModel;

    public function __construct()
    {
        $this->roleModel = new Role();
        $this->rolePermissionModel = new RolePermission();
        $this->permissionModel = new Permission();
        // Cria conexão mysqli para o QueryBuilder (mesmo padrão do MessageQueueService)
        $this->qb = new QueryBuilder($this->createMysqliConnection());
    }

    /**
     * Cria conexão mysqli direta (QueryBuilder usa mysqli, não PDO)
     */
    private function createMysqliConnection(): \mysqli
    {
        $mysqli = new \mysqli(
            Database::env('DB_HOST', 'localhost'),
            Database::env('DB_USERNAME'),
            Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'),
            (int) Database::env('DB_PORT', '3306')
        );

        if ($mysqli->connect_error) {
            throw new \RuntimeException('Erro ao conectar com o banco de dados: ' . $mysqli->connect_error);
        }

        $mysqli->set_charset('utf8mb4');
        return $mysqli;
    }

    /**
     * Cria um novo tenant (locadora)
     *
     * Cria funcionário admin, matriz, link filial e role com todas as permissões.
     * Idempotente: se chave já existe, retorna dados existentes.
     */
    public function criarTenant(array $dados): array
    {
        $chave = $dados['chave'];

        // Idempotência: verifica se chave já existe
        $existente = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->select(['id', 'chave', 'usuario', 'email', 'plano'])
            ->where('chave', '=', $chave)
            ->first();

        if ($existente) {
            $this->logAuditoria($chave, "Tentativa de criação duplicada. Tenant já existe.");
            return [
                'success' => true,
                'already_existed' => true,
                'chave' => $chave,
                'id_funcionario' => (int) $existente['id'],
                'usuario' => $existente['usuario'],
            ];
        }

        // Verifica se usuario é único globalmente
        $usuarioExiste = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->where('usuario', '=', $dados['usuario'])
            ->exists();

        if ($usuarioExiste) {
            throw new \InvalidArgumentException("Usuário '{$dados['usuario']}' já está em uso");
        }

        $this->qb->beginTransaction();

        try {
            // 1. Cria o funcionário admin
            $idFuncionario = $this->qb
                ->table('funcionarios')
                ->withoutChave()
                ->insert([
                    'chave' => $chave,
                    'nome' => $dados['nomeCompleto'],
                    'usuario' => $dados['usuario'],
                    'email' => $dados['email'],
                    'senha' => password_hash($dados['senha'], PASSWORD_ARGON2ID),
                    'status' => 'A',
                    'plano' => $dados['plano'],
                ]);

            // 2. Cria a matriz (filial principal)
            $idMatriz = $this->qb
                ->table('matrizes_filiais')
                ->withoutChave()
                ->insert([
                    'chave' => $chave,
                    'tipo' => 'M',
                    'razao_social' => $dados['razao_social'] ?? $dados['nomeCompleto'],
                    'nome_fantasia' => $dados['nome_fantasia'] ?? $dados['nomeCompleto'],
                    'cpf_cnpj' => $dados['cpf_cnpj'] ?? '',
                    'email' => $dados['email'],
                    'locale' => 'pt_BR',
                    'currency_code' => 'BRL',
                    'date_format' => 'd/m/Y H:i:s',
                    'sequencia_locacoes' => 1,
                    'sequencia_contratos' => 1,
                    'sequencia_financeiro' => 1,
                ]);

            // 3. Link funcionário → filial
            $this->qb
                ->table('funcionarios_filiais')
                ->withoutChave()
                ->insert([
                    'id_funcionario' => $idFuncionario,
                    'id_matriz_filial' => $idMatriz,
                    'chave' => $chave,
                ]);

            // 4. Atualiza id_matriz_filial no funcionário
            $this->qb
                ->table('funcionarios')
                ->withoutChave()
                ->where('id', '=', $idFuncionario)
                ->update(['id_matriz_filial' => $idMatriz]);

            // 5. Cria role "Proprietário" com todas as permissões
            // Mesmo padrão do ConcederAcessoController::criar()
            $roleId = $this->roleModel->criar(
                $chave,
                'Proprietário',
                'Acesso total ao sistema'
            );

            $todasPermissoes = $this->permissionModel->listarTodas();
            $permissionIds = array_column($todasPermissoes, 'id');
            $this->rolePermissionModel->sincronizar($roleId, $permissionIds);

            // 6. Atualiza id_role no funcionário
            $this->qb
                ->table('funcionarios')
                ->withoutChave()
                ->where('id', '=', $idFuncionario)
                ->update(['id_role' => $roleId]);

            $this->qb->commit();

            $this->logAuditoria(
                $chave,
                "Tenant criado. Plano: {$dados['plano']}, Usuário: {$dados['usuario']}",
                $idFuncionario
            );

            $this->logOperacaoWhmcs('create', $chave, [
                'plano' => $dados['plano'],
                'usuario' => $dados['usuario'],
                'id_funcionario' => $idFuncionario,
            ]);

            return [
                'success' => true,
                'already_existed' => false,
                'chave' => $chave,
                'id_funcionario' => $idFuncionario,
                'id_matriz_filial' => $idMatriz,
                'usuario' => $dados['usuario'],
            ];

        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Suspende um tenant (status A → S)
     */
    public function suspenderTenant(string $chave): array
    {
        $this->verificarTenantExiste($chave);

        $affected = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->where('status', '=', 'A')
            ->update(['status' => 'S']);

        $this->logAuditoria($chave, "Tenant suspenso via WHMCS. {$affected} usuário(s) afetado(s).");
        $this->logOperacaoWhmcs('suspend', $chave, ['affected_users' => $affected]);

        return [
            'success' => true,
            'affected_users' => $affected,
        ];
    }

    /**
     * Reativa um tenant (status S → A)
     */
    public function reativarTenant(string $chave): array
    {
        $this->verificarTenantExiste($chave);

        $affected = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->where('status', '=', 'S')
            ->update(['status' => 'A']);

        $this->logAuditoria($chave, "Tenant reativado via WHMCS. {$affected} usuário(s) afetado(s).");
        $this->logOperacaoWhmcs('unsuspend', $chave, ['affected_users' => $affected]);

        return [
            'success' => true,
            'affected_users' => $affected,
        ];
    }

    /**
     * Muda o plano de um tenant
     */
    public function mudarPacote(string $chave, string $plano): array
    {
        $this->verificarTenantExiste($chave);

        // Captura plano anterior para log
        $usuario = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->select(['plano'])
            ->where('chave', '=', $chave)
            ->first();

        $planoAnterior = $usuario['plano'] ?? 'N/A';

        $affected = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->update(['plano' => $plano]);

        $this->logAuditoria($chave, "Plano alterado via WHMCS: {$planoAnterior} → {$plano}. {$affected} usuário(s) afetado(s).");
        $this->logOperacaoWhmcs('change_package', $chave, [
            'plano_anterior' => $planoAnterior,
            'plano_novo' => $plano,
            'affected_users' => $affected,
        ]);

        return [
            'success' => true,
            'plano_anterior' => $planoAnterior,
            'plano_novo' => $plano,
            'affected_users' => $affected,
        ];
    }

    /**
     * Atualiza a senha de um funcionário específico
     */
    public function atualizarSenha(string $chave, string $usuario, string $senha): array
    {
        $funcionario = $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->select(['id'])
            ->where('chave', '=', $chave)
            ->where('usuario', '=', $usuario)
            ->first();

        if (!$funcionario) {
            throw new \InvalidArgumentException("Funcionário não encontrado: chave={$chave}, usuario={$usuario}");
        }

        $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->where('id', '=', $funcionario['id'])
            ->update(['senha' => password_hash($senha, PASSWORD_ARGON2ID)]);

        $this->logAuditoria($chave, "Senha atualizada via WHMCS para usuário: {$usuario}", (int) $funcionario['id']);
        $this->logOperacaoWhmcs('change_password', $chave, [
            'usuario' => $usuario,
            'id_funcionario' => (int) $funcionario['id'],
        ]);

        return [
            'success' => true,
            'usuario' => $usuario,
        ];
    }

    /**
     * Termina um tenant — apaga TODOS os dados e a pasta de uploads
     */
    public function terminarTenant(string $chave): array
    {
        // Idempotencia: terminate ja executado antes retorna sucesso, nao 404
        if (!$this->tenantExiste($chave)) {
            $this->logOperacaoWhmcs('terminate', $chave, ['already_terminated' => true]);
            return [
                'success' => true,
                'already_terminated' => true,
                'deleted_tables' => [],
            ];
        }

        $this->qb->beginTransaction();

        try {
            // Única tabela SEM coluna chave: funcionarios_role_permissions
            // Precisa buscar os role_ids primeiro
            $roleIds = $this->qb
                ->table('funcionarios_roles')
                ->withoutChave()
                ->select(['id'])
                ->where('chave', '=', $chave)
                ->pluck('id');

            if (!empty($roleIds)) {
                foreach ($roleIds as $roleId) {
                    $this->qb
                        ->table('funcionarios_role_permissions')
                        ->withoutChave()
                        ->where('role_id', '=', $roleId)
                        ->delete();
                }
            }

            // Todas as tabelas com coluna `chave` — ordem respeitando dependências
            // NÃO incluir: feature_requests, feature_request_followers, feature_request_votes (sistema interno 7Carros)
            $tabelas = [
                'funcionarios_filiais',
                'funcionarios_tokens',
                'logs',
                'checklist',
                'checklist_modelos',
                'contratos_taxaseservicos',
                'contratos_odometros',
                'contratos_veiculos',
                'contratos',
                'locacoes_taxaseservicos',
                'locacoes_veiculos',
                'locacoes',
                'financeiro_itens',
                'financeiro_transacoes',
                'financeiro',
                'manutencoes_itens',
                'manutencoes',
                'manutencoes_plano',
                'estoque',
                'veiculos_acessorios_vinculados',
                'veiculos_acessorios',
                'veiculos_encargos',
                'veiculos',
                'grupos_precos_dias_filiais',
                'grupos_precos_dias',
                'grupos_precos_filiais',
                'temporadas_grupos',
                'grupos',
                'clientes_arquivos',
                'clientes_cartoes',
                'clientes',
                'fornecedores',
                'oficinas',
                'temporadas',
                'feriados',
                'taxaseservicos_filiais',
                'taxaseservicos',
                'formas_pagamento_comandos',
                'formas_pagamento_filiais',
                'formas_pagamento_gateways',
                'formas_pagamento',
                'contas_bancarias',
                'gateways_filiais',
                'gateways_pagamento',
                'promocoes_valores_filiais',
                'promocoes_filiais',
                'promocoes',
                'comissoes_investidores',
                'documentos',
                'message_templates',
                'multas',
                'assinaturas',
                'pagamentos_links',
                'notificacoes',
                'configuracoes',
                'promissorias',
                'promissoria_templates',
                'planos_de_contas',
                'nfse_configuracoes',
                'nfse',
                'serpro_configuracoes',
                'serpro_consultas_log',
                'serpro_indicacoes',
                'serpro_saldo',
                'serpro_transacoes',
                'messages_queue',
                'whatsapp_filiais',
                'whatsapp',
                'sms_filiais',
                'sms',
                'smtp_filiais',
                'smtp',
                'horarios_excecoes',
                'horarios_funcionamento',
                'codigos_indicacao',
                'sistema_gravacoes',
                'security_logs',
                'security_user_quotas',
                'agenda',
                'contatos_emails',
                'contatos_telefones',
                'funcionarios_roles',
                'matrizes_filiais',
                'funcionarios',
            ];

            $counts = [];

            foreach ($tabelas as $tabela) {
                $deleted = $this->qb
                    ->table($tabela)
                    ->withoutChave()
                    ->where('chave', '=', $chave)
                    ->delete();

                if ($deleted > 0) {
                    $counts[$tabela] = $deleted;
                }
            }

            $this->qb->commit();

            // Apaga pasta de uploads do tenant
            $this->apagarArquivosDoTenant($chave);

            // Log no error_log pois a tabela logs foi deletada
            error_log("[WHMCS] Tenant terminado: {$chave}. Tabelas afetadas: " . json_encode($counts));

            // Trilha de auditoria sistemica (sobrevive a delecao da tabela logs)
            $this->logOperacaoWhmcs('terminate', $chave, ['deleted_tables' => $counts]);

            return [
                'success' => true,
                'deleted_tables' => $counts,
            ];

        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Apaga TODOS os arquivos do tenant em disco:
     * - storage/uploads/{chave} (PDFs NFS-e, assinaturas, fotos de checklist, imagens)
     * - storage/certificates/{chave}_*.pfx|p12 (certificados digitais NFS-e)
     * - storage/certificates/{chave} (caminho legado com subpasta)
     */
    private function apagarArquivosDoTenant(string $chave): void
    {
        $this->apagarDiretorio(APP_ROOT . '/storage/uploads/' . $chave);
        $this->apagarArquivosPorPadrao(APP_ROOT . '/storage/certificates/' . $chave . '_*.pfx');
        $this->apagarArquivosPorPadrao(APP_ROOT . '/storage/certificates/' . $chave . '_*.PFX');
        $this->apagarArquivosPorPadrao(APP_ROOT . '/storage/certificates/' . $chave . '_*.p12');
        $this->apagarArquivosPorPadrao(APP_ROOT . '/storage/certificates/' . $chave . '_*.P12');
        $this->apagarDiretorio(APP_ROOT . '/storage/certificates/' . $chave);
    }

    private function apagarArquivosPorPadrao(string $pattern): void
    {
        foreach (glob($pattern) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Remove um diretorio recursivamente (no-op se nao existir)
     */
    private function apagarDiretorio(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($path);

        error_log("[WHMCS] Diretorio removido: {$path}");
    }

    /**
     * Verifica se um tenant existe (tem pelo menos 1 funcionário)
     */
    private function verificarTenantExiste(string $chave): void
    {
        if (!$this->tenantExiste($chave)) {
            throw new \InvalidArgumentException("Tenant não encontrado: {$chave}");
        }
    }

    /**
     * Retorna true se o tenant existe (tem pelo menos 1 funcionário)
     */
    private function tenantExiste(string $chave): bool
    {
        return $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->exists();
    }

    /**
     * Registra log de auditoria para ações WHMCS
     *
     * Insere diretamente na tabela logs pois AuditLogService
     * depende de $_SESSION['chave'] que não existe em webhooks.
     */
    private function logAuditoria(string $chave, string $mensagem, ?int $idFuncionario = null): void
    {
        try {
            $this->qb
                ->table('logs')
                ->withoutChave()
                ->insert([
                    'chave' => $chave,
                    'id_funcionario' => $idFuncionario ?? 0,
                    'data' => now(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'mensagem' => '[WHMCS] ' . $mensagem,
                    'campos_alterados' => null,
                ]);
        } catch (\Exception $e) {
            // Não falhar a operação principal por erro de log
            error_log('[WHMCS] Erro ao registrar log: ' . $e->getMessage());
        }
    }

    /**
     * Trilha de auditoria sistemica para operacoes WHMCS.
     *
     * Grava uma linha JSON em storage/logs/whmcs-operations.log.
     * Ao contrario de logAuditoria(), sobrevive a delecao do tenant
     * (terminate apaga a tabela logs, este arquivo nao).
     */
    private function logOperacaoWhmcs(string $acao, string $chave, array $extra = []): void
    {
        try {
            $logDir = APP_ROOT . '/storage/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $linha = json_encode([
                'timestamp' => \App\Helpers\DateHelper::isoNow(),
                'acao' => $acao,
                'chave' => $chave,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'extra' => $extra,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

            file_put_contents(
                $logDir . '/whmcs-operations.log',
                $linha,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao gravar whmcs-operations.log: ' . $e->getMessage());
        }
    }
}
