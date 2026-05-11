<?php

/**
 * Translations for the Payment Gateways module - English (United States)
 */

return [
    'title' => 'Payment Gateways',
    'title_singular' => 'Payment Gateway',
    'new_title' => 'New Payment Gateway',
    'edit_title' => 'Edit Payment Gateway',

    // Sections
    'sections' => [
        'gateway_data' => 'Gateway Data',
        'payment_methods' => 'Enabled Payment Methods',
        'payment_methods_desc' => 'Select which payment methods will be available for this gateway.',
        'credentials' => 'Credentials',
        'credentials_desc' => 'Configure the gateway access credentials.',
        'webhook' => 'Webhook',
        'webhook_desc' => 'Set this URL in the gateway dashboard to receive payment notifications.',
    ],

    // Fields
    'fields' => [
        'gateway' => 'Gateway',
        'name' => 'Identification name',
        'branches' => 'Branches',
        'currencies' => 'Accepted Currencies',
        'environment' => 'Environment',
        'status' => 'Status',
        'display_order' => 'Display order',
        'methods' => 'Methods',
        'webhook_url' => 'Webhook URL',
    ],

    // Payment methods
    'methods' => [
        'pix' => 'PIX',
        'pix_desc' => 'Instant payment',
        'boleto' => 'Boleto',
        'boleto_desc' => 'Bank slip',
        'credit_card' => 'Credit Card',
        'credit_card_desc' => 'Installments available',
        'debit_card' => 'Debit Card',
        'debit_card_desc' => 'Direct debit',
        'none' => 'None',
    ],

    // Environment
    'environment' => [
        'sandbox' => 'Sandbox (Test)',
        'production' => 'Production',
    ],

    // Status
    'status_options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'not_configured' => 'Not configured',
    ],

    // Countries
    'countries' => [
        'BR' => 'Brazil',
        'PY' => 'Paraguay',
        'INTL' => 'International',
    ],

    // Currencies
    'currencies' => [
        'BRL' => 'Brazilian Real',
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
        'JPY' => 'Japanese Yen',
        'MXN' => 'Mexican Peso',
        'CHF' => 'Swiss Franc',
        'PYG' => 'Paraguayan Guarani',
        'ARS' => 'Argentine Peso',
        'CLP' => 'Chilean Peso',
        'COP' => 'Colombian Peso',
        'PEN' => 'Peruvian Sol',
        'UYU' => 'Uruguayan Peso',
    ],

    // Hints
    'hints' => [
        'branches' => 'Leave blank to make available in all branches.',
        'currencies' => 'Select the currencies this gateway accepts. Available options depend on the selected gateway.',
        'display_order' => 'Lower number appears first in the options list.',
        'name_placeholder' => 'E.g.: Main Asaas, Stripe Production',
    ],

    // Dropdowns
    'dropdowns' => [
        'select_gateway' => 'Select a gateway...',
        'select_gateway_first' => 'Select a gateway first',
        'all_branches' => 'All branches',
        'no_branches' => 'No branches registered',
        'no_branches_short' => 'No branches',
        'no_currencies' => 'No currency selected',
        'load_error' => 'Error loading',
    ],

    // Table
    'table' => [
        'gateway' => 'Gateway',
        'branch' => 'Branch',
        'methods' => 'Methods',
        'environment' => 'Environment',
        'status' => 'Status',
        'actions' => 'Actions',
        'all_branches' => 'All',
    ],

    // Actions
    'actions' => [
        'test_connection' => 'Test Connection',
        'testing' => 'Testing...',
        'copy_url' => 'Copy URL',
        'view_docs' => 'View documentation',
        'configure' => 'Configure',
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search gateway...',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No gateway configured',
        'no_name' => 'No name',
        'load_error' => 'Error loading data',
        'server_error' => 'Error connecting to the server',
        'delete_error' => 'Error deleting record',
        'status_error' => 'Error changing status',
        'test_success' => 'Connection successful! Credentials validated.',
        'test_fail' => 'Connection failed. Check your credentials.',
        'test_error' => 'Error testing connection',
        'not_found' => 'Record not found',
        'gateway_required' => 'Please select a gateway',
        'name_required' => 'Please enter the identification name',
        'currency_required' => 'Please select at least one currency',
        'save_error' => 'Error saving',
        'save_success' => 'Saved successfully',
        'load_branches_error' => 'Error loading branches',
        'branch_fallback' => 'Branch :id',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Records per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type for delete modal
    'record_type' => 'gateway_pagamento',
];
