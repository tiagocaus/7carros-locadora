<?php

return [
    'title' => 'Investor Commissions',

    'filters' => [
        'investor' => 'Investor',
        'status' => 'Status',
        'type' => 'Type',
        'date_start' => 'Start Date',
        'date_end' => 'End Date',
    ],

    'status_options' => [
        'all' => 'All',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

    'type_options' => [
        'all' => 'All',
        'rental' => 'Rental',
        'contract' => 'Contract',
        'monthly' => 'Monthly',
    ],

    'totals' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
        'commissions_count' => 'commission(s)',
    ],

    'table' => [
        'date_ref' => 'Ref. Date',
        'investor' => 'Investor',
        'vehicle' => 'Vehicle',
        'type' => 'Type',
        'base_value' => 'Base Value',
        'rental_company' => 'Rental Company',
        'investor_value' => 'Investor',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'actions' => [
        'mark_paid' => 'Mark as Paid',
        'cancel' => 'Cancel',
    ],

    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    'messages' => [
        'no_records' => 'No records found',
        'load_error' => 'Error loading data',
        'server_error' => 'Error connecting to the server',
        'confirm_payment' => 'Confirm the payment of this commission to the investor?',
        'paid_success' => 'Commission marked as paid!',
        'cancel_reason' => 'Cancellation reason (optional):',
        'cancelled_success' => 'Commission cancelled!',
    ],
];
