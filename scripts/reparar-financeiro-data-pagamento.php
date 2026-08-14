<?php

/**
 * Audita e normaliza datas de pagamento em lancamentos pendentes.
 *
 * Padrao: somente previa.
 * Producao:  php scripts/reparar-financeiro-data-pagamento.php --env=production
 * Tenant:    php scripts/reparar-financeiro-data-pagamento.php --env=production --tenant=CHAVE
 * Aplicacao: php scripts/reparar-financeiro-data-pagamento.php --env=production --apply --confirm=NORMALIZAR_DATA_PAGO_PENDENTES
 */

$ambiente = 'development';
$ambientesPermitidos = ['development', 'production'];
$tenantFiltro = null;
$aplicar = in_array('--apply', $argv, true);
$confirmacao = null;

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--env=')) {
        $ambiente = substr($argumento, strlen('--env='));
    } elseif (str_starts_with($argumento, '--tenant=')) {
        $tenantFiltro = trim(substr($argumento, strlen('--tenant=')));
    } elseif (str_starts_with($argumento, '--confirm=')) {
        $confirmacao = substr($argumento, strlen('--confirm='));
    }
}

if (!in_array($ambiente, $ambientesPermitidos, true)) {
    fwrite(STDERR, "Ambiente invalido: {$ambiente}. Use development ou production.\n");
    exit(1);
}

if ($aplicar && $confirmacao !== 'NORMALIZAR_DATA_PAGO_PENDENTES') {
    fwrite(
        STDERR,
        "Confirmacao obrigatoria para aplicar: --confirm=NORMALIZAR_DATA_PAGO_PENDENTES\n"
    );
    exit(1);
}

$_ENV['APP_ENV'] = $ambiente;
putenv("APP_ENV={$ambiente}");

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\Model;

$mysqli = Model::sharedMysqli();
$nomeBanco = (string) Database::env('DB_DATABASE', 'nao informado');
$hostBanco = strtolower(trim((string) Database::env('DB_HOST', '')));

if ($ambiente === 'production' && !in_array($hostBanco, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Producao exige DB_HOST local. Host configurado: {$hostBanco}\n");
    Model::closeConnection();
    exit(1);
}

$consultarResumo = static function () use ($mysqli, $tenantFiltro): array {
    $sql = "
        SELECT
            chave,
            COUNT(*) AS quantidade,
            SUM(data_pago = '0000-00-00') AS datas_zero,
            SUM(data_pago <> '0000-00-00') AS datas_validas
        FROM financeiro
        WHERE pago = 'N'
          AND data_pago IS NOT NULL
    ";

    if ($tenantFiltro !== null && $tenantFiltro !== '') {
        $sql .= ' AND chave = ?';
    }

    $sql .= ' GROUP BY chave ORDER BY chave';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Erro ao preparar auditoria: {$mysqli->error}");
    }

    if ($tenantFiltro !== null && $tenantFiltro !== '') {
        $stmt->bind_param('s', $tenantFiltro);
    }

    $stmt->execute();
    $resumo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $resumo;
};

$consultarPagosSemDataValida = static function () use ($mysqli, $tenantFiltro): array {
    $sql = "
        SELECT
            chave,
            COUNT(*) AS quantidade,
            SUM(CASE WHEN tipo = 'R' THEN valor_total ELSE 0 END) AS receitas,
            SUM(CASE WHEN tipo = 'D' THEN valor_total ELSE 0 END) AS despesas
        FROM financeiro
        WHERE pago = 'S'
          AND (data_pago IS NULL OR data_pago < '1900-01-01' OR data_pago > '2100-12-31')
    ";

    if ($tenantFiltro !== null && $tenantFiltro !== '') {
        $sql .= ' AND chave = ?';
    }

    $sql .= ' GROUP BY chave ORDER BY chave';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Erro ao preparar auditoria de pagos: {$mysqli->error}");
    }

    if ($tenantFiltro !== null && $tenantFiltro !== '') {
        $stmt->bind_param('s', $tenantFiltro);
    }

    $stmt->execute();
    $resumo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $resumo;
};

try {
    $resumoAntes = $consultarResumo();
    $pagosSemDataValida = $consultarPagosSemDataValida();
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao consultar inconsistencias: {$e->getMessage()}\n");
    Model::closeConnection();
    exit(1);
}

echo "AMBIENTE | {$ambiente}\n";
echo "BANCO | {$nomeBanco}\n";
echo $aplicar ? "MODO APLICACAO\n" : "MODO PREVIA (nenhuma gravacao)\n";
if ($tenantFiltro !== null && $tenantFiltro !== '') {
    echo "TENANT | {$tenantFiltro}\n";
}

$totalAntes = 0;
$totalDatasZero = 0;
$totalDatasValidas = 0;

foreach ($resumoAntes as $tenant) {
    $quantidade = (int) $tenant['quantidade'];
    $datasZero = (int) $tenant['datas_zero'];
    $datasValidas = (int) $tenant['datas_validas'];
    $totalAntes += $quantidade;
    $totalDatasZero += $datasZero;
    $totalDatasValidas += $datasValidas;

    printf(
        "%s | registros=%d | datas_validas=%d | datas_zero=%d\n",
        $tenant['chave'],
        $quantidade,
        $datasValidas,
        $datasZero
    );
}

printf(
    "TOTAL_PREVIA | tenants=%d | registros=%d | datas_validas=%d | datas_zero=%d\n",
    count($resumoAntes),
    $totalAntes,
    $totalDatasValidas,
    $totalDatasZero
);

$totalPagosSemData = 0;
foreach ($pagosSemDataValida as $tenant) {
    $quantidade = (int) $tenant['quantidade'];
    $totalPagosSemData += $quantidade;
    printf(
        "PAGO_SEM_DATA_VALIDA | %s | registros=%d | receitas=%.2f | despesas=%.2f\n",
        $tenant['chave'],
        $quantidade,
        (float) $tenant['receitas'],
        (float) $tenant['despesas']
    );
}
printf(
    "TOTAL_PAGOS_SEM_DATA_VALIDA | tenants=%d | registros=%d | somente_auditoria=sim\n",
    count($pagosSemDataValida),
    $totalPagosSemData
);

if (!$aplicar || $totalAntes === 0) {
    Model::closeConnection();
    exit(0);
}

$atualizar = $mysqli->prepare("
    UPDATE financeiro
    SET data_pago = NULL,
        updated_at = ?
    WHERE chave = ?
      AND pago = 'N'
      AND data_pago IS NOT NULL
");

if (!$atualizar) {
    fwrite(STDERR, "Erro ao preparar normalizacao: {$mysqli->error}\n");
    Model::closeConnection();
    exit(1);
}

$totalAtualizado = 0;

foreach ($resumoAntes as $tenant) {
    $chave = (string) $tenant['chave'];
    $mysqli->begin_transaction();

    try {
        $updatedAt = DateHelper::systemNow();
        $atualizar->bind_param('ss', $updatedAt, $chave);
        $atualizar->execute();
        $afetadas = $atualizar->affected_rows;
        $mysqli->commit();
        $totalAtualizado += $afetadas;

        printf("APLICADO | %s | registros=%d\n", $chave, $afetadas);
    } catch (Throwable $e) {
        $mysqli->rollback();
        fwrite(STDERR, "ERRO | tenant={$chave} | operacao revertida | {$e->getMessage()}\n");
        $atualizar->close();
        Model::closeConnection();
        exit(1);
    }
}

$atualizar->close();

try {
    $resumoDepois = $consultarResumo();
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao verificar normalizacao: {$e->getMessage()}\n");
    Model::closeConnection();
    exit(1);
}

$totalRestante = array_sum(array_map(
    static fn(array $tenant): int => (int) $tenant['quantidade'],
    $resumoDepois
));

printf(
    "TOTAL_APLICADO | registros=%d | restantes=%d\n",
    $totalAtualizado,
    $totalRestante
);

Model::closeConnection();
exit($totalRestante === 0 ? 0 : 2);
