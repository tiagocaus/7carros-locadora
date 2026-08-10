<?php

/**
 * Teste: veiculos vendidos, roubados e excluidos ficam no final da listagem.
 *
 * Execute: php tests/test_veiculos_inativos_final_lista.php
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
use App\Models\Veiculo;

$chave = 'ordv' . substr(bin2hex(random_bytes(8)), 0, 16);
$outraChave = 'ordv' . substr(bin2hex(random_bytes(8)), 0, 16);
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste Ordenacao Veiculos';

$ids = [];
$falhas = 0;

function validarOrdenacaoVeiculos(bool $condicao, string $mensagem): void
{
    global $falhas;

    echo ($condicao ? 'PASS' : 'FAIL') . ": {$mensagem}\n";
    if (!$condicao) {
        $falhas++;
    }
}

function inserirVeiculoOrdenacao(
    string $chave,
    string $placa,
    string $modelo,
    string $disponibilidade
): int {
    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => $placa,
        'marca' => 'Marca Ordenacao',
        'modelo' => $modelo,
        'disponibilidade' => $disponibilidade,
    ]);
}

try {
    $ids[] = [inserirVeiculoOrdenacao($chave, 'ORD1Z01', 'ZZORD Zulu', 'D'), $chave];
    $ids[] = [inserirVeiculoOrdenacao($chave, 'ORD1O02', 'ZZORD Omega', 'L'), $chave];
    $ids[] = [inserirVeiculoOrdenacao($chave, 'ORD1A03', 'ZZORD Alpha', 'V'), $chave];
    $ids[] = [inserirVeiculoOrdenacao($chave, 'ORD1B04', 'ZZORD Beta', 'RO'), $chave];
    $ids[] = [inserirVeiculoOrdenacao($chave, 'ORD1G05', 'ZZORD Gamma', 'E'), $chave];
    $ids[] = [inserirVeiculoOrdenacao($outraChave, 'ORD1X99', 'ZZORD Aardvark', 'D'), $outraChave];

    $model = new Veiculo();
    $todos = $model->listarPaginado($chave, 1, 10, 'ZZORD');

    validarOrdenacaoVeiculos(
        array_column($todos, 'modelo') === [
            'ZZORD Omega',
            'ZZORD Zulu',
            'ZZORD Alpha',
            'ZZORD Beta',
            'ZZORD Gamma',
        ],
        'ativos aparecem antes dos inativos e cada grupo preserva a ordem por modelo'
    );
    validarOrdenacaoVeiculos(
        !in_array('ORD1X99', array_column($todos, 'placa'), true),
        'a listagem continua isolada por tenant'
    );

    $pagina1 = $model->listarPaginado($chave, 1, 2, 'ZZORD');
    $pagina2 = $model->listarPaginado($chave, 2, 2, 'ZZORD');
    $pagina3 = $model->listarPaginado($chave, 3, 2, 'ZZORD');

    validarOrdenacaoVeiculos(
        array_column($pagina1, 'disponibilidade') === ['L', 'D'],
        'a primeira pagina contem somente veiculos ativos'
    );
    validarOrdenacaoVeiculos(
        array_column($pagina2, 'disponibilidade') === ['V', 'RO'],
        'os veiculos inativos comecam somente depois dos ativos'
    );
    validarOrdenacaoVeiculos(
        array_column($pagina3, 'disponibilidade') === ['E'],
        'o ultimo veiculo inativo permanece na pagina final'
    );
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($ids as [$id, $tenant]) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$id, $tenant]);
    }
}

if ($falhas > 0) {
    throw new RuntimeException("Teste falhou com {$falhas} erro(s).");
}

echo "Ordenacao de veiculos inativos validada.\n";
