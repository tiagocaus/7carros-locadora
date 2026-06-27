<?php

/**
 * Teste: disponibilidade do site desconta reservas por grupo sem veiculo.
 *
 * Execute: php tests/test_website_disponibilidade_reserva_grupo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Veiculo;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;

$filialId = 14;
$periodoIni = '2031-01-10 10:00:00';
$periodoFim = '2031-01-12 10:00:00';

$falhas = 0;
$sucessos = 0;

function checkReservaGrupo(string $label, $atual, $esperado): void
{
    global $falhas, $sucessos;
    $ok = ($atual === $esperado);
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado=" . var_export($esperado, true) . ', atual=' . var_export($atual, true) . PHP_EOL;
    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

echo "=== Teste disponibilidade por reserva de grupo ===\n";

$veiculoModel = new Veiculo();
$baseline = $veiculoModel->gruposDisponiveisPorFilial($filialId, $periodoIni, $periodoFim);
echo 'Baseline: ' . json_encode($baseline) . PHP_EOL;

$grupoId = null;
$qtdLivre = 0;
foreach ($baseline as $idGrupo => $qtd) {
    if ($qtd > 0) {
        $grupoId = (int) $idGrupo;
        $qtdLivre = (int) $qtd;
        break;
    }
}

if (!$grupoId) {
    echo "Nenhum grupo disponivel para montar o teste.\n";
    exit(1);
}

$db = Database::getConnection();
$db->beginTransaction();

try {
    Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => 'TOF' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 4),
        'marca' => 'Teste',
        'modelo' => 'Oficina',
        'id_grupo' => $grupoId,
        'id_matriz_filial' => $filialId,
        'disponibilidade' => 'O',
        'odometro' => 0,
    ]);

    $comOficina = $veiculoModel->gruposDisponiveisPorFilial($filialId, $periodoIni, $periodoFim);
    checkReservaGrupo('veiculo em oficina nao aumenta disponibilidade do grupo', $comOficina[$grupoId] ?? 0, $qtdLivre);

    $stmtLoc = $db->prepare("
        INSERT INTO locacoes (codigo, chave, status, data_saida, data_prevista, dias, cliente_nome, created_at)
        VALUES (?, ?, ?, ?, ?, 2, 'TEST RESERVA GRUPO', NOW())
    ");
    $stmtLV = $db->prepare("
        INSERT INTO locacoes_veiculos (chave, id_locacao, id_veiculo, id_grupo, plano, data_saida, data_entrada, created_at)
        VALUES (?, ?, NULL, ?, 'KML', ?, NULL, NOW())
    ");

    for ($i = 0; $i < $qtdLivre; $i++) {
        $status = $i === 0 ? 'P' : 'R';
        $stmtLoc->execute(['TG' . substr(uniqid(), -6), $chave, $status, $periodoIni, $periodoFim]);
        $locacaoId = (int) $db->lastInsertId();
        $stmtLV->execute([$chave, $locacaoId, $grupoId, $periodoIni]);
    }

    $comReservas = $veiculoModel->gruposDisponiveisPorFilial($filialId, $periodoIni, $periodoFim);
    echo 'Com reservas por grupo: ' . json_encode($comReservas) . PHP_EOL;
    checkReservaGrupo('grupo escolhido fica esgotado por reservas sem veiculo', $comReservas[$grupoId] ?? 0, 0);

    $foraPeriodo = $veiculoModel->gruposDisponiveisPorFilial($filialId, '2031-02-01 10:00:00', '2031-02-02 10:00:00');
    checkReservaGrupo('fora do periodo nao reduz disponibilidade', $foraPeriodo[$grupoId] ?? 0, $qtdLivre);
} finally {
    $db->rollBack();
}

$aposRollback = $veiculoModel->gruposDisponiveisPorFilial($filialId, $periodoIni, $periodoFim);
checkReservaGrupo('rollback restaura disponibilidade original', $aposRollback[$grupoId] ?? 0, $qtdLivre);

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
