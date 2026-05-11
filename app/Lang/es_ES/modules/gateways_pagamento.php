<?php

/**
 * Traducciones del módulo Gateways de Pago - Español (España)
 */

return [
    'title' => 'Gateways de Pago',
    'title_singular' => 'Gateway de Pago',
    'new_title' => 'Nuevo Gateway de Pago',
    'edit_title' => 'Editar Gateway de Pago',

    // Secciones
    'sections' => [
        'gateway_data' => 'Datos del Gateway',
        'payment_methods' => 'Métodos de Pago Habilitados',
        'payment_methods_desc' => 'Seleccione qué métodos de pago estarán disponibles para este gateway.',
        'credentials' => 'Credenciales',
        'credentials_desc' => 'Configure las credenciales de acceso al gateway.',
        'webhook' => 'Webhook',
        'webhook_desc' => 'Configure esta URL en el panel del gateway para recibir notificaciones de pago.',
    ],

    // Campos
    'fields' => [
        'gateway' => 'Gateway',
        'name' => 'Nombre de identificación',
        'branches' => 'Sucursales',
        'currencies' => 'Monedas Aceptadas',
        'environment' => 'Entorno',
        'status' => 'Estado',
        'display_order' => 'Orden de visualización',
        'methods' => 'Métodos',
        'webhook_url' => 'URL del Webhook',
    ],

    // Métodos de pago
    'methods' => [
        'pix' => 'PIX',
        'pix_desc' => 'Pago instantáneo',
        'boleto' => 'Boleto',
        'boleto_desc' => 'Boleto bancario',
        'credit_card' => 'Tarjeta de Crédito',
        'credit_card_desc' => 'Pago en cuotas disponible',
        'debit_card' => 'Tarjeta de Débito',
        'debit_card_desc' => 'Débito en cuenta',
        'none' => 'Ninguno',
    ],

    // Entorno
    'environment' => [
        'sandbox' => 'Sandbox (Pruebas)',
        'production' => 'Producción',
    ],

    // Estado
    'status_options' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Países
    'countries' => [
        'BR' => 'Brasil',
        'PY' => 'Paraguay',
        'INTL' => 'Internacional',
    ],

    // Monedas
    'currencies' => [
        'BRL' => 'Real Brasileño',
        'USD' => 'Dólar Estadounidense',
        'EUR' => 'Euro',
        'GBP' => 'Libra Esterlina',
        'CAD' => 'Dólar Canadiense',
        'AUD' => 'Dólar Australiano',
        'JPY' => 'Yen Japonés',
        'MXN' => 'Peso Mexicano',
        'CHF' => 'Franco Suizo',
        'PYG' => 'Guaraní Paraguayo',
        'ARS' => 'Peso Argentino',
        'CLP' => 'Peso Chileno',
        'COP' => 'Peso Colombiano',
        'PEN' => 'Sol Peruano',
        'UYU' => 'Peso Uruguayo',
    ],

    // Sugerencias
    'hints' => [
        'branches' => 'Deje en blanco para que esté disponible en todas las sucursales.',
        'currencies' => 'Seleccione las monedas que acepta este gateway. Las opciones disponibles dependen del gateway seleccionado.',
        'display_order' => 'El número menor aparece primero en la lista de opciones.',
        'name_placeholder' => 'Ej: Asaas Principal, Stripe Producción',
    ],

    // Desplegables
    'dropdowns' => [
        'select_gateway' => 'Seleccione un gateway...',
        'select_gateway_first' => 'Seleccione un gateway primero',
        'all_branches' => 'Todas las sucursales',
        'no_branches' => 'Ninguna sucursal registrada',
        'no_branches_short' => 'Ninguna sucursal',
        'no_currencies' => 'Ninguna moneda seleccionada',
        'load_error' => 'Error al cargar',
    ],

    // Tabla
    'table' => [
        'gateway' => 'Gateway',
        'branch' => 'Sucursal',
        'methods' => 'Métodos',
        'environment' => 'Entorno',
        'status' => 'Estado',
        'actions' => 'Acciones',
        'all_branches' => 'Todas',
    ],

    // Acciones
    'actions' => [
        'test_connection' => 'Probar Conexión',
        'testing' => 'Probando...',
        'copy_url' => 'Copiar URL',
        'view_docs' => 'Ver documentación',
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar gateway...',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'Ningún gateway configurado',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar datos',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar el registro',
        'status_error' => 'Error al cambiar el estado',
        'test_success' => '¡Conexión exitosa! Credenciales validadas.',
        'test_fail' => 'Fallo en la conexión. Verifique las credenciales.',
        'test_error' => 'Error al probar la conexión',
        'not_found' => 'Registro no encontrado',
        'gateway_required' => 'Por favor, seleccione un gateway',
        'name_required' => 'Por favor, introduzca el nombre de identificación',
        'currency_required' => 'Por favor, seleccione al menos una moneda',
        'save_error' => 'Error al guardar',
        'save_success' => 'Guardado correctamente',
        'load_branches_error' => 'Error al cargar sucursales',
        'branch_fallback' => 'Sucursal :id',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro para modal de eliminación
    'record_type' => 'gateway_pagamento',
];
