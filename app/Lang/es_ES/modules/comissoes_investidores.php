<?php

return [
    'title' => 'Comisiones de Inversores',

    'filters' => [
        'investor' => 'Inversor',
        'status' => 'Estado',
        'type' => 'Tipo',
        'date_start' => 'Fecha Inicio',
        'date_end' => 'Fecha Fin',
    ],

    'status_options' => [
        'all' => 'Todos',
        'pending' => 'Pendiente',
        'paid' => 'Pagado',
        'cancelled' => 'Cancelado',
    ],

    'type_options' => [
        'all' => 'Todos',
        'rental' => 'Alquiler',
        'contract' => 'Contrato',
        'monthly' => 'Mensual',
    ],

    'totals' => [
        'pending' => 'Pendientes',
        'paid' => 'Pagadas',
        'cancelled' => 'Canceladas',
        'commissions_count' => 'comisión(es)',
    ],

    'table' => [
        'date_ref' => 'Fecha Ref.',
        'investor' => 'Inversor',
        'vehicle' => 'Vehículo',
        'type' => 'Tipo',
        'base_value' => 'Valor Base',
        'rental_company' => 'Empresa de Alquiler',
        'investor_value' => 'Inversor',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    'actions' => [
        'mark_paid' => 'Marcar como Pagado',
        'cancel' => 'Cancelar',
    ],

    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    'messages' => [
        'no_records' => 'No se encontraron registros',
        'load_error' => 'Error al cargar',
        'server_error' => 'Error al conectar con el servidor',
        'confirm_payment' => '¿Confirma el pago de esta comisión al inversor?',
        'paid_success' => '¡Comisión marcada como pagada!',
        'cancel_reason' => 'Motivo de la cancelación (opcional):',
        'cancelled_success' => '¡Comisión cancelada!',
    ],
];
