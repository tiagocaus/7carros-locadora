<?php

/**
 * Elementos de menú y navegación - Español (España)
 *
 * Contiene todos los elementos de menú, barra de navegación,
 * barra lateral y notificaciones del sistema.
 */

return [
    // Menú principal
    'main' => [
        'dashboard' => 'Panel',
        'home' => 'Inicio',
    ],

    // Top Bar - Selector de sistemas
    'topbar' => [
        'rental' => 'Alquiler de vehículos',
        'workshop' => 'Taller mecánico',
        'parts' => 'Repuestos',
        'inspection' => 'Inspección vehicular',
        'resale' => 'Reventa de vehículos',
    ],

    // Menú Sistema
    'sistema' => [
        'title' => 'Sistema',
        'referral_program' => 'Programa de referidos',
        'feature_request' => 'Solicitar nueva función',
        'activity_logs' => 'Registros de actividad',
        'grant_access' => 'Conceder acceso',
        'settings' => 'Configuración',
        'message_templates' => 'Plantillas de Mensaje',
        'changelog' => 'Changelog',
        'screen_recording' => 'Grabar pantalla',
        'logout' => 'Cerrar sesión',
    ],

    // Menú Contrato/Alquileres
    'contratos_loc' => [
        'title' => 'Contrato/Alquileres',
        'new_rental' => 'Nuevo Alquiler',
        'rentals_reservations' => 'Alquileres/Reservas',
        'new_contract' => 'Nuevo contrato',
        'contracts' => 'Contratos',
    ],

    // Menú Empresa
    'empresa' => [
        'title' => 'Empresa',
        'branches' => 'Sede y sucursales',
        'clients' => 'Clientes',
        'messaging' => 'WhatsApp, SMS y SMTP',
        'employees' => 'Empleados',
        'documents' => 'Documentos',
        'fees_services' => 'Tasas y servicios',
        'workshops' => 'Talleres',
        'promotions' => 'Promociones',
        'fines' => 'Multas',
        'fines_central' => 'Central de Multas',
        'bank_accounts' => 'Cuentas bancarias/caja',
        'payment_methods' => 'Formas de pago',
        'payment_gateways' => 'Pasarelas de pago',
        'suppliers' => 'Proveedores',
        'inventory' => 'Inventario',
    ],

    // Menú Vehículos
    'veiculos_menu' => [
        'title' => 'Vehículos',
        'vehicles' => 'Vehículos',
        'groups' => 'Grupos',
        'seasons' => 'Temporadas',
        'accessories' => 'Accesorios y elementos',
        'maintenance' => 'Mantenimientos',
        'maintenance_plans' => 'Plan de mantenimientos',
        'checklist' => 'Checklist',
        'checklist_templates' => 'Modelos de checklist',
    ],

    // Menú Informes
    'relatorios_menu' => [
        'title' => 'Informes',
        // KPIs
        'kpis' => 'KPIs / Indicadores',
        'kpi_occupancy_rate' => 'Tasa de ocupación de la flota',
        'kpi_revpar' => 'RevPAR (Ingreso por vehículo/día)',
        'kpi_adr' => 'Tarifa media diaria (ADR)',
        'kpi_gross_margin' => 'Margen bruto por día',
        'kpi_revenue_vehicle' => 'Ingreso por vehículo',
        'kpi_additional_revenue' => '% Ingresos adicionales',
        'kpi_avg_rental_time' => 'Tiempo medio de alquiler',
        'kpi_roi_vehicle' => 'ROI por vehículo',
        // Financiero
        'financial' => 'Financiero',
        'fin_detailed' => 'Movimientos Financieros',
        'fin_billing' => 'Facturación',
        'fin_income_statement' => 'Cuenta de resultados',
        'fin_cashbook' => 'Libro de caja',
        'fin_bank_accounts' => 'Cuentas bancarias/Cajas',
        'fin_chart_accounts' => 'Plan de cuentas',

        'fin_revenue_projection' => 'Proyección de ingresos',
        'fin_profitability' => 'Análisis de rentabilidad',
        'fin_delinquency' => 'Morosidad general',
        'fin_fees_charged' => 'Tasas y servicios cobrados',
        // Vehicular
        'vehicle' => 'Vehicular',
        'veh_maintenance' => 'Mantenimientos vehiculares',
        'veh_profit' => 'Beneficio por vehículo',
        'veh_expenses' => 'Gastos vehiculares',
        'veh_client' => 'Vehículo/cliente',
        'veh_licensing' => 'ITV y permisos',
        'veh_availability' => 'Disponibilidad',
        'veh_group_occupancy' => 'Tasa de ocupación por grupo',

        'veh_depreciation' => 'Depreciación de flota',
        'veh_avg_idle_time' => 'Tiempo medio parado',
        'veh_avg_mileage' => 'Kilometraje medio',

        'veh_total_cost' => 'Coste total de propiedad',
        // Clientes
        'clients' => 'Clientes',
        'cli_contracts_rentals' => 'Contrato/alquileres',
        'cli_birthdays' => 'Cumpleaños',
        'cli_expired_license' => 'Licencias de conducir caducadas',
        'cli_top_clients' => 'Top clientes (ranking)',

        'cli_rental_frequency' => 'Frecuencia de alquileres',
        'cli_relationship_time' => 'Antigüedad del cliente',
        'cli_incident_history' => 'Historial de incidencias',
        'cli_inactive' => 'Clientes inactivos',
        // Contratos/Alquileres
        'contracts_rentals' => 'Contratos/Alquileres',
        'cr_general' => 'Visión General',
        'cr_by_period' => 'Por período',
        'cr_by_payment' => 'Por forma de pago',

        'cr_extensions' => 'Extensiones de contrato',
        'cr_vehicle_swap' => 'Cambios de vehículo',
        // Operativo
        'operational' => 'Operativo',
        'op_checklists' => 'Checklists realizados',
        'op_damages' => 'Daños y siniestros',
        'op_traffic_fines' => 'Multas de tráfico',
        'op_early_returns' => 'Devoluciones anticipadas',
        'op_late_returns' => 'Devoluciones con retraso',
        'op_cancelled_reservations' => 'Reservas canceladas',
        'op_turnaround' => 'Turnaround (tiempo de retorno)',
        'op_fuel' => 'Combustible',
        // Facturas
        'invoices' => 'Facturas',
        'inv_due_upcoming' => 'Vencidas/por vencer',
        'inv_by_vehicle' => 'Por vehículo',
        'inv_payable_receivable' => 'A pagar/a cobrar',
        // Comercial
        'commercial' => 'Comercial',
        'com_conversion_rate' => 'Tasa de conversión',
        'com_rental_origin' => 'Origen de los alquileres',
        'com_promotions_used' => 'Promociones utilizadas',
        'com_discounts_given' => 'Descuentos concedidos',
        'com_season_analysis' => 'Análisis de temporada',
        // Proveedores
        'suppliers' => 'Proveedores',
        'sup_suppliers' => 'Compras y Pagos',
        'sup_investor' => 'Proveedor inversor',
        // Empleados
        'employees' => 'Empleados',
        'emp_sales' => 'Ventas',
        'emp_commissions' => 'Comisiones',
        'emp_productivity' => 'Productividad',

        'emp_goals' => 'Objetivos vs realizado',
        // Comparativos
        'comparisons' => 'Comparativos',
        'comp_monthly_annual' => 'Comparativo mensual/anual',
        'comp_between_branches' => 'Comparativo entre sucursales',
        'comp_vehicle_ranking' => 'Ranking de vehículos',
        'comp_trends' => 'Análisis de tendencias',
    ],

    // Menú Financiero
    'financeiro_menu' => [
        'title' => 'Financiero',
        'entries' => 'Asientos',
        'new_entry' => 'Nuevo asiento',
        'promissory_notes' => 'Pagarés',
        'investor_commissions' => 'Comisiones Inversores',
    ],

    // Menú WebSite
    'website' => [
        'title' => 'WebSite',
        'activate' => 'Activar',
        'settings' => 'Configuración',
        'appearance' => 'Apariencia',
        'contents' => 'Contenidos',
        'banners' => 'Banners',
        'seo' => 'SEO',
        'integrations' => 'Integraciones',
        'publish' => 'Publicar',
    ],

    // Notificaciones
    'notifications' => [
        'title' => 'Notificaciones',
        'maintenance' => 'Mantenimientos',
        'tasks' => 'Tareas',
        'overdue_invoices' => 'Facturas vencidas',
        'licensing' => 'ITV y permisos',
        'expired_license' => 'Licencias de conducir caducadas',
        'problems' => 'Problemas',
        'all_notifications' => 'Todas las notificaciones',
    ],

    // Barra de navegación secundaria (accesos rápidos)
    'secondary_nav' => [
        'sidebar_mode' => 'Modo Barra lateral',
        'rentals' => 'Alquileres/Reservas',
        'contracts' => 'Contratos',
        'vehicles' => 'Vehículos',
        'clients' => 'Clientes',
        'employees' => 'Empleados',
        'find' => 'Buscar',
        'schedule' => 'Agenda',
        'branches' => 'Sede y Sucursales',
        'refresh' => 'Actualizar',
    ],

    // Barra lateral
    'sidebar' => [
        'home' => 'Inicio',
        'quick_search' => 'Búsqueda rápida',
        'vehicle' => 'Vehículo',
        'select' => 'Seleccione',
    ],

    // Tooltips y títulos
    'tooltips' => [
        'select_language' => 'Seleccionar Idioma',
        'notifications' => 'Notificaciones',
        'user_profile' => 'Perfil del Usuario',
        'logout' => 'Cerrar sesión',
        'refresh_page' => 'Actualizar página',
    ],

    // Menú del usuario
    'user' => [
        'profile' => 'Mi Perfil',
        'settings' => 'Configuración',
        'password' => 'Cambiar Contraseña',
        'notifications' => 'Notificaciones',
        'language' => 'Idioma',
        'logout' => 'Cerrar sesión',
    ],

    // Acciones comunes
    'actions' => [
        'new' => 'Nuevo',
        'add' => 'Añadir',
        'edit' => 'Editar',
        'view' => 'Ver',
        'delete' => 'Eliminar',
        'export' => 'Exportar',
        'import' => 'Importar',
        'print' => 'Imprimir',
        'filter' => 'Filtrar',
        'search' => 'Buscar',
    ],

    // Migas de pan
    'breadcrumbs' => [
        'home' => 'Inicio',
        'list' => 'Lista',
        'new' => 'Nuevo',
        'edit' => 'Editar',
        'view' => 'Ver',
    ],

    // Módulo Clientes (mantenido por compatibilidad)
    'clientes' => [
        'title' => 'Clientes',
        'list' => 'Lista de Clientes',
        'new' => 'Nuevo Cliente',
        'edit' => 'Editar Cliente',
        'view' => 'Ver Cliente',
        'import' => 'Importar Clientes',
        'export' => 'Exportar Clientes',
    ],

    // Módulo Vehículos (mantenido por compatibilidad)
    'veiculos' => [
        'title' => 'Vehículos',
        'list' => 'Lista de Vehículos',
        'new' => 'Nuevo Vehículo',
        'edit' => 'Editar Vehículo',
        'view' => 'Ver Vehículo',
        'categories' => 'Categorías',
        'maintenance' => 'Mantenimientos',
        'availability' => 'Disponibilidad',
    ],

    // Módulo Alquileres (mantenido por compatibilidad)
    'locacoes' => [
        'title' => 'Alquileres',
        'list' => 'Lista de Alquileres',
        'new' => 'Nuevo Alquiler',
        'edit' => 'Editar Alquiler',
        'view' => 'Ver Alquiler',
        'calendar' => 'Calendario',
        'checklist' => 'Checklist',
        'return' => 'Devolución',
    ],

    // Módulo Contratos (mantenido por compatibilidad)
    'contratos' => [
        'title' => 'Contratos',
        'list' => 'Lista de Contratos',
        'new' => 'Nuevo Contrato',
        'edit' => 'Editar Contrato',
        'view' => 'Ver Contrato',
        'templates' => 'Modelos de Contrato',
    ],

    // Módulo Financiero (mantenido por compatibilidad)
    'financeiro' => [
        'title' => 'Financiero',
        'dashboard' => 'Panel Financiero',
        'receivables' => 'Cuentas a Cobrar',
        'payables' => 'Cuentas a Pagar',
        'invoices' => 'Facturas',
        'payments' => 'Pagos',
        'cashflow' => 'Flujo de Caja',
        'reports' => 'Informes',
    ],

    // Módulo Empleados (mantenido por compatibilidad)
    'funcionarios' => [
        'title' => 'Empleados',
        'list' => 'Lista de Empleados',
        'new' => 'Nuevo Empleado',
        'edit' => 'Editar Empleado',
        'roles' => 'Cargos y Permisos',
    ],

    // Módulo Agenda (mantenido por compatibilidad)
    'agenda' => [
        'title' => 'Agenda',
        'calendar' => 'Calendario',
        'events' => 'Eventos',
        'reminders' => 'Recordatorios',
    ],

    // Módulo Informes (mantenido por compatibilidad)
    'relatorios' => [
        'title' => 'Informes',
        'rentals' => 'Informe de Alquileres',
        'clients' => 'Informe de Clientes',
        'vehicles' => 'Informe de Vehículos',
        'financial' => 'Informe Financiero',
        'custom' => 'Informe Personalizado',
    ],

    // Módulo Configuración (mantenido por compatibilidad)
    'configuracoes' => [
        'title' => 'Configuración',
        'general' => 'Configuración General',
        'company' => 'Datos de la Empresa',
        'branches' => 'Sucursales',
        'payment_methods' => 'Formas de Pago',
        'notifications' => 'Notificaciones',
        'integrations' => 'Integraciones',
        'templates' => 'Plantillas de Mensaje',
        'documents' => 'Modelos de Documento',
        'backup' => 'Copia de seguridad',
        'logs' => 'Registros del Sistema',
    ],
];
