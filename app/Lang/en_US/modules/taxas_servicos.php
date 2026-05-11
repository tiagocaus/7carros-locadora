<?php

/**
 * Translations for the Fees and Services module - English (US)
 */

return [
    'title' => 'Fees and Services',
    'title_singular' => 'Fee/Service',
    'new_title' => 'New Fee/Service',
    'edit_title' => 'Edit Fee/Service',

    // Sections
    'sections' => [
        'fee_data' => 'Fee/Service Data',
    ],

    // Fields
    'fields' => [
        'name' => 'Name',
        'branches' => 'Branches',
        'calculation_base' => 'Calculation Base',
        'value_type' => 'Value Type',
        'value' => 'Value',
        'auto_apply' => 'Auto Apply',
        'where_to_use' => 'Where to Use',
    ],

    // Tooltips
    'tooltips' => [
        'auto_apply' => 'When active, the fee will be automatically added to new contracts.',
        'where_to_use' => 'Select where this fee will be available.',
    ],

    // Calculation base options
    'calculation_options' => [
        'fixed' => 'Fixed (single value)',
        'per_period' => 'Per Period (calculated per day)',
        'total_value' => 'Total Value',
    ],

    // Value type options
    'value_type_options' => [
        'monetary' => 'Monetary ($)',
        'percentage' => 'Percentage (%)',
    ],

    // Apply options
    'apply_options' => [
        'no' => 'No (requires manual selection)',
        'yes' => 'Yes (applied automatically)',
    ],

    // Where to use options
    'display_options' => [
        'system' => 'System',
        'site' => 'Website',
        'app' => 'App',
        'all' => 'All',
    ],

    // Badges
    'badges' => [
        'base_fixed' => 'Fixed',
        'base_per_period' => 'Per Period',
        'base_total_value' => 'Total Value',
        'apply_yes' => 'Yes',
        'apply_no' => 'No',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search fee...',
        'select_branches' => 'Select branches...',
        'all_branches' => 'All branches',
        'select' => 'Select...',
        'name_example' => 'Ex: Cleaning fee',
    ],

    // Table
    'table' => [
        'name' => 'Name',
        'calculation_base' => 'Calc. Base',
        'value' => 'Value',
        'auto_apply' => 'Auto Apply',
        'branches' => 'Branches',
        'actions' => 'Actions',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No fees or services found',
        'no_name' => 'No name',
        'all_branches' => 'All',
        'load_error' => 'Error loading data',
        'server_error' => 'Error connecting to the server',
        'delete_error' => 'Error deleting record',
        'this_record' => 'this fee/service',
        'not_found' => 'Fee/service not found',
        'load_branches_error' => 'Error loading branches',
        'load_branches_text' => 'Error loading',
        'no_branches' => 'No branches registered',
        'no_branches_text' => 'No branches',
        'loading_branches' => 'Loading branches...',
        'required_fields' => 'Please fill in the required fields:',
        'saving' => 'Saving...',
        'save_error' => 'Error saving',
        'created' => 'Fee/service created successfully!',
        'updated' => 'Fee/service updated successfully!',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Records per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'taxa_servico',
];
