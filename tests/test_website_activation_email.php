<?php

/**
 * Testa o email interno gerado pela solicitacao de ativacao do Website.
 *
 * Nao publica mensagens nem altera o banco de dados.
 * Execute: php tests/test_website_activation_email.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\Funcionario;
use App\Models\MatrizFilial;
use App\Models\SiteConfig;
use App\Services\WebsiteService;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

function assertWebsiteActivation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 1;
$_SESSION['chave'] = '1111111111111';
$_SESSION['user_name'] = 'Solicitante Teste';
$_SESSION['user_usuario'] = 'usuario<&teste';
$_SESSION['user_email'] = 'teste@example.com';
$_SESSION['user_plano'] = 'P2';

$matrizModel = new class extends MatrizFilial {
    public function __construct()
    {
    }

    public function buscarMatriz(): ?array
    {
        return [
            'nome_fantasia' => 'Empresa <Teste>',
            'razao_social' => 'Razao Teste',
        ];
    }
};

$funcionarioModel = new class extends Funcionario {
    public function __construct()
    {
    }

    public function getPlanoTenant(): string
    {
        return 'P2';
    }
};

$configModel = new class extends SiteConfig {
    public array $atualizacoes = [];

    public function __construct()
    {
    }

    public function criarOuAtualizar(array $dados): int
    {
        $this->atualizacoes[] = $dados;
        return 1;
    }
};

$mensagens = [];
$publisher = static function (string $type, array $payload) use (&$mensagens): int {
    $mensagens[] = compact('type', 'payload');
    return count($mensagens);
};

$service = new WebsiteService($matrizModel, $funcionarioModel, $configModel, $publisher);
$service->solicitarAtivacao([
    'dominio' => 'www.exemplo.com.br',
    'quer_registro' => true,
]);

assertWebsiteActivation(count($mensagens) === 1, 'A mensagem de registro nao foi capturada.');
$primeira = $mensagens[0];
$body = $primeira['payload']['body'];

assertWebsiteActivation($primeira['type'] === 'email', 'O canal deve ser email.');
assertWebsiteActivation(
    $primeira['payload']['subject'] === 'Ativação de Website - Empresa <Teste> [1111111111111]',
    'Assunto incorreto.'
);
assertWebsiteActivation(str_contains($body, 'Empresa &lt;Teste&gt;'), 'Empresa deve ser escapada.');
assertWebsiteActivation(str_contains($body, 'usuario&lt;&amp;teste'), 'Username deve ser escapado.');
assertWebsiteActivation(str_contains($body, 'exemplo.com.br'), 'Dominio normalizado deve aparecer no email.');
assertWebsiteActivation(!str_contains($body, 'www.exemplo.com.br'), 'Email nao deve manter o prefixo www.');
assertWebsiteActivation(str_contains($body, 'Intermediário (P2)'), 'Nome e codigo do plano devem aparecer.');
assertWebsiteActivation(
    str_contains($body, 'Sim, Quero registrar o domínio.'),
    'Opcao de registro afirmativa incorreta.'
);
assertWebsiteActivation(!str_contains($body, 'Hospedagem'), 'Hospedagem nao deve aparecer no email.');
assertWebsiteActivation($configModel->atualizacoes === [[
    'dominio' => 'exemplo.com.br',
    'status' => 'pendente',
]], 'Status pendente nao foi persistido corretamente.');

$service->solicitarAtivacao([
    'dominio' => 'www.existente.com.br',
    'quer_registro' => false,
]);

assertWebsiteActivation(
    str_contains($mensagens[1]['payload']['body'], 'Não, Já tenho meu domínio (vou alterar o DNS).'),
    'Opcao de registro negativa incorreta.'
);

$mensagensAntesDaFalha = count($mensagens);
$atualizacoesAntesDaFalha = count($configModel->atualizacoes);
unset($_SESSION['user_usuario']);

try {
    $service->solicitarAtivacao([
        'dominio' => 'www.invalido.com.br',
        'quer_registro' => false,
    ]);
    throw new RuntimeException('Contexto incompleto deveria impedir a solicitacao.');
} catch (RuntimeException $exception) {
    assertWebsiteActivation(
        $exception->getMessage() === 'Nao foi possivel identificar empresa, plano ou usuario solicitante',
        'Erro inesperado para contexto incompleto.'
    );
}

assertWebsiteActivation(count($mensagens) === $mensagensAntesDaFalha, 'Falha nao pode publicar mensagem.');
assertWebsiteActivation(
    count($configModel->atualizacoes) === $atualizacoesAntesDaFalha,
    'Falha nao pode alterar o status do site.'
);

echo "OK: email de ativacao usa dados confiaveis, opcoes descritivas e omite hospedagem.\n";
