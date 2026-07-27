<?php

/**
 * Teste: listagem e atualizacao atomica do valor por fracao dos veiculos.
 *
 * Execute: php tests/test_veiculos_valor_fracao_lote.php
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

$chave = 'frac' . substr(bin2hex(random_bytes(8)), 0, 16);
$outraChave = 'frac' . substr(bin2hex(random_bytes(8)), 0, 16);
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste Valor Fracao';

$falhas = 0;
$sucessos = 0;
$idsVeiculos = [];
$idsGrupos = [];
$idsFiliais = [];

function checkValorFracao(string $label, bool $ok): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}\n";
    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function valorVeiculoFracao(int $id): float
{
    return (float) Database::fetchColumn(
        'SELECT valor_por_fracao FROM veiculos WHERE id = ?',
        [$id]
    );
}

echo "=== Teste ajuste em lote do valor por fracao ===\n";

try {
    $filialId = Database::insertGetId('matrizes_filiais', [
        'chave' => $chave,
        'tipo' => 'M',
        'status' => 'A',
        'razao_social' => 'Filial Teste Fracao',
        'nome_fantasia' => 'Filial Fracao',
        'locale' => 'pt_BR',
        'currency_code' => 'BRL',
    ]);
    $idsFiliais[] = [$filialId, $chave];

    $outraFilialId = Database::insertGetId('matrizes_filiais', [
        'chave' => $outraChave,
        'tipo' => 'M',
        'status' => 'A',
        'razao_social' => 'Outra Filial Teste Fracao',
        'nome_fantasia' => 'Outra Filial Fracao',
        'locale' => 'pt_BR',
        'currency_code' => 'BRL',
    ]);
    $idsFiliais[] = [$outraFilialId, $outraChave];

    $grupoId = Database::insertGetId('grupos', [
        'chave' => $chave,
        'nome' => 'Grupo Teste Fracao',
    ]);
    $idsGrupos[] = [$grupoId, $chave];

    $veiculoA = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'id_matriz_filial' => $filialId,
        'id_grupo' => $grupoId,
        'placa' => 'FRA1A01',
        'marca' => 'Marca A',
        'modelo' => 'Modelo A',
        'valor_por_fracao' => 30.00,
    ]);
    $idsVeiculos[] = [$veiculoA, $chave];

    $veiculoB = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'id_matriz_filial' => $filialId,
        'id_grupo' => $grupoId,
        'placa' => 'FRA1B02',
        'marca' => 'Marca B',
        'modelo' => 'Modelo B',
        'valor_por_fracao' => 40.00,
    ]);
    $idsVeiculos[] = [$veiculoB, $chave];

    $veiculoSemGrupo = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'id_matriz_filial' => $filialId,
        'placa' => 'FRA1C03',
        'marca' => 'Marca C',
        'modelo' => 'Modelo C',
        'valor_por_fracao' => 0.00,
    ]);
    $idsVeiculos[] = [$veiculoSemGrupo, $chave];

    $veiculoOutroTenant = Database::insertGetId('veiculos', [
        'chave' => $outraChave,
        'id_matriz_filial' => $outraFilialId,
        'placa' => 'FRA1X99',
        'marca' => 'Outra Marca',
        'modelo' => 'Outro Tenant',
        'valor_por_fracao' => 99.00,
    ]);
    $idsVeiculos[] = [$veiculoOutroTenant, $outraChave];

    $model = new Veiculo();
    $listados = $model->listarParaAjusteValorFracao($filialId);
    checkValorFracao('lista apenas os tres veiculos do tenant e filial atuais', count($listados) === 3);
    checkValorFracao(
        'listagem inclui veiculo sem grupo',
        count(array_filter($listados, static fn(array $item): bool => $item['id_grupo'] === null)) === 1
    );

    $atualizados = $model->atualizarValoresFracaoEmLote($filialId, [
        ['id' => $veiculoA, 'valor_original' => 30.00, 'novo_valor' => 33.00],
        ['id' => $veiculoSemGrupo, 'valor_original' => 0.00, 'novo_valor' => 12.50],
    ]);
    checkValorFracao('atualiza todos os itens validos do lote', count($atualizados) === 2);
    checkValorFracao('persiste valor percentual calculado', valorVeiculoFracao($veiculoA) === 33.0);
    checkValorFracao('permite definir valor para veiculo que estava zerado', valorVeiculoFracao($veiculoSemGrupo) === 12.5);

    $houveConflito = false;
    try {
        $model->atualizarValoresFracaoEmLote($filialId, [
            ['id' => $veiculoA, 'valor_original' => 30.00, 'novo_valor' => 50.00],
            ['id' => $veiculoB, 'valor_original' => 40.00, 'novo_valor' => 44.00],
        ]);
    } catch (DomainException) {
        $houveConflito = true;
    }
    checkValorFracao('detecta valor original desatualizado', $houveConflito);
    checkValorFracao('conflito faz rollback integral do lote', valorVeiculoFracao($veiculoB) === 40.0);

    $bloqueouOutroTenant = false;
    try {
        $model->atualizarValoresFracaoEmLote($filialId, [
            ['id' => $veiculoOutroTenant, 'valor_original' => 99.00, 'novo_valor' => 100.00],
        ]);
    } catch (DomainException) {
        $bloqueouOutroTenant = true;
    }
    checkValorFracao('ID de outro tenant nao pode ser atualizado', $bloqueouOutroTenant);
    checkValorFracao('valor do outro tenant permanece intacto', valorVeiculoFracao($veiculoOutroTenant) === 99.0);
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($idsVeiculos as [$id, $tenant]) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$id, $tenant]);
    }
    foreach ($idsGrupos as [$id, $tenant]) {
        Database::execute('DELETE FROM grupos WHERE id = ? AND chave = ?', [$id, $tenant]);
    }
    foreach ($idsFiliais as [$id, $tenant]) {
        Database::execute('DELETE FROM matrizes_filiais WHERE id = ? AND chave = ?', [$id, $tenant]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
