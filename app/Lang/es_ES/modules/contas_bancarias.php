<?php

/**
 * Traducciones del módulo Cuentas Bancarias - Español (España)
 */

return [
    'title' => 'Cuentas Bancarias/Caja',
    'title_singular' => 'Cuenta Bancaria/Caja',
    'new_title' => 'Nueva Cuenta',
    'edit_title' => 'Editar Cuenta',

    // Secciones
    'sections' => [
        'account_data' => 'Datos de la Cuenta',
        'bank_data' => 'Datos Bancarios',
        'notes' => 'Observaciones',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre',
        'type' => 'Tipo',
        'status' => 'Estado',
        'bank' => 'Banco',
        'branch' => 'Sucursal',
        'account_number' => 'Número de Cuenta',
        'notes' => 'Observaciones',
    ],

    // Opciones de tipo
    'type_options' => [
        'bank_account' => 'Cuenta Bancaria',
        'cash' => 'Caja',
    ],

    // Badges
    'badges' => [
        'type_bank' => 'Bancaria',
        'type_cash' => 'Caja',
        'status_active' => 'Activo',
        'status_inactive' => 'Inactivo',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar cuenta...',
        'name_example' => 'Ej: Caja Principal, Banco Santander',
        'bank_example' => 'Ej: Banco Santander, BBVA',
        'branch_example' => 'Ej: 1234-5',
        'account_example' => 'Ej: 12345-6',
        'notes_example' => 'Información adicional sobre la cuenta...',
    ],

    // Tabla
    'table' => [
        'name' => 'Nombre',
        'type' => 'Tipo',
        'bank' => 'Banco',
        'branch' => 'Sucursal',
        'account' => 'Cuenta',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron cuentas',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar las cuentas',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar la cuenta',
        'this_record' => 'esta cuenta',
        'not_found' => 'Cuenta no encontrada',
        'load_account_error' => 'Error al cargar los datos de la cuenta',
        'name_required' => 'Por favor, introduzca el nombre de la cuenta',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar la cuenta',
        'saved' => 'Cuenta guardada correctamente',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'cuenta',
];
