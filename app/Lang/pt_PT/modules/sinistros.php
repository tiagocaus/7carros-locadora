<?php
return array_replace_recursive(require __DIR__ . '/../../pt_BR/modules/sinistros.php', [
    'save_first' => 'Guarde primeiro o contrato/aluguer para registar sinistros.',
    'register' => 'Registar sinistro',
    'delete_action' => 'Eliminar',
    'delete_error' => 'Não foi possível eliminar o sinistro.',
    'deleted' => 'Sinistro eliminado com sucesso.',
    'delete_with_charge' => 'a cobrança associada também será eliminada',
    'delete_paid_blocked' => 'Estorne a cobrança paga antes de eliminar o sinistro.',
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
