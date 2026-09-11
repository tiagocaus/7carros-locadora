<?php

/** Regressao do principal: tabelas temporarias locais, sem alterar dados reais. */
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Models\Model;
use App\Models\ContratoEncerramento;
use App\Services\ContratoEncerramentoService;

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Este teste exige DB_HOST=localhost');
}
$_SESSION['chave'] = '1111111111111';
$db = Model::sharedMysqli();
$temporarias = [];
$assert = static function (float $esperado, float $obtido, string $cenario): void {
    if (abs($esperado - $obtido) > 0.001) {
        throw new RuntimeException("{$cenario}: esperado {$esperado}, obtido {$obtido}");
    }
    echo "PASS - {$cenario}\n";
};

try {
    foreach (['financeiro', 'planos_de_contas', 'contratos_caucoes'] as $tabela) {
        $db->query("CREATE TEMPORARY TABLE teste_estrutura_{$tabela} LIKE {$tabela}");
        $temporarias[] = "teste_estrutura_{$tabela}";
        $db->query("CREATE TEMPORARY TABLE {$tabela} LIKE teste_estrutura_{$tabela}");
        $temporarias[] = $tabela;
    }
    $planos = $db->prepare('INSERT INTO planos_de_contas (id, chave, hierarquia, tipo) VALUES (?, ?, ?, ?)');
    foreach ([101 => '4.1.1.03', 102 => '1.1.5.01', 103 => '1.1.5.02', 104 => '1.1.6.01', 105 => '1.1.6.02', 106 => '3.4.1.23'] as $id => $hierarquia) {
        $planos->execute([$id, '0', $hierarquia, 'A']);
    }
    $inserir = $db->prepare("INSERT INTO financeiro
        (id, chave, id_contrato, id_plano_de_conta, tipo, pago, data_criada, data_venci, valor_subtotal, id_multa)
        VALUES (?, ?, ?, ?, ?, 'S', '2026-03-12', '2026-03-12', ?, ?)");
    $receita = static function (int $id, ?int $plano, float $valor, string $tipo = 'R', string $chave = '1111111111111', int $contrato = 1, ?int $multa = null) use ($inserir): void {
        $inserir->execute([$id, $chave, $contrato, $plano, $tipo, $valor, $multa]);
    };
    $model = new ContratoEncerramento();
    $receita(1, 101, 19500);
    $receita(2, 102, 1200);
    $receita(3, 102, 1200);
    $assert(19500, $model->calcularPrincipalLancado(1), 'Caucoes legadas sem vinculo nao entram no aluguel');

    $resultado = (new ContratoEncerramentoService())->calcular(
        ['contagem' => 'semana', 'data_ini' => '2026-03-12 17:26:00', 'total_pagar' => 9000],
        [['id' => 1, 'data_saida' => '2026-03-12 17:26:00', 'plano' => 'KL', 'valor_plano_km_livre' => 750]],
        [], [['id_contrato_veiculo' => 1, 'data_entrada' => '2026-09-08 16:30:00']], [],
        $model->calcularPrincipalLancado(1)
    );
    $assert(19178.57, $resultado['total_final'], 'Aluguel proporcional do caso investigado');
    $assert(321.43, $resultado['ajuste_valor'], 'Credito do caso investigado sem as caucoes');
    if ($resultado['ajuste_tipo'] !== 'D') throw new RuntimeException('Ajuste deve ser credito');

    foreach ([103, 104, 105] as $indice => $plano) $receita(10 + $indice, $plano, 500);
    $assert(19500, $model->calcularPrincipalLancado(1), 'Planos de entrada e saida de bloqueio/caucao excluidos mesmo sem vinculo');
    $receita(20, 101, 600);
    $receita(21, 101, 600);
    $db->query("INSERT INTO contratos_caucoes (chave, id_contrato, id_cliente, id_financeiro_entrada, id_financeiro_devolucao, valor)
        VALUES ('1111111111111', 1, 1, 20, 21, 600)");
    $assert(19500, $model->calcularPrincipalLancado(1), 'Vinculo de caucao protege entrada e devolucao com plano incorreto');
    $receita(30, null, 50);
    $receita(31, 106, 100, 'D');
    $receita(32, 101, 900, 'D');
    $receita(33, 101, 500, 'R', '1111111111111', 1, 1);
    $receita(34, 101, 9000, 'R', '2222222222222');
    $receita(35, 101, 9000, 'R', '1111111111111', 2);
    $db->query('UPDATE financeiro SET desconto=107.14, juros=10, multa=20, valor_taxa=5 WHERE id=1');
    $assert(19450, $model->calcularPrincipalLancado(1), 'Preserva sem plano e principal bruto; desconta credito; isola contrato, tenant e multas');
    $db->query("INSERT INTO contratos_caucoes (chave, id_contrato, id_cliente, id_financeiro_entrada, valor)
        VALUES ('2222222222222', 1, 1, 1, 19500)");
    $assert(19450, $model->calcularPrincipalLancado(1), 'Caucao de outro tenant nao exclui receita atual');
    $assert(0, $model->calcularPrincipalLancado(999), 'Contrato sem financeiro');
} finally {
    foreach (array_reverse($temporarias) as $tabela) $db->query("DROP TEMPORARY TABLE {$tabela}");
    Model::closeConnection();
}
