<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkChosenPlacement(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$script = file_get_contents(APP_ROOT . '/public/assets/js/chosen-select.js');
checkChosenPlacement($script !== false, 'Componente chosen-select deve estar disponivel.');

checkChosenPlacement(
    str_contains($script, "placement: select.dataset.chosenPlacement === 'bottom' ? 'bottom' : 'auto'"),
    'Inicializacao deve ler data-chosen-placement e manter auto como padrao.'
);
checkChosenPlacement(
    str_contains($script, "if (this.options.placement === 'bottom')"),
    'Componente deve possuir posicionamento explicito abaixo do campo.'
);
checkChosenPlacement(
    str_contains($script, 'rect.left + scrollX') && str_contains($script, 'rect.bottom + scrollY'),
    'Posicionamento bottom deve usar coordenadas absolutas do documento.'
);
checkChosenPlacement(
    str_contains($script, 'this.scheduleDropdownPositionUpdate();'),
    'Componente deve recalcular a posicao apos renderizar resultados.'
);

echo "OK: chosen-select suporta posicionamento imediatamente abaixo do campo.\n";
