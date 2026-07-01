<?php

/**
 * Traducciones del modulo Logs del Sistema - Espanol
 */

return [
    'title' => 'Logs del Sistema',
    'search_placeholder' => 'Buscar log...',
    'tabs' => [
        'audit' => 'Auditoría',
        'messages' => 'Envíos',
    ],
    'filters' => [
        'all_channels' => 'Todos los canales',
        'all_statuses' => 'Todos los estados',
    ],
    'table' => [
        'date' => 'Fecha',
        'user' => 'Usuario',
        'message' => 'Mensaje',
        'ip' => 'IP',
        'actions' => 'Acciones',
        'channel' => 'Canal',
        'recipient' => 'Destinatario',
        'status' => 'Estado',
        'error' => 'Error',
        'processed_at' => 'Procesado en',
    ],
    'channels' => [
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
    ],
    'status' => [
        'pending' => 'Pendiente',
        'processing' => 'Procesando',
        'sent' => 'Enviado',
        'failed' => 'Falló',
        'skipped' => 'Ignorado',
    ],
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
        'showing_lazy' => 'Mostrando registros :start-:end',
    ],
    'no_records' => 'Ningún log encontrado',
    'details_title' => 'Detalles del Cambio',
    'payload_title' => 'Detalles del Envío',
    'empty_value' => '(vacío)',
    'unrecognized_format' => 'Formato de datos no reconocido.',
    'view_details' => 'Ver detalles',
    'no_details' => 'Sin detalles',
    'messages' => [
        'load_error' => 'Error al cargar logs',
        'server_error' => 'Error al conectar con el servidor',
        'sent_hint' => 'Estado enviado significa que el worker procesó el mensaje y el proveedor aceptó la solicitud; no confirma lectura o entrega final en el dispositivo.',
    ],
];
