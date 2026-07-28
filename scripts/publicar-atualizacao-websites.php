#!/usr/bin/env php
<?php

/**
 * Publica a versao atual do template nos sites ativos dos clientes.
 *
 * Uso:
 *   php scripts/publicar-atualizacao-websites.php --env=production
 *   php scripts/publicar-atualizacao-websites.php --env=production --chave=123 --apply --confirm=1.3.0
 *   php scripts/publicar-atualizacao-websites.php --env=production --apply --confirm=1.3.0
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', [
    'env::',
    'apply',
    'confirm::',
    'chave::',
    'usuario-ftp::',
    'limit::',
    'stop-on-error',
    'help',
]);

if (array_key_exists('help', $options)) {
    echo <<<TXT
Publica a versao atual do template nos sites ativos.

Por padrao, apenas simula e nao altera dados nem envia arquivos.

Opcoes:
  --env=production       Ambiente da aplicacao (padrao: development)
  --apply                Executa os uploads
  --confirm=VERSAO       Confirmacao obrigatoria, igual a versao do template
  --chave=CHAVE          Limita a um tenant
  --usuario-ftp=USUARIO  Localiza um unico tenant pelo usuario FTP
  --limit=N              Limita a quantidade de candidatos
  --stop-on-error        Interrompe depois da primeira falha
  --help                 Exibe esta ajuda

TXT;
    exit(0);
}

$env = (string) ($options['env'] ?? getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development'));
if (!in_array($env, ['development', 'production'], true)) {
    fwrite(STDERR, "Ambiente invalido. Use development ou production.\n");
    exit(2);
}

$_ENV['APP_ENV'] = $env;
putenv('APP_ENV=' . $env);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\Model;
use App\Models\SiteConfig;
use App\Services\WebsiteBuilderService;

@set_time_limit(0);

$sessionPath = sys_get_temp_dir() . '/7carros-cli-sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
    fwrite(STDERR, "Nao foi possivel preparar a sessao CLI.\n");
    exit(1);
}
session_save_path($sessionPath);
session_start();

$apply = array_key_exists('apply', $options);
$confirm = (string) ($options['confirm'] ?? '');
$chave = isset($options['chave']) ? trim((string) $options['chave']) : null;
$usuarioFtp = isset($options['usuario-ftp']) ? trim((string) $options['usuario-ftp']) : null;
$stopOnError = array_key_exists('stop-on-error', $options);
$limit = null;

if ($chave === '' || $usuarioFtp === '') {
    fwrite(STDERR, "Os filtros informados nao podem ser vazios.\n");
    exit(2);
}

if ($chave !== null && $usuarioFtp !== null) {
    fwrite(STDERR, "Use apenas um filtro: --chave ou --usuario-ftp.\n");
    exit(2);
}

if (isset($options['limit'])) {
    $limitRaw = filter_var($options['limit'], FILTER_VALIDATE_INT);
    if ($limitRaw === false || $limitRaw < 1) {
        fwrite(STDERR, "O limite deve ser um inteiro maior que zero.\n");
        exit(2);
    }
    $limit = $limitRaw;
}

$builder = new WebsiteBuilderService();
$versaoDestino = $builder->getVersaoArquivo();
if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $versaoDestino)) {
    fwrite(STDERR, "A versao do template e invalida: {$versaoDestino}\n");
    exit(1);
}

if ($apply && !hash_equals($versaoDestino, $confirm)) {
    fwrite(
        STDERR,
        "Para aplicar, informe --confirm={$versaoDestino}, igual a versao atual do template.\n"
    );
    exit(2);
}

echo "Publicacao em lote do template de websites\n";
echo "Ambiente: {$env}\n";
echo 'Modo: ' . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Versao destino: {$versaoDestino}\n";
echo 'Filtro chave: ' . ($chave ?? 'todos') . "\n";
echo 'Filtro usuario FTP: ' . ($usuarioFtp ?? 'nenhum') . "\n";
echo 'Limite: ' . ($limit ?? 'sem limite') . "\n\n";

try {
    $sites = (new SiteConfig())->listarParaAtualizacaoEmLote($chave);
} catch (Throwable $e) {
    fwrite(STDERR, "Falha ao consultar os sites: {$e->getMessage()}\n");
    Model::closeConnection();
    exit(1);
}

if ($usuarioFtp !== null) {
    $sites = array_values(array_filter(
        $sites,
        static fn(array $site): bool => strcasecmp(
            $usuarioFtp,
            trim((string) ($site['ftp_usuario'] ?? ''))
        ) === 0
    ));

    if (count($sites) !== 1) {
        fwrite(
            STDERR,
            count($sites) === 0
                ? "Nenhum site encontrado para o usuario FTP {$usuarioFtp}.\n"
                : "Mais de um site usa o usuario FTP {$usuarioFtp}; utilize --chave.\n"
        );
        Model::closeConnection();
        exit(1);
    }
}

if ($chave !== null && $sites === []) {
    fwrite(STDERR, "Nenhum site encontrado para a chave {$chave}.\n");
    Model::closeConnection();
    exit(1);
}

$candidatos = [];
$ignorados = [];

foreach ($sites as $site) {
    $motivo = null;
    $versaoAtual = trim((string) ($site['versao'] ?? ''));

    if (($site['status'] ?? '') !== 'ativo') {
        $motivo = 'status=' . ($site['status'] ?? 'indefinido');
    } elseif (empty($site['credencial_id'])) {
        $motivo = 'credenciais FTP ausentes';
    } elseif (empty($site['tem_api_token'])) {
        $motivo = 'token da API ausente';
    } elseif ($versaoAtual !== '' && version_compare($versaoAtual, $versaoDestino, '>=')) {
        $motivo = 'versao atual ou superior';
    }

    if ($motivo !== null) {
        $ignorados[] = [
            'chave' => $site['chave'],
            'versao' => $versaoAtual !== '' ? $versaoAtual : 'nao publicada',
            'motivo' => $motivo,
        ];
        continue;
    }

    $candidatos[] = [
        'chave' => $site['chave'],
        'versao' => $versaoAtual !== '' ? $versaoAtual : 'nao publicada',
    ];
}

if ($limit !== null) {
    $candidatos = array_slice($candidatos, 0, $limit);
}

echo 'Sites consultados: ' . count($sites) . "\n";
echo 'Candidatos: ' . count($candidatos) . "\n";
echo 'Ignorados: ' . count($ignorados) . "\n";

foreach ($candidatos as $site) {
    echo "  [CANDIDATO] chave={$site['chave']} {$site['versao']} -> {$versaoDestino}\n";
}

$ignoradosExibidos = ($chave !== null || $usuarioFtp !== null)
    ? $ignorados
    : array_slice($ignorados, 0, 20);
foreach ($ignoradosExibidos as $site) {
    echo "  [IGNORADO] chave={$site['chave']} versao={$site['versao']} motivo={$site['motivo']}\n";
}
if (count($ignorados) > count($ignoradosExibidos)) {
    echo '  ... ' . (count($ignorados) - count($ignoradosExibidos)) . " ignorado(s) omitido(s).\n";
}

$motivosIgnorados = [];
foreach ($ignorados as $site) {
    $motivosIgnorados[$site['motivo']] = ($motivosIgnorados[$site['motivo']] ?? 0) + 1;
}
if ($motivosIgnorados !== []) {
    echo "Resumo dos ignorados:\n";
    foreach ($motivosIgnorados as $motivo => $totalMotivo) {
        echo "  {$motivo}: {$totalMotivo}\n";
    }
}

if (!$apply) {
    echo "\nDRY-RUN concluido. Nenhum arquivo ou dado foi alterado.\n";
    Model::closeConnection();
    exit(0);
}

if ($candidatos === []) {
    echo "\nNenhum site precisa ser atualizado.\n";
    Model::closeConnection();
    exit(0);
}

echo "\nIniciando publicacao sequencial...\n";
$sucessos = 0;
$falhas = 0;

foreach ($candidatos as $indice => $site) {
    $numero = $indice + 1;
    $total = count($candidatos);
    echo "[{$numero}/{$total}] chave={$site['chave']}... ";

    try {
        $resultado = $builder->deploy(
            (string) $site['chave'],
            null,
            'update',
            [
                'origem' => 'cli_bulk_template',
                'ambiente' => $env,
            ]
        );
    } catch (Throwable $e) {
        $resultado = [
            'success' => false,
            'message' => $e->getMessage(),
        ];
    }

    if ($resultado['success'] ?? false) {
        $sucessos++;
        echo "OK - {$resultado['message']}\n";
        continue;
    }

    $falhas++;
    echo 'FALHA - ' . ($resultado['message'] ?? 'erro desconhecido') . "\n";
    if ($stopOnError) {
        echo "Execucao interrompida por --stop-on-error.\n";
        break;
    }
}

echo "\nResumo\n";
echo "  Sucessos: {$sucessos}\n";
echo "  Falhas: {$falhas}\n";
echo '  Nao processados nesta execucao: ' . (count($candidatos) - $sucessos - $falhas) . "\n";

Model::closeConnection();
exit($falhas > 0 ? 1 : 0);
