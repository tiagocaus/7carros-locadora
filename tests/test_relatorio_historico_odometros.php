<?php
/** Integração local com fixtures em transação, sempre revertida. */
require __DIR__ . '/../vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';
use App\Core\Database;
use App\Core\Request;
use App\Models\Relatorios\VeicularReport;
use App\Controllers\Relatorios\VeicularController;
use App\Helpers\PdfHelper;
if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Somente localhost.');
class OdometerControllerProbe extends VeicularController {
    public function filters(array $query): array { $_GET = $query; return $this->filtrosHistoricoOdometros(new Request()); }
}
function expectOdo(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$db = VeicularReport::sharedMysqli();
$db->begin_transaction();
$oldSession = $_SESSION ?? [];
$tenant = 'TEST_ODO_' . bin2hex(random_bytes(6));
$_SESSION['chave'] = $tenant;
function insertOdo(string $table, array $data): int {
    global $db;
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($data)) . '`) VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')';
    $stmt = $db->prepare($sql);
    $values = array_values($data);
    $stmt->bind_param(str_repeat('s', count($data)), ...$values);
    $stmt->execute();
    return $db->insert_id;
}
try {
    $model = new VeicularReport();
    $branch = insertOdo('matrizes_filiais', ['chave' => $tenant, 'nome_fantasia' => 'Filial Teste']);
    $branch2 = insertOdo('matrizes_filiais', ['chave' => $tenant, 'nome_fantasia' => 'Outra Filial']);
    $group = insertOdo('grupos', ['chave' => $tenant, 'nome' => 'Grupo Teste']);
    $client = insertOdo('clientes', ['chave' => $tenant, 'nome_rsocial' => 'Cliente Teste', 'foto' => '', 'data_cadastro' => '2020-01-01']);
    $vehicle = insertOdo('veiculos', ['chave' => $tenant, 'placa' => 'ODO0001', 'modelo' => 'Modelo Teste', 'diagrama' => 'carro', 'id_grupo' => $group]);
    $contract = insertOdo('contratos', ['chave' => $tenant, 'codigo' => 'ODO_TEST', 'sequencia' => 12345, 'id_cliente' => $client, 'id_matriz_filial_retirada' => $branch, 'data_ini' => '2020-01-01 08:00:00', 'data_fim' => '2026-12-31 08:00:00', 'contagem' => 'Mensal', 'dias' => 30, 'status' => 'F']);
    $link = insertOdo('contratos_veiculos', ['chave' => $tenant, 'id_contrato' => $contract, 'id_veiculo' => $vehicle, 'id_grupo' => $group, 'data_saida' => '2020-01-01 08:00:00', 'data_entrada' => '2026-12-31 08:00:00', 'plano' => 'KL']);
    for ($i = 0; $i < 55; $i++) {
        insertOdo('contratos_odometros', ['chave' => $tenant, 'id_contrato' => $contract, 'id_contrato_veiculo' => $link, 'data' => '2026-09-01', 'data_referencia' => $i === 0 ? null : '2026-09-01 10:' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . ':00', 'odometro' => 10000 + $i, 'obs' => $i === 0 ? null : str_repeat('Observação com acentuação & <teste>. ', 6)]);
    }
    insertOdo('contratos_odometros', ['chave' => $tenant, 'id_contrato' => $contract, 'id_contrato_veiculo' => $link, 'data' => '2020-01-01', 'odometro' => 100]);
    $all = $model->historicoOdometros([], '1=1', []);
    expectOdo(count($all['details']) === 50 && $all['totals'] === ['registros' => 56, 'veiculos' => 1, 'contratos' => 1], 'Todos os registros, totais e paginação');
    $page2 = $model->historicoOdometros([], '1=1', [], 2);
    expectOdo(count($page2['details']) === 6 && !array_intersect(array_column($all['details'], 'id'), array_column($page2['details'], 'id')), 'Segunda página sem duplicações');
    expectOdo((int)$all['details'][0]['odometro'] === 10054, 'Ordem das leituras no mesmo dia');
    foreach (['filial' => $branch, 'grupo' => $group, 'cliente' => $client, 'veiculo' => $vehicle, 'contrato' => 12345] as $key => $value) {
        expectOdo($model->historicoOdometros([$key => $value], '1=1', [])['totals']['registros'] === 56, "Filtro $key");
    }
    expectOdo($model->historicoOdometros(['filial' => $branch2], '1=1', [])['totals']['registros'] === 0, 'Filial sem dados');
    expectOdo($model->historicoOdometros([], 'c.id_matriz_filial_retirada IN (?)', [$branch2])['totals']['registros'] === 0, 'Restrição de filial sem filtros');
    expectOdo($model->historicoOdometros(['data_inicio' => '2026-09-01'], '1=1', [])['totals']['registros'] === 55, 'Somente data inicial');
    expectOdo($model->historicoOdometros(['data_fim' => '2020-01-01'], '1=1', [])['totals']['registros'] === 1, 'Somente data final e legado');
    $filtered = ['filial' => $branch, 'grupo' => $group, 'veiculo' => $vehicle, 'cliente' => $client, 'contrato' => 12345, 'data_inicio' => '2020-01-01', 'data_fim' => '2026-09-01'];
    expectOdo($model->historicoOdometros($filtered, '1=1', [])['totals']['registros'] === 56, 'Filtros combinados e intervalo acima de dois anos');
    $_SESSION['chave'] = $tenant . '_OUTRO';
    expectOdo($model->historicoOdometros($filtered, '1=1', [])['totals']['registros'] === 0, 'Isolamento de tenant mesmo com IDs conhecidos');
    $_SESSION['chave'] = $tenant;
    $controller = new OdometerControllerProbe();
    expectOdo($controller->filters([])['data_inicio'] === '', 'Datas opcionais no Controller');
    $controller->filters(['data_inicio' => '2020-01-01', 'data_fim' => '2026-09-01']);
    foreach ([['data_inicio' => '2026-02-30'], ['data_fim' => ['2026-01-01']], ['data_inicio' => '2026-02-01', 'data_fim' => '2026-01-01'], ['cliente' => '-1'], ['contrato' => '1 OR 1=1']] as $invalid) {
        $rejected = false;
        try { $controller->filters($invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
        expectOdo($rejected, 'Rejeita filtro inválido: ' . json_encode($invalid));
    }
    $export = $model->historicoOdometros([], '1=1', [], 1, 50, true);
    expectOdo(count($export['details']) === 56 && $export['totals'] === $all['totals'], 'Exportação completa igual à consulta');
    $details = $export['details']; $totals = $export['totals'];
    $dataInicio = ''; $dataFim = ''; $titulo = t('modules.relatorios.veicular.historico_odometros.title');
    $descricao = ''; $empresa = ['nome' => 'Empresa Teste', 'logo' => '']; $usuario = 'Teste';
    ob_start(); include APP_ROOT . '/app/Views/pages/relatorios/imprimir/veicular/historico-odometros.php'; $html = ob_get_clean();
    expectOdo(str_contains($html, 'Todo o histórico') && !str_contains($html, '<teste>'), 'Cabeçalho sem datas e escape HTML');
    $pdf = PdfHelper::generateAsString($html, ['orientation' => 'L']);
    expectOdo(str_starts_with($pdf, '%PDF-'), 'PDF gerado');
    file_put_contents(sys_get_temp_dir() . '/historico-odometros-qa.pdf', $pdf);
} finally {
    $db->rollback();
    $_SESSION = $oldSession;
}
