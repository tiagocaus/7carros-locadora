<?php

/**
 * Traducciones del módulo Feature Requests - Español (España)
 */

return [
    'title' => 'Solicitudes de Funciones',
    'new_title' => 'Nueva Solicitud de Función',
    'details_title' => 'Detalles de la Solicitud',
    'edit_title' => 'Editar Solicitud',
    'new_request' => 'Nueva Solicitud',

    // Campos
    'fields' => [
        'title' => 'Título de la Solicitud',
        'module' => 'Módulo/Área',
        'description' => 'Descripción Detallada',
        'phone' => 'Teléfono/WhatsApp (opcional)',
        'follow_auto' => 'Quiero recibir notificaciones cuando está solicitud sea completada',
    ],

    // Filtros
    'filters' => [
        'status' => 'Estado',
        'module' => 'Módulo',
        'sort' => 'Ordenar',
        'all' => 'Todos',
        'my_requests' => 'Mis solicitudes',
        'sort_recent' => 'Más Recientes',
        'sort_votes' => 'Más Votadas',
        'sort_oldest' => 'Más Antiguas',
    ],

    // Estado
    'status' => [
        'pending' => 'Pendiente',
        'in_review' => 'En Revisión',
        'in_development' => 'En Desarrollo',
        'completed' => 'Completada',
        'rejected' => 'Rechazada',
        'awaiting_info' => 'Esperando Info',
        'awaiting_info_full' => 'Esperando Información',
    ],

    // Prioridades
    'priorities' => [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ],

    // Tabla
    'table' => [
        'title' => 'Título',
        'module' => 'Módulo',
        'status' => 'Estado',
        'votes' => 'Votos',
        'actions' => 'Acciones',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar solicitud...',
        'title_input' => 'Describe brevemente lo que necesitas...',
        'description_input' => 'Explica con detalle lo que necesitas, qué problema quieres resolver y cómo imaginas la solución...',
        'phone_input' => '(+34) 999 999 999',
        'select_module' => 'Seleccionar...',
        'admin_response' => 'Añade una respuesta o comentario sobre la solicitud...',
    ],

    // Sugerencias
    'hints' => [
        'title' => 'Sé claro y conciso en el título',
        'module' => '¿A qué parte del sistema hace referencia?',
        'description' => 'Cuantos más detalles proporciones, mejor entenderemos tu necesidad',
        'phone' => 'Para recibir notificaciones por WhatsApp',
    ],

    // Botones y acciones
    'actions' => [
        'vote' => 'Votar por esta solicitud',
        'remove_vote' => 'Quitar voto',
        'follow' => 'Seguir',
        'unfollow' => 'Dejar de seguir',
        'view_details' => 'Ver detalles',
        'view' => 'Ver',
        'submit' => 'Enviar Solicitud',
        'sending' => 'Enviando...',
        'save_changes' => 'Guardar Cambios',
    ],

    // Información
    'info' => [
        'voted' => 'Has votado por esta solicitud',
        'following' => 'Recibirás una notificación cuando sea completada',
        'vote_priority' => 'Votar aumenta la prioridad de la solicitud',
        'follow_updates' => 'Seguir para recibir notificaciones cuando haya actualizaciones',
        'requested_by' => 'Solicitado por',
        'not_categorized' => 'Sin categorizar',
        'votes_label' => 'votos',
        'followers_label' => 'seguidores',
        'responded_at' => 'Respondido el',
    ],

    // Similares
    'similar' => [
        'found' => 'Encontramos solicitudes similares:',
        'follow_existing' => 'Puedes seguir una solicitud existente y recibirás una notificación cuando sea completada.',
        'follow_btn' => 'Seguir',
    ],

    // Detalles
    'details' => [
        'description' => 'Descripción',
        'admin_response' => 'Respuesta del Equipo 7Carros',
        'additional_info' => 'Información Adicional',
        'id' => 'ID:',
        'priority' => 'Prioridad:',
        'updated' => 'Actualizado:',
        'email' => 'Email:',
    ],

    // Admin
    'admin' => [
        'panel_title' => 'Panel de Administración',
        'change_status' => 'Cambiar Estado',
        'priority' => 'Prioridad',
        'response' => 'Respuesta/Comentario',
        'notify_creator' => 'Notificar al creador sobre este cambio',
        'notify_followers' => 'Notificar a los seguidores',
        'followers_title' => 'Seguidores',
        'no_followers' => 'Aún no hay seguidores',
        'notify_email' => 'Notificar por email',
        'notify_whatsapp' => 'Notificar por WhatsApp',
    ],

    // Modal de edición
    'edit' => [
        'title_label' => 'Título',
        'description_label' => 'Descripción',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron solicitudes',
        'no_title' => 'Sin título',
        'other_module' => 'Otro',
        'load_error' => 'Error al cargar las solicitudes',
        'server_error' => 'Error al conectar con el servidor',
        'vote_error' => 'Error al procesar el voto',
        'follow_error' => 'Error al seguir la solicitud',
        'process_error' => 'Error al procesar',
        'follow_success' => '¡Ahora sigues esta solicitud y recibirás una notificación cuando sea completada!',
        'now_following' => '¡Ahora sigues esta solicitud!',
        'unfollowed' => 'Has dejado de seguir esta solicitud',
        'vote_added' => '¡Voto registrado!',
        'vote_removed' => 'Voto eliminado',
        'title_required' => 'Introduce el título de la solicitud',
        'module_required' => 'Selecciona el módulo/área',
        'description_required' => 'Introduce la descripción detallada',
        'title_required_edit' => 'Introduce el título',
        'description_required_edit' => 'Introduce la descripción',
        'submit_success' => '¡Solicitud enviada con éxito!',
        'submit_error' => 'Error al enviar la solicitud',
        'update_success' => '¡Solicitud actualizada con éxito!',
        'update_error' => 'Error al actualizar',
        'update_request_error' => 'Error al actualizar la solicitud',
        'not_found' => 'Solicitud no encontrada',
        'id_not_found' => 'ID de solicitud no proporcionado',
        'load_request_error' => 'Error al cargar la solicitud',
        'admin_save_success' => '¡Cambios guardados con éxito!',
        'admin_save_error' => 'Error al guardar',
        'admin_save_changes_error' => 'Error al guardar los cambios',
        'saving' => 'Guardando...',
        'back_to_list' => 'Volver a la lista',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Módulos del sistema (categorías)
    'sistema_inicial' => 'Sistema - Inicio',
    'sistema_locacoes' => 'Sistema - Alquileres',
    'sistema_contratos' => 'Sistema - Contratos',
    'sistema_matriz_filiais' => 'Sistema - Matriz y sucursales',
    'sistema_funcionarios' => 'Sistema - Empleados',
    'sistema_taxas_servicos' => 'Sistema - Tarifas y servicios',
    'sistema_oficinas' => 'Sistema - Talleres',
    'sistema_promocoes' => 'Sistema - Promociones',
    'sistema_multas' => 'Sistema - Multas',
    'sistema_contas_caixa' => 'Sistema - Cuentas bancarias/caja',
    'sistema_formas_pagamento' => 'Sistema - Formas de pago',
    'sistema_fornecedores' => 'Sistema - Proveedores',
    'sistema_veiculos' => 'Sistema - Vehículos',
    'sistema_grupos' => 'Sistema - Grupos',
    'sistema_acessorios_itens' => 'Sistema - Accesorios e ítems',
    'sistema_manutencoes' => 'Sistema - Mantenimientos',
    'sistema_plano_manutencoes' => 'Sistema - Plan de mantenimientos',
    'sistema_checklist' => 'Sistema - Checklist',
    'sistema_checklist_modelos' => 'Sistema - Modelos de checklist',
    'sistema_relatorios' => 'Sistema - Informes',
    'sistema_financeiro' => 'Sistema - Financiero',
    'sistema_site' => 'Sistema - Sitio web',
    'sistema_clientes' => 'Sistema - Clientes',
    'sistema_whatsapp' => 'Sistema - WhatsApp',
    'sistema_documentos' => 'Sistema - Documentos',
    'sistema_estoque' => 'Sistema - Inventario',
    'sistema_agenda' => 'Sistema - Agenda',

    // Website y Aplicación
    'website_site' => 'Website - Sitio',
    'aplicativo_checklist' => 'Aplicación - Checklist',

    // Otros
    'outros' => 'Otros',
];
