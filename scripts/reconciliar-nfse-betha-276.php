#!/usr/bin/env php
<?php

/**
 * Recupera o protocolo de cancelamento Betha da NFS-e 276 da Teles Locadora.
 *
 * O pedido ja foi recebido pela Betha. Este script apenas restaura o estado
 * local para que o cron consulte o protocolo; ele nunca reenvia o evento.
 *
 * Uso no servidor:
 *   php scripts/reconciliar-nfse-betha-276.php --env=production
 *   php scripts/reconciliar-nfse-betha-276.php --env=production --apply
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

use App\Core\Database;

const NFSE_ID = 1450;
const FILIAL_ID = 1428;
const NFSE_NUMERO = 276;
const EVENTO_ID = 4220;
const PROTOCOLO_CANCELAMENTO = '608635427881452';
const CANCELAMENTO_RECEBIDO_EM = '2026-08-26 12:22:09';
const MOTIVO_CANCELAMENTO = 'reajuste de valores';

$host = (string) Database::env('DB_HOST', 'localhost');
if ($env === 'production' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Em producao, execute este script no servidor com DB_HOST=localhost.\n");
    exit(1);
}

$pdo = Database::getConnection();
$buscar = $pdo->prepare(<<<'SQL'
SELECT n.id, n.chave, n.id_matriz_filial, n.numero, n.tipo_emissao, n.status,
       n.cancelamento_status, n.cancelamento_protocolo, n.cancelamento_solicitado_em,
       n.motivo_cancelamento, mf.nome_fantasia, mf.razao_social,
       e.id AS evento_id, e.tipo_evento, e.codigo_retorno, e.xml_evento
FROM nfse n
INNER JOIN matrizes_filiais mf
        ON mf.id = n.id_matriz_filial AND mf.chave = n.chave
INNER JOIN nfse_eventos e
        ON e.id = :evento_id AND e.id_nfse = n.id
WHERE n.id = :nfse_id
  AND n.id_matriz_filial = :filial_id
  AND n.numero = :numero
LIMIT 1
SQL);
$buscar->execute([
    ':evento_id' => EVENTO_ID,
    ':nfse_id' => NFSE_ID,
    ':filial_id' => FILIAL_ID,
    ':numero' => NFSE_NUMERO,
]);
$nota = $buscar->fetch(PDO::FETCH_ASSOC);

echo "Reconciliacao do cancelamento Betha da NFS-e 276\n";
echo "Ambiente: {$env}\n";
echo 'Modo: ' . ($apply ? 'APLICAR' : 'DRY-RUN') . "\n\n";

if (!$nota) {
    fwrite(STDERR, "Pre-condicao falhou: nota, filial ou evento esperado nao foi localizado.\n");
    exit(2);
}

$empresa = mb_strtoupper(trim((string) ($nota['nome_fantasia'] ?: $nota['razao_social'])), 'UTF-8');
$xmlEvento = (string) ($nota['xml_evento'] ?? '');
$jaReconciliada = $nota['status'] === 'autorizada'
    && $nota['cancelamento_status'] === 'processando'
    && $nota['cancelamento_protocolo'] === PROTOCOLO_CANCELAMENTO;

$preCondicoes = [
    'empresa' => str_contains($empresa, 'TELES LOCADORA'),
    'emissor' => $nota['tipo_emissao'] === 'betha',
    'nota autorizada' => $nota['status'] === 'autorizada',
    'estado recuperavel' => $nota['cancelamento_status'] === 'erro' || $jaReconciliada,
    'evento de erro esperado' => $nota['evento_id'] == EVENTO_ID && $nota['tipo_evento'] === 'erro',
    'protocolo no retorno original' => str_contains($xmlEvento, '<ns2:protocolo>' . PROTOCOLO_CANCELAMENTO . '</ns2:protocolo>'),
    'status no retorno original' => str_contains($xmlEvento, '<ns2:status>Não processado</ns2:status>'),
];

foreach ($preCondicoes as $descricao => $valida) {
    echo sprintf("  [%s] %s\n", $valida ? 'OK' : 'FALHA', $descricao);
}
if (in_array(false, $preCondicoes, true)) {
    fwrite(STDERR, "\nNenhuma alteracao realizada: as pre-condicoes nao conferem.\n");
    exit(2);
}

echo "\nEmpresa: " . ($nota['nome_fantasia'] ?: $nota['razao_social']) . "\n";
echo "NFS-e: " . NFSE_NUMERO . " (id=" . NFSE_ID . ")\n";
echo "Acao: restaurar protocolo aceito e aguardar consulta automatica; nenhum evento sera reenviado.\n";

if ($jaReconciliada) {
    echo "Estado ja reconciliado; nenhuma alteracao necessaria.\n";
    exit(0);
}
if (!$apply) {
    echo "\nNenhuma alteracao foi realizada. Revise o resultado e use --apply para executar.\n";
    exit(0);
}

try {
    $pdo->beginTransaction();

    $atualizar = $pdo->prepare(<<<'SQL'
UPDATE nfse
SET cancelamento_status = 'processando',
    cancelamento_protocolo = :protocolo,
    cancelamento_solicitado_em = :solicitado_em,
    motivo_cancelamento = :motivo
WHERE id = :id
  AND chave = :chave
  AND id_matriz_filial = :filial_id
  AND numero = :numero
  AND tipo_emissao = 'betha'
  AND status = 'autorizada'
  AND cancelamento_status = 'erro'
  AND cancelamento_protocolo IS NULL
SQL);
    $atualizar->execute([
        ':protocolo' => PROTOCOLO_CANCELAMENTO,
        ':solicitado_em' => CANCELAMENTO_RECEBIDO_EM,
        ':motivo' => MOTIVO_CANCELAMENTO,
        ':id' => NFSE_ID,
        ':chave' => $nota['chave'],
        ':filial_id' => FILIAL_ID,
        ':numero' => NFSE_NUMERO,
    ]);
    if ($atualizar->rowCount() !== 1) {
        throw new RuntimeException('Atualizacao nao afetou exatamente uma NFS-e.');
    }

    $inserirEvento = $pdo->prepare(<<<'SQL'
INSERT INTO nfse_eventos (id_nfse, tipo_evento, codigo_retorno, mensagem)
SELECT :id_nfse, 'reconciliacao', 'CANCELAMENTO_BETHA_RECUPERADO', :mensagem
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM nfse_eventos
    WHERE id_nfse = :id_nfse_existente
      AND tipo_evento = 'reconciliacao'
      AND codigo_retorno = 'CANCELAMENTO_BETHA_RECUPERADO'
)
SQL);
    $inserirEvento->execute([
        ':id_nfse' => NFSE_ID,
        ':mensagem' => 'Protocolo Betha aceito recuperado do retorno original; cancelamento aguardando consulta automatica.',
        ':id_nfse_existente' => NFSE_ID,
    ]);

    $pdo->commit();
    echo "\nReconciliacao concluida. O cron consultara o protocolo sem reenviar o cancelamento.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Falha; transacao revertida: {$e->getMessage()}\n");
    exit(3);
}
