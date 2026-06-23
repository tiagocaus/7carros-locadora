<?php

/**
 * Traducciones del módulo Dashboard - Español
 */

return [
    'title' => 'Panel de Control',

    // KPI Cards
    'kpi' => [
        'total_vehicles' => 'Total de Vehículos',
        'rented_today' => 'Alquilados Hoy',
        'occupancy_rate' => 'Tasa de Ocupación',
        'expected_revenue_today' => 'Ingresos Prev. Hoy',
    ],

    // Barra de disponibilidad
    'availability' => [
        'title' => 'Disponibilidad de Vehículos',
        'total' => 'Total',
        'available' => 'Disponibles',
        'rented' => 'Alquilados',
        'reserved' => 'Reservados',
        'workshop' => 'Taller',
    ],

    'operations' => [
        'reservations_pending' => 'Reservas/Pendientes',
        'reserved' => 'Reservados',
        'pending' => 'Pendientes',
    ],

    // Sub-tabs
    'tabs' => [
        'quick_search' => 'Búsqueda rápida',
        'reservations' => 'Reservas',
        'rented' => 'Alquilados',
        'available' => 'Disponibles',
        'pending_arrival' => 'Llegada pendiente',
        'upcoming_returns' => 'Próximas Devoluciones',
    ],

    // Placeholders
    'placeholders' => [
        'tab_content' => 'Contenido de la pestaña ":tab" aquí.',
        'tab_content_will_appear' => 'Contenido de la pestaña ":tab" aparecerá aquí.',
    ],

    'subtabs' => [
        'reservations_empty' => 'No se encontraron reservas.',
        'rented_empty' => 'No se encontró ningún alquiler o contrato abierto.',
        'available_empty' => 'No se encontró ningún vehículo disponible.',
        'pending_arrival_empty' => 'No hay llegadas pendientes.',
        'upcoming_returns_empty' => 'No hay devoluciones próximas.',
        'departure' => 'Salida',
        'expected' => 'Prevista',
        'loading' => 'Cargando :title...',
        'load_error' => 'No fue posible cargar los datos de esta pestaña.',
        'updated' => 'Actualizado :time',
        'plate' => 'Placa',
        'vehicle' => 'Vehículo',
        'group' => 'Grupo',
        'branch' => 'Sucursal',
        'odometer' => 'Odómetro',
        'actions' => 'Acciones',
        'code' => 'Código',
        'type' => 'Tipo',
        'client' => 'Cliente',
        'deadline' => 'Plazo',
        'open' => 'Abrir',
        'rental' => 'Alquiler',
        'contract' => 'Contrato',
        'today' => 'Hoy',
        'tomorrow' => 'Mañana',
        'pending_pickup' => 'Retirada pendiente',
        'available_badge' => 'Disponible',
        'no_vehicle' => 'Sin vehículo',
        'contract_duration_today' => 'Iniciado hoy',
        'contract_duration_days' => ':count día de contrato|:count días de contrato',
        'overdue_minutes' => ':count min de atraso|:count min de atraso',
        'overdue_hours' => ':count h de atraso|:count h de atraso',
        'overdue_days' => ':count día de atraso|:count días de atraso',
    ],

    // Dashboard v2 (Cockpit)
    'v2' => [
        'title' => 'Panel de Control',

        'kpi' => [
            'rented_now' => 'Alquilados Ahora',
            'utilization_rate' => 'Tasa de Utilización',
            'average_daily_rate' => 'Tarifa Diaria Media (ADR)',
            'revenue_month' => 'Ingresos del Mes',
            'overdue_amount' => 'Cuentas Vencidas',
            'active_contracts' => 'Contratos Activos',
            'maintenance_cost' => 'Costo Mant. %',
            'invoices' => 'facturas',
            'expiring_soon' => 'vencen pronto',
        ],

        'operations' => [
            'title' => 'Operaciones del Día',
            'departures_today' => 'Salidas Hoy',
            'returns_today' => 'Devoluciones Hoy',
            'overdue_returns' => 'Atrasados',
        ],

        'alerts' => [
            'title' => 'Alertas',
            'overdue_vehicles' => 'vehículos atrasados en la devolución',
            'expiring_contracts' => 'contratos vencen en 7 días',
            'expiring_insurance' => 'seguro vence en 5 días',
            'overdue_invoices' => 'en facturas vencidas',
            'pending_fines' => 'multas pendientes',
            'pending_maintenance' => 'vehículos con mantenimiento preventivo pendiente',
        ],

        'reservations' => [
            'upcoming_title' => 'Reservas Próximos 7 Días',
            'latest_title' => 'Últimas Reservas',
            'code' => 'Código',
            'client' => 'Cliente',
            'vehicle' => 'Vehículo',
            'date' => 'Fecha',
            'status_confirmed' => 'Confirmada',
            'status_new' => 'Nueva',
            'status_cancelled' => 'Cancelada',
        ],

        'financial' => [
            'title' => 'Resumen Financiero',
            'cash_flow' => 'Flujo del Mes',
            'revenue' => 'Ingresos',
            'expenses' => 'Gastos',
            'balance' => 'Saldo',
            'top_overdue' => 'Mayores Vencidas',
            'upcoming_due' => 'Vencen en 7 Días',
        ],

        'refresh' => [
            'auto_refresh' => 'Actualiza cada :seconds s',
        ],
    ],
];
