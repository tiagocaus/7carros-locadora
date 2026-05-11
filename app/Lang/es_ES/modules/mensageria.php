<?php

/**
 * Traducciones del modulo Mensajeria - Espanol (Espana)
 */

return [
    'title' => 'Mensajeria WhatsApp, SMS y SMTP',
    'subtitle' => 'Mensajeria: WhatsApp, SMS y SMTP(Mail)',

    // Tipos de conexion
    'types' => [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'smtp' => 'SMTP (Mail)',
    ],

    // Comun (compartido entre sub-vistas)
    'common' => [
        'connection' => 'Conexión',
        'branches_label' => 'Empresas/Sucursales',
        'branches_desc' => 'Seleccione las empresas que utilizarán esta conexión',
        'no_branches' => 'Ninguna empresa disponible',
        'already_linked' => 'Ya vinculada',
        'none' => 'Ninguna',
        'load_error' => 'Error al cargar datos',
        'load_branches_error' => 'Error al cargar empresas',
        'load_connection_error' => 'Error al cargar conexión',
        'fill_required' => 'Complete todos los campos obligatorios',
        'select_branch' => 'Seleccione al menos una empresa',
        'connection_id_missing' => 'ID de la conexión no informado',
    ],

    // Tabla
    'table' => [
        'type' => 'Tipo',
        'linked_branches' => 'Empresas Vinculadas',
        'identifier' => 'Identificador',
        'status' => 'Estado',
        'actions' => 'Acciones',
        'no_records' => 'Ninguna conexión encontrada',
        'load_error_branches' => 'Error al cargar',
    ],

    // Botones
    'buttons' => [
        'new_whatsapp' => 'Nuevo WhatsApp',
        'new_sms' => 'Nuevo SMS',
        'new_smtp' => 'Nuevo SMTP',
    ],

    // Busqueda
    'search_placeholder' => 'Buscar conexión...',

    // Paginacion
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Insignias de estado
    'status' => [
        'connected' => 'Conectado',
        'connecting' => 'Conectando',
        'disconnected' => 'Desconectado',
        'validated' => 'Validado',
        'pending' => 'Pendiente',
        'invalid' => 'Invalido',
        'unknown' => 'Desconocido',
    ],

    // Titulos de acciones (botones en la tabla)
    'actions' => [
        'test' => 'Probar',
        'restart' => 'Reiniciar',
        'disconnect' => 'Desconectar',
        'connect' => 'Conectar',
        'recreate' => 'Recrear conexión',
        'test_sms' => 'Probar SMS',
        'check_balance' => 'Consultar Saldo',
        'validate_credentials' => 'Validar Credenciales',
        'test_email' => 'Probar Email',
        'validate_connection' => 'Validar Conexión',
    ],

    // Titulos de offcanvas
    'offcanvas' => [
        'new_whatsapp' => 'Nueva Conexión WhatsApp',
        'edit_whatsapp' => 'Editar Conexión WhatsApp',
        'connect_whatsapp' => 'Conectar WhatsApp',
        'test_whatsapp' => 'Probar WhatsApp',
        'new_sms' => 'Nueva Conexión SMS',
        'edit_sms' => 'Editar Conexión SMS',
        'test_sms' => 'Probar SMS',
        'new_smtp' => 'Nueva Conexión SMTP',
        'edit_smtp' => 'Editar Conexión SMTP',
        'test_smtp' => 'Probar SMTP',
    ],

    // Confirmaciones
    'confirms' => [
        'delete' => 'Desea eliminar la conexión ":name"?',
        'disconnect' => 'Desea realmente desconectar esta conexión?',
        'restart' => 'Desea reiniciar esta conexión? La conexión será restablecida.',
    ],

    // Mensajes
    'messages' => [
        // SMTP
        'smtp_created' => 'Conexión SMTP creada con exito!',
        'smtp_updated' => 'Conexión actualizada con exito!',
        'smtp_deleted' => 'Conexión SMTP eliminada con exito',
        'smtp_validated' => 'Conexión SMTP validada con exito!',
        'smtp_validation_failed' => 'Fallo en la validación',
        'smtp_create_error' => 'Error al crear conexión',
        'smtp_update_error' => 'Error al actualizar',
        'smtp_delete_error' => 'Error al eliminar conexión',
        'smtp_validate_error' => 'Error al validar',

        // WhatsApp
        'whatsapp_created' => 'Conexión creada! Escanee el QR Code para conectar.',
        'whatsapp_created_short' => 'Conexión creada! Escanee el QR Code.',
        'whatsapp_updated' => 'Conexión actualizada con exito!',
        'whatsapp_deleted' => 'Conexión WhatsApp eliminada con exito',
        'whatsapp_disconnected' => 'Desconectado con exito',
        'whatsapp_restarted' => 'Conexión reiniciada. Espere la reconexión...',
        'whatsapp_recreated' => 'Instancia recreada! Abriendo QR Code...',
        'whatsapp_disconnect_error' => 'Error al desconectar',
        'whatsapp_restart_error' => 'Error al reiniciar',
        'whatsapp_recreate_error' => 'Error al recrear',
        'whatsapp_create_error' => 'Error al crear conexión',
        'whatsapp_update_error' => 'Error al actualizar conexión',
        'whatsapp_delete_error' => 'Error al eliminar conexión',

        // SMS
        'sms_created' => 'Conexión SMS creada con exito!',
        'sms_updated' => 'Conexión SMS actualizada con exito!',
        'sms_deleted' => 'Conexión SMS eliminada con exito',
        'sms_validated' => 'Credenciales validadas con exito!',
        'sms_validation_failed' => 'Credenciales invalidas',
        'sms_create_error' => 'Error al crear conexión',
        'sms_update_error' => 'Error al actualizar conexión',
        'sms_delete_error' => 'Error al eliminar conexión',
        'sms_validate_error' => 'Error al validar',
        'sms_balance' => 'Saldo: :currency :balance',
        'sms_balance_error' => 'Error al consultar saldo',

        // Pruebas
        'test_sent' => 'Prueba enviada!',
        'test_success' => 'Enviado con exito!',
        'test_error' => 'Error al enviar',
        'email_sent' => 'Email enviado!',
        'email_test_success' => 'Email de prueba enviado con exito!',
        'email_test_error' => 'Fallo al enviar email de prueba',
        'email_test_send_error' => 'Error al enviar email de prueba',
        'sms_sent' => 'SMS enviado!',
        'sms_test_success' => 'SMS de prueba enviado con exito!',
        'sms_test_error' => 'Fallo al enviar SMS de prueba',
        'sms_test_send_error' => 'Error al enviar SMS de prueba',
        'provide_email' => 'Informe un email para prueba',
        'provide_valid_email' => 'Informe un email valido',
        'provide_phone' => 'Informe un telefono para prueba',
        'provide_valid_phone' => 'Informe un telefono valido',
        'sending_email' => 'Enviando email...',
        'sending_sms' => 'Enviando SMS...',

        // QR Code
        'qr_generating' => 'Generando QR Code...',
        'qr_scan' => 'Escanee el QR Code con su WhatsApp',
        'qr_error' => 'Error al generar QR Code',
        'qr_connect_error' => 'Error al conectar',
        'qr_waiting' => 'Esperando conexión...',
        'qr_connected' => 'Conectado!',
        'server_error' => 'Error al conectar con el servidor',
    ],

    // SMTP especifico
    'smtp' => [
        'provider' => 'Proveedor',
        'connection_name' => 'Nombre de la Conexión',
        'server' => 'Servidor SMTP',
        'port' => 'Puerto',
        'encryption' => 'Cifrado',
        'encryption_none' => 'Ninguno',
        'auth_email' => 'Email de Autenticacion',
        'password' => 'Contraseña / App Password',
        'from_email' => 'Email Remitente',
        'from_name' => 'Nombre Remitente',
        'reply_to' => 'Email de Respuesta (opcional)',
        'daily_limit' => 'Límite Diario (opcional)',
        'daily_limit_hint' => 'Deje vacío para sin límite',
        'password_hint_gmail' => 'Para Gmail, use una <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="text-blue-600 hover:underline">contraseña de aplicación</a>',
        'password_hint_custom' => 'Consulte la documentacion de su proveedor SMTP',
        'password_hint_default' => 'Use la contraseña o App Password del proveedor',
        'password_change_hint' => 'Cambiar la contraseña revalidara la conexión',
        'keep_blank' => 'Deje en blanco para mantener',
        'provider_settings' => 'Configuraciones del proveedor:',
        'create_validate' => 'Crear y Validar Conexión',
        'test_email_label' => 'Email para prueba',
        'test_email_hint' => 'Un email de prueba será enviado a esta dirección',
        'send_test' => 'Enviar Email de Prueba',
    ],

    // Placeholders SMTP
    'smtp_placeholders' => [
        'name' => 'Ej: Email Principal',
        'server' => 'smtp.suservidor.com',
        'auth_email' => 'su@email.com',
        'password' => 'Contraseña o contraseña de aplicación',
        'from_email' => 'noreply@suempresa.com',
        'from_name' => 'Su Empresa',
        'reply_to' => 'contacto@suempresa.com',
        'daily_limit' => 'Ej: 500',
    ],

    // WhatsApp especifico
    'whatsapp' => [
        'create_connection' => 'Crear Conexión WhatsApp',
        'send_text' => 'Enviar Texto',
        'send_image' => 'Enviar Imagen',
        'send_document' => 'Enviar Documento',
        'instance_label' => 'Instancia',
    ],

    // SMS especifico
    'sms' => [
        'provider' => 'Proveedor',
        'sender_id' => 'Sender ID (Remitente)',
        'sender_id_hint' => 'Max 11 caracteres alfanumericos',
        'username' => 'Username ClickSend',
        'api_key' => 'API Key',
        'api_credentials_hint' => 'Encuentrelo en: ClickSend Dashboard > Developers > API Credentials',
        'api_key_change_hint' => 'Cambiar la API Key revalidara las credenciales',
        'create_validate' => 'Crear y Validar',
        'test_phone_label' => 'Telefono para prueba',
        'test_phone_hint' => 'Formato: código del pais + prefijo + número',
        'test_phone_placeholder' => '34 612 345 678',
        'send_test' => 'Enviar SMS de Prueba',
        'sender_id_short' => 'Sender ID',
    ],
];
