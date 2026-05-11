<?php

/**
 * Traducciones del módulo Temporadas - Español (España)
 */

return [
    'title' => 'Temporadas',
    'title_singular' => 'Temporada',
    'new_title' => 'Nueva Temporada',
    'edit_title' => 'Editar: :name',

    // Secciones
    'sections' => [
        'season_data' => 'Datos de la Temporada',
        'group_adjustments' => 'Ajustes por Grupo de Vehículo',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre',
        'country' => 'País',
        'period_start' => 'Inicio del Período',
        'period_end' => 'Fin del Período',
        'active' => 'Temporada activa',
    ],

    // Países
    'countries' => [
        'BR' => 'Brasil',
        'US' => 'Estados Unidos',
        'IT' => 'Italia',
        'ES' => 'España',
        'PT' => 'Portugal',
    ],

    // Meses
    'months' => [
        '1' => 'Enero',
        '2' => 'Febrero',
        '3' => 'Marzo',
        '4' => 'Abril',
        '5' => 'Mayo',
        '6' => 'Junio',
        '7' => 'Julio',
        '8' => 'Agosto',
        '9' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ],

    // Insignias
    'badges' => [
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ],

    // Descripciones
    'descriptions' => [
        'adjustments' => 'Defina el porcentaje de ajuste de precio para cada grupo. Ej: 30 = +30%, -10 = -10%',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar temporada...',
        'name_example' => 'Ej: Semana Santa 2025, Verano...',
    ],

    // Plantillas
    'templates' => [
        'title' => 'Plantillas de Temporada',
        'activate_title' => 'Activar Plantilla de Temporada',
        'filter_country' => 'Filtrar por país',
        'all_countries' => 'Todos los países',
        'loading' => 'Cargando plantillas...',
        'load_error' => 'Error al cargar plantillas.',
        'no_templates' => 'No hay plantillas disponibles para este país.',
        'activate' => 'Activar',
        'activating' => 'Activando...',
        'activate_error' => 'Error al activar la plantilla',
    ],

    // Tabla
    'table' => [
        'name' => 'Nombre',
        'country' => 'País',
        'period' => 'Período',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron temporadas',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar temporadas',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar la temporada',
        'this_record' => 'esta temporada',
        'load_season_error' => 'Error al cargar la temporada',
        'load_adjustments_error' => 'Error al cargar los ajustes.',
        'no_groups' => 'No hay grupos de vehículos registrados.',
        'loading_groups' => 'Cargando grupos...',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar la temporada',
        'request_error' => 'Error al procesar la solicitud',
        'created' => '¡Temporada creada con éxito!',
        'updated' => '¡Temporada actualizada con éxito!',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'temporada',

    // Botones
    'buttons' => [
        'templates' => 'Plantillas',
        'new' => 'Añadir',
    ],
];
