<?php
/** Integração local: php tests/test_relatorio_disponibilidade.php (fixtures com rollback). */
require_once __DIR__ . '/../vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';
$_ENV['APP_ENV'] = 'development';
session_save_path(sys_get_temp_dir());
session_start();

use App\Models\Model;
use App\Models\Relatorios\VeicularReport;
use App\Controllers\Relatorios\VeicularController;

function checkDisponibilidade(string $label, mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
    echo "PASS: $label\n";
}

$db = Model::sharedMysqli();
$db->begin_transaction();
try {
    $chave = 'TEST_DISP_' . bin2hex(random_bytes(6));
    $_SESSION['chave'] = $chave;
    $insertFilial = $db->prepare("INSERT INTO matrizes_filiais (chave, tipo, nome_fantasia) VALUES (?, 'F', 'Teste disponibilidade')");
    $insertFilial->bind_param('s', $chave);
    $insertFilial->execute();
    $filial = $db->insert_id;
    $insertFilial->execute();
    $outraFilial = $db->insert_id;
    $insertGrupo = $db->prepare("INSERT INTO grupos (chave, nome) VALUES (?, 'Teste disponibilidade')");
    $insertGrupo->bind_param('s', $chave);
    $insertGrupo->execute();
    $grupo = $db->insert_id;
    $insertGrupo->execute();
    $outroGrupo = $db->insert_id;
    $insert = $db->prepare('INSERT INTO veiculos (chave, disponibilidade, id_matriz_filial, id_grupo, placa) VALUES (?, ?, ?, ?, ?)');
    $tenant = $chave;
    $branch = $filial;
    $group = $grupo;
    $insert->bind_param('ssiis', $tenant, $status, $branch, $group, $placa);
    foreach (array_keys(VeicularReport::opcoesDisponibilidade()) as $status) {
        $placa = 'TEST-' . $status;
        $insert->execute();
    }
    $status = 'D'; $placa = 'OUTRA-F'; $branch = $outraFilial; $group = $outroGrupo;
    $insert->execute();
    $tenant = $chave . '_OUTRO'; $placa = 'OUTRO-T';
    $insert->execute();

    $model = new VeicularReport();
    $all = $model->disponibilidade('', []);
    checkDisponibilidade('padrão inclui todos e isola tenant', $all['totals']['total_frota'], 10);
    $selected = $model->disponibilidade('', [], '', '', ['D', 'L']);
    checkDisponibilidade('múltiplas disponibilidades', count($selected['details']), 3);
    checkDisponibilidade('total e gráfico coerentes', array_sum($selected['chart']['datasets'][0]['data']), 3);
    checkDisponibilidade('taxa sobre conjunto filtrado', $selected['totals']['taxa_ocupacao_atual'], 33.33);
    checkDisponibilidade('uma disponibilidade', $model->disponibilidade('', [], '', '', ['RO'])['details'][0]['status_label'], 'Roubado');
    checkDisponibilidade('uso interno', $model->disponibilidade('', [], '', '', ['UI'])['details'][0]['status_label'], 'Uso interno');
    $excluded = $model->disponibilidade('', [], '', '', ['E']);
    checkDisponibilidade('excluído', $excluded['details'][0]['status_label'], 'Excluído');
    checkDisponibilidade('excluído não integra oficina', $excluded['totals']['oficina'], 0);
    checkDisponibilidade('oficina somente O', $all['totals']['oficina'], 1);
    checkDisponibilidade('todas as opções', $model->disponibilidade('', [], '', '', array_keys(VeicularReport::opcoesDisponibilidade()))['totals'], $all['totals']);
    checkDisponibilidade('filial e grupo', $model->disponibilidade('', [], (string)$filial, (string)$grupo, ['D', 'L'])['totals']['total_frota'], 2);
    checkDisponibilidade('restrição de filial', $model->disponibilidade('id_matriz_filial IN (?)', [$filial], '', '', ['D'])['totals']['total_frota'], 1);
    $empty = $model->disponibilidade('id_matriz_filial IN (?)', [$filial], (string)$outraFilial, '', ['D']);
    checkDisponibilidade('filial fora da permissão não retorna veículos', $empty['details'], []);
    checkDisponibilidade('vazio sem divisão por zero', $empty['totals']['taxa_ocupacao_atual'], 0.0);

    $controller = new VeicularController();
    $parse = new ReflectionMethod($controller, 'parseDisponibilidades');
    checkDisponibilidade('filtro vazio', $parse->invoke($controller, ''), []);
    checkDisponibilidade('normaliza espaços e duplicatas', $parse->invoke($controller, 'D, L,D'), ['D', 'L']);
    foreach (['INVALIDO', 'D,INVALIDO', 'D,', ['D'], [['D']]] as $value) {
        try { $parse->invoke($controller, $value); throw new RuntimeException('Aceitou filtro inválido'); }
        catch (InvalidArgumentException) { echo "PASS: rejeita filtro inválido\n"; }
    }

    $totals = $selected['totals']; $details = $selected['details'];
    $disponibilidadesSelecionadas = ['D', 'L'];
    $disponibilidadeOptions = VeicularReport::opcoesDisponibilidade();
    $empresa = ['nome' => 'Teste disponibilidade']; $usuario = 'Teste';
    $titulo = 'Disponibilidade da Frota'; $descricao = 'Validação local';
    $dataInicio = $dataFim = '2026-09-15';
    ob_start();
    include APP_ROOT . '/app/Views/pages/relatorios/imprimir/veicular/disponibilidade.php';
    $html = ob_get_clean();
    checkDisponibilidade('PDF identifica seleção', str_contains($html, 'Disponível, Locado'), true);
    checkDisponibilidade('PDF não inclui outros status', str_contains($html, 'TEST-RO'), false);
    if (getenv('DISP_PDF_PATH')) {
        App\Helpers\PdfHelper::saveToFile($html, getenv('DISP_PDF_PATH'));
        echo "PASS: PDF gerado\n";
    }
} finally {
    $db->rollback();
    session_destroy();
}
