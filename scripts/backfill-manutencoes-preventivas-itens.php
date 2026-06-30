<?php

/**
 * Backfill de itens normalizados para OS preventivas geradas pelo cron.
 *
 * Uso:
 *   php scripts/backfill-manutencoes-preventivas-itens.php --db-config=temp-bd.txt
 *   php scripts/backfill-manutencoes-preventivas-itens.php --db-config=temp-bd.txt --chave=1111111111111
 *   php scripts/backfill-manutencoes-preventivas-itens.php --db-config=temp-bd.txt --apply --confirm=BACKFILL_MANUTENCAO_ITENS
 *
 * Por padrao executa apenas em dry-run.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

use App\Core\Database;

$options = getopt('', [
    'db-config::',
    'chave::',
    'apply',
    'confirm::',
    'limit::',
]);

$apply = array_key_exists('apply', $options);
$confirm = (string) ($options['confirm'] ?? '');
$chaveFiltro = isset($options['chave']) ? trim((string) $options['chave']) : '';
$limit = isset($options['limit']) ? max(0, (int) $options['limit']) : 0;

if ($apply && $confirm !== 'BACKFILL_MANUTENCAO_ITENS') {
    fwrite(STDERR, "Para aplicar, informe --confirm=BACKFILL_MANUTENCAO_ITENS\n");
    exit(1);
}

$config = carregarConfigBanco(isset($options['db-config']) ? (string) $options['db-config'] : null);
$pdo = conectar($config);

$params = [];
$whereChave = '';
if ($chaveFiltro !== '') {
    $whereChave = ' AND m.chave = :chave';
    $params[':chave'] = $chaveFiltro;
}

$limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';

$sql = "
    SELECT m.id, m.chave, m.os, m.status, m.array_servicos, m.created_at
    FROM manutencoes m
    LEFT JOIN (
        SELECT id_manutencao, COUNT(*) AS qtd_itens
        FROM manutencoes_itens
        GROUP BY id_manutencao
    ) mi ON mi.id_manutencao = m.id
    WHERE m.motivo = 'Manutenção preventiva gerada pelo sistema.'
      AND m.array_servicos IS NOT NULL
      AND m.array_servicos <> ''
      AND m.array_servicos <> '[]'
      AND COALESCE(mi.qtd_itens, 0) = 0
      {$whereChave}
    ORDER BY m.id
    {$limitSql}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$manutencoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOs = count($manutencoes);
$totalItens = 0;
$erros = 0;
$porTenant = [];

echo "Backfill de itens de manutencao preventiva\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "OS candidatas: {$totalOs}\n";

if (!$apply) {
    echo "Nenhuma escrita sera feita. Use --apply --confirm=BACKFILL_MANUTENCAO_ITENS para aplicar.\n";
}

if ($apply) {
    $pdo->beginTransaction();
}

try {
    $insert = $pdo->prepare("
        INSERT INTO manutencoes_itens
            (chave, id_manutencao, id_estoque, descricao, quantidade, valor_unitario, desconto, valor_total, pago, ordem)
        VALUES
            (:chave, :id_manutencao, NULL, :descricao, :quantidade, :valor_unitario, 0, :valor_total, :pago, :ordem)
    ");

    foreach ($manutencoes as $manutencao) {
        $itens = json_decode((string) $manutencao['array_servicos'], true);

        if (!is_array($itens) || empty($itens)) {
            $erros++;
            continue;
        }

        $qtdItensOs = 0;
        $ordemFallback = 1;
        $pago = $manutencao['status'] === 'F' ? 'S' : 'N';

        foreach ($itens as $item) {
            if (!is_array($item) || count($item) < 5) {
                $erros++;
                continue;
            }

            $ordem = (int) ($item[0] ?? 0);
            if ($ordem <= 0) {
                $ordem = $ordemFallback;
            }

            $descricao = trim((string) ($item[1] ?? ''));
            if ($descricao === '') {
                $descricao = 'Item sem descricao';
            }

            $quantidade = normalizarNumero($item[2] ?? '1');
            $valorUnitario = normalizarNumero($item[3] ?? '0');
            $valorTotal = normalizarNumero($item[4] ?? '0');

            if ($apply) {
                $insert->execute([
                    ':chave' => $manutencao['chave'],
                    ':id_manutencao' => (int) $manutencao['id'],
                    ':descricao' => $descricao,
                    ':quantidade' => $quantidade,
                    ':valor_unitario' => $valorUnitario,
                    ':valor_total' => $valorTotal,
                    ':pago' => $pago,
                    ':ordem' => $ordem,
                ]);
            }

            $qtdItensOs++;
            $ordemFallback++;
        }

        $totalItens += $qtdItensOs;
        $porTenant[$manutencao['chave']] = ($porTenant[$manutencao['chave']] ?? 0) + $qtdItensOs;
    }

    if ($apply) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

echo "Itens " . ($apply ? 'inseridos' : 'que seriam inseridos') . ": {$totalItens}\n";
echo "Erros de parse: {$erros}\n";
echo "Tenants afetados: " . count($porTenant) . "\n";

foreach ($porTenant as $chave => $qtd) {
    echo "  {$chave}: {$qtd} item(ns)\n";
}

function carregarConfigBanco(?string $path): array
{
    if ($path !== null && $path !== '') {
        $fullPath = str_starts_with($path, '/') ? $path : APP_ROOT . '/' . $path;
        if (!is_file($fullPath)) {
            throw new RuntimeException("Arquivo de configuracao nao encontrado: {$path}");
        }

        $config = [];
        foreach (file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }

        return $config;
    }

    return [
        'DB_DRIVER' => Database::env('DB_DRIVER', 'mysql'),
        'DB_HOST' => Database::env('DB_HOST'),
        'DB_PORT' => Database::env('DB_PORT', '3306'),
        'DB_DATABASE' => Database::env('DB_DATABASE'),
        'DB_USERNAME' => Database::env('DB_USERNAME'),
        'DB_PASSWORD' => Database::env('DB_PASSWORD'),
        'DB_CHARSET' => Database::env('DB_CHARSET', 'utf8mb4'),
    ];
}

function conectar(array $config): PDO
{
    $driver = $config['DB_DRIVER'] ?? 'mysql';
    $host = $config['DB_HOST'] ?? 'localhost';
    $port = $config['DB_PORT'] ?? '3306';
    $database = $config['DB_DATABASE'] ?? '';
    $charset = $config['DB_CHARSET'] ?? 'utf8mb4';
    $username = $config['DB_USERNAME'] ?? '';
    $password = $config['DB_PASSWORD'] ?? '';

    $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function normalizarNumero(mixed $valor): float
{
    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }

    $valor = trim((string) $valor);
    if ($valor === '') {
        return 0.0;
    }

    if (str_contains($valor, ',') && str_contains($valor, '.')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } else {
        $valor = str_replace(',', '.', $valor);
    }

    return (float) $valor;
}
