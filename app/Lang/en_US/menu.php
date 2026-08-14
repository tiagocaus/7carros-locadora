<?php

/**
 * Menu and navigation items - English (US)
 *
 * Contains all menu items, navigation bar,
 * sidebar and system notifications.
 */

return [
    // Main menu
    'main' => [
        'dashboard' => 'Dashboard',
        'home' => 'Home',
    ],

    // Top Bar - System selector
    'topbar' => [
        'rental' => 'Vehicle rental',
        'workshop' => 'Auto repair shop',
        'parts' => 'Auto parts',
        'inspection' => 'Vehicle inspection',
        'resale' => 'Vehicle resale',
    ],

    // System menu
    'sistema' => [
        'title' => 'System',
        'referral_program' => 'Referral program',
        'feature_request' => 'Request new feature',
        'activity_logs' => 'Activity logs',
        'grant_access' => 'Grant access',
        'settings' => 'Settings',
        'message_templates' => 'Message Templates',
        'changelog' => 'Changelog',
        'screen_recording' => 'Screen recording',
        'logout' => 'Log out',
    ],

    // Contracts/Rentals menu
    'contratos_loc' => [
        'title' => 'Contracts/Rentals',
        'new_rental' => 'New Rental',
        'rentals_reservations' => 'Rentals/Reservations',
        'new_contract' => 'New contract',
        'contracts' => 'Contracts',
    ],

    // Company menu
    'empresa' => [
        'title' => 'Company',
        'branches' => 'Headquarters & branches',
        'clients' => 'Clients',
        'messaging' => 'WhatsApp, SMS & SMTP',
        'employees' => 'Employees',
        'documents' => 'Documents',
        'fees_services' => 'Fees & services',
        'workshops' => 'Workshops',
        'promotions' => 'Promotions',
        'fines' => 'Fines',
        'fines_central' => 'Traffic Fines Center',
        'bank_accounts' => 'Bank accounts/Cash registers',
        'payment_methods' => 'Payment methods',
        'payment_gateways' => 'Payment gateways',
        'suppliers' => 'Suppliers',
        'inventory' => 'Inventory',
    ],

    // Vehicles menu
    'veiculos_menu' => [
        'title' => 'Vehicles',
        'vehicles' => 'Vehicles',
        'groups' => 'Groups',
        'seasons' => 'Seasons',
        'accessories' => 'Accessories & items',
        'maintenance' => 'Maintenance',
        'maintenance_plans' => 'Maintenance plans',
        'checklist' => 'Checklist',
        'checklist_templates' => 'Checklist templates',
    ],

    // Reports menu
    'relatorios_menu' => [
        'title' => 'Reports',
        // KPIs
        'kpis' => 'KPIs / Indicators',
        'kpi_occupancy_rate' => 'Fleet occupancy rate',
        'kpi_revpar' => 'RevPAR (Revenue per vehicle/day)',
        'kpi_adr' => 'Average daily rate (ADR)',
        'kpi_gross_margin' => 'Gross margin per day',
        'kpi_revenue_vehicle' => 'Revenue per vehicle',
        'kpi_additional_revenue' => '% Additional revenue',
        'kpi_avg_rental_time' => 'Average rental duration',
        'kpi_roi_vehicle' => 'ROI per vehicle',
        // Financial
        'financial' => 'Financial',
        'fin_detailed' => 'Financial Transactions',
        'fin_billing' => 'Billing',
        'fin_income_statement' => 'Income statement',
        'fin_cash_result' => 'Cash-basis management result',
        'fin_cashbook' => 'Cash book',
        'fin_bank_accounts' => 'Bank accounts/Cash registers',
        'fin_chart_accounts' => 'Chart of accounts',

        'fin_revenue_projection' => 'Revenue projection',
        'fin_profitability' => 'Profitability analysis',
        'fin_delinquency' => 'Overall delinquency',
        'fin_fees_charged' => 'Fees & services charged',
        // Vehicle
        'vehicle' => 'Vehicle',
        'veh_maintenance' => 'Vehicle maintenance',
        'veh_profit' => 'Profit per vehicle',
        'veh_expenses' => 'Vehicle expenses',
        'veh_client' => 'Vehicle/client',
        'veh_licensing' => 'Licensing',
        'veh_availability' => 'Availability',
        'veh_group_occupancy' => 'Occupancy rate by group',

        'veh_depreciation' => 'Fleet depreciation',
        'veh_avg_idle_time' => 'Average idle time',
        'veh_avg_mileage' => 'Average mileage',

        'veh_total_cost' => 'Total cost of ownership',
        // Clients
        'clients' => 'Clients',
        'cli_contracts_rentals' => 'Contracts/rentals',
        'cli_birthdays' => 'Birthdays',
        'cli_expired_license' => "Expired CNH (Driver's License)",
        'cli_top_clients' => 'Top clients (ranking)',

        'cli_rental_frequency' => 'Rental frequency',
        'cli_relationship_time' => 'Relationship duration',
        'cli_incident_history' => 'Incident history',
        'cli_inactive' => 'Inactive clients',
        // Contracts/Rentals
        'contracts_rentals' => 'Contracts/Rentals',
        'cr_general' => 'Overview',
        'cr_by_period' => 'By period',
        'cr_by_payment' => 'By payment method',

        'cr_extensions' => 'Contract extensions',
        'cr_vehicle_swap' => 'Vehicle swaps',
        // Operational
        'operational' => 'Operational',
        'op_checklists' => 'Completed checklists',
        'op_damages' => 'Damages & claims',
        'op_traffic_fines' => 'Traffic fines',
        'op_early_returns' => 'Early returns',
        'op_late_returns' => 'Late returns',
        'op_cancelled_reservations' => 'Cancelled reservations',
        'op_turnaround' => 'Turnaround (return time)',
        'op_fuel' => 'Fuel',
        // Invoices
        'invoices' => 'Invoices',
        'inv_due_upcoming' => 'Overdue/upcoming',
        'inv_by_vehicle' => 'By vehicle',
        'inv_payable_receivable' => 'Payable/receivable',
        // Commercial
        'commercial' => 'Commercial',
        'com_conversion_rate' => 'Conversion rate',
        'com_rental_origin' => 'Rental origin',
        'com_promotions_used' => 'Promotions used',
        'com_discounts_given' => 'Discounts given',
        'com_season_analysis' => 'Seasonal analysis',
        // Suppliers
        'suppliers' => 'Suppliers',
        'sup_suppliers' => 'Purchases & Payments',
        'sup_investor' => 'Investor supplier',
        // Employees
        'employees' => 'Employees',
        'emp_sales' => 'Sales',
        'emp_commissions' => 'Commissions',
        'emp_productivity' => 'Productivity',

        'emp_goals' => 'Goals vs actual',
        // Comparisons
        'comparisons' => 'Comparisons',
        'comp_monthly_annual' => 'Monthly/annual comparison',
        'comp_between_branches' => 'Branch comparison',
        'comp_vehicle_ranking' => 'Vehicle ranking',
        'comp_trends' => 'Trend analysis',
    ],

    // Financial menu
    'financeiro_menu' => [
        'title' => 'Financial',
        'entries' => 'Entries',
        'new_entry' => 'New entry',
        'promissory_notes' => 'Promissory notes',
        'investor_commissions' => 'Investor Commissions',
    ],

    // Website menu
    'website' => [
        'title' => 'Website',
        'activate' => 'Activate',
        'settings' => 'Settings',
        'appearance' => 'Appearance',
        'contents' => 'Contents',
        'banners' => 'Banners',
        'seo' => 'SEO',
        'integrations' => 'Integrations',
        'publish' => 'Publish',
    ],

    // Notifications
    'notifications' => [
        'title' => 'Notifications',
        'maintenance' => 'Maintenance',
        'tasks' => 'Tasks',
        'overdue_invoices' => 'Overdue invoices',
        'licensing' => 'Licensing',
        'expired_license' => "Expired CNH (Driver's License)",
        'problems' => 'Problems',
        'all_notifications' => 'All notifications',
    ],

    // Secondary navigation bar (shortcuts)
    'secondary_nav' => [
        'sidebar_mode' => 'Sidebar Mode',
        'rentals' => 'Rentals/Reservations',
        'contracts' => 'Contracts',
        'vehicles' => 'Vehicles',
        'clients' => 'Clients',
        'employees' => 'Employees',
        'find' => 'Find',
        'schedule' => 'Schedule',
        'branches' => 'Headquarters & Branches',
        'refresh' => 'Refresh',
    ],

    // Sidebar
    'sidebar' => [
        'home' => 'Home',
        'quick_search' => 'Quick search',
        'vehicle' => 'Vehicle',
        'select' => 'Select',
    ],

    // Tooltips and titles
    'tooltips' => [
        'select_language' => 'Select Language',
        'notifications' => 'Notifications',
        'user_profile' => 'User Profile',
        'logout' => 'Log out',
        'refresh_page' => 'Refresh page',
    ],

    // User menu
    'user' => [
        'profile' => 'My Profile',
        'settings' => 'Settings',
        'password' => 'Change Password',
        'notifications' => 'Notifications',
        'language' => 'Language',
        'logout' => 'Log out',
    ],

    // Common actions
    'actions' => [
        'new' => 'New',
        'add' => 'Add',
        'edit' => 'Edit',
        'view' => 'View',
        'delete' => 'Delete',
        'export' => 'Export',
        'import' => 'Import',
        'print' => 'Print',
        'filter' => 'Filter',
        'search' => 'Search',
    ],

    // Breadcrumbs
    'breadcrumbs' => [
        'home' => 'Home',
        'list' => 'List',
        'new' => 'New',
        'edit' => 'Edit',
        'view' => 'View',
    ],

    // Clients module (kept for compatibility)
    'clientes' => [
        'title' => 'Clients',
        'list' => 'Client List',
        'new' => 'New Client',
        'edit' => 'Edit Client',
        'view' => 'View Client',
        'import' => 'Import Clients',
        'export' => 'Export Clients',
    ],

    // Vehicles module (kept for compatibility)
    'veiculos' => [
        'title' => 'Vehicles',
        'list' => 'Vehicle List',
        'new' => 'New Vehicle',
        'edit' => 'Edit Vehicle',
        'view' => 'View Vehicle',
        'categories' => 'Categories',
        'maintenance' => 'Maintenance',
        'availability' => 'Availability',
    ],

    // Rentals module (kept for compatibility)
    'locacoes' => [
        'title' => 'Rentals',
        'list' => 'Rental List',
        'new' => 'New Rental',
        'edit' => 'Edit Rental',
        'view' => 'View Rental',
        'calendar' => 'Calendar',
        'checklist' => 'Checklist',
        'return' => 'Return',
    ],

    // Contracts module (kept for compatibility)
    'contratos' => [
        'title' => 'Contracts',
        'list' => 'Contract List',
        'new' => 'New Contract',
        'edit' => 'Edit Contract',
        'view' => 'View Contract',
        'templates' => 'Contract Templates',
    ],

    // Financial module (kept for compatibility)
    'financeiro' => [
        'title' => 'Financial',
        'dashboard' => 'Financial Dashboard',
        'receivables' => 'Accounts Receivable',
        'payables' => 'Accounts Payable',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'cashflow' => 'Cash Flow',
        'reports' => 'Reports',
    ],

    // Employees module (kept for compatibility)
    'funcionarios' => [
        'title' => 'Employees',
        'list' => 'Employee List',
        'new' => 'New Employee',
        'edit' => 'Edit Employee',
        'roles' => 'Roles & Permissions',
    ],

    // Schedule module (kept for compatibility)
    'agenda' => [
        'title' => 'Schedule',
        'calendar' => 'Calendar',
        'events' => 'Events',
        'reminders' => 'Reminders',
    ],

    // Reports module (kept for compatibility)
    'relatorios' => [
        'title' => 'Reports',
        'rentals' => 'Rental Report',
        'clients' => 'Client Report',
        'vehicles' => 'Vehicle Report',
        'financial' => 'Financial Report',
        'custom' => 'Custom Report',
    ],

    // Settings module (kept for compatibility)
    'configuracoes' => [
        'title' => 'Settings',
        'general' => 'General Settings',
        'company' => 'Company Information',
        'branches' => 'Branches',
        'payment_methods' => 'Payment Methods',
        'notifications' => 'Notifications',
        'integrations' => 'Integrations',
        'templates' => 'Message Templates',
        'documents' => 'Document Templates',
        'backup' => 'Backup',
        'logs' => 'System Logs',
    ],
];
