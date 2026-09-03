<?php

/**
 * Regressao: preferencias de veiculo em reservas nao bloqueiam substituicoes.
 *
 * Execute: php tests/test_locacao_substituicao_ignora_reserva.php
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
use App\Models\LocacaoVeiculo;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$locacaoId = null;
$veiculoId = null;
$sufixo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
$codigo = 'L' . $sufixo;
$placa = 'T' . substr($sufixo, 0, 6);

$verificar = static function (string $rotulo, bool $condicao) use (&$falhas): void {
    echo ($condicao ? 'PASS' : 'FAIL') . " {$rotulo}\n";
    if (!$condicao) {
        $falhas++;
    }
};

echo "=== Teste substituicao ignora reserva ===\n";

try {
    $veiculoId = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => $placa,
        'marca' => 'Teste',
        'modelo' => 'Reserva',
        'disponibilidade' => 'D',
        'odometro' => '0',
    ]);

    $locacaoId = Database::insertGetId('locacoes', [
        'codigo' => $codigo,
        'chave' => $chave,
        'status' => 'R',
        'data_saida' => '2026-09-11 14:00:00',
        'data_prevista' => '2026-09-16 14:00:00',
        'dias' => 5,
        'cliente_nome' => 'Cliente Teste',
    ]);

    Database::insertGetId('locacoes_veiculos', [
        'id_locacao' => $locacaoId,
        'id_veiculo' => $veiculoId,
        'data_saida' => '2026-09-11 14:00:00',
        'plano' => 'KL',
        'chave' => $chave,
    ]);

    $model = new LocacaoVeiculo();

    $verificar(
        'reserva confirmada nao bloqueia o veiculo',
        $model->veiculoEstaLocado($veiculoId) === null
    );

    Database::execute('UPDATE locacoes SET status = ? WHERE id = ? AND chave = ?', ['P', $locacaoId, $chave]);
    $verificar(
        'reserva pendente nao bloqueia o veiculo',
        $model->veiculoEstaLocado($veiculoId) === null
    );

    Database::execute('UPDATE locacoes SET status = ? WHERE id = ? AND chave = ?', ['A', $locacaoId, $chave]);
    $conflito = $model->veiculoEstaLocado($veiculoId);
    $verificar(
        'locacao aberta bloqueia e informa o codigo conflitante',
        ($conflito['locacao_codigo'] ?? null) === $codigo
    );
    $verificar(
        'a propria locacao pode ser excluida da verificacao',
        $model->veiculoEstaLocado($veiculoId, $locacaoId) === null
    );

    Database::execute('UPDATE locacoes SET status = ? WHERE id = ? AND chave = ?', ['F', $locacaoId, $chave]);
    $verificar(
        'locacao fechada nao bloqueia mesmo com vinculo historico aberto',
        $model->veiculoEstaLocado($veiculoId) === null
    );
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    if ($locacaoId !== null) {
        Database::execute('DELETE FROM locacoes_veiculos WHERE id_locacao = ?', [$locacaoId]);
        Database::execute('DELETE FROM locacoes WHERE id = ? AND chave = ?', [$locacaoId, $chave]);
    }
    if ($veiculoId !== null) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$veiculoId, $chave]);
    }
}

echo "\nFalhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
