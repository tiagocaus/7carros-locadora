<?php

/**
 * Translations for the Promotions module - English (United States)
 */

return [
    'title' => 'Promotions',
    'title_singular' => 'Promotion',
    'new_title' => 'New Promotion',
    'edit_title' => 'Edit Promotion',

    // Sections
    'sections' => [
        'promotion_data' => 'Promotion Details',
    ],

    // Fields
    'fields' => [
        'branches' => 'Branches',
        'code' => 'Code',
        'name' => 'Promotion Name',
        'validity' => 'Validity',
        'minimum_days' => 'Minimum Daily Rate',
        'discount_type' => 'Discount Type',
        'discount_value' => 'Discount Value',
        'where_to_show' => 'Where to Display',
        'status' => 'Status',
    ],

    // Tooltips
    'tooltips' => [
        'validity' => 'Deadline for using the promotion. Leave blank for no expiration.',
        'minimum_days' => 'Minimum number of rental days for the promotion to be valid.',
        'where_to_show' => 'Select where this promotion will be available.',
    ],

    // Type options
    'type_options' => [
        'fixed' => 'Fixed',
        'percentage' => 'Percentage (%)',
    ],

    // Status options
    'status_options' => [
        'active' => 'Active',
        'disabled' => 'Disabled',
    ],

    // Display options
    'display_options' => [
        'system' => 'System',
        'site' => 'Website',
        'app' => 'App',
        'all' => 'All',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search promotion...',
        'select_branches' => 'Select branches...',
        'select' => 'Select...',
        'code_example' => 'E.g.: PROMO2024',
        'name_example' => 'E.g.: Summer Discount',
    ],

    // Badges
    'badges' => [
        'type_percentage' => 'Percentage',
        'type_fixed' => 'Fixed',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
    ],

    // Table
    'table' => [
        'code' => 'Code',
        'name' => 'Name',
        'type' => 'Type',
        'value' => 'Value',
        'min_days' => 'Min Days',
        'branches' => 'Branches',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No promotions found',
        'no_name' => 'No name',
        'all_branches' => 'All',
        'days_suffix' => 'days',
        'load_error' => 'Error loading data',
        'server_error' => 'Error connecting to server',
        'delete_error' => 'Error deleting record',
        'this_record' => 'this promotion',
        'not_found' => 'Promotion not found',
        'load_branches_error' => 'Error loading branches',
        'load_branches_text' => 'Error loading',
        'no_branches' => 'No branches registered',
        'no_branches_text' => 'No branches',
        'loading_branches' => 'Loading branches...',
        'required_fields' => 'Please fill in the required fields:',
        'saving' => 'Saving...',
        'save_error' => 'Error saving',
        'created' => 'Promotion created successfully!',
        'updated' => 'Promotion updated successfully!',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Records per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'promotion',
];
