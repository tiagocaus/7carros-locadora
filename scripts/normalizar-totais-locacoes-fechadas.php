#!/usr/bin/env php
<?php

/**
 * Normaliza total_fatura/total_pagar de locacoes fechadas antigas.
 *
 * Uso:
 *   php scripts/normalizar-totais-locacoes-fechadas.php --db-config=temp-bd.txt
 *   php scripts/normalizar-totais-locacoes-fechadas.php --db-config=temp-bd.txt --chave=1111111111111
 *   php scripts/normalizar-totais-locacoes-fechadas.php --db-config=temp-bd.txt --locacao=144459
 *   php scripts/normalizar-totais-locacoes-fechadas.php --db-config=temp-bd.txt --batch-size=100
 *   php scripts/normalizar-totais-locacoes-fechadas.php --db-config=temp-bd.txt --apply --confirm=NORMALIZAR_LOCACOES
 *
 * O modo padrao eh DRY-RUN. A fonte de verdade eh o mesmo totalizador backend
 * usado pelo Resumo da Locacao.
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Locacao;
use App\Models\Model;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', [
    'env::',
    'db-config::',
    'apply',
    'confirm::',
    'chave::',
    'locacao::',
    'limit::',
    'batch-size::',
    'max-batches::',
    'csv::',
]);

$env = $options['env'] ?? getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
$dbConfigPath = $options['db-config'] ?? null;
$apply = array_key_exists('apply', $options);
$confirm = $options['confirm'] ?? '';
$chaveFiltro = $options['chave'] ?? null;
$locacaoFiltro = isset($options['locacao']) ? (int) $options['locacao'] : null;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;
$batchSize = isset($options['batch-size']) ? max(1, min(1000, (int) $options['batch-size'])) : 100;
$maxBatches = isset($options['max-batches']) ? max(1, (int) $options['max-batches']) : null;
$csvPath = $options['csv'] ?? (__DIR__ . '/normalizar-totais-locacoes-fechadas-' . date('Ymd-His') . '.csv');

if ($apply && $confirm !== 'NORMALIZAR_LOCACOES') {
    fwrite(STDERR, "Para aplicar, informe --confirm=NORMALIZAR_LOCACOES.\n");
    exit(1);
}

putenv('APP_ENV=' . $env);
$_ENV['APP_ENV'] = $env;

$config = $dbConfigPath ? parseDbConfigFile($dbConfigPath) : parseEnvFile(resolveEnvPath($env));
aplicarConfigDatabase($config);
$pdo = conectarPdo($config);

echo "Normalizacao de totais de locacoes fechadas\n";
echo "Ambiente: {$env}\n";
echo "Credenciais DB: " . ($dbConfigPath ?: '.env.' . $env) . "\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Filtro chave: " . ($chaveFiltro ?: 'todos') . "\n";
echo "Filtro locacao: " . ($locacaoFiltro ?: 'todos') . "\n";
echo "Limite: " . ($limit ?: 'sem limite') . "\n";
echo "Tamanho do lote: {$batchSize}\n";
echo "Maximo de lotes: " . ($maxBatches ?: 'sem limite') . "\n";
echo "CSV: {$csvPath}\n\n";

inicializarCsv($csvPath);
if ($apply) {
    criarTabelaAuditoria($pdo);
}

$cursorId = null;
$lote = 0;
$totalCandidatos = 0;
$totalCalculados = 0;
$totalErros = 0;
$totalAtualizaveis = 0;
$totalAtualizadas = 0;
$totalVeiculosAtualizados = 0;

while (true) {
    if ($maxBatches !== null && $lote >= $maxBatches) {
        echo "\nLimite de lotes atingido (--max-batches={$maxBatches}).\n";
        break;
    }

    $restante = $limit !== null ? $limit - $totalCandidatos : null;
    if ($restante !== null && $restante <= 0) {
        echo "\nLimite de candidatos atingido (--limit={$limit}).\n";
        break;
    }

    $tamanhoBusca = $restante !== null ? min($batchSize, $restante) : $batchSize;
    $candidatos = buscarCandidatos($pdo, $chaveFiltro, $locacaoFiltro, $tamanhoBusca, $cursorId);
    if (empty($candidatos)) {
        break;
    }

    $lote++;
    $totalCandidatos += count($candidatos);
    $cursorId = min(array_map(static fn(array $row): int => (int) $row['id'], $candidatos));

    echo "Lote {$lote}: candidatos=" . count($candidatos) . " cursor_id={$cursorId}\n";

    $linhas = calcularNovosTotais($candidatos);
    gravarCsv($csvPath, $linhas, true);

    $erros = contarErros($linhas);
    $atualizaveis = filtrarAtualizaveis($linhas);
    $totalCalculados += count($linhas) - $erros;
    $totalErros += $erros;
    $totalAtualizaveis += count($atualizaveis);

    $resultadoAplicacao = ['locacoes' => 0, 'veiculos' => 0];
    if ($apply && !empty($atualizaveis)) {
        $resultadoAplicacao = aplicarAtualizaveis($pdo, $atualizaveis, $csvPath);
        $totalAtualizadas += $resultadoAplicacao['locacoes'];
        $totalVeiculosAtualizados += $resultadoAplicacao['veiculos'];
    }

    echo sprintf(
        "  calculados=%d erros=%d atualizaveis=%d locacoes_atualizadas=%d veiculos_atualizados=%d memoria=%s pico=%s\n",
        count($linhas) - $erros,
        $erros,
        count($atualizaveis),
        $resultadoAplicacao['locacoes'],
        $resultadoAplicacao['veiculos'],
        formatarBytes(memory_get_usage(true)),
        formatarBytes(memory_get_peak_usage(true))
    );

    unset($candidatos, $linhas, $atualizaveis);
    Model::closeConnection();
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    if ($locacaoFiltro !== null) {
        break;
    }
}

Model::closeConnection();

echo "\nResumo final\n";
echo "Lotes processados: {$lote}\n";
echo "Candidatos encontrados: {$totalCandidatos}\n";
echo "Calculados com sucesso: {$totalCalculados}\n";
echo "Com erro: {$totalErros}\n";
echo "Atualizaveis: {$totalAtualizaveis}\n";
echo "Locacoes atualizadas: {$totalAtualizadas}\n";
echo "Historicos de veiculo atualizados: {$totalVeiculosAtualizados}\n";
echo "CSV: {$csvPath}\n";

if (!$apply) {
    echo "\nDRY-RUN concluido. Nenhum dado foi alterado.\n";
} else {
    echo "\nAPLICADO com sucesso.\n";
    echo "Auditoria gravada em locacoes_totais_normalizacao_audit.\n";
}

function buscarCandidatos(PDO $pdo, ?string $chave, ?int $locacaoId, int $limit, ?int $cursorId = null): array
{
    $where = [
        "l.status = 'F'",
        'EXISTS (SELECT 1 FROM locacoes_veiculos lv WHERE lv.id_locacao = l.id AND lv.chave = l.chave)',
    ];
    $params = [];

    if ($locacaoId === null) {
        $where[] = "(
            (
                COALESCE(l.total_fatura, 0) = 0
                AND EXISTS (
                    SELECT 1
                    FROM financeiro f
                    WHERE f.id_locacao = l.id
                      AND f.chave = l.chave
                      AND f.tipo = 'R'
                      AND COALESCE(f.valor_total, 0) > 0
                )
            )
            OR EXISTS (
                SELECT 1
                FROM locacoes_veiculos lv_check
                WHERE lv_check.id_locacao = l.id
                  AND lv_check.chave = l.chave
                  AND lv_check.id = (
                    SELECT MAX(lv_last.id)
                    FROM locacoes_veiculos lv_last
                    WHERE lv_last.id_locacao = l.id
                      AND lv_last.chave = l.chave
                  )
                  AND (
                    (
                        lv_check.plano = 'KMC'
                        AND lv_check.odometro_saida IS NOT NULL
                        AND lv_check.odometro_entrada IS NOT NULL
                        AND (
                            lv_check.km_excedente IS NULL
                            OR lv_check.km_excedente <> GREATEST(
                                0,
                                CASE
                                    WHEN lv_check.odometro_entrada > lv_check.odometro_saida
                                    THEN CAST(lv_check.odometro_entrada AS SIGNED) - CAST(lv_check.odometro_saida AS SIGNED)
                                    ELSE 0
                                END - (COALESCE(lv_check.km_franquia, 0) * GREATEST(1, COALESCE(l.dias, 1)))
                            )
                        )
                    )
                    OR (
                        lv_check.combustivel_saida IS NOT NULL
                        AND lv_check.combustivel_entrada IS NOT NULL
                        AND (
                            lv_check.combustivel_usado IS NULL
                            OR lv_check.combustivel_usado <> CASE
                                WHEN lv_check.combustivel_saida > lv_check.combustivel_entrada
                                THEN CAST(lv_check.combustivel_saida AS SIGNED) - CAST(lv_check.combustivel_entrada AS SIGNED)
                                ELSE 0
                            END
                        )
                    )
                  )
            )
        )";
    }

    if ($chave !== null && $chave !== '') {
        $where[] = 'l.chave = :chave';
        $params[':chave'] = $chave;
    }
    if ($locacaoId !== null && $locacaoId > 0) {
        $where[] = 'l.id = :locacao';
        $params[':locacao'] = $locacaoId;
    } elseif ($cursorId !== null && $cursorId > 0) {
        $where[] = 'l.id < :cursor_id';
        $params[':cursor_id'] = $cursorId;
    }

    $limitSql = ' LIMIT ' . (int) $limit;
    $sql = "
        SELECT
            l.id,
            l.chave,
            l.codigo,
            l.total_fatura,
            l.total_pagar,
            COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS total_receitas,
            COALESCE(SUM(CASE WHEN f.tipo = 'R' AND f.pago = 'S' THEN f.valor_total ELSE 0 END), 0) AS total_pago,
            (
                SELECT lv.id
                FROM locacoes_veiculos lv
                WHERE lv.id_locacao = l.id
                  AND lv.chave = l.chave
                ORDER BY lv.id DESC
                LIMIT 1
            ) AS id_locacao_veiculo_atual,
            (
                SELECT lv.km_excedente
                FROM locacoes_veiculos lv
                WHERE lv.id_locacao = l.id
                  AND lv.chave = l.chave
                ORDER BY lv.id DESC
                LIMIT 1
            ) AS km_excedente_atual,
            (
                SELECT lv.combustivel_usado
                FROM locacoes_veiculos lv
                WHERE lv.id_locacao = l.id
                  AND lv.chave = l.chave
                ORDER BY lv.id DESC
                LIMIT 1
            ) AS combustivel_usado_atual,
            (
                SELECT lv.combustivel_valor
                FROM locacoes_veiculos lv
                WHERE lv.id_locacao = l.id
                  AND lv.chave = l.chave
                ORDER BY lv.id DESC
                LIMIT 1
            ) AS combustivel_valor_atual
        FROM locacoes l
        LEFT JOIN financeiro f
            ON f.id_locacao = l.id
           AND f.chave = l.chave
        WHERE " . implode(' AND ', $where) . "
        GROUP BY l.id, l.chave, l.codigo, l.total_fatura, l.total_pagar
        ORDER BY l.id DESC
        {$limitSql}
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calcularNovosTotais(array $candidatos): array
{
    $locacaoModel = new Locacao();
    $linhas = [];

    foreach ($candidatos as $row) {
        $_SESSION['chave'] = $row['chave'];

        try {
            $totais = $locacaoModel->calcularTotaisResumo((int) $row['id']);
            $linhas[] = [
                'id' => (int) $row['id'],
                'chave' => $row['chave'],
                'codigo' => $row['codigo'],
                'total_fatura_atual' => (float) $row['total_fatura'],
                'total_pagar_atual' => (float) $row['total_pagar'],
                'total_fatura_calculado' => (float) $totais['total_fatura'],
                'total_pagar_calculado' => (float) $totais['total_pagar'],
                'total_receitas' => (float) $row['total_receitas'],
                'total_pago' => (float) $row['total_pago'],
                'km_excedente_atual' => $row['km_excedente_atual'],
                'combustivel_usado_atual' => $row['combustivel_usado_atual'],
                'combustivel_valor_atual' => $row['combustivel_valor_atual'],
                'total_veiculos' => (float) $totais['total_veiculos'],
                'total_taxas' => (float) $totais['total_taxas'],
                'total_condutores' => (float) $totais['total_condutores'],
                'total_km_excedente' => (float) $totais['total_km_excedente'],
                'total_combustivel' => (float) $totais['total_combustivel'],
                'km_excedente' => (int) ($totais['km_excedente'] ?? 0),
                'valor_km_excedente' => (float) ($totais['valor_km_excedente'] ?? 0),
                'combustivel_usado' => (int) ($totais['combustivel_usado'] ?? 0),
                'valor_combustivel_unitario' => (float) ($totais['valor_combustivel_unitario'] ?? 0),
                'id_locacao_veiculo' => $totais['id_locacao_veiculo'] ?? null,
                'erro' => '',
            ];
        } catch (Throwable $e) {
            $linhas[] = [
                'id' => (int) $row['id'],
                'chave' => $row['chave'],
                'codigo' => $row['codigo'],
                'total_fatura_atual' => (float) $row['total_fatura'],
                'total_pagar_atual' => (float) $row['total_pagar'],
                'total_fatura_calculado' => 0,
                'total_pagar_calculado' => 0,
                'total_receitas' => (float) $row['total_receitas'],
                'total_pago' => (float) $row['total_pago'],
                'km_excedente_atual' => $row['km_excedente_atual'] ?? null,
                'combustivel_usado_atual' => $row['combustivel_usado_atual'] ?? null,
                'combustivel_valor_atual' => $row['combustivel_valor_atual'] ?? null,
                'total_veiculos' => 0,
                'total_taxas' => 0,
                'total_condutores' => 0,
                'total_km_excedente' => 0,
                'total_combustivel' => 0,
                'km_excedente' => 0,
                'valor_km_excedente' => 0,
                'combustivel_usado' => 0,
                'valor_combustivel_unitario' => 0,
                'id_locacao_veiculo' => null,
                'erro' => $e->getMessage(),
            ];
        }
    }

    unset($_SESSION['chave']);
    return $linhas;
}

function filtrarAtualizaveis(array $linhas): array
{
    return array_values(array_filter($linhas, static function (array $row): bool {
        $kmAtual = $row['km_excedente_atual'];
        $combustivelUsadoAtual = $row['combustivel_usado_atual'];
        $combustivelValorAtual = (float) $row['combustivel_valor_atual'];
        $veiculoInconsistente = !empty($row['id_locacao_veiculo'])
            && (
                $kmAtual === null
                || (int) $kmAtual !== (int) $row['km_excedente']
                || $combustivelUsadoAtual === null
                || (int) $combustivelUsadoAtual !== (int) $row['combustivel_usado']
                || abs($combustivelValorAtual - (float) $row['total_combustivel']) > 0.009
            );

        return empty($row['erro'])
            && (
                abs((float) $row['total_fatura_atual'] - (float) $row['total_fatura_calculado']) > 0.009
                || abs((float) $row['total_pagar_atual'] - (float) $row['total_pagar_calculado']) > 0.009
                || $veiculoInconsistente
            );
    }));
}

function aplicarAtualizaveis(PDO $pdo, array $atualizaveis, string $csvPath): array
{
    $pdo->beginTransaction();
    try {
        $stmtUpdate = $pdo->prepare(
            "UPDATE locacoes
             SET total_fatura = :total_fatura,
                 total_pagar = :total_pagar,
                 updated_at = NOW()
             WHERE id = :id
               AND chave = :chave"
        );
        $stmtUpdateVeiculo = $pdo->prepare(
            "UPDATE locacoes_veiculos
             SET km_excedente = :km_excedente,
                 combustivel_usado = :combustivel_usado,
                 combustivel_valor = :combustivel_valor,
                 updated_at = NOW()
             WHERE id = :id_locacao_veiculo
               AND id_locacao = :id_locacao
               AND chave = :chave"
        );
        $stmtAudit = $pdo->prepare(
            "INSERT INTO locacoes_totais_normalizacao_audit
                (chave, id_locacao, codigo, total_fatura_anterior, total_pagar_anterior,
                 total_fatura_novo, total_pagar_novo, total_receitas, total_pago,
                 dry_run_csv, created_at)
             VALUES
                (:chave, :id_locacao, :codigo, :total_fatura_anterior, :total_pagar_anterior,
                 :total_fatura_novo, :total_pagar_novo, :total_receitas, :total_pago,
                 :dry_run_csv, NOW())"
        );

        $atualizadas = 0;
        $veiculosAtualizados = 0;
        foreach ($atualizaveis as $row) {
            $stmtAudit->execute([
                ':chave' => $row['chave'],
                ':id_locacao' => (int) $row['id'],
                ':codigo' => $row['codigo'],
                ':total_fatura_anterior' => (float) $row['total_fatura_atual'],
                ':total_pagar_anterior' => (float) $row['total_pagar_atual'],
                ':total_fatura_novo' => (float) $row['total_fatura_calculado'],
                ':total_pagar_novo' => (float) $row['total_pagar_calculado'],
                ':total_receitas' => (float) $row['total_receitas'],
                ':total_pago' => (float) $row['total_pago'],
                ':dry_run_csv' => $csvPath,
            ]);

            $stmtUpdate->execute([
                ':total_fatura' => (float) $row['total_fatura_calculado'],
                ':total_pagar' => (float) $row['total_pagar_calculado'],
                ':id' => (int) $row['id'],
                ':chave' => $row['chave'],
            ]);
            $atualizadas += $stmtUpdate->rowCount();

            if (!empty($row['id_locacao_veiculo'])) {
                $stmtUpdateVeiculo->execute([
                    ':km_excedente' => (int) $row['km_excedente'],
                    ':combustivel_usado' => (int) $row['combustivel_usado'],
                    ':combustivel_valor' => (float) $row['total_combustivel'],
                    ':id_locacao_veiculo' => (int) $row['id_locacao_veiculo'],
                    ':id_locacao' => (int) $row['id'],
                    ':chave' => $row['chave'],
                ]);
                $veiculosAtualizados += $stmtUpdateVeiculo->rowCount();
            }
        }

        $pdo->commit();

        return [
            'locacoes' => $atualizadas,
            'veiculos' => $veiculosAtualizados,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function inicializarCsv(string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        throw new RuntimeException("Diretorio do CSV nao existe: {$dir}");
    }

    $fh = fopen($path, 'w');
    if (!$fh) {
        throw new RuntimeException("Nao foi possivel criar CSV: {$path}");
    }

    fputcsv($fh, csvHeaders());
    fclose($fh);
}

function gravarCsv(string $path, array $linhas, bool $append = false): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        throw new RuntimeException("Diretorio do CSV nao existe: {$dir}");
    }

    $fh = fopen($path, $append ? 'a' : 'w');
    if (!$fh) {
        throw new RuntimeException("Nao foi possivel criar CSV: {$path}");
    }

    $headers = csvHeaders();
    if (!$append) {
        fputcsv($fh, $headers);
    }

    foreach ($linhas as $row) {
        fputcsv($fh, array_map(static fn($key) => $row[$key] ?? '', $headers));
    }

    fclose($fh);
}

function csvHeaders(): array
{
    return [
        'id',
        'chave',
        'codigo',
        'total_fatura_atual',
        'total_pagar_atual',
        'total_fatura_calculado',
        'total_pagar_calculado',
        'total_receitas',
        'total_pago',
        'km_excedente_atual',
        'combustivel_usado_atual',
        'combustivel_valor_atual',
        'total_veiculos',
        'total_taxas',
        'total_condutores',
        'total_km_excedente',
        'total_combustivel',
        'km_excedente',
        'valor_km_excedente',
        'combustivel_usado',
        'valor_combustivel_unitario',
        'id_locacao_veiculo',
        'erro',
    ];
}

function contarErros(array $linhas): int
{
    return count(array_filter($linhas, static fn(array $row): bool => !empty($row['erro'])));
}

function formatarBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . 'MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    }

    return $bytes . 'B';
}

function criarTabelaAuditoria(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS locacoes_totais_normalizacao_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chave VARCHAR(45) NOT NULL,
            id_locacao INT UNSIGNED NOT NULL,
            codigo VARCHAR(15) NULL,
            total_fatura_anterior DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_pagar_anterior DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_fatura_novo DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_pagar_novo DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_receitas DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_pago DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            dry_run_csv VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ltn_chave_locacao (chave, id_locacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function conectarPdo(array $config): PDO
{
    $driver = $config['DB_DRIVER'] ?? 'mysql';
    $host = $config['DB_HOST'] ?? '127.0.0.1';
    $port = $config['DB_PORT'] ?? '3306';
    $database = $config['DB_DATABASE'] ?? null;
    $username = $config['DB_USERNAME'] ?? null;
    $password = $config['DB_PASSWORD'] ?? '';
    $charset = $config['DB_CHARSET'] ?? 'utf8mb4';

    if (!$database || !$username) {
        throw new RuntimeException('Credenciais de banco incompletas.');
    }

    return new PDO(
        "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function aplicarConfigDatabase(array $config): void
{
    $reflection = new ReflectionClass(Database::class);
    $property = $reflection->getProperty('config');
    $property->setAccessible(true);
    $property->setValue(null, $config);
}

function parseDbConfigFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Arquivo de credenciais nao encontrado: {$path}");
    }

    $config = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim($value, "\"' ");
    }

    return $config;
}

function parseEnvFile(string $path): array
{
    return parseDbConfigFile($path);
}

function resolveEnvPath(string $env): string
{
    $path = __DIR__ . '/../.env.' . $env;
    if (is_file($path)) {
        return $path;
    }

    $fallback = __DIR__ . '/../.env.development';
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException('Arquivo .env nao encontrado.');
}
