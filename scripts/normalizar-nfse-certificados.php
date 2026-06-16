#!/usr/bin/env php
<?php

/**
 * Normaliza senhas de certificados NFS-e importadas do sistema legado.
 *
 * Uso:
 *   php scripts/normalizar-nfse-certificados.php --env=production
 *   php scripts/normalizar-nfse-certificados.php --env=production --apply
 *   php scripts/normalizar-nfse-certificados.php --env=production --db-config=temp-bd.txt --cert-dir=/tmp/nfse-certs-check --apply
 */

use App\Services\NFSe\NFSeCertificado;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', ['env::', 'db-config::', 'cert-dir::', 'apply']);
$env = $options['env'] ?? 'development';
putenv('APP_ENV=' . $env);
$_ENV['APP_ENV'] = $env;

$apply = array_key_exists('apply', $options);
$certDir = $options['cert-dir'] ?? (__DIR__ . '/../storage/certificates');
$dbConfigPath = $options['db-config'] ?? null;

echo "Normalizacao de certificados NFS-e\n";
echo "Ambiente: {$env}\n";
echo "Credenciais DB: " . ($dbConfigPath ?: '.env.' . $env) . "\n";
echo "Diretorio certificados: {$certDir}\n";
echo "Modo: " . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n\n";

if (!is_dir($certDir)) {
    fwrite(STDERR, "Diretorio de certificados nao encontrado: {$certDir}\n");
    exit(1);
}

$pdo = conectarPdo($dbConfigPath);
$certificado = new NFSeCertificado($certDir);

$stmt = $pdo->query("
    SELECT id, chave, id_matriz_filial, certificado_arquivo, certificado_senha, certificado_validade
    FROM nfse_configuracoes
    WHERE certificado_arquivo IS NOT NULL
      AND certificado_arquivo <> ''
");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($configs);
$normalizados = 0;
$atuais = 0;
$invalidos = 0;
$vencidos = 0;
$validadeAtualizada = 0;

$update = $pdo->prepare("
    UPDATE nfse_configuracoes
    SET certificado_senha = :senha,
        certificado_validade = :validade
    WHERE id = :id
");

foreach ($configs as $config) {
    $id = (int) $config['id'];
    $arquivo = (string) ($config['certificado_arquivo'] ?? '');
    $senhaCriptografada = (string) ($config['certificado_senha'] ?? '');

    if ($arquivo === '' || $senhaCriptografada === '') {
        $invalidos++;
        echo "[SKIP] id={$id} filial={$config['id_matriz_filial']} sem arquivo ou senha.\n";
        continue;
    }

    $analise = $certificado->analisar((string) $config['chave'], $arquivo, $senhaCriptografada, true);
    $status = $analise['status'] ?? 'desconhecido';
    $formato = $analise['formato_senha'] ?? 'n/a';
    $validade = $analise['validade'] ?? null;
    $precisaAtualizarValidade = $validade !== null && $validade !== ($config['certificado_validade'] ?? null);

    if ($status === 'vencido') {
        $vencidos++;
    }

    if ($formato === 'legado' && !empty($analise['senha'])) {
        $normalizados++;
        $novaSenha = encrypt($analise['senha']);

        if ($apply) {
            $update->execute([
                ':senha' => $novaSenha,
                ':validade' => $validade,
                ':id' => $id,
            ]);
        }

        echo "[OK] id={$id} filial={$config['id_matriz_filial']} legado -> atual status={$status} validade={$validade}\n";
        continue;
    }

    if ($formato === 'atual') {
        $atuais++;
        if ($precisaAtualizarValidade) {
            $validadeAtualizada++;
            if ($apply) {
                $update->execute([
                    ':senha' => $senhaCriptografada,
                    ':validade' => $validade,
                    ':id' => $id,
                ]);
            }
        }

        echo "[OK] id={$id} filial={$config['id_matriz_filial']} atual status={$status} validade={$validade}\n";
        continue;
    }

    $invalidos++;
    echo "[ERRO] id={$id} filial={$config['id_matriz_filial']} status={$status} mensagem=\"{$analise['mensagem']}\"\n";
}

echo "\nResumo\n";
echo "Total: {$total}\n";
echo "Legados normalizados: {$normalizados}\n";
echo "Ja atuais: {$atuais}\n";
echo "Validades atualizadas em senha atual: {$validadeAtualizada}\n";
echo "Vencidos reais: {$vencidos}\n";
echo "Invalidos/pendentes: {$invalidos}\n";

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
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim(trim($value), '"\'');
    }

    return $config;
}
