<?php

/**
 * Dashboard module translations - English (US)
 */

return [
    'title' => 'Dashboard',

    // KPI Cards
    'kpi' => [
        'total_vehicles' => 'Total Vehicles',
        'rented_today' => 'Rented Today',
        'occupancy_rate' => 'Occupancy Rate',
        'expected_revenue_today' => 'Expected Revenue Today',
    ],

    // Availability bar
    'availability' => [
        'title' => 'Vehicle Availability',
        'total' => 'Total',
        'available' => 'Available',
        'rented' => 'Rented',
        'reserved' => 'Reserved',
        'workshop' => 'Workshop',
    ],

    // Sub-tabs
    'tabs' => [
        'quick_search' => 'Quick search',
        'reservations' => 'Reservations',
        'rented' => 'Rented',
        'available' => 'Available',
        'pending_arrival' => 'Pending arrival',
        'upcoming_returns' => 'Upcoming Returns',
    ],

    // Placeholders
    'placeholders' => [
        'tab_content' => '":tab" tab content here.',
        'tab_content_will_appear' => '":tab" tab content will appear here.',
    ],

    'subtabs' => [
        'reservations_empty' => 'No reservations found.',
        'rented_empty' => 'No open rental or contract found.',
        'available_empty' => 'No available vehicle found.',
        'pending_arrival_empty' => 'No pending arrival found.',
        'upcoming_returns_empty' => 'No upcoming return found.',
        'departure' => 'Departure',
        'expected' => 'Expected',
        'loading' => 'Loading :title...',
        'load_error' => 'Could not load this tab data.',
        'updated' => 'Updated :time',
        'plate' => 'Plate',
        'vehicle' => 'Vehicle',
        'group' => 'Group',
        'branch' => 'Branch',
        'odometer' => 'Odometer',
        'actions' => 'Actions',
        'code' => 'Code',
        'type' => 'Type',
        'client' => 'Client',
        'deadline' => 'Due',
        'open' => 'Open',
        'rental' => 'Rental',
        'contract' => 'Contract',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'pending_pickup' => 'Pending pickup',
        'available_badge' => 'Available',
        'no_vehicle' => 'No vehicle',
        'contract_duration_today' => 'Started today',
        'contract_duration_days' => ':count contract day|:count contract days',
        'overdue_minutes' => ':count min overdue|:count min overdue',
        'overdue_hours' => ':count h overdue|:count h overdue',
        'overdue_days' => ':count day overdue|:count days overdue',
    ],

    // Dashboard v2 (Cockpit)
    'v2' => [
        'title' => 'Control Panel',

        'kpi' => [
            'rented_now' => 'Rented Now',
            'utilization_rate' => 'Utilization Rate',
            'average_daily_rate' => 'Avg. Daily Rate (ADR)',
            'revenue_month' => 'Monthly Revenue',
            'overdue_amount' => 'Overdue Receivables',
            'active_contracts' => 'Active Contracts',
            'maintenance_cost' => 'Maint. Cost %',
            'invoices' => 'invoices',
            'expiring_soon' => 'expiring soon',
        ],

        'operations' => [
            'title' => 'Today\'s Operations',
            'departures_today' => 'Departures Today',
            'returns_today' => 'Returns Today',
            'overdue_returns' => 'Overdue',
        ],

        'alerts' => [
            'title' => 'Alerts',
            'overdue_vehicles' => 'vehicles overdue for return',
            'expiring_contracts' => 'contracts expire in 7 days',
            'expiring_insurance' => 'insurance expires in 5 days',
            'overdue_invoices' => 'in overdue invoices',
            'pending_fines' => 'pending fines',
            'pending_maintenance' => 'vehicles with pending preventive maintenance',
        ],

        'reservations' => [
            'upcoming_title' => 'Reservations Next 7 Days',
            'latest_title' => 'Latest Reservations',
            'code' => 'Code',
            'client' => 'Client',
            'vehicle' => 'Vehicle',
            'date' => 'Date',
            'status_confirmed' => 'Confirmed',
            'status_new' => 'New',
            'status_cancelled' => 'Cancelled',
        ],

        'financial' => [
            'title' => 'Financial Summary',
            'cash_flow' => 'Monthly Cash Flow',
            'revenue' => 'Revenue',
            'expenses' => 'Expenses',
            'balance' => 'Balance',
            'top_overdue' => 'Top Overdue',
            'upcoming_due' => 'Due in 7 Days',
        ],

        'refresh' => [
            'auto_refresh' => 'Refreshes every :seconds s',
        ],
    ],
];
