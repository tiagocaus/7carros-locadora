#!/usr/bin/env php
<?php

/**
 * Preenche id_veiculo em lancamentos financeiros criados a partir de manutencoes.
 *
 * Uso:
 *   php scripts/normalizar-financeiro-manutencoes-veiculos.php
 *   php scripts/normalizar-financeiro-manutencoes-veiculos.php --db-config=temp-bd.txt --chave=1111111111111
 *   php scripts/normalizar-financeiro-manutencoes-veiculos.php --apply --confirm=CORRIGIR_MANUTENCAO
 */

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', ['env::', 'db-config::', 'apply', 'confirm::', 'chave::']);
$env = $options['env'] ?? getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
$apply = array_key_exists('apply', $options);
$confirm = $options['confirm'] ?? '';
$dbConfigPath = $options['db-config'] ?? null;
$chaveFiltro = $options['chave'] ?? null;

if ($apply && $confirm !== 'CORRIGIR_MANUTENCAO') {
    fwrite(STDERR, "Para aplicar, informe --confirm=CORRIGIR_MANUTENCAO.\n");
    exit(1);
}

echo "Normalizacao de veiculos no financeiro de manutencoes\n";
echo "Ambiente: {$env}\n";
echo "Credenciais DB: " . ($dbConfigPath ?: '.env.' . $env) . "\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Filtro chave: " . ($chaveFiltro ?: 'todos') . "\n\n";

$pdo = conectarPdo($env, $dbConfigPath);
$candidatos = buscarCandidatos($pdo, $chaveFiltro);

echo "Lancamentos financeiros candidatos: " . count($candidatos) . "\n";
foreach (array_slice($candidatos, 0, 20) as $row) {
    echo sprintf(
        "  financeiro=%d manutencao=%d os=%s veiculo=%d chave=%s itens_sem_veiculo=%d cabecalho_sem_veiculo=%s\n",
        (int) $row['id_financeiro'],
        (int) $row['id_manutencao'],
        $row['os'],
        (int) $row['id_veiculo'],
        $row['chave'],
        (int) $row['itens_sem_veiculo'],
        empty($row['financeiro_id_veiculo']) ? 'sim' : 'nao'
    );
}
if (count($candidatos) > 20) {
    echo "  ... " . (count($candidatos) - 20) . " lancamento(s) adicionais omitidos da amostra.\n";
}

if (!$apply) {
    echo "\nDRY-RUN concluido. Nenhum dado foi alterado.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $financeirosAtualizados = 0;
    $itensAtualizados = 0;

    $stmtFinanceiro = $pdo->prepare(
        "UPDATE financeiro
         SET id_veiculo = :id_veiculo
         WHERE id = :id_financeiro
           AND chave = :chave
           AND id_veiculo IS NULL"
    );
    $stmtItens = $pdo->prepare(
        "UPDATE financeiro_itens
         SET id_veiculo = :id_veiculo
         WHERE id_financeiro = :id_financeiro
           AND chave = :chave
           AND id_veiculo IS NULL"
    );

    foreach ($candidatos as $row) {
        $stmtFinanceiro->execute([
            ':id_veiculo' => (int) $row['id_veiculo'],
            ':id_financeiro' => (int) $row['id_financeiro'],
            ':chave' => $row['chave'],
        ]);
        $financeirosAtualizados += $stmtFinanceiro->rowCount();

        $stmtItens->execute([
            ':id_veiculo' => (int) $row['id_veiculo'],
            ':id_financeiro' => (int) $row['id_financeiro'],
            ':chave' => $row['chave'],
        ]);
        $itensAtualizados += $stmtItens->rowCount();
    }

    $pdo->commit();
    echo "\nAPLICADO com sucesso.\n";
    echo "Financeiros atualizados: {$financeirosAtualizados}\n";
    echo "Itens financeiros atualizados: {$itensAtualizados}\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Erro ao aplicar: " . $e->getMessage() . "\n");
    exit(1);
}

function buscarCandidatos(PDO $pdo, ?string $chaveFiltro): array
{
    $whereChavePrincipal = $chaveFiltro ? 'AND m.chave = :chave_principal' : '';
    $whereChaveItem = $chaveFiltro ? 'AND m.chave = :chave_item' : '';
    $sql = "
        SELECT
            f.id AS id_financeiro,
            f.id_veiculo AS financeiro_id_veiculo,
            origem.id_manutencao,
            origem.os,
            origem.chave,
            origem.id_veiculo,
            SUM(CASE WHEN fi.id IS NOT NULL AND fi.id_veiculo IS NULL THEN 1 ELSE 0 END) AS itens_sem_veiculo
        FROM (
            SELECT
                m.id AS id_manutencao,
                m.os,
                m.chave,
                m.id_veiculo,
                m.id_financeiro_principal AS id_financeiro
            FROM manutencoes m
            WHERE m.id_veiculo IS NOT NULL
              AND m.id_financeiro_principal IS NOT NULL
              {$whereChavePrincipal}

            UNION

            SELECT
                m.id AS id_manutencao,
                m.os,
                m.chave,
                m.id_veiculo,
                mi.id_financeiro
            FROM manutencoes m
            INNER JOIN manutencoes_itens mi
                ON mi.id_manutencao = m.id
               AND mi.chave = m.chave
            WHERE m.id_veiculo IS NOT NULL
              AND mi.id_financeiro IS NOT NULL
              {$whereChaveItem}
        ) origem
        INNER JOIN financeiro f
            ON f.id = origem.id_financeiro
           AND f.chave = origem.chave
        LEFT JOIN financeiro_itens fi
            ON fi.id_financeiro = f.id
           AND fi.chave = f.chave
        GROUP BY f.id, f.id_veiculo, origem.id_manutencao, origem.os, origem.chave, origem.id_veiculo
        HAVING f.id_veiculo IS NULL OR itens_sem_veiculo > 0
        ORDER BY origem.chave, origem.id_manutencao, f.id
    ";
    $stmt = $pdo->prepare($sql);
    if ($chaveFiltro) {
        $stmt->bindValue(':chave_principal', $chaveFiltro);
        $stmt->bindValue(':chave_item', $chaveFiltro);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function conectarPdo(string $env, ?string $dbConfigPath): PDO
{
    $config = $dbConfigPath ? parseDbConfigFile($dbConfigPath) : parseEnvFile(resolveEnvPath($env));
    $host = $config['DB_HOST'] ?? $config['host'] ?? '127.0.0.1';
    $port = $config['DB_PORT'] ?? $config['port'] ?? '3306';
    $database = $config['DB_DATABASE'] ?? $config['database'] ?? $config['dbname'] ?? null;
    $username = $config['DB_USERNAME'] ?? $config['username'] ?? $config['user'] ?? null;
    $password = $config['DB_PASSWORD'] ?? $config['password'] ?? $config['pass'] ?? '';

    if (!$database || !$username) {
        throw new RuntimeException('Credenciais de banco incompletas.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function resolveEnvPath(string $env): string
{
    $path = __DIR__ . '/../.env.' . $env;
    if (is_file($path)) {
        return $path;
    }

    $productionPath = __DIR__ . '/../.env.production';
    if ($env === 'development' && is_file($productionPath)) {
        return $productionPath;
    }

    return $path;
}

function parseEnvFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Arquivo de ambiente nao encontrado: {$path}");
    }
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $out[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $out;
}

function parseDbConfigFile(string $path): array
{
    $config = parseEnvFile($path);
    if (!empty($config)) {
        return $config;
    }

    $content = trim((string) file_get_contents($path));
    $parts = preg_split('/\s+/', $content);
    return [
        'host' => $parts[0] ?? null,
        'database' => $parts[1] ?? null,
        'username' => $parts[2] ?? null,
        'password' => $parts[3] ?? '',
    ];
}
