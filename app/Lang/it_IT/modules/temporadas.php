<?php

/**
 * Traduzioni del modulo Stagioni - Italiano (Italia)
 */

return [
    'title' => 'Stagioni',
    'title_singular' => 'Stagione',
    'new_title' => 'Nuova Stagione',
    'edit_title' => 'Modifica: :name',

    // Sezioni
    'sections' => [
        'season_data' => 'Dati della Stagione',
        'group_adjustments' => 'Aggiustamenti per Gruppo di Veicoli',
    ],

    // Campi
    'fields' => [
        'name' => 'Nome',
        'country' => 'Paese',
        'period_start' => 'Inizio Periodo',
        'period_end' => 'Fine Periodo',
        'active' => 'Stagione attiva',
    ],

    // Paesi
    'countries' => [
        'BR' => 'Brasile',
        'US' => 'Stati Uniti',
        'IT' => 'Italia',
        'ES' => 'Spagna',
        'PT' => 'Portogallo',
    ],

    // Mesi
    'months' => [
        '1' => 'Gennaio',
        '2' => 'Febbraio',
        '3' => 'Marzo',
        '4' => 'Aprile',
        '5' => 'Maggio',
        '6' => 'Giugno',
        '7' => 'Luglio',
        '8' => 'Agosto',
        '9' => 'Settembre',
        '10' => 'Ottobre',
        '11' => 'Novembre',
        '12' => 'Dicembre',
    ],

    // Badge
    'badges' => [
        'active' => 'Attiva',
        'inactive' => 'Inattiva',
    ],

    // Descrizioni
    'descriptions' => [
        'adjustments' => 'Definisci la percentuale di aggiustamento del prezzo per ogni gruppo. Es: 30 = +30%, -10 = -10%',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca stagione...',
        'name_example' => 'Es: Estate 2025, Natale...',
    ],

    // Modelli
    'templates' => [
        'title' => 'Modelli di Stagione',
        'activate_title' => 'Attiva Modello di Stagione',
        'filter_country' => 'Filtra per paese',
        'all_countries' => 'Tutti i paesi',
        'loading' => 'Caricamento modelli...',
        'load_error' => 'Errore durante il caricamento dei modelli.',
        'no_templates' => 'Nessun modello disponibile per questo paese.',
        'activate' => 'Attiva',
        'activating' => 'Attivazione...',
        'activate_error' => 'Errore durante l\'attivazione del modello',
    ],

    // Tabella
    'table' => [
        'name' => 'Nome',
        'country' => 'Paese',
        'period' => 'Periodo',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessuna stagione trovata',
        'no_name' => 'Senza nome',
        'load_error' => 'Errore durante il caricamento delle stagioni',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione della stagione',
        'this_record' => 'questa stagione',
        'load_season_error' => 'Errore durante il caricamento della stagione',
        'load_adjustments_error' => 'Errore durante il caricamento degli aggiustamenti.',
        'no_groups' => 'Nessun gruppo di veicoli registrato.',
        'loading_groups' => 'Caricamento gruppi...',
        'saving' => 'Salvataggio...',
        'save_error' => 'Errore durante il salvataggio della stagione',
        'request_error' => 'Errore durante l\'elaborazione della richiesta',
        'created' => 'Stagione creata con successo!',
        'updated' => 'Stagione aggiornata con successo!',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'stagione',

    // Pulsanti
    'buttons' => [
        'templates' => 'Modelli',
        'new' => 'Nuova',
    ],
];
