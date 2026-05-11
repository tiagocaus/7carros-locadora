<?php

return [
    'title' => 'Comissões Investidores',

    'filters' => [
        'investor' => 'Investidor',
        'status' => 'Estado',
        'type' => 'Tipo',
        'date_start' => 'Data Início',
        'date_end' => 'Data Fim',
    ],

    'status_options' => [
        'all' => 'Todos',
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'cancelled' => 'Cancelado',
    ],

    'type_options' => [
        'all' => 'Todos',
        'rental' => 'Locação',
        'contract' => 'Contrato',
        'monthly' => 'Mensal',
    ],

    'totals' => [
        'pending' => 'Pendentes',
        'paid' => 'Pagas',
        'cancelled' => 'Canceladas',
        'commissions_count' => 'comissão(ões)',
    ],

    'table' => [
        'date_ref' => 'Data Ref.',
        'investor' => 'Investidor',
        'vehicle' => 'Veículo',
        'type' => 'Tipo',
        'base_value' => 'Valor Base',
        'rental_company' => 'Locadora',
        'investor_value' => 'Investidor',
        'status' => 'Estado',
        'actions' => 'Ações',
    ],

    'actions' => [
        'mark_paid' => 'Marcar como Pago',
        'cancel' => 'Cancelar',
    ],

    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    'messages' => [
        'no_records' => 'Nenhum registo encontrado',
        'load_error' => 'Erro ao carregar',
        'server_error' => 'Erro ao ligar ao servidor',
        'confirm_payment' => 'Confirma o pagamento desta comissão ao investidor?',
        'paid_success' => 'Comissão marcada como paga!',
        'cancel_reason' => 'Motivo do cancelamento (opcional):',
        'cancelled_success' => 'Comissão cancelada!',
    ],
];
