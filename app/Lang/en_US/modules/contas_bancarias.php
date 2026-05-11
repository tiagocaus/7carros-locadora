<?php

/**
 * Translations for the Bank Accounts module - English (US)
 */

return [
    'title' => 'Bank Accounts/Cash Register',
    'title_singular' => 'Bank Account/Cash Register',
    'new_title' => 'New Account',
    'edit_title' => 'Edit Account',

    // Sections
    'sections' => [
        'account_data' => 'Account Data',
        'bank_data' => 'Bank Data',
        'notes' => 'Notes',
    ],

    // Fields
    'fields' => [
        'name' => 'Name',
        'type' => 'Type',
        'status' => 'Status',
        'bank' => 'Bank',
        'branch' => 'Branch',
        'account_number' => 'Account Number',
        'notes' => 'Notes',
    ],

    // Type options
    'type_options' => [
        'bank_account' => 'Bank Account',
        'cash' => 'Cash Register',
    ],

    // Badges
    'badges' => [
        'type_bank' => 'Bank',
        'type_cash' => 'Cash',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search account...',
        'name_example' => 'Ex: Main Cash Register, Bank of America',
        'bank_example' => 'Ex: Bank of America, Chase',
        'branch_example' => 'Ex: 1234-5',
        'account_example' => 'Ex: 12345-6',
        'notes_example' => 'Additional information about the account...',
    ],

    // Table
    'table' => [
        'name' => 'Name',
        'type' => 'Type',
        'bank' => 'Bank',
        'branch' => 'Branch',
        'account' => 'Account',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No accounts found',
        'no_name' => 'No name',
        'load_error' => 'Error loading accounts',
        'server_error' => 'Error connecting to the server',
        'delete_error' => 'Error deleting account',
        'this_record' => 'this account',
        'not_found' => 'Account not found',
        'load_account_error' => 'Error loading account data',
        'name_required' => 'Please enter the account name',
        'saving' => 'Saving...',
        'save_error' => 'Error saving account',
        'saved' => 'Account saved successfully',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Records per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'account',
];
