<?php

/**
 * Traduzioni del modulo Documenti - Italiano (Italia)
 */

return [
    'title' => 'Modelli di Documento',
    'title_singular' => 'Documento',
    'new_title' => 'Nuovo Documento',
    'edit_title' => 'Modifica Documento',

    // Filtri per tipo
    'filters' => [
        'all' => 'Tutti',
        'both' => 'Contratto/Noleggio',
        'contract' => 'Contratto',
        'rental' => 'Noleggio',
        'fine' => 'Multa',
    ],

    // Tabella
    'table' => [
        'title' => 'Titolo',
        'type' => 'Tipo',
        'status' => 'Stato',
        'updated_at' => 'Aggiornato il',
        'actions' => 'Azioni',
    ],

    // Etichette
    'badges' => [
        'type_both' => 'Contratto/Noleggio',
        'type_contract' => 'Contratto',
        'type_rental' => 'Noleggio',
        'type_fine' => 'Multa',
        'status_active' => 'Attivo',
        'status_inactive' => 'Inattivo',
    ],

    // Campi del modulo
    'fields' => [
        'title' => 'Titolo',
        'type' => 'Tipo',
        'status' => 'Stato',
        'content' => 'Contenuto',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca documento...',
        'title_example' => 'Es: Contratto di Noleggio',
    ],

    // Pannello variabili
    'variables' => [
        'title' => 'Variabili Disponibili',
        'description' => 'Clicca per inserire nell\'editor',
        'no_variables' => 'Nessuna variabile disponibile',
        'load_error' => 'Errore nel caricamento delle variabili',
    ],

    // Descrizione
    'description' => 'Crea modelli di documenti con variabili compilate automaticamente',

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun documento trovato',
        'no_title' => 'Senza titolo',
        'load_error' => 'Errore nel caricamento dei documenti',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore nell\'eliminazione del documento',
        'this_record' => 'questo documento',
        'title_required' => 'Il titolo è obbligatorio',
        'saving' => 'Salvataggio...',
        'save_error' => 'Errore nel salvataggio del documento',
        'saved' => 'Documento salvato con successo',
        'imported' => 'Documento importato con successo!',
        'editor_error' => 'Errore nel caricamento dell\'editor. Ricarica la pagina.',
        'content_required' => 'Inserisci del contenuto per visualizzare l\'anteprima',
        'preview_error' => 'Errore nella generazione dell\'anteprima',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'documento',
];
