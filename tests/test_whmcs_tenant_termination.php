<?php

/**
 * Teste de regressão do término completo e idempotente de tenants pelo WHMCS.
 *
 * Execute: php tests/test_whmcs_tenant_termination.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Controllers\WhmcsController;
use App\Core\Request;
use App\Models\TenantProvisioning;
use App\Services\TenantProvisioningService;
use App\Services\AuthorizationHoldReleaseService;

if (($argv[1] ?? '') === '--response-contract') {
    $_POST = [];

    if (($argv[2] ?? '') === 'success') {
        $_POST['chave'] = (string) ($argv[3] ?? '');
    }

    (new WhmcsController())->terminar(new Request());
}

$pdo = Database::getConnection();
$chave = 'WHMCS_TEST_' . bin2hex(random_bytes(8));
$usuario = strtolower($chave);
$uploadDir = APP_ROOT . '/storage/uploads/' . $chave;
$certificateDir = APP_ROOT . '/storage/certificates';
$certificatePath = $certificateDir . '/' . $chave . '_teste.pfx';
$falhas = 0;

function verificarWhmcs(bool $condicao, string $mensagem): void
{
    global $falhas;

    echo ($condicao ? 'PASS' : 'FAIL') . " - {$mensagem}\n";
    if (!$condicao) {
        $falhas++;
    }
}

function contarTenant(\PDO $pdo, string $tabela, string $chave): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$tabela}` WHERE chave = ?");
    $stmt->execute([$chave]);
    return (int) $stmt->fetchColumn();
}

function limparArquivosTeste(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
    }

    @rmdir($path);
}

function executarContratoRespostaWhmcs(string $cenario, ?string $chave = null): array
{
    $comando = [PHP_BINARY, __FILE__, '--response-contract', $cenario];
    if ($chave !== null) {
        $comando[] = $chave;
    }

    $pipes = [];
    $processo = proc_open(
        $comando,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($processo)) {
        throw new RuntimeException('Não foi possível iniciar o teste do contrato HTTP');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return [
        'exit_code' => proc_close($processo),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

try {
    $model = new TenantProvisioning();
    $diagnostico = $model->diagnosticarInventario();

    verificarWhmcs(
        count($diagnostico['ordem']) === count($diagnostico['gerenciadas']),
        'todas as tabelas tenant descobertas receberam ordem de exclusão'
    );
    verificarWhmcs($diagnostico['ciclos'] === [], 'foreign keys restritivas não formam ciclos');
    verificarWhmcs(
        in_array('_site_legacy', $diagnostico['ordem'], true),
        'tabela legada existente é descoberta automaticamente'
    );
    verificarWhmcs(
        !in_array('grupos_precos_dias', $diagnostico['ordem'], true),
        'tabela removida não depende de inventário manual'
    );
    verificarWhmcs(
        $diagnostico['bloqueios_exclusoes'] === [],
        'tabelas internas preservadas não bloqueiam o término'
    );

    $service = new TenantProvisioningService();
    try {
        $service->terminarTenant('../tenant');
        verificarWhmcs(false, 'chave com path traversal deveria ser rejeitada');
    } catch (InvalidArgumentException) {
        verificarWhmcs(true, 'chave com path traversal é rejeitada antes de acessar arquivos');
    }

    $criado = $service->criarTenant([
        'chave' => $chave,
        'nomeCompleto' => 'Teste WHMCS',
        'email' => $usuario . '@example.test',
        'usuario' => $usuario,
        'senha' => 'teste-inicial',
        'plano' => 'P4',
    ]);
    $idFuncionario = (int) $criado['id_funcionario'];

    verificarWhmcs($criado['success'] === true, 'provisionamento usa a conexão Singleton');
    verificarWhmcs($service->suspenderTenant($chave)['affected_users'] === 1, 'suspensão isola pela chave informada');
    verificarWhmcs($service->reativarTenant($chave)['affected_users'] === 1, 'reativação isola pela chave informada');

    $pacote = $service->mudarPacote($chave, 'P2');
    verificarWhmcs(
        $pacote['plano_anterior'] === 'P4' && $pacote['plano_novo'] === 'P2',
        'alteração de pacote preserva o plano anterior'
    );

    $service->atualizarSenha($chave, $usuario, 'teste-atualizado');
    $stmt = $pdo->prepare('SELECT senha FROM funcionarios WHERE id = ? AND chave = ?');
    $stmt->execute([$idFuncionario, $chave]);
    verificarWhmcs(
        password_verify('teste-atualizado', (string) $stmt->fetchColumn()),
        'alteração de senha permanece tenant-scoped'
    );

    $modelComFalha = new class extends TenantProvisioning {
        public function apagarDadosTenant(string $chave, array $tabelas): array
        {
            parent::apagarDadosTenant($chave, ['funcionarios']);
            throw new RuntimeException(
                'falha simulada após DELETE accesshash=valor-secreto senha=senha-secreta'
            );
        }
    };
    $serviceComFalha = new TenantProvisioningService($modelComFalha);
    $logPath = APP_ROOT . '/storage/logs/whmcs-operations.log';
    $logOffset = is_file($logPath) ? filesize($logPath) : 0;

    try {
        $serviceComFalha->terminarTenant($chave);
        verificarWhmcs(false, 'falha de banco simulada deveria interromper o término');
    } catch (RuntimeException $e) {
        verificarWhmcs(
            str_starts_with($e->getMessage(), 'falha simulada após DELETE'),
            'falha de banco é propagada para o controller'
        );
        verificarWhmcs(
            contarTenant($pdo, 'funcionarios', $chave) === 1,
            'transação restaura os dados após falha'
        );

        clearstatcache(true, $logPath);
        $logAdicionado = is_file($logPath)
            ? (string) file_get_contents($logPath, false, null, $logOffset)
            : '';
        verificarWhmcs(
            str_contains($logAdicionado, '"acao":"terminate_failed"')
            && str_contains($logAdicionado, '"fase":"tabelas_tenant"')
            && str_contains($logAdicionado, '"tabela":"funcionarios"')
            && str_contains($logAdicionado, 'accesshash=[redacted]')
            && str_contains($logAdicionado, 'senha=[redacted]')
            && !str_contains($logAdicionado, 'valor-secreto')
            && !str_contains($logAdicionado, 'senha-secreta'),
            'falha registra fase e tabela no log persistente'
        );
    }

    $stmt = $pdo->prepare(
        'INSERT INTO comissoes_funcionarios
            (chave, id_funcionario, tipo_origem, valor_base, comissao_tipo,
             comissao_valor_fixo, valor_comissao, valor_total, data_referencia)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$chave, $idFuncionario, 'manual', 100, 'fixo', 10, 10, 10, date('Y-m-d')]);

    $stmt = $pdo->prepare('INSERT INTO site_config (chave, status) VALUES (?, ?)');
    $stmt->execute([$chave, 'inativo']);

    mkdir($uploadDir, 0755, true);
    file_put_contents($uploadDir . '/arquivo.txt', 'teste');
    if (!is_dir($certificateDir)) {
        mkdir($certificateDir, 0755, true);
    }
    file_put_contents($certificatePath, 'teste');

    $holdReleaseComFalha = new class extends AuthorizationHoldReleaseService {
        public function __construct()
        {
        }

        public function liberarDoTenant(string $chave): array
        {
            throw new RuntimeException(
                'falha simulada token=token-secreto'
            );
        }
    };
    $serviceComFalhaBloqueio = new TenantProvisioningService(
        new TenantProvisioning(),
        $holdReleaseComFalha
    );
    $logOffsetBloqueio = is_file($logPath) ? filesize($logPath) : 0;
    $resultado = $serviceComFalhaBloqueio->terminarTenant($chave);

    verificarWhmcs($resultado['success'] === true, 'primeiro término retorna sucesso');
    clearstatcache(true, $logPath);
    $logBloqueio = is_file($logPath)
        ? (string) file_get_contents($logPath, false, null, $logOffsetBloqueio)
        : '';
    verificarWhmcs(
        str_contains($logBloqueio, '"acao":"terminate_hold_release"')
        && str_contains($logBloqueio, '"failed":1')
        && str_contains($logBloqueio, 'token=[redacted]')
        && !str_contains($logBloqueio, 'token-secreto'),
        'falha ao liberar hold e registrada e nao impede termino WHMCS'
    );
    verificarWhmcs(
        ($resultado['deleted_tables']['comissoes_funcionarios'] ?? 0) === 1,
        'tabela recente sem cascade é contabilizada'
    );
    verificarWhmcs(
        ($resultado['deleted_tables']['site_config'] ?? 0) === 1,
        'dados do site são contabilizados'
    );

    foreach ($diagnostico['atuais'] as $tabela) {
        if (str_starts_with($tabela, 'feature_request')) {
            continue;
        }
        verificarWhmcs(contarTenant($pdo, $tabela, $chave) === 0, "{$tabela} não preservou dados do tenant");
    }

    verificarWhmcs(!is_dir($uploadDir), 'diretório de uploads foi removido');
    verificarWhmcs(!is_file($certificatePath), 'certificado foi removido');

    mkdir($uploadDir, 0755, true);
    file_put_contents($uploadDir . '/retry.txt', 'teste');
    file_put_contents($certificatePath, 'teste');

    $retry = $service->terminarTenant($chave);
    verificarWhmcs(($retry['already_terminated'] ?? false) === true, 'retry é idempotente');
    verificarWhmcs(!is_dir($uploadDir), 'retry remove uploads remanescentes');
    verificarWhmcs(!is_file($certificatePath), 'retry remove certificados remanescentes');

    $chaveContrato = 'WHMCS_CONTRACT_' . bin2hex(random_bytes(8));
    $respostaSucesso = executarContratoRespostaWhmcs('success', $chaveContrato);
    verificarWhmcs(
        $respostaSucesso['exit_code'] === 0
        && $respostaSucesso['stdout'] === '{"success":true}',
        'controller expõe somente success=true no término'
    );

    $respostaErro = executarContratoRespostaWhmcs('missing-key');
    verificarWhmcs(
        $respostaErro['exit_code'] === 0
        && $respostaErro['stdout'] === '{"success":false,"message":"Campo obrigatório ausente: chave"}',
        'controller expõe somente success=false e message no erro'
    );
} catch (Throwable $e) {
    $falhas++;
    echo 'FAIL - exceção: ' . $e->getMessage() . "\n";
} finally {
    try {
        $cleanup = new TenantProvisioning();
        if ($cleanup->tenantExiste($chave)) {
            $cleanup->beginTransaction();
            $cleanup->apagarPermissoesRoles($cleanup->roleIds($chave));
            $cleanup->apagarDadosTenant($chave, $cleanup->tabelasParaTermino());
            $cleanup->commit();
        }
    } catch (Throwable) {
        try {
            $cleanup->rollback();
        } catch (Throwable) {
            // Melhor esforço para não esconder a falha original.
        }
    }

    limparArquivosTeste($uploadDir);
    @unlink($certificatePath);
}

echo $falhas === 0
    ? "OK - término de tenant WHMCS validado\n"
    : "ERRO - {$falhas} falha(s)\n";

exit($falhas === 0 ? 0 : 1);
