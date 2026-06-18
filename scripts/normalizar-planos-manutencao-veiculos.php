#!/usr/bin/env php
<?php

/**
 * Normaliza planos de manutencao vinculados aos veiculos e remove OS
 * preventivas automaticas criadas indevidamente no lote de 2026-06-15 a
 * 2026-06-17.
 *
 * Uso:
 *   php scripts/normalizar-planos-manutencao-veiculos.php --db-config=temp-bd.txt
 *   php scripts/normalizar-planos-manutencao-veiculos.php --db-config=temp-bd.txt --chave=1111111111111
 *   php scripts/normalizar-planos-manutencao-veiculos.php --db-config=temp-bd.txt --apply --confirm=CORRIGIR_MANUTENCAO
 *   php scripts/normalizar-planos-manutencao-veiculos.php --cleanup-historico-os --apply --confirm=CORRIGIR_MANUTENCAO
 *
 * O modo padrao eh DRY-RUN. Em --apply:
 * - recalcula veiculos.plano_manutencao_array como odometro + intervalo do plano;
 * - remove somente OS automaticas abertas, sem financeiro e sem item com estoque/financeiro.
 *
 * Por padrao, a remocao de OS fica limitada ao lote de 2026-06-15 a 2026-06-17.
 * Use --cleanup-historico-os para remover tambem historico automatico aberto
 * com os motivos "gerada pelo sistema" e "programada pelo sistema".
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
    'confirm::',
    'chave::',
    'limit::',
    'cleanup-historico-os',
]);

$env = $options['env'] ?? 'development';
putenv('APP_ENV=' . $env);
$_ENV['APP_ENV'] = $env;

$apply = array_key_exists('apply', $options);
$confirm = $options['confirm'] ?? '';
$dbConfigPath = $options['db-config'] ?? null;
$chaveFiltro = $options['chave'] ?? null;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;
$cleanupHistoricoOs = array_key_exists('cleanup-historico-os', $options);
$runId = date('YmdHis') . '-' . bin2hex(random_bytes(4));

if ($apply && $confirm !== 'CORRIGIR_MANUTENCAO') {
    fwrite(STDERR, "Para aplicar, informe --confirm=CORRIGIR_MANUTENCAO.\n");
    exit(1);
}

echo "Normalizacao de planos de manutencao dos veiculos\n";
echo "Ambiente: {$env}\n";
echo "Credenciais DB: " . ($dbConfigPath ?: '.env.' . $env) . "\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Filtro chave: " . ($chaveFiltro ?: 'todos') . "\n";
echo "Limite veiculos: " . ($limit ?: 'sem limite') . "\n";
echo "Limpeza OS: " . ($cleanupHistoricoOs ? 'historico automatico aberto' : 'lote 2026-06-15 a 2026-06-17') . "\n";
echo "Run ID: {$runId}\n\n";

$pdo = conectarPdo($dbConfigPath);

$veiculos = buscarVeiculosComPlanoAtivo($pdo, $chaveFiltro, $limit);
$normalizacoes = montarNormalizacoes($veiculos);
$osCandidatas = buscarOsCandidatasRemocao($pdo, $chaveFiltro, $cleanupHistoricoOs);
$osPreservadas = contarOsPreservadas($pdo, $chaveFiltro, $cleanupHistoricoOs);
$osPorDia = agruparOsPorDia($osCandidatas);

echo "Veiculos com plano ativo analisados: " . count($veiculos) . "\n";
echo "Veiculos que precisam atualizar plano_manutencao_array: " . count($normalizacoes) . "\n";
echo "OS automaticas candidatas a remocao: " . count($osCandidatas) . "\n";
echo "OS automaticas preservadas no escopo: {$osPreservadas}\n\n";

echo "OS candidatas por dia:\n";
if (empty($osPorDia)) {
    echo "  nenhuma\n";
} else {
    foreach ($osPorDia as $dia => $qtd) {
        echo "  {$dia}: {$qtd}\n";
    }
}

echo "\nAmostra de veiculos a atualizar:\n";
if (empty($normalizacoes)) {
    echo "  nenhum\n";
} else {
    foreach (array_slice($normalizacoes, 0, 10) as $row) {
        echo sprintf(
            "  veiculo=%d placa=%s chave=%s odometro=%s plano=%d\n",
            (int) $row['id'],
            $row['placa'] ?: '-',
            $row['chave'],
            $row['odometro_original'] ?: '0',
            (int) $row['id_plano_manutencao']
        );
        echo "    antes: " . abreviar($row['plano_anterior'] ?: 'NULL', 180) . "\n";
        echo "    depois: " . abreviar($row['plano_novo'], 180) . "\n";
    }

    if (count($normalizacoes) > 10) {
        echo "  ... " . (count($normalizacoes) - 10) . " veiculo(s) adicionais omitidos da amostra.\n";
    }
}

echo "\nAmostra de OS candidatas a remocao:\n";
if (empty($osCandidatas)) {
    echo "  nenhuma\n";
} else {
    foreach (array_slice($osCandidatas, 0, 15) as $row) {
        echo sprintf(
            "  manutencao=%d os=%s veiculo=%s placa=%s criado=%s status=%s\n",
            (int) $row['id'],
            $row['os'],
            $row['id_veiculo'] ?? '-',
            $row['placa'] ?: '-',
            $row['created_at'],
            $row['status']
        );
    }

    if (count($osCandidatas) > 15) {
        echo "  ... " . (count($osCandidatas) - 15) . " OS adicionais omitidas da amostra.\n";
    }
}

if (!$apply) {
    echo "\nDRY-RUN concluido. Nenhum dado foi alterado.\n";
    exit(0);
}

criarTabelasAuditoria($pdo);

$pdo->beginTransaction();
try {
    registrarAuditoriaVeiculos($pdo, $runId, $normalizacoes);
    atualizarVeiculos($pdo, $normalizacoes);

    registrarAuditoriaOs($pdo, $runId, $osCandidatas);
    removerOsCandidatas($pdo, array_column($osCandidatas, 'id'), $cleanupHistoricoOs);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo "\nAplicado com sucesso.\n";
echo "Veiculos atualizados: " . count($normalizacoes) . "\n";
echo "OS removidas: " . count($osCandidatas) . "\n";
echo "Auditoria de veiculos: manutencao_planos_normalizacao_audit\n";
echo "Auditoria de OS: manutencao_os_normalizacao_audit\n";

function buscarVeiculosComPlanoAtivo(PDO $pdo, ?string $chave, ?int $limit): array
{
    $where = ["p.status = 'A'"];
    $params = [];

    if ($chave !== null && $chave !== '') {
        $where[] = 'v.chave = :chave';
        $params[':chave'] = $chave;
    }

    $sql = "
        SELECT
            v.id,
            v.chave,
            v.placa,
            v.odometro,
            v.id_plano_manutencao,
            v.plano_manutencao_array,
            p.array AS plano_intervalos
        FROM veiculos v
        INNER JOIN manutencoes_plano p
            ON p.id = v.id_plano_manutencao
           AND p.chave = v.chave
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.chave, v.id
    ";

    if ($limit !== null) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function montarNormalizacoes(array $veiculos): array
{
    $normalizacoes = [];

    foreach ($veiculos as $veiculo) {
        $intervalos = json_decode((string) $veiculo['plano_intervalos'], true);
        if (!is_array($intervalos)) {
            continue;
        }

        $odometro = parseKm($veiculo['odometro'] ?? 0);
        $novoPlano = [];

        foreach ($intervalos as $item => $valorIntervalo) {
            $intervalo = parseKm($valorIntervalo);
            $novoPlano[$item] = $intervalo > 0
                ? formatKm($odometro + $intervalo)
                : '0';
        }

        $jsonNovo = json_encode($novoPlano, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonAnteriorNormalizado = normalizarJsonPlano($veiculo['plano_manutencao_array'] ?? null);

        if ($jsonAnteriorNormalizado === $jsonNovo) {
            continue;
        }

        $normalizacoes[] = [
            'id' => (int) $veiculo['id'],
            'chave' => $veiculo['chave'],
            'placa' => $veiculo['placa'],
            'odometro_original' => (string) ($veiculo['odometro'] ?? ''),
            'odometro_normalizado' => $odometro,
            'id_plano_manutencao' => (int) $veiculo['id_plano_manutencao'],
            'plano_anterior' => $veiculo['plano_manutencao_array'],
            'plano_novo' => $jsonNovo,
        ];
    }

    return $normalizacoes;
}

function buscarOsCandidatasRemocao(PDO $pdo, ?string $chave, bool $cleanupHistoricoOs): array
{
    $where = [
        "m.os LIKE 'MA%'",
        condicaoMotivoOsAutomatica($cleanupHistoricoOs),
        "m.status = 'C'",
        'm.id_financeiro_principal IS NULL',
        'NOT EXISTS (
            SELECT 1
            FROM manutencoes_itens mi
            WHERE mi.id_manutencao = m.id
              AND (mi.id_financeiro IS NOT NULL OR mi.id_estoque IS NOT NULL)
        )',
    ];
    $params = [];

    if (!$cleanupHistoricoOs) {
        $where[] = "m.created_at >= '2026-06-15'";
        $where[] = "m.created_at < '2026-06-18'";
    }

    if ($chave !== null && $chave !== '') {
        $where[] = 'm.chave = :chave';
        $params[':chave'] = $chave;
    }

    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.chave,
            m.os,
            m.id_veiculo,
            v.placa,
            m.status,
            m.id_financeiro_principal,
            m.created_at,
            m.odo_enviado,
            m.array_servicos
        FROM manutencoes m
        LEFT JOIN veiculos v ON v.id = m.id_veiculo AND v.chave = m.chave
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.created_at ASC, m.id ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contarOsPreservadas(PDO $pdo, ?string $chave, bool $cleanupHistoricoOs): int
{
    $where = [
        "m.os LIKE 'MA%'",
        condicaoMotivoOsAutomatica($cleanupHistoricoOs),
        "(
            m.status <> 'C'
            OR m.id_financeiro_principal IS NOT NULL
            OR EXISTS (
                SELECT 1
                FROM manutencoes_itens mi
                WHERE mi.id_manutencao = m.id
                  AND (mi.id_financeiro IS NOT NULL OR mi.id_estoque IS NOT NULL)
            )
        )",
    ];
    $params = [];

    if (!$cleanupHistoricoOs) {
        $where[] = "m.created_at >= '2026-06-15'";
        $where[] = "m.created_at < '2026-06-18'";
    }

    if ($chave !== null && $chave !== '') {
        $where[] = 'm.chave = :chave';
        $params[':chave'] = $chave;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM manutencoes m WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function agruparOsPorDia(array $osCandidatas): array
{
    $dias = [];
    foreach ($osCandidatas as $row) {
        $dia = substr((string) $row['created_at'], 0, 10);
        $dias[$dia] = ($dias[$dia] ?? 0) + 1;
    }

    ksort($dias);
    return $dias;
}

function criarTabelasAuditoria(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS manutencao_planos_normalizacao_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_id VARCHAR(40) NOT NULL,
            id_veiculo INT UNSIGNED NOT NULL,
            chave VARCHAR(45) NOT NULL,
            placa VARCHAR(10) NULL,
            odometro_original VARCHAR(20) NULL,
            odometro_normalizado INT UNSIGNED NOT NULL DEFAULT 0,
            id_plano_manutencao INT UNSIGNED NOT NULL,
            plano_anterior MEDIUMTEXT NULL,
            plano_novo MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_manut_plan_norm_run (run_id),
            KEY idx_manut_plan_norm_chave_veiculo (chave, id_veiculo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS manutencao_os_normalizacao_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_id VARCHAR(40) NOT NULL,
            id_manutencao INT UNSIGNED NOT NULL,
            chave VARCHAR(45) NOT NULL,
            os VARCHAR(20) NOT NULL,
            id_veiculo INT UNSIGNED NULL,
            placa VARCHAR(10) NULL,
            status VARCHAR(1) NOT NULL,
            id_financeiro_principal INT UNSIGNED NULL,
            manutencao_created_at DATETIME NOT NULL,
            odo_enviado VARCHAR(10) NULL,
            array_servicos MEDIUMTEXT NULL,
            acao VARCHAR(30) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_manut_os_norm_manutencao (id_manutencao),
            KEY idx_manut_os_norm_run (run_id),
            KEY idx_manut_os_norm_chave_veiculo (chave, id_veiculo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function registrarAuditoriaVeiculos(PDO $pdo, string $runId, array $normalizacoes): void
{
    $stmt = $pdo->prepare("
        INSERT INTO manutencao_planos_normalizacao_audit (
            run_id,
            id_veiculo,
            chave,
            placa,
            odometro_original,
            odometro_normalizado,
            id_plano_manutencao,
            plano_anterior,
            plano_novo
        ) VALUES (
            :run_id,
            :id_veiculo,
            :chave,
            :placa,
            :odometro_original,
            :odometro_normalizado,
            :id_plano_manutencao,
            :plano_anterior,
            :plano_novo
        )
    ");

    foreach ($normalizacoes as $row) {
        $stmt->execute([
            ':run_id' => $runId,
            ':id_veiculo' => $row['id'],
            ':chave' => $row['chave'],
            ':placa' => $row['placa'],
            ':odometro_original' => $row['odometro_original'],
            ':odometro_normalizado' => $row['odometro_normalizado'],
            ':id_plano_manutencao' => $row['id_plano_manutencao'],
            ':plano_anterior' => $row['plano_anterior'],
            ':plano_novo' => $row['plano_novo'],
        ]);
    }
}

function atualizarVeiculos(PDO $pdo, array $normalizacoes): void
{
    $stmt = $pdo->prepare("
        UPDATE veiculos
        SET plano_manutencao_array = :plano_novo
        WHERE id = :id
          AND chave = :chave
    ");

    foreach ($normalizacoes as $row) {
        $stmt->execute([
            ':plano_novo' => $row['plano_novo'],
            ':id' => $row['id'],
            ':chave' => $row['chave'],
        ]);
    }
}

function registrarAuditoriaOs(PDO $pdo, string $runId, array $osCandidatas): void
{
    $stmt = $pdo->prepare("
        INSERT INTO manutencao_os_normalizacao_audit (
            run_id,
            id_manutencao,
            chave,
            os,
            id_veiculo,
            placa,
            status,
            id_financeiro_principal,
            manutencao_created_at,
            odo_enviado,
            array_servicos,
            acao
        ) VALUES (
            :run_id,
            :id_manutencao,
            :chave,
            :os,
            :id_veiculo,
            :placa,
            :status,
            :id_financeiro_principal,
            :manutencao_created_at,
            :odo_enviado,
            :array_servicos,
            'delete_wrong_auto_preventive'
        )
        ON DUPLICATE KEY UPDATE
            run_id = VALUES(run_id),
            acao = VALUES(acao)
    ");

    foreach ($osCandidatas as $row) {
        $stmt->execute([
            ':run_id' => $runId,
            ':id_manutencao' => (int) $row['id'],
            ':chave' => $row['chave'],
            ':os' => $row['os'],
            ':id_veiculo' => $row['id_veiculo'] !== null ? (int) $row['id_veiculo'] : null,
            ':placa' => $row['placa'],
            ':status' => $row['status'],
            ':id_financeiro_principal' => $row['id_financeiro_principal'] !== null ? (int) $row['id_financeiro_principal'] : null,
            ':manutencao_created_at' => $row['created_at'],
            ':odo_enviado' => $row['odo_enviado'],
            ':array_servicos' => $row['array_servicos'],
        ]);
    }
}

function removerOsCandidatas(PDO $pdo, array $ids, bool $cleanupHistoricoOs): void
{
    if (empty($ids)) {
        return;
    }

    $where = [
        "m.id IN (%s)",
        "m.os LIKE 'MA%'",
        condicaoMotivoOsAutomatica($cleanupHistoricoOs),
        "m.status = 'C'",
        "m.id_financeiro_principal IS NULL",
        "NOT EXISTS (
            SELECT 1
            FROM manutencoes_itens mi
            WHERE mi.id_manutencao = m.id
              AND (mi.id_financeiro IS NOT NULL OR mi.id_estoque IS NOT NULL)
        )",
    ];

    if (!$cleanupHistoricoOs) {
        $where[] = "m.created_at >= '2026-06-15'";
        $where[] = "m.created_at < '2026-06-18'";
    }

    foreach (array_chunk(array_map('intval', $ids), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $where[0] = sprintf("m.id IN (%s)", $placeholders);
        $stmt = $pdo->prepare("
            DELETE m
            FROM manutencoes m
            WHERE " . implode("\n              AND ", $where) . "
        ");
        $stmt->execute($chunk);
    }
}

function condicaoMotivoOsAutomatica(bool $cleanupHistoricoOs): string
{
    if ($cleanupHistoricoOs) {
        return "m.motivo IN (
            'Manutenção preventiva gerada pelo sistema.',
            'Manutenção preventiva programada pelo sistema.'
        )";
    }

    return "m.motivo = 'Manutenção preventiva gerada pelo sistema.'";
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

function normalizarJsonPlano(?string $json): ?string
{
    if ($json === null || trim($json) === '') {
        return null;
    }

    $dados = json_decode($json, true);
    if (!is_array($dados)) {
        return $json;
    }

    $normalizado = [];
    foreach ($dados as $item => $valor) {
        $km = parseKm($valor);
        $normalizado[$item] = $km > 0 ? formatKm($km) : '0';
    }

    return json_encode($normalizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function parseKm(mixed $valor): int
{
    if ($valor === null) {
        return 0;
    }

    if (is_int($valor)) {
        return max(0, $valor);
    }

    if (is_float($valor)) {
        return max(0, (int) round($valor));
    }

    $digits = preg_replace('/\D+/', '', (string) $valor);
    if ($digits === '') {
        return 0;
    }

    return max(0, (int) $digits);
}

function formatKm(int $valor): string
{
    return number_format($valor, 0, '', '.');
}

function abreviar(string $texto, int $limite): string
{
    if (mb_strlen($texto) <= $limite) {
        return $texto;
    }

    return mb_substr($texto, 0, $limite - 3) . '...';
}
