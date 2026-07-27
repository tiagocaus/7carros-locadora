<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkContratoBloqueioEstadoNovo(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$viewAdicionar = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/adicionar.php');
$viewEditar = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/editar.php');

checkContratoBloqueioEstadoNovo(
    $viewAdicionar !== false && $viewEditar !== false,
    'Views de contratos devem estar disponiveis.'
);

checkContratoBloqueioEstadoNovo(
    str_contains($viewAdicionar, 'id="bloqueioEstadoNovo"')
        && str_contains($viewAdicionar, "t('modules.contratos.messages.save_before_hold')"),
    'Novo contrato deve orientar o usuario a salvar antes de criar o bloqueio.'
);

checkContratoBloqueioEstadoNovo(
    str_contains($viewAdicionar, 'id="bloqueioFormFields" class="hidden"'),
    'Campos do bloqueio devem permanecer ocultos enquanto o contrato nao foi salvo.'
);

checkContratoBloqueioEstadoNovo(
    str_contains($viewAdicionar, "const registroSalvo = Boolean(document.getElementById('registroId')?.value);")
        && str_contains($viewAdicionar, "classList.toggle('hidden', !registroSalvo || !temHold);")
        && str_contains($viewAdicionar, "classList.toggle('hidden', registroSalvo);"),
    'Consulta de gateway nao deve reexibir campos antes de existir registroId.'
);

checkContratoBloqueioEstadoNovo(
    str_contains($viewEditar, 'id="toggleBloqueio"')
        && str_contains($viewEditar, 'id="conteudoBloqueio" class="mt-4 hidden"')
        && str_contains($viewEditar, 'id="bloqueioFormFields"'),
    'Contrato salvo deve manter a secao recolhida e os controles disponiveis ao expandir.'
);

echo "OK: bloqueio de novo contrato exige salvar antes de exibir os campos.\n";
