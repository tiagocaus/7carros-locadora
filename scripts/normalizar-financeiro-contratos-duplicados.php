#!/usr/bin/env php
<?php

/**
 * Normaliza parcelas financeiras duplicadas geradas por contratos.
 *
 * Uso:
 *   php scripts/normalizar-financeiro-contratos-duplicados.php --db-config=temp-bd.txt
 *   php scripts/normalizar-financeiro-contratos-duplicados.php --db-config=temp-bd.txt --chave=1111111111111
 *   php scripts/normalizar-financeiro-contratos-duplicados.php --db-config=temp-bd.txt --apply --confirm-delete=REMOVER_DUPLICADOS
 *
 * O modo padrao eh DRY-RUN. Em --apply, apenas remove duplicados pendentes que
 * tenham uma parcela paga equivalente no mesmo tenant, contrato, vencimento e valor.
 */

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', [
    'env::',
    'db-config::',
    'apply',
    'confirm-delete::',
    'chave::',
    'contrato::',
    'limit::',
]);

$env = $options['env'] ?? 'development';
putenv('APP_ENV=' . $env);
$_ENV['APP_ENV'] = $env;

$apply = array_key_exists('apply', $options);
$confirmDelete = $options['confirm-delete'] ?? '';
$dbConfigPath = $options['db-config'] ?? null;
$chaveFiltro = $options['chave'] ?? null;
$contratoFiltro = isset($options['contrato']) ? (int) $options['contrato'] : null;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;

if ($apply && $confirmDelete !== 'REMOVER_DUPLICADOS') {
    fwrite(STDERR, "Para aplicar, informe --confirm-delete=REMOVER_DUPLICADOS.\n");
    exit(1);
}

echo "Normalizacao de financeiros duplicados de contratos\n";
echo "Ambiente: {$env}\n";
echo "Credenciais DB: " . ($dbConfigPath ?: '.env.' . $env) . "\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Filtro chave: " . ($chaveFiltro ?: 'todos') . "\n";
echo "Filtro contrato: " . ($contratoFiltro ?: 'todos') . "\n";
echo "Limite: " . ($limit ?: 'sem limite') . "\n\n";

$pdo = conectarPdo($dbConfigPath);
$candidatos = buscarCandidatos($pdo, $chaveFiltro, $contratoFiltro, $limit);

if (empty($candidatos)) {
    echo "Nenhum duplicado comprovado encontrado.\n";
    exit(0);
}

echo "Duplicados comprovados encontrados: " . count($candidatos) . "\n\n";

foreach (array_slice($candidatos, 0, 50) as $row) {
    echo sprintf(
        "#%d contrato=%d chave=%s venc=%s valor=%0.2f pago_ref=%d criado=%s desc=\"%s\"\n",
        (int) $row['id'],
        (int) $row['id_contrato'],
        $row['chave'],
        $row['data_venci'],
        (float) $row['valor_total'],
        (int) $row['matched_paid_id'],
        $row['created_at'] ?? '-',
        abreviar((string) ($row['descricao'] ?? ''), 80)
    );
}

if (count($candidatos) > 50) {
    echo "... " . (count($candidatos) - 50) . " candidato(s) adicionais omitidos da amostra.\n";
}

if (!$apply) {
    echo "\nDRY-RUN concluido. Nenhum dado foi alterado.\n";
    exit(0);
}

$ids = array_map(static fn(array $row): int => (int) $row['id'], $candidatos);

criarTabelaAuditoria($pdo);
$pdo->beginTransaction();
try {
    registrarAuditoria($pdo, $candidatos);
    removerDuplicados($pdo, $ids);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo "\nAplicado com sucesso. Duplicados removidos: " . count($ids) . "\n";
echo "Auditoria gravada em financeiro_duplicados_normalizacao_audit.\n";

function buscarCandidatos(PDO $pdo, ?string $chave, ?int $contratoId, ?int $limit): array
{
    $where = [
        "f.pago = 'N'",
        'f.id_contrato IS NOT NULL',
        'f.data_venci < CURDATE()',
        'f.codigo IS NULL',
        'ABS(COALESCE(f.valor_total, 0)) >= 0.01',
    ];
    $params = [];

    if ($chave !== null && $chave !== '') {
        $where[] = 'f.chave = :chave';
        $params[':chave'] = $chave;
    }

    if ($contratoId !== null && $contratoId > 0) {
        $where[] = 'f.id_contrato = :contrato';
        $params[':contrato'] = $contratoId;
    }

    $sql = "
        SELECT
            f.id,
            f.chave,
            f.id_contrato,
            f.data_venci,
            f.valor_total,
            f.pago,
            f.codigo,
            f.descricao,
            f.created_at,
            MIN(dup.id) AS matched_paid_id,
            GROUP_CONCAT(dup.id ORDER BY dup.id) AS paid_matches
        FROM financeiro f
        INNER JOIN financeiro dup
            ON dup.chave = f.chave
           AND dup.id_contrato = f.id_contrato
           AND dup.data_venci = f.data_venci
           AND ABS(COALESCE(dup.valor_total, 0) - COALESCE(f.valor_total, 0)) < 0.01
           AND dup.pago = 'S'
           AND dup.id <> f.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY
            f.id, f.chave, f.id_contrato, f.data_venci, f.valor_total,
            f.pago, f.codigo, f.descricao, f.created_at
        ORDER BY f.created_at ASC, f.id ASC
    ";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function criarTabelaAuditoria(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS financeiro_duplicados_normalizacao_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_financeiro_duplicado INT UNSIGNED NOT NULL,
            id_financeiro_pago INT UNSIGNED NOT NULL,
            chave VARCHAR(45) NOT NULL,
            id_contrato INT UNSIGNED NOT NULL,
            data_venci DATE NOT NULL,
            valor_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            acao VARCHAR(30) NOT NULL,
            payload TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_fin_dup_norm (id_financeiro_duplicado),
            KEY idx_fin_dup_norm_chave_contrato (chave, id_contrato)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function registrarAuditoria(PDO $pdo, array $candidatos): void
{
    $stmt = $pdo->prepare("
        INSERT INTO financeiro_duplicados_normalizacao_audit (
            id_financeiro_duplicado,
            id_financeiro_pago,
            chave,
            id_contrato,
            data_venci,
            valor_total,
            acao,
            payload
        ) VALUES (
            :id_financeiro_duplicado,
            :id_financeiro_pago,
            :chave,
            :id_contrato,
            :data_venci,
            :valor_total,
            'delete_duplicate_pending',
            :payload
        )
        ON DUPLICATE KEY UPDATE
            id_financeiro_pago = VALUES(id_financeiro_pago),
            payload = VALUES(payload)
    ");

    foreach ($candidatos as $row) {
        $stmt->execute([
            ':id_financeiro_duplicado' => (int) $row['id'],
            ':id_financeiro_pago' => (int) $row['matched_paid_id'],
            ':chave' => $row['chave'],
            ':id_contrato' => (int) $row['id_contrato'],
            ':data_venci' => $row['data_venci'],
            ':valor_total' => (float) $row['valor_total'],
            ':payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

function removerDuplicados(PDO $pdo, array $ids): void
{
    foreach (array_chunk($ids, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("DELETE FROM financeiro WHERE id IN ({$placeholders}) AND pago = 'N' AND codigo IS NULL");
        $stmt->execute($chunk);
    }
}

function conectarPdo(?string $dbConfigPath): PDO
{
    $config = $dbConfigPath
        ? carregarEnvArquivo($dbConfigPath)
        : [
            'DB_DRIVER' => App\Core\Database::env('DB_DRIVER', 'mysql'),
            'DB_HOST' => App\Core\Database::env('DB_HOST', 'localhost'),
            'DB_PORT' => App\Core\Database::env('DB_PORT', '3306'),
            'DB_DATABASE' => App\Core\Database::env('DB_DATABASE'),
            'DB_USERNAME' => App\Core\Database::env('DB_USERNAME'),
            'DB_PASSWORD' => App\Core\Database::env('DB_PASSWORD'),
            'DB_CHARSET' => App\Core\Database::env('DB_CHARSET', 'utf8mb4'),
        ];

    $driver = $config['DB_DRIVER'] ?? 'mysql';
    $host = $config['DB_HOST'] ?? 'localhost';
    $port = $config['DB_PORT'] ?? '3306';
    $database = $config['DB_DATABASE'] ?? '';
    $username = $config['DB_USERNAME'] ?? '';
    $password = $config['DB_PASSWORD'] ?? '';
    $charset = $config['DB_CHARSET'] ?? 'utf8mb4';
    $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function carregarEnvArquivo(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo de credenciais nao encontrado: ' . $path);
    }

    $config = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim(trim($value), '"\'');
    }

    return $config;
}

function abreviar(string $texto, int $limite): string
{
    if (mb_strlen($texto) <= $limite) {
        return $texto;
    }

    return mb_substr($texto, 0, $limite - 3) . '...';
}
