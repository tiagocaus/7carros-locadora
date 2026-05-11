<?php

/**
 * Translations for the Grupos module - English (United States)
 */

return [
    'title' => 'Vehicle Groups',
    'title_singular' => 'Group',
    'new_title' => 'New Group',
    'edit_title' => 'Edit Group',

    // Tabs
    'tabs' => [
        'group_data' => 'Group Data',
        'prices_by_days' => 'Prices by Days',
    ],

    // Sections
    'sections' => [
        'basic_data' => 'Basic Data',
        'rental_plans' => 'Rental Plans',
        'insurance' => 'Insurance',
        'tolerance_extras' => 'Tolerance and Extras',
        'investor_commission' => 'Investor Commission',
        'progressive_prices' => 'Progressive Prices by Days',
    ],

    // Fields
    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'visible_on_site' => 'Visible on site',
        'km_paid_value' => 'Paid Mileage Rate',
        'km_controlled_value' => 'Controlled Mileage Rate',
        'km_free_value' => 'Free Mileage Rate',
        'km_excess_value' => 'Excess Mileage Rate',
        'km_franchise' => 'Mileage Allowance',
        'car_insurance_value' => 'Car Insurance Rate (per day)',
        'third_party_insurance_value' => 'Third Party Insurance Rate (per day)',
        'car_coverage' => 'Car Coverage',
        'third_party_coverage' => 'Third Party Coverage',
        'tolerance_minutes' => 'Tolerance Minutes',
        'tolerance_value' => 'Tolerance Fee',
        'return_km_value' => 'Return Mileage Rate',
        'additional_driver_value' => 'Additional Driver Fee',
        'commission_type' => 'Commission Type',
        'commission_value' => 'Value',
    ],

    // Commission options
    'commission_options' => [
        'none' => 'None (no commission)',
        'percentage_rental' => 'Percentage for Rental Company',
        'fixed_rental_invoice' => 'Fixed Amount for Rental Company (per invoice)',
        'fixed_rental_monthly' => 'Fixed Monthly Amount for Rental Company',
        'fixed_investor_monthly' => 'Fixed Monthly Amount for Investor',
    ],

    // Dynamic commission labels
    'commission_labels' => [
        'rental_percentage' => 'Rental Company Percentage',
        'fixed_per_invoice' => 'Fixed Amount per Invoice',
        'monthly_rental' => 'Monthly Amount (Rental Company)',
        'monthly_investor' => 'Monthly Amount (Investor)',
    ],

    // Commission hints
    'commission_hints' => [
        'percentage_rental' => 'E.g.: 20% means the rental company keeps 20% of the value and the investor receives 80%.',
        'fixed_rental_invoice' => 'E.g.: $50 per invoice means the rental company keeps a fixed $50 from each payment.',
        'fixed_rental_monthly' => 'E.g.: $300/month per vehicle. The rental company receives this fixed monthly amount for each investor vehicle.',
        'fixed_investor_monthly' => 'E.g.: $2,000/month per vehicle. The investor receives this fixed monthly amount, regardless of rentals.',
    ],

    // Descriptions
    'descriptions' => [
        'investor_commission' => 'Configure how the commission will be calculated for investor vehicles in this group.',
        'progressive_prices' => 'Configure differentiated rates based on the number of rental days. If no range is configured, the base rate will be used.',
    ],

    // Price sub-tabs
    'price_tabs' => [
        'km_paid' => 'Paid Mileage',
        'km_controlled' => 'Controlled Mileage',
        'km_free' => 'Free Mileage',
    ],

    // Price ranges
    'ranges' => [
        'from' => 'From',
        'to' => 'to',
        'days_equals' => 'days =',
        'add_range' => 'Add Range',
        'no_ranges' => 'No ranges configured. The base rate will be used.',
        'infinity' => '(unlimited)',
    ],

    // Image
    'image' => [
        'alt' => 'Group Image',
        'change' => 'Change Image',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search group...',
    ],

    // Table
    'table' => [
        'image' => 'Image',
        'name' => 'Name',
        'description' => 'Description',
        'site' => 'Site',
        'actions' => 'Actions',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No groups found',
        'no_name' => 'No name',
        'load_error' => 'Error loading groups',
        'server_error' => 'Error connecting to server',
        'delete_error' => 'Error deleting group',
        'this_record' => 'this group',
        'load_group_error' => 'Error loading group',
        'invalid_image_format' => 'Please select a valid image (JPG, PNG or WebP)',
        'image_too_large' => 'Image must be no larger than 5MB',
        'name_required' => 'Name is required',
        'saving' => 'Saving...',
        'save_error' => 'Error saving',
        'save_server_error' => 'Error saving group',
        'created' => 'Group created successfully!',
        'updated' => 'Group updated successfully!',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type
    'record_type' => 'group',
];
