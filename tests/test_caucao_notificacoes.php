<?php

/** Regressao local: apenas tabelas temporarias; nao altera dados reais nem envia mensagens. */
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\CaucaoDeposito;
use App\Models\Model;
use App\Services\NotificationService;

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Teste exige localhost');
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION = ['authenticated' => true, 'chave' => '1111111111111', 'filiais_permitidas' => []];
$db = Model::sharedMysqli();
$temporarias = [];
$checks = 0;
$check = static function (bool $ok, string $mensagem) use (&$checks): void {
    if (!$ok) throw new RuntimeException($mensagem);
    $checks++;
    echo "PASS - {$mensagem}\n";
};
$insert = static function (string $tabela, array $dados) use ($db): void {
    $stmt = $db->prepare('INSERT INTO ' . $tabela . ' (`' . implode('`,`', array_keys($dados)) . '`)
        VALUES (' . implode(',', array_fill(0, count($dados), '?')) . ')');
    $stmt->execute(array_values($dados));
    $stmt->close();
};
$config = new ReflectionProperty(DateHelper::class, 'configCache');
$configOriginal = $config->getValue();
$sqlTimezone = $db->query('SELECT @@session.time_zone')->fetch_row()[0];
try {
    // Apenas as tabelas de origem sao temporarias. Os JOINs de cadastro sao somente leitura.
    foreach (['contratos', 'contratos_caucoes', 'locacoes', 'locacoes_caucoes'] as $tabela) {
        $db->query("CREATE TEMPORARY TABLE estrutura_{$tabela} LIKE {$tabela}");
        $temporarias[] = "estrutura_{$tabela}";
        $db->query("CREATE TEMPORARY TABLE {$tabela} LIKE estrutura_{$tabela}");
        $temporarias[] = $tabela;
    }
    $config->setValue(null, ['timezone' => 'America/Sao_Paulo', 'app_timezone' => 'UTC']);
    $hoje = DateHelper::todayForDatabase();
    $dia = static fn(int $offset): string => DateHelper::addDaysForDatabase($offset, $hoje);
    $model = new CaucaoDeposito();

    foreach (['contrato', 'locacao'] as $origem) {
        $id = 0;
        $criar = static function (string $status, string $fechamento, ?int $prazo = 15,
            string $statusCaucao = 'ativa', ?string $devolvidaEm = null, string $chave = '1111111111111',
            int $filial = 1, ?int $filialDevolucao = null) use (&$id, $insert, $origem, $dia): int {
            $id++;
            $dados = ['id' => $id, 'codigo' => strtoupper($origem[0]) . 'TEST' . $id, 'chave' => $chave,
                'status' => $status, 'id_matriz_filial_retirada' => $filial];
            if ($origem === 'contrato') {
                $insert('contratos', $dados + ['data_ini' => $dia(-60), 'data_fim' => $fechamento,
                    'contagem' => 'dia', 'dias' => 1]);
            } else {
                $insert('locacoes', $dados + ['data_saida' => $dia(-60), 'data_prevista' => $dia(-30),
                    'data_chegada' => $fechamento, 'dias' => 1, 'cliente_nome' => 'Teste caucao',
                    'id_matriz_filial_devolucao' => $filialDevolucao]);
            }
            $insert($origem === 'contrato' ? 'contratos_caucoes' : 'locacoes_caucoes', [
                'id' => $id, 'chave' => $chave, 'id_' . $origem => $id, 'id_cliente' => 0,
                'valor' => 1500, 'prazo_devolucao' => $prazo, 'status' => $statusCaucao,
                'data_devolucao' => $devolvidaEm,
            ]);
            return $id;
        };
        $aberto = $criar('A', $dia(-30)); // Inclui devolucao parcial: origem permanece A.
        $criar('R', $dia(-30));
        $criar('P', $dia(-30));
        $vencido = $criar('F', $dia(-16));
        $venceHoje = $criar('F', $dia(-15) . ' 23:59:59');
        $amanha = $criar('F', $dia(-14));
        $emSete = $criar('F', $dia(-8));
        $criar('F', $dia(-7)); // Fora da janela de proximas do relatorio.
        $criar('F', $dia(-30), 15, 'devolvida', $dia(-1));
        $criar('F', $dia(-30), 15, 'cancelada');
        $criar('F', $dia(-30), 15, 'ativa', $dia(-1)); // Legado inconsistente nao notifica.
        $zero = $criar('F', $hoje, 0);
        $vazio = $criar('F', $hoje, null);
        $criar('F', $dia(-30), 15, 'ativa', null, '2222222222222');
        $outraFilial = $criar('F', $dia(-30), 15, 'ativa', null, '1111111111111', 2);
        $semData = $criar('F', $dia(-30));
        // Contratos nao aceitam NULL; a tabela temporaria permite simular dado legado invalido.
        $db->query('ALTER TABLE ' . ($origem === 'contrato' ? 'contratos' : 'locacoes')
            . ' MODIFY ' . ($origem === 'contrato' ? 'data_fim' : 'data_chegada') . ' DATETIME NULL');
        $db->query('UPDATE ' . ($origem === 'contrato' ? 'contratos' : 'locacoes') . ' SET '
            . ($origem === 'contrato' ? 'data_fim' : 'data_chegada') . ' = NULL WHERE id=' . $semData);
        $_SESSION['filiais_permitidas'] = [1];
        $filtros = ['origem' => $origem, 'status' => 'notificacao', 'perPage' => 100];
        $resultado = $model->buscar($filtros);
        $ids = array_column($resultado['details'], 'id');
        sort($ids);
        $check($ids === [$vencido, $venceHoje, $zero, $vazio], "$origem: apenas fechados com prazo atingido e sem devolucao");
        $check($resultado['total'] === 4 && $resultado['totals']['valor_total'] === 6000.0,
            "$origem: contagem e soma corretas");
        $check($model->buscar(['origem' => $origem, 'status' => 'vencida'])['total'] === 1,
            "$origem: vencida exclui o proprio dia do vencimento");
        $proximas = $model->buscar(['origem' => $origem, 'status' => 'proxima']);
        $check($proximas['total'] === 5, "$origem: relatorio preserva proximas de hoje ate sete dias");
        $relatorio = $model->buscar(['origem' => $origem, 'perPage' => 100]);
        $porId = array_column($relatorio['details'], null, 'id');
        $check($porId[$aberto]['data_prevista_devolucao'] === null && $porId[$aberto]['situacao'] === 'ativa',
            "$origem: aberto permanece consultavel sem previsao nem atraso");
        $check($porId[$semData]['data_prevista_devolucao'] === null,
            "$origem: fechamento sem data nao usa inicio nem previsao como fallback");
        $check($porId[$venceHoje]['data_prevista_devolucao'] === $hoje && $porId[$amanha]['data_prevista_devolucao'] === $dia(1),
            "$origem: prazo contado do retorno real em dias corridos, sem conversao de hora");
        $check($relatorio['totals']['vencidas'] === 1 && $relatorio['totals']['proximas'] === 5,
            "$origem: totais do relatorio seguem classificacao corrigida");
        $p1 = $model->buscar(['page' => 1, 'perPage' => 2] + $filtros);
        $p2 = $model->buscar(['page' => 2, 'perPage' => 2] + $filtros);
        $check($p1['total'] === 4 && count(array_unique(array_column(array_merge($p1['details'], $p2['details']), 'id'))) === 4,
            "$origem: paginacao sem repeticoes");
        $check($model->buscar(['filial' => 2] + $filtros)['total'] === 1,
            "$origem: filtro explicito de filial preservado");
        if ($origem === 'locacao') {
            $criar('F', $dia(-30), 15, 'ativa', null, '1111111111111', 2, 1);
            $check($model->buscar($filtros)['total'] === 5, 'locacao: acesso pela filial de devolucao');
        }
    }
    $check($model->contarPendentesNotificacao() === 9 && count($model->listarParaNotificacoes()) === 9,
        'Contador e lista de notificacoes usam o mesmo criterio nas duas origens');
    $service = (new NotificationService())->getList('caucao');
    $check($service['total'] === 9 && count($service['items']) === 9, 'Servico entrega somente caucoes elegiveis');
    $todas = (new NotificationService())->getList('all', 1, 100);
    $caucoesTodas = array_values(array_filter($todas['items'], static fn(array $item): bool => $item['tipo'] === 'caucao'));
    $titulos = array_column($service['items'], 'titulo');
    $titulosTodas = array_column($caucoesTodas, 'titulo');
    sort($titulos);
    sort($titulosTodas);
    $check($titulos === $titulosTodas, 'Categoria Todas apresenta as mesmas caucoes elegiveis');
    $check((new NotificationService())->getCounts(fresh: true)['caucoes'] === 9,
        'Contador do servico coincide com a categoria Caucoes');

    // Hoje varia por tenant, mesmo quando o banco esta em outro dia.
    $db->query("SET SESSION time_zone = '-12:00'");
    $config->setValue(null, ['timezone' => 'Pacific/Kiritimati', 'app_timezone' => 'UTC']);
    $hojeTenant = DateHelper::todayForDatabase();
    $db->query("UPDATE contratos SET data_fim='" . $db->real_escape_string($hojeTenant) . "' WHERE id={$zero}");
    $registro = $model->buscar(['origem' => 'contrato', 'status' => 'notificacao', 'perPage' => 100]);
    $check(in_array($zero, array_column($registro['details'], 'id'), true)
        && $db->query('SELECT CURDATE()')->fetch_row()[0] < $hojeTenant,
        'Vencimento considera o dia do tenant mesmo antes da virada de dia no banco');
    $_SESSION['authenticated'] = false;
    $check($model->contarPendentesNotificacao() === 0 && $model->buscar()['total'] === 0,
        'Ausencia de contexto autenticado nao retorna caucoes');
    echo "OK: {$checks} verificacoes.\n";
} finally {
    $config->setValue(null, $configOriginal);
    $db->query("SET SESSION time_zone = '" . $db->real_escape_string($sqlTimezone) . "'");
    foreach (array_reverse($temporarias) as $tabela) $db->query("DROP TEMPORARY TABLE {$tabela}");
    Model::closeConnection();
    session_destroy();
}
