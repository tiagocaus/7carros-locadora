<?php

/** Regressao local com tabelas temporarias; nao altera dados reais nem envia mensagens. */
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Model;
use App\Models\Contrato;
use App\Models\ContratoCaucao;

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Somente localhost');
$_SESSION = ['chave' => '1111111111111'];
$db = Model::sharedMysqli();
$temporarias = [];
$checks = 0;
$check = static function (bool $ok, string $msg) use (&$checks): void {
    if (!$ok) throw new RuntimeException($msg);
    $checks++;
    echo "PASS - {$msg}\n";
};
$insert = static function (string $tabela, array $dados) use ($db): int {
    $stmt = $db->prepare('INSERT INTO ' . $tabela . ' (`' . implode('`,`', array_keys($dados)) . '`)
        VALUES (' . implode(',', array_fill(0, count($dados), '?')) . ')');
    $stmt->execute(array_values($dados));
    return (int) $db->insert_id;
};
try {
    foreach (['contratos', 'contratos_caucoes', 'locacoes_caucoes', 'financeiro', 'financeiro_itens',
        'planos_de_contas', 'contas_bancarias', 'formas_pagamento', 'logs', 'matrizes_filiais',
        'contratos_km_apuracoes', 'contratos_km_ciclos', 'contratos_veiculos'] as $tabela) {
        $db->query("CREATE TEMPORARY TABLE estrutura_{$tabela} LIKE {$tabela}");
        $temporarias[] = "estrutura_{$tabela}";
        $db->query("CREATE TEMPORARY TABLE {$tabela} LIKE estrutura_{$tabela}");
        $temporarias[] = $tabela;
    }
    foreach ([101 => '1.1.6.01', 102 => '1.1.6.02', 103 => '1.1.5.01', 104 => '1.1.5.02'] as $id => $hierarquia) {
        $insert('planos_de_contas', ['id' => $id, 'chave' => '0', 'hierarquia' => $hierarquia, 'tipo' => 'A']);
    }
    $insert('contas_bancarias', ['id' => 1, 'chave' => $_SESSION['chave'], 'nome' => 'Conta teste', 'e_conta_bancaria' => 'S', 'status' => 'A']);
    $insert('formas_pagamento', ['id' => 1, 'chave' => $_SESSION['chave'], 'nome' => 'Forma teste', 'lancar_pago' => 'N', 'status' => 'A']);
    $contratoBase = ['chave' => $_SESSION['chave'], 'data_ini' => '2026-10-06 16:33:00',
        'data_fim' => '2026-12-29 16:33:00', 'contagem' => 'semana', 'dias' => 12, 'status' => 'A', 'id_cliente' => 1, 'total_pagar' => 1800];
    $insert('contratos', ['id' => 1, 'codigo' => 'TESTCAU1'] + $contratoBase);
    $finBase = ['chave' => $_SESSION['chave'], 'id_contrato' => 1, 'id_cliente' => 1,
        'tipo' => 'R', 'pago' => 'N', 'data_criada' => '2026-10-06', 'data_venci' => '2026-10-06',
        'parcela' => 1, 'total_parcelas' => 1, 'valor_subtotal' => 600, 'valor_total' => 600];
    $insert('financeiro', ['id' => 1] + $finBase);
    $insert('financeiro', ['id' => 2, 'pago' => 'S'] + $finBase);
    foreach ([3 => null, 4 => null, 5 => 101, 6 => 102, 7 => 103, 8 => 104] as $id => $plano) {
        $insert('financeiro', ['id' => $id, 'id_plano_de_conta' => $plano, 'valor_subtotal' => 1200, 'valor_total' => 1200] + $finBase);
    }
    $caucaoBase = ['chave' => $_SESSION['chave'], 'id_contrato' => 1, 'id_cliente' => 1,
        'id_conta' => 1, 'id_forma_pagamento' => 1, 'valor' => 1200, 'lancar_financeiro' => 1, 'status' => 'ativa'];
    $insert('contratos_caucoes', ['id' => 1, 'id_financeiro_entrada' => 3, 'id_financeiro_devolucao' => 4] + $caucaoBase);
    $insert('financeiro', ['id' => 9, 'chave' => '2222222222222'] + $finBase);
    $insert('financeiro', ['id' => 10, 'id_contrato' => 2] + $finBase);
    $insert('financeiro', ['id' => 11] + $finBase);
    $insert('contratos_caucoes', ['id' => 2, 'chave' => '2222222222222', 'id_financeiro_entrada' => 11] + $caucaoBase);
    $model = new Contrato();
    $resumo = $model->resumoFinanceiroContrato(1);
    $check((float) $resumo['total_lancado'] === 1800.0, 'Resumo exclui garantias vinculadas e legadas, mantendo isolamento');
    $check(count($model->listarFaturasAbertasContrato(1)) === 2, 'Faturas abertas excluem caucoes pelo mesmo criterio');
    $db->query('UPDATE contratos SET total_pagar=2000 WHERE id=1');
    $resultado = $model->recalcularParcelasContrato(1);
    $check($resultado['parcelas_ajustadas'] === 2, 'Recalculo ajusta somente parcelas comuns');
    $check((float) $db->query('SELECT valor_total FROM financeiro WHERE id=3')->fetch_row()[0] === 1200.0, 'Recalculo preserva valor da caucao');
    $check($model->limparParcelasPendentes(1) === 2, 'Limpeza remove somente parcelas comuns pendentes');
    $check($db->query('SELECT id FROM financeiro WHERE id IN (2,3,4,5,6,7,8,9,10)')->num_rows === 9, 'Preserva pagos, garantias, outros contratos e tenants');
    $check((int) $db->query('SELECT id_financeiro_entrada FROM contratos_caucoes WHERE id=1')->fetch_row()[0] === 3, 'Limpeza preserva vinculo da caucao');

    // Mesmo vencimento e valor nao permitem reutilizar uma caucao como parcela de aluguel.
    $db->query('UPDATE financeiro SET valor_subtotal=600, valor_total=600 WHERE id IN (3,5)');
    $buscar = new ReflectionMethod(Contrato::class, 'buscarParcelaContratoEquivalente');
    $check($buscar->invoke($model, 1, ['data_venci' => '2026-10-06', 'valor_total' => 600])['id'] === 2, 'Equivalencia prioriza parcela paga, sem confundir garantias');
    $db->query('DELETE FROM financeiro WHERE id=2');
    $check($buscar->invoke($model, 1, ['data_venci' => '2026-10-06', 'valor_total' => 600]) === null, 'Garantia do mesmo valor nao substitui aluguel');

    $insert('contratos', ['id' => 15, 'codigo' => 'TESTNOVO15', 'id_cliente' => 15] + $contratoBase);
    $caucaoNova = (new ContratoCaucao())->sincronizarAtiva(15, [
        'caucao_valor' => 1137.36, 'caucao_lancar_financeiro' => '1', 'id_conta_caucao' => 1,
        'id_forma_pagamento_caucao' => 1, 'caucao_prazo_devolucao' => 30,
    ], $model->buscarPorId(15));
    $parcelas = [];
    for ($n = 1; $n <= 12; $n++) $parcelas[] = ['parcela' => $n, 'total_parcelas' => 12,
        'valor_subtotal' => 650, 'valor_total' => 650, 'data_venci' => '2026-10-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
        'id_conta' => 1, 'id_forma_pagamento' => 1];
    $check($model->limparParcelasPendentes(15) === 0, 'Criacao: salvar parcelas nao exclui entrada recem-criada da caucao');
    $check(count($model->salvarParcelasContrato(15, $parcelas, $_SESSION['chave'])) === 12, 'Criacao gera as 12 parcelas de aluguel');
    $check($model->limparParcelasPendentes(15) === 12, 'Regeneracao remove apenas as 12 parcelas');
    $check(count($model->salvarParcelasContrato(15, $parcelas, $_SESSION['chave'])) === 12, 'Regeneracao recria aluguel');
    $check((int) (new ContratoCaucao())->buscarAtivaPorContrato(15)['id_financeiro_entrada'] === (int) $caucaoNova['id_financeiro_entrada'], 'Criacao e regeneracao preservam a mesma entrada da caucao');

    echo "OK: {$checks} verificacoes.\n";
} finally {
    foreach (array_reverse($temporarias) as $tabela) $db->query("DROP TEMPORARY TABLE {$tabela}");
    Model::closeConnection();
}
