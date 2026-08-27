<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkSinistros(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$migration = file_get_contents(APP_ROOT . '/app/Database/migrations/00423_create_sinistros.php');
$splitMigration = file_get_contents(APP_ROOT . '/app/Database/migrations/00424_split_sinistros_chart_account.php');
$model = file_get_contents(APP_ROOT . '/app/Models/Sinistro.php');
$service = file_get_contents(APP_ROOT . '/app/Services/SinistroService.php');
$controller = file_get_contents(APP_ROOT . '/app/Controllers/SinistrosController.php');
$routes = file_get_contents(APP_ROOT . '/app/Routes/web.php');
$javascript = file_get_contents(APP_ROOT . '/public/assets/js/sinistros.js');
$auditService = file_get_contents(APP_ROOT . '/app/Services/AuditLogService.php');
$partial = file_get_contents(APP_ROOT . '/app/Views/pages/sinistros/_tab.php');
$contratoView = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/editar.php');
$contratoAdicionarView = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/adicionar.php');
$locacaoView = file_get_contents(APP_ROOT . '/app/Views/pages/locacoes/adicionar.php');
$report = file_get_contents(APP_ROOT . '/app/Models/Relatorios/OperacionalReport.php');
$locacaoModel = file_get_contents(APP_ROOT . '/app/Models/Locacao.php');
$locacaoController = file_get_contents(APP_ROOT . '/app/Controllers/LocacoesController.php');
$sinistrosDoc = file_get_contents(APP_ROOT . '/docs/sinistros.md');

foreach (compact('migration', 'splitMigration', 'model', 'service', 'controller', 'routes', 'javascript', 'auditService', 'partial', 'contratoView', 'contratoAdicionarView', 'locacaoView', 'report', 'locacaoModel', 'locacaoController', 'sinistrosDoc') as $name => $source) {
    checkSinistros($source !== false, "Fonte {$name} deve estar disponivel.");
}

checkSinistros(!str_contains($model, 'withoutChave('), 'Model de sinistros nunca deve remover o filtro de chave.');
checkSinistros(!str_contains($controller, 'new mysqli') && !str_contains($controller, 'Database::getConnection'), 'Controller nao deve criar conexao de banco.');
checkSinistros(str_contains($service, "Auth") === false, 'Service nao deve depender de sessao para definir tenant ou permissao.');
checkSinistros(str_contains($service, "id_financeiro") && str_contains($migration, "uniq_sinistros_financeiro"), 'Cobranca deve ter vinculo unico com o sinistro.');
checkSinistros(str_contains($model, "PLANO_CONTA_SINISTROS = '4.2.2.05'"), 'Sinistros devem possuir plano de contas proprio.');
checkSinistros(str_contains($service, 'Sinistro::PLANO_CONTA_SINISTROS'), 'Cobranca de sinistro deve usar o plano exclusivo.');
checkSinistros(str_contains($splitMigration, 's.id_financeiro = f.id'), 'Reclassificacao historica deve se limitar a cobrancas vinculadas a sinistros.');
checkSinistros(str_contains($service, "financeiro.criar") === false && str_contains($controller, "Auth::can('financeiro.criar')"), 'Permissao financeira deve ser validada no Controller.');
checkSinistros(str_contains($routes, "'/api/sinistros'"), 'API propria de sinistros deve estar registrada.');
checkSinistros(str_contains($routes, "->delete('/api/sinistros/{id}'"), 'API deve disponibilizar exclusao de sinistros.');
checkSinistros(str_contains($controller, "Auth::can('financeiro.excluir')"), 'Excluir sinistro com cobranca deve exigir permissao financeira.');
checkSinistros(str_contains($service, 'buscarPorIdParaAtualizacao') && str_contains($service, "=== 'S'"), 'Exclusao deve bloquear os registros e impedir cobranca paga.');
checkSinistros(str_contains($service, 'registrarComCamposNaTransacao'), 'Auditoria deve participar da transacao de exclusao.');
checkSinistros(str_contains($auditService, 'new QueryBuilder($connection)'), 'Auditoria transacional deve usar a mesma conexao mysqli.');
checkSinistros(str_contains($javascript, "_method: 'DELETE'") && str_contains($javascript, "action: 'openDeleteModal'"), 'Frontend deve usar method spoofing e o modal global de exclusao.');
checkSinistros(!preg_match('/(^|[^A-Za-z])(?:alert|confirm)\s*\(/', $javascript), 'Frontend de sinistros nao deve usar alert ou confirm nativos.');

$taxasContrato = strpos($contratoView, 'data-form-tab-target="#tabTaxas"');
$sinistrosContrato = strpos($contratoView, 'data-form-tab-target="#tabSinistros"');
$financeiroContrato = strpos($contratoView, 'data-form-tab-target="#tabFinanceiro"');
checkSinistros($taxasContrato < $sinistrosContrato && $sinistrosContrato < $financeiroContrato, 'Aba de sinistros deve ficar entre Taxas e servicos e Financeiro no contrato.');

$taxasNovoContrato = strpos($contratoAdicionarView, 'data-form-tab-target="#tabTaxas"');
$sinistrosNovoContrato = strpos($contratoAdicionarView, 'data-form-tab-target="#tabSinistros"');
$financeiroNovoContrato = strpos($contratoAdicionarView, 'data-form-tab-target="#tabFinanceiro"');
checkSinistros($taxasNovoContrato < $sinistrosNovoContrato && $sinistrosNovoContrato < $financeiroNovoContrato, 'Aba de sinistros deve ficar entre Taxas e servicos e Financeiro no novo contrato.');

$taxasLocacao = strpos($locacaoView, 'data-form-tab-target="#tabTaxas"');
$sinistrosLocacao = strpos($locacaoView, 'data-form-tab-target="#tabSinistros"');
$financeiroLocacao = strpos($locacaoView, 'data-form-tab-target="#tabFinanceiro"');
checkSinistros($taxasLocacao < $sinistrosLocacao && $sinistrosLocacao < $financeiroLocacao, 'Aba de sinistros deve ficar entre Taxas e servicos e Financeiro na locacao.');

foreach ([
    'contrato' => $contratoView,
    'novo contrato' => $contratoAdicionarView,
    'locacao' => $locacaoView,
] as $contexto => $view) {
    checkSinistros(
        substr_count($view, '<div id="tabSinistros" class="form-tab-content">') === 1,
        "{$contexto} deve possuir um unico conteudo de aba para Sinistros."
    );
}

foreach (['sinistro_data_ocorrencia', 'sinistro_id_veiculo', 'sinistro_tipo', 'sinistro_descricao', 'sinistro_valor_estimado', 'sinistro_status', 'sinistro_observacoes'] as $field) {
    checkSinistros(str_contains($partial, 'id="' . $field . '"'), "Campo {$field} deve existir.");
}
checkSinistros(!str_contains($partial, 'sinistro_local') && !str_contains($partial, 'sinistro_anexo'), 'Local e anexos devem permanecer fora desta versao.');
checkSinistros(str_contains($partial, 'sinistro_gerar_cobranca'), 'Cobranca opcional deve estar disponivel.');
checkSinistros(str_contains($report, "->table('sinistros', 's')"), 'Relatorio deve usar a tabela real de sinistros.');
checkSinistros(!str_contains(substr($report, strpos($report, 'public function avariasSinistros'), 7000), "->table('checklist', 'ch')"), 'Relatorio nao deve inferir sinistro pelo checklist.');
checkSinistros(str_contains($locacaoModel, 'AS total_sinistros'), 'Resumo da locacao deve separar o total de sinistros.');
checkSinistros(str_contains($locacaoController, "['total_sinistros']"), 'Fechamento da locacao deve considerar sinistros separadamente.');
checkSinistros(str_contains($sinistrosDoc, '`4.2.2.01`') && str_contains($sinistrosDoc, '`4.2.2.05`'), 'Documentacao deve preservar a separacao contabil.');
checkSinistros(str_contains($sinistrosDoc, 'DELETE /api/sinistros/{id}') && str_contains($sinistrosDoc, 'Cobrancas pagas bloqueiam'), 'Documentacao deve registrar as regras de exclusao.');

checkSinistros(Database::env('DB_HOST') === 'localhost', 'Teste de schema deve usar o MySQL local.');
$pdo = Database::getConnection();
$columns = $pdo->query('SHOW COLUMNS FROM sinistros')->fetchAll(PDO::FETCH_COLUMN);
foreach (['chave', 'id_contrato', 'id_locacao', 'id_veiculo', 'id_financeiro', 'data_ocorrencia', 'tipo', 'descricao', 'valor_estimado', 'observacoes', 'status'] as $column) {
    checkSinistros(in_array($column, $columns, true), "Coluna {$column} deve existir no schema local.");
}
checkSinistros(!in_array('local', $columns, true) && !in_array('anexo', $columns, true), 'Schema nao deve conter local ou anexo.');

$planos = $pdo->query(<<<'SQL'
SELECT hierarquia, tipo, JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR')) descricao
FROM planos_de_contas
WHERE chave = '0' AND hierarquia IN ('4.2.2.01', '4.2.2.05')
SQL)->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
checkSinistros(($planos['4.2.2.01']['descricao'] ?? '') === 'Avarias', '4.2.2.01 deve permanecer exclusivo de Avarias.');
checkSinistros(($planos['4.2.2.05']['descricao'] ?? '') === 'Sinistros', '4.2.2.05 deve ser exclusivo de Sinistros.');
checkSinistros(($planos['4.2.2.05']['tipo'] ?? '') === 'R', 'Plano de Sinistros deve ser receita.');

$cobrancasForaDoPlano = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM sinistros s
INNER JOIN financeiro f ON f.id = s.id_financeiro AND f.chave = s.chave
INNER JOIN planos_de_contas pc ON pc.id = f.id_plano_de_conta
WHERE s.id_financeiro IS NOT NULL AND pc.hierarquia <> '4.2.2.05'
SQL)->fetchColumn();
checkSinistros($cobrancasForaDoPlano === 0, 'Toda cobranca vinculada a sinistro deve estar em 4.2.2.05.');

echo "OK: cadastro simples de sinistros, cobranca opcional e isolamento estrutural validados.\n";
