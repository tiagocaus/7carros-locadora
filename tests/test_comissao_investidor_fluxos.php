<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\ComissaoInvestidor;
use App\Models\Financeiro;
use App\Services\ComissaoInvestidorService;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

const TENANT_TESTE_FLUXO_COMISSAO = '1111111111111';

$_SESSION['chave'] = TENANT_TESTE_FLUXO_COMISSAO;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

function validarFluxoComissao(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$vinculo = Database::fetchOne(
    "SELECT cv.id_contrato, cv.id_veiculo, COALESCE(cv.id_grupo, v.id_grupo) AS id_grupo,
            v.id_fornecedor
     FROM contratos_veiculos cv
     INNER JOIN veiculos v ON v.id = cv.id_veiculo AND v.chave = cv.chave
     INNER JOIN fornecedores f ON f.id = v.id_fornecedor AND f.chave = v.chave
     WHERE cv.chave = ?
       AND cv.data_entrada IS NULL
       AND f.investidor = 1
       AND v.id_fornecedor IS NOT NULL
       AND COALESCE(cv.id_grupo, v.id_grupo) IS NOT NULL
     ORDER BY cv.id DESC
     LIMIT 1",
    [TENANT_TESTE_FLUXO_COMISSAO]
);

validarFluxoComissao($vinculo !== null, 'O tenant de teste precisa ter contrato ativo com veiculo de investidor');

$vinculoLocacao = Database::fetchOne(
    "SELECT lv.id_locacao, lv.id_veiculo, COALESCE(lv.id_grupo, v.id_grupo) AS id_grupo,
            v.id_fornecedor
     FROM locacoes_veiculos lv
     INNER JOIN veiculos v ON v.id = lv.id_veiculo AND v.chave = lv.chave
     INNER JOIN fornecedores f ON f.id = v.id_fornecedor AND f.chave = v.chave
     WHERE lv.chave = ?
       AND lv.data_entrada IS NULL
       AND f.investidor = 1
       AND v.id_fornecedor IS NOT NULL
       AND COALESCE(lv.id_grupo, v.id_grupo) IS NOT NULL
     ORDER BY lv.id DESC
     LIMIT 1",
    [TENANT_TESTE_FLUXO_COMISSAO]
);

validarFluxoComissao($vinculoLocacao !== null, 'O tenant de teste precisa ter locacao ativa com veiculo de investidor');

$idsRegras = [];
$idsFinanceirosOrigem = [];
$idsComissoes = [];
$idsFinanceirosRepasse = [];

try {
    $idsRegras[] = Database::insertGetId('fornecedores_comissoes_regras', [
        'chave' => TENANT_TESTE_FLUXO_COMISSAO,
        'id_fornecedor' => (int) $vinculo['id_fornecedor'],
        'id_grupo' => (int) $vinculo['id_grupo'],
        'comissao_tipo' => 'percentual_locadora',
        'comissao_valor' => 20,
        'ativo' => 1,
        'updated_at' => now(),
    ]);

    $idFinanceiroOrigem = (new Financeiro())->criar([
        'chave' => TENANT_TESTE_FLUXO_COMISSAO,
        'tipo' => 'R',
        'pago' => 'S',
        'descricao' => 'Teste fluxo comissao investidor',
        'data_criada' => '2026-08-05',
        'data_venci' => '2026-08-05',
        'data_pago' => '2026-08-05',
        'valor_subtotal' => 900,
        'juros' => 450,
        'multa' => 0,
        'desconto' => 0,
        'id_contrato' => (int) $vinculo['id_contrato'],
        'id_veiculo' => (int) $vinculo['id_veiculo'],
    ]);
    $idsFinanceirosOrigem[] = $idFinanceiroOrigem;

    $service = new ComissaoInvestidorService();
    $analise = $service->analisarComissaoPorFinanceiro($idFinanceiroOrigem);
    validarFluxoComissao($analise['aplicavel'] === true, 'Receita paga do contrato deveria ser elegivel');
    validarFluxoComissao(
        abs((float) $analise['dados_comissao']['valor_base'] - 1350.00) < 0.001,
        'Base deve usar valor_total, incluindo juros'
    );
    validarFluxoComissao(
        abs((float) $analise['dados_comissao']['valor_repasse_investidor'] - 1080.00) < 0.001,
        'Repasse deve corresponder a 80% do valor_total'
    );
    validarFluxoComissao(
        $analise['dados_comissao']['data_referencia'] === '2026-08-05',
        'Data de referencia deve preservar a data do pagamento'
    );

    $primeiraComissao = (int) $service->processarComissaoPorFinanceiro($idFinanceiroOrigem);
    validarFluxoComissao($primeiraComissao > 0, 'Processamento deve criar a comissao');
    $idsComissoes[] = $primeiraComissao;
    validarFluxoComissao(
        $service->processarComissaoPorFinanceiro($idFinanceiroOrigem) === null,
        'Reprocessamento nao pode duplicar comissao ativa'
    );

    $cancelamentoPendente = $service->cancelarPorFinanceiroOrigem(
        $idFinanceiroOrigem,
        TENANT_TESTE_FLUXO_COMISSAO,
        'Teste de estorno pendente'
    );
    validarFluxoComissao($cancelamentoPendente['cancelada'] === true, 'Estorno deve cancelar comissao pendente');
    validarFluxoComissao(
        ((new ComissaoInvestidor())->buscarPorId($primeiraComissao)['status'] ?? null) === 'cancelado',
        'Comissao pendente deve ficar cancelada'
    );
    validarFluxoComissao(
        $service->cancelarPorFinanceiroOrigem(
            $idFinanceiroOrigem,
            TENANT_TESTE_FLUXO_COMISSAO,
            'Repeticao do estorno'
        )['cancelada'] === false,
        'Cancelamento repetido deve ser idempotente'
    );

    $segundaComissao = (int) $service->processarComissaoPorFinanceiro($idFinanceiroOrigem);
    validarFluxoComissao($segundaComissao > 0, 'Nova baixa apos cancelamento deve gerar nova comissao');
    $idsComissoes[] = $segundaComissao;

    $pagamento = $service->marcarComoPago($segundaComissao, TENANT_TESTE_FLUXO_COMISSAO);
    $idRepasse = (int) ($pagamento['id_financeiro'] ?? 0);
    validarFluxoComissao($idRepasse > 0, 'Pagamento da comissao deve criar despesa de repasse');
    $idsFinanceirosRepasse[] = $idRepasse;

    $cancelamentoPago = $service->cancelarPorFinanceiroOrigem(
        $idFinanceiroOrigem,
        TENANT_TESTE_FLUXO_COMISSAO,
        'Teste de estorno com repasse pago'
    );
    validarFluxoComissao($cancelamentoPago['cancelada'] === true, 'Estorno deve cancelar comissao paga');

    $repasse = (new Financeiro())->buscarPorId($idRepasse);
    validarFluxoComissao(
        ($repasse['pago'] ?? null) === 'N' && empty($repasse['data_pago']),
        'Estorno da origem deve reverter o pagamento da despesa de repasse'
    );

    $logCancelamento = Database::fetchOne(
        'SELECT campos_alterados FROM logs WHERE chave = ? AND mensagem LIKE ? ORDER BY id DESC LIMIT 1',
        [TENANT_TESTE_FLUXO_COMISSAO, "%cancelou comissao investidor ID [{$segundaComissao}]%"]
    );
    validarFluxoComissao(
        !empty($logCancelamento['campos_alterados']),
        'Cancelamento deve registrar IDs, status e motivo nos campos de auditoria'
    );

    $idsRegras[] = Database::insertGetId('fornecedores_comissoes_regras', [
        'chave' => TENANT_TESTE_FLUXO_COMISSAO,
        'id_fornecedor' => (int) $vinculoLocacao['id_fornecedor'],
        'id_grupo' => (int) $vinculoLocacao['id_grupo'],
        'comissao_tipo' => 'percentual_locadora',
        'comissao_valor' => 20,
        'ativo' => 1,
        'updated_at' => now(),
    ]);

    $idFinanceiroLocacao = (new Financeiro())->criar([
        'chave' => TENANT_TESTE_FLUXO_COMISSAO,
        'tipo' => 'R',
        'pago' => 'S',
        'descricao' => 'Teste fluxo comissao investidor locacao',
        'data_criada' => '2026-08-06',
        'data_venci' => '2026-08-06',
        'data_pago' => '2026-08-06',
        'valor_subtotal' => 500,
        'id_locacao' => (int) $vinculoLocacao['id_locacao'],
        'id_veiculo' => (int) $vinculoLocacao['id_veiculo'],
    ]);
    $idsFinanceirosOrigem[] = $idFinanceiroLocacao;

    $comissaoLocacao = (int) $service->processarComissaoPorFinanceiro($idFinanceiroLocacao);
    validarFluxoComissao($comissaoLocacao > 0, 'Baixa de locacao deve gerar comissao');
    $idsComissoes[] = $comissaoLocacao;
    $registroLocacao = (new ComissaoInvestidor())->buscarPorId($comissaoLocacao);
    validarFluxoComissao(
        ($registroLocacao['tipo_origem'] ?? null) === 'locacao'
            && (int) ($registroLocacao['id_locacao'] ?? 0) === (int) $vinculoLocacao['id_locacao'],
        'Comissao da locacao deve preservar o vinculo de origem'
    );
    validarFluxoComissao(
        $service->cancelarPorFinanceiroOrigem(
            $idFinanceiroLocacao,
            TENANT_TESTE_FLUXO_COMISSAO,
            'Teste de estorno da locacao'
        )['cancelada'] === true,
        'Estorno da locacao deve cancelar a comissao'
    );

    $contratosController = file_get_contents(APP_ROOT . '/app/Controllers/ContratosController.php');
    $locacoesController = file_get_contents(APP_ROOT . '/app/Controllers/LocacoesController.php');
    validarFluxoComissao(
        substr_count($contratosController, 'cancelarPorFinanceiroOrigem(') >= 1
            && substr_count($contratosController, 'processarComissaoPorFinanceiro(') >= 1,
        'Controller de contratos deve sincronizar baixa e estorno da comissao'
    );
    validarFluxoComissao(
        substr_count($locacoesController, 'cancelarPorFinanceiroOrigem(') >= 1
            && substr_count($locacoesController, 'processarComissaoPorFinanceiro(') >= 1,
        'Controller de locacoes deve sincronizar baixa e estorno da comissao'
    );

    echo "OK: geracao, idempotencia, base total e estorno de comissoes de investidores\n";
} finally {
    $_SESSION['chave'] = TENANT_TESTE_FLUXO_COMISSAO;

    foreach ($idsComissoes as $idComissao) {
        Database::execute(
            'DELETE FROM logs WHERE chave = ? AND mensagem LIKE ?',
            [TENANT_TESTE_FLUXO_COMISSAO, "%comissao investidor ID [{$idComissao}]%"]
        );
    }
    if ($idsComissoes) {
        Database::execute(
            'DELETE FROM comissoes_investidores WHERE chave = ? AND id IN (' . implode(',', array_map('intval', $idsComissoes)) . ')',
            [TENANT_TESTE_FLUXO_COMISSAO]
        );
    }
    foreach ($idsFinanceirosRepasse as $idFinanceiroRepasse) {
        Database::execute(
            'DELETE FROM financeiro WHERE chave = ? AND id = ?',
            [TENANT_TESTE_FLUXO_COMISSAO, $idFinanceiroRepasse]
        );
    }
    foreach ($idsFinanceirosOrigem as $idFinanceiroOrigem) {
        Database::execute(
            'DELETE FROM financeiro WHERE chave = ? AND id = ?',
            [TENANT_TESTE_FLUXO_COMISSAO, $idFinanceiroOrigem]
        );
    }
    foreach ($idsRegras as $idRegra) {
        Database::execute(
            'DELETE FROM fornecedores_comissoes_regras WHERE chave = ? AND id = ?',
            [TENANT_TESTE_FLUXO_COMISSAO, $idRegra]
        );
    }
}
