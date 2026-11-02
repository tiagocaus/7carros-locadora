<?php

/**
 * Integracao read-only da listagem publica de filiais.
 * Execute: php tests/test_website_filiais_ativas.php
 * Exige site ativo e filiais ativas/inativas no tenant local de teste.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Request;
use App\Controllers\PublicWebsiteController;
use App\Models\MatrizFilial;
use App\Models\SiteConfig;

define('APP_ROOT', dirname(__DIR__));
$_ENV['APP_ENV'] = 'development';
require_once APP_ROOT . '/app/Helpers/helpers.php';

function exigirFiliaisAtivas(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

try {
    exigirFiliaisAtivas(Database::env('DB_HOST') === 'localhost', 'O teste exige DB_HOST=localhost.');
    $_SESSION = ['chave' => '1111111111111'];
    $model = new MatrizFilial();
    $todas = $model->listar();
    $inativas = array_filter($todas, static fn(array $f): bool => $f['status'] === 'I');
    exigirFiliaisAtivas($inativas !== [], 'Cenario local incompleto: falta filial inativa no tenant de teste.');

    $esperadas = Database::fetchAll(
        "SELECT id FROM matrizes_filiais
         WHERE chave = ? AND status = ?
           AND (TRIM(COALESCE(estado, '')) <> ''
                OR TRIM(COALESCE(cidade, '')) <> ''
                OR TRIM(COALESCE(nome_fantasia, razao_social, '')) <> '')
         ORDER BY tipo DESC, nome_fantasia ASC",
        ['1111111111111', 'A']
    );
    exigirFiliaisAtivas($esperadas !== [], 'Cenario local incompleto: falta unidade ativa publicavel.');
    $config = (new SiteConfig())->buscarPorChave();
    exigirFiliaisAtivas(
        $config !== null && ($config['status'] ?? '') === 'ativo',
        'Cenario local incompleto: configure um site ativo para o tenant 1111111111111.'
    );

    if (($argv[1] ?? '') === '--endpoint') {
        $token = empty($config['api_token']) ? '' : decrypt($config['api_token']);
        exigirFiliaisAtivas($token !== null, 'Nao foi possivel descriptografar o token local.');
        $_GET = ['chave' => '1111111111111'];
        $_POST = $_FILES = $_COOKIE = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_X_SITE_TOKEN' => $token];
        (new PublicWebsiteController())->dadosSite(new Request());
        exit(1);
    }

    // Response::json encerra o processo; executar o endpoint real em processo separado.
    $processo = proc_open(
        [PHP_BINARY, __FILE__, '--endpoint'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        APP_ROOT
    );
    exigirFiliaisAtivas(is_resource($processo), 'Nao foi possivel iniciar o endpoint.');
    fclose($pipes[0]);
    $saida = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $erros = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $codigo = proc_close($processo);
    exigirFiliaisAtivas($codigo === 0 && $erros === '', 'Falha ao executar o endpoint local.');
    $dados = json_decode($saida, true);
    exigirFiliaisAtivas(is_array($dados) && ($dados['success'] ?? false) === true, 'Endpoint nao retornou JSON de sucesso.');
    $ids = array_map('intval', array_column($dados['filiais'] ?? [], 'id'));
    exigirFiliaisAtivas($ids === array_map('intval', array_column($esperadas, 'id')), 'Lista publica diverge das unidades ativas do tenant ou da ordenacao esperada.');
    exigirFiliaisAtivas(array_intersect($ids, array_column($inativas, 'id')) === [], 'API publicou filial inativa.');
    foreach ($dados['filiais'] as $filial) {
        foreach (['email', 'telefone', 'whatsapp', 'currency_code', 'locale', 'horarios', 'precos_grupos'] as $campo) {
            exigirFiliaisAtivas(array_key_exists($campo, $filial), "Campo esperado ausente: {$campo}.");
        }
    }
    echo "OK: API publica apenas unidades ativas do tenant; listagem administrativa preserva inativas.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FALHA: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
