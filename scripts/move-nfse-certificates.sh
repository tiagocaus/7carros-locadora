#!/usr/bin/env bash
#
# Padroniza certificados NFS-e em storage/certificates.
#
# Uso:
#   ./scripts/move-nfse-certificates.sh              # dry-run
#   ./scripts/move-nfse-certificates.sh --execute    # move/renomeia e atualiza o banco
#
# Este script deve ser executado depois da migration que popula
# nfse_configuracoes.

set -euo pipefail

EXECUTE=0
ENV_FILE="${ENV_FILE:-.env.production}"
APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${TARGET_DIR:-$APP_ROOT/storage/certificates}"
OLD_UPLOAD_DIR="${OLD_UPLOAD_DIR:-$APP_ROOT/storage/uploads/certificados}"
LEGACY_CERT_BASE="${LEGACY_CERT_BASE:-$APP_ROOT/storage/certificates}"

while (( $# )); do
  case "$1" in
    --execute)
      EXECUTE=1
      ;;
    --env)
      shift
      ENV_FILE="${1:-}"
      ;;
    -h|--help)
      sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "Flag desconhecida: $1" >&2
      exit 2
      ;;
  esac
  shift
done

cd "$APP_ROOT"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Arquivo de ambiente nao encontrado: $ENV_FILE" >&2
  exit 1
fi

EXECUTE="$EXECUTE" \
ENV_FILE="$ENV_FILE" \
TARGET_DIR="$TARGET_DIR" \
OLD_UPLOAD_DIR="$OLD_UPLOAD_DIR" \
LEGACY_CERT_BASE="$LEGACY_CERT_BASE" \
php <<'PHP'
<?php
$execute = getenv('EXECUTE') === '1';
$envFile = getenv('ENV_FILE') ?: '.env.production';
$targetDir = rtrim((string) getenv('TARGET_DIR'), '/');
$oldUploadDir = rtrim((string) getenv('OLD_UPLOAD_DIR'), '/');
$legacyCertBase = rtrim((string) getenv('LEGACY_CERT_BASE'), '/');

function loadEnv(string $envFile): array
{
    $env = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim(trim($value), "\"'\r");
    }

    return $env;
}

function isCertificateFile(string $file): bool
{
    return (bool) preg_match('/\.(pfx|p12)$/i', $file);
}

function isStandardName(string $file, string $chave, int $idMatrizFilial): bool
{
    $pattern = '/^' . preg_quote($chave, '/') . '_' . $idMatrizFilial . '_\d+\.(pfx|p12)$/';
    return (bool) preg_match($pattern, $file);
}

function nextStandardName(string $targetDir, string $chave, int $idMatrizFilial, string $ext): string
{
    $timestamp = time();

    do {
        $file = "{$chave}_{$idMatrizFilial}_{$timestamp}.{$ext}";
        $timestamp++;
    } while (file_exists($targetDir . '/' . $file));

    return $file;
}

$env = loadEnv($envFile);
foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
    if (empty($env[$key])) {
        fwrite(STDERR, "Credencial {$key} ausente em {$envFile}\n");
        exit(1);
    }
}

$mysqli = new mysqli(
    $env['DB_HOST'],
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'] ?? '',
    $env['DB_DATABASE'],
    (int) ($env['DB_PORT'] ?? 3306)
);

if ($mysqli->connect_errno) {
    fwrite(STDERR, "Erro ao conectar no banco: {$mysqli->connect_error}\n");
    exit(1);
}

$mysqli->set_charset('utf8mb4');
$result = $mysqli->query("
    SELECT id, chave, id_matriz_filial, certificado_arquivo
    FROM nfse_configuracoes
    WHERE certificado_arquivo IS NOT NULL
      AND certificado_arquivo <> ''
    ORDER BY chave, id_matriz_filial
");

if (!$result) {
    fwrite(STDERR, "Erro ao consultar nfse_configuracoes: {$mysqli->error}\n");
    exit(1);
}

echo "Destino final : {$targetDir}\n";
echo "Origem antiga : {$oldUploadDir}\n";
echo "Origem legada : {$legacyCertBase}/{chave}/\n";
echo 'Modo          : ' . ($execute ? 'EXECUTE' : 'DRY-RUN (use --execute para mover e atualizar o banco)') . "\n\n";

$moveCandidates = 0;
$dbUpdates = 0;
$missing = 0;
$skipped = 0;

if ($execute && !is_dir($targetDir) && !mkdir($targetDir, 0700, true)) {
    fwrite(STDERR, "Erro ao criar destino: {$targetDir}\n");
    exit(1);
}

while ($row = $result->fetch_assoc()) {
    $id = (int) $row['id'];
    $chave = (string) $row['chave'];
    $idMatrizFilial = (int) $row['id_matriz_filial'];
    $arquivoAtual = basename((string) $row['certificado_arquivo']);

    if (!isCertificateFile($arquivoAtual)) {
        echo "SKIP extensao inesperada: {$arquivoAtual}\n";
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($arquivoAtual, PATHINFO_EXTENSION));
    $arquivoNovo = isStandardName($arquivoAtual, $chave, $idMatrizFilial)
        ? $arquivoAtual
        : nextStandardName($targetDir, $chave, $idMatrizFilial, $ext);

    $targetCurrent = $targetDir . '/' . $arquivoAtual;
    $targetNew = $targetDir . '/' . $arquivoNovo;
    $oldUploadCurrent = $oldUploadDir . '/' . $arquivoAtual;
    $legacyCurrent = $legacyCertBase . '/' . $chave . '/' . $arquivoAtual;

    $source = null;
    if (is_file($targetCurrent)) {
        $source = $targetCurrent;
    } elseif (is_file($oldUploadCurrent)) {
        $source = $oldUploadCurrent;
    } elseif (is_file($legacyCurrent)) {
        $source = $legacyCurrent;
    }

    if ($source === null && is_file($targetNew)) {
        echo "UPDATE DB: {$arquivoAtual} -> {$arquivoNovo} (arquivo ja existe no destino)\n";
        if ($execute && $arquivoAtual !== $arquivoNovo) {
            $stmt = $mysqli->prepare('UPDATE nfse_configuracoes SET certificado_arquivo = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('si', $arquivoNovo, $id);
            $stmt->execute();
            $dbUpdates++;
        }
        continue;
    }

    if ($source === null) {
        echo "MISSING: {$arquivoAtual} (chave {$chave}, filial {$idMatrizFilial})\n";
        $missing++;
        continue;
    }

    if ($source === $targetNew && $arquivoAtual === $arquivoNovo) {
        echo "OK padronizado: {$targetNew}\n";
        $skipped++;
        continue;
    }

    echo "MOVE: {$source} -> {$targetNew}\n";
    $moveCandidates++;

    if ($execute) {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true)) {
            fwrite(STDERR, "Erro ao criar destino: {$targetDir}\n");
            exit(1);
        }

        if (is_file($targetNew) && realpath($source) !== realpath($targetNew)) {
            fwrite(STDERR, "Destino ja existe, nao vou sobrescrever: {$targetNew}\n");
            exit(1);
        }

        if ($source !== $targetNew && !rename($source, $targetNew)) {
            fwrite(STDERR, "Erro ao mover {$source} para {$targetNew}\n");
            exit(1);
        }

        chmod($targetNew, 0600);
        @chown($targetDir, '7carros');
        @chgrp($targetDir, '7carros');
        @chown($targetNew, '7carros');
        @chgrp($targetNew, '7carros');

        if ($arquivoAtual !== $arquivoNovo) {
            $stmt = $mysqli->prepare('UPDATE nfse_configuracoes SET certificado_arquivo = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('si', $arquivoNovo, $id);
            $stmt->execute();
            $dbUpdates++;
        }
    }
}

echo "\nResumo:\n";
echo "  move candidates: {$moveCandidates}\n";
echo "  db updates: {$dbUpdates}\n";
echo "  missing: {$missing}\n";
echo "  skipped/ok: {$skipped}\n";
PHP
