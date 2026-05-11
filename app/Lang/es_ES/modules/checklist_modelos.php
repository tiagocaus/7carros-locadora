<?php

/**
 * Traducciones del módulo Checklist Modelos - Español (España)
 */

return [
    'title' => 'Modelos de Checklist',
    'title_singular' => 'Modelo de Checklist',
    'new_title' => 'Nuevo Modelo de Checklist',
    'edit_title' => 'Editar Modelo de Checklist',

    // Secciones
    'sections' => [
        'model_data' => 'Datos del Modelo',
        'questions' => 'Pregunta',
        'inspection' => 'Inspección',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre',
        'type' => 'Tipo',
        'status' => 'Estado',
        'item_name' => 'Nombre:',
    ],

    // Opciones de tipo
    'type_options' => [
        'digital' => 'Digital',
        'printed' => 'Impreso',
    ],

    // Opciones de estado
    'status_options' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Badges
    'badges' => [
        'type_printed' => 'Impreso',
        'type_digital' => 'Digital',
        'status_active' => 'Activo',
        'status_inactive' => 'Inactivo',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar modelo...',
        'name_example' => 'Ej: Modelo predeterminado',
    ],

    // Tabla
    'table' => [
        'name' => 'Nombre',
        'type' => 'Tipo',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron modelos de checklist',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar los datos',
        'server_error' => 'Error al conectar con el servidor',
        'not_found' => 'Modelo no encontrado',
        'delete_error' => 'Error al eliminar el registro',
        'save_error' => 'Error al guardar',
        'saving' => 'Guardando...',
        'saved' => 'Modelo guardado correctamente',
        'required_fields' => 'Complete los campos obligatorios:',
        'required_name' => '- Nombre',
    ],

    // Modales nestable
    'nestable' => [
        'add_question' => 'Agregar Pregunta',
        'edit_question' => 'Editar Pregunta',
        'add_inspection' => 'Agregar Inspección',
        'edit_inspection' => 'Editar Inspección',
        'question' => 'Pregunta',
        'inspection' => 'Inspección',
        'item' => 'elemento',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'checklist_modelo',
];
