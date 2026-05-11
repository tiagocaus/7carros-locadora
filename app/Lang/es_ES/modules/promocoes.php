<?php

/**
 * Traducciones del módulo Promociones - Español (España)
 */

return [
    'title' => 'Promociones',
    'title_singular' => 'Promoción',
    'new_title' => 'Nueva Promoción',
    'edit_title' => 'Editar Promoción',

    // Secciones
    'sections' => [
        'promotion_data' => 'Datos de la Promoción',
    ],

    // Campos
    'fields' => [
        'branches' => 'Sucursales',
        'code' => 'Código',
        'name' => 'Nombre de la Promoción',
        'validity' => 'Validez',
        'minimum_days' => 'Tarifa Diaria Mínima',
        'discount_type' => 'Tipo de Descuento',
        'discount_value' => 'Valor del Descuento',
        'where_to_show' => 'Donde Mostrar',
        'status' => 'Estado',
    ],

    // Tooltips
    'tooltips' => [
        'validity' => 'Fecha límite para el uso de la promoción. Deje en blanco para no tener plazo.',
        'minimum_days' => 'Número mínimo de días de alquiler para que la promoción sea válida.',
        'where_to_show' => 'Seleccione donde estará disponible esta promoción.',
    ],

    // Opciones de tipo
    'type_options' => [
        'fixed' => 'Fijo',
        'percentage' => 'Porcentaje (%)',
    ],

    // Opciones de estado
    'status_options' => [
        'active' => 'Activo',
        'disabled' => 'Desactivado',
    ],

    // Opciones de visualizacion
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Sitio web',
        'app' => 'App',
        'all' => 'Todos',
    ],

    // Marcadores de posicion
    'placeholders' => [
        'search' => 'Buscar promoción...',
        'select_branches' => 'Seleccione las sucursales...',
        'select' => 'Seleccione...',
        'code_example' => 'Ej: PROMO2024',
        'name_example' => 'Ej: Descuento Verano',
    ],

    // Etiquetas
    'badges' => [
        'type_percentage' => 'Porcentaje',
        'type_fixed' => 'Fijo',
        'status_active' => 'Activo',
        'status_inactive' => 'Inactivo',
    ],

    // Tabla
    'table' => [
        'code' => 'Código',
        'name' => 'Nombre',
        'type' => 'Tipo',
        'value' => 'Valor',
        'min_days' => 'Días Min',
        'branches' => 'Sucursales',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron promociones',
        'no_name' => 'Sin nombre',
        'all_branches' => 'Todas',
        'days_suffix' => 'días',
        'load_error' => 'Error al cargar los datos',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar el registro',
        'this_record' => 'esta promoción',
        'not_found' => 'Promoción no encontrada',
        'load_branches_error' => 'Error al cargar las sucursales',
        'load_branches_text' => 'Error al cargar',
        'no_branches' => 'No hay sucursales registradas',
        'no_branches_text' => 'Sin sucursales',
        'loading_branches' => 'Cargando sucursales...',
        'required_fields' => 'Complete los campos obligatorios:',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar',
        'created' => 'Promoción creada con exito!',
        'updated' => 'Promoción actualizada con exito!',
    ],

    // Paginacion
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'promoción',
];
