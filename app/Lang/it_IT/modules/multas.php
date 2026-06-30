<?php

return [
    'title' => 'Multe',
    'title_singular' => 'Multa',
    'new_title' => 'Nuova Multa',
    'edit_title' => 'Modifica Multa',

    'sections' => [
        'search_responsible' => 'Identificare Responsabile',
        'responsible_data' => 'Dati del Responsabile',
        'fine_data' => 'Dati della Multa',
    ],

    'fields' => [
        'date_time' => 'Data e Ora della Multa',
        'plate' => 'Targa del Veicolo',
        'due_date' => 'Data di Scadenza',
        'value' => 'Valore',
        'infraction_number' => 'N. Infrazione',
        'issuing_body' => 'Ente Sanzionatore',
        'location' => 'Luogo',
        'city' => 'Città',
        'state' => 'Provincia',
        'description' => 'Descrizione',
        'type' => 'Tipo',
        'status' => 'Stato',
        'branch' => 'Filiale',
        'client' => 'Cliente',
        'manual_responsible' => 'Responsabile manuale',
        'vehicle' => 'Veicolo',
        'contract_code' => 'Codice Contratto',
        'rental_code' => 'Codice Noleggio',
        'code' => 'Codice',
        'photo' => 'Foto della Multa',
        'payer' => 'Chi pagherà la multa?',
        'payer_client' => 'Cliente',
        'payer_company' => 'Azienda',
    ],

    'table' => [
        'plate' => 'Targa',
        'client' => 'Cliente',
        'type' => 'Tipo',
        'date_time' => 'Data/Ora',
        'value' => 'Valore',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    'badges' => [
        'type_contract' => 'Contratto',
        'type_rental' => 'Noleggio',
        'status_paid' => 'Pagato',
        'status_pending' => 'In attesa',
        'status_unknown' => 'Senza tipo',
    ],

    'buttons' => [
        'search_responsible' => 'Cerca Responsabile',
        'continue' => 'Continua con questo responsabile',
        'add_manual_responsible' => 'Aggiungi responsabile manualmente',
        'continue_manual' => 'Continua con responsabile manuale',
        'mark_paid' => 'Segna come Pagato',
        'mark_unpaid' => 'Annulla Pagamento',
    ],

    'messages' => [
        'no_records' => 'Nessuna multa trovata',
        'load_error' => 'Errore nel caricamento dei dati',
        'server_error' => 'Errore di connessione al server',
        'save_error' => 'Errore nel salvataggio',
        'created' => 'Multa registrata con successo!',
        'updated' => 'Multa aggiornata con successo!',
        'invalid_file_type' => 'Carica solo un\'immagine o un PDF.',
        'photo_allowed_types' => 'Immagine o PDF',
        'pdf_selected' => 'PDF selezionato',
        'deleted' => 'Multa eliminata con successo!',
        'marked_paid' => 'Multa segnata come pagata!',
        'marked_unpaid' => 'Pagamento annullato!',
        'not_found' => 'Multa non trovata',
        'vehicle_not_found' => 'Veicolo non trovato con questa targa',
        'responsible_found' => 'Responsabile trovato',
        'responsible_not_found' => 'Nessun contratto o noleggio trovato per questo veicolo alla data/ora indicata.',
        'manual_responsible_hint' => 'Seleziona il cliente che aveva il veicolo in quella data. La multa sarà registrata senza contratto o noleggio collegato.',
        'select_manual_responsible' => 'Seleziona il responsabile manuale',
        'search_client_placeholder' => 'Digita il nome o documento del cliente...',
        'required_fields' => 'Compila i campi obbligatori:',
        'saving' => 'Salvataggio...',
        'searching' => 'Ricerca...',
        'confirm_delete' => 'Vuoi davvero eliminare questa multa?',
        'confirm_mark_paid' => 'Vuoi segnare questa multa come pagata?',
        'confirm_mark_unpaid' => 'Vuoi annullare il pagamento di questa multa?',
        'cannot_delete_paid' => 'Non è possibile eliminare una multa già pagata.',
        'this_record' => 'questa multa',
        'select_doc_before_pdf' => 'Seleziona un documento prima di generare il PDF',
        'select_doc_before_send' => 'Seleziona un documento prima di inviare',
        'sending' => 'Invio in corso...',
        'send_success' => 'Documento inviato con successo',
        'send_error' => 'Errore nell\'invio del documento',
        'send_connection_error' => 'Errore di connessione durante l\'invio',
    ],

    'filters' => [
        'all_types' => 'Tutti i tipi',
        'type_contract' => 'Contratto',
        'type_rental' => 'Noleggio',
        'all_status' => 'Tutti gli stati',
        'paid' => 'Pagato',
        'pending' => 'In attesa',
    ],

    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Mostrando',
        'of' => 'di',
        'records' => 'record',
    ],

    'actions' => [
        'new' => 'Nuova Multa',
    ],

    'record_type' => 'multa',

    // =========================================================
    // Stampa (offcanvas-impressao.php)
    // =========================================================
    'print' => [
        'title' => 'Stampa Multa',
        'fine_label' => 'Multa',
        'print_type' => 'Tipo di Documento',
        'notification' => 'Notifica al Cliente',
        'document' => 'Documento Personalizzato',
        'receipt' => 'Ricevuta di Pagamento',
        'indication_term' => 'Modulo di Indicazione del Conducente',
        'select_document' => 'Seleziona Documento',
        'select_document_placeholder' => 'Scegli un modello',
        'no_documents' => 'Nessun modello registrato per Multe',
        'generate_pdf' => 'Genera PDF',
        'send_via' => 'Invia tramite',
    ],

    // =========================================================
    // Modelli PDF
    // =========================================================
    'pdf' => [
        'notification_title' => 'Notifica di Multa',
        'receipt_title' => 'Ricevuta di Pagamento Multa',
        'indication_title' => 'Modulo di Indicazione del Conducente',
        'document_title' => 'Documento',
        'fine_data_section' => 'Dati dell\'Infrazione',
        'vehicle_data_section' => 'Dati del Veicolo',
        'fine_origin_section' => 'Dati della Multa',
        'client_section' => 'Dati del Cliente',
        'owner_section' => 'Dati del Proprietario',
        'driver_section' => 'Dati del Conducente (compilare)',
        'fine_number_label' => 'Numero:',
        'date_label' => 'Data:',
        'ait_label' => 'AIT:',
        'infraction_code_label' => 'Codice Infrazione:',
        'issuing_body_label' => 'Ente Sanzionatore:',
        'location_label' => 'Luogo:',
        'city_state_label' => 'Città/Provincia:',
        'date_time_label' => 'Data/Ora:',
        'description_label' => 'Descrizione:',
        'plate_label' => 'Targa:',
        'brand_model_label' => 'Marca/Modello:',
        'value_label' => 'Importo da Pagare',
        'amount_paid_label' => 'Importo Pagato',
        'discount_40_label' => 'Con sconto del 40%',
        'due_date_label' => 'Scadenza',
        'fine_date_label' => 'Data della Multa:',
        'client_name' => 'Nome:',
        'client_document' => 'Codice Fiscale:',
        'company_name_label' => 'Ragione Sociale:',
        'driver_name' => 'Nome',
        'driver_cpf' => 'Codice Fiscale',
        'driver_cnh' => 'Patente',
        'driver_address' => 'Indirizzo',
        'driver_city' => 'Città',
        'driver_phone' => 'Telefono',
        'signature_place_label' => 'Luogo',
        'signature_date_label' => 'Data',
        'owner_signature' => 'Firma del Proprietario',
        'driver_signature' => 'Firma del Conducente',
        'witness_1' => 'Testimone 1',
        'witness_2' => 'Testimone 2',
        'indication_declaration' => 'Dichiaro, sotto le pene della legge, che il conducente identificato sopra è stato il responsabile dell\'infrazione descritta.',
        'indication_footer' => 'Presentare questo modulo all\'ente sanzionatore entro il termine legale stabilito.',
        'notification_text' => 'Gentile :client, comunichiamo che è stata registrata una multa stradale collegata al veicolo con targa :plate. L\'importo da pagare e di :value, con scadenza il :due. Chiediamo la regolarizzazione entro il termine indicato.',
        'receipt_text' => 'Abbiamo ricevuto da :client, in possesso del documento :document, l\'importo di :value, relativo alla multa n. :fine_number del veicolo con targa :plate, avvenuta il :fine_date. Per chiarezza, firmiamo la presente ricevuta.',
        'receipt_validity' => 'Questa ricevuta ha validità legale e comprova il saldo della multa identificata sopra.',
        'generated_at' => 'Generato il :datetime',
        'page_label' => 'Pagina :page di :total',
    ],

    // =========================================================
    // Centrale Multe (central.php)
    // =========================================================
    'central' => [
        'title' => 'Centrale Multe',
        'search_placeholder' => 'Cerca (nome, targa, AIT)',
        'add_fine' => 'Aggiungi Multa',
        'check_online' => 'Consulta Multe',
        'check_batch' => 'Consulta in Blocco',

        'kpi' => [
            'overdue' => 'Scadute',
            'expiring_30d' => 'In scadenza 30g',
            'on_time' => 'In regola',
            'pending' => 'In attesa',
            'paid' => 'Pagate',
            'pending_value' => 'Valore in Attesa',
        ],

        'balance' => [
            'title' => 'Saldo Consulte',
            'manage' => 'Gestisci',
            'query' => 'Consulta',
            'event' => 'Evento',
            'indication' => 'Indicazione',
        ],

        'origin' => [
            'title' => 'Origine',
            'manual' => 'Manuale',
            'online_query' => 'Consulta Online',
            'auto_event' => 'Evento Automatico',
        ],

        'nominations' => [
            'title' => 'Indicazioni',
            'view_all' => 'Vedi tutte',
            'pending_nomination' => 'In attesa di indicazione',
            'new_unprocessed' => 'Nuove (non elaborate)',
            'sent' => 'Indicazioni inviate',
        ],

        'automation' => [
            'title' => 'Automazioni',
            'auto_query' => 'Auto-consulta',
            'auto_query_help' => 'Consulta automaticamente le multe dei veicoli registrati nell intervallo scelto. L addebito viene effettuato per targa consultata, non in base al numero di multe trovate. Esempio: se una targa restituisce varie multe, viene addebitata solo 1 consulta per quella targa.',
            'every' => 'ogni',
            'auto_events' => 'Eventi automatici',
            'auto_events_help' => 'Riceve notifiche automatiche di Consulta Online quando vengono identificati nuovi eventi di multe. Ogni evento ricevuto consuma saldo come Evento, separato dall addebito di Consulta per targa.',
            'last_query' => 'Ultima consulta: :date',
            'interval_1d' => '1 giorno',
            'interval_3d' => '3 giorni',
            'interval_7d' => '7 giorni',
            'interval_14d' => '14 giorni',
            'interval_30d' => '30 giorni',
            'online_query_requires_cnpj' => 'Consulta Online richiede un CNPJ. Registra una sede principale o filiale con CNPJ valido per attivare le automazioni.',
            'online_query_multiple_cnpjs' => 'Esiste più di un CNPJ registrato. Configura quale CNPJ usare in Consulta Online prima di attivare le automazioni.',
        ],

        'filters' => [
            'type_all' => 'Tipo: Tutti',
            'type_contract' => 'Contratto',
            'type_rental' => 'Noleggio',
            'payment_all' => 'Pag.: Tutti',
            'payment_pending' => 'In attesa',
            'payment_paid' => 'Pagate',
            'due_all' => 'Scad.: Tutti',
            'due_overdue' => 'Scadute',
            'due_expiring' => 'In scadenza 30g',
            'due_on_time' => 'In regola',
            'origin_all' => 'Origine: Tutte',
            'origin_manual' => 'Manuale',
            'origin_online' => 'Consulta Online',
            'origin_event' => 'Evento Automatico',
            'status_all' => 'Stato: Tutti',
            'status_new' => 'Nuovo',
            'status_pending_nomination' => 'In attesa Indicazione',
            'status_nomination_sent' => 'Indicazione Inviata',
            'status_nominated' => 'Indicato',
            'status_transferred' => 'Trasferito',
        ],

        'table' => [
            'plate' => 'Targa',
            'client' => 'Cliente',
            'date' => 'Data',
            'infraction' => 'Infrazione',
            'value' => 'Valore',
            'due' => 'Scad.',
            'payment' => 'Pag.',
            'origin' => 'Origine',
            'status' => 'Stato',
            'actions' => 'Azioni',
        ],

        'pagination' => [
            'rows' => 'Righe:',
            'showing' => 'Mostrando :start-:end di :total',
        ],

        'ranking' => [
            'title' => 'Classifica Veicoli con più Multe',
            'position' => '#',
            'plate' => 'Targa',
            'model' => 'Modello',
            'total' => 'Totale',
            'pending' => 'In attesa',
            'pending_value' => 'Valore in Attesa',
            'no_data' => 'Nessun dato disponibile',
        ],

        'badges' => [
            'origin_query' => 'Consulta',
            'origin_event' => 'Evento',
            'origin_manual' => 'Manuale',
            'paid' => 'Pagato',
            'pending' => 'In attesa',
        ],

        'confirm' => [
            'mark_paid_title' => 'Segna come Pagato',
            'mark_paid_message' => 'Confermi di segnare questa multa come pagata?',
            'revert_title' => 'Annulla Pagamento',
            'revert_message' => 'Confermi di annullare il pagamento di questa multa?',
            'cannot_delete_paid' => 'Non è possibile eliminare una multa già pagata',
            'activate_auto_query_title' => 'Attivare Auto-consulta',
            'activate_auto_query_message' => 'L\'auto-consulta effettuera consulte automatiche periodiche per tutti i veicoli brasiliani. Ogni consulta consuma saldo. Vuoi attivare?',
            'activate_auto_events_title' => 'Attivare Eventi Automatici',
            'activate_auto_events_message' => 'Gli eventi automatici registrano notifiche in tempo reale su nuove infrazioni. Ogni evento consuma saldo. Vuoi attivare?',
            'confirm_activate' => 'Si, attivare',
        ],

        'toast' => [
            'fine_deleted' => 'Multa eliminata con successo',
            'fine_marked_paid' => 'Multa segnata come pagata',
            'payment_reverted' => 'Pagamento annullato',
            'config_error' => 'Errore nell\'aggiornamento della configurazione',
        ],

        'actions' => [
            'edit' => 'Modifica',
            'nominate' => 'Indicare Reale Trasgressore',
            'mark_paid' => 'Segna come Pagato',
            'mark_unpaid' => 'Segna come Non Pagato',
            'delete' => 'Elimina',
            'print' => 'Stampa',
        ],
    ],

    // =========================================================
    // Indicazioni del Conducente (indicacao.php)
    // =========================================================
    'indicacoes' => [
        'title' => 'Indicazioni del Conducente',
        'new_nomination' => 'Nuova Indicazione',

        'summary' => [
            'total' => 'Totale',
            'sent' => 'Inviate',
            'pending' => 'In attesa',
            'accepted' => 'Accettate',
            'rejected' => 'Rifiutate',
        ],

        'filters' => [
            'all_types' => 'Tutti i tipi',
            'real_offender' => 'Reale Trasgressore',
            'main_driver' => 'Conducente Principale',
            'all_status' => 'Tutti gli stati',
            'sent' => 'Inviato',
            'pending' => 'In attesa',
            'processing' => 'In elaborazione',
            'accepted' => 'Accettato',
            'rejected' => 'Rifiutato',
            'cancelled' => 'Cancellato',
            'deleted' => 'Eliminato',
            'expired' => 'Scaduto',
            'plate' => 'Targa',
        ],

        'table' => [
            'date' => 'Data',
            'type' => 'Tipo',
            'plate' => 'Targa',
            'nominee' => 'Indicato',
            'ait' => 'AIT',
            'status' => 'Stato',
            'actions' => 'Azioni',
        ],

        'pagination' => [
            'rows' => 'Righe:',
            'showing' => 'Mostrando :start-:end di :total',
        ],

        'badges' => [
            'real_offender' => 'Reale Trasgressore',
            'main_driver' => 'Conducente Principale',
        ],

        'messages' => [
            'loading' => 'Caricamento...',
            'no_nominations' => 'Nessuna indicazione trovata',
        ],

        'confirm' => [
            'cancel_title' => 'Cancellare Indicazione',
            'cancel_message' => 'Sei sicuro di voler cancellare questa indicazione?',
        ],

        'actions' => [
            'check_status' => 'Verifica stato',
            'cancel' => 'Cancella',
        ],
    ],

    // =========================================================
    // Saldo Consulte (saldo.php)
    // =========================================================
    'saldo' => [
        'title' => 'Saldo Consulte',

        'cards' => [
            'current_balance' => 'Saldo Attuale',
            'total_spent' => 'Totale Speso',
            'total_recharged' => 'Totale Ricaricato',
            'prices_title' => 'Prezzi per Operazione',
            'query' => 'Consulta:',
            'event' => 'Evento:',
            'indication' => 'Indicazione:',
        ],

        'buttons' => [
            'pix' => 'PIX',
            'card' => 'Carta',
            'save' => 'Salva',
        ],

        'auto_recharge' => [
            'title' => 'Auto-ricarica',
            'threshold_label' => 'Ricarica quando il saldo è inferiore a',
            'value_label' => 'Valore della ricarica',
            'requires_card' => 'Richiede una carta di credito salvata. L\'addebito verra effettuato automaticamente tramite Stripe.',
            'card_saved' => 'Carta salvata configurata',
        ],

        'history_title' => 'Storico Transazioni',

        'filters' => [
            'type_all' => 'Tipo: Tutti',
            'type_queries' => 'Consulte',
            'type_events' => 'Eventi',
            'type_indications' => 'Indicazioni',
            'type_pix' => 'Ricarica PIX',
            'type_card' => 'Ricarica Carta',
            'until' => 'fino a',
        ],

        'table' => [
            'date' => 'Data',
            'type' => 'Tipo',
            'description' => 'Descrizione',
            'value' => 'Valore',
            'balance' => 'Saldo',
            'status' => 'Stato',
        ],

        'pagination' => [
            'rows' => 'Righe:',
            'showing' => 'Mostrando :start-:end di :total record',
        ],

        'badges' => [
            'query' => 'Consulta',
            'event' => 'Evento',
            'indication' => 'Indicazione',
            'pix' => 'PIX',
            'card' => 'Carta',
            'confirmed' => 'Confermato',
            'pending' => 'In attesa',
            'failed' => 'Fallito',
        ],

        'messages' => [
            'loading' => 'Caricamento...',
            'no_transactions' => 'Nessuna transazione trovata',
            'auto_recharge_updated' => 'Auto-ricarica aggiornata',
            'save_error' => 'Errore nel salvataggio',
        ],
    ],
];
