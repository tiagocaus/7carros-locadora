<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\ComissaoInvestidor;
use App\Models\Financeiro;
use App\Models\PlanoDeContas;
use App\Services\ComissaoInvestidorService;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

const TENANT_TESTE_COMISSAO = '1111111111111';
const HIERARQUIA_COMISSAO_INVESTIDOR = '3.3.1.10';

$_SESSION['chave'] = TENANT_TESTE_COMISSAO;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

function validarComissaoInvestidor(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$base = Database::fetchOne(
    'SELECT v.id_fornecedor, v.id AS id_veiculo, v.id_grupo
     FROM veiculos v
     INNER JOIN fornecedores f ON f.id = v.id_fornecedor AND f.chave = v.chave
     INNER JOIN grupos g ON g.id = v.id_grupo AND g.chave = v.chave
     WHERE v.chave = ? AND f.investidor = 1
     ORDER BY v.id
     LIMIT 1',
    [TENANT_TESTE_COMISSAO]
);

validarComissaoInvestidor(
    $base !== null,
    'O tenant de teste precisa ter um veiculo vinculado a fornecedor investidor e grupo'
);

$model = new ComissaoInvestidor();
$financeiroModel = new Financeiro();
$idComissao = null;
$idFinanceiro = null;

try {
    $idComissao = $model->criar([
        'chave' => TENANT_TESTE_COMISSAO,
        'id_fornecedor' => (int) $base['id_fornecedor'],
        'id_veiculo' => (int) $base['id_veiculo'],
        'id_grupo' => (int) $base['id_grupo'],
        'tipo_origem' => 'mensal',
        'valor_base' => 0,
        'comissao_tipo' => 'fixo_investidor_mensal',
        'comissao_valor_fixo' => 37.45,
        'valor_comissao_locadora' => 0,
        'valor_repasse_investidor' => 37.45,
        'status' => 'pendente',
        'data_referencia' => date('Y-m-01'),
    ]);

    $_SESSION['chave'] = 'tenant-inexistente-teste';
    $modelOutroTenant = new ComissaoInvestidor();
    validarComissaoInvestidor(
        $modelOutroTenant->buscarPorId($idComissao) === null,
        'Busca por ID nao pode atravessar o tenant da sessao'
    );
    validarComissaoInvestidor(
        $modelOutroTenant->contar('tenant-inexistente-teste') === 0,
        'Contagem deve respeitar a chave explicita'
    );
    validarComissaoInvestidor(
        $modelOutroTenant->listarPaginado('tenant-inexistente-teste', 1, 10) === [],
        'Listagem deve respeitar a chave explicita'
    );
    validarComissaoInvestidor(
        $modelOutroTenant->listarPendentesPorInvestidor(
            'tenant-inexistente-teste',
            (int) $base['id_fornecedor']
        ) === [],
        'Pendencias do investidor nao podem atravessar tenants'
    );
    validarComissaoInvestidor(
        !$modelOutroTenant->existeParaOrigem('tenant-inexistente-teste', PHP_INT_MAX),
        'Verificacao de origem deve respeitar a chave explicita'
    );
    $totaisOutroTenant = $modelOutroTenant->totaisPorStatus('tenant-inexistente-teste');
    validarComissaoInvestidor(
        ($totaisOutroTenant['pendente']['quantidade'] ?? -1) === 0
            && ($totaisOutroTenant['pago']['quantidade'] ?? -1) === 0
            && ($totaisOutroTenant['cancelado']['quantidade'] ?? -1) === 0,
        'Totais devem respeitar a chave explicita'
    );

    try {
        $modelOutroTenant->atualizar($idComissao, ['status' => 'cancelado']);
        throw new RuntimeException('Atualizacao cross-tenant deveria falhar');
    } catch (InvalidArgumentException $e) {
        validarComissaoInvestidor(
            str_contains($e->getMessage(), 'nao encontrada'),
            'Atualizacao cross-tenant deve tratar a comissao como inexistente'
        );
    }

    $_SESSION['chave'] = TENANT_TESTE_COMISSAO;
    $serviceSemPlano = new ComissaoInvestidorService();
    $planoAusente = new class extends PlanoDeContas
    {
        public function buscarPorHierarquia(string $hierarquia): ?array
        {
            return null;
        }
    };
    $property = new ReflectionProperty(ComissaoInvestidorService::class, 'planoDeContasModel');
    $property->setValue($serviceSemPlano, $planoAusente);

    try {
        $serviceSemPlano->marcarComoPago($idComissao, TENANT_TESTE_COMISSAO);
        throw new RuntimeException('Pagamento sem plano contabil deveria falhar');
    } catch (RuntimeException $e) {
        validarComissaoInvestidor(
            str_contains($e->getMessage(), 'nao configurado'),
            'Ausencia do plano deve retornar erro explicito'
        );
    }

    $comissaoPendente = (new ComissaoInvestidor())->buscarPorId($idComissao);
    validarComissaoInvestidor(
        ($comissaoPendente['status'] ?? null) === 'pendente'
            && empty($comissaoPendente['id_financeiro']),
        'Falha ao resolver o plano deve preservar a comissao pendente'
    );

    $service = new ComissaoInvestidorService();
    $resultado = $service->marcarComoPago($idComissao, TENANT_TESTE_COMISSAO);
    $idFinanceiro = (int) ($resultado['id_financeiro'] ?? 0);

    validarComissaoInvestidor($idFinanceiro > 0, 'Pagamento deve criar lancamento financeiro');

    $comissaoPaga = (new ComissaoInvestidor())->buscarPorId($idComissao);
    validarComissaoInvestidor(
        ($comissaoPaga['status'] ?? null) === 'pago'
            && (int) ($comissaoPaga['id_financeiro'] ?? 0) === $idFinanceiro,
        'Comissao deve ficar paga e vinculada ao financeiro criado'
    );

    $financeiro = $financeiroModel->buscarPorId($idFinanceiro);
    $plano = Database::fetchOne(
        'SELECT id FROM planos_de_contas WHERE chave = ? AND hierarquia = ? AND tipo = ?',
        ['0', HIERARQUIA_COMISSAO_INVESTIDOR, 'D']
    );

    validarComissaoInvestidor($plano !== null, 'Plano global de comissoes deve existir');
    validarComissaoInvestidor(
        ($financeiro['tipo'] ?? null) === 'D'
            && ($financeiro['pago'] ?? null) === 'S'
            && (int) ($financeiro['id_fornecedor'] ?? 0) === (int) $base['id_fornecedor']
            && (int) ($financeiro['id_plano_de_conta'] ?? 0) === (int) $plano['id']
            && abs((float) ($financeiro['valor_total'] ?? 0) - 37.45) < 0.001,
        'Financeiro deve registrar o repasse pago no plano 3.3.1.10'
    );

    try {
        $service->marcarComoPago($idComissao, TENANT_TESTE_COMISSAO);
        throw new RuntimeException('Segunda baixa deveria ter sido rejeitada');
    } catch (InvalidArgumentException $e) {
        validarComissaoInvestidor(
            str_contains($e->getMessage(), 'nao esta pendente'),
            'Segunda baixa deve falhar por status invalido'
        );
    }

    echo "OK: pagamento de comissao, plano contabil e isolamento multi-tenant\n";
} finally {
    $_SESSION['chave'] = TENANT_TESTE_COMISSAO;

    if ($idComissao !== null) {
        $registro = Database::fetchOne(
            'SELECT id_financeiro FROM comissoes_investidores WHERE chave = ? AND id = ?',
            [TENANT_TESTE_COMISSAO, $idComissao]
        );
        $idFinanceiro = $idFinanceiro ?: (int) ($registro['id_financeiro'] ?? 0);

        Database::execute(
            'DELETE FROM logs WHERE chave = ? AND mensagem LIKE ?',
            [TENANT_TESTE_COMISSAO, "%comissao investidor ID [{$idComissao}]%"]
        );
        Database::execute(
            'DELETE FROM comissoes_investidores WHERE chave = ? AND id = ?',
            [TENANT_TESTE_COMISSAO, $idComissao]
        );
    }

    if ($idFinanceiro) {
        Database::execute(
            'DELETE FROM financeiro WHERE chave = ? AND id = ?',
            [TENANT_TESTE_COMISSAO, $idFinanceiro]
        );
    }
}
