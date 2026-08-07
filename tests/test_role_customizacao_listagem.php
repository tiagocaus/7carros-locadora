<?php

/**
 * Regressao: uma role personalizada pelo tenant deve substituir visualmente a
 * role global de mesmo nome e, ao ser excluida, a global deve reaparecer.
 *
 * Execute: php tests/test_role_customizacao_listagem.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Models\Model;
use App\Models\Role;

function assertRoleCustomizacao(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rolesComNome(array $roles, string $name): array
{
    return array_values(array_filter(
        $roles,
        static fn(array $role): bool => ($role['name'] ?? null) === $name
    ));
}

$sessionAnterior = $_SESSION ?? null;
$tenant = 'TEST_ROLE_' . strtoupper(bin2hex(random_bytes(8)));
$outroTenant = 'TEST_ROLE_' . strtoupper(bin2hex(random_bytes(8)));
$_SESSION = ['chave' => $tenant];

$roleModel = new Role();
$roleModel->beginTransaction();

try {
    $nomeRole = 'Atendente';
    $roleSistema = $roleModel->buscarRoleSistema($nomeRole);

    assertRoleCustomizacao($roleSistema !== null, 'A role global Atendente deve existir no banco local.');
    $systemId = (int) $roleSistema['id'];

    $listaInicial = rolesComNome($roleModel->listar($tenant), $nomeRole);
    $selectInicial = rolesComNome($roleModel->listarParaSelect($tenant), $nomeRole);

    assertRoleCustomizacao(count($listaInicial) === 1, 'A listagem inicial deve conter uma unica role Atendente.');
    assertRoleCustomizacao((int) $listaInicial[0]['id'] === $systemId, 'Sem customizacao, a listagem deve usar a role global.');
    assertRoleCustomizacao(count($selectInicial) === 1, 'O select inicial deve conter uma unica role Atendente.');
    assertRoleCustomizacao((int) $selectInicial[0]['id'] === $systemId, 'Sem customizacao, o select deve usar a role global.');

    $foreignId = $roleModel->criar($outroTenant, $nomeRole, 'Customizacao de outro tenant');
    assertRoleCustomizacao(
        $roleModel->buscarPorId($foreignId, $tenant) === null,
        'Uma role de outro tenant nao pode ser acessada pelo ID.'
    );
    assertRoleCustomizacao(
        $roleModel->buscarPorIdSemRestricao($foreignId) === null,
        'O fluxo de exclusao nao pode enxergar uma role de outro tenant.'
    );

    $listaComOutroTenant = rolesComNome($roleModel->listar($tenant), $nomeRole);
    assertRoleCustomizacao(
        count($listaComOutroTenant) === 1 && (int) $listaComOutroTenant[0]['id'] === $systemId,
        'A customizacao de outro tenant nao deve ocultar a role global.'
    );

    $customId = $roleModel->criar($tenant, $nomeRole, 'Customizacao do tenant atual');
    $listaCustomizada = rolesComNome($roleModel->listar($tenant), $nomeRole);
    $selectCustomizado = rolesComNome($roleModel->listarParaSelect($tenant), $nomeRole);

    assertRoleCustomizacao(count($listaCustomizada) === 1, 'A listagem nao pode duplicar a role personalizada.');
    assertRoleCustomizacao((int) $listaCustomizada[0]['id'] === $customId, 'A listagem deve priorizar a role do tenant.');
    assertRoleCustomizacao(count($selectCustomizado) === 1, 'O select nao pode duplicar a role personalizada.');
    assertRoleCustomizacao((int) $selectCustomizado[0]['id'] === $customId, 'O select deve priorizar a role do tenant.');

    assertRoleCustomizacao(
        $roleModel->atualizarDescricao($systemId, 'Tentativa de alterar role global') === 0,
        'O CRUD normal do tenant nao pode atualizar uma role global.'
    );

    assertRoleCustomizacao($roleModel->deletar($customId) === 1, 'A customizacao do tenant deve ser excluida.');

    $listaRestaurada = rolesComNome($roleModel->listar($tenant), $nomeRole);
    $selectRestaurado = rolesComNome($roleModel->listarParaSelect($tenant), $nomeRole);

    assertRoleCustomizacao(count($listaRestaurada) === 1, 'A listagem restaurada deve conter uma unica role Atendente.');
    assertRoleCustomizacao((int) $listaRestaurada[0]['id'] === $systemId, 'Ao excluir a customizacao, a role global deve reaparecer.');
    assertRoleCustomizacao(count($selectRestaurado) === 1, 'O select restaurado deve conter uma unica role Atendente.');
    assertRoleCustomizacao((int) $selectRestaurado[0]['id'] === $systemId, 'Ao excluir a customizacao, o select deve voltar para a role global.');

    $roleModel->rollback();
} catch (Throwable $e) {
    $roleModel->rollback();
    throw $e;
} finally {
    if ($sessionAnterior === null) {
        unset($_SESSION);
    } else {
        $_SESSION = $sessionAnterior;
    }

    Model::closeConnection();
}

$roleSource = file_get_contents(__DIR__ . '/../app/Models/Role.php');
assertRoleCustomizacao($roleSource !== false, 'Nao foi possivel ler o Model de roles.');
assertRoleCustomizacao(
    !str_contains($roleSource, '->withoutChave()'),
    'O CRUD de roles nao deve desabilitar o isolamento automatico por chave.'
);

echo "OK: roles personalizadas substituem as globais sem duplicidade e podem ser restauradas.\n";
