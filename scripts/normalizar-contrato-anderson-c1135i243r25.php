#!/usr/bin/env php
<?php

/**
 * Simula/normaliza as parcelas faltantes do contrato C1135I243R25.
 *
 * Uso:
 *   php scripts/normalizar-contrato-anderson-c1135i243r25.php
 *   php scripts/normalizar-contrato-anderson-c1135i243r25.php --env=production
 *   php scripts/normalizar-contrato-anderson-c1135i243r25.php --db-config=temp-bd.txt
 *   php scripts/normalizar-contrato-anderson-c1135i243r25.php --apply --confirm=GERAR_C1135I243R25
 *
 * O modo padrao eh DRY-RUN. Nenhum dado e alterado sem --apply + --confirm.
 */

require __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Contrato;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', ['env::', 'db-config::', 'apply', 'confirm::']);
$env = $options['env'] ?? 'development';
$dbConfigPath = $options['db-config'] ?? null;
$apply = array_key_exists('apply', $options);
$confirm = $options['confirm'] ?? '';

if ($apply && $confirm !== 'GERAR_C1135I243R25') {
    fwrite(STDERR, "Para aplicar, informe --confirm=GERAR_C1135I243R25.\n");
    exit(1);
}

if ($apply && $dbConfigPath) {
    fwrite(STDERR, "Por seguranca, --db-config e aceito apenas em DRY-RUN. Para aplicar, execute no ambiente do servidor.\n");
    exit(1);
}

putenv('APP_ENV=' . $env);
$_ENV['APP_ENV'] = $env;

if ($dbConfigPath) {
    aplicarConfigDatabase(carregarDbConfig($dbConfigPath));
}

$codigo = 'C1135I243R25';
$periodoInicio = '2026-06-17';
$periodoFim = '2026-09-09';

echo "Normalizacao do contrato {$codigo}\n";
echo "Ambiente: {$env}\n";
echo "Credenciais DB: " . ($dbConfigPath ?: '.env.' . $env) . "\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n";
echo "Periodo: {$periodoInicio} ate {$periodoFim}\n\n";

$pdo = conectarPdo();
$contratoBase = buscarContratoBase($pdo, $codigo);
if (!$contratoBase) {
    fwrite(STDERR, "Contrato {$codigo} nao encontrado.\n");
    exit(1);
}

$_SESSION['chave'] = $contratoBase['chave'];
$_SESSION['user_id'] = 0;
$_SESSION['user_name'] = 'Sistema';

$contratoModel = new Contrato();
$contrato = $contratoModel->buscarPorId((int) $contratoBase['id']);
if (!$contrato) {
    fwrite(STDERR, "Contrato {$codigo} nao encontrado no contexto do tenant.\n");
    exit(1);
}

$preview = $contratoModel->gerarPreviewParcelas((int) $contrato['id'], [
    'id_forma_pagamento' => (int) ($contrato['id_forma_pagamento'] ?? 0),
    'id_comando_parcela' => (int) ($contrato['id_comando_parcela'] ?? 0),
    'id_conta' => (int) ($contrato['id_conta'] ?? 0),
    'primeiro_vencimento' => $periodoInicio,
    'data_fim' => $periodoFim,
    'valor_desconto' => 0,
]);

$existentes = buscarParcelasExistentes($pdo, (int) $contrato['id']);
$faltantes = [];

echo "Contrato ID: {$contrato['id']}\n";
echo "Chave: {$contrato['chave']}\n";
echo "Comando: " . ($contrato['comando_parcela_comando'] ?? '-') . "\n";
echo "Forma: " . ($contrato['forma_pagamento_descricao'] ?? '-') . "\n";
echo "Conta: " . ($contrato['conta_descricao'] ?? '-') . "\n";
echo "Parcelas esperadas: " . count($preview['parcelas']) . "\n\n";

foreach ($preview['parcelas'] as $parcela) {
    $key = chaveParcela($parcela['data_venci']);
    $existente = $existentes[$key] ?? null;

    if ($existente) {
        echo sprintf(
            "OK      venc=%s esperado=%0.2f atual=%0.2f financeiro_id=%d pago=%s\n",
            $parcela['data_venci'],
            (float) $parcela['valor_total'],
            (float) $existente['valor'],
            (int) $existente['id'],
            $existente['pago']
        );
        continue;
    }

    $faltantes[] = $parcela;
    echo sprintf(
        "FALTA   venc=%s valor=%0.2f parcela=%d/%d\n",
        $parcela['data_venci'],
        (float) $parcela['valor_total'],
        (int) $parcela['parcela'],
        (int) $parcela['total_parcelas']
    );
}

echo "\nResumo: " . count($faltantes) . " parcela(s) faltante(s).\n";

if (!$apply) {
    echo "DRY-RUN concluido. Nenhum dado foi alterado.\n";
    exit(0);
}

if (empty($faltantes)) {
    echo "Nada a criar.\n";
    exit(0);
}

$resultado = $contratoModel->salvarParcelasContratoComResultado(
    (int) $contrato['id'],
    $faltantes,
    (string) $contrato['chave']
);

echo "Aplicado. Criadas: " . count($resultado['ids_criados'])
    . ", ja existentes: " . count($resultado['ids_existentes']) . ".\n";
echo "IDs criados: " . implode(', ', $resultado['ids_criados']) . "\n";

function conectarPdo(): PDO
{
    $host = Database::env('DB_HOST');
    $port = (int) Database::env('DB_PORT', '3306');
    $database = Database::env('DB_DATABASE');
    $charset = Database::env('DB_CHARSET', 'utf8mb4');
    $user = Database::env('DB_USERNAME');
    $password = Database::env('DB_PASSWORD');

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function carregarDbConfig(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Arquivo de configuracao nao encontrado: {$path}");
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

function aplicarConfigDatabase(array $config): void
{
    $ref = new ReflectionClass(Database::class);
    $configProp = $ref->getProperty('config');
    $configProp->setAccessible(true);
    $configProp->setValue(null, $config);

    $connectionProp = $ref->getProperty('connection');
    $connectionProp->setAccessible(true);
    $connectionProp->setValue(null, null);
}

function buscarContratoBase(PDO $pdo, string $codigo): ?array
{
    $stmt = $pdo->prepare('SELECT id, chave FROM contratos WHERE codigo = :codigo LIMIT 1');
    $stmt->execute(['codigo' => $codigo]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function buscarParcelasExistentes(PDO $pdo, int $contratoId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, data_venci, pago, COALESCE(valor_total, valor_subtotal, 0) AS valor
         FROM financeiro
         WHERE id_contrato = :id_contrato"
    );
    $stmt->execute(['id_contrato' => $contratoId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[chaveParcela($row['data_venci'])] = $row;
    }
    return $rows;
}

function chaveParcela(string $dataVenci): string
{
    return $dataVenci;
}
