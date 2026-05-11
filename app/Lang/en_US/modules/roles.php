<?php

/**
 * Translations for the Roles module - English (United States)
 */

return [
    'title' => 'Manage Roles',
    'title_singular' => 'Role',
    'new_title' => 'New Role',
    'edit_title' => 'Edit Role',
    'edit_prefix' => 'Edit:',

    // Sections
    'sections' => [
        'role_data' => 'Role Data',
        'permissions' => 'Permissions',
        'permissions_desc' => 'Select the permissions this role will have access to:',
    ],

    // Fields
    'fields' => [
        'name' => 'Role Name',
        'description' => 'Description',
    ],

    // Placeholders
    'placeholders' => [
        'name' => 'Ex: Manager, Attendant...',
        'name_full' => 'Ex: Manager, Attendant, Driver...',
        'description' => 'Describe the responsibilities...',
        'description_full' => 'Describe the responsibilities of this role...',
    ],

    // Badges
    'badges' => [
        'system' => 'System',
        'custom' => 'Custom',
    ],

    // Warnings
    'warnings' => [
        'system_role_title' => 'System Role',
        'system_role_desc' => 'This is a default system role. When you save your changes, a <strong>custom copy</strong> exclusive to your company will be created. The original system role will remain unchanged.',
        'system_role_short' => 'This is a system role. When saved, a custom copy will be created for your company.',
        'custom_role_title' => 'Custom Role',
        'custom_role_desc' => 'This is a customized version of a system role. The name cannot be changed.',
        'name_locked' => 'Name locked (custom system role)',
        'name_locked_title' => 'The name cannot be changed in custom system roles',
        'irreversible' => 'This action cannot be undone.',
    ],

    // Actions
    'actions' => [
        'save_role' => 'Save Role',
        'save_changes' => 'Save Changes',
        'create_copy' => 'Create Custom Copy',
        'delete_role' => 'Delete Role',
        'select_all' => 'Select all',
        'select_all_short' => 'All',
    ],

    // Messages
    'messages' => [
        'loading_roles' => 'Loading roles...',
        'loading_permissions' => 'Loading permissions...',
        'load_error' => 'Error loading roles.',
        'load_role_error' => 'Error loading role data',
        'load_permissions_error' => 'Error loading permissions.',
        'no_records' => 'No roles registered.',
        'no_permissions' => 'No permissions available.',
        'not_found' => 'Role not found',
        'save_error' => 'Error saving role',
        'delete_error' => 'Error deleting role',
        'process_error' => 'Error processing request',
        'deleting' => 'Deleting...',
        'create_success' => 'Role Created!',
        'update_success' => 'Role Updated!',
        'copy_created' => 'Custom Copy Created!',
        'delete_confirm' => 'Are you sure you want to delete the role ":name"?',
        'closing_countdown' => 'Closing in :seconds seconds...',
    ],

    // Module names (for permissions display)
    'module_names' => [
        'dashboard' => 'Dashboard',
        'locacoes' => 'Rentals',
        'contratos' => 'Contracts',
        'veiculos' => 'Vehicles',
        'clientes' => 'Customers',
        'funcionarios' => 'Employees',
        'financeiro' => 'Financial',
        'relatorios' => 'Reports',
        'configuracoes' => 'Settings',
        'roles' => 'Roles',
        'matrizes_filiais' => 'Headquarters/Branches',
        'empresas' => 'Companies',
        'fornecedores' => 'Suppliers',
        'acessorios' => 'Accessories',
        'grupos' => 'Vehicle Groups',
        'taxas_servicos' => 'Fees and Services',
        'oficinas' => 'Workshops',
        'localizar' => 'Locate Vehicle',
        'agenda' => 'Calendar',
        'website' => 'Website',
        'logs' => 'System Logs',
        'app_vistoria' => 'Inspection App',
        'multas' => 'Fines',
        'promocoes' => 'Promotions',
        'manutencoes' => 'Maintenances',
        'manutencao' => 'Maintenance',
        'manutencoes_planos' => 'Maintenance Plans',
        'formas' => 'Payment Methods',
        'checklists' => 'Checklists',
        'checklist' => 'Checklist',
        'checklists_modelos' => 'Checklist Templates',
        'contas' => 'Bank Accounts',
        'cartao' => 'Card',
        'documentos' => 'Documents',
        'estoque' => 'Inventory',
        'acesso' => 'Access Control',
        'notificacoes' => 'Notifications',
        'whatsapp' => 'WhatsApp',
        'promissorias' => 'Promissory Notes',
        'feature_requests' => 'Request new feature',
        'reservas' => 'Reservations',
    ],
];
