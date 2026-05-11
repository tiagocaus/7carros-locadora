<?php

/**
 * Translations for the Inventory module - English (US)
 */

return [
    'title' => 'Inventory',
    'title_singular' => 'Product',
    'new_title' => 'New Product',
    'edit_title' => 'Edit Product',

    // Sections
    'sections' => [
        'product_data' => 'Product Data',
        'stock' => 'Inventory',
        'values' => 'Values',
    ],

    // Fields
    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'brand' => 'Brand',
        'model' => 'Model',
        'unit' => 'Unit',
        'storage_location' => 'Storage Location',
        'branch' => 'Headquarters/Branch',
        'supplier' => 'Supplier',
        'current_stock' => 'Current Stock',
        'minimum_stock' => 'Minimum Stock',
        'purchase_value' => 'Purchase Value',
        'sale_value' => 'Sale Value',
        'auto_deduct' => 'Auto deduct',
        'auto_deduct_enable' => 'Enable',
        'allow_negative_stock' => 'Allow negative stock',
        'allow_negative_stock_enable' => 'Enable',
    ],

    // Unit options
    'unit_options' => [
        'UN' => 'UN - Unit',
        'PC' => 'PC - Piece',
        'CX' => 'CX - Box',
        'KG' => 'KG - Kilogram',
        'L' => 'L - Liter',
        'M' => 'M - Meter',
        'M2' => 'M2 - Square Meter',
        'M3' => 'M3 - Cubic Meter',
        'JG' => 'JG - Set',
        'KIT' => 'KIT - Kit',
        'PAR' => 'PAR - Pair',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search...',
        'select' => 'Select...',
        'storage_location' => 'Ex: Shelf A3',
        'search_branch' => 'Type to search...',
        'search_supplier' => 'Type to search...',
        'none' => 'None',
    ],

    // Status
    'status' => [
        'label' => 'Status',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    // Filters
    'filters' => [
        'all_branches' => 'All branches',
        'all_status' => 'All statuses',
    ],

    // Tooltips
    'tooltips' => [
        'minimum_stock' => 'Alert when this value is reached. 0 = disabled.',
        'auto_deduct' => 'When enabled, stock will be automatically decremented when this product is used in a maintenance work order.',
        'allow_negative_stock' => 'When enabled, allows using this product even with no available stock. When disabled, prevents selection with zero stock and limits quantity to available stock.',
    ],

    // Table
    'table' => [
        'code' => 'Code',
        'product' => 'Product',
        'brand_model' => 'Brand/Model',
        'unit' => 'Unit',
        'stock' => 'Stock',
        'purchase_value' => 'Purchase Value',
        'branch' => 'Branch',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No records found',
        'no_name' => 'No name',
        'load_error' => 'Error loading',
        'server_error' => 'Error connecting to the server',
        'delete_error' => 'Error deleting',
        'inactivated' => 'Product inactivated. It is linked to maintenance and cannot be deleted.',
        'reactivated' => 'Product reactivated successfully!',
        'already_inactive' => 'Product is already inactive',
        'reactivate_error' => 'Error reactivating',
        'this_record' => 'this record',
        'load_data_error' => 'Error loading data',
        'load_product_error' => 'Error loading product data',
        'saving' => 'Saving...',
        'save_error' => 'Error saving',
        'save_product_error' => 'Error saving product',
        'created' => 'Product created successfully!',
        'updated' => 'Product updated successfully!',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'inventory',
];
