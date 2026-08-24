#!/usr/bin/env php
<?php

/**
 * Saneia rejeicoes locais de IBS/CBS e reconcilia DPS Nacional ja gerada.
 *
 * Uso:
 *   php scripts/reconciliar-nfse-nacional.php --env=production
 *   php scripts/reconciliar-nfse-nacional.php --env=production --apply
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

use App\Models\NFSe as NFSeModel;
use App\Models\NFSeEvento;
use App\Services\NFSe\NFSeErros;
use App\Services\NFSe\NFSeService;

$host = (string) App\Core\Database::env('DB_HOST', 'localhost');
if ($env === 'production' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Em producao, execute este script no servidor com DB_HOST=localhost.\n");
    exit(1);
}

$pdo = conectarBanco();

$configsSimples = $pdo->query("
    SELECT c.id, c.chave, c.id_matriz_filial, c.regime_tributario, mf.nome_fantasia
    FROM nfse_configuracoes c
    LEFT JOIN matrizes_filiais mf ON mf.id = c.id_matriz_filial AND mf.chave = c.chave
    WHERE c.ativo = 'S'
      AND c.tipo_emissao = 'nacional'
      AND c.preencher_ibscbs = 'S'
      AND c.regime_tributario IN (1, 4)
    ORDER BY c.chave, c.id_matriz_filial
")->fetchAll(PDO::FETCH_ASSOC);

$notas = $pdo->query("
    SELECT DISTINCT n.id, n.chave, n.numero, n.id_matriz_filial, n.codigo_rejeicao,
           n.motivo_rejeicao, n.tentativas_envio, mf.nome_fantasia,
           EXISTS(
               SELECT 1
               FROM nfse_eventos e
               WHERE e.id_nfse = n.id
                 AND e.codigo_retorno = 'E0014'
           ) AS possui_e0014
    FROM nfse n
    LEFT JOIN matrizes_filiais mf ON mf.id = n.id_matriz_filial AND mf.chave = n.chave
    WHERE n.tipo_emissao = 'nacional'
      AND n.status = 'rejeitada'
      AND (
          n.codigo_rejeicao = 'DPS_JA_GERADA'
          OR n.motivo_rejeicao LIKE '%Preenchimento de IBS/CBS ainda não habilitado%'
          OR EXISTS(
              SELECT 1
              FROM nfse_eventos e
              WHERE e.id_nfse = n.id
                AND (
                    e.codigo_retorno = 'E0014'
                    OR e.mensagem LIKE '%Preenchimento de IBS/CBS ainda não habilitado%'
                )
          )
      )
    ORDER BY n.chave, n.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Saneamento NFS-e Nacional\n";
echo "Ambiente: {$env}\n";
echo 'Modo: ' . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n\n";

echo 'Configuracoes Simples Nacional com IBS/CBS antecipado: ' . count($configsSimples) . "\n";
foreach ($configsSimples as $config) {
    echo sprintf(
        "  config=%d tenant=%s filial=%d empresa=%s -> preencher_ibscbs=N\n",
        $config['id'],
        $config['chave'],
        $config['id_matriz_filial'],
        $config['nome_fantasia'] ?: '-'
    );
}

echo "\nNotas afetadas: " . count($notas) . "\n";
foreach ($notas as $nota) {
    echo sprintf(
        "  nfse=%d tenant=%s numero=%s empresa=%s tentativas=%d -> %s\n",
        $nota['id'],
        $nota['chave'],
        $nota['numero'] ?: '-',
        $nota['nome_fantasia'] ?: '-',
        $nota['tentativas_envio'],
        (int) $nota['possui_e0014'] === 1 ? 'reconciliar pela DPS' : 'restaurar rejeicao externa'
    );
}

if (!$apply) {
    echo "\nNenhuma alteracao foi realizada. Use --apply para executar.\n";
    exit(0);
}

$updateConfig = $pdo->prepare("
    UPDATE nfse_configuracoes
    SET preencher_ibscbs = 'N'
    WHERE id = :id AND chave = :chave AND regime_tributario IN (1, 4)
");
foreach ($configsSimples as $config) {
    $updateConfig->execute([':id' => $config['id'], ':chave' => $config['chave']]);
}

$buscarErroExterno = $pdo->prepare("
    SELECT codigo_retorno, mensagem
    FROM nfse_eventos
    WHERE id_nfse = :id_nfse
      AND tipo_evento = 'erro'
      AND codigo_retorno IS NOT NULL
      AND codigo_retorno NOT IN ('CONN_CURL', 'IBSCBS_CONFIGURACAO')
      AND mensagem NOT LIKE '%Preenchimento de IBS/CBS ainda não habilitado%'
    ORDER BY id ASC
    LIMIT 1
");

$reconciliadas = 0;
$restauradas = 0;
$falhas = 0;

foreach ($notas as $nota) {
    $_SESSION['chave'] = (string) $nota['chave'];
    $id = (int) $nota['id'];
    $nfseModel = new NFSeModel();
    $eventoModel = new NFSeEvento();

    if ((int) $nota['possui_e0014'] === 1) {
        $nfseModel->atualizarStatus($id, 'rejeitada', 'DPS já gerada; aguardando reconciliação com a SEFIN.', 'DPS_JA_GERADA');
        $resultado = (new NFSeService())->consultar($id, (string) $nota['chave']);
        if ($resultado['sucesso'] ?? false) {
            $reconciliadas++;
            echo "[OK] nfse={$id} reconciliada.\n";
        } else {
            $falhas++;
            echo "[ERRO] nfse={$id} " . ($resultado['mensagem'] ?? 'falha na reconciliacao') . "\n";
        }
        continue;
    }

    $buscarErroExterno->execute([':id_nfse' => $id]);
    $erroExterno = $buscarErroExterno->fetch(PDO::FETCH_ASSOC);
    if (!$erroExterno) {
        $falhas++;
        echo "[SKIP] nfse={$id} sem rejeicao externa anterior para restaurar.\n";
        continue;
    }

    $codigoInterno = NFSeErros::mapearErroRetorno(
        (string) $erroExterno['codigo_retorno'],
        (string) $erroExterno['mensagem']
    );
    $nfseModel->atualizarStatus($id, 'rejeitada', (string) $erroExterno['mensagem'], $codigoInterno);
    $eventoModel->registrar($id, 'saneamento', $codigoInterno, 'Rejeição externa restaurada após remoção do bloqueio local de IBS/CBS.');
    $restauradas++;
    echo "[OK] nfse={$id} restaurada para {$codigoInterno}.\n";
}

echo "\nResumo\n";
echo 'Configuracoes normalizadas: ' . count($configsSimples) . "\n";
echo "Notas reconciliadas: {$reconciliadas}\n";
echo "Notas restauradas: {$restauradas}\n";
echo "Falhas/pendencias: {$falhas}\n";

exit($falhas > 0 ? 2 : 0);

function conectarBanco(): PDO
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
