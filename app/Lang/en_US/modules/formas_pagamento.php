<?php

/**
 * Translations for the Payment Methods module - English (US)
 */

return [
    // Titles
    'title' => 'Payment Methods',
    'title_singular' => 'Payment Method',
    'new_title' => 'New Payment Method',
    'edit_title' => 'Edit Payment Method',

    // Sections
    'sections' => [
        'payment_data' => 'Payment Method Details',
        'penalty_interest' => 'Late Penalty & Interest',
        'billing_fees' => 'Billing Fees',
        'billing_fees_desc' => 'Configure fees retained by the payment processor. On settlement, the system records gross revenue and a separate expense. Set 0.00 to disable.',
        'early_discount' => 'Early Payment Discount',
        'early_discount_desc' => 'Configure a discount for payments made before the due date. Set all values to zero to disable.',
    ],

    // Fields
    'fields' => [
        'name' => 'Name',
        'branches' => 'Branches',
        'branches_hint' => 'Select which companies this payment method will be available for.',
        'where_to_show' => 'Where to Show',
        'where_to_show_hint' => 'Select where this payment method will be available.',
        'post_as_paid' => 'Post as paid',
        'payment_gateways' => 'Payment Gateways',
        'payment_gateways_hint' => 'Select the linked payment gateways. If no gateway is selected, this payment method will not process online payments automatically.',
        'penalty_percent' => 'Penalty (%)',
        'penalty_hint' => 'Penalty percentage applied in case of late payment.',
        'interest_per_day' => 'Interest per Day (%)',
        'interest_hint' => 'Interest percentage charged per day of late payment.',
        'fixed_fee_total' => 'Total Fixed Fee',
        'fixed_fee_total_hint' => 'Fixed amount spread across installments.<br>Ex: $10 in 2x = $5 per installment.',
        'fixed_fee_installment' => 'Fixed Fee per Installment',
        'fixed_fee_installment_hint' => 'Amount charged on each installment.<br>Ex: $2.50 in 2x = $5 total.',
        'percent_fee_installment' => 'Fee % per Installment',
        'percent_fee_installment_hint' => 'Percentage over each installment.<br>Ex: 5% of $100 = $5 per installment.',
        'fee_account' => 'Fee Chart of Account',
        'fee_account_hint' => 'Expense account used by the automatic entry. The gateway is stored separately for analysis.',
        'fee_account_default' => 'Default: 3.4.1.21 - Payment processing fees',
        'days_before_due' => 'Days Before Due Date',
        'days_before_due_hint' => 'Number of days before the due date to apply the discount.',
        'discount_percent' => 'Discount (%)',
        'discount_percent_hint' => 'Discount percentage.<br>Ex: 3% of $100 = $3 discount.',
    ],

    // Where to show options
    'where_options' => [
        'site' => 'Website',
        'system' => 'System',
        'app' => 'App',
        'all' => 'All',
    ],

    // Table
    'table' => [
        'name' => 'Name',
        'fees' => 'Fees',
        'early_discount' => 'Early Discount',
        'post_as_paid' => 'Post as Paid',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // Actions
    'actions' => [
        'new' => 'New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'installment_commands' => 'Installment Commands',
    ],

    // Badges and labels
    'badges' => [
        'fixed' => 'Fixed',
        'fixed_installment' => 'Fixed/inst',
        'percent_installment' => '%/inst',
        'no_fees' => 'No fees',
        'yes' => 'Yes',
        'no' => 'No',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'no_name' => 'No name',
        'in_days' => 'in :daysd',
    ],

    // Dropdowns
    'dropdowns' => [
        'select_branches' => 'Select branches...',
        'loading_branches' => 'Loading branches...',
        'error_loading_branches' => 'Error loading branches',
        'error_loading' => 'Error loading',
        'no_branches' => 'No branches registered',
        'no_branches_short' => 'No branches',
        'no_gateway_selected' => 'No gateway selected (optional)',
        'loading_gateways' => 'Loading gateways...',
        'error_loading_gateways' => 'Error loading gateways',
        'no_gateways' => 'No gateways registered',
        'no_gateways_available' => 'No gateways available',
        'no_active_gateways' => 'No active gateways registered',
        'select' => 'Select...',
    ],

    // Discount example
    'discount_example' => [
        'label' => 'Example:',
        'text' => 'Paying :days days before the due date, an installment of $:amount will have a discount of :percent% ($:discount), resulting in $:final.',
    ],

    // Messages
    'messages' => [
        'load_error' => 'Error loading data',
        'server_error' => 'Error connecting to server',
        'no_records' => 'No payment methods found',
        'delete_error' => 'Error deleting record',
        'delete_confirm' => 'Do you want to delete the payment method ":name"?',
        'this_record' => 'this payment method',
        'not_found' => 'Record not found',
        'name_required' => 'Name is required',
        'branches_required' => 'Please select at least one branch',
        'save_success' => 'Saved successfully',
        'save_error' => 'Error saving',
        'saving' => 'Saving...',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search method...',
    ],

    // Record type
    'record_type' => 'forma_pagamento',

    // ===== Installment Commands =====
    'commands' => [
        'title' => 'Installment Commands',
        'new_title' => 'New Command',
        'edit_title' => 'Edit Command',

        // Fields
        'fields' => [
            'command' => 'Command',
            'command_hint' => 'Usage examples:<br><br> <b>0</b> - Upfront payment. <br><br> <b>15</b> - Payment due in 15 days. <br><br> <b>1-12</b> - Generates monthly installments from 1 to 12x. <br><br> <b>7/14/21/28</b> - In this example, 4 installments are generated with the specified due dates. <br><br> <b>Sun, Mon, Tue, Wed, Thu, Fri, Sat</b> - Specify which day of the week the due date will be. <br><br> <b>d5, d10, d15, ...</b> - Which day of the month the due date will be.<br><br> <b>w36</b> - 36 weekly installments will be created.<br><br> <b>w36-Seg</b> - 36 weekly installments will be created with the due date every Monday.',
            'description' => 'Description',
            'active' => 'Active',
        ],

        // Table
        'table' => [
            'command' => 'Command',
            'description' => 'Description',
            'origin' => 'Origin',
            'status' => 'Status',
            'actions' => 'Actions',
        ],

        // Badges
        'badges' => [
            'system' => 'System',
            'custom' => 'Custom',
            'system_command' => 'System command',
        ],

        // Actions
        'actions' => [
            'new' => 'New Command',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        // Placeholders
        'placeholders' => [
            'search' => 'Search command...',
            'command' => 'Ex: 0, 1-12, 7/14/21/28',
            'description' => 'Optional command description',
        ],

        // Messages
        'messages' => [
            'no_records' => 'No installment commands found',
            'load_error' => 'Error loading data',
            'server_error' => 'Error connecting to server',
            'command_required' => 'The Command field is required.',
            'save_success' => 'Command saved successfully!',
            'save_error' => 'Error saving command.',
            'load_command_error' => 'Error loading command',
            'not_found' => 'Record not found',
            'delete_error' => 'Error deleting record.',
            'delete_confirm' => 'Do you want to delete the command ":name"?',
            'this_record' => 'this command',
        ],

        // Pagination
        'pagination' => [
            'rows_per_page' => 'Rows per page:',
            'showing' => 'Showing :start-:end of :total records',
        ],
    ],
];
