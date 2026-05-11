<?php

/**
 * Maintenance Plan module translations - English (US)
 *
 * Strings specific to the Maintenance Plans CRUD
 */

return [
    // Titles
    'title' => 'Maintenance Plans',
    'title_new' => 'Add Maintenance Plan',
    'title_edit' => 'Edit Maintenance Plan',

    // Buttons
    'btn_new' => 'New',
    'btn_save' => 'Save',
    'btn_cancel' => 'Cancel',
    'btn_back' => 'Back',

    // Form labels
    'field_name' => 'Plan Name',
    'field_name_placeholder' => 'E.g.: Standard Plan, Premium Plan...',
    'field_vehicle_type' => 'Vehicle Type',
    'vehicle_car' => 'Car',
    'vehicle_motorcycle' => 'Motorcycle',
    'field_status' => 'Status',
    'field_status_active' => 'Active',
    'field_status_inactive' => 'Inactive',
    'field_interval' => 'Interval (km)',
    'field_interval_placeholder' => '0',
    'field_interval_hint' => 'Set to 0 to disable this item',

    // Form sections
    'section_basic' => 'Basic Information',
    'section_intervals' => 'Maintenance Intervals',
    'section_intervals_hint' => 'Configure the interval in kilometers for each maintenance item. Items with interval 0 will be ignored.',

    // Table
    'table_name' => 'Name',
    'table_status' => 'Status',
    'table_items' => 'Configured Items',
    'table_actions' => 'Actions',
    'table_empty' => 'No maintenance plans found',
    'table_loading' => 'Loading...',

    // Messages
    'messages' => [
        'created' => 'Maintenance plan created successfully!',
        'updated' => 'Maintenance plan updated successfully!',
        'deleted' => 'Maintenance plan deleted successfully!',
        'not_found' => 'Maintenance plan not found.',
        'name_required' => 'Plan name is required.',
        'confirm_delete' => 'Do you want to delete the plan ":name"?',
        'has_vehicles' => 'This plan is linked to vehicles and cannot be deleted.',
        'load_error' => 'Error loading maintenance plans.',
        'save_error' => 'Error saving maintenance plan.',
        'delete_error' => 'Error deleting maintenance plan.',
        'no_name' => 'No name',
        'this_plan' => 'this plan',
    ],

    // Pagination
    'pagination_info' => 'Showing :start-:end of :total records',
    'pagination_per_page' => 'Records per page',
    'pagination_page_navigation' => 'Page navigation',

    // Search
    'search_placeholder' => 'Search plan...',

    // Tooltips
    'tooltip_edit' => 'Edit plan',
    'tooltip_delete' => 'Delete plan',
    'tooltip_interval' => 'Kilometers between maintenance',
];
