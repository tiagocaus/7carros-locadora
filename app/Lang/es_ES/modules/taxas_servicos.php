<?php

/**
 * Traducciones del módulo Tasas y Servicios - Español (España)
 */

return [
    'title' => 'Tasas y Servicios',
    'title_singular' => 'Tasa/Servicio',
    'new_title' => 'Nueva Tasa/Servicio',
    'edit_title' => 'Editar Tasa/Servicio',

    // Secciones
    'sections' => [
        'fee_data' => 'Datos de la Tasa/Servicio',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre',
        'branches' => 'Sucursales',
        'calculation_base' => 'Base de Cálculo',
        'value_type' => 'Tipo de Valor',
        'value' => 'Valor',
        'auto_apply' => 'Aplicar Automáticamente',
        'where_to_use' => 'Dónde Usar',
    ],

    // Tooltips
    'tooltips' => [
        'auto_apply' => 'Cuando está activo, la tasa se añadirá automáticamente en nuevos contratos.',
        'where_to_use' => 'Seleccione dónde estará disponible esta tasa.',
    ],

    // Opciones de base de cálculo
    'calculation_options' => [
        'fixed' => 'Fijo (valor único)',
        'per_period' => 'Por Período (calculado por día)',
        'total_value' => 'Valor Total',
    ],

    // Opciones de tipo de valor
    'value_type_options' => [
        'monetary' => 'Monetario (€)',
        'percentage' => 'Porcentaje (%)',
    ],

    // Opciones de aplicar
    'apply_options' => [
        'no' => 'No (requiere selección manual)',
        'yes' => 'Sí (aplicada automáticamente)',
    ],

    // Opciones de dónde usar
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Sitio Web',
        'app' => 'App',
        'all' => 'Todos',
    ],

    // Insignias
    'badges' => [
        'base_fixed' => 'Fijo',
        'base_per_period' => 'Por Período',
        'base_total_value' => 'Valor Total',
        'apply_yes' => 'Sí',
        'apply_no' => 'No',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar tasa...',
        'select_branches' => 'Seleccione las sucursales...',
        'all_branches' => 'Todas las sucursales',
        'select' => 'Seleccione...',
        'name_example' => 'Ej: Tasa de limpieza',
    ],

    // Tabla
    'table' => [
        'name' => 'Nombre',
        'calculation_base' => 'Base Cálculo',
        'value' => 'Valor',
        'auto_apply' => 'Aplicar Auto',
        'branches' => 'Sucursales',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron tasas ni servicios',
        'no_name' => 'Sin nombre',
        'all_branches' => 'Todas',
        'load_error' => 'Error al cargar los datos',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar el registro',
        'this_record' => 'esta tasa/servicio',
        'not_found' => 'Tasa/servicio no encontrado',
        'load_branches_error' => 'Error al cargar las sucursales',
        'load_branches_text' => 'Error al cargar',
        'no_branches' => 'Ninguna sucursal registrada',
        'no_branches_text' => 'Ninguna sucursal',
        'loading_branches' => 'Cargando sucursales...',
        'required_fields' => 'Complete los campos obligatorios:',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar',
        'created' => '¡Tasa/servicio creado con éxito!',
        'updated' => '¡Tasa/servicio actualizado con éxito!',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'taxa_servico',
];
