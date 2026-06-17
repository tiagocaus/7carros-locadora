<?php

/**
 * Traduzioni del modulo Forme di Pagamento - Italiano (Italia)
 */

return [
    // Titoli
    'title' => 'Forme di Pagamento',
    'title_singular' => 'Forma di Pagamento',
    'new_title' => 'Nuova Forma di Pagamento',
    'edit_title' => 'Modifica Forma di Pagamento',

    // Sezioni
    'sections' => [
        'payment_data' => 'Dati della Forma di Pagamento',
        'penalty_interest' => 'Mora e Interessi per Ritardo',
        'billing_fees' => 'Commissioni di Addebito',
        'billing_fees_desc' => 'Configura le commissioni che verranno detratte/aggiunte al valore. Lascia 0,00 per disattivare.',
        'early_discount' => 'Sconto per Pagamento Anticipato',
        'early_discount_desc' => 'Configura uno sconto per i pagamenti effettuati prima della scadenza. Lascia i valori a zero per disattivare.',
    ],

    // Campi
    'fields' => [
        'name' => 'Nome',
        'branches' => 'Filiali',
        'branches_hint' => 'Seleziona in quali aziende questa forma di pagamento sarà disponibile.',
        'where_to_show' => 'Dove Mostrare',
        'where_to_show_hint' => 'Seleziona dove questa forma di pagamento sarà disponibile.',
        'post_as_paid' => 'Registra come pagato',
        'payment_gateways' => 'Gateway di Pagamento',
        'payment_gateways_hint' => 'Seleziona i gateway di pagamento collegati. Se nessun gateway viene selezionato, questa forma di pagamento non elaborerà pagamenti online automaticamente.',
        'penalty_percent' => 'Mora (%)',
        'penalty_hint' => 'Percentuale di mora applicata in caso di ritardo.',
        'interest_per_day' => 'Interessi per Giorno (%)',
        'interest_hint' => 'Percentuale di interessi applicata per ogni giorno di ritardo.',
        'fixed_fee_total' => 'Commissione Fissa Totale',
        'fixed_fee_total_hint' => 'Valore fisso suddiviso tra le rate.<br>Es: € 10 in 2 rate = € 5 per rata.',
        'fixed_fee_installment' => 'Commissione Fissa per Rata',
        'fixed_fee_installment_hint' => 'Valore addebitato su ogni rata.<br>Es: € 2,50 in 2 rate = € 5 totale.',
        'percent_fee_installment' => 'Commissione % per Rata',
        'percent_fee_installment_hint' => 'Percentuale su ogni rata.<br>Es: 5% di € 100 = € 5 per rata.',
        'days_before_due' => 'Giorni Prima della Scadenza',
        'days_before_due_hint' => 'Numero di giorni prima della scadenza per applicare lo sconto.',
        'discount_percent' => 'Sconto (%)',
        'discount_percent_hint' => 'Percentuale di sconto.<br>Es: 3% di € 100 = € 3 di sconto.',
    ],

    // Opzioni dove mostrare
    'where_options' => [
        'site' => 'Sito',
        'system' => 'Sistema',
        'app' => 'Applicazione',
        'all' => 'Tutti',
    ],

    // Tabella
    'table' => [
        'name' => 'Nome',
        'fees' => 'Commissioni',
        'early_discount' => 'Sconto Anticip.',
        'post_as_paid' => 'Registra Pagato',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    // Azioni
    'actions' => [
        'new' => 'Nuovo',
        'edit' => 'Modifica',
        'delete' => 'Elimina',
        'installment_commands' => 'Comandi Rate',
    ],

    // Badge ed etichette
    'badges' => [
        'fixed' => 'Fisso',
        'fixed_installment' => 'Fisso/rata',
        'percent_installment' => '%/rata',
        'no_fees' => 'Senza commissioni',
        'yes' => 'Sì',
        'no' => 'No',
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
        'no_name' => 'Senza nome',
        'in_days' => 'in :daysd',
    ],

    // Dropdown
    'dropdowns' => [
        'select_branches' => 'Seleziona le filiali...',
        'loading_branches' => 'Caricamento filiali...',
        'error_loading_branches' => 'Errore nel caricamento delle filiali',
        'error_loading' => 'Errore nel caricamento',
        'no_branches' => 'Nessuna filiale registrata',
        'no_branches_short' => 'Nessuna filiale',
        'no_gateway_selected' => 'Nessun gateway selezionato (facoltativo)',
        'loading_gateways' => 'Caricamento gateway...',
        'error_loading_gateways' => 'Errore nel caricamento dei gateway',
        'no_gateways' => 'Nessun gateway registrato',
        'no_gateways_available' => 'Nessun gateway disponibile',
        'no_active_gateways' => 'Nessun gateway attivo registrato',
        'select' => 'Seleziona...',
    ],

    // Esempio di sconto
    'discount_example' => [
        'label' => 'Esempio:',
        'text' => 'Pagando :days giorni prima della scadenza, una rata di € :amount avrà uno sconto del :percent% (€ :discount), per un totale di € :final.',
    ],

    // Messaggi
    'messages' => [
        'load_error' => 'Errore nel caricamento dei dati',
        'server_error' => 'Errore di connessione al server',
        'no_records' => 'Nessuna forma di pagamento trovata',
        'delete_error' => 'Errore durante l\'eliminazione del record',
        'delete_confirm' => 'Vuoi eliminare la forma di pagamento ":name"?',
        'this_record' => 'questa forma di pagamento',
        'not_found' => 'Record non trovato',
        'name_required' => 'Il nome è obbligatorio',
        'branches_required' => 'Seleziona almeno una filiale',
        'save_success' => 'Salvato con successo',
        'save_error' => 'Errore durante il salvataggio',
        'saving' => 'Salvataggio in corso...',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca forma...',
    ],

    // Tipo di record
    'record_type' => 'forma_pagamento',

    // ===== Comandi Rate =====
    'commands' => [
        'title' => 'Comandi Rate',
        'new_title' => 'Nuovo Comando',
        'edit_title' => 'Modifica Comando',

        // Campi
        'fields' => [
            'command' => 'Comando',
            'command_hint' => 'Esempi di utilizzo:<br><br> <b>0</b> - Pagamento in un\'unica soluzione. <br><br> <b>15</b> - Pagamento tra 15 giorni. <br><br> <b>1-12</b> - Genera rata mensile da 1 a 12 rate. <br><br> <b>7/14/21/28</b> - In questo esempio vengono generate 4 rate con le scadenze stabilite. <br><br> <b>Dom, Lun, Mar, Mer, Gio, Ven, Sab</b> - Indica quale giorno della settimana sarà la scadenza. <br><br> <b>d5, d10, d15, ...</b> - Quale giorno del mese sarà la scadenza.<br><br> <b>w36</b> - Verranno create 36 rate settimanali.<br><br> <b>w36-Lun</b> - Verranno create 36 rate settimanali con scadenza ogni lunedì.',
            'description' => 'Descrizione',
            'active' => 'Attivo',
        ],

        // Tabella
        'table' => [
            'command' => 'Comando',
            'description' => 'Descrizione',
            'origin' => 'Origine',
            'status' => 'Stato',
            'actions' => 'Azioni',
        ],

        // Badge
        'badges' => [
            'system' => 'Sistema',
            'custom' => 'Personalizzato',
            'system_command' => 'Comando di sistema',
        ],

        // Azioni
        'actions' => [
            'new' => 'Nuovo Comando',
            'edit' => 'Modifica',
            'delete' => 'Elimina',
        ],

        // Segnaposto
        'placeholders' => [
            'search' => 'Cerca comando...',
            'command' => 'Es: 0, 1-12, 7/14/21/28',
            'description' => 'Descrizione facoltativa del comando',
        ],

        // Messaggi
        'messages' => [
            'no_records' => 'Nessun comando rata trovato',
            'load_error' => 'Errore nel caricamento dei dati',
            'server_error' => 'Errore di connessione al server',
            'command_required' => 'Il campo Comando è obbligatorio.',
            'save_success' => 'Comando salvato con successo!',
            'save_error' => 'Errore durante il salvataggio del comando.',
            'load_command_error' => 'Errore nel caricamento del comando',
            'not_found' => 'Record non trovato',
            'delete_error' => 'Errore durante l\'eliminazione del record.',
            'delete_confirm' => 'Vuoi eliminare il comando ":name"?',
            'this_record' => 'questo comando',
        ],

        // Paginazione
        'pagination' => [
            'rows_per_page' => 'Record per pagina:',
            'showing' => 'Visualizzazione :start-:end di :total record',
        ],
    ],
];
