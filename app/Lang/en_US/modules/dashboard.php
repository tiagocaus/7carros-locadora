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
