<?php

/**
 * Traducciones del módulo Plan de Mantenimiento - Español (España)
 *
 * Cadenas específicas del CRUD de Planes de Mantenimiento
 */

return [
    // Títulos
    'title' => 'Planes de Mantenimiento',
    'title_new' => 'Agregar Plan de Mantenimiento',
    'title_edit' => 'Editar Plan de Mantenimiento',

    // Botones
    'btn_new' => 'Nuevo',
    'btn_save' => 'Guardar',
    'btn_cancel' => 'Cancelar',
    'btn_back' => 'Volver',

    // Etiquetas del formulario
    'field_name' => 'Nombre del Plan',
    'field_name_placeholder' => 'Ej: Plan Estándar, Plan Premium...',
    'field_vehicle_type' => 'Tipo de Vehículo',
    'vehicle_car' => 'Coche',
    'vehicle_motorcycle' => 'Moto',
    'field_status' => 'Estado',
    'field_status_active' => 'Activo',
    'field_status_inactive' => 'Inactivo',
    'field_interval' => 'Intervalo (km)',
    'field_interval_placeholder' => '0',
    'field_interval_hint' => 'Deje 0 para desactivar este ítem',

    // Secciones del formulario
    'section_basic' => 'Datos Básicos',
    'section_intervals' => 'Intervalos de Mantenimiento',
    'section_intervals_hint' => 'Configure el intervalo en kilómetros para cada ítem de mantenimiento. Los ítems con intervalo 0 serán ignorados.',

    // Tabla
    'table_name' => 'Nombre',
    'table_status' => 'Estado',
    'table_items' => 'Ítems Configurados',
    'table_actions' => 'Acciones',
    'table_empty' => 'Ningún plan de mantenimiento encontrado',
    'table_loading' => 'Cargando...',

    // Mensajes
    'messages' => [
        'created' => '¡Plan de mantenimiento creado con éxito!',
        'updated' => '¡Plan de mantenimiento actualizado con éxito!',
        'deleted' => '¡Plan de mantenimiento eliminado con éxito!',
        'not_found' => 'Plan de mantenimiento no encontrado.',
        'name_required' => 'El nombre del plan es obligatorio.',
        'confirm_delete' => '¿Desea eliminar el plan ":name"?',
        'has_vehicles' => 'Este plan está vinculado a vehículos y no puede ser eliminado.',
        'load_error' => 'Error al cargar planes de mantenimiento.',
        'save_error' => 'Error al guardar plan de mantenimiento.',
        'delete_error' => 'Error al eliminar plan de mantenimiento.',
        'no_name' => 'Sin nombre',
        'this_plan' => 'este plan',
    ],

    // Paginación
    'pagination_info' => 'Mostrando :start-:end de :total registros',
    'pagination_per_page' => 'Registros por página',
    'pagination_page_navigation' => 'Navegación de páginas',

    // Búsqueda
    'search_placeholder' => 'Buscar plan...',

    // Tooltips
    'tooltip_edit' => 'Editar plan',
    'tooltip_delete' => 'Eliminar plan',
    'tooltip_interval' => 'Kilómetros entre mantenimientos',
];
