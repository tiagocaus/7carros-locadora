<?php

/**
 * Regressao: o nome de usuario e definido somente no cadastro do funcionario.
 * A edicao deve exibi-lo como informacao e nunca persisti-lo novamente.
 *
 * Execute: php tests/test_funcionario_usuario_imutavel.php
 */

function assertFuncionarioUsuarioImutavel(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = file_get_contents(__DIR__ . '/../app/Controllers/FuncionariosController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/pages/funcionarios/adicionar.php');

assertFuncionarioUsuarioImutavel($controller !== false, 'Nao foi possivel ler o controller de funcionarios.');
assertFuncionarioUsuarioImutavel($view !== false, 'Nao foi possivel ler a view de funcionarios.');

$storeInicio = strpos($controller, 'public function store(');
$updateInicio = strpos($controller, 'public function update(');
$destroyInicio = strpos($controller, 'public function destroy(');

assertFuncionarioUsuarioImutavel(
    $storeInicio !== false && $updateInicio !== false && $destroyInicio !== false,
    'Nao foi possivel localizar os metodos de cadastro e edicao de funcionarios.'
);

$store = substr($controller, $storeInicio, $updateInicio - $storeInicio);
$update = substr($controller, $updateInicio, $destroyInicio - $updateInicio);

assertFuncionarioUsuarioImutavel(
    str_contains($store, "'usuario' => \$request->input('usuario'"),
    'O cadastro deve continuar aceitando o nome de usuario.'
);
assertFuncionarioUsuarioImutavel(
    !str_contains($update, "\$request->input('usuario'"),
    'A edicao nao pode aceitar o nome de usuario enviado pelo request.'
);
assertFuncionarioUsuarioImutavel(
    str_contains($update, 'AuditLogService::registrarComAuditFrontend(')
        && str_contains($update, "\$request->input('_audit_changes')"),
    'A edicao deve registrar os campos alterados pela auditoria do formulario.'
);
assertFuncionarioUsuarioImutavel(
    str_contains($view, 'usuarioInput.disabled = true;'),
    'O campo de usuario deve ficar desabilitado no modo de edicao.'
);
assertFuncionarioUsuarioImutavel(
    str_contains($view, "FormAudit.CONFIG.ignoredFields.push('usuario')"),
    'O usuario imutavel nao deve aparecer como campo alterado na auditoria.'
);
assertFuncionarioUsuarioImutavel(
    str_contains($view, "FormAudit.recapture(document.getElementById('formFuncionario'))"),
    'A auditoria deve recapturar os dados carregados via AJAX antes da edicao.'
);

echo "OK: nome de usuario permanece imutavel na edicao de funcionarios.\n";
