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
        INSERT INTO locacoes (
            codigo, chave, id_matriz_filial_retirada, status,
            data_saida, data_prevista, dias, cliente_nome, created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, 2, 'TEST RESERVA GRUPO', NOW())
    ");
    $stmtLV = $db->prepare("
        INSERT INTO locacoes_veiculos (chave, id_locacao, id_veiculo, id_grupo, plano, data_saida, data_entrada, created_at)
        VALUES (?, ?, NULL, ?, 'KML', ?, NULL, NOW())
    ");

    $stmtLoc->execute(['TO' . substr(uniqid(), -6), $chave, 13, 'R', $periodoIni, $periodoFim]);
    $locacaoOutraFilialId = (int) $db->lastInsertId();
    $stmtLV->execute([$chave, $locacaoOutraFilialId, $grupoId, $periodoIni]);

    $comReservaOutraFilial = $veiculoModel->gruposDisponiveisPorFilial($filialId, $periodoIni, $periodoFim);
    checkReservaGrupo('reserva por grupo de outra filial nao reduz disponibilidade', $comReservaOutraFilial[$grupoId] ?? 0, $qtdLivre);

    for ($i = 0; $i < $qtdLivre; $i++) {
        $status = $i === 0 ? 'P' : 'R';
        $stmtLoc->execute(['TG' . substr(uniqid(), -6), $chave, $filialId, $status, $periodoIni, $periodoFim]);
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

$script = <<<'PHP'
<?php
if (!defined('APP_ROOT')) define('APP_ROOT', '__APP_ROOT__');
require_once APP_ROOT . '/vendor/autoload.php';

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Core\Request;
use App\Controllers\PublicWebsiteController;
use App\Models\Veiculo;

$chave = '1111111111111';
$filialId = 14;
$periodoIni = '2031-03-10 10:00:00';
$periodoFim = '2031-03-12 10:00:00';
$_SESSION['chave'] = $chave;

$veiculoModel = new Veiculo();
$baseline = $veiculoModel->gruposDisponiveisPorFilial($filialId, $periodoIni, $periodoFim);
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
    echo json_encode(['success' => false, 'message' => 'Sem grupo disponivel para teste']);
    exit(2);
}

$db = Database::getConnection();
$configOriginal = Database::fetchOne(
    "SELECT status, overbooking FROM site_config WHERE chave=?",
    [$chave]
);
$locacoesCriadas = [];
register_shutdown_function(static function () use (&$locacoesCriadas, $chave, $configOriginal): void {
    if ($locacoesCriadas) {
        $placeholders = implode(',', array_fill(0, count($locacoesCriadas), '?'));
        Database::execute(
            "DELETE FROM locacoes_veiculos WHERE chave=? AND id_locacao IN ({$placeholders})",
            array_merge([$chave], $locacoesCriadas)
        );
        Database::execute(
            "DELETE FROM locacoes WHERE chave=? AND id IN ({$placeholders})",
            array_merge([$chave], $locacoesCriadas)
        );
    }
    if ($configOriginal) {
        Database::execute(
            "UPDATE site_config SET status=?, overbooking=? WHERE chave=?",
            [$configOriginal['status'], $configOriginal['overbooking'], $chave]
        );
    }
});

Database::execute(
    "UPDATE site_config SET status='ativo', overbooking=0 WHERE chave=?",
    [$chave]
);

$stmtLoc = $db->prepare("
    INSERT INTO locacoes (
        codigo, chave, id_matriz_filial_retirada, status,
        data_saida, data_prevista, dias, cliente_nome, created_at
    )
    VALUES (?, ?, ?, 'R', ?, ?, 2, 'TEST RESERVA API', NOW())
");
$stmtLV = $db->prepare("
    INSERT INTO locacoes_veiculos (chave, id_locacao, id_veiculo, id_grupo, plano, data_saida, data_entrada, created_at)
    VALUES (?, ?, NULL, ?, 'KML', ?, NULL, NOW())
");

for ($i = 0; $i < $qtdLivre; $i++) {
    $stmtLoc->execute(['TA' . substr(uniqid(), -6), $chave, $filialId, $periodoIni, $periodoFim]);
    $locacaoId = (int) $db->lastInsertId();
    $locacoesCriadas[] = $locacaoId;
    $stmtLV->execute([$chave, $locacaoId, $grupoId, $periodoIni]);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'chave' => $chave,
    'filial_retirada_id' => $filialId,
    'filial_devolucao_id' => $filialId,
    'data_saida' => '2031-03-10',
    'hora_saida' => '10:00',
    'data_chegada' => '2031-03-12',
    'hora_chegada' => '10:00',
    'grupo_id' => $grupoId,
    'plano' => 'KML',
];

(new PublicWebsiteController())->criarReserva(new Request());
PHP;
$script = str_replace('__APP_ROOT__', dirname(__DIR__), $script);

$tmp = tempnam(sys_get_temp_dir(), 'teste_reserva_site_');
file_put_contents($tmp, $script);
$saidaApi = shell_exec(PHP_BINARY . ' ' . escapeshellarg($tmp) . ' 2>&1');
unlink($tmp);
$jsonApi = json_decode((string) $saidaApi, true);
checkReservaGrupo('POST publico bloqueia grupo esgotado sem overbooking', $jsonApi['message'] ?? null, 'Grupo esgotado para o período selecionado.');

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
