<?php

/**
 * Regressao: criar OS antes do financeiro da devolucao nao pode autobloquear
 * a sequencia da filial, e a auditoria deve participar do rollback.
 *
 * Execute: php tests/test_contrato_devolucao_transacao.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Contrato;
use App\Models\Manutencao;
use App\Models\Model;
use App\Services\AuditLogService;

if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Teste exige MySQL local.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$_SESSION['chave'] = '1111111111111';
$_SESSION['user_id'] = 0;
$_SESSION['user_name'] = 'Teste devolucao transacional';

$falhas = 0;
function checkDevolucaoTransacional(string $mensagem, bool $condicao): void
{
    global $falhas;
    echo ($condicao ? 'PASS ' : 'FAIL ') . $mensagem . PHP_EOL;
    if (!$condicao) {
        $falhas++;
    }
}

$sourceHelper = file_get_contents(APP_ROOT . '/app/Helpers/SequenciaHelper.php');
$sourceContrato = file_get_contents(APP_ROOT . '/app/Models/Contrato.php');
$sourceController = file_get_contents(APP_ROOT . '/app/Controllers/ContratosController.php');

checkDevolucaoTransacional(
    'helper nao remove o filtro automatico de tenant',
    $sourceHelper !== false && !str_contains($sourceHelper, 'withoutChave()')
);
checkDevolucaoTransacional(
    'financeiro da devolucao reserva sequencia na transacao atual',
    $sourceContrato !== false && str_contains($sourceContrato, 'proximaSequenciaNaTransacao(')
);
checkDevolucaoTransacional(
    'auditorias da devolucao usam a conexao transacional',
    $sourceController !== false
        && substr_count($sourceController, 'AuditLogService::registrarComCamposNaTransacao(') >= 3
);

$db = Model::sharedMysqli();
$qb = new QueryBuilder($db);
$filial = $qb
    ->table('matrizes_filiais')
    ->select(['id', 'sequencia_financeiro'])
    ->where('status', '=', 'A')
    ->orderBy('id', 'ASC')
    ->first();

if (!$filial) {
    throw new RuntimeException('Tenant de teste nao possui matriz/filial ativa.');
}

$sequenciaAntes = (int) $filial['sequencia_financeiro'];
$veiculoId = null;
$contratoId = null;
$manutencaoId = null;
$financeiroId = null;
$logId = null;
$db->begin_transaction();

try {
    $veiculoId = $qb->table('veiculos')->insert([
        'chave' => $_SESSION['chave'],
        'id_matriz_filial' => (int) $filial['id'],
        'placa' => 'TDT' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 7),
        'marca' => 'Teste',
        'modelo' => 'Devolucao transacional',
        'disponibilidade' => 'D',
        'odometro' => '1',
        'tanque_fracao' => '8',
    ]);

    $contratoId = $qb->table('contratos')->insert([
        'chave' => $_SESSION['chave'],
        'codigo' => 'TDT' . substr(strtoupper(bin2hex(random_bytes(5))), 0, 10),
        'id_matriz_filial_retirada' => (int) $filial['id'],
        'data_ini' => '2026-10-01 10:00:00',
        'data_fim' => '2026-10-08 10:00:00',
        'contagem' => 'semana',
        'dias' => 1,
        'valor_desconto' => 0,
        'total_fatura' => 100,
        'total_pagar' => 100,
        'status' => 'A',
    ]);

    $qb->table('contratos_veiculos')->insert([
        'chave' => $_SESSION['chave'],
        'id_contrato' => $contratoId,
        'id_veiculo' => $veiculoId,
        'data_saida' => '2026-10-01 10:00:00',
        'plano' => 'KL',
        'odometro_saida' => 1,
        'combustivel_saida' => 8,
    ]);

    $manutencaoId = (new Manutencao())->criar([
        'chave' => $_SESSION['chave'],
        'id_matriz_filial' => (int) $filial['id'],
        'id_veiculo' => $veiculoId,
        'data_enviado' => '2026-10-07 10:00:00',
        'odo_enviado' => 1,
        'tanque_enviado' => 8,
        'motivo' => 'Teste de regressao transacional',
        'status' => 'C',
    ]);

    $financeiroId = (new Contrato())->criarFinanceiroDevolucao(
        $contratoId,
        [
            'valor_total' => 100,
            'tipo' => 'R',
            'data_venci' => '2026-10-08',
            'pago' => 'N',
            'id_veiculo' => $veiculoId,
            'descricao' => 'Ajuste de encerramento - teste transacional',
        ],
        $_SESSION['chave'],
    );
    $financeiro = $qb->table('financeiro')->where('id', '=', $financeiroId)->first();
    $logId = AuditLogService::registrarComCamposNaTransacao(
        $db,
        'Teste de auditoria da devolucao na transacao',
        []
    );

    checkDevolucaoTransacional('OS foi criada dentro da transacao', $manutencaoId > 0);
    checkDevolucaoTransacional('financeiro foi criado depois da OS sem lock wait', $financeiroId > 0);
    checkDevolucaoTransacional(
        'sequencia avancou sem lock wait',
        (int) ($financeiro['sequencia'] ?? 0) === $sequenciaAntes + 1
    );
    checkDevolucaoTransacional(
        'contador foi atualizado na mesma transacao',
        (int) $qb->table('matrizes_filiais')->where('id', '=', (int) $filial['id'])->value('sequencia_financeiro') === $sequenciaAntes + 1
    );
    checkDevolucaoTransacional(
        'item financeiro foi criado na mesma transacao',
        $qb->table('financeiro_itens')->where('id_financeiro', '=', $financeiroId)->exists()
    );
    checkDevolucaoTransacional('auditoria foi criada dentro da transacao', $logId > 0);
} finally {
    $db->rollback();
}

checkDevolucaoTransacional(
    'rollback remove a OS',
    $manutencaoId !== null && !$qb->table('manutencoes')->where('id', '=', $manutencaoId)->exists()
);
checkDevolucaoTransacional(
    'rollback remove o financeiro',
    $financeiroId !== null && !$qb->table('financeiro')->where('id', '=', $financeiroId)->exists()
);
checkDevolucaoTransacional(
    'rollback remove o contrato temporario',
    $contratoId !== null && !$qb->table('contratos')->where('id', '=', $contratoId)->exists()
);
checkDevolucaoTransacional(
    'rollback remove o veiculo temporario',
    $veiculoId !== null && !$qb->table('veiculos')->where('id', '=', $veiculoId)->exists()
);
checkDevolucaoTransacional(
    'rollback restaura a sequencia',
    (int) $qb->table('matrizes_filiais')->where('id', '=', (int) $filial['id'])->value('sequencia_financeiro') === $sequenciaAntes
);
checkDevolucaoTransacional(
    'rollback remove a auditoria',
    $logId !== null && !$qb->table('logs')->where('id', '=', $logId)->exists()
);

exit($falhas > 0 ? 1 : 0);
