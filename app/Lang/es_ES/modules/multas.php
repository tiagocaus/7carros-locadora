<?php

return [
    'title' => 'Multas',
    'title_singular' => 'Multa',
    'new_title' => 'Nueva Multa',
    'edit_title' => 'Editar Multa',

    'sections' => [
        'search_responsible' => 'Identificar Responsable',
        'responsible_data' => 'Datos del Responsable',
        'fine_data' => 'Datos de la Multa',
    ],

    'fields' => [
        'date_time' => 'Fecha y Hora de la Multa',
        'plate' => 'Placa del Vehículo',
        'due_date' => 'Fecha de Vencimiento',
        'value' => 'Valor',
        'infraction_number' => 'N. Infracción',
        'issuing_body' => 'Órgano Sancionador',
        'location' => 'Lugar',
        'city' => 'Ciudad',
        'state' => 'Estado',
        'description' => 'Descripcion',
        'type' => 'Tipo',
        'status' => 'Estado',
        'branch' => 'Sucursal',
        'client' => 'Cliente',
        'vehicle' => 'Vehículo',
        'contract_code' => 'Código del Contrato',
        'rental_code' => 'Código de la Locación',
        'code' => 'Código',
        'photo' => 'Foto de la Multa',
    ],

    'table' => [
        'plate' => 'Placa',
        'client' => 'Cliente',
        'type' => 'Tipo',
        'date_time' => 'Fecha/Hora',
        'value' => 'Valor',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    'badges' => [
        'type_contract' => 'Contrato',
        'type_rental' => 'Locación',
        'status_paid' => 'Pagado',
        'status_pending' => 'Pendiente',
        'status_unknown' => 'Sin tipo',
    ],

    'buttons' => [
        'search_responsible' => 'Buscar Responsable',
        'continue' => 'Continuar con este responsable',
        'mark_paid' => 'Marcar como Pagado',
        'mark_unpaid' => 'Revertir Pago',
    ],

    'messages' => [
        'no_records' => 'Ninguna multa encontrada',
        'load_error' => 'Error al cargar datos',
        'server_error' => 'Error al conectar con el servidor',
        'save_error' => 'Error al guardar',
        'created' => 'Multa registrada con exito!',
        'updated' => 'Multa actualizada con exito!',
        'deleted' => 'Multa eliminada con exito!',
        'marked_paid' => 'Multa marcada como pagada!',
        'marked_unpaid' => 'Pago revertido!',
        'not_found' => 'Multa no encontrada',
        'vehicle_not_found' => 'Vehículo no encontrado con esta placa',
        'responsible_found' => 'Responsable encontrado',
        'responsible_not_found' => 'Ningún contrato o locación encontrado para este vehículo en la fecha/hora indicada.',
        'required_fields' => 'Complete los campos obligatorios:',
        'saving' => 'Guardando...',
        'searching' => 'Buscando...',
        'confirm_delete' => 'Desea realmente eliminar esta multa?',
        'confirm_mark_paid' => 'Desea marcar esta multa como pagada?',
        'confirm_mark_unpaid' => 'Desea revertir el pago de esta multa?',
        'cannot_delete_paid' => 'No es posible eliminar una multa ya pagada.',
        'this_record' => 'esta multa',
        'select_doc_before_pdf' => 'Seleccione un documento antes de generar el PDF',
        'select_doc_before_send' => 'Seleccione un documento antes de enviar',
        'sending' => 'Enviando...',
        'send_success' => 'Documento enviado con exito',
        'send_error' => 'Error al enviar el documento',
        'send_connection_error' => 'Error de conexión al enviar',
    ],

    'filters' => [
        'all_types' => 'Todos los tipos',
        'type_contract' => 'Contrato',
        'type_rental' => 'Locación',
        'all_status' => 'Todos los estados',
        'paid' => 'Pagado',
        'pending' => 'Pendiente',
    ],

    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando',
        'of' => 'de',
        'records' => 'registros',
    ],

    'actions' => [
        'new' => 'Nueva Multa',
    ],

    'record_type' => 'multa',

    // =========================================================
    // Impresion (offcanvas-impressao.php)
    // =========================================================
    'print' => [
        'title' => 'Imprimir Multa',
        'fine_label' => 'Multa',
        'print_type' => 'Tipo de Documento',
        'notification' => 'Notificación al Cliente',
        'document' => 'Documento Personalizado',
        'receipt' => 'Comprobante de Pago',
        'indication_term' => 'Término de Indicación de Conductor',
        'select_document' => 'Seleccionar Documento',
        'select_document_placeholder' => 'Elija un modelo',
        'no_documents' => 'Ningún modelo registrado para Multa',
        'generate_pdf' => 'Generar PDF',
        'send_via' => 'Enviar por',
    ],

    // =========================================================
    // Plantillas PDF
    // =========================================================
    'pdf' => [
        'notification_title' => 'Notificación de Multa',
        'receipt_title' => 'Comprobante de Pago de Multa',
        'indication_title' => 'Término de Indicación de Conductor',
        'document_title' => 'Documento',
        'fine_data_section' => 'Datos de la Infracción',
        'vehicle_data_section' => 'Datos del Vehículo',
        'fine_origin_section' => 'Datos de la Multa',
        'client_section' => 'Datos del Cliente',
        'owner_section' => 'Datos del Propietario',
        'driver_section' => 'Datos del Conductor (rellenar)',
        'fine_number_label' => 'Número:',
        'date_label' => 'Fecha:',
        'ait_label' => 'AIT:',
        'infraction_code_label' => 'Código de Infracción:',
        'issuing_body_label' => 'Órgano Sancionador:',
        'location_label' => 'Lugar:',
        'city_state_label' => 'Ciudad/Estado:',
        'date_time_label' => 'Fecha/Hora:',
        'description_label' => 'Descripcion:',
        'plate_label' => 'Placa:',
        'brand_model_label' => 'Marca/Modelo:',
        'value_label' => 'Valor a Pagar',
        'amount_paid_label' => 'Valor Pagado',
        'discount_40_label' => 'Con descuento del 40%',
        'due_date_label' => 'Vencimiento',
        'fine_date_label' => 'Fecha de la Multa:',
        'client_name' => 'Nombre:',
        'client_document' => 'CPF/CNPJ:',
        'company_name_label' => 'Razon Social:',
        'driver_name' => 'Nombre',
        'driver_cpf' => 'CPF',
        'driver_cnh' => 'Licencia',
        'driver_address' => 'Dirección',
        'driver_city' => 'Ciudad',
        'driver_phone' => 'Telefono',
        'signature_place_label' => 'Lugar',
        'signature_date_label' => 'Fecha',
        'owner_signature' => 'Firma del Propietario',
        'driver_signature' => 'Firma del Conductor',
        'witness_1' => 'Testigo 1',
        'witness_2' => 'Testigo 2',
        'indication_declaration' => 'Declaro, bajo las penas de la ley, que el conductor identificado arriba fue el responsable por la infracción descrita.',
        'indication_footer' => 'Presentar este término al órgano sancionador dentro del plazo legal establecido.',
        'notification_text' => 'Estimado(a) :client, comunicamos que se registró una multa de tránsito vinculada al vehículo de placa :plate. El valor a pagar es de :value, con vencimiento en :due. Solicitamos la regularización dentro del plazo indicado.',
        'receipt_text' => 'Recibimos de :client, portador(a) del documento :document, la cantidad de :value, referente a la multa n. :fine_number del vehículo de placa :plate, ocurrida en :fine_date. Para claridad, firmamos el presente recibo.',
        'receipt_validity' => 'Este recibo tiene validez legal y comprueba el pago de la multa identificada arriba.',
        'generated_at' => 'Generado en :datetime',
        'page_label' => 'Página :page de :total',
    ],

    // =========================================================
    // Central de Multas (central.php)
    // =========================================================
    'central' => [
        'title' => 'Central de Multas',
        'search_placeholder' => 'Buscar (nombre, placa, AIT)',
        'add_fine' => 'Agregar Multa',
        'check_online' => 'Consultar Multas',
        'check_batch' => 'Consultar Lote',

        'kpi' => [
            'overdue' => 'Vencidas',
            'expiring_30d' => 'Vencen 30d',
            'on_time' => 'Al dia',
            'pending' => 'Pendientes',
            'paid' => 'Pagadas',
            'pending_value' => 'Valor Pendiente',
        ],

        'balance' => [
            'title' => 'Saldo Consultas',
            'manage' => 'Gestionar',
            'query' => 'Consulta',
            'event' => 'Evento',
            'indication' => 'Indicación',
        ],

        'origin' => [
            'title' => 'Origen',
            'manual' => 'Manual',
            'online_query' => 'Consulta Online',
            'auto_event' => 'Evento Automático',
        ],

        'nominations' => [
            'title' => 'Indicaciones',
            'view_all' => 'Ver todas',
            'pending_nomination' => 'Pendientes de indicación',
            'new_unprocessed' => 'Nuevas (no procesadas)',
            'sent' => 'Indicaciones enviadas',
        ],

        'automation' => [
            'title' => 'Automatizaciones',
            'auto_query' => 'Auto-consulta',
            'auto_query_help' => 'Consulta automáticamente las multas de los vehículos registrados en el intervalo elegido. El cobro se realiza por placa consultada, no por la cantidad de multas encontradas. Ejemplo: si una placa devuelve varias multas, se cobrará solo 1 consulta de esa placa.',
            'every' => 'cada',
            'auto_events' => 'Eventos automáticos',
            'auto_events_help' => 'Recibe notificaciones automáticas de Consulta Online cuando se identifiquen nuevos eventos de multas. Cada evento recibido consume saldo como Evento, separado del cobro de Consulta por placa.',
            'last_query' => 'Ultima consulta: :date',
            'interval_1d' => '1 dia',
            'interval_3d' => '3 días',
            'interval_7d' => '7 días',
            'interval_14d' => '14 días',
            'interval_30d' => '30 días',
            'online_query_requires_cnpj' => 'Consulta Online exige CNPJ. Registre una matriz o filial con CNPJ válido para activar las automatizaciones.',
            'online_query_multiple_cnpjs' => 'Hay más de un CNPJ registrado. Configure qué CNPJ se usará en Consulta Online antes de activar las automatizaciones.',
        ],

        'filters' => [
            'type_all' => 'Tipo: Todos',
            'type_contract' => 'Contrato',
            'type_rental' => 'Locación',
            'payment_all' => 'Pago: Todos',
            'payment_pending' => 'Pendientes',
            'payment_paid' => 'Pagadas',
            'due_all' => 'Venc.: Todos',
            'due_overdue' => 'Vencidas',
            'due_expiring' => 'Vencen 30d',
            'due_on_time' => 'Al dia',
            'origin_all' => 'Origen: Todos',
            'origin_manual' => 'Manual',
            'origin_online' => 'Consulta Online',
            'origin_event' => 'Evento Automático',
            'status_all' => 'Estado: Todos',
            'status_new' => 'Nuevo',
            'status_pending_nomination' => 'Pendiente Indicación',
            'status_nomination_sent' => 'Indicación Enviada',
            'status_nominated' => 'Indicado',
            'status_transferred' => 'Transferido',
        ],

        'table' => [
            'plate' => 'Placa',
            'client' => 'Cliente',
            'date' => 'Fecha',
            'infraction' => 'Infracción',
            'value' => 'Valor',
            'due' => 'Venc.',
            'payment' => 'Pago',
            'origin' => 'Origen',
            'status' => 'Estado',
            'actions' => 'Acciones',
        ],

        'pagination' => [
            'rows' => 'Filas:',
            'showing' => 'Mostrando :start-:end de :total',
        ],

        'ranking' => [
            'title' => 'Ranking de Vehículos con mas Multas',
            'position' => '#',
            'plate' => 'Placa',
            'model' => 'Modelo',
            'total' => 'Total',
            'pending' => 'Pendientes',
            'pending_value' => 'Valor Pendiente',
            'no_data' => 'No hay datos disponibles',
        ],

        'badges' => [
            'origin_query' => 'Consulta',
            'origin_event' => 'Evento',
            'origin_manual' => 'Manual',
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
        ],

        'confirm' => [
            'mark_paid_title' => 'Marcar como Pagado',
            'mark_paid_message' => 'Confirma marcar esta multa como pagada?',
            'revert_title' => 'Revertir Pago',
            'revert_message' => 'Confirma revertir el pago de esta multa?',
            'cannot_delete_paid' => 'No es posible eliminar una multa ya pagada',
            'activate_auto_query_title' => 'Activar Auto-consulta',
            'activate_auto_query_message' => 'La auto-consulta realizará consultas automáticas periódicas para todos los vehículos brasileños. Cada consulta consume saldo. Desea activar?',
            'activate_auto_events_title' => 'Activar Eventos Automaticos',
            'activate_auto_events_message' => 'Los eventos automáticos registran notificaciones en tiempo real sobre nuevas infracciones. Cada evento consume saldo. Desea activar?',
            'confirm_activate' => 'Si, activar',
        ],

        'toast' => [
            'fine_deleted' => 'Multa eliminada con exito',
            'fine_marked_paid' => 'Multa marcada como pagada',
            'payment_reverted' => 'Pago revertido',
            'config_error' => 'Error al actualizar configuración',
        ],

        'actions' => [
            'edit' => 'Editar',
            'nominate' => 'Indicar Real Infractor',
            'mark_paid' => 'Marcar como Pagado',
            'mark_unpaid' => 'Marcar como No Pagado',
            'delete' => 'Eliminar',
            'print' => 'Imprimir',
        ],
    ],

    // =========================================================
    // Indicaciones de Conductor (indicacao.php)
    // =========================================================
    'indicacoes' => [
        'title' => 'Indicaciones de Conductor',
        'new_nomination' => 'Nueva Indicación',

        'summary' => [
            'total' => 'Total',
            'sent' => 'Enviadas',
            'pending' => 'Pendientes',
            'accepted' => 'Aceptadas',
            'rejected' => 'Rechazadas',
        ],

        'filters' => [
            'all_types' => 'Todos los tipos',
            'real_offender' => 'Real Infractor',
            'main_driver' => 'Conductor Principal',
            'all_status' => 'Todos los estados',
            'sent' => 'Enviado',
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
            'deleted' => 'Eliminado',
            'expired' => 'Expirado',
            'plate' => 'Placa',
        ],

        'table' => [
            'date' => 'Fecha',
            'type' => 'Tipo',
            'plate' => 'Placa',
            'nominee' => 'Indicado',
            'ait' => 'AIT',
            'status' => 'Estado',
            'actions' => 'Acciones',
        ],

        'pagination' => [
            'rows' => 'Filas:',
            'showing' => 'Mostrando :start-:end de :total',
        ],

        'badges' => [
            'real_offender' => 'Real Infractor',
            'main_driver' => 'Conductor Principal',
        ],

        'messages' => [
            'loading' => 'Cargando...',
            'no_nominations' => 'Ninguna indicación encontrada',
        ],

        'confirm' => [
            'cancel_title' => 'Cancelar Indicación',
            'cancel_message' => 'Está seguro de que desea cancelar esta indicación?',
        ],

        'actions' => [
            'check_status' => 'Consultar estado',
            'cancel' => 'Cancelar',
        ],
    ],

    // =========================================================
    // Saldo de Consultas (saldo.php)
    // =========================================================
    'saldo' => [
        'title' => 'Saldo de Consultas',

        'cards' => [
            'current_balance' => 'Saldo Actual',
            'total_spent' => 'Total Gastado',
            'total_recharged' => 'Total Recargado',
            'prices_title' => 'Precios por Operación',
            'query' => 'Consulta:',
            'event' => 'Evento:',
            'indication' => 'Indicación:',
        ],

        'buttons' => [
            'pix' => 'PIX',
            'card' => 'Tarjeta',
            'save' => 'Guardar',
        ],

        'auto_recharge' => [
            'title' => 'Auto-recarga',
            'threshold_label' => 'Recargar cuando el saldo este por debajo de',
            'value_label' => 'Valor de la recarga',
            'requires_card' => 'Requiere tarjeta de crédito guardada. El cobro se realizará automáticamente via Stripe.',
            'card_saved' => 'Tarjeta guardada configurada',
        ],

        'history_title' => 'Historial de Transacciones',

        'filters' => [
            'type_all' => 'Tipo: Todos',
            'type_queries' => 'Consultas',
            'type_events' => 'Eventos',
            'type_indications' => 'Indicaciones',
            'type_pix' => 'Recarga PIX',
            'type_card' => 'Recarga Tarjeta',
            'until' => 'hasta',
        ],

        'table' => [
            'date' => 'Fecha',
            'type' => 'Tipo',
            'description' => 'Descripcion',
            'value' => 'Valor',
            'balance' => 'Saldo',
            'status' => 'Estado',
        ],

        'pagination' => [
            'rows' => 'Filas:',
            'showing' => 'Mostrando :start-:end de :total registros',
        ],

        'badges' => [
            'query' => 'Consulta',
            'event' => 'Evento',
            'indication' => 'Indicación',
            'pix' => 'PIX',
            'card' => 'Tarjeta',
            'confirmed' => 'Confirmado',
            'pending' => 'Pendiente',
            'failed' => 'Fallido',
        ],

        'messages' => [
            'loading' => 'Cargando...',
            'no_transactions' => 'Ninguna transacción encontrada',
            'auto_recharge_updated' => 'Auto-recarga actualizada',
            'save_error' => 'Error al guardar',
        ],
    ],
];
