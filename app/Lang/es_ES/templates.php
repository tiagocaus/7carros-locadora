<?php

/**
 * Traducciones de Plantillas de Mensajes - Español
 *
 * Contiene los nombres y descripciones de los tipos de plantillas disponibles.
 */

return [
    'installment' => [
        'with_total' => 'Cuota :parcela de :total',
        'without_total' => 'Cuota :parcela',
    ],
    // Tipos de Plantillas
    'types' => [
        // Onboarding
        'welcome' => 'Bienvenida',
        'welcome_description' => 'Mensaje enviado al registrar un nuevo cliente',
        'welcome_desc' => 'Mensaje enviado al registrar un nuevo cliente',

        'cliente_nova_senha' => 'Restablecimiento de contraseña del cliente',
        'cliente_nova_senha_desc' => 'Enviado al cliente con una nueva contraseña de acceso',
        'cliente_nova_senha_link_desc' => 'Enviado al cliente con un enlace seguro para restablecer la contraseña',

        'funcionario_nova_senha' => 'Restablecimiento de contraseña del empleado',
        'funcionario_nova_senha_desc' => 'Enviado al empleado con una nueva contraseña segura de acceso al panel',

        // Alquiler
        'rental_confirmation' => 'Confirmación de Alquiler',
        'rental_confirmation_description' => 'Enviado cuando se confirma un alquiler',

        'contract_confirmation' => 'Confirmación de Contrato',
        'contract_confirmation_description' => 'Enviado cuando se firma un contrato',

        // Recordatorios
        'return_reminder' => 'Recordatorio de Devolución',
        'return_reminder_description' => 'Aviso antes de la fecha de devolución programada',

        'cnh_expiring' => 'Licencia por Vencer',
        'cnh_expiring_description' => 'Aviso cuando la licencia de conducir del cliente está por vencer',

        // Facturación
        'payment_reminder' => 'Recordatorio de Pago',
        'payment_reminder_description' => 'Aviso de factura próxima a vencer',

        'invoice_generated' => 'Factura Generada',
        'invoice_generated_description' => 'Enviado cuando se genera una nueva factura',

        'overdue_notice' => 'Aviso de Mora',
        'overdue_notice_description' => 'Notificación de factura vencida',

        'payment_received' => 'Pago Recibido',
        'payment_received_description' => 'Confirmación de recepción de pago',

        // Otros
        'general_notification' => 'Notificación General',
        'general_notification_description' => 'Plantilla para notificaciones diversas',
    ],

    // Categorías
    'categories' => [
        'onboarding' => 'Registro',
        'rental' => 'Alquiler',
        'reminder' => 'Recordatorios',
        'billing' => 'Facturación',
        'notification' => 'Notificaciones',
    ],

    // Canales
    'channels' => [
        'email' => 'Correo electrónico',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    // Mensajes de UI
    'ui' => [
        'title' => 'Plantillas de Mensajes',
        'subtitle' => 'Personaliza los mensajes enviados a los clientes',
        'search_placeholder' => 'Buscar plantillas...',
        'select_template' => 'Selecciona una plantilla para editar',
        'available_variables' => 'Variables Disponibles',
        'preview' => 'Vista Previa',
        'editor' => 'Editor',
        'restore_default' => 'Restaurar Predeterminado',
        'save_changes' => 'Guardar Cambios',
        'unsaved_changes' => 'Tienes cambios sin guardar. ¿Deseas salir?',
        'template_saved' => '¡Plantilla guardada con éxito!',
        'template_restored' => 'Plantilla restaurada a los valores predeterminados',
        'no_templates' => 'No hay plantillas disponibles',
        'custom_template' => 'Personalizado',
        'default_template' => 'Predeterminado',
        'subject' => 'Asunto',
        'content' => 'Contenido',
        'content_plain' => 'Contenido (texto plano)',
        'locale' => 'Idioma',
        'channel' => 'Canal',
        'insert_variable' => 'Clic para insertar',
    ],

    // Validación
    'validation' => [
        'entity_not_allowed' => 'La entidad ":entity" no está permitida en esta plantilla',
        'variable_not_found' => 'La variable ":variable" no existe',
        'content_required' => 'El contenido de la plantilla es obligatorio',
        'subject_required_email' => 'El asunto es obligatorio para plantillas de correo electrónico',
    ],
];
