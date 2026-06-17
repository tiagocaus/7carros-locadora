<?php

/**
 * Traduzioni del modulo Checklists - Italiano (Italia)
 */

return [
    // Titolo
    'title' => 'Checklists',

    // Tabella
    'table' => [
        'code' => 'Codice',
        'model' => 'Modello',
        'vehicle' => 'Veicolo',
        'date' => 'Data',
        'type' => 'Tipo',
        'actions' => 'Azioni',
        'status' => 'Stato',
    ],

    // Tipi
    'types' => [
        'linked' => 'Collegato',
        'standalone' => 'Indipendente',
    ],

    // Stampa
    'print' => [
        'doc_title' => 'CHECKLIST DEL VEICOLO',
        'code' => 'Codice',
        'type' => 'Tipo',
        'date' => 'Data',
        'title_prefix' => 'Checklist',
        'landscape' => 'Orizzontale',
        'portrait' => 'Verticale',
        'plate' => 'Targa',
        'vehicle' => 'Veicolo',
        'renavam' => 'Renavam',
        'departure' => 'PARTENZA',
        'arrival' => 'ARRIVO',
        'questionnaire' => 'Questionario',
        'item' => 'Voce',
        'answer' => 'Risposta',
        'observations' => 'Osservazioni',
        'inspection_photos' => 'Ispezione (Foto)',
        'no_arrival_data' => 'Nessun dato di arrivo',
        'signature_departure' => 'Firma Partenza',
        'signature_arrival' => 'Firma Arrivo',
        'signature' => 'Firma',
    ],

    // Badge di risposta
    'answers' => [
        'matches' => 'Conforme',
        'not_matches' => 'Non conforme',
        'damaged' => 'Danneggiato',
        'na' => 'N/A',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Cerca...',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun checklist trovato',
        'load_error' => 'Errore nel caricamento dei dati',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione',
        'this_record' => 'questo checklist',
        'mobile_only' => 'Per completare la checklist, accedi a questo sistema dal browser di un cellulare o tablet.',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzando :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'checklist',

    // Checklist digitale
    'digital' => [
        'title' => 'Checklist digitale',
        'tab_info' => 'Info',
        'tab_questions' => 'Domande',
        'tab_inspection' => 'Ispezioni',
        'tab_signature' => 'Firma',
        'type' => 'Tipo',
        'type_standalone' => 'Indipendente',
        'type_linked' => 'Collegato',
        'moment' => 'Momento',
        'moment_departure' => 'Partenza',
        'moment_arrival' => 'Arrivo',
        'vehicle' => 'Veicolo',
        'contract_rental' => 'Noleggio / Contratto',
        'checklist_model' => 'Modello del checklist',
        'tank' => 'Serbatoio',
        'battery_charge' => 'Carica della Batteria',
        'odometer' => 'Contachilometri attuale',
        'observations' => 'Osservazioni',
        'observations_placeholder' => 'Inserire le osservazioni...',
        'advance' => 'Avanti',
        'save' => 'Salva',
        'clear' => 'Cancella',
        'close' => 'Chiudi',
        'back' => 'Indietro',
        'list' => 'Elenco',
        'new' => 'Nuovo checklist',
        'next_vehicle' => 'Fare checklist del prossimo veicolo',
        'saved_success' => 'Checklist Salvato!',
        'saved_message' => 'Il checklist è stato completato con successo.',
        'auto_saved' => 'Salvato automaticamente',
        'questionnaire' => 'Questionario',
        'information' => 'Informazioni',
        'select' => 'Seleziona...',
        'select_vehicle' => 'Seleziona il veicolo...',
        'select_link_first' => 'Seleziona prima il collegamento...',
        'search_code_client' => 'Cerca per codice o cliente...',
        'search_plate_model' => 'Cerca per targa o modello...',
        'select_model' => 'Seleziona il modello...',
        'departure_done' => 'Partenza effettuata',
        'arrival_done' => 'Arrivo effettuato',
        'status_pending' => 'In attesa',
        'status_done' => 'Completato',
        'legend_linked' => 'Collegato',
        'legend_standalone' => 'Indipendente',
        'continue' => 'Continua',
        'loading' => 'Caricamento...',
        'processing' => 'Elaborazione...',
        'creating' => 'Creazione checklist...',
        'saving_questions' => 'Salvataggio questionario...',
        'saving_checklist' => 'Salvataggio checklist...',
        'sending_photo' => 'Invio foto...',
        'deleting_photo' => 'Eliminazione foto...',
        'no_records' => 'Nessun checklist trovato',
        'err_select_type' => 'Seleziona il tipo',
        'err_select_moment' => 'Seleziona il momento',
        'err_select_link' => 'Seleziona un noleggio o contratto',
        'err_select_vehicle' => 'Seleziona un veicolo',
        'err_select_model' => 'Seleziona un modello di checklist',
        'err_select_tank' => 'Seleziona il livello del serbatoio',
        'err_fill_odometer' => 'Inserisci il contachilometri attuale',
        'err_answer_all' => 'Rispondi a tutte le domande (:count in sospeso)',
        'err_sign' => 'Disegna la firma prima di salvare',
        'err_min_photo' => 'Scatta almeno una foto dell\'ispezione',
    ],
];
