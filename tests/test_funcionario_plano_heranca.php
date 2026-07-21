<?php

/**
 * Regressao: novos funcionarios herdam o plano autenticado e o request nao
 * pode definir nem alterar a assinatura do tenant.
 *
 * Execute: php tests/test_funcionario_plano_heranca.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Funcionario;

function assertFuncionarioPlano(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertFuncionarioPlano(
    Funcionario::planoParaNovoCadastro(['plano' => ' p2 ']) === 'P2',
    'O novo funcionario deve herdar e normalizar o plano do usuario autenticado.'
);

foreach ([null, [], ['plano' => ''], ['plano' => 'INVALIDO']] as $usuarioInvalido) {
    try {
        Funcionario::planoParaNovoCadastro($usuarioInvalido);
        throw new RuntimeException('Plano ausente ou invalido deveria impedir o cadastro.');
    } catch (UnexpectedValueException $e) {
        // Comportamento esperado.
    }
}

$idsParaNormalizar = Funcionario::agruparIdsParaNormalizacaoPlano([
    ['id' => 1, 'chave' => 'tenant-a', 'plano' => 'P2'],
    ['id' => 2, 'chave' => 'tenant-a', 'plano' => ''],
    ['id' => 3, 'chave' => 'tenant-a', 'plano' => ' '],
    ['id' => 4, 'chave' => 'tenant-b', 'plano' => 'P3'],
    ['id' => 5, 'chave' => 'tenant-b', 'plano' => 'P4'],
    ['id' => 6, 'chave' => 'tenant-b', 'plano' => ''],
    ['id' => 7, 'chave' => 'tenant-c', 'plano' => ''],
]);
assertFuncionarioPlano(
    $idsParaNormalizar === ['P2' => [2, 3]],
    'A normalizacao deve corrigir apenas tenants que possuem um unico plano valido.'
);

$controller = file_get_contents(__DIR__ . '/../app/Controllers/FuncionariosController.php');
assertFuncionarioPlano($controller !== false, 'Nao foi possivel ler o controller de funcionarios.');
assertFuncionarioPlano(
    !str_contains($controller, "'plano' => \$request->input('plano'"),
    'O plano nao pode ser aceito do request no cadastro ou na edicao.'
);
assertFuncionarioPlano(
    str_contains($controller, "'plano' => \$planoCriador"),
    'O cadastro deve persistir o plano resolvido a partir do usuario autenticado.'
);

echo "OK: funcionario herda plano autenticado e request nao controla a assinatura.\n";
