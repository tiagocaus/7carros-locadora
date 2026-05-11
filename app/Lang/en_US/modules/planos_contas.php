<?php

/**
 * Traduções do módulo Planos de Contas - English (US)
 */

return [
    // Títulos
    'title' => 'Chart of Accounts',
    'title_singular' => 'Account',
    'list_title' => 'Chart of Accounts',
    'new_title' => 'New Account',
    'edit_title' => 'Edit Account',

    // Campos do formulário
    'fields' => [
        'hierarquia' => 'Code',
        'descricao' => 'Description',
        'tipo' => 'Type',
        'tipo_ativo' => 'Asset',
        'tipo_passivo' => 'Liability',
        'tipo_despesa' => 'Expense',
        'tipo_receita' => 'Revenue',
        'conta_pai' => 'Parent Account',
        'descricao_pt_BR' => 'Portuguese (Brazil)',
        'descricao_en_US' => 'English (US)',
        'descricao_es_ES' => 'Spanish',
        'descricao_it_IT' => 'Italian',
        'descricao_pt_PT' => 'Portuguese (Portugal)',
    ],

    // Seções do formulário
    'sections' => [
        'basic_info' => 'Basic Information',
        'translations' => 'Descriptions by Language',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search chart of accounts...',
        'descricao' => 'E.g.: General cash',
        'descricao_optional' => 'Optional - will use pt_BR if empty',
        'conta_pai' => 'Select parent account (optional for root account)',
        'selecione_tipo' => 'Select type first',
        'hierarquia' => 'E.g.: 1.1.1.01',
    ],

    // Filtros
    'filters' => [
        'all_types' => 'All types',
    ],

    // Tooltips
    'tooltips' => [
        'hierarquia' => 'Unique hierarchical code. E.g.: 1.1.1.01',
        'tipo' => 'Accounting classification of the account.',
    ],

    // Mensagens
    'messages' => [
        'created' => 'Account created successfully!',
        'updated' => 'Account updated successfully!',
        'deleted' => 'Account deleted successfully!',
        'saved' => 'Account saved successfully!',
        'not_found' => 'Account not found.',
        'has_transactions' => 'This account has financial transactions and cannot be deleted.',
        'hierarquia_required' => 'The hierarchical code is required.',
        'hierarquia_exists' => 'An account with this code already exists.',
        'tipo_invalid' => 'Invalid account type.',
        'descricao_required' => 'The description in Portuguese (Brazil) is required.',
        'cannot_edit_system' => 'System accounts cannot be edited.',
        'cannot_delete_system' => 'System accounts cannot be deleted.',
        'system_readonly' => 'This is a system account and cannot be modified.',
        'no_records' => 'No accounts found.',
        'translations_help' => 'Fill in the Portuguese (Brazil) description. Other languages are optional and will use the pt_BR value if left blank.',
        'error_list' => 'Error listing accounts',
        'error_load' => 'Error loading account',
        'error_create' => 'Error creating account',
        'error_update' => 'Error updating account',
        'error_delete' => 'Error deleting account',
        'error_save' => 'Error saving account',
        'codigo_disponivel' => 'Code available',
        'codigo_em_uso' => 'This code is already in use',
        'codigo_sugerido' => 'Automatically suggested code',
        'conta_raiz' => 'Root account (main level)',
        'formato_invalido' => 'Invalid format. Use only numbers and dots (e.g.: 1.1.01)',
        'this_record' => 'this account',
    ],
];
