<?php

/**
 * Translations for the Fornecedores module - English (US)
 */

return [
    'title' => 'Suppliers',
    'title_singular' => 'Supplier',
    'new_title' => 'New Supplier',
    'edit_title' => 'Edit Supplier',

    // Sections
    'sections' => [
        'basic_data' => 'Basic Data',
        'address' => 'Address',
        'investor' => 'Investor',
        'observations' => 'Observations',
    ],

    // Fields
    'fields' => [
        'type' => 'Type',
        'cpf_cnpj' => 'CPF/CNPJ',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'name' => 'Name',
        'company_name' => 'Company Name',
        'trade_name' => 'Trade Name',
        'rg' => 'RG',
        'state_registration' => 'State Registration',
        'municipal_registration' => 'Municipal Registration',
        'email' => 'Email',
        'phone1' => 'Phone 1',
        'phone2' => 'Phone 2',
        'zip_code' => 'ZIP Code',
        'street' => 'Street',
        'number' => 'Number',
        'complement' => 'Complement',
        'neighborhood' => 'Neighborhood',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'supplies_vehicles' => 'Supplies Vehicles',
        'is_investor' => 'Is Investor?',
        'split_gateway' => 'Split Gateway',
        'split_account_id' => 'Account/Wallet ID',
        'pix_key' => 'PIX Key',
        'pix_key_type' => 'PIX Key Type',
        'bank_code' => 'Bank Code',
        'bank_branch' => 'Branch',
        'bank_account' => 'Account',
        'bank_account_type' => 'Account Type',
        'portal_password' => 'Portal password',
        'portal_password_help' => 'Use at least 8 characters. When editing, leave it blank to keep the current password.',
    ],

    // Type options
    'type_options' => [
        'PJ' => 'Legal Entity',
        'PF' => 'Individual',
    ],

    // Gateway options
    'gateway_options' => [
        'none' => 'None (manual)',
        'asaas' => 'Asaas',
        'gerencianet' => 'Gerencianet',
        'stripe' => 'Stripe',
        'inter' => 'Banco Inter',
    ],

    // PIX key type options
    'pix_type_options' => [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'Email',
        'telefone' => 'Phone',
        'aleatoria' => 'Random Key',
    ],

    // Account type options
    'account_type_options' => [
        'corrente' => 'Checking',
        'poupanca' => 'Savings',
    ],

    'commission_rules' => [
        'title' => 'Commission rules',
        'description' => 'The first row, "Default rule", applies to all of this investor’s groups when there is no specific exception. To set a different deal for one group, click "Add group exception".',
        'help' => 'The "Default rule" is the investor’s general rule. Use it when this investor has the same commission for all of their vehicles, regardless of group. Example: if the default rule is 20% for the rental company, all vehicles from this investor use that rule, even if they are in different groups. If a group has a different deal, click "Add group exception", choose the group, and enter the specific commission. The system uses the group exception first; if there is no exception, it uses the investor default rule; if there is no default rule either, it uses the rule configured on the vehicle group.',
        'add_group_rule' => 'Add group exception',
        'default_rule' => 'Default rule',
        'group_rule' => 'Group rule',
        'group_placeholder' => 'Select group',
        'type_placeholder' => 'Commission type',
        'value' => 'Value',
        'remove' => 'Remove',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search...',
        'split_account' => 'Ex: wal_xxxx',
        'bank_code' => 'Ex: 001',
        'select' => 'Select...',
    ],

    // Filters
    'filters' => [
        'all' => 'All',
        'suppliers' => 'Suppliers',
        'investors' => 'Investors',
    ],

    // Table
    'table' => [
        'name' => 'Name',
        'cpf_cnpj' => 'CPF/CNPJ',
        'phone' => 'Phone',
        'investor' => 'Investor',
        'actions' => 'Actions',
    ],

    // Badges
    'badges' => [
        'investor_yes' => 'Yes',
        'investor_no' => 'No',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No records found',
        'no_name' => 'No name',
        'load_error' => 'Error loading',
        'server_error' => 'Error connecting to the server',
        'delete_error' => 'Error deleting',
        'this_record' => 'this record',
        'load_data_error' => 'Error loading data',
        'load_supplier_error' => 'Error loading supplier data',
        'saving' => 'Saving...',
        'save_error' => 'Error saving',
        'save_supplier_error' => 'Error saving supplier',
        'created' => 'Supplier created successfully!',
        'updated' => 'Supplier updated successfully!',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'supplier',
];
