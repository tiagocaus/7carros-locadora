<?php

/**
 * Teste: manutencao aberta deve bloquear disponibilidade do veiculo como oficina.
 *
 * Execute: php tests/test_manutencao_disponibilidade.php
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
use App\Models\Manutencao;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$veiculosCriados = [];
$manutencoesCriadas = [];

function checkManutencaoDisponibilidade(string $label, int $veiculoId, string $esperado): void
{
    global $falhas, $sucessos, $chave;

    $atual = Database::fetchColumn(
        'SELECT disponibilidade FROM veiculos WHERE id = ? AND chave = ?',
        [$veiculoId, $chave]
    );
    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado={$esperado}, atual={$atual}\n";

    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function placaTesteManutencao(string $prefixo): string
{
    return $prefixo . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6);
}

function criarVeiculoManutencao(string $chave, string $status = 'D'): int
{
    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => placaTesteManutencao('TM'),
        'marca' => 'Teste',
        'modelo' => 'Manutencao',
        'disponibilidade' => $status,
        'odometro' => '1000',
        'tanque_fracao' => '8',
    ]);
}

echo "=== Teste disponibilidade em manutencoes ===\n";

try {
    $model = new Manutencao();

    $veiculoAbertura = criarVeiculoManutencao($chave);
    $veiculosCriados[] = $veiculoAbertura;
    $manutencaoAbertura = $model->criar([
        'chave' => $chave,
        'os' => placaTesteManutencao('OS'),
        'id_veiculo' => $veiculoAbertura,
        'status' => 'C',
    ]);
    $manutencoesCriadas[] = $manutencaoAbertura;

    $resultado = $model->mudarStatus($manutencaoAbertura, 'A', ['odometro' => 1000, 'tanque' => 8]);
    if (!$resultado['success']) {
        throw new RuntimeException($resultado['message']);
    }
    checkManutencaoDisponibilidade('abrir manutencao marca veiculo como oficina', $veiculoAbertura, 'O');

    Database::execute(
        'UPDATE manutencoes SET data_retorno = ?, odo_retorno = ?, tanque_retorno = ? WHERE id = ? AND chave = ?',
        ['2026-06-02 08:00:00', 1100, 8, $manutencaoAbertura, $chave]
    );
    $model->fechar($manutencaoAbertura);
    checkManutencaoDisponibilidade('fechar ultima manutencao libera veiculo', $veiculoAbertura, 'D');

    $veiculoMultiplas = criarVeiculoManutencao($chave);
    $veiculosCriados[] = $veiculoMultiplas;
    $manutencaoUm = $model->criar([
        'chave' => $chave,
        'os' => placaTesteManutencao('OS'),
        'id_veiculo' => $veiculoMultiplas,
        'status' => 'C',
    ]);
    $manutencaoDois = $model->criar([
        'chave' => $chave,
        'os' => placaTesteManutencao('OS'),
        'id_veiculo' => $veiculoMultiplas,
        'status' => 'C',
    ]);
    $manutencoesCriadas[] = $manutencaoUm;
    $manutencoesCriadas[] = $manutencaoDois;

    $resultado = $model->mudarStatus($manutencaoUm, 'A');
    if (!$resultado['success']) {
        throw new RuntimeException($resultado['message']);
    }
    Database::execute('UPDATE veiculos SET disponibilidade = ? WHERE id = ? AND chave = ?', ['D', $veiculoMultiplas, $chave]);
    $resultado = $model->mudarStatus($manutencaoDois, 'A');
    if (!$resultado['success']) {
        throw new RuntimeException($resultado['message']);
    }

    Database::execute(
        'UPDATE manutencoes SET data_retorno = ?, odo_retorno = ?, tanque_retorno = ? WHERE id = ? AND chave = ?',
        ['2026-06-03 08:00:00', 1200, 8, $manutencaoUm, $chave]
    );
    $model->fechar($manutencaoUm);
    checkManutencaoDisponibilidade('fechar uma OS mantem oficina quando ha outra aberta', $veiculoMultiplas, 'O');
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($manutencoesCriadas as $idManutencao) {
        Database::execute('DELETE FROM manutencoes WHERE id = ? AND chave = ?', [$idManutencao, $chave]);
    }
    foreach ($veiculosCriados as $idVeiculo) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$idVeiculo, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
