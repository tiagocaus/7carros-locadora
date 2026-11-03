<?php

/** php tests/test_planos_de_contas_tenant.php — fixtures somente em tabela temporaria local. */
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Models\Model;
use App\Models\PlanoDeContas;
use App\Controllers\PlanosDeContasController;

function t(string $key): string { return $key; }

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Este teste exige DB_HOST=localhost.');
}

$_SESSION['chave'] = 'teste_plano_a';
$db = Model::sharedMysqli();
$checks = 0;
$assert = static function (bool $ok, string $mensagem) use (&$checks): void {
    if (!$ok) throw new RuntimeException($mensagem);
    $checks++;
};
$schema = $db->query('SHOW CREATE TABLE planos_de_contas')->fetch_assoc()['Create Table'];
$db->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema));

try {
    $indices = $db->query('SHOW INDEX FROM planos_de_contas')->fetch_all(MYSQLI_ASSOC);
    $composto = array_values(array_filter($indices, static fn ($i) => $i['Key_name'] === 'uniq_planos_contas_chave_hierarquia'));
    $assert(array_column($composto, 'Column_name') === ['chave', 'hierarquia'], 'Indice deve incluir chave e hierarquia nessa ordem.');
    $assert(array_unique(array_map('intval', array_column($composto, 'Non_unique'))) === [0], 'Indice composto deve ser unico.');
    $assert(!in_array('hierarquia', array_column($indices, 'Key_name'), true), 'Indice global deve ter sido removido.');

    $db->query("INSERT INTO planos_de_contas (chave, hierarquia, descricao_i18n, tipo) VALUES
        ('0', '1.1.2', '{\"pt_BR\":\"Bancos com movimento\"}', 'A'),
        ('0', '1.1.2.09', '{\"pt_BR\":\"Banco Inter\"}', 'A'),
        ('teste_plano_b', '1.1.2.10', '{\"pt_BR\":\"Via Credi\"}', 'A')");

    $model = new PlanoDeContas();
    $assert($model->sugerirProximoCodigo('1.1.2', 'A') === '1.1.2.10', 'Outro tenant nao deve afetar a sugestao.');
    $assert(!$model->hierarquiaExiste('1.1.2.10'), 'Codigo de outro tenant deve estar disponivel.');
    $assert($model->hierarquiaExiste('1.1.2.09'), 'Codigo global deve estar reservado.');

    $dados = ['chave' => 'teste_plano_a', 'hierarquia' => '1.1.2.10', 'descricao_i18n' => '{"pt_BR":"Banco Nubank"}', 'tipo' => 'A'];
    $id = $model->criar($dados);
    $assert($id > 0, 'Mesmo codigo deve poder existir em tenants diferentes.');
    $assert($model->hierarquiaExiste('1.1.2.10'), 'Duplicidade no tenant deve ser detectada.');
    $assert(!$model->hierarquiaExiste('1.1.2.10', $id), 'Edicao deve excluir o proprio registro da validacao.');
    $model->atualizar($id, ['descricao_i18n' => '{"pt_BR":"Nubank editado"}']);
    $assert($model->buscarPorId($id)['descricao_i18n'] === '{"pt_BR":"Nubank editado"}', 'Edicao deve preservar o ID.');
    $assert($model->sugerirProximoCodigo('1.1.2', 'A') === '1.1.2.11', 'Proxima sugestao deve avancar para .11.');

    // Simula validacao anterior a uma gravacao concorrente: a restricao final e do banco.
    try {
        $model->criar($dados);
        throw new RuntimeException('Banco aceitou duplicidade no tenant.');
    } catch (mysqli_sql_exception $e) {
        $assert($e->getCode() === 1062 && str_contains($e->getMessage(), 'uniq_planos_contas_chave_hierarquia'), 'Colisao deve identificar o indice tratado pelo controller.');
        $colisao = $e;
    }

    $_SESSION['chave'] = 'teste_plano_b';
    $model = new PlanoDeContas();
    $assert($model->buscarPorId($id) === null, 'Outro tenant nao pode buscar o Nubank por ID.');
    $assert(PlanoDeContas::getDescricao($model->buscarPorHierarquia('1.1.2.10'), 'pt_BR') === 'Via Credi', 'Busca deve retornar a conta do tenant atual.');
    $assert(count($model->listar()) === 3, 'Listagem deve conter somente conta propria e globais.');
    $responder = new ReflectionMethod(PlanosDeContasController::class, 'responderHierarquiaDuplicada');
    $controller = new PlanosDeContasController();
    $assert($responder->invoke($controller, new RuntimeException('Falha de conexao')) === false, 'Outros erros devem seguir o tratamento original.');
    $assert($responder->invoke($controller, new mysqli_sql_exception("Duplicate entry for key 'PRIMARY'", 1062)) === false, 'Outro indice nao deve ser tratado como hierarquia duplicada.');
} finally {
    $db->query('DROP TEMPORARY TABLE planos_de_contas');
    Model::closeConnection();
}

if (in_array('--resposta-duplicidade', $argv, true)) {
    ob_start();
    register_shutdown_function(static function (): void {
        $resposta = json_decode(ob_get_clean(), true);
        if (http_response_code() !== 400 || $resposta !== [
            'success' => false,
            'message' => 'modules.planos_contas.messages.hierarquia_exists'
        ]) {
            fwrite(STDERR, "Resposta de duplicidade inesperada.\n");
            exit(1);
        }
        echo "OK: HTTP 400 para colisao real do indice.\n";
    });
    $responder->invoke($controller, $colisao);
    throw new RuntimeException('Resposta JSON deveria encerrar a requisicao.');
}

$processo = proc_open([PHP_BINARY, __FILE__, '--resposta-duplicidade'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$saida = stream_get_contents($pipes[1]);
$erro = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$assert(proc_close($processo) === 0 && str_contains($saida, 'HTTP 400'), 'Controller deve responder 400 a colisao real: ' . $erro);
echo "OK: {$checks} verificacoes de schema, duplicidade, isolamento e resposta HTTP.\n";
