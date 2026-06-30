<?php

/**
 * Teste: cron de manutencao preventiva deve criar itens normalizados na OS.
 *
 * Execute: php tests/test_cron_manutencao_preventiva_itens.php
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

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Crons\Jobs\CheckPreventiveMaintenanceJob;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$veiculosCriados = [];
$manutencoesCriadas = [];

function checkPreventivaItens(string $label, bool $ok, mixed $atual = null): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}";
    if ($atual !== null) {
        echo " - atual={$atual}";
    }
    echo "\n";

    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function placaTestePreventiva(): string
{
    return 'TP' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6);
}

echo "=== Teste cron manutencao preventiva cria itens ===\n";

try {
    $mysqli = new mysqli(
        Database::env('DB_HOST'),
        Database::env('DB_USERNAME'),
        Database::env('DB_PASSWORD'),
        Database::env('DB_DATABASE'),
        (int) Database::env('DB_PORT', '3306')
    );
    $mysqli->set_charset('utf8mb4');
    $qb = new QueryBuilder($mysqli);

    $veiculoId = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => placaTestePreventiva(),
        'marca' => 'Teste',
        'modelo' => 'Preventiva',
        'disponibilidade' => 'D',
        'odometro' => '9500',
        'plano_manutencao_array' => json_encode([
            'motor_oleo' => '10.000',
            'motor_filtrooleo' => '10.000',
        ]),
    ]);
    $veiculosCriados[] = $veiculoId;

    $veiculo = [
        'id' => $veiculoId,
        'chave' => $chave,
        'id_matriz_filial' => null,
        'placa' => Database::fetchColumn('SELECT placa FROM veiculos WHERE id = ? AND chave = ?', [$veiculoId, $chave]),
        'odometro' => '9500',
        'plano_manutencao_array' => json_encode([
            'motor_oleo' => '10.000',
            'motor_filtrooleo' => '10.000',
        ]),
    ];

    $itensPendentes = [
        'motor_oleo' => [
            'km_proxima' => 10000,
            'km_atual' => 9500,
            'diferenca' => 500,
            'intervalo' => 10000,
            'label' => 'Oleo do motor',
        ],
        'motor_filtrooleo' => [
            'km_proxima' => 10000,
            'km_atual' => 9500,
            'diferenca' => 500,
            'intervalo' => 10000,
            'label' => 'Filtro de oleo',
        ],
    ];

    $job = new CheckPreventiveMaintenanceJob();

    $gerarOS = new ReflectionMethod($job, 'gerarOS');
    $gerarOS->setAccessible(true);
    $atualizarPlano = new ReflectionMethod($job, 'atualizarPlanoVeiculo');
    $atualizarPlano->setAccessible(true);

    $mysqli->begin_transaction();
    $osInfo = $gerarOS->invoke($job, $qb, $veiculo, $itensPendentes);
    $atualizarPlano->invoke($job, $qb, $veiculo, $itensPendentes, [
        'motor_oleo' => '10.000',
        'motor_filtrooleo' => '10.000',
    ]);
    $mysqli->commit();

    $manutencoesCriadas[] = (int) $osInfo['id'];

    $qtdItens = (int) Database::fetchColumn(
        'SELECT COUNT(*) FROM manutencoes_itens WHERE id_manutencao = ? AND chave = ?',
        [$osInfo['id'], $chave]
    );
    checkPreventivaItens('OS preventiva possui itens normalizados', $qtdItens === 2, $qtdItens);

    $status = Database::fetchColumn(
        'SELECT status FROM manutencoes WHERE id = ? AND chave = ?',
        [$osInfo['id'], $chave]
    );
    checkPreventivaItens('OS preventiva nasce criada', $status === 'C', $status);

    $totalServicos = (float) Database::fetchColumn(
        'SELECT total_servicos FROM manutencoes WHERE id = ? AND chave = ?',
        [$osInfo['id'], $chave]
    );
    checkPreventivaItens('totais permanecem coerentes para itens sem valor', abs($totalServicos - 0.0) < 0.0001, $totalServicos);

    $planoAtualizado = json_decode((string) Database::fetchColumn(
        'SELECT plano_manutencao_array FROM veiculos WHERE id = ? AND chave = ?',
        [$veiculoId, $chave]
    ), true);
    checkPreventivaItens('proxima manutencao do oleo avancou', ($planoAtualizado['motor_oleo'] ?? null) === '20.000', $planoAtualizado['motor_oleo'] ?? null);
    checkPreventivaItens('proxima manutencao do filtro avancou', ($planoAtualizado['motor_filtrooleo'] ?? null) === '20.000', $planoAtualizado['motor_filtrooleo'] ?? null);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->errno === 0) {
        try {
            $mysqli->rollback();
        } catch (Throwable) {
        }
    }

    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($manutencoesCriadas as $idManutencao) {
        Database::execute('DELETE FROM manutencoes WHERE id = ? AND chave = ?', [$idManutencao, $chave]);
    }
    foreach ($veiculosCriados as $idVeiculo) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$idVeiculo, $chave]);
    }
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
