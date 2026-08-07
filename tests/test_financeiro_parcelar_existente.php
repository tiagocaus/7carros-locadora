<?php

require dirname(__DIR__) . '/vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\Financeiro;
use App\Models\FinanceiroItem;
use App\Services\PagamentoLinkSyncService;

$_SESSION['chave'] = '1111111111111';
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

function validarParcelamentoExistente(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$financeiro = new Financeiro();
$itens = new FinanceiroItem();
$idOrigem = null;
$idsParcelas = [];
$idOrigemLocacao = null;
$idsParcelasLocacao = [];

try {
    $contrato = Database::fetchOne(
        'SELECT id FROM contratos WHERE chave = ? ORDER BY id ASC LIMIT 1',
        [$_SESSION['chave']]
    );
    $locacao = Database::fetchOne(
        'SELECT id FROM locacoes WHERE chave = ? ORDER BY id ASC LIMIT 1',
        [$_SESSION['chave']]
    );

    $idOrigem = $financeiro->criar([
        'chave' => $_SESSION['chave'],
        'id_contrato' => $contrato['id'] ?? null,
        'tipo' => 'R',
        'pago' => 'N',
        'descricao' => 'Teste conversao de fatura existente ' . uniqid('', true),
        'data_criada' => DateHelper::todayForDatabase(),
        'data_venci' => '2026-08-10',
        'valor_subtotal' => 900,
        'juros' => 100,
        'multa' => 20,
        'desconto' => 20,
        'total_parcelas' => 1,
    ]);

    $itens->salvarTodos($idOrigem, $_SESSION['chave'], [
        ['descricao' => 'Diarias', 'valor' => 600],
        ['descricao' => 'Servicos', 'valor' => 300],
    ]);
    $financeiro->atualizar($idOrigem, [
        'juros' => 100,
        'multa' => 20,
        'desconto' => 20,
    ]);

    $linkService = new PagamentoLinkSyncService();
    $linkAntes = $linkService->obterOuCriarLinkAtualizado($idOrigem, $_SESSION['chave']);

    $resultado = $financeiro->parcelarExistente(
        $idOrigem,
        3,
        '2026-08-10',
        1,
        'meses',
        $_SESSION['chave']
    );
    $idsParcelas = $resultado['ids_parcelas'];
    $linkService->invalidarLinksPendentes($idOrigem, $_SESSION['chave']);
    $linkDepois = $linkService->obterOuCriarLinkAtualizado($idOrigem, $_SESSION['chave']);

    validarParcelamentoExistente($resultado['id_origem'] === $idOrigem, 'A fatura original deve permanecer como origem');
    validarParcelamentoExistente($resultado['quantidade'] === 3, 'Devem ser criadas tres parcelas');
    validarParcelamentoExistente(abs($resultado['valor_original'] - 1000.0) < 0.001, 'O saldo atual deve incluir os encargos');
    validarParcelamentoExistente($linkAntes['link_id'] === $linkDepois['link_id'], 'O link publico da parcela 1 deve permanecer estavel');
    $linkPersistido = Database::fetchOne('SELECT valor FROM pagamentos_links WHERE id = ?', [$linkDepois['link_id']]);
    validarParcelamentoExistente(abs((float) $linkPersistido['valor'] - 333.33) < 0.001, 'O link publico deve refletir o valor da parcela 1');
    validarParcelamentoExistente(
        array_column($resultado['parcelas'], 'data_venci') === ['2026-08-10', '2026-09-10', '2026-10-10'],
        'Os vencimentos mensais devem ser calculados pelo DateHelper'
    );

    $parcelas = $financeiro->listarParcelas($idOrigem);
    validarParcelamentoExistente(count($parcelas) === 3, 'A familia deve conter origem e duas filhas');

    $valores = array_map(static fn(array $parcela): float => (float) $parcela['valor_subtotal'], $parcelas);
    validarParcelamentoExistente($valores === [333.33, 333.33, 333.34], 'A ultima parcela deve absorver o centavo restante');
    validarParcelamentoExistente(abs(array_sum($valores) - 1000.0) < 0.001, 'A soma das parcelas deve fechar o saldo original');

    foreach ($idsParcelas as $index => $idParcela) {
        $lancamento = $financeiro->buscarPorId($idParcela);
        $itensParcela = $itens->listarPorFinanceiro($idParcela);
        $somaItens = array_sum(array_map(static fn(array $item): float => (float) $item['valor'], $itensParcela));

        validarParcelamentoExistente(count($itensParcela) === 2, 'Cada parcela deve preservar os dois itens contabeis');
        validarParcelamentoExistente(abs($somaItens - $valores[$index]) < 0.001, 'Os itens devem fechar o subtotal da parcela');
        validarParcelamentoExistente((int) $lancamento['parcela'] === $index + 1, 'A numeracao das parcelas deve ser sequencial');
        validarParcelamentoExistente((int) $lancamento['total_parcelas'] === 3, 'Todas as parcelas devem informar o total da serie');
        validarParcelamentoExistente((float) $lancamento['juros'] === 0.0, 'Juros anteriores devem ser consolidados no saldo');
        validarParcelamentoExistente((float) $lancamento['multa'] === 0.0, 'Multa anterior deve ser consolidada no saldo');
        validarParcelamentoExistente((float) $lancamento['desconto'] === 0.0, 'Desconto anterior deve ser consolidado no saldo');

        if ($contrato) {
            validarParcelamentoExistente(
                (int) $lancamento['id_contrato'] === (int) $contrato['id'],
                'O vinculo com o contrato deve ser preservado'
            );
        }
    }

    try {
        $financeiro->parcelarExistente($idOrigem, 2, '2026-11-10', 1, 'meses', $_SESSION['chave']);
        throw new RuntimeException('Uma fatura ja parcelada nao pode ser convertida novamente');
    } catch (InvalidArgumentException $e) {
        validarParcelamentoExistente(
            str_contains($e->getMessage(), 'parcelamento') || str_contains($e->getMessage(), 'parcelas'),
            'A segunda conversao deve ser rejeitada pelo motivo correto'
        );
    }

    $idOrigemLocacao = $financeiro->criar([
        'chave' => $_SESSION['chave'],
        'id_locacao' => $locacao['id'],
        'tipo' => 'R',
        'pago' => 'N',
        'descricao' => 'Teste vinculo locacao no parcelamento ' . uniqid('', true),
        'data_criada' => DateHelper::todayForDatabase(),
        'data_venci' => '2026-08-15',
        'valor_subtotal' => 250,
        'total_parcelas' => 1,
    ]);
    $resultadoLocacao = $financeiro->parcelarExistente(
        $idOrigemLocacao,
        2,
        '2026-08-15',
        15,
        'dias',
        $_SESSION['chave']
    );
    $idsParcelasLocacao = $resultadoLocacao['ids_parcelas'];

    validarParcelamentoExistente(
        array_column($resultadoLocacao['parcelas'], 'data_venci') === ['2026-08-15', '2026-08-30'],
        'O intervalo em dias deve ser preservado'
    );
    foreach ($idsParcelasLocacao as $idParcela) {
        $lancamento = $financeiro->buscarPorId($idParcela);
        validarParcelamentoExistente(
            (int) $lancamento['id_locacao'] === (int) $locacao['id'],
            'O vinculo com a locacao deve ser preservado'
        );
    }

    echo "OK: fatura existente convertida com rateio, vinculos e fechamento de centavos\n";
} finally {
    foreach (array_reverse(array_values(array_unique(array_map('intval', $idsParcelasLocacao)))) as $idParcela) {
        if ($idParcela !== (int) $idOrigemLocacao) {
            $financeiro->deletar($idParcela);
        }
    }

    if ($idOrigemLocacao !== null) {
        $financeiro->deletar($idOrigemLocacao);
    }

    foreach (array_reverse(array_values(array_unique(array_map('intval', $idsParcelas)))) as $idParcela) {
        if ($idParcela !== (int) $idOrigem) {
            $financeiro->deletar($idParcela);
        }
    }

    if ($idOrigem !== null) {
        $financeiro->deletar($idOrigem);
    }
}
