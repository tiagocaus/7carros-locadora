<?php

require_once __DIR__ . '/../vendor/autoload.php';
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Models\Model;
use App\Models\MatrizFilial;
use App\Models\Orcamento;
use App\Helpers\PdfHelper;
use App\Controllers\OrcamentosController;

$db = Model::sharedMysqli();
$fixture = $db->query(<<<'SQL'
SELECT mf.chave, mf.id AS filial_id, c.id AS cliente_id, c.nome_rsocial AS cliente_nome,
       g.id AS grupo_id, g.nome AS grupo_nome,
       (SELECT cb.id FROM contas_bancarias cb WHERE cb.chave = mf.chave ORDER BY cb.id LIMIT 1) AS conta_id,
       (SELECT fp.id FROM formas_pagamento fp WHERE fp.chave = mf.chave ORDER BY fp.id LIMIT 1) AS forma_id,
       (SELECT f.id FROM funcionarios f WHERE f.chave = mf.chave ORDER BY f.id LIMIT 1) AS funcionario_id
FROM matrizes_filiais mf
JOIN clientes c ON c.chave = mf.chave
JOIN grupos g ON g.chave = mf.chave
WHERE mf.status = 'A'
HAVING conta_id IS NOT NULL AND forma_id IS NOT NULL
LIMIT 1
SQL)->fetch_assoc();

if (!$fixture) {
    echo "SKIP: fixture local insuficiente para orçamentos.\n";
    exit(0);
}

$failures = 0;
function checkQuote(bool $condition, string $message): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $failures++;
}

$_SESSION['chave'] = $fixture['chave'];
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = (int) $fixture['funcionario_id'];
$_SESSION['filiais_permitidas'] = [];
$fixtureChave = (string) $fixture['chave'];
$orcamentoTesteId = null;
$locacaoTesteId = null;
$erro = null;
try {
    $calcular = new ReflectionMethod(OrcamentosController::class, 'validarECalcular');
    $calculado = $calcular->invoke(new OrcamentosController(), [
        'id_cliente' => (int) $fixture['cliente_id'],
        'id_matriz_filial_retirada' => (int) $fixture['filial_id'],
        'id_matriz_filial_devolucao' => (int) $fixture['filial_id'],
        'data_saida' => date('Y-m-d 09:00:00', strtotime('+7 days')),
        'data_prevista' => date('Y-m-d 09:00:00', strtotime('+10 days')),
        'id_grupo' => (int) $fixture['grupo_id'],
        'plano' => 'KL',
        'diaria_valor_origem' => 'manual',
        'diaria_valor' => '100,00',
        'seguro_carro' => 'S',
        'valor_seguro_carro' => '10,00',
        'valor_desconto' => '20,00',
    ]);
    checkQuote($calculado['dias'] === 3 && (float) $calculado['total_pagar'] === 310.0, 'calcula diárias, proteção e desconto no servidor');

    $model = new Orcamento();
    $id = $model->criar([
        'chave' => $fixture['chave'],
        'status' => 'A',
        'validade' => date('Y-m-d', strtotime('+3 days')),
        'id_cliente' => (int) $fixture['cliente_id'],
        'cliente_nome' => $fixture['cliente_nome'],
        'id_matriz_filial_retirada' => (int) $fixture['filial_id'],
        'id_matriz_filial_devolucao' => (int) $fixture['filial_id'],
        'id_funcionario' => (int) $fixture['funcionario_id'],
        'data_saida' => date('Y-m-d 09:00:00', strtotime('+7 days')),
        'data_prevista' => date('Y-m-d 09:00:00', strtotime('+10 days')),
        'dias' => 3,
        'id_grupo' => (int) $fixture['grupo_id'],
        'grupo_nome' => $fixture['grupo_nome'],
        'plano' => 'KL',
        'diaria_valor' => 100,
        'id_conta' => (int) $fixture['conta_id'],
        'id_forma_pagamento' => (int) $fixture['forma_id'],
        'valor_desconto' => 20,
        'taxas' => [[
            'id_taxa' => null, 'nome' => 'Taxa snapshot', 'base_calculo' => 'FIX',
            'tipo_valor' => 'MON', 'quantidade' => 1, 'valor_unitario' => 30, 'valor_total' => 30,
        ]],
        'subtotal_diarias' => 300,
        'subtotal_adicionais' => 30,
        'total_fatura' => 330,
        'total_pagar' => 310,
    ]);
    $orcamentoTesteId = $id;

    $saved = $model->buscarPorId($id);
    checkQuote($saved !== null && str_starts_with($saved['codigo'], 'O'), 'cria orçamento com código próprio');
    checkQuote(is_array($saved['taxas']) && ($saved['taxas'][0]['nome'] ?? '') === 'Taxa snapshot', 'preserva snapshot de taxas');
    checkQuote((float) $saved['total_pagar'] === 310.0, 'preserva os totais apresentados');

    $orcamento = $saved;
    $empresa = (new MatrizFilial())->buscarPorId((int) $fixture['filial_id']);
    $logoPath = APP_ROOT . '/public/assets/img/logo_padrao.png';
    ob_start();
    include APP_ROOT . '/app/Views/pages/orcamentos/imprimir.php';
    $htmlPdf = (string) ob_get_clean();
    checkQuote(str_contains($htmlPdf, 'class="logo-img"'), 'inclui a logo resolvida no cabeçalho do orçamento');
    checkQuote(str_contains($htmlPdf, 'class="header-title"><h1>ORÇAMENTO</h1>'), 'centraliza o título na célula central do cabeçalho');
    $pdf = PdfHelper::generateAsString($htmlPdf);
    checkQuote(str_starts_with($pdf, '%PDF-'), 'gera PDF pelo PdfHelper com output buffering');

    $logoPath = '';
    ob_start();
    include APP_ROOT . '/app/Views/pages/orcamentos/imprimir.php';
    $htmlSemLogo = (string) ob_get_clean();
    checkQuote(!str_contains($htmlSemLogo, 'class="logo-img"'), 'usa fallback textual quando a filial não possui logo');

    $_SESSION['chave'] = 'TENANT_QUE_NAO_EXISTE_TESTE_ORCAMENTO';
    checkQuote((new Orcamento())->buscarPorId($id) === null, 'isola consulta por tenant sem withoutChave');
    $_SESSION['chave'] = $fixture['chave'];

    $locacaoId = $model->converterEmReserva($id, (int) $fixture['funcionario_id']);
    $locacaoTesteId = $locacaoId;
    $converted = $model->buscarPorId($id);
    checkQuote($locacaoId > 0 && (int) $converted['id_locacao_convertida'] === $locacaoId, 'converte e vincula uma reserva');
    checkQuote($converted['status'] === 'C', 'marca orçamento como convertido');

    $locacao = $db->query('SELECT status,total_pagar FROM locacoes WHERE id=' . (int) $locacaoId)->fetch_assoc();
    checkQuote(($locacao['status'] ?? '') === 'R' && (float) ($locacao['total_pagar'] ?? 0) === 310.0, 'reserva recebe status e snapshot total');

    try {
        $model->converterEmReserva($id, (int) $fixture['funcionario_id']);
        checkQuote(false, 'impede conversão duplicada');
    } catch (InvalidArgumentException) {
        checkQuote(true, 'impede conversão duplicada');
    }

} catch (Throwable $e) {
    $erro = $e;
} finally {
    if ($orcamentoTesteId !== null) {
        $stmt = $db->prepare('DELETE FROM orcamentos WHERE id = ? AND chave = ?');
        $stmt->bind_param('is', $orcamentoTesteId, $fixtureChave);
        $stmt->execute();
        $stmt->close();
    }
    if ($locacaoTesteId !== null) {
        foreach (['locacoes_taxaseservicos', 'locacoes_veiculos'] as $table) {
            $stmt = $db->prepare("DELETE FROM `{$table}` WHERE id_locacao = ? AND chave = ?");
            $stmt->bind_param('is', $locacaoTesteId, $fixtureChave);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $db->prepare('DELETE FROM locacoes WHERE id = ? AND chave = ?');
        $stmt->bind_param('is', $locacaoTesteId, $fixtureChave);
        $stmt->execute();
        $stmt->close();
    }
}

if ($erro !== null) {
    fwrite(STDERR, 'ERRO: ' . $erro->getMessage() . "\n");
    exit(1);
}

exit($failures > 0 ? 1 : 0);
