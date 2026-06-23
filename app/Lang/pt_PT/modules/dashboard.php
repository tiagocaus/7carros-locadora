<?php

/**
 * Traduções do módulo Dashboard - Português (Portugal)
 */

return [
    'title' => 'Painel de Controlo',

    // KPI Cards
    'kpi' => [
        'total_vehicles' => 'Total de Veículos',
        'rented_today' => 'Alugados Hoje',
        'occupancy_rate' => 'Taxa de Ocupação',
        'expected_revenue_today' => 'Receita Prev. Hoje',
    ],

    // Barra de disponibilidade
    'availability' => [
        'title' => 'Disponibilidade de Veículos',
        'total' => 'Total',
        'available' => 'Disponíveis',
        'rented' => 'Alugados',
        'reserved' => 'Reservados',
        'workshop' => 'Oficina',
    ],

    'operations' => [
        'reservations_pending' => 'Reservas/Pendentes',
        'reserved' => 'Reservados',
        'pending' => 'Pendentes',
    ],

    // Sub-tabs
    'tabs' => [
        'quick_search' => 'Pesquisa rápida',
        'reservations' => 'Reservas',
        'rented' => 'Alugados',
        'available' => 'Disponíveis',
        'pending_arrival' => 'Chegada pendente',
        'upcoming_returns' => 'Próximas Devoluções',
    ],

    // Placeholders
    'placeholders' => [
        'tab_content' => 'Conteúdo do separador ":tab" aqui.',
        'tab_content_will_appear' => 'Conteúdo do separador ":tab" aparecerá aqui.',
    ],

    'subtabs' => [
        'reservations_empty' => 'Nenhuma reserva encontrada.',
        'rented_empty' => 'Nenhuma locação ou contrato aberto encontrado.',
        'available_empty' => 'Nenhum veículo disponível encontrado.',
        'pending_arrival_empty' => 'Nenhuma chegada pendente encontrada.',
        'upcoming_returns_empty' => 'Nenhuma devolução próxima encontrada.',
        'departure' => 'Saída',
        'expected' => 'Prevista',
        'loading' => 'A carregar :title...',
        'load_error' => 'Não foi possível carregar os dados deste separador.',
        'updated' => 'Atualizado :time',
        'plate' => 'Matrícula',
        'vehicle' => 'Veículo',
        'group' => 'Grupo',
        'branch' => 'Filial',
        'odometer' => 'Odómetro',
        'actions' => 'Ações',
        'code' => 'Código',
        'type' => 'Tipo',
        'client' => 'Cliente',
        'deadline' => 'Prazo',
        'open' => 'Abrir',
        'rental' => 'Locação',
        'contract' => 'Contrato',
        'today' => 'Hoje',
        'tomorrow' => 'Amanhã',
        'pending_pickup' => 'Retirada pendente',
        'available_badge' => 'Disponível',
        'no_vehicle' => 'Sem veículo',
        'contract_duration_today' => 'Iniciado hoje',
        'contract_duration_days' => ':count dia de contrato|:count dias de contrato',
        'overdue_minutes' => ':countmin atraso|:countmin atraso',
        'overdue_hours' => ':counth atraso|:counth atraso',
        'overdue_days' => ':count dia atraso|:count dias atraso',
    ],

    // Dashboard v2 (Cockpit)
    'v2' => [
        'title' => 'Painel de Controlo',

        'kpi' => [
            'rented_now' => 'Alugados Agora',
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
