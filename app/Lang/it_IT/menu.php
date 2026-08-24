<?php

/**
 * Voci di menu e navigazione - Italiano (Italia)
 *
 * Contiene tutte le voci di menu, barra di navigazione,
 * sidebar e notifiche del sistema.
 */

return [
    // Menu principale
    'main' => [
        'dashboard' => 'Pannello',
        'home' => 'Home',
    ],

    // Top Bar - Selettore di sistemi
    'topbar' => [
        'rental' => 'Noleggio veicoli',
        'workshop' => 'Officina meccanica',
        'parts' => 'Ricambi auto',
        'inspection' => 'Ispezione veicolare',
        'resale' => 'Rivendita veicoli',
    ],

    // Menu Sistema
    'sistema' => [
        'title' => 'Sistema',
        'referral_program' => 'Programma referral',
        'feature_request' => 'Richiedi nuova funzionalità',
        'activity_logs' => 'Log delle attività',
        'grant_access' => 'Concedi accesso',
        'settings' => 'Impostazioni',
        'message_templates' => 'Modelli di messaggio',
        'changelog' => 'Changelog',
        'screen_recording' => 'Registra schermo',
        'logout' => 'Esci',
    ],

    // Menu Contratto/Noleggi
    'contratos_loc' => [
        'title' => 'Contratto/Noleggi',
        'new_quote' => 'Nuovo preventivo',
        'quotes' => 'Preventivi',
        'new_rental' => 'Nuovo noleggio',
        'rentals_reservations' => 'Noleggi/Prenotazioni',
        'new_contract' => 'Nuovo contratto',
        'contracts' => 'Contratti',
    ],

    // Menu Azienda
    'empresa' => [
        'title' => 'Azienda',
        'branches' => 'Sede e filiali',
        'clients' => 'Clienti',
        'messaging' => 'WhatsApp, SMS e SMTP',
        'employees' => 'Dipendenti',
        'documents' => 'Documenti',
        'fees_services' => 'Tariffe e servizi',
        'workshops' => 'Officine',
        'promotions' => 'Promozioni',
        'fines' => 'Multe',
        'fines_central' => 'Centrale Multe',
        'bank_accounts' => 'Conti bancari/cassa',
        'payment_methods' => 'Metodi di pagamento',
        'payment_gateways' => 'Gateway di pagamento',
        'suppliers' => 'Fornitori',
        'inventory' => 'Magazzino',
    ],

    // Menu Veicoli
    'veiculos_menu' => [
        'title' => 'Veicoli',
        'vehicles' => 'Veicoli',
        'groups' => 'Gruppi',
        'seasons' => 'Stagioni',
        'accessories' => 'Accessori e articoli',
        'maintenance' => 'Manutenzioni',
        'maintenance_plans' => 'Piani di manutenzione',
        'checklist' => 'Checklist',
        'checklist_templates' => 'Modelli checklist',
    ],

    // Menu Report
    'relatorios_menu' => [
        'title' => 'Report',
        // KPI
        'kpis' => 'KPI / Indicatori',
        'kpi_occupancy_rate' => 'Tasso di occupazione della flotta',
        'kpi_revpar' => 'RevPAR (Ricavo per veicolo/giorno)',
        'kpi_adr' => 'Tariffa media giornaliera (ADR)',
        'kpi_gross_margin' => 'Margine lordo giornaliero',
        'kpi_revenue_vehicle' => 'Ricavo per veicolo',
        'kpi_additional_revenue' => '% Ricavi aggiuntivi',
        'kpi_avg_rental_time' => 'Durata media del noleggio',
        'kpi_roi_vehicle' => 'ROI per veicolo',
        // Finanziario
        'financial' => 'Finanziario',
        'fin_detailed' => 'Movimenti Finanziari',
        'fin_billing' => 'Fatturazione',
        'fin_income_statement' => 'Conto economico',
        'fin_cash_result' => 'Risultato gestionale per cassa',
        'fin_cashbook' => 'Libro di cassa',
        'fin_bank_accounts' => 'Conti bancari/Casse',
        'fin_chart_accounts' => 'Piano dei conti',

        'fin_revenue_projection' => 'Proiezione dei ricavi',
        'fin_profitability' => 'Analisi di redditività',
        'fin_delinquency' => 'Insolvenza generale',
        'fin_fees_charged' => 'Tariffe e servizi addebitati',
        // Veicolare
        'vehicle' => 'Veicolare',
        'veh_maintenance' => 'Manutenzioni veicoli',
        'veh_profit' => 'Profitto per veicolo',
        'veh_expenses' => 'Spese veicoli',
        'veh_client' => 'Veicolo/cliente',
        'veh_licensing' => 'Revisione',
        'veh_availability' => 'Disponibilità',
        'veh_group_occupancy' => 'Tasso di occupazione per gruppo',

        'veh_depreciation' => 'Ammortamento della flotta',
        'veh_avg_idle_time' => 'Tempo medio di inattività',
        'veh_avg_mileage' => 'Chilometraggio medio',

        'veh_total_cost' => 'Costo totale di proprietà',
        // Clienti
        'clients' => 'Clienti',
        'cli_contracts_rentals' => 'Contratti/Noleggi',
        'cli_birthdays' => 'Compleanni',
        'cli_expired_license' => 'Patenti di guida scadute',
        'cli_top_clients' => 'Top clienti (classifica)',

        'cli_rental_frequency' => 'Frequenza di noleggio',
        'cli_relationship_time' => 'Durata del rapporto',
        'cli_incident_history' => 'Storico degli incidenti',
        'cli_inactive' => 'Clienti inattivi',
        // Contratti/Noleggi
        'contracts_rentals' => 'Contratti/Noleggi',
        'cr_general' => 'Panoramica',
        'cr_by_period' => 'Per periodo',
        'cr_by_payment' => 'Per metodo di pagamento',

        'cr_extensions' => 'Estensioni di contratto',
        'cr_vehicle_swap' => 'Sostituzioni di veicolo',
        // Operativo
        'operational' => 'Operativo',
        'op_checklists' => 'Checklist effettuati',
        'op_damages' => 'Sinistri',
        'op_traffic_fines' => 'Multe stradali',
        'op_early_returns' => 'Restituzioni anticipate',
        'op_late_returns' => 'Restituzioni in ritardo',
        'op_cancelled_reservations' => 'Prenotazioni annullate',
        'op_turnaround' => 'Turnaround (tempo di riconsegna)',
        'op_fuel' => 'Carburante',
        // Fatture
        'invoices' => 'Fatture',
        'inv_due_upcoming' => 'Scadute/in scadenza',
        'inv_by_vehicle' => 'Per veicolo',
        'inv_payable_receivable' => 'Dare/avere',
        // Commerciale
        'commercial' => 'Commerciale',
        'com_conversion_rate' => 'Tasso di conversione',
        'com_rental_origin' => 'Origine dei noleggi',
        'com_promotions_used' => 'Promozioni utilizzate',
        'com_discounts_given' => 'Sconti concessi',
        'com_season_analysis' => 'Analisi stagionale',
        // Fornitori
        'suppliers' => 'Fornitori',
        'sup_suppliers' => 'Acquisti e Pagamenti',
        'sup_investor' => 'Fornitore investitore',
        // Dipendenti
        'employees' => 'Dipendenti',
        'emp_sales' => 'Vendite',
        'emp_commissions' => 'Provvigioni',
        'emp_productivity' => 'Produttività',

        'emp_goals' => 'Obiettivi vs realizzato',
        // Comparativi
        'comparisons' => 'Comparativi',
        'comp_monthly_annual' => 'Confronto mensile/annuale',
        'comp_between_branches' => 'Confronto tra filiali',
        'comp_vehicle_ranking' => 'Classifica veicoli',
        'comp_trends' => 'Analisi delle tendenze',
    ],

    // Menu Finanziario
    'financeiro_menu' => [
        'title' => 'Finanziario',
        'entries' => 'Registrazioni',
        'new_entry' => 'Nuova registrazione',
        'promissory_notes' => 'Cambiali',
        'investor_commissions' => 'Provvigioni investitori',
    ],

    // Menu Sito Web
    'website' => [
        'title' => 'Sito Web',
        'activate' => 'Attivare',
        'settings' => 'Impostazioni',
        'appearance' => 'Aspetto',
        'contents' => 'Contenuti',
        'banners' => 'Banner',
        'seo' => 'SEO',
        'integrations' => 'Integrazioni',
        'publish' => 'Pubblica',
    ],

    // Notifiche
    'notifications' => [
        'title' => 'Notifiche',
        'maintenance' => 'Manutenzioni',
        'tasks' => 'Attività',
        'overdue_invoices' => 'Fatture scadute',
        'licensing' => 'Revisione',
        'expired_license' => 'Patenti di guida scadute',
        'problems' => 'Problemi',
        'all_notifications' => 'Tutte le notifiche',
    ],

    // Barra di navigazione secondaria (scorciatoie)
    'secondary_nav' => [
        'sidebar_mode' => 'Modalità Sidebar',
        'rentals' => 'Noleggi/Prenotazioni',
        'contracts' => 'Contratti',
        'vehicles' => 'Veicoli',
        'clients' => 'Clienti',
        'employees' => 'Dipendenti',
        'find' => 'Cerca',
        'schedule' => 'Agenda',
        'branches' => 'Sede e Filiali',
        'refresh' => 'Aggiorna',
    ],

    // Sidebar
    'sidebar' => [
        'home' => 'Home',
        'quick_search' => 'Ricerca rapida',
        'vehicle' => 'Veicolo',
        'select' => 'Seleziona',
    ],

    // Tooltip e titoli
    'tooltips' => [
        'select_language' => 'Seleziona lingua',
        'notifications' => 'Notifiche',
        'user_profile' => 'Profilo utente',
        'logout' => 'Esci',
        'refresh_page' => 'Aggiorna pagina',
    ],

    // Menu utente
    'user' => [
        'profile' => 'Il mio profilo',
        'settings' => 'Impostazioni',
        'password' => 'Cambia password',
        'notifications' => 'Notifiche',
        'language' => 'Lingua',
        'logout' => 'Esci',
    ],

    // Azioni comuni
    'actions' => [
        'new' => 'Nuovo',
        'add' => 'Aggiungi',
        'edit' => 'Modifica',
        'view' => 'Visualizza',
        'delete' => 'Elimina',
        'export' => 'Esporta',
        'import' => 'Importa',
        'print' => 'Stampa',
        'filter' => 'Filtra',
        'search' => 'Cerca',
    ],

    // Breadcrumbs
    'breadcrumbs' => [
        'home' => 'Home',
        'list' => 'Elenco',
        'new' => 'Nuovo',
        'edit' => 'Modifica',
        'view' => 'Visualizza',
    ],

    // Modulo Clienti (mantenuto per compatibilità)
    'clientes' => [
        'title' => 'Clienti',
        'list' => 'Elenco clienti',
        'new' => 'Nuovo cliente',
        'edit' => 'Modifica cliente',
        'view' => 'Visualizza cliente',
        'import' => 'Importa clienti',
        'export' => 'Esporta clienti',
    ],

    // Modulo Veicoli (mantenuto per compatibilità)
    'veiculos' => [
        'title' => 'Veicoli',
        'list' => 'Elenco veicoli',
        'new' => 'Nuovo veicolo',
        'edit' => 'Modifica veicolo',
        'view' => 'Visualizza veicolo',
        'categories' => 'Categorie',
        'maintenance' => 'Manutenzioni',
        'availability' => 'Disponibilità',
    ],

    // Modulo Noleggi (mantenuto per compatibilità)
    'locacoes' => [
        'title' => 'Noleggi',
        'list' => 'Elenco noleggi',
        'new' => 'Nuovo noleggio',
        'edit' => 'Modifica noleggio',
        'view' => 'Visualizza noleggio',
        'calendar' => 'Calendario',
        'checklist' => 'Checklist',
        'return' => 'Restituzione',
    ],

    // Modulo Contratti (mantenuto per compatibilità)
    'contratos' => [
        'title' => 'Contratti',
        'list' => 'Elenco contratti',
        'new' => 'Nuovo contratto',
        'edit' => 'Modifica contratto',
        'view' => 'Visualizza contratto',
        'templates' => 'Modelli di contratto',
    ],

    // Modulo Finanziario (mantenuto per compatibilità)
    'financeiro' => [
        'title' => 'Finanziario',
        'dashboard' => 'Pannello finanziario',
        'receivables' => 'Crediti',
        'payables' => 'Debiti',
        'invoices' => 'Fatture',
        'payments' => 'Pagamenti',
        'cashflow' => 'Flusso di cassa',
        'reports' => 'Report',
    ],

    // Modulo Dipendenti (mantenuto per compatibilità)
    'funcionarios' => [
        'title' => 'Dipendenti',
        'list' => 'Elenco dipendenti',
        'new' => 'Nuovo dipendente',
        'edit' => 'Modifica dipendente',
        'roles' => 'Ruoli e permessi',
    ],

    // Modulo Agenda (mantenuto per compatibilità)
    'agenda' => [
        'title' => 'Agenda',
        'calendar' => 'Calendario',
        'events' => 'Eventi',
        'reminders' => 'Promemoria',
    ],

    // Modulo Report (mantenuto per compatibilità)
    'relatorios' => [
        'title' => 'Report',
        'rentals' => 'Report noleggi',
        'clients' => 'Report clienti',
        'vehicles' => 'Report veicoli',
        'financial' => 'Report finanziario',
        'custom' => 'Report personalizzato',
    ],

    // Modulo Impostazioni (mantenuto per compatibilità)
    'configuracoes' => [
        'title' => 'Impostazioni',
        'general' => 'Impostazioni generali',
        'company' => 'Dati aziendali',
        'branches' => 'Filiali',
        'payment_methods' => 'Metodi di pagamento',
        'notifications' => 'Notifiche',
        'integrations' => 'Integrazioni',
        'templates' => 'Modelli di messaggio',
        'documents' => 'Modelli di documento',
        'backup' => 'Backup',
        'logs' => 'Log di sistema',
    ],
];
