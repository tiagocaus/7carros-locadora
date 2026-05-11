<?php

/**
 * Traducciones del módulo Fornecedores - Español (España)
 */

return [
    'title' => 'Proveedores',
    'title_singular' => 'Proveedor',
    'new_title' => 'Nuevo Proveedor',
    'edit_title' => 'Editar Proveedor',

    // Secciones
    'sections' => [
        'basic_data' => 'Datos Basicos',
        'address' => 'Dirección',
        'investor' => 'Inversor',
        'observations' => 'Observaciones',
    ],

    // Campos
    'fields' => [
        'type' => 'Tipo',
        'cpf_cnpj' => 'CPF/CNPJ',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'name' => 'Nombre',
        'company_name' => 'Razon Social',
        'trade_name' => 'Nombre Comercial',
        'rg' => 'RG',
        'state_registration' => 'Inscripcion Estatal',
        'municipal_registration' => 'Inscripcion Municipal',
        'email' => 'Email',
        'phone1' => 'Telefono 1',
        'phone2' => 'Telefono 2',
        'zip_code' => 'Código Postal',
        'street' => 'Calle',
        'number' => 'Número',
        'complement' => 'Complemento',
        'neighborhood' => 'Barrio',
        'city' => 'Ciudad',
        'state' => 'Provincia',
        'country' => 'Pais',
        'supplies_vehicles' => 'Suministra Vehículos',
        'is_investor' => 'Es Inversor?',
        'split_gateway' => 'Pasarela para Split',
        'split_account_id' => 'ID Cuenta/Wallet',
        'pix_key' => 'Clave PIX',
        'pix_key_type' => 'Tipo de Clave PIX',
        'bank_code' => 'Código de Banco',
        'bank_branch' => 'Sucursal',
        'bank_account' => 'Cuenta',
        'bank_account_type' => 'Tipo de Cuenta',
    ],

    // Opciones de tipo
    'type_options' => [
        'PJ' => 'Persona Juridica',
        'PF' => 'Persona Fisica',
    ],

    // Opciones de pasarela
    'gateway_options' => [
        'none' => 'Ninguno (manual)',
        'asaas' => 'Asaas',
        'gerencianet' => 'Gerencianet',
        'stripe' => 'Stripe',
        'inter' => 'Banco Inter',
    ],

    // Opciones de tipo de clave PIX
    'pix_type_options' => [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'Email',
        'telefone' => 'Telefono',
        'aleatoria' => 'Clave Aleatoria',
    ],

    // Opciones de tipo de cuenta
    'account_type_options' => [
        'corrente' => 'Corriente',
        'poupanca' => 'Ahorros',
    ],

    // Marcadores de posicion
    'placeholders' => [
        'search' => 'Buscar...',
        'split_account' => 'Ej: wal_xxxx',
        'bank_code' => 'Ej: 001',
        'select' => 'Seleccione...',
    ],

    // Filtros
    'filters' => [
        'all' => 'Todos',
        'suppliers' => 'Proveedores',
        'investors' => 'Inversores',
    ],

    // Tabla
    'table' => [
        'name' => 'Nombre',
        'cpf_cnpj' => 'CPF/CNPJ',
        'phone' => 'Telefono',
        'investor' => 'Inversor',
        'actions' => 'Acciones',
    ],

    // Insignias
    'badges' => [
        'investor_yes' => 'Si',
        'investor_no' => 'No',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron registros',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar',
        'this_record' => 'este registro',
        'load_data_error' => 'Error al cargar datos',
        'load_supplier_error' => 'Error al cargar datos del proveedor',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar',
        'save_supplier_error' => 'Error al guardar proveedor',
        'created' => 'Proveedor creado con exito!',
        'updated' => 'Proveedor actualizado con exito!',
    ],

    // Paginacion
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'proveedor',
];
