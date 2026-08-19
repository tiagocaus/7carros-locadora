<?php

/**
 * Teste: contratos usam o veiculo ativo mais recente e preservam o ultimo
 * veiculo do historico depois da finalizacao.
 *
 * Execute: php tests/test_contrato_veiculo_atual_ou_ultimo.php
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
use App\Models\Contrato;
use App\Models\ContratoVeiculo;

$chave = 'ctvu' . substr(bin2hex(random_bytes(8)), 0, 16);
$outraChave = 'ctvu' . substr(bin2hex(random_bytes(8)), 0, 16);
$sufixo = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
$_SESSION['chave'] = $chave;

$contratoId = 0;
$veiculoIds = [];
$vinculoIds = [];
$falhas = 0;

function validarVeiculoAtualContrato(bool $condicao, string $mensagem): void
{
    global $falhas;

    echo ($condicao ? 'PASS' : 'FAIL') . ": {$mensagem}\n";
    if (!$condicao) {
        $falhas++;
    }
}

try {
    foreach (['ANT', 'AT1', 'AT2'] as $indice => $prefixo) {
        $veiculoIds[] = Database::insertGetId('veiculos', [
            'chave' => $chave,
            'placa' => substr($prefixo . $sufixo, 0, 10),
            'marca' => 'Marca Teste',
            'modelo' => 'Modelo ' . ($indice + 1),
            'disponibilidade' => 'L',
        ]);
    }

    $contratoId = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => substr('CV' . $sufixo, 0, 15),
        'data_ini' => '2026-08-01 08:00:00',
        'data_fim' => '2026-08-10 08:00:00',
        'contagem' => 'dia',
        'dias' => 9,
        'status' => 'A',
    ]);

    $vinculos = [
        [$veiculoIds[0], '2026-08-01 08:00:00', '2026-08-02 08:00:00'],
        [$veiculoIds[1], '2026-08-02 08:00:00', null],
        [$veiculoIds[2], '2026-08-03 08:00:00', null],
    ];

    foreach ($vinculos as [$veiculoId, $dataSaida, $dataEntrada]) {
        $vinculoIds[] = Database::insertGetId('contratos_veiculos', [
            'chave' => $chave,
            'id_contrato' => $contratoId,
            'id_veiculo' => $veiculoId,
            'data_saida' => $dataSaida,
            'data_entrada' => $dataEntrada,
            'plano' => 'KL',
        ]);
    }

    $model = new ContratoVeiculo();
    $ativo = $model->buscarAtivo($contratoId);
    $atual = $model->buscarAtualOuUltimo($contratoId);

    validarVeiculoAtualContrato(
        (int) ($ativo['id_veiculo'] ?? 0) === $veiculoIds[2],
        'buscarAtivo retorna o veiculo ativo vinculado mais recentemente'
    );
    validarVeiculoAtualContrato(
        (int) ($atual['id_veiculo'] ?? 0) === $veiculoIds[2],
        'buscarAtualOuUltimo prioriza o veiculo ativo mais recente'
    );

    Database::execute(
        'UPDATE contratos_veiculos SET data_entrada = ? WHERE id_contrato = ? AND chave = ? AND data_entrada IS NULL',
        ['2026-08-10 08:00:00', $contratoId, $chave]
    );
    Database::execute(
        'UPDATE contratos SET status = ? WHERE id = ? AND chave = ?',
        ['F', $contratoId, $chave]
    );

    $finalizado = $model->buscarAtualOuUltimo($contratoId);
    validarVeiculoAtualContrato(
        (int) ($finalizado['id_veiculo'] ?? 0) === $veiculoIds[2],
        'contrato finalizado preserva o ultimo veiculo do historico'
    );

    $contratoCompleto = (new Contrato())->buscarCompleto($contratoId);
    validarVeiculoAtualContrato(
        ($contratoCompleto['veiculo_ativo'] ?? null) === null,
        'contrato finalizado permanece sem veiculo operacional ativo'
    );
    validarVeiculoAtualContrato(
        (int) ($contratoCompleto['veiculo_atual']['id_veiculo'] ?? 0) === $veiculoIds[2],
        'buscarCompleto expoe o ultimo veiculo em veiculo_atual'
    );

    $_SESSION['chave'] = $outraChave;
    validarVeiculoAtualContrato(
        $model->buscarAtualOuUltimo($contratoId) === null,
        'consulta normal nao atravessa o tenant da sessao'
    );
    validarVeiculoAtualContrato(
        (int) ($model->buscarAtualOuUltimo($contratoId, $chave)['id_veiculo'] ?? 0) === $veiculoIds[2],
        'consulta publica usa a chave explicita sem desabilitar o filtro de tenant'
    );
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    if ($contratoId > 0) {
        Database::execute('DELETE FROM contratos_veiculos WHERE id_contrato = ? AND chave = ?', [$contratoId, $chave]);
        Database::execute('DELETE FROM contratos WHERE id = ? AND chave = ?', [$contratoId, $chave]);
    }

    foreach ($veiculoIds as $veiculoId) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$veiculoId, $chave]);
    }
}

if ($falhas > 0) {
    throw new RuntimeException("Teste falhou com {$falhas} erro(s).");
}

echo "Selecao do veiculo atual ou ultimo do contrato validada.\n";
