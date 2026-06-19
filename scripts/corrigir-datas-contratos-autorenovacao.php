#!/usr/bin/env php
<?php

/**
 * Restaura data_ini/data_fim de contratos alterados indevidamente pela
 * autorenovacao, usando contratos_clone como referencia.
 *
 * Uso:
 *   php scripts/corrigir-datas-contratos-autorenovacao.php --db-config=temp-bd.txt
 *   php scripts/corrigir-datas-contratos-autorenovacao.php --db-config=temp-bd.txt --chave=1111111111111
 *   php scripts/corrigir-datas-contratos-autorenovacao.php --db-config=temp-bd.txt --contrato=123
 *   php scripts/corrigir-datas-contratos-autorenovacao.php --db-config=temp-bd.txt --apply --confirm=CORRIGIR_DATAS_CONTRATOS
 *
 * O modo padrao eh DRY-RUN.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', [
    'db-config:',
    'apply',
    'confirm::',
    'chave::',
    'contrato::',
]);

$dbConfigPath = $options['db-config'] ?? null;
$apply = array_key_exists('apply', $options);
$confirm = $options['confirm'] ?? '';
$chaveFiltro = $options['chave'] ?? null;
$contratoFiltro = isset($options['contrato']) ? (int) $options['contrato'] : null;

if (!$dbConfigPath) {
    fwrite(STDERR, "Informe --db-config=temp-bd.txt.\n");
    exit(1);
}

if ($apply && $confirm !== 'CORRIGIR_DATAS_CONTRATOS') {
    fwrite(STDERR, "Para aplicar, informe --confirm=CORRIGIR_DATAS_CONTRATOS.\n");
    exit(1);
}

$config = parseDbConfigFile($dbConfigPath);
$pdo = conectarPdo($config);
$linhas = buscarDivergenciasAutorenovacao($pdo, $chaveFiltro, $contratoFiltro);
$avaliadas = avaliarCandidatos($linhas);

echo "Correcao de datas de contratos com autorenovacao\n";
echo "Credenciais DB: {$dbConfigPath}\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Filtro chave: " . ($chaveFiltro ?: 'todos') . "\n";
echo "Filtro contrato: " . ($contratoFiltro ?: 'todos') . "\n\n";

echo "Divergencias auto encontradas: " . count($linhas) . "\n";
echo "Candidatos seguros: " . count($avaliadas['candidatos']) . "\n";
echo "Ignorados: " . count($avaliadas['ignorados']) . "\n";
echo "  - com log de renovacao: {$avaliadas['com_log']}\n";
echo "  - por padrao de ciclo: {$avaliadas['por_padrao']}\n\n";

if (!empty($avaliadas['ignorados'])) {
    echo "Ignorados para revisao manual:\n";
    foreach (array_slice($avaliadas['ignorados'], 0, 20) as $item) {
        echo "- id={$item['id']} codigo={$item['codigo']} motivo={$item['motivo']}\n";
    }
    if (count($avaliadas['ignorados']) > 20) {
        echo "- ... +" . (count($avaliadas['ignorados']) - 20) . " ignorados\n";
    }
    echo "\n";
}

if (empty($avaliadas['candidatos'])) {
    echo "Nada a corrigir.\n";
    exit(0);
}

echo "Amostra dos candidatos:\n";
foreach (array_slice($avaliadas['candidatos'], 0, 20) as $item) {
    echo "- id={$item['id']} codigo={$item['codigo']} chave={$item['chave']}"
        . " data_ini {$item['data_ini']} -> {$item['clone_data_ini']}"
        . " data_fim {$item['data_fim']} -> {$item['clone_data_fim']}\n";
}
if (count($avaliadas['candidatos']) > 20) {
    echo "- ... +" . (count($avaliadas['candidatos']) - 20) . " candidatos\n";
}
echo "\n";

if (!$apply) {
    echo "DRY-RUN concluido. Nenhum dado foi alterado.\n";
    exit(0);
}

$resultado = aplicarCorrecoes($pdo, $avaliadas['candidatos']);
echo "Aplicacao concluida.\n";
echo "Atualizados: {$resultado['atualizados']}\n";
echo "Nao atualizados por concorrencia/condicao: {$resultado['nao_atualizados']}\n";

function parseDbConfigFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Arquivo de config nao encontrado: {$path}");
    }

    $config = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, 'OBS')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = array_map('trim', explode('=', $line, 2));
        } elseif (str_contains($line, ':')) {
            [$key, $value] = array_map('trim', explode(':', $line, 2));
        } else {
            continue;
        }

        $config[$key] = $value;
    }

    foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $required) {
        if (!array_key_exists($required, $config)) {
            throw new RuntimeException("Config ausente: {$required}");
        }
    }

    return $config;
}

function conectarPdo(array $config): PDO
{
    $host = $config['DB_HOST'];
    $port = (int) ($config['DB_PORT'] ?? 3306);
    $database = $config['DB_DATABASE'];
    $charset = $config['DB_CHARSET'] ?? 'utf8mb4';
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

    return new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function buscarDivergenciasAutorenovacao(PDO $pdo, ?string $chaveFiltro, ?int $contratoFiltro): array
{
    $where = [
        "c.auto_renovacao = 'auto'",
        "(c.data_ini <> cc.dataIni OR c.data_fim <> cc.dataFim)",
    ];
    $params = [];

    if ($chaveFiltro) {
        $where[] = 'c.chave = :chave';
        $params[':chave'] = $chaveFiltro;
    }

    if ($contratoFiltro) {
        $where[] = 'c.id = :contrato';
        $params[':contrato'] = $contratoFiltro;
    }

    $sql = "
        SELECT
            c.id,
            c.chave,
            c.codigo,
            c.status,
            c.contagem,
            c.dias,
            c.data_ini,
            c.data_fim,
            c.data_renovacao,
            c.updated_at,
            cc.dataIni AS clone_data_ini,
            cc.dataFim AS clone_data_fim,
            TIMESTAMPDIFF(SECOND, cc.dataIni, cc.dataFim) AS clone_periodo_seg,
            TIMESTAMPDIFF(SECOND, cc.dataIni, c.data_ini) AS avanco_ini_seg,
            TIMESTAMPDIFF(SECOND, cc.dataFim, c.data_fim) AS avanco_fim_seg,
            EXISTS (
                SELECT 1
                FROM logs l
                WHERE l.chave = c.chave
                  AND l.mensagem LIKE CONCAT('%[', c.codigo, ']%')
                  AND l.mensagem LIKE '%renova%autom%'
            ) AS tem_log_renovacao
        FROM contratos c
        INNER JOIN contratos_clone cc ON cc.id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.chave, c.id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function avaliarCandidatos(array $linhas): array
{
    $candidatos = [];
    $ignorados = [];
    $comLog = 0;
    $porPadrao = 0;

    foreach ($linhas as $linha) {
        $temLog = (int) ($linha['tem_log_renovacao'] ?? 0) === 1;
        $padrao = batePadraoDeCiclo($linha);
        $avancado = strtotime($linha['data_ini']) >= strtotime($linha['clone_data_ini'])
            && strtotime($linha['data_fim']) >= strtotime($linha['clone_data_fim']);

        if (!$avancado) {
            $linha['motivo'] = 'datas atuais nao estao avancadas em relacao ao clone';
            $ignorados[] = $linha;
            continue;
        }

        if (!$temLog && !$padrao) {
            $linha['motivo'] = 'sem log de renovacao e sem padrao inequivoco de ciclo';
            $ignorados[] = $linha;
            continue;
        }

        $linha['criterio'] = $temLog ? 'log' : 'padrao';
        $candidatos[] = $linha;
        $comLog += $temLog ? 1 : 0;
        $porPadrao += (!$temLog && $padrao) ? 1 : 0;
    }

    return [
        'candidatos' => $candidatos,
        'ignorados' => $ignorados,
        'com_log' => $comLog,
        'por_padrao' => $porPadrao,
    ];
}

function batePadraoDeCiclo(array $linha): bool
{
    $periodo = (int) ($linha['clone_periodo_seg'] ?? 0);
    $avancoIni = (int) ($linha['avanco_ini_seg'] ?? 0);
    $avancoFim = (int) ($linha['avanco_fim_seg'] ?? 0);

    if ($periodo <= 0 || $avancoIni < 0 || $avancoFim < 0) {
        return false;
    }

    return $avancoIni === $avancoFim && $avancoIni % $periodo === 0;
}

function aplicarCorrecoes(PDO $pdo, array $candidatos): array
{
    $stmt = $pdo->prepare("
        UPDATE contratos
        SET data_ini = :clone_data_ini,
            data_fim = :clone_data_fim,
            updated_at = NOW()
        WHERE id = :id
          AND chave = :chave
          AND data_ini = :data_ini_atual
          AND data_fim = :data_fim_atual
    ");

    $atualizados = 0;
    $naoAtualizados = 0;

    $pdo->beginTransaction();
    try {
        foreach ($candidatos as $linha) {
            $stmt->execute([
                ':clone_data_ini' => $linha['clone_data_ini'],
                ':clone_data_fim' => $linha['clone_data_fim'],
                ':id' => (int) $linha['id'],
                ':chave' => $linha['chave'],
                ':data_ini_atual' => $linha['data_ini'],
                ':data_fim_atual' => $linha['data_fim'],
            ]);

            if ($stmt->rowCount() === 1) {
                $atualizados++;
            } else {
                $naoAtualizados++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'atualizados' => $atualizados,
        'nao_atualizados' => $naoAtualizados,
    ];
}
