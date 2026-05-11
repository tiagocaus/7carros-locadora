<?php

/**
 * Traducciones del módulo Documentos - Español (España)
 */

return [
    'title' => 'Plantillas de Documento',
    'title_singular' => 'Documento',
    'new_title' => 'Nuevo Documento',
    'edit_title' => 'Editar Documento',

    // Filtros de tipo
    'filters' => [
        'all' => 'Todos',
        'both' => 'Contrato/Alquiler',
        'contract' => 'Contrato',
        'rental' => 'Alquiler',
        'fine' => 'Multa',
    ],

    // Tabla
    'table' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Estado',
        'updated_at' => 'Actualizado el',
        'actions' => 'Acciones',
    ],

    // Insignias
    'badges' => [
        'type_both' => 'Contrato/Alquiler',
        'type_contract' => 'Contrato',
        'type_rental' => 'Alquiler',
        'type_fine' => 'Multa',
        'status_active' => 'Activo',
        'status_inactive' => 'Inactivo',
    ],

    // Campos del formulario
    'fields' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Estado',
        'content' => 'Contenido',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar documento...',
        'title_example' => 'Ej: Contrato de Alquiler',
    ],

    // Panel de variables
    'variables' => [
        'title' => 'Variables Disponibles',
        'description' => 'Haga clic para insertar en el editor',
        'no_variables' => 'No hay variables disponibles',
        'load_error' => 'Error al cargar las variables',
    ],

    // Descripción
    'description' => 'Cree plantillas de documentos con variables rellenas automáticamente',

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron documentos',
        'no_title' => 'Sin título',
        'load_error' => 'Error al cargar los documentos',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar el documento',
        'this_record' => 'este documento',
        'title_required' => 'El título es obligatorio',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar el documento',
        'saved' => 'Documento guardado correctamente',
        'imported' => '¡Documento importado correctamente!',
        'editor_error' => 'Error al cargar el editor. Recargue la página.',
        'content_required' => 'Introduzca algún contenido para obtener una vista previa',
        'preview_error' => 'Error al generar la vista previa',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'documento',
];
