<?php

/**
 * Traducciones del módulo Mantenimiento - Español (España)
 *
 * Contiene etiquetas de ítems compartidos entre pantallas:
 * - Planes de Mantenimiento (CRUD)
 * - Mantenimientos (Órdenes de Trabajo)
 * - CRON de verificación
 */

return [
    // Títulos generales
    'title' => 'Mantenimiento',
    'preventive_title' => 'Mantenimiento Preventivo',

    // Etiquetas de ítems de mantenimiento (compartidos)
    'items' => [
        'motor_oleo' => 'Aceite del motor',
        'motor_filtrooleo' => 'Filtro de aceite',
        'motor_correiadentada' => 'Correa de distribución',
        'motor_correiaalternador' => 'Correa del alternador',
        'motor_correiaarcondicionado' => 'Correa del aire acondicionado',
        'motor_correiabombadagua' => 'Correa de la bomba de agua',
        'motor_filtrodear' => 'Filtro de aire del motor',
        'motor_filtrodecabine' => 'Filtro de aire del habitáculo',
        'motor_filtrodecombustivel' => 'Filtro de combustible',
        'motor_fluidodofreio' => 'Líquido de frenos',
        'motor_fluidoembreagem' => 'Líquido de embrague',
        'motor_discodeembreagem' => 'Disco de embrague',
        'motor_fluidocaixademarcha' => 'Aceite de transmisión',
        'motor_limpesaarrefecimento' => 'Limpieza del sistema de refrigeración',
        'motor_vejas' => 'Bujías',
        'motor_bateria' => 'Batería',
        'rodagem_pneus' => 'Neumáticos',
        'rodagem_alinhamento' => 'Alineación',
        'rodagem_pastilhasdefreio' => 'Pastillas de freno',
        'rodagem_discodefreios' => 'Discos de freno',
        'rodagem_rodiziodepneus' => 'Rotación de neumáticos',
        'acessorio_paletasparabrisa' => 'Escobillas del limpiaparabrisas',
        'moto_corrente' => 'Cadena de transmisión',
        'moto_kitrelacao' => 'Kit relación (corona/piñón)',
        'moto_oleosuspensao' => 'Aceite de suspensión/horquilla',
        'moto_caboembreagem' => 'Cable de embrague',
        'moto_caboacelerador' => 'Cable de acelerador',
    ],

    // Categorías (agrupación en la UI)
    'categories' => [
        'motor' => 'Motor',
        'rodagem' => 'Rodaje',
        'acessorio' => 'Accesorios',
        'moto' => 'Moto',
    ],

    // Mensajes del CRON
    'cron' => [
        'disabled' => 'Mantenimiento preventivo deshabilitado via ENV',
        'processing_tenant' => 'Procesando tenant: :chave',
        'os_generated' => 'OT :código generada para vehículo :placa',
        'finished' => 'Finalizado: :tenants tenants | :veiculos vehículos | :os OT generadas',
        'result' => 'Procesados :tenants tenants, :veiculos vehículos, :os OT generadas',
    ],

    // Logs de auditoría
    'audit' => [
        'os_created' => 'Sistema generó mantenimiento preventivo para vehículo [:placa] - OT [:código]',
    ],

    // Campos de la OT generada
    'os' => [
        'reason' => 'Mantenimiento preventivo generado por el sistema.',
        'status_created' => 'Creada por el sistema',
    ],

    // Notificaciones (por vehículo - detalladas)
    'notifications' => [
        'email_subject' => 'Mantenimiento Preventivo - Matrícula :placa',
        'email_body' => "Vehículo: :placa\nOdómetro Actual: :odómetro km\n\nÍtems de mantenimiento pendientes:\n:itens\n\nUna Orden de Trabajo fue creada automáticamente.",
        'whatsapp_title' => '*Mantenimiento Preventivo*',
        'whatsapp_body' => "Vehículo: :placa\nÍtems: :itens\n\nOT creada automáticamente en el sistema.",
    ],

    // Notificaciones del CRON (consolidadas por tenant)
    'cron_notifications' => [
        'email_subject' => 'Mantenimientos Preventivos Creados',
        'email_body' => 'Se han creado mantenimientos preventivos, acceda al menú vehículos > mantenimientos.',
        'sms_body' => 'Se han creado mantenimientos preventivos, acceda al menu vehículos > mantenimientos.',
        'whatsapp_body' => '*[7Carros]* Se han creado mantenimientos preventivos, acceda al menú vehículos > mantenimientos.',
    ],

    // ===== Vistas (index.php + adicionar.php) =====

    // Títulos de las vistas
    'title_list' => 'Mantenimientos',
    'new_title' => 'Nuevo Mantenimiento',
    'edit_title' => 'Editar Mantenimiento',

    // Pestañas
    'tabs' => [
        'data' => 'Datos',
        'items' => 'Ítems',
        'financial' => 'Financiero',
    ],

    // Secciones
    'sections' => [
        'maintenance_data' => 'Datos del Mantenimiento',
        'send_to_workshop' => 'Envío al taller',
        'return_from_workshop' => 'Retorno del taller',
        'services_performed' => 'Servicios Realizados',
        'services_performed_note' => 'Esta información es solo para registro y podrá usarse en cálculos futuros.',
        'maintenance_items' => 'Ítems del Mantenimiento',
        'financial_entries' => 'Asientos Financieros',
        'entry_config' => 'Configuración del Asiento',
    ],

    // Campos
    'fields' => [
        'os' => 'OT',
        'status' => 'Estado',
        'branch' => 'Matriz/Sucursal',
        'vehicle' => 'Vehículo',
        'workshop' => 'Taller',
        'send_date' => 'Fecha Envío',
        'send_odometer' => 'Odómetro Envío',
        'send_tank' => 'Tanque Envío',
        'return_date' => 'Fecha Retorno',
        'return_odometer' => 'Odómetro Retorno',
        'return_tank' => 'Tanque Retorno',
        'odometer' => 'Odómetro',
        'tank' => 'Tanque',
        'send_reason' => 'Motivo del envío al taller',
        'workshop_notes' => 'Observaciones del Taller',
        'changed_oil' => 'Cambió Aceite',
        'changed_tires' => 'Cambió Neumáticos',
        'product' => 'Producto',
        'qty' => 'Cant',
        'unit_value' => 'Valor Unit.',
        'total_value' => 'Valor Total',
        'action' => 'Acción',
        'description' => 'Descripción',
        'value' => 'Valor',
        'payment_method' => 'Forma de Pago',
        'installments' => 'Cuotas',
        'first_due_date' => '1er Vencimiento',
        'interval_days' => 'Intervalo (días)',
    ],

    // Opciones de estado
    'status_options' => [
        'created' => 'Creada',
        'created_by_system' => 'Creada por el sistema',
        'open' => 'Abierta',
        'closed' => 'Cerrada',
    ],

    // Niveles del tanque
    'tank_levels' => [
        'full' => 'Lleno',
        'reserve' => 'Reserva',
    ],

    // Badges
    'badges' => [
        'paid' => 'Pagado',
        'pending' => 'Pendiente',
        'new' => 'Nuevo',
        'editing' => 'Editando',
    ],

    // Acciones
    'actions' => [
        'new' => 'Nueva',
        'add_item' => 'Agregar Ítem',
        'create_full_entry' => 'Crear Asiento Completo',
        'close_selected' => 'Cerrar Ítems Seleccionados',
        'go_to_list' => 'Ir al Listado',
    ],

    // Tabla
    'table' => [
        'os' => 'OT',
        'vehicle' => 'Vehículo',
        'workshop' => 'Taller',
        'send_date' => 'Fecha Envío',
        'total' => 'Total',
        'status' => 'Estado',
        'actions' => 'Acciones',
        'totals' => 'Totales:',
        'total_paid' => 'Total Pagado:',
        'total_pending' => 'Total Pendiente:',
        'total_selected' => 'Total Seleccionado:',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar OT, vehículo...',
        'select' => 'Seleccione...',
        'search_type' => 'Escriba para buscar...',
        'search_product' => 'Buscar producto...',
        'search_product_service' => 'Buscar producto/servicio...',
        'item_description' => 'Descripción del ítem',
        'manual_description' => 'Escribir descripción manual',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'Ningún mantenimiento encontrado',
        'load_error' => 'Error al cargar',
        'server_error' => 'Error al conectar',
        'delete_error' => 'Error al eliminar',
        'save_error' => 'Error al guardar',
        'save_success' => 'Mantenimiento guardado correctamente',
        'no_items' => 'Ningún ítem agregado',
        'no_pending_items' => 'Ningún ítem pendiente',
        'select_product' => 'Seleccione un producto',
        'cannot_remove_paid' => 'No es posible eliminar ítems pagados',
        'cannot_edit_paid' => 'No es posible editar ítems pagados',
        'provide_description' => 'Ingrese la descripción o seleccione un producto',
        'product_out_of_stock' => 'Producto sin stock disponible.',
        'stock_insufficient' => 'Solo hay :qty disponible(s). Cantidad ajustada.',
        'select_at_least_one' => 'Seleccione al menos un ítem',
        'entry_created' => 'Asiento creado correctamente',
        'generic_error' => 'Error',
        'odometer_required' => 'Ingrese el odómetro de retorno',
        'saved_title' => 'Mantenimiento Guardado',
        'saved_go_to_list' => '¿Desea volver al listado?',
        'financial_desc' => 'Seleccione los ítems pendientes para crear un asiento financiero parcial o haga clic en "Crear Asiento Completo" para incluir todos.',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
        'page_navigation' => 'Navegación de páginas',
    ],

    // Impresión
    'print' => [
        'title' => 'Orden de Trabajo',
        'action' => 'Imprimir',
        'cpf_cnpj_label' => 'CPF/CNPJ:',
    ],

    // Tipo de registro (para modal de eliminación)
    'record_type' => 'manutencao',

    // Auditoría financiera
    'audit_financial' => [
        'section' => 'Asiento Financiero',
        'type' => 'Tipo',
        'complete' => 'Completo',
        'partial' => 'Parcial',
        'payment_method' => 'Forma de Pago',
        'installments' => 'Cuotas',
        'first_due_date' => '1er Vencimiento',
        'interval' => 'Intervalo',
        'days' => 'días',
        'total_value' => 'Valor Total',
        'selected_items' => 'Ítems Seleccionados',
        'item' => 'Ítem',
        'value' => 'Valor',
    ],
];
