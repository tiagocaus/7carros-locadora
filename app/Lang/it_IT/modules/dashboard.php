<?php

/**
 * Traduzioni del modulo Dashboard - Italiano
 */

return [
    'title' => 'Pannello di Controllo',

    // KPI Cards
    'kpi' => [
        'total_vehicles' => 'Totale Veicoli',
        'rented_today' => 'Noleggiati Oggi',
        'occupancy_rate' => 'Tasso di Occupazione',
        'expected_revenue_today' => 'Entrate Prev. Oggi',
    ],

    // Barra di disponibilità
    'availability' => [
        'title' => 'Disponibilità Veicoli',
        'total' => 'Totale',
        'available' => 'Disponibili',
        'rented' => 'Noleggiati',
        'reserved' => 'Prenotati',
        'workshop' => 'Officina',
    ],

    // Sub-tabs
    'tabs' => [
        'quick_search' => 'Ricerca rapida',
        'reservations' => 'Prenotazioni',
        'rented' => 'Noleggiati',
        'available' => 'Disponibili',
        'pending_arrival' => 'Arrivo in sospeso',
        'upcoming_returns' => 'Prossime Restituzioni',
    ],

    // Placeholders
    'placeholders' => [
        'tab_content' => 'Contenuto della scheda ":tab" qui.',
        'tab_content_will_appear' => 'Contenuto della scheda ":tab" apparirà qui.',
    ],

    'subtabs' => [
        'reservations_empty' => 'Nessuna prenotazione trovata.',
        'rented_empty' => 'Nessun noleggio o contratto aperto trovato.',
        'available_empty' => 'Nessun veicolo disponibile trovato.',
        'pending_arrival_empty' => 'Nessun arrivo in sospeso trovato.',
        'upcoming_returns_empty' => 'Nessuna restituzione prossima trovata.',
        'departure' => 'Uscita',
        'expected' => 'Prevista',
        'loading' => 'Caricamento :title...',
        'load_error' => 'Non e stato possibile caricare i dati di questa scheda.',
        'updated' => 'Aggiornato :time',
        'plate' => 'Targa',
        'vehicle' => 'Veicolo',
        'group' => 'Gruppo',
        'branch' => 'Filiale',
        'odometer' => 'Contachilometri',
        'actions' => 'Azioni',
        'code' => 'Codice',
        'type' => 'Tipo',
        'client' => 'Cliente',
        'deadline' => 'Scadenza',
        'open' => 'Apri',
        'rental' => 'Noleggio',
        'contract' => 'Contratto',
        'today' => 'Oggi',
        'tomorrow' => 'Domani',
        'pending_pickup' => 'Ritiro in sospeso',
        'available_badge' => 'Disponibile',
        'no_vehicle' => 'Senza veicolo',
        'contract_duration_today' => 'Iniziato oggi',
        'contract_duration_days' => ':count giorno di contratto|:count giorni di contratto',
        'overdue_minutes' => ':count min di ritardo|:count min di ritardo',
        'overdue_hours' => ':count h di ritardo|:count h di ritardo',
        'overdue_days' => ':count giorno di ritardo|:count giorni di ritardo',
    ],

    // Dashboard v2 (Cockpit)
    'v2' => [
        'title' => 'Pannello di Controllo',

        'kpi' => [
            'rented_now' => 'Noleggiati Ora',
            'utilization_rate' => 'Tasso di Utilizzo',
            'average_daily_rate' => 'Tariffa Giornaliera Media (ADR)',
            'revenue_month' => 'Entrate del Mese',
            'overdue_amount' => 'Crediti Scaduti',
            'active_contracts' => 'Contratti Attivi',
            'maintenance_cost' => 'Costo Manut. %',
            'invoices' => 'fatture',
            'expiring_soon' => 'in scadenza',
        ],

        'operations' => [
            'title' => 'Operazioni del Giorno',
            'departures_today' => 'Partenze Oggi',
            'returns_today' => 'Restituzioni Oggi',
            'overdue_returns' => 'In Ritardo',
        ],

        'alerts' => [
            'title' => 'Avvisi',
            'overdue_vehicles' => 'veicoli in ritardo nella restituzione',
            'expiring_contracts' => 'contratti scadono tra 7 giorni',
            'expiring_insurance' => 'assicurazione scade tra 5 giorni',
            'overdue_invoices' => 'in fatture scadute',
            'pending_fines' => 'multe in sospeso',
            'pending_maintenance' => 'veicoli con manutenzione preventiva in sospeso',
        ],

        'reservations' => [
            'upcoming_title' => 'Prenotazioni Prossimi 7 Giorni',
            'latest_title' => 'Ultime Prenotazioni',
            'code' => 'Codice',
            'client' => 'Cliente',
            'vehicle' => 'Veicolo',
            'date' => 'Data',
            'status_confirmed' => 'Confermata',
            'status_new' => 'Nuova',
            'status_cancelled' => 'Annullata',
        ],

        'financial' => [
            'title' => 'Riepilogo Finanziario',
            'cash_flow' => 'Flusso del Mese',
            'revenue' => 'Entrate',
            'expenses' => 'Spese',
            'balance' => 'Saldo',
            'top_overdue' => 'Maggiori Scadute',
            'upcoming_due' => 'Scadono tra 7 Giorni',
        ],

        'refresh' => [
            'auto_refresh' => 'Aggiorna ogni :seconds s',
        ],
    ],
];
