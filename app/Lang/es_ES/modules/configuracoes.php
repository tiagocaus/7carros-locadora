<?php

/**
 * Traducciones del módulo Configuraciones - Español (España)
 */

return [
    'templates_title' => 'Plantillas de Mensaje',
    'templates_description' => 'Personalice las plantillas de email, WhatsApp y SMS enviadas a los clientes.',

    'categories' => [
        'all' => 'Todos',
        'onboarding' => 'Onboarding',
        'rental' => 'Alquiler',
        'reminder' => 'Recordatorios',
        'billing' => 'Financiero',
    ],

    'category_labels' => [
        'onboarding' => 'Onboarding',
        'rental' => 'Alquiler',
        'reminder' => 'Recordatorio',
        'billing' => 'Financiero',
    ],

    'edit_title' => 'Editar Plantilla',
    'edit_title_prefix' => 'Editar plantilla:',

    'labels' => [
        'customized' => 'Personalizado',
        'using_default' => 'Usando plantilla del sistema',
        'email_subject' => 'Asunto del Email',
        'content' => 'Contenido',
        'characters' => 'caracteres',
        'available_variables' => 'Variables Disponibles',
        'click_to_insert' => 'Haga clic para insertar en el editor',
        'subject' => 'Asunto:',
        'no_subject' => '(sin asunto)',
        'content_label' => 'Contenido:',
    ],

    'placeholders' => [
        'email_subject' => 'Ej: Confirmación de Alquiler #{{alquiler.número}}',
        'message_content' => 'Escriba el contenido del mensaje...',
    ],

    'warnings' => [
        'sms_split' => 'SMS con más de 160 caracteres será dividido',
    ],

    'buttons' => [
        'preview' => 'Vista previa',
        'restore_default' => 'Restaurar Predeterminado',
    ],

    'modals' => [
        'attention' => 'Atención',
        'unsaved_changes' => '¿Tiene cambios sin guardar. ¿Desea continuar?',
        'continue' => 'Continuar',
        'restore_title' => 'Restaurar Plantilla Predeterminada',
        'restore_confirm' => '¿Está seguro de que desea restaurar esta plantilla a la predeterminada del sistema?',
        'restore_warning' => 'Sus personalizaciones se perderán.',
        'restore_btn' => 'Restaurar',
        'preview_title' => 'Vista Previa de la Plantilla',
        'close' => 'Cerrar',
    ],

    'messages' => [
        'loading' => 'Cargando plantillas...',
        'loading_page' => 'Cargando...',
        'load_error' => 'Error al cargar plantillas.',
        'no_templates' => 'Ninguna plantilla encontrada.',
        'no_variables' => 'Ninguna variable disponible',
        'saving' => 'Guardando...',
        'save_success' => '¡Plantilla guardada con éxito!',
        'save_error' => 'Error al guardar plantilla',
        'preview_error' => 'Error al generar vista previa',
        'restoring' => 'Restaurando...',
        'restore_success' => 'Plantilla restaurada a la predeterminada del sistema',
        'restore_error' => 'Error al restaurar plantilla',
    ],
];
