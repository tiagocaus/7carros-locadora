<?php

/**
 * Traduções do módulo Formas de Pagamento - Español (España)
 */

return [
    // Títulos
    'title' => 'Formas de Pago',
    'title_singular' => 'Forma de Pago',
    'new_title' => 'Nueva Forma de Pago',
    'edit_title' => 'Editar Forma de Pago',

    // Seções
    'sections' => [
        'payment_data' => 'Datos de la Forma de Pago',
        'penalty_interest' => 'Multa e Intereses por Retraso',
        'billing_fees' => 'Tasas de Cobro',
        'billing_fees_desc' => 'Configure las tasas que se descontarán/añadirán al valor. Deje 0,00 para desactivar.',
        'early_discount' => 'Descuento por Anticipación',
        'early_discount_desc' => 'Configure un descuento para pagos realizados antes del vencimiento. Deje los valores en cero para desactivar.',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre',
        'branches' => 'Sucursales',
        'branches_hint' => 'Seleccione en qué empresas estará disponible esta forma de pago.',
        'where_to_show' => 'Dónde Mostrar',
        'where_to_show_hint' => 'Seleccione dónde estará disponible esta forma de pago.',
        'post_as_paid' => 'Registrar como pagado',
        'payment_gateways' => 'Pasarelas de Pago',
        'payment_gateways_hint' => 'Seleccione las pasarelas de pago vinculadas. Si no se selecciona ninguna pasarela, esta forma de pago no procesará pagos en línea automáticamente.',
        'penalty_percent' => 'Multa (%)',
        'penalty_hint' => 'Porcentaje de multa aplicado en caso de retraso.',
        'interest_per_day' => 'Interés por Día (%)',
        'interest_hint' => 'Porcentaje de interés cobrado por día de retraso.',
        'fixed_fee_total' => 'Tasa Fija Total',
        'fixed_fee_total_hint' => 'Valor fijo diluido entre las cuotas.<br>Ej: € 10 en 2x = € 5 por cuota.',
        'fixed_fee_installment' => 'Tasa Fija por Cuota',
        'fixed_fee_installment_hint' => 'Valor cobrado en cada cuota.<br>Ej: € 2,50 en 2x = € 5 en total.',
        'percent_fee_installment' => 'Tasa % por Cuota',
        'percent_fee_installment_hint' => 'Porcentaje sobre cada cuota.<br>Ej: 5% de € 100 = € 5 por cuota.',
        'days_before_due' => 'Días Antes del Vencimiento',
        'days_before_due_hint' => 'Cantidad de días antes del vencimiento para aplicar el descuento.',
        'discount_percent' => 'Descuento (%)',
        'discount_percent_hint' => 'Porcentaje de descuento.<br>Ej: 3% de € 100 = € 3 de descuento.',
    ],

    // Opções onde exibir
    'where_options' => [
        'site' => 'Sitio web',
        'system' => 'Sistema',
        'app' => 'Aplicación',
        'all' => 'Todos',
    ],

    // Tabela
    'table' => [
        'name' => 'Nombre',
        'fees' => 'Tasas',
        'early_discount' => 'Descuento Anticip.',
        'post_as_paid' => 'Registrar Pagado',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Ações
    'actions' => [
        'new' => 'Nuevo',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'installment_commands' => 'Comandos de Cuotas',
    ],

    // Badges e labels
    'badges' => [
        'fixed' => 'Fija',
        'fixed_installment' => 'Fija/cuota',
        'percent_installment' => '%/cuota',
        'no_fees' => 'Sin tasas',
        'yes' => 'Sí',
        'no' => 'No',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'no_name' => 'Sin nombre',
        'in_days' => 'en :daysd',
    ],

    // Dropdowns
    'dropdowns' => [
        'select_branches' => 'Seleccione las sucursales...',
        'loading_branches' => 'Cargando sucursales...',
        'error_loading_branches' => 'Error al cargar sucursales',
        'error_loading' => 'Error al cargar',
        'no_branches' => 'Ninguna sucursal registrada',
        'no_branches_short' => 'Ninguna sucursal',
        'no_gateway_selected' => 'Ninguna pasarela seleccionada (opcional)',
        'loading_gateways' => 'Cargando pasarelas...',
        'error_loading_gateways' => 'Error al cargar pasarelas',
        'no_gateways' => 'Ninguna pasarela registrada',
        'no_gateways_available' => 'Ninguna pasarela disponible',
        'no_active_gateways' => 'Ninguna pasarela activa registrada',
        'select' => 'Seleccione...',
    ],

    // Exemplo de desconto
    'discount_example' => [
        'label' => 'Ejemplo:',
        'text' => 'Pagando :days días antes del vencimiento, una cuota de € :amount tendrá un descuento de :percent% (€ :discount), quedando en € :final.',
    ],

    // Mensagens
    'messages' => [
        'load_error' => 'Error al cargar datos',
        'server_error' => 'Error al conectar con el servidor',
        'no_records' => 'Ninguna forma de pago encontrada',
        'delete_error' => 'Error al eliminar registro',
        'delete_confirm' => '¿Desea eliminar la forma de pago ":name"?',
        'this_record' => 'esta forma de pago',
        'not_found' => 'Registro no encontrado',
        'name_required' => 'El nombre es obligatorio',
        'branches_required' => 'Por favor, seleccione al menos una sucursal',
        'save_success' => 'Guardado con éxito',
        'save_error' => 'Error al guardar',
        'saving' => 'Guardando...',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar forma...',
    ],

    // Tipo de registro
    'record_type' => 'forma_pagamento',

    // ===== Comandos de Parcelas =====
    'commands' => [
        'title' => 'Comandos de Cuotas',
        'new_title' => 'Nuevo Comando',
        'edit_title' => 'Editar Comando',

        // Campos
        'fields' => [
            'command' => 'Comando',
            'command_hint' => 'Ejemplos de uso:<br><br> <b>0</b> - Pago al contado. <br><br> <b>15</b> - Pago para dentro de 15 días. <br><br> <b>1-12</b> - Genera cuota mensual de 1 a 12x. <br><br> <b>7/14/21/28</b> - En este ejemplo se generan 4 cuotas con los plazos establecidos. <br><br> <b>Dom, Seg, Ter, Qua, Qui, Sex, Sab</b> - Indique qué día de la semana será el vencimiento (Dom=Dom, Seg=Lun, Ter=Mar, Qua=Mié, Qui=Jue, Sex=Vie, Sab=Sáb). <br><br> <b>d5, d10, d15, ...</b> - Qué día del mes será el vencimiento.<br><br> <b>w36</b> - Se crearán 36 cuotas semanales.<br><br> <b>w36-Seg</b> - Se crearán 36 cuotas semanales con vencimiento cada Lunes.',
            'description' => 'Descripción',
            'active' => 'Activo',
        ],

        // Tabela
        'table' => [
            'command' => 'Comando',
            'description' => 'Descripción',
            'origin' => 'Origen',
            'status' => 'Estado',
            'actions' => 'Acciones',
        ],

        // Badges
        'badges' => [
            'system' => 'Sistema',
            'custom' => 'Personalizado',
            'system_command' => 'Comando del sistema',
        ],

        // Ações
        'actions' => [
            'new' => 'Nuevo Comando',
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        // Placeholders
        'placeholders' => [
            'search' => 'Buscar comando...',
            'command' => 'Ej: 0, 1-12, 7/14/21/28',
            'description' => 'Descripción opcional del comando',
        ],

        // Mensagens
        'messages' => [
            'no_records' => 'Ningún comando de cuota encontrado',
            'load_error' => 'Error al cargar datos',
            'server_error' => 'Error al conectar con el servidor',
            'command_required' => 'El campo Comando es obligatorio.',
            'save_success' => '¡Comando guardado con éxito!',
            'save_error' => 'Error al guardar comando.',
            'load_command_error' => 'Error al cargar comando',
            'not_found' => 'Registro no encontrado',
            'delete_error' => 'Error al eliminar registro.',
            'delete_confirm' => '¿Desea eliminar el comando ":name"?',
            'this_record' => 'este comando',
        ],

        // Paginação
        'pagination' => [
            'rows_per_page' => 'Registros por página:',
            'showing' => 'Mostrando :start-:end de :total registros',
        ],
    ],
];
