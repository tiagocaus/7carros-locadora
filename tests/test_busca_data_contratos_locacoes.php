<?php

/**
 * Teste: busca por data nas listagens de contratos e locacoes.
 *
 * Execute: php tests/test_busca_data_contratos_locacoes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

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
use App\Models\Contrato;
use App\Models\Locacao;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
DateHelper::clearCache();

$falhas = 0;
$sucessos = 0;
$locacoesCriadas = [];
$contratosCriados = [];

function checkBuscaData(string $label, bool $ok): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}\n";
    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function contemIdBuscaData(array $rows, int $id): bool
{
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return true;
        }
    }

    return false;
}

function codigoBuscaData(string $prefixo): string
{
    return $prefixo . random_int(100000, 999999);
}

echo "=== Teste busca por data em contratos e locacoes ===\n";

try {
    $dataBusca = '2026-06-15';
    $termoBusca = format_date($dataBusca);

    $locacoesCriadas[] = Database::insertGetId('locacoes', [
        'codigo' => codigoBuscaData('LD'),
        'chave' => $chave,
        'status' => 'R',
        'data_saida' => '2026-06-15 09:00:00',
        'data_prevista' => '2026-06-20 09:00:00',
        'dias' => 5,
        'cliente_nome' => 'Busca Data Saida',
    ]);
    $locacoesCriadas[] = Database::insertGetId('locacoes', [
        'codigo' => codigoBuscaData('LP'),
        'chave' => $chave,
        'status' => 'R',
        'data_saida' => '2026-06-10 09:00:00',
        'data_prevista' => '2026-06-15 18:00:00',
        'dias' => 5,
        'cliente_nome' => 'Busca Data Prevista',
    ]);
    $locacoesCriadas[] = Database::insertGetId('locacoes', [
        'codigo' => codigoBuscaData('LC'),
        'chave' => $chave,
        'status' => 'F',
        'data_saida' => '2026-06-10 09:00:00',
        'data_prevista' => '2026-06-14 09:00:00',
        'data_chegada' => '2026-06-15 11:30:00',
        'dias' => 5,
        'cliente_nome' => 'Busca Data Chegada',
    ]);

    $contratosCriados[] = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => codigoBuscaData('CD'),
        'data_ini' => '2026-06-15 08:00:00',
        'data_fim' => '2026-07-15 08:00:00',
        'contagem' => 'dia',
        'dias' => 30,
        'status' => 'A',
    ]);
    $contratosCriados[] = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => codigoBuscaData('CF'),
        'data_ini' => '2026-05-15 08:00:00',
        'data_fim' => '2026-06-15 18:00:00',
        'contagem' => 'dia',
        'dias' => 30,
        'status' => 'A',
    ]);
    $contratosCriados[] = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => codigoBuscaData('CR'),
        'data_ini' => '2026-05-15 08:00:00',
        'data_fim' => '2026-07-15 08:00:00',
        'data_renovacao' => '2026-06-15',
        'contagem' => 'dia',
        'dias' => 30,
        'status' => 'A',
    ]);

    $locacaoModel = new Locacao();
    $locacoes = $locacaoModel->listarPaginado($chave, 1, 100, $termoBusca);
    $totalLocacoes = $locacaoModel->contar($chave, $termoBusca);

    foreach ($locacoesCriadas as $idLocacao) {
        checkBuscaData("locacao {$idLocacao} encontrada por {$termoBusca}", contemIdBuscaData($locacoes, (int) $idLocacao));
    }
    checkBuscaData('contador de locacoes considera busca por data', $totalLocacoes >= count($locacoesCriadas));

    $contratoModel = new Contrato();
    $contratos = $contratoModel->listarPaginado($chave, 1, 100, $termoBusca);
    $totalContratos = $contratoModel->contar($chave, $termoBusca);

    foreach ($contratosCriados as $idContrato) {
        checkBuscaData("contrato {$idContrato} encontrado por {$termoBusca}", contemIdBuscaData($contratos, (int) $idContrato));
    }
    checkBuscaData('contador de contratos considera busca por data', $totalContratos >= count($contratosCriados));
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($locacoesCriadas as $idLocacao) {
        Database::execute('DELETE FROM locacoes WHERE id = ? AND chave = ?', [$idLocacao, $chave]);
    }
    foreach ($contratosCriados as $idContrato) {
        Database::execute('DELETE FROM contratos WHERE id = ? AND chave = ?', [$idContrato, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
