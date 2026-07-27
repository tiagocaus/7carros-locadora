<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Permission;
use App\Models\TenantProvisioning;

/**
 * Service para provisionamento de tenants via WHMCS
 *
 * Gerencia o ciclo de vida completo: criar, suspender, reativar,
 * mudar plano, atualizar senha e terminar.
 *
 * Opera sem sessão, sempre informando a chave do tenant nas queries isoladas.
 */
class TenantProvisioningService
{
    private QueryBuilder $qb;
    private Role $roleModel;
    private RolePermission $rolePermissionModel;
    private Permission $permissionModel;
    private TenantProvisioning $tenantModel;
    private AuthorizationHoldReleaseService $holdReleaseService;

    public function __construct(
        ?TenantProvisioning $tenantModel = null,
        ?AuthorizationHoldReleaseService $holdReleaseService = null
    )
    {
        $this->roleModel = new Role();
        $this->rolePermissionModel = new RolePermission();
        $this->permissionModel = new Permission();
        $this->tenantModel = $tenantModel ?? new TenantProvisioning();
        $this->holdReleaseService = $holdReleaseService ?? new AuthorizationHoldReleaseService();
        $this->qb = new QueryBuilder(Model::sharedMysqli());
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
            ->withChave($chave)
            ->select(['id', 'chave', 'usuario', 'email', 'plano'])
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
                ->withChave($chave)
                ->insert([
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
                ->withChave($chave)
                ->insert([
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
                ->withChave($chave)
                ->insert([
                    'id_funcionario' => $idFuncionario,
                    'id_matriz_filial' => $idMatriz,
                ]);

            // 4. Atualiza id_matriz_filial no funcionário
            $this->qb
                ->table('funcionarios')
                ->withChave($chave)
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
                ->withChave($chave)
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
            ->withChave($chave)
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
            ->withChave($chave)
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
            ->withChave($chave)
            ->select(['plano'])
            ->first();

        $planoAnterior = $usuario['plano'] ?? 'N/A';

        $affected = $this->qb
            ->table('funcionarios')
            ->withChave($chave)
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
            ->withChave($chave)
            ->select(['id'])
            ->where('usuario', '=', $usuario)
            ->first();

        if (!$funcionario) {
            throw new \InvalidArgumentException("Funcionário não encontrado: chave={$chave}, usuario={$usuario}");
        }

        $this->qb
            ->table('funcionarios')
            ->withChave($chave)
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
        $fase = 'validacao';
        $transacaoAtiva = false;
        $counts = [];
        $holdRelease = null;

        try {
            $this->validarChaveParaArquivos($chave);

            $fase = 'consulta_tenant';
            $alreadyTerminated = !$this->tenantExiste($chave);

            if (!$alreadyTerminated) {
                $fase = 'preflight_schema';
                $tabelas = $this->tenantModel->tabelasParaTermino();

                $fase = 'consulta_roles';
                $roleIds = $this->tenantModel->roleIds($chave);

                $fase = 'liberacao_bloqueios';
                try {
                    $holdRelease = $this->holdReleaseService->liberarDoTenant($chave);
                } catch (\Throwable $releaseError) {
                    $holdRelease = [
                        'total' => 0,
                        'released' => 0,
                        'already_safe' => 0,
                        'failed' => 1,
                        'failures' => [[
                            'origem' => 'tenant',
                            'id' => 0,
                            'exception' => get_class($releaseError),
                            'message' => $releaseError->getMessage(),
                        ]],
                    ];
                }
                $holdRelease = $this->sanitizarResultadoBloqueios($holdRelease);
                $this->logOperacaoWhmcs('terminate_hold_release', $chave, $holdRelease);

                $fase = 'inicio_transacao';
                $this->tenantModel->beginTransaction();
                $transacaoAtiva = true;

                $fase = 'permissoes_roles';
                $this->tenantModel->apagarPermissoesRoles($roleIds);

                $fase = 'tabelas_tenant';
                $counts = $this->tenantModel->apagarDadosTenant($chave, $tabelas);

                $fase = 'commit';
                $this->tenantModel->commit();
                $transacaoAtiva = false;
            }

            // Também roda no retry idempotente, concluindo uma eventual limpeza
            // de arquivos que tenha falhado depois do commit do banco.
            $fase = 'arquivos';
            $this->apagarArquivosDoTenant($chave);

            $extra = [
                'deleted_tables' => $counts,
                'hold_release' => $holdRelease,
            ];
            if ($alreadyTerminated) {
                $extra['already_terminated'] = true;
            }

            error_log("[WHMCS] Tenant terminado: {$chave}. Tabelas afetadas: " . json_encode($counts));
            $this->logOperacaoWhmcs('terminate', $chave, $extra);

            $resultado = [
                'success' => true,
                'deleted_tables' => $counts,
            ];

            if ($alreadyTerminated) {
                $resultado['already_terminated'] = true;
            }

            return $resultado;
        } catch (\Throwable $e) {
            $rollbackError = null;

            if ($transacaoAtiva) {
                try {
                    $this->tenantModel->rollback();
                } catch (\Throwable $rollbackException) {
                    $rollbackError = $this->sanitizarErro($rollbackException->getMessage());
                }
            }

            $extra = [
                'fase' => $fase,
                'tabela' => $fase === 'tabelas_tenant'
                    ? $this->tenantModel->ultimaTabelaProcessada()
                    : null,
                'exception' => get_class($e),
                'message' => $this->sanitizarErro($e->getMessage()),
            ];

            if ($rollbackError !== null) {
                $extra['rollback_error'] = $rollbackError;
            }

            $this->logOperacaoWhmcs('terminate_failed', $chave, $extra);
            throw $e;
        }
    }

    /**
     * Impede path traversal e metacaracteres de glob na limpeza de arquivos.
     */
    private function validarChaveParaArquivos(string $chave): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $chave)) {
            throw new \InvalidArgumentException('Chave do tenant inválida');
        }
    }

    private function sanitizarErro(string $message): string
    {
        $message = preg_replace(
            '/\\b(accesshash|password|passwd|senha|secret|token)\\s*([=:])\\s*([^\\s&,;]+)/iu',
            '$1$2[redacted]',
            $message
        ) ?? $message;

        return substr(str_replace(["\r", "\n"], ' ', $message), 0, 1000);
    }

    private function sanitizarResultadoBloqueios(array $result): array
    {
        foreach ($result['failures'] ?? [] as $index => $failure) {
            $result['failures'][$index]['message'] = $this->sanitizarErro(
                (string) ($failure['message'] ?? '')
            );
        }

        return $result;
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
            if (is_file($path) && !@unlink($path) && is_file($path)) {
                throw new \RuntimeException("Não foi possível remover o arquivo: {$path}");
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
                $dirPath = $file->getRealPath();
                if (!@rmdir($dirPath) && is_dir($dirPath)) {
                    throw new \RuntimeException("Não foi possível remover o diretório: {$dirPath}");
                }
            } else {
                $filePath = $file->getRealPath();
                if (!@unlink($filePath) && is_file($filePath)) {
                    throw new \RuntimeException("Não foi possível remover o arquivo: {$filePath}");
                }
            }
        }

        if (!@rmdir($path) && is_dir($path)) {
            throw new \RuntimeException("Não foi possível remover o diretório: {$path}");
        }

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
        return $this->tenantModel->tenantExiste($chave);
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
                ->withChave($chave)
                ->insert([
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
            if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                throw new \RuntimeException('Não foi possível criar storage/logs');
            }

            $linha = json_encode([
                'timestamp' => \App\Helpers\DateHelper::isoNow(),
                'acao' => $acao,
                'chave' => $chave,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'extra' => $extra,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

            $written = @file_put_contents(
                $logDir . '/whmcs-operations.log',
                $linha,
                FILE_APPEND | LOCK_EX
            );

            if ($written === false) {
                throw new \RuntimeException('Não foi possível gravar whmcs-operations.log');
            }
        } catch (\Throwable $e) {
            error_log('[WHMCS] Erro ao gravar whmcs-operations.log: ' . $e->getMessage());
        }
    }
}
