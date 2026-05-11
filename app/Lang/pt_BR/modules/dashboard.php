<?php

/**
 * Traduções do módulo Dashboard - Português (Brasil)
 */

return [
    'title' => 'Dashboard',

    // KPI Cards
    'kpi' => [
        'total_vehicles' => 'Total de Veículos',
        'rented_today' => 'Locados Hoje',
        'occupancy_rate' => 'Taxa de Ocupação',
        'expected_revenue_today' => 'Receita Prev. Hoje',
    ],

    // Barra de disponibilidade
    'availability' => [
        'title' => 'Disponibilidade de Veículos',
        'total' => 'Total',
        'available' => 'Disponíveis',
        'rented' => 'Locados',
        'reserved' => 'Reservados',
        'workshop' => 'Oficina',
    ],

    // Sub-tabs
    'tabs' => [
        'quick_search' => 'Busca rápida',
        'reservations' => 'Reservas',
        'rented' => 'Alugados',
        'available' => 'Disponíveis',
        'pending_arrival' => 'Chegada pendente',
        'upcoming_returns' => 'Próximas Devoluções',
    ],

    // Placeholders
    'placeholders' => [
        'tab_content' => 'Conteúdo da sub-aba ":tab" aqui.',
        'tab_content_will_appear' => 'Conteúdo da sub-aba ":tab" aparecerá aqui.',
    ],

    // Dashboard v2 (Cockpit)
    'v2' => [
        'title' => 'Painel de Controle',

        'kpi' => [
            'rented_now' => 'Locados Agora',
            'utilization_rate' => 'Taxa de Utilização',
            'average_daily_rate' => 'Diária Média (ADR)',
            'revenue_month' => 'Receita do Mês',
            'overdue_amount' => 'A Receber Vencido',
            'active_contracts' => 'Contratos Ativos',
            'maintenance_cost' => 'Custo Manut. %',
            'invoices' => 'títulos',
            'expiring_soon' => 'vencem em breve',
        ],

        'operations' => [
            'title' => 'Operações do Dia',
            'departures_today' => 'Saídas Hoje',
            'returns_today' => 'Devoluções Hoje',
            'overdue_returns' => 'Atrasados',
        ],

        'alerts' => [
            'title' => 'Alertas',
            'overdue_vehicles' => 'veículos atrasados na devolução',
            'expiring_contracts' => 'contratos vencem em 7 dias',
            'expiring_insurance' => 'seguro vence em 5 dias',
            'overdue_invoices' => 'em faturas vencidas',
            'pending_fines' => 'multas pendentes',
            'pending_maintenance' => 'veículos com manutenção preventiva pendente',
        ],

        'reservations' => [
            'upcoming_title' => 'Reservas Próximos 7 Dias',
            'latest_title' => 'Últimas Reservas',
            'code' => 'Código',
            'client' => 'Cliente',
            'vehicle' => 'Veículo',
            'date' => 'Data',
            'status_confirmed' => 'Confirmada',
            'status_new' => 'Nova',
            'status_cancelled' => 'Cancelada',
        ],

        'financial' => [
            'title' => 'Resumo Financeiro',
            'cash_flow' => 'Fluxo do Mês',
            'revenue' => 'Receitas',
            'expenses' => 'Despesas',
            'balance' => 'Saldo',
            'top_overdue' => 'Maiores Vencidas',
            'upcoming_due' => 'Vencem em 7 Dias',
        ],

        'refresh' => [
            'auto_refresh' => 'Atualiza a cada :seconds s',
        ],
    ],
];
