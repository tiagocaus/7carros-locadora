<?php

/** php tests/test_locacao_parcelas_veiculo_encerrado.php (somente tabelas temporarias locais). */
require_once __DIR__ . '/../scripts/reparar-veiculos-financeiro-bravik.php';

use App\Core\Database;
use App\Models\Model;
use App\Models\Locacao;
use App\Models\Relatorios\BaseReportModel;

class RelatorioVeiculosLocacaoTeste extends BaseReportModel
{
    public function receitas(): array
    {
        return $this->financeiroVeiculoRows('R', '2026-10-01', '2026-10-31');
    }

    protected function fetchReportRows(string $sql, array $params): array
    {
        // MySQL nao permite reabrir a mesma tabela temporaria no UNION.
        // Cada referencia recebe uma copia dos fixtures; a consulta e os
        // parametros reais do relatorio permanecem os mesmos.
        $copias = [];
        $db = $this->getMysqli();
        try {
            $sql = preg_replace_callback('/\b(FROM|JOIN) (financeiro|financeiro_itens|veiculos)\b/', static function ($m) use ($db, &$copias) {
                $copia = 'teste_relatorio_' . count($copias);
                $db->query("CREATE TEMPORARY TABLE {$copia} AS SELECT * FROM {$m[2]}");
                $copias[] = $copia;
                return $m[1] . ' ' . $copia;
            }, $sql);
            return parent::fetchReportRows($sql, $params);
        } finally {
            foreach ($copias as $copia) $db->query("DROP TEMPORARY TABLE {$copia}");
        }
    }
}

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Este teste exige DB_HOST=localhost');
}
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION = ['chave' => '1111111111111', 'authenticated' => true];
$chave = $_SESSION['chave'];
$db = Model::sharedMysqli();
$temporarias = [];
$checks = 0;
$assert = static function (bool $ok, string $mensagem) use (&$checks): void {
    if (!$ok) throw new RuntimeException($mensagem);
    $checks++;
    echo "PASS - {$mensagem}\n";
};
$inserir = static function (string $tabela, array $dados) use ($db): int {
    $colunas = implode(',', array_keys($dados));
    $params = implode(',', array_fill(0, count($dados), '?'));
    $stmt = $db->prepare("INSERT INTO {$tabela} ({$colunas}) VALUES ({$params})");
    $stmt->execute(array_values($dados));
    return $db->insert_id;
};
$buscar = static function (int $id) use ($db): array {
    return $db->query('SELECT * FROM financeiro WHERE id = ' . $id)->fetch_assoc();
};
$bloqueia = static function (callable $acao, string $mensagem) use ($assert): void {
    try {
        $acao();
    } catch (InvalidArgumentException|RuntimeException $e) {
        $assert(true, $mensagem);
        return;
    }
    $assert(false, $mensagem);
};

try {
    foreach (['locacoes', 'locacoes_veiculos', 'veiculos', 'financeiro', 'financeiro_itens', 'logs'] as $tabela) {
        $estrutura = 'teste_estrutura_' . $tabela;
        $db->query("CREATE TEMPORARY TABLE {$estrutura} LIKE {$tabela}");
        $temporarias[] = $estrutura;
        $db->query("CREATE TEMPORARY TABLE {$tabela} LIKE {$estrutura}");
        $temporarias[] = $tabela;
    }
    foreach ([1, 2] as $id) {
        $inserir('veiculos', ['id' => $id, 'chave' => $chave, 'placa' => 'TESTE' . $id]);
    }
    foreach ([900001 => 'A', 900002 => 'F', 900003 => 'R', 900004 => 'F', 900005 => 'F', 900006 => 'F'] as $id => $status) {
        $inserir('locacoes', [
            'id' => $id, 'chave' => $chave, 'codigo' => 'LVTEST' . $id, 'status' => $status,
            'data_saida' => '2026-10-24 10:00:00', 'data_prevista' => '2026-10-25 10:00:00',
            'dias' => 1, 'cliente_nome' => 'Teste', 'total_fatura' => 500, 'total_pagar' => 500,
        ]);
    }
    $vinculo = static function (int $locacao, ?int $veiculo, ?string $entrada, string $tenant = '1111111111111', string $saida = '2026-10-24 10:00:00') use ($inserir): int {
        return $inserir('locacoes_veiculos', [
            'chave' => $tenant, 'id_locacao' => $locacao, 'id_veiculo' => $veiculo,
            'data_saida' => $saida, 'data_entrada' => $entrada, 'plano' => 'KL', 'valor_plano_km_livre' => 500,
        ]);
    };
    $vinculo(900001, 1, null);
    $vinculo(900002, 1, '2026-10-25 10:00:00');
    $vinculo(900003, null, null);
    $vinculo(900005, null, '2026-10-25 10:00:00');
    $vinculo(900006, 2, '2026-10-25 10:00:00', '2222222222222');
    $model = new Locacao();
    $dadosGeracao = ['quantidade' => 1, 'data_primeiro_vencimento' => '2026-10-24'];
    $dadosAvulsa = ['valor' => 100, 'data_venci' => '2026-10-24'];

    foreach ([900001 => 1, 900002 => 1, 900003 => null] as $id => $esperado) {
        $gerado = $model->gerarParcelas($id, $dadosGeracao, $chave)[0];
        $avulso = $model->adicionarParcela($id, $dadosAvulsa, $chave);
        foreach ([$gerado, $avulso] as $financeiroId) {
            $valor = $buscar($financeiroId)['id_veiculo'];
            $assert(($valor === null ? null : (int) $valor) === $esperado, "Veiculo correto na locacao {$id}, financeiro {$financeiroId}");
        }
    }
    foreach ([900004, 900005, 900006] as $id) {
        $bloqueia(fn() => $model->gerarParcelas($id, $dadosGeracao, $chave), "Geracao bloqueia historico invalido/ausente de {$id}");
        $bloqueia(fn() => $model->adicionarParcela($id, $dadosAvulsa, $chave), "Avulsa bloqueia historico invalido/ausente de {$id}");
        $assert((int) $db->query("SELECT COUNT(*) FROM financeiro WHERE id_locacao = {$id}")->fetch_row()[0] === 0, 'Falha nao grava parcelas');
    }
    $bloqueia(fn() => $model->calcularTotaisResumo(900005), 'Calculo rejeita finalizada com vinculo sem veiculo antes de atualizar totais');

    // Substituicao: novas parcelas usam o ultimo veiculo; as antigas preservam o primeiro.
    $anterior = (int) $db->query('SELECT MIN(id) FROM financeiro WHERE id_locacao = 900002')->fetch_row()[0];
    $vinculo(900002, 2, '2026-10-25 12:00:00', $chave, '2026-10-25 10:00:00');
    $nova = $model->adicionarParcela(900002, $dadosAvulsa, $chave);
    $assert((int) $buscar($anterior)['id_veiculo'] === 1 && (int) $buscar($nova)['id_veiculo'] === 2, 'Substituicao preserva parcelas antigas e vincula novas ao ultimo veiculo');
    $db->query("UPDATE financeiro SET pago = 'S', data_pago = '2026-10-24' WHERE id = {$nova}");
    $db->query("UPDATE financeiro SET pago = 'N', data_pago = NULL WHERE id = {$nova}");
    $model->removerParcela(900002, $nova);
    $recriada = $model->gerarParcelas(900002, $dadosGeracao + ['total_pagar_final' => 700], $chave)[0];
    $assert((int) $buscar($recriada)['id_veiculo'] === 2, 'Estorno, exclusao e recriacao apos encerramento mantem veiculo');
    $_SESSION['chave'] = '2222222222222';
    $bloqueia(fn() => $model->gerarParcelas(900002, $dadosGeracao, '2222222222222'), 'Outro tenant nao gera parcelas da locacao');
    $_SESSION['chave'] = $chave;

    // Reparo pontual: fixtures com os IDs reais existem apenas nestas tabelas temporarias.
    $bravik = '09B69ADCD7CBAE914C6204984097D2DA';
    foreach ([[1088070, 161525, 'LGP4ED2N', 20365, 'OLN-6F49'], [1088065, 161530, 'LZQOAML7', 19965, 'OTK-6669']] as [$f, $l, $codigo, $v, $placa]) {
        $inserir('veiculos', ['id' => $v, 'chave' => $bravik, 'placa' => $placa]);
        $inserir('locacoes', ['id' => $l, 'chave' => $bravik, 'codigo' => $codigo, 'status' => 'F', 'data_saida' => '2026-10-24 10:00:00', 'data_prevista' => '2026-10-25 10:00:00', 'dias' => 1, 'cliente_nome' => 'Teste']);
        $vinculo($l, $v, '2026-10-25 10:00:00', $bravik);
        $inserir('financeiro', ['id' => $f, 'chave' => $bravik, 'id_locacao' => $l, 'tipo' => 'R', 'pago' => 'S', 'valor_subtotal' => 500, 'valor_total' => 500, 'data_criada' => '2026-10-29', 'data_venci' => '2026-10-24', 'data_pago' => '2026-10-24']);
    }
    $antes = [$buscar(1088070), $buscar(1088065)];
    $_SESSION['chave'] = $bravik;
    $assert((new RelatorioVeiculosLocacaoTeste())->receitas() === [], 'Consulta dos relatorios reproduz ausencia antes do reparo');
    $_SESSION['chave'] = $chave;
    repararVeiculosFinanceiroBravik($db);
    $assert($antes === [$buscar(1088070), $buscar(1088065)], 'Previa nao altera registros');
    $db->query("UPDATE locacoes SET chave = 'OUTRO_TENANT' WHERE id = 161530");
    $bloqueia(fn() => repararVeiculosFinanceiroBravik($db, true), 'Reparo rejeita locacao de outro tenant');
    $assert($buscar(1088070)['id_veiculo'] === null, 'Divergencia no segundo alvo nao altera o primeiro');
    $db->query("UPDATE locacoes SET chave = '{$bravik}' WHERE id = 161530");
    $inserir('financeiro_itens', ['chave' => $bravik, 'id_financeiro' => 1088065, 'valor' => 500]);
    $bloqueia(fn() => repararVeiculosFinanceiroBravik($db, true), 'Reparo rejeita financeiro que passou a ter itens');
    $db->query('DELETE FROM financeiro_itens WHERE id_financeiro = 1088065');

    // Forca falha de auditoria depois do UPDATE para comprovar rollback integral.
    $db->query('ALTER TABLE logs CHANGE campos_alterados teste_campos LONGTEXT NULL');
    $bloqueia(fn() => repararVeiculosFinanceiroBravik($db, true), 'Falha na auditoria interrompe reparo');
    $assert($antes === [$buscar(1088070), $buscar(1088065)], 'Falha na auditoria reverte alteracao financeira');
    $db->query('ALTER TABLE logs CHANGE teste_campos campos_alterados LONGTEXT NULL');
    repararVeiculosFinanceiroBravik($db, true);
    foreach ([[1088070, 20365, $antes[0]], [1088065, 19965, $antes[1]]] as [$id, $veiculo, $original]) {
        $atual = $buscar($id);
        $assert((int) $atual['id_veiculo'] === $veiculo, "Reparo associa financeiro {$id} ao veiculo correto");
        unset($atual['id_veiculo'], $original['id_veiculo']);
        $assert($atual === $original, 'Todos os demais campos permanecem intactos');
    }
    $logs = $db->query('SELECT * FROM logs ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $assert(count($logs) === 2 && $logs[0]['chave'] === $bravik && str_contains($logs[0]['campos_alterados'], 'id_veiculo'), 'Auditoria registra ambos os reparos com tenant e campo alterado');
    repararVeiculosFinanceiroBravik($db, true);
    $assert((int) $db->query('SELECT COUNT(*) FROM logs')->fetch_row()[0] === 2, 'Reexecucao nao altera nem duplica auditoria');
    $assert($_SESSION['chave'] === $chave, 'Manutencao restaura sessao do chamador');
    $_SESSION['chave'] = $bravik;
    $receitas = (new RelatorioVeiculosLocacaoTeste())->receitas();
    $assert(count($receitas) === 2 && abs(array_sum(array_column($receitas, 'valor')) - 1000) < 0.001, 'Consulta real dos relatorios inclui os R$ 1.000,00 nos dois veiculos');
    echo "{$checks} verificacoes aprovadas.\n";
} finally {
    foreach (array_reverse($temporarias) as $tabela) $db->query("DROP TEMPORARY TABLE {$tabela}");
    Model::closeConnection();
    session_destroy();
}
