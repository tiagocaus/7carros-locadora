<?php

/**
 * Translations for the Documents module - English (US)
 */

return [
    'title' => 'Document Templates',
    'title_singular' => 'Document',
    'new_title' => 'New Document',
    'edit_title' => 'Edit Document',

    // Type filters
    'filters' => [
        'all' => 'All',
        'both' => 'Contract/Rental',
        'contract' => 'Contract',
        'rental' => 'Rental',
        'fine' => 'Fine',
    ],

    // Table
    'table' => [
        'title' => 'Title',
        'type' => 'Type',
        'status' => 'Status',
        'updated_at' => 'Updated at',
        'actions' => 'Actions',
    ],

    // Badges
    'badges' => [
        'type_both' => 'Contract/Rental',
        'type_contract' => 'Contract',
        'type_rental' => 'Rental',
        'type_fine' => 'Fine',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
    ],

    // Form fields
    'fields' => [
        'title' => 'Title',
        'type' => 'Type',
        'status' => 'Status',
        'content' => 'Content',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search document...',
        'title_example' => 'Ex: Rental Agreement',
    ],

    // Variables panel
    'variables' => [
        'title' => 'Available Variables',
        'description' => 'Click to insert into the editor',
        'no_variables' => 'No variables available',
        'load_error' => 'Error loading variables',
    ],

    // Description
    'description' => 'Create document templates with auto-filled variables',

    // Messages
    'messages' => [
        'no_records' => 'No documents found',
        'no_title' => 'No title',
        'load_error' => 'Error loading documents',
        'server_error' => 'Error connecting to the server',
        'delete_error' => 'Error deleting document',
        'this_record' => 'this document',
        'title_required' => 'Title is required',
        'saving' => 'Saving...',
        'save_error' => 'Error saving document',
        'saved' => 'Document saved successfully',
        'imported' => 'Document imported successfully!',
        'editor_error' => 'Error loading editor. Please reload the page.',
        'content_required' => 'Enter some content to preview',
        'preview_error' => 'Error generating preview',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'document',
];
