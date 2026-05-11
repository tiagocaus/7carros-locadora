<?php

/**
 * Traduzioni del modulo Promozioni - Italiano (Italia)
 */

return [
    'title' => 'Promozioni',
    'title_singular' => 'Promozione',
    'new_title' => 'Nuova Promozione',
    'edit_title' => 'Modifica Promozione',

    // Sezioni
    'sections' => [
        'promotion_data' => 'Dati della Promozione',
    ],

    // Campi
    'fields' => [
        'branches' => 'Filiali',
        'code' => 'Codice',
        'name' => 'Nome della Promozione',
        'validity' => 'Validita',
        'minimum_days' => 'Tariffa Giornaliera Minima',
        'discount_type' => 'Tipo di Sconto',
        'discount_value' => 'Valore dello Sconto',
        'where_to_show' => 'Dove Visualizzare',
        'status' => 'Stato',
    ],

    // Suggerimenti
    'tooltips' => [
        'validity' => 'Data limite per l\'utilizzo della promozione. Lasciare vuoto per nessuna scadenza.',
        'minimum_days' => 'Numero minimo di giorni di noleggio affinché la promozione sia valida.',
        'where_to_show' => 'Seleziona dove sara disponibile questa promozione.',
    ],

    // Opzioni tipo
    'type_options' => [
        'fixed' => 'Fisso',
        'percentage' => 'Percentuale (%)',
    ],

    // Opzioni stato
    'status_options' => [
        'active' => 'Attivo',
        'disabled' => 'Disabilitato',
    ],

    // Opzioni di visualizzazione
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Sito web',
        'app' => 'App',
        'all' => 'Tutti',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca promozione...',
        'select_branches' => 'Seleziona le filiali...',
        'select' => 'Seleziona...',
        'code_example' => 'Es: PROMO2024',
        'name_example' => 'Es: Sconto Estate',
    ],

    // Badge
    'badges' => [
        'type_percentage' => 'Percentuale',
        'type_fixed' => 'Fisso',
        'status_active' => 'Attivo',
        'status_inactive' => 'Inattivo',
    ],

    // Tabella
    'table' => [
        'code' => 'Codice',
        'name' => 'Nome',
        'type' => 'Tipo',
        'value' => 'Valore',
        'min_days' => 'Giorni Min',
        'branches' => 'Filiali',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessuna promozione trovata',
        'no_name' => 'Senza nome',
        'all_branches' => 'Tutte',
        'days_suffix' => 'giorni',
        'load_error' => 'Errore durante il caricamento dei dati',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione del record',
        'this_record' => 'questa promozione',
        'not_found' => 'Promozione non trovata',
        'load_branches_error' => 'Errore durante il caricamento delle filiali',
        'load_branches_text' => 'Errore di caricamento',
        'no_branches' => 'Nessuna filiale registrata',
        'no_branches_text' => 'Nessuna filiale',
        'loading_branches' => 'Caricamento filiali...',
        'required_fields' => 'Compila i campi obbligatori:',
        'saving' => 'Salvataggio...',
        'save_error' => 'Errore durante il salvataggio',
        'created' => 'Promozione creata con successo!',
        'updated' => 'Promozione aggiornata con successo!',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'promozione',
];
