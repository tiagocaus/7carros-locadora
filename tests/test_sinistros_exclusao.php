<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\Contrato;
use App\Models\Model;
use App\Services\SinistroService;

function checkSinistroExclusao(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$db = Model::sharedMysqli();
$fixture = $db->query(<<<'SQL'
SELECT c.id, c.chave, cv.id_veiculo
FROM contratos c
INNER JOIN contratos_veiculos cv
    ON cv.id_contrato = c.id
   AND cv.chave = c.chave
WHERE c.chave IS NOT NULL
  AND c.chave <> ''
ORDER BY c.id DESC
LIMIT 1
SQL)->fetch_assoc();

if (!$fixture) {
    fwrite(STDERR, "SKIP: nenhum contrato com veiculo disponivel para o teste.\n");
    exit(0);
}

$_SESSION['chave'] = $fixture['chave'];
$_SESSION['user_id'] = 0;
$prefix = 'Teste exclusao sinistro ' . bin2hex(random_bytes(5));
$_SESSION['user_name'] = $prefix;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$parent = (new Contrato())->buscarPorId((int) $fixture['id']);
checkSinistroExclusao($parent !== null, 'Contrato da fixture deve estar acessivel no tenant.');

$sinistrosCriados = [];
$financeirosCriados = [];

$criarFinanceiro = static function (string $pago) use ($db, $fixture, $parent, &$financeirosCriados): int {
    $stmt = $db->prepare(<<<'SQL'
INSERT INTO financeiro (
    chave, id_matriz_filial, id_cliente, id_contrato, id_veiculo,
    descricao, tipo, pago, data_criada, data_venci, data_pago,
    valor_subtotal, valor_total
) VALUES (?, ?, ?, ?, ?, ?, 'R', ?, CURDATE(), CURDATE(), ?, 125.50, 125.50)
SQL);
    $descricao = 'Cobranca fixture de sinistro';
    $dataPago = $pago === 'S' ? date('Y-m-d') : null;
    $idFilial = (int) ($parent['id_matriz_filial_retirada'] ?? 0) ?: null;
    $idCliente = (int) ($parent['id_cliente'] ?? 0) ?: null;
    $idContrato = (int) $fixture['id'];
    $idVeiculo = (int) $fixture['id_veiculo'];
    $stmt->bind_param(
        'siiiisss',
        $fixture['chave'],
        $idFilial,
        $idCliente,
        $idContrato,
        $idVeiculo,
        $descricao,
        $pago,
        $dataPago
    );
    $stmt->execute();
    $id = (int) $db->insert_id;
    $financeirosCriados[] = $id;
    return $id;
};

$criarSinistro = static function (?int $idFinanceiro) use ($db, $fixture, &$sinistrosCriados): int {
    $stmt = $db->prepare(<<<'SQL'
INSERT INTO sinistros (
    chave, id_contrato, id_veiculo, id_financeiro, data_ocorrencia,
    tipo, descricao, valor_estimado, status
) VALUES (?, ?, ?, ?, NOW(), 'colisao', 'Sinistro fixture para exclusao', 125.50, 'A')
SQL);
    $idContrato = (int) $fixture['id'];
    $idVeiculo = (int) $fixture['id_veiculo'];
    $stmt->bind_param('siii', $fixture['chave'], $idContrato, $idVeiculo, $idFinanceiro);
    $stmt->execute();
    $id = (int) $db->insert_id;
    $sinistrosCriados[] = $id;
    return $id;
};

try {
    $service = new SinistroService();

    $idSemCobranca = $criarSinistro(null);
    $service->excluir($idSemCobranca, 'contrato', $parent, $prefix, false);
    checkSinistroExclusao(
        (int) $db->query("SELECT COUNT(*) FROM sinistros WHERE id = {$idSemCobranca}")->fetch_row()[0] === 0,
        'Sinistro sem cobranca deve ser excluido.'
    );

    $idFinanceiroPendente = $criarFinanceiro('N');
    $idComCobranca = $criarSinistro($idFinanceiroPendente);
    $service->excluir($idComCobranca, 'contrato', $parent, $prefix, true);
    checkSinistroExclusao(
        (int) $db->query("SELECT COUNT(*) FROM sinistros WHERE id = {$idComCobranca}")->fetch_row()[0] === 0,
        'Sinistro com cobranca pendente deve ser excluido.'
    );
    checkSinistroExclusao(
        (int) $db->query("SELECT COUNT(*) FROM financeiro WHERE id = {$idFinanceiroPendente}")->fetch_row()[0] === 0,
        'Cobranca pendente vinculada deve ser excluida.'
    );

    $idFinanceiroSemPermissao = $criarFinanceiro('N');
    $idSemPermissao = $criarSinistro($idFinanceiroSemPermissao);
    try {
        $service->excluir($idSemPermissao, 'contrato', $parent, $prefix, false);
        checkSinistroExclusao(false, 'Cobranca deveria exigir permissao financeira.');
    } catch (InvalidArgumentException $e) {
        checkSinistroExclusao(str_contains($e->getMessage(), 'permissao'), 'Bloqueio deve informar a falta de permissao.');
    }
    checkSinistroExclusao(
        (int) $db->query("SELECT COUNT(*) FROM sinistros WHERE id = {$idSemPermissao}")->fetch_row()[0] === 1
        && (int) $db->query("SELECT COUNT(*) FROM financeiro WHERE id = {$idFinanceiroSemPermissao}")->fetch_row()[0] === 1,
        'Falta de permissao financeira deve preservar os dois registros.'
    );

    $idFinanceiroPago = $criarFinanceiro('S');
    $idPago = $criarSinistro($idFinanceiroPago);
    try {
        $service->excluir($idPago, 'contrato', $parent, $prefix, true);
        checkSinistroExclusao(false, 'Cobranca paga deveria bloquear a exclusao.');
    } catch (InvalidArgumentException $e) {
        checkSinistroExclusao(str_contains($e->getMessage(), 'Estorne'), 'Bloqueio deve orientar o estorno.');
    }
    checkSinistroExclusao(
        (int) $db->query("SELECT COUNT(*) FROM sinistros WHERE id = {$idPago}")->fetch_row()[0] === 1
        && (int) $db->query("SELECT COUNT(*) FROM financeiro WHERE id = {$idFinanceiroPago}")->fetch_row()[0] === 1,
        'Bloqueio da cobranca paga deve preservar os dois registros.'
    );

    $logs = $db->query("SELECT mensagem, campos_alterados FROM logs WHERE mensagem LIKE '" . $db->real_escape_string($prefix) . "%' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    checkSinistroExclusao(count($logs) === 2, 'Cada exclusao concluida deve gerar um log.');
    checkSinistroExclusao(
        str_contains($logs[1]['mensagem'], 'cobranca vinculada')
        && str_contains($logs[1]['campos_alterados'], 'Cobranca vinculada'),
        'Log da exclusao conjunta deve identificar e detalhar a cobranca.'
    );

    echo "OK: exclusao transacional de sinistros e cobrancas validada.\n";
} finally {
    if ($sinistrosCriados) {
        $ids = implode(',', array_map('intval', $sinistrosCriados));
        $db->query("DELETE FROM sinistros WHERE id IN ({$ids}) AND chave = '" . $db->real_escape_string($fixture['chave']) . "'");
    }
    if ($financeirosCriados) {
        $ids = implode(',', array_map('intval', $financeirosCriados));
        $db->query("DELETE FROM financeiro WHERE id IN ({$ids}) AND chave = '" . $db->real_escape_string($fixture['chave']) . "'");
    }
    $db->query("DELETE FROM logs WHERE mensagem LIKE '" . $db->real_escape_string($prefix) . "%'");
}
