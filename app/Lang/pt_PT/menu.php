<?php

/**
 * Itens de menu e navegação - Português (Portugal)
 *
 * Contém todos os itens de menu, barra de navegação,
 * sidebar e notificações do sistema.
 */

return [
    // Menu principal
    'main' => [
        'dashboard' => 'Painel',
        'home' => 'Início',
    ],

    // Top Bar - Seletor de sistemas
    'topbar' => [
        'rental' => 'Aluguer de veículo',
        'workshop' => 'Oficina mecânica',
        'parts' => 'Peças auto',
        'inspection' => 'Vistoria veicular',
        'resale' => 'Revenda de veículos',
    ],

    // Menu Sistema
    'sistema' => [
        'title' => 'Sistema',
        'referral_program' => 'Programa de indicação',
        'feature_request' => 'Pedir novo recurso',
        'activity_logs' => 'Registos de atividades',
        'grant_access' => 'Conceder acesso',
        'settings' => 'Definições',
        'message_templates' => 'Templates de Mensagem',
        'changelog' => 'Changelog',
        'screen_recording' => 'Gravar ecrã',
        'logout' => 'Sair',
    ],

    // Menu Contrato/Alugueres
    'contratos_loc' => [
        'title' => 'Contrato/Alugueres',
        'new_quote' => 'Novo orçamento',
        'quotes' => 'Orçamentos',
        'new_rental' => 'Novo Aluguer',
        'rentals_reservations' => 'Alugueres/Reservas',
        'new_contract' => 'Novo contrato',
        'contracts' => 'Contratos',
    ],

    // Menu Empresa
    'empresa' => [
        'title' => 'Empresa',
        'branches' => 'Matriz e filiais',
        'clients' => 'Clientes',
        'messaging' => 'WhatsApp, SMS e SMTP',
        'employees' => 'Funcionários',
        'documents' => 'Documentos',
        'fees_services' => 'Taxas e serviços',
        'workshops' => 'Oficinas',
        'promotions' => 'Promoções',
        'fines' => 'Multas',
        'fines_central' => 'Central de Multas',
        'bank_accounts' => 'Contas bancárias/caixa',
        'payment_methods' => 'Formas de pagamento',
        'payment_gateways' => 'Gateways de pagamento',
        'suppliers' => 'Fornecedores',
        'inventory' => 'Stock',
    ],

    // Menu Veículos
    'veiculos_menu' => [
        'title' => 'Veículos',
        'vehicles' => 'Veículos',
        'groups' => 'Grupos',
        'seasons' => 'Temporadas',
        'accessories' => 'Acessórios e itens',
        'maintenance' => 'Manutenções',
        'maintenance_plans' => 'Plano de manutenções',
        'checklist' => 'Checklist',
        'checklist_templates' => 'Checklist modelos',
    ],

    // Menu Relatórios
    'relatorios_menu' => [
        'title' => 'Relatórios',
        // KPIs
        'kpis' => 'KPIs / Indicadores',
        'kpi_occupancy_rate' => 'Taxa de ocupação da frota',
        'kpi_revpar' => 'RevPAR (Receita por veículo/dia)',
        'kpi_adr' => 'Diária média (ADR)',
        'kpi_gross_margin' => 'Margem bruta por dia',
        'kpi_revenue_vehicle' => 'Receita por veículo',
        'kpi_additional_revenue' => '% Receitas adicionais',
        'kpi_avg_rental_time' => 'Tempo médio de aluguer',
        'kpi_roi_vehicle' => 'ROI por veículo',
        // Financeiro
        'financial' => 'Financeiro',
        'fin_detailed' => 'Movimentações Financeiras',
        'fin_billing' => 'Faturação',
        'fin_income_statement' => 'Demonstrativos de resultados',
        'fin_cash_result' => 'Resultado de gestão por caixa',
        'fin_cashbook' => 'Livro de caixa',
        'fin_bank_accounts' => 'Contas bancárias/Caixas',
        'fin_chart_accounts' => 'Plano de contas',

        'fin_revenue_projection' => 'Projeção de receitas',
        'fin_profitability' => 'Análise de rentabilidade',
        'fin_delinquency' => 'Incumprimento geral',
        'fin_fees_charged' => 'Taxas e serviços cobrados',
        // Veicular
        'vehicle' => 'Veicular',
        'veh_maintenance' => 'Manutenções veicular',
        'veh_profit' => 'Lucro por veículo',
        'veh_expenses' => 'Despesas veicular',
        'veh_client' => 'Veículo/cliente',
        'veh_licensing' => 'Licenciamento',
        'veh_availability' => 'Disponibilidade',
        'veh_group_occupancy' => 'Taxa de ocupação por grupo',

        'veh_depreciation' => 'Depreciação de frota',
        'veh_avg_idle_time' => 'Tempo médio parado',
        'veh_avg_mileage' => 'Quilometragem média',

        'veh_total_cost' => 'Custo total de propriedade',
        // Clientes
        'clients' => 'Clientes',
        'cli_contracts_rentals' => 'Contrato/alugueres',
        'cli_birthdays' => 'Aniversariantes',
        'cli_expired_license' => 'Cartas de condução expiradas',
        'cli_top_clients' => 'Top clientes (ranking)',

        'cli_rental_frequency' => 'Frequência de alugueres',
        'cli_relationship_time' => 'Tempo de relacionamento',
        'cli_incident_history' => 'Histórico de ocorrências',
        'cli_inactive' => 'Clientes inativos',
        // Contratos/Alugueres
        'contracts_rentals' => 'Contratos/Alugueres',
        'cr_general' => 'Visão Geral',
        'cr_by_period' => 'Por período',
        'cr_by_payment' => 'Por forma de pagamento',

        'cr_extensions' => 'Extensões de contrato',
        'cr_vehicle_swap' => 'Trocas de veículo',
        // Operacional
        'operational' => 'Operacional',
        'op_checklists' => 'Checklists realizados',
        'op_damages' => 'Sinistros',
        'op_traffic_fines' => 'Multas de trânsito',
        'op_early_returns' => 'Devoluções antecipadas',
        'op_late_returns' => 'Devoluções atrasadas',
        'op_cancelled_reservations' => 'Reservas canceladas',
        'op_turnaround' => 'Turnaround (tempo de retorno)',
        'op_fuel' => 'Combustível',
        // Faturas
        'invoices' => 'Faturas',
        'inv_due_upcoming' => 'Vencidas/a vencer',
        'inv_by_vehicle' => 'Por veículo',
        'inv_payable_receivable' => 'Pagar/receber',
        // Comercial
        'commercial' => 'Comercial',
        'com_conversion_rate' => 'Taxa de conversão',
        'com_rental_origin' => 'Origem dos alugueres',
        'com_promotions_used' => 'Promoções utilizadas',
        'com_discounts_given' => 'Descontos concedidos',
        'com_season_analysis' => 'Análise de temporada',
        // Fornecedores
        'suppliers' => 'Fornecedores',
        'sup_suppliers' => 'Compras e Pagamentos',
        'sup_investor' => 'Fornecedor investidor',
        // Funcionários
        'employees' => 'Funcionários',
        'emp_sales' => 'Vendas',
        'emp_commissions' => 'Comissões',
        'emp_productivity' => 'Produtividade',

        'emp_goals' => 'Metas vs realizado',
        // Comparativos
        'comparisons' => 'Comparativos',
        'comp_monthly_annual' => 'Comparativo mensal/anual',
        'comp_between_branches' => 'Comparativo entre filiais',
        'comp_vehicle_ranking' => 'Ranking de veículos',
        'comp_trends' => 'Análise de tendências',
    ],

    // Menu Financeiro
    'financeiro_menu' => [
        'title' => 'Financeiro',
        'entries' => 'Lançamentos',
        'new_entry' => 'Novo lançamento',
        'promissory_notes' => 'Promissórias',
        'investor_commissions' => 'Comissões Investidores',
    ],

    // Menu WebSite
    'website' => [
        'title' => 'WebSite',
        'activate' => 'Ativar',
        'settings' => 'Definições',
        'appearance' => 'Aparência',
        'contents' => 'Conteúdos',
        'banners' => 'Banners',
        'seo' => 'SEO',
        'integrations' => 'Integrações',
        'publish' => 'Publicar',
    ],

    // Notificações
    'notifications' => [
        'title' => 'Notificações',
        'maintenance' => 'Manutenções',
        'tasks' => 'Tarefas',
        'overdue_invoices' => 'Faturas vencidas',
        'licensing' => 'Licenciamento',
        'expired_license' => 'Cartas de condução expiradas',
        'problems' => 'Problemas',
        'all_notifications' => 'Todas as notificações',
    ],

    // Barra de navegação secundária (atalhos)
    'secondary_nav' => [
        'sidebar_mode' => 'Modo Sidebar',
        'rentals' => 'Alugueres/Reservas',
        'contracts' => 'Contratos',
        'vehicles' => 'Veículos',
        'clients' => 'Clientes',
        'employees' => 'Funcionários',
        'find' => 'Localizar',
        'schedule' => 'Agenda',
        'branches' => 'Matriz e Filiais',
        'refresh' => 'Atualizar',
    ],

    // Sidebar
    'sidebar' => [
        'home' => 'Início',
        'quick_search' => 'Pesquisa rápida',
        'vehicle' => 'Veículo',
        'select' => 'Selecione',
    ],

    // Tooltips e títulos
    'tooltips' => [
        'select_language' => 'Selecionar Idioma',
        'notifications' => 'Notificações',
        'user_profile' => 'Perfil do Utilizador',
        'logout' => 'Sair',
        'refresh_page' => 'Atualizar página',
    ],

    // Menu do utilizador
    'user' => [
        'profile' => 'O Meu Perfil',
        'settings' => 'Definições',
        'password' => 'Alterar Palavra-passe',
        'notifications' => 'Notificações',
        'language' => 'Idioma',
        'logout' => 'Sair',
    ],

    // Ações comuns
    'actions' => [
        'new' => 'Novo',
        'add' => 'Adicionar',
        'edit' => 'Editar',
        'view' => 'Visualizar',
        'delete' => 'Eliminar',
        'export' => 'Exportar',
        'import' => 'Importar',
        'print' => 'Imprimir',
        'filter' => 'Filtrar',
        'search' => 'Pesquisar',
    ],

    // Breadcrumbs
    'breadcrumbs' => [
        'home' => 'Início',
        'list' => 'Lista',
        'new' => 'Novo',
        'edit' => 'Editar',
        'view' => 'Visualizar',
    ],

    // Módulo Clientes (mantido para compatibilidade)
    'clientes' => [
        'title' => 'Clientes',
        'list' => 'Lista de Clientes',
        'new' => 'Novo Cliente',
        'edit' => 'Editar Cliente',
        'view' => 'Visualizar Cliente',
        'import' => 'Importar Clientes',
        'export' => 'Exportar Clientes',
    ],

    // Módulo Veículos (mantido para compatibilidade)
    'veiculos' => [
        'title' => 'Veículos',
        'list' => 'Lista de Veículos',
        'new' => 'Novo Veículo',
        'edit' => 'Editar Veículo',
        'view' => 'Visualizar Veículo',
        'categories' => 'Categorias',
        'maintenance' => 'Manutenções',
        'availability' => 'Disponibilidade',
    ],

    // Módulo Alugueres (mantido para compatibilidade)
    'locacoes' => [
        'title' => 'Alugueres',
        'list' => 'Lista de Alugueres',
        'new' => 'Novo Aluguer',
        'edit' => 'Editar Aluguer',
        'view' => 'Visualizar Aluguer',
        'calendar' => 'Calendário',
        'checklist' => 'Checklist',
        'return' => 'Devolução',
    ],

    // Módulo Contratos (mantido para compatibilidade)
    'contratos' => [
        'title' => 'Contratos',
        'list' => 'Lista de Contratos',
        'new' => 'Novo Contrato',
        'edit' => 'Editar Contrato',
        'view' => 'Visualizar Contrato',
        'templates' => 'Modelos de Contrato',
    ],

    // Módulo Financeiro (mantido para compatibilidade)
    'financeiro' => [
        'title' => 'Financeiro',
        'dashboard' => 'Painel Financeiro',
        'receivables' => 'Contas a Receber',
        'payables' => 'Contas a Pagar',
        'invoices' => 'Faturas',
        'payments' => 'Pagamentos',
        'cashflow' => 'Fluxo de Caixa',
        'reports' => 'Relatórios',
    ],

    // Módulo Funcionários (mantido para compatibilidade)
    'funcionarios' => [
        'title' => 'Funcionários',
        'list' => 'Lista de Funcionários',
        'new' => 'Novo Funcionário',
        'edit' => 'Editar Funcionário',
        'roles' => 'Cargos e Permissões',
    ],

    // Módulo Agenda (mantido para compatibilidade)
    'agenda' => [
        'title' => 'Agenda',
        'calendar' => 'Calendário',
        'events' => 'Eventos',
        'reminders' => 'Lembretes',
    ],

    // Módulo Relatórios (mantido para compatibilidade)
    'relatorios' => [
        'title' => 'Relatórios',
        'rentals' => 'Relatório de Alugueres',
        'clients' => 'Relatório de Clientes',
        'vehicles' => 'Relatório de Veículos',
        'financial' => 'Relatório Financeiro',
        'custom' => 'Relatório Personalizado',
    ],

    // Módulo Definições (mantido para compatibilidade)
    'configuracoes' => [
        'title' => 'Definições',
        'general' => 'Definições Gerais',
        'company' => 'Dados da Empresa',
        'branches' => 'Filiais',
        'payment_methods' => 'Formas de Pagamento',
        'notifications' => 'Notificações',
        'integrations' => 'Integrações',
        'templates' => 'Templates de Mensagem',
        'documents' => 'Modelos de Documento',
        'backup' => 'Cópia de segurança',
        'logs' => 'Registos do Sistema',
    ],
];
