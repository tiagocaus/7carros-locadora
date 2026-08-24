<?php
return array_replace_recursive(require __DIR__ . '/../../pt_BR/modules/sinistros.php', [
    'save_first' => 'Guarde primeiro o contrato/aluguer para registar sinistros.',
    'register' => 'Registar sinistro',
    'fields' => ['vehicle' => 'Viatura'],
    'report' => [
        'title' => 'Sinistros',
        'description' => 'Sinistros registados em contratos e alugueres, com valores e situação da cobrança.',
        'total' => 'Total de sinistros',
        'open' => 'Abertos',
        'completed' => 'Concluídos',
        'charged_value' => 'Valor cobrado',
        'client' => 'Cliente',
        'link' => 'Vínculo',
    ],
]);
