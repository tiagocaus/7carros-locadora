<?php

/**
 * Traduzioni del modulo Conti Bancari - Italiano
 */

return [
    'title' => 'Conti Bancari/Cassa',
    'title_singular' => 'Conto Bancario/Cassa',
    'new_title' => 'Nuovo Conto',
    'edit_title' => 'Modifica Conto',

    // Sezioni
    'sections' => [
        'account_data' => 'Dati del Conto',
        'bank_data' => 'Dati Bancari',
        'notes' => 'Note',
    ],

    // Campi
    'fields' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Stato',
        'bank' => 'Banca',
        'branch' => 'Filiale',
        'account_number' => 'Numero di Conto',
        'notes' => 'Note',
    ],

    // Opzioni tipo
    'type_options' => [
        'bank_account' => 'Conto Bancario',
        'cash' => 'Cassa',
    ],

    // Badges
    'badges' => [
        'type_bank' => 'Bancario',
        'type_cash' => 'Cassa',
        'status_active' => 'Attivo',
        'status_inactive' => 'Inattivo',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca conto...',
        'name_example' => 'Es: Cassa Principale, Banca Intesa',
        'bank_example' => 'Es: Banca Intesa, UniCredit',
        'branch_example' => 'Es: 1234-5',
        'account_example' => 'Es: 12345-6',
        'notes_example' => 'Informazioni aggiuntive sul conto...',
    ],

    // Tabella
    'table' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'bank' => 'Banca',
        'branch' => 'Filiale',
        'account' => 'Conto',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun conto trovato',
        'no_name' => 'Senza nome',
        'load_error' => 'Errore durante il caricamento dei conti',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione del conto',
        'this_record' => 'questo conto',
        'not_found' => 'Conto non trovato',
        'load_account_error' => 'Errore durante il caricamento dei dati del conto',
        'name_required' => 'Inserire il nome del conto',
        'saving' => 'Salvataggio in corso...',
        'save_error' => 'Errore durante il salvataggio del conto',
        'saved' => 'Conto salvato con successo',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'conto',
];
