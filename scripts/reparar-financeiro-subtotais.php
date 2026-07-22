<?php

/**
 * Audita e repara subtotais inconsistentes do financeiro.
 *
 * Padrao: somente previa.
 * Producao:  php scripts/reparar-financeiro-subtotais.php --env=production
 * Tenant:    php scripts/reparar-financeiro-subtotais.php --env=production --tenant=CHAVE
 * Aplicacao: php scripts/reparar-financeiro-subtotais.php --env=production --apply
 */

$ambiente = 'development';
$ambientesPermitidos = ['development', 'production'];
$tenantFiltro = null;
$aplicar = in_array('--apply', $argv, true);

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--env=')) {
        $ambiente = substr($argumento, strlen('--env='));
    } elseif (str_starts_with($argumento, '--tenant=')) {
        $tenantFiltro = trim(substr($argumento, strlen('--tenant=')));
    }
}

if (!in_array($ambiente, $ambientesPermitidos, true)) {
    fwrite(STDERR, "Ambiente invalido: {$ambiente}. Use development ou production.\n");
    exit(1);
}

$_ENV['APP_ENV'] = $ambiente;
putenv("APP_ENV={$ambiente}");

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\Model;

$mysqli = Model::sharedMysqli();
$nomeBanco = (string) Database::env('DB_DATABASE', 'nao informado');

$sql = "
    SELECT
        f.id,
        f.chave,
        f.sequencia,
        f.descricao,
        f.valor_subtotal,
        f.juros,
        f.multa,
        f.desconto,
        f.valor_total,
        COUNT(fi.id) AS quantidade_itens,
        COALESCE(SUM(fi.valor), 0) AS soma_itens
    FROM financeiro f
    LEFT JOIN financeiro_itens fi
      ON fi.chave = f.chave
     AND fi.id_financeiro = f.id
";

$params = [];
if ($tenantFiltro !== null && $tenantFiltro !== '') {
    $sql .= ' WHERE f.chave = ?';
    $params[] = $tenantFiltro;
}

$sql .= "
    GROUP BY f.id
    HAVING (
        quantidade_itens > 0
        AND (
            ABS(COALESCE(f.valor_subtotal, 0) - soma_itens) > 0.009
            OR ABS(COALESCE(f.valor_total, 0) - (
                soma_itens + COALESCE(f.juros, 0) + COALESCE(f.multa, 0) - COALESCE(f.desconto, 0)
            )) > 0.009
        )
    ) OR (
        quantidade_itens = 0
        AND COALESCE(f.valor_subtotal, 0) = 0
        AND (COALESCE(f.valor_total, 0) - COALESCE(f.juros, 0) - COALESCE(f.multa, 0) + COALESCE(f.desconto, 0)) > 0.009
    )
    ORDER BY f.chave, f.id
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "Erro ao preparar auditoria: {$mysqli->error}\n");
    Model::closeConnection();
    exit(1);
}
if ($params) {
    $stmt->bind_param('s', $params[0]);
}
$stmt->execute();
$result = $stmt->get_result();
$candidatos = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "AMBIENTE | {$ambiente}\n";
echo "BANCO | {$nomeBanco}\n";
echo $aplicar ? "MODO APLICACAO\n" : "MODO PREVIA (nenhuma gravacao)\n";
if ($tenantFiltro !== null && $tenantFiltro !== '') {
    echo "TENANT | {$tenantFiltro}\n";
}

$atualizar = $mysqli->prepare("
    UPDATE financeiro
    SET valor_subtotal = ?, valor_total = ?, updated_at = ?
    WHERE id = ?
      AND chave = ?
      AND ABS(COALESCE(valor_subtotal, 0) - ?) < 0.001
      AND ABS(COALESCE(valor_total, 0) - ?) < 0.001
      AND ABS(COALESCE(juros, 0) - ?) < 0.001
      AND ABS(COALESCE(multa, 0) - ?) < 0.001
      AND ABS(COALESCE(desconto, 0) - ?) < 0.001
");
if (!$atualizar) {
    fwrite(STDERR, "Erro ao preparar reparacao: {$mysqli->error}\n");
    Model::closeConnection();
    exit(1);
}

$quantidade = 0;
$totalSubtotalAntes = 0.0;
$totalSubtotalDepois = 0.0;

foreach ($candidatos as $candidato) {
    $quantidadeItens = (int) $candidato['quantidade_itens'];
    $subtotalAntes = round((float) $candidato['valor_subtotal'], 2);
    $juros = round((float) $candidato['juros'], 2);
    $multa = round((float) $candidato['multa'], 2);
    $desconto = round((float) $candidato['desconto'], 2);
    $totalAntes = round((float) $candidato['valor_total'], 2);

    if ($quantidadeItens > 0) {
        $origem = 'itens';
        $subtotalDepois = round((float) $candidato['soma_itens'], 2);
        $totalDepois = round($subtotalDepois + $juros + $multa - $desconto, 2);
    } else {
        $origem = 'total';
        $subtotalDepois = round($totalAntes - $juros - $multa + $desconto, 2);
        $totalDepois = round($subtotalDepois + $juros + $multa - $desconto, 2);
    }

    if ($subtotalDepois <= 0 || ($origem === 'total' && abs($totalDepois - $totalAntes) > 0.009)) {
        fwrite(
            STDERR,
            sprintf(
                "IGNORADO | financeiro=%d | subtotal calculado ou total invalido\n",
                (int) $candidato['id']
            )
        );
        continue;
    }

    $quantidade++;
    $totalSubtotalAntes += $subtotalAntes;
    $totalSubtotalDepois += $subtotalDepois;

    printf(
        "%s | financeiro=%d | sequencia=%s | origem=%s | itens=%d | subtotal=%.2f->%.2f | total=%.2f->%.2f | descricao=%s\n",
        $candidato['chave'],
        (int) $candidato['id'],
        $candidato['sequencia'] ?? '-',
        $origem,
        $quantidadeItens,
        $subtotalAntes,
        $subtotalDepois,
        $totalAntes,
        $totalDepois,
        str_replace(["\r", "\n", '|'], [' ', ' ', '/'], (string) $candidato['descricao'])
    );

    if (!$aplicar) {
        continue;
    }

    $mysqli->begin_transaction();
    try {
        $updatedAt = DateHelper::systemNow();
        $id = (int) $candidato['id'];
        $chave = (string) $candidato['chave'];
        $atualizar->bind_param(
            'ddsisddddd',
            $subtotalDepois,
            $totalDepois,
            $updatedAt,
            $id,
            $chave,
            $subtotalAntes,
            $totalAntes,
            $juros,
            $multa,
            $desconto
        );
        $atualizar->execute();

        if ($atualizar->affected_rows !== 1) {
            throw new RuntimeException('Registro nao atualizado ou alterado concorrentemente');
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        fwrite(STDERR, sprintf("ERRO | financeiro=%d | operacao revertida | %s\n", $id, $e->getMessage()));
        $atualizar->close();
        Model::closeConnection();
        exit(1);
    }
}

$atualizar->close();
printf(
    "TOTAL | registros=%d | subtotal_antes=%.2f | subtotal_depois=%.2f\n",
    $quantidade,
    $totalSubtotalAntes,
    $totalSubtotalDepois
);

Model::closeConnection();
