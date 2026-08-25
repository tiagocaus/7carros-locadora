#!/usr/bin/env php
<?php

/**
 * Saneia exclusivamente as quatro conciliacoes indevidas da TRIP10 LOCADORA.
 *
 * Uso:
 *   php scripts/sanear-nfse-trip10.php --env=production
 *   php scripts/sanear-nfse-trip10.php --env=production --apply
 *
 * Execute antes da migracao 00427, que cria a unicidade por tenant/chave.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$options = getopt('', ['env::', 'apply']);
$env = (string) ($options['env'] ?? 'development');
$apply = array_key_exists('apply', $options);
putenv('APP_ENV=' . $env);
$_ENV['APP_ENV'] = $env;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

const TENANT_TRIP10 = 'CE758408F6EF98D7C7A7B786ECA7B3A8';
const NFSE_IDS_TRIP10 = [1481, 1490, 1491, 1492];
const MENSAGEM_CONFLITO = 'Nenhuma NFS-e foi emitida. A numeração já estava sendo utilizada em outro emissor.';

$host = (string) App\Core\Database::env('DB_HOST', 'localhost');
if ($env === 'production' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Em producao, execute no servidor com DB_HOST=localhost.\n");
    exit(1);
}

$pdo = conectarBancoTrip10();
$idsSql = implode(',', array_map('intval', NFSE_IDS_TRIP10));
$stmt = $pdo->prepare("
    SELECT id, status, codigo_rejeicao, chave_acesso, codigo_verificacao,
           xml_retorno IS NOT NULL AS possui_xml_retorno,
           pdf_url IS NOT NULL AS possui_pdf, xml_envio IS NOT NULL AS possui_xml_envio
    FROM nfse
    WHERE chave = :chave AND id IN ({$idsSql})
    ORDER BY id
");
$stmt->execute([':chave' => TENANT_TRIP10]);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Saneamento NFS-e TRIP10 LOCADORA\n";
echo "Ambiente: {$env}\n";
echo 'Modo: ' . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n\n";

if (count($notas) !== count(NFSE_IDS_TRIP10)) {
    fwrite(STDERR, 'Pre-condicao falhou: foram localizadas ' . count($notas) . " das 4 notas esperadas no tenant.\n");
    exit(2);
}

foreach ($notas as $nota) {
    $jaSaneada = $nota['status'] === 'rejeitada'
        && $nota['codigo_rejeicao'] === 'DPS_CONFLITO'
        && empty($nota['chave_acesso'])
        && empty($nota['codigo_verificacao'])
        && (int) $nota['possui_xml_retorno'] === 0
        && (int) $nota['possui_pdf'] === 0;

    if (!$jaSaneada && $nota['status'] !== 'autorizada') {
        fwrite(STDERR, "Pre-condicao falhou: NFS-e {$nota['id']} nao esta autorizada nem previamente saneada.\n");
        exit(2);
    }
    if ((int) $nota['possui_xml_envio'] !== 1) {
        fwrite(STDERR, "Pre-condicao falhou: NFS-e {$nota['id']} nao possui o XML da tentativa local.\n");
        exit(2);
    }

    echo sprintf(
        "  nfse=%d status=%s -> %s\n",
        $nota['id'],
        $nota['status'],
        $jaSaneada ? 'ja saneada' : 'marcar DPS_CONFLITO e remover somente dados externos'
    );
}

echo "  configuracao do tenant -> ativo=N, emissao_auto=N\n";
if (!$apply) {
    echo "\nNenhuma alteracao foi realizada. Use --apply para executar.\n";
    exit(0);
}

try {
    $pdo->beginTransaction();

    $atualizar = $pdo->prepare("
        UPDATE nfse
        SET status = 'rejeitada', codigo_rejeicao = 'DPS_CONFLITO', motivo_rejeicao = :mensagem,
            chave_acesso = NULL, codigo_verificacao = NULL, xml_retorno = NULL, pdf_url = NULL
        WHERE chave = :chave AND id IN ({$idsSql})
    ");
    $atualizar->execute([':mensagem' => MENSAGEM_CONFLITO, ':chave' => TENANT_TRIP10]);

    $inserirEvento = $pdo->prepare("
        INSERT INTO nfse_eventos (id_nfse, tipo_evento, codigo_retorno, mensagem)
        SELECT :id_nfse, 'saneamento', 'CONFLITO', :mensagem
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM nfse_eventos
            WHERE id_nfse = :id_nfse_evento
              AND tipo_evento = 'saneamento' AND mensagem = :mensagem_evento
        )
    ");
    foreach (NFSE_IDS_TRIP10 as $id) {
        $mensagemEvento = 'DPS_CONFLITO: conciliação externa incompatível removida; tentativa local preservada.';
        $inserirEvento->execute([
            ':id_nfse' => $id,
            ':mensagem' => $mensagemEvento,
            ':id_nfse_evento' => $id,
            ':mensagem_evento' => $mensagemEvento,
        ]);
    }

    $desativar = $pdo->prepare("
        UPDATE nfse_configuracoes SET ativo = 'N', emissao_auto = 'N' WHERE chave = :chave
    ");
    $desativar->execute([':chave' => TENANT_TRIP10]);

    $pdo->commit();
    echo "\nSaneamento concluido. Nenhuma nota externa foi importada.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Falha; transacao revertida: {$e->getMessage()}\n");
    exit(3);
}

function conectarBancoTrip10(): PDO
{
    $driver = (string) App\Core\Database::env('DB_DRIVER', 'mysql');
    $host = (string) App\Core\Database::env('DB_HOST', 'localhost');
    $port = (string) App\Core\Database::env('DB_PORT', '3306');
    $database = (string) App\Core\Database::env('DB_DATABASE');
    $username = (string) App\Core\Database::env('DB_USERNAME');
    $password = (string) App\Core\Database::env('DB_PASSWORD');
    $charset = (string) App\Core\Database::env('DB_CHARSET', 'utf8mb4');

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
