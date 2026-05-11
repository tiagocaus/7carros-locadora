<?php

/**
 * Traducciones del módulo Roles (Funciones) - Español (España)
 */

return [
    'title' => 'Gestionar Roles',
    'title_singular' => 'Rol',
    'new_title' => 'Nuevo Rol',
    'edit_title' => 'Editar Rol',
    'edit_prefix' => 'Editar:',

    // Secciones
    'sections' => [
        'role_data' => 'Datos del Rol',
        'permissions' => 'Permisos',
        'permissions_desc' => 'Seleccione los permisos a los que este rol tendrá acceso:',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre del Rol',
        'description' => 'Descripción',
    ],

    // Marcadores de posición
    'placeholders' => [
        'name' => 'Ej: Gerente, Recepcionista...',
        'name_full' => 'Ej: Gerente, Recepcionista, Conductor...',
        'description' => 'Describa las responsabilidades...',
        'description_full' => 'Describa las responsabilidades de este rol...',
    ],

    // Etiquetas
    'badges' => [
        'system' => 'Sistema',
        'custom' => 'Personalizado',
    ],

    // Avisos
    'warnings' => [
        'system_role_title' => 'Rol de Sistema',
        'system_role_desc' => 'Este es un rol predeterminado del sistema. Al guardar sus cambios, se creará una <strong>copia personalizada</strong> exclusiva para su empresa. El rol original del sistema permanecerá sin cambios.',
        'system_role_short' => 'Este es un rol de sistema. Al guardar, se creará una copia personalizada para su empresa.',
        'custom_role_title' => 'Rol Personalizado',
        'custom_role_desc' => 'Esta es una versión personalizada de un rol de sistema. El nombre no puede modificarse.',
        'name_locked' => 'Nombre bloqueado (rol personalizado del sistema)',
        'name_locked_title' => 'El nombre no puede modificarse en roles personalizados del sistema',
        'irreversible' => 'Esta acción no puede deshacerse.',
    ],

    // Acciones
    'actions' => [
        'save_role' => 'Guardar Rol',
        'save_changes' => 'Guardar Cambios',
        'create_copy' => 'Crear Copia Personalizada',
        'delete_role' => 'Eliminar Rol',
        'select_all' => 'Seleccionar todos',
        'select_all_short' => 'Todos',
    ],

    // Mensajes
    'messages' => [
        'loading_roles' => 'Cargando roles...',
        'loading_permissions' => 'Cargando permisos...',
        'load_error' => 'Error al cargar roles.',
        'load_role_error' => 'Error al cargar datos del rol',
        'load_permissions_error' => 'Error al cargar permisos.',
        'no_records' => 'Ningún rol registrado.',
        'no_permissions' => 'Ningún permiso disponible.',
        'not_found' => 'Rol no encontrado',
        'save_error' => 'Error al guardar rol',
        'delete_error' => 'Error al eliminar rol',
        'process_error' => 'Error al procesar solicitud',
        'deleting' => 'Eliminando...',
        'create_success' => '¡Rol Creado!',
        'update_success' => '¡Rol Actualizado!',
        'copy_created' => '¡Copia Personalizada Creada!',
        'delete_confirm' => '¿Está seguro de que desea eliminar el rol ":name"?',
        'closing_countdown' => 'Cerrando en :seconds segundos...',
    ],

    // Nombres de módulos (para visualización de permisos)
    'module_names' => [
        'dashboard' => 'Panel de Control',
        'locacoes' => 'Alquileres',
        'contratos' => 'Contratos',
        'veiculos' => 'Vehículos',
        'clientes' => 'Clientes',
        'funcionarios' => 'Empleados',
        'financeiro' => 'Finanzas',
        'relatorios' => 'Informes',
        'configuracoes' => 'Configuración',
        'roles' => 'Roles',
        'matrizes_filiais' => 'Matrices/Sucursales',
        'empresas' => 'Empresas',
        'fornecedores' => 'Proveedores',
        'acessorios' => 'Accesorios',
        'grupos' => 'Grupos de Vehículos',
        'taxas_servicos' => 'Tarifas y Servicios',
        'oficinas' => 'Talleres',
        'localizar' => 'Localizar Vehículo',
        'agenda' => 'Agenda',
        'website' => 'Sitio Web',
        'logs' => 'Registros del Sistema',
        'app_vistoria' => 'App de Inspección',
        'multas' => 'Multas',
        'promocoes' => 'Promociones',
        'manutencoes' => 'Mantenimientos',
        'manutencao' => 'Mantenimiento',
        'manutencoes_planos' => 'Planes de Mantenimiento',
        'formas' => 'Formas de Pago',
        'checklists' => 'Listas de Verificación',
        'checklist' => 'Lista de Verificación',
        'checklists_modelos' => 'Plantillas de Lista de Verificación',
        'contas' => 'Cuentas Bancarias',
        'cartao' => 'Tarjeta',
        'documentos' => 'Documentos',
        'estoque' => 'Inventario',
        'acesso' => 'Control de Acceso',
        'notificacoes' => 'Notificaciones',
        'whatsapp' => 'WhatsApp',
        'promissorias' => 'Pagarés',
        'feature_requests' => 'Solicitar nueva función',
        'reservas' => 'Reservas',
    ],
];
