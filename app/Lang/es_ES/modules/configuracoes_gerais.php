<?php

/**
 * Traducciones del módulo Configuraciones Generales - Español (España)
 */

return [
    'title' => 'Configuraciones Generales',

    'sections' => [
        'locale' => 'Localización y Formato',
        'notifications' => 'Notificaciones',
        'print' => 'Impresión',
        'sequences' => 'Secuencias de Numeración',
        'sequences_desc' => 'Defina el próximo número a utilizar en cada tipo de documento. El valor no puede ser menor que el actual.',
    ],

    'fields' => [
        'locale' => 'Idioma',
        'currency' => 'Moneda',
        'date_format' => 'Formato de Fecha',
        'datetime_format' => 'Formato de Fecha/Hora',
        'notification_title' => 'Título de Notificaciones',
        'notification_title_placeholder' => 'Ej: Nombre de su empresa de alquiler',
        'next_rental_number' => 'Próximo Alquiler',
        'next_contract_number' => 'Próximo Contrato',
        'next_financial_number' => 'Próximo Financiero',
    ],

    'notifications' => [
        'sms_title' => 'SMS',
        'sms_desc' => 'Enviar notificaciones por SMS',
        'email_title' => 'Correo Electrónico',
        'email_desc' => 'Enviar notificaciones por correo',
        'whatsapp_title' => 'WhatsApp',
        'whatsapp_desc' => 'Enviar notificaciones por WhatsApp',
    ],

    'print' => [
        'bold_variables' => 'Variables en Negrita',
        'bold_variables_desc' => 'Resaltar variables en documentos impresos',
        'remove_yellow_stripe' => 'Quitar Franja Amarilla',
        'remove_yellow_stripe_desc' => 'Quitar resaltado amarillo de los campos',
    ],

    'messages' => [
        'save_success' => '¡Configuraciones guardadas con éxito!',
        'save_error' => 'Error al guardar configuraciones',
        'load_error' => 'Error al cargar configuraciones',
    ],
];
