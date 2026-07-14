<?php

/**
 * Retroativo das taxas de meios de pagamento.
 *
 * Padrao: somente previa.
 * Producao:  php scripts/backfill-taxas-meios-pagamento.php --env=production
 * Aplicacao: php scripts/backfill-taxas-meios-pagamento.php --env=production --apply
 * Tenant:    php scripts/backfill-taxas-meios-pagamento.php --env=production --tenant=CHAVE
 */

$ambiente = 'development';
$ambientesPermitidos = ['development', 'production'];

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--env=')) {
        $ambiente = substr($argumento, strlen('--env='));
    }
}

if (!in_array($ambiente, $ambientesPermitidos, true)) {
    fwrite(STDERR, "Ambiente invalido: {$ambiente}. Use development ou production.\n");
    exit(1);
}

// Database::loadConfig() consulta $_ENV; putenv() mantem compatibilidade com outros consumidores.
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
use App\Models\FinanceiroTaxa;
use App\Models\Model;
use App\Services\FinanceiroTaxaService;

$aplicar = in_array('--apply', $argv, true);
$tenantFiltro = null;
foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--tenant=')) {
        $tenantFiltro = substr($argumento, strlen('--tenant='));
    }
}

$mysqli = Model::sharedMysqli();
$nomeBanco = (string) Database::env('DB_DATABASE', 'nao informado');
$sql = "
    SELECT DISTINCT f.chave
    FROM financeiro f
    LEFT JOIN financeiro ft
      ON ft.chave = f.chave
     AND ft.id_financeiro_taxa_origem = f.id
    WHERE f.tipo = 'R'
      AND f.pago = 'S'
      AND f.valor_taxa > 0
      AND ft.id IS NULL
";
$params = [];
if ($tenantFiltro !== null && $tenantFiltro !== '') {
    $sql .= ' AND f.chave = ?';
    $params[] = $tenantFiltro;
}
$sql .= ' ORDER BY f.chave';

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param('s', $params[0]);
}
$stmt->execute();
$result = $stmt->get_result();
$chaves = array_column($result->fetch_all(MYSQLI_ASSOC), 'chave');
$stmt->close();

$quantidade = 0;
$total = 0.0;
$service = new FinanceiroTaxaService();

echo "AMBIENTE | {$ambiente}\n";
echo "BANCO | {$nomeBanco}\n";
echo $aplicar ? "MODO APLICACAO\n" : "MODO PREVIA (nenhuma gravacao)\n";

foreach ($chaves as $chave) {
    $_SESSION['chave'] = $chave;
    $model = new FinanceiroTaxa();

    foreach ($model->listarReceitasParaRetroativo() as $item) {
        $receita = $model->buscarReceita((int) $item['id']);
        if (!$receita) {
            continue;
        }

        $taxa = round((float) $receita['valor_taxa'], 2);
        $quantidade++;
        $total += $taxa;

        printf(
            "%s | financeiro=%d | data=%s | filial=%s | conta=%s | forma=%s | taxa=%.2f\n",
            $chave,
            (int) $receita['id'],
            $receita['data_pago'] ?: '-',
            $receita['id_matriz_filial'] ?: '-',
            $receita['id_conta'] ?: '-',
            $receita['forma_pagamento_nome'] ?: '-',
            $taxa
        );

        if ($aplicar) {
            try {
                $service->sincronizar((int) $receita['id']);
            } catch (\Throwable $e) {
                fwrite(
                    STDERR,
                    sprintf(
                        "ERRO | financeiro=%d | operacao revertida | %s\n",
                        (int) $receita['id'],
                        $e->getMessage()
                    )
                );
                Model::closeConnection();
                exit(1);
            }
        }
    }
}

printf("TOTAL | registros=%d | taxas=%.2f\n", $quantidade, $total);
Model::closeConnection();
