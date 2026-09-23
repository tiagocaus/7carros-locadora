<?php
/** Regressao da busca de vinculos. Somente localhost; fixtures revertidas. */
require __DIR__ . '/../vendor/autoload.php';
$_ENV['APP_ENV'] = 'development';

use App\Core\Database;
use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\Promissoria;

if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Teste exige localhost');
}
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION = ['authenticated' => true, 'chave' => '1111111111111', 'filiais_permitidas' => []];
$db = Model::sharedMysqli();
$qb = new QueryBuilder($db);
$filiais = $qb->table('matrizes_filiais')->select(['id'])->limit(2)->get();
if (count($filiais) < 2) throw new RuntimeException('Teste exige duas filiais no tenant de teste');
[$filialA, $filialB] = array_column($filiais, 'id');
$falhas = 0;
function verificarVinculo(string $nome, bool $ok): void {
    global $falhas;
    echo ($ok ? 'PASS ' : 'FAIL ') . $nome . "\n";
    if (!$ok) $falhas++;
}
$prefixo = 'T' . bin2hex(random_bytes(3));
$db->begin_transaction();
try {
    $cliente = $qb->table('clientes')->insert([
        'nome_rsocial' => $prefixo . ' Cliente', 'foto' => '', 'data_cadastro' => '2026-09-23',
    ]);
    $base = ['id_cliente' => $cliente, 'id_matriz_filial_retirada' => $filialA, 'dias' => 1];
    $contrato = $base + ['data_ini' => '2026-09-23 10:00:00', 'data_fim' => '2026-09-24 10:00:00', 'contagem' => 'diaria'];
    $locacao = $base + ['data_saida' => '2026-09-23 10:00:00', 'data_prevista' => '2026-09-24 10:00:00', 'cliente_nome' => 'Teste'];
    foreach (['A', 'F'] as $status) {
        $qb->table('contratos')->insert($contrato + ['codigo' => $prefixo . 'C' . $status, 'status' => $status]);
    }
    foreach (['P', 'R', 'A', 'F'] as $status) {
        $qb->table('locacoes')->insert($locacao + ['codigo' => $prefixo . 'L' . $status, 'status' => $status]);
    }
    $model = new Promissoria();
    $buscar = fn(string $q) => array_column($model->buscarVinculosParaSelect($q), 'id');
    verificarVinculo('Todos os status e ambas as origens', count($buscar($prefixo)) === 6);
    verificarVinculo('Codigo parcial de reserva', $buscar($prefixo . 'LR') === [$prefixo . 'LR']);
    verificarVinculo('Busca por nome de cliente', count($buscar($prefixo . ' Cliente')) === 6);
    verificarVinculo('Sem resultados', $buscar($prefixo . 'inexistente') === []);

    $outroCliente = $qb->table('clientes')->insert([
        'nome_rsocial' => $prefixo . ' Outro', 'foto' => '', 'data_cadastro' => '2026-09-23',
    ]);
    $qb->table('locacoes')->insert(array_replace($locacao, ['id_cliente' => $outroCliente, 'codigo' => $prefixo . 'OUT', 'status' => 'R']));
    verificarVinculo('Busca inclui outros clientes', count($buscar($prefixo)) === 7);
    $qb->table('locacoes')->insert(array_replace($locacao, [
        'codigo' => $prefixo . 'DEV', 'status' => 'R',
        'id_matriz_filial_retirada' => $filialB, 'id_matriz_filial_devolucao' => $filialA,
    ]));
    $qb->table('contratos')->insert(array_replace($contrato, ['codigo' => $prefixo . 'NEG', 'status' => 'A', 'id_matriz_filial_retirada' => $filialB]));
    $qb->table('locacoes')->insert(array_replace($locacao, ['codigo' => $prefixo . 'NLG', 'status' => 'R', 'id_matriz_filial_retirada' => $filialB]));
    $_SESSION['filiais_permitidas'] = [$filialA];
    $ids = $buscar($prefixo);
    verificarVinculo('Locacao acessivel pela devolucao', in_array($prefixo . 'DEV', $ids, true));
    verificarVinculo('Retirada permitida', in_array($prefixo . 'CA', $ids, true) && in_array($prefixo . 'LR', $ids, true));
    verificarVinculo('Filiais nao permitidas excluidas', !in_array($prefixo . 'NEG', $ids, true) && !in_array($prefixo . 'NLG', $ids, true));
    $_SESSION['filiais_permitidas'] = [];

    for ($i = 0; $i < 30; $i++) {
        $qb->table('contratos')->insert($contrato + ['codigo' => $prefixo . 'X' . $i, 'status' => 'A']);
    }
    $ids = $buscar($prefixo);
    verificarVinculo('Limite independente: 25 contratos e 7 locacoes', count($ids) === 32);
    verificarVinculo('Locacoes nao ocultadas por contratos', in_array($prefixo . 'LR', $ids, true));
    verificarVinculo('Busca antes do limite', $buscar($prefixo . 'CA') === [$prefixo . 'CA']);
    verificarVinculo('Preload inclui reserva e limita a 50', in_array($prefixo . 'LR', $buscar(''), true) && count($buscar('')) <= 50);
    verificarVinculo('Ordem decrescente dentro da origem', $ids[0] === $prefixo . 'X29');

    $_SESSION['chave'] = 'teste-sem-registros';
    verificarVinculo('Isolamento de tenant mesmo reutilizando Model', $buscar($prefixo) === []);
} finally {
    $db->rollback();
    session_destroy();
}
exit($falhas ? 1 : 0);
