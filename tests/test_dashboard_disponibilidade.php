<?php

/**
 * Teste: resumo de disponibilidade do dashboard separa frota atual de reservas.
 *
 * Execute: php tests/test_dashboard_disponibilidade.php
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
use App\Models\Locacao;
use App\Models\Veiculo;

$chave = 'dash' . substr(bin2hex(random_bytes(6)), 0, 12);
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$veiculosCriados = [];
$manutencoesCriadas = [];
$locacoesCriadas = [];

function checkDashboardValor(string $label, mixed $atual, mixed $esperado): void
{
    global $falhas, $sucessos;

    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado={$esperado}, atual={$atual}\n";

    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function criarVeiculoDashboard(string $chave, string $status): int
{
    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => 'TD' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6),
        'marca' => 'Teste',
        'modelo' => 'Dashboard',
        'disponibilidade' => $status,
        'odometro' => '0',
    ]);
}

function criarLocacaoDashboard(string $chave, string $status): int
{
    return Database::insertGetId('locacoes', [
        'codigo' => 'LD' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 8),
        'chave' => $chave,
        'status' => $status,
        'data_saida' => '2026-06-24 08:00:00',
        'data_prevista' => '2026-06-25 08:00:00',
        'dias' => 1,
        'cliente_nome' => 'Cliente Dashboard',
    ]);
}

echo "=== Teste dashboard disponibilidade ===\n";

try {
    $veiculoDisponivel = criarVeiculoDashboard($chave, 'D');
    $veiculoLocado = criarVeiculoDashboard($chave, 'L');
    $veiculoLocadoManutencao = criarVeiculoDashboard($chave, 'L');
    $veiculoOficina = criarVeiculoDashboard($chave, 'O');
    $veiculoVendido = criarVeiculoDashboard($chave, 'V');
    $veiculoReservadoLegado = criarVeiculoDashboard($chave, 'R');
    $veiculosCriados = [
        $veiculoDisponivel,
        $veiculoLocado,
        $veiculoLocadoManutencao,
        $veiculoOficina,
        $veiculoVendido,
        $veiculoReservadoLegado,
    ];

    $manutencoesCriadas[] = Database::insertGetId('manutencoes', [
        'chave' => $chave,
        'os' => 'OD' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 8),
        'id_veiculo' => $veiculoLocadoManutencao,
        'status' => 'A',
        'data_enviado' => '2026-06-24 08:00:00',
    ]);
    $manutencoesCriadas[] = Database::insertGetId('manutencoes', [
        'chave' => $chave,
        'os' => 'OD' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 8),
        'id_veiculo' => $veiculoLocadoManutencao,
        'status' => 'A',
        'data_enviado' => '2026-06-24 09:00:00',
    ]);

    $locacoesCriadas[] = criarLocacaoDashboard($chave, 'R');
    $locacoesCriadas[] = criarLocacaoDashboard($chave, 'P');
    $locacoesCriadas[] = criarLocacaoDashboard($chave, 'A');

    $fleet = (new Veiculo())->dashboardSummary($chave);
    checkDashboardValor('total soma apenas Disponiveis/Locados/Oficina', $fleet['total'], 4);
    checkDashboardValor('disponiveis', $fleet['available'], 1);
    checkDashboardValor('locados exclui veiculo em manutencao aberta', $fleet['rented'], 1);
    checkDashboardValor('oficina inclui status O e manutencao aberta sem duplicar', $fleet['workshop'], 2);
    checkDashboardValor('reservados nao entram na barra', $fleet['reserved'], 0);
    checkDashboardValor('taxa de utilizacao usa locados sobre total atual', $fleet['utilization_rate'], 25.0);

    $operations = (new Locacao())->dashboardOperations($chave);
    checkDashboardValor('reservados contam locacoes R', $operations['reservations'], 1);
    checkDashboardValor('pendentes contam locacoes P', $operations['pending_reservations'], 1);
    checkDashboardValor('reservas pendentes mantem total de compatibilidade', $operations['reservations_pending'], 2);
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($manutencoesCriadas as $idManutencao) {
        Database::execute('DELETE FROM manutencoes WHERE id = ? AND chave = ?', [$idManutencao, $chave]);
    }
    foreach ($locacoesCriadas as $idLocacao) {
        Database::execute('DELETE FROM locacoes WHERE id = ? AND chave = ?', [$idLocacao, $chave]);
    }
    foreach ($veiculosCriados as $idVeiculo) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$idVeiculo, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
