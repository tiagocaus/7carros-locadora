<?php

/**
 * Traducciones del módulo Checklists - Español (España)
 */

return [
    // Título
    'title' => 'Checklists',

    // Tabla
    'table' => [
        'code' => 'Código',
        'model' => 'Modelo',
        'vehicle' => 'Vehículo',
        'date' => 'Fecha',
        'type' => 'Tipo',
        'actions' => 'Acciones',
        'status' => 'Estado',
    ],

    // Tipos
    'types' => [
        'linked' => 'Vinculado',
        'standalone' => 'Independiente',
    ],

    // Impresión
    'print' => [
        'doc_title' => 'CHECKLIST DE VEHÍCULO',
        'code' => 'Código',
        'type' => 'Tipo',
        'date' => 'Fecha',
        'title_prefix' => 'Checklist',
        'landscape' => 'Horizontal',
        'portrait' => 'Vertical',
        'plate' => 'Matrícula',
        'vehicle' => 'Vehículo',
        'renavam' => 'Renavam',
        'departure' => 'SALIDA',
        'arrival' => 'LLEGADA',
        'questionnaire' => 'Cuestionario',
        'item' => 'Ítem',
        'answer' => 'Respuesta',
        'observations' => 'Observaciones',
        'inspection_photos' => 'Inspección (Fotos)',
        'no_arrival_data' => 'Sin datos de llegada',
        'signature_departure' => 'Firma Salida',
        'signature_arrival' => 'Firma Llegada',
        'signature' => 'Firma',
    ],

    // Badges de respuesta
    'answers' => [
        'matches' => 'Conforme',
        'not_matches' => 'No conforme',
        'damaged' => 'Dañado',
        'na' => 'N/A',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar...',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'Ningún checklist encontrado',
        'load_error' => 'Error al cargar datos',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar registro',
        'this_record' => 'este checklist',
        'mobile_only' => 'Para realizar el checklist, accede a este sistema desde el navegador de un celular o tablet.',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'checklist',

    // Checklist digital
    'digital' => [
        'title' => 'Checklist digital',
        'tab_info' => 'Info',
        'tab_questions' => 'Preguntas',
        'tab_inspection' => 'Inspecciones',
        'tab_signature' => 'Firma',
        'type' => 'Tipo',
        'type_standalone' => 'Independiente',
        'type_linked' => 'Vinculado',
        'moment' => 'Momento',
        'moment_departure' => 'Salida',
        'moment_arrival' => 'Llegada',
        'vehicle' => 'Vehículo',
        'contract_rental' => 'Alquiler / Contrato',
        'checklist_model' => 'Modelo del checklist',
        'tank' => 'Tanque',
        'battery_charge' => 'Carga de la Batería',
        'odometer' => 'Odómetro actual',
        'observations' => 'Observaciones',
        'observations_placeholder' => 'Escriba las observaciones...',
        'advance' => 'Avanzar',
        'save' => 'Guardar',
        'clear' => 'Limpiar',
        'close' => 'Cerrar',
        'back' => 'Volver',
        'list' => 'Lista',
        'new' => 'Nuevo checklist',
        'next_vehicle' => 'Hacer checklist del próximo vehículo',
        'saved_success' => '¡Checklist Guardado!',
        'saved_message' => 'El checklist se ha finalizado con éxito.',
        'auto_saved' => 'Guardado automáticamente',
        'questionnaire' => 'Cuestionario',
        'information' => 'Información',
        'select' => 'Seleccione...',
        'select_vehicle' => 'Seleccione el vehículo...',
        'select_link_first' => 'Seleccione el vínculo primero...',
        'search_code_client' => 'Buscar por código o cliente...',
        'search_plate_model' => 'Buscar por matrícula o modelo...',
        'select_model' => 'Seleccione el modelo...',
        'departure_done' => 'Salida hecha',
        'arrival_done' => 'Llegada hecha',
        'status_pending' => 'Pendiente',
        'status_done' => 'Finalizado',
        'legend_linked' => 'Vinculado',
        'legend_standalone' => 'Independiente',
        'continue' => 'Continuar',
        'loading' => 'Cargando...',
        'processing' => 'Procesando...',
        'creating' => 'Creando checklist...',
        'saving_questions' => 'Guardando cuestionario...',
        'saving_checklist' => 'Guardando checklist...',
        'sending_photo' => 'Enviando foto...',
        'deleting_photo' => 'Eliminando foto...',
        'no_records' => 'Ningún checklist encontrado',
        'err_select_type' => 'Seleccione el tipo',
        'err_select_moment' => 'Seleccione el momento',
        'err_select_link' => 'Seleccione un alquiler o contrato',
        'err_select_vehicle' => 'Seleccione un vehículo',
        'err_select_model' => 'Seleccione un modelo de checklist',
        'err_select_tank' => 'Seleccione el nivel del tanque',
        'err_fill_odometer' => 'Ingrese el odómetro actual',
        'err_answer_all' => 'Responda todas las preguntas (:count pendiente(s))',
        'err_sign' => 'Dibuje la firma antes de guardar',
        'err_min_photo' => 'Tome al menos una foto de inspección',
    ],
];
