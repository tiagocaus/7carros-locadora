<?php

/**
 * Traduzioni del modulo Manutenzione - Italiano (Italia)
 *
 * Contiene etichette degli elementi condivisi tra schermate:
 * - Piani di Manutenzione (CRUD)
 * - Manutenzioni (Ordini di Lavoro)
 * - CRON di verifica
 */

return [
    // Titoli generali
    'title' => 'Manutenzione',
    'preventive_title' => 'Manutenzione Preventiva',

    // Etichette degli elementi di manutenzione (condivisi)
    'items' => [
        'motor_oleo' => 'Olio motore',
        'motor_filtrooleo' => 'Filtro olio',
        'motor_correiadentada' => 'Cinghia di distribuzione',
        'motor_correiaalternador' => "Cinghia dell'alternatore",
        'motor_correiaarcondicionado' => 'Cinghia del climatizzatore',
        'motor_correiabombadagua' => "Cinghia della pompa dell'acqua",
        'motor_filtrodear' => 'Filtro aria motore',
        'motor_filtrodecabine' => 'Filtro aria abitacolo',
        'motor_filtrodecombustivel' => 'Filtro carburante',
        'motor_fluidodofreio' => 'Liquido freni',
        'motor_fluidoembreagem' => 'Liquido frizione',
        'motor_discodeembreagem' => 'Disco frizione',
        'motor_fluidocaixademarcha' => 'Olio cambio',
        'motor_limpesaarrefecimento' => 'Lavaggio sistema raffreddamento',
        'motor_vejas' => 'Candele',
        'motor_bateria' => 'Batteria',
        'rodagem_pneus' => 'Pneumatici',
        'rodagem_alinhamento' => 'Allineamento ruote',
        'rodagem_pastilhasdefreio' => 'Pastiglie freno',
        'rodagem_discodefreios' => 'Dischi freno',
        'rodagem_rodiziodepneus' => 'Rotazione pneumatici',
        'acessorio_paletasparabrisa' => 'Spazzole tergicristallo',
        'moto_corrente' => 'Catena di trasmissione',
        'moto_kitrelacao' => 'Kit rapporto (corona/pignone)',
        'moto_oleosuspensao' => 'Olio forcella/sospensione',
        'moto_caboembreagem' => 'Cavo frizione',
        'moto_caboacelerador' => 'Cavo acceleratore',
    ],

    // Categorie (raggruppamento nella UI)
    'categories' => [
        'motor' => 'Motore',
        'rodagem' => 'Trasmissione',
        'acessorio' => 'Accessori',
        'moto' => 'Moto',
    ],

    // Messaggi del CRON
    'cron' => [
        'processing_tenant' => 'Elaborazione tenant: :chave',
        'os_generated' => 'OdL :código generato per veicolo :placa',
        'finished' => 'Completato: :tenants tenants | :veículos veicoli | :os OdL generati',
        'result' => 'Elaborati :tenants tenants, :veículos veicoli, :os OdL generati',
    ],

    // Log di audit
    'audit' => [
        'os_created' => 'Sistema ha generato manutenzione preventiva per veicolo [:placa] - OdL [:código]',
    ],

    // Campi OdL generato
    'os' => [
        'reason' => 'Manutenzione preventiva generata dal sistema.',
        'status_created' => 'Creato dal sistema',
    ],

    // Notifiche (per veicolo - dettagliate)
    'notifications' => [
        'email_subject' => 'Manutenzione Preventiva - Targa :placa',
        'email_body' => "Veicolo: :placa\nContachilometri Attuale: :odômetro km\n\nElementi di manutenzione in scadenza:\n:itens\n\nUn Ordine di Lavoro è stato creato automaticamente.",
        'whatsapp_title' => '*Manutenzione Preventiva*',
        'whatsapp_body' => "Veicolo: :placa\nElementi: :itens\n\nOdL creato automaticamente nel sistema.",
    ],

    // Notifiche CRON (consolidate per tenant)
    'cron_notifications' => [
        'email_subject' => 'Manutenzioni Preventive Create',
        'email_body' => 'Sono state create manutenzioni preventive, accedi al menu veicoli > manutenzioni.',
        'sms_body' => 'Sono state create manutenzioni preventive, accedi al menu veicoli > manutenzioni.',
        'whatsapp_body' => '*[7Carros]* Sono state create manutenzioni preventive, accedi al menu veicoli > manutenzioni.',
    ],

    // ===== Viste (index.php + adicionar.php) =====

    // Titoli delle viste
    'title_list' => 'Manutenzioni',
    'new_title' => 'Nuova Manutenzione',
    'edit_title' => 'Modifica Manutenzione',

    // Schede
    'tabs' => [
        'data' => 'Dati',
        'items' => 'Elementi',
        'financial' => 'Finanziario',
    ],

    // Sezioni
    'sections' => [
        'maintenance_data' => 'Dati della Manutenzione',
        'send_to_workshop' => 'Invio all\'officina',
        'return_from_workshop' => 'Ritorno dall\'officina',
        'services_performed' => 'Servizi Eseguiti',
        'services_performed_note' => 'Queste informazioni sono solo per registrazione e potranno essere usate in calcoli futuri.',
        'maintenance_items' => 'Elementi della Manutenzione',
        'financial_entries' => 'Registrazioni Finanziarie',
        'entry_config' => 'Configurazione della Registrazione',
    ],

    // Campi
    'fields' => [
        'os' => 'OdL',
        'status' => 'Stato',
        'branch' => 'Sede/Filiale',
        'vehicle' => 'Veicolo',
        'workshop' => 'Officina',
        'client' => 'Cliente responsabile del pagamento',
        'send_date' => 'Data Invio',
        'send_odometer' => 'Odometro Invio',
        'send_tank' => 'Serbatoio Invio',
        'return_date' => 'Data Ritorno',
        'return_odometer' => 'Odometro Ritorno',
        'return_tank' => 'Serbatoio Ritorno',
        'odometer' => 'Contachilometri',
        'tank' => 'Serbatoio',
        'send_reason' => 'Motivo dell\'invio all\'officina',
        'workshop_notes' => 'Note dell\'Officina',
        'changed_oil' => 'Cambio Olio',
        'changed_tires' => 'Cambio Pneumatici',
        'product' => 'Prodotto',
        'qty' => 'Qtà',
        'unit_value' => 'Valore Unit.',
        'discount' => 'Sconto',
        'total_value' => 'Valore Totale',
        'action' => 'Azione',
        'description' => 'Descrizione',
        'value' => 'Valore',
        'payment_method' => 'Metodo di Pagamento',
        'installments' => 'Rate',
        'first_due_date' => '1ª Scadenza',
        'interval_days' => 'Intervallo (giorni)',
    ],

    'helpers' => [
        'client_payer' => 'Alla creazione del movimento finanziario: con un cliente sarà un credito; senza cliente sarà una spesa aziendale.',
    ],

    // Opzioni stato
    'status_options' => [
        'created' => 'Creato',
        'created_by_system' => 'Creato dal sistema',
        'open' => 'Aperto',
        'closed' => 'Chiuso',
    ],

    // Livelli serbatoio
    'tank_levels' => [
        'full' => 'Pieno',
        'reserve' => 'Riserva',
    ],

    // Badge
    'badges' => [
        'paid' => 'Pagato',
        'pending' => 'In sospeso',
        'new' => 'Nuovo',
        'editing' => 'In modifica',
    ],

    // Azioni
    'actions' => [
        'new' => 'Nuova',
        'add_item' => 'Aggiungi Elemento',
        'create_full_entry' => 'Crea Registrazione Completa',
        'close_selected' => 'Chiudi Elementi Selezionati',
        'go_to_list' => 'Vai all\'Elenco',
    ],

    // Tabella
    'table' => [
        'os' => 'OdL',
        'vehicle' => 'Veicolo',
        'workshop' => 'Officina',
        'send_date' => 'Data Invio',
        'total' => 'Totale',
        'status' => 'Stato',
        'actions' => 'Azioni',
        'totals' => 'Totali:',
        'total_paid' => 'Totale Pagato:',
        'total_pending' => 'Totale In Sospeso:',
        'total_selected' => 'Totale Selezionato:',
    ],

    // Placeholder
    'placeholders' => [
        'search' => 'Cerca OdL, veicolo...',
        'select' => 'Seleziona...',
        'search_type' => 'Digita per cercare...',
        'search_product' => 'Cerca prodotto...',
        'search_product_service' => 'Cerca prodotto/servizio...',
        'item_description' => 'Descrizione dell\'elemento',
        'manual_description' => 'Inserire descrizione manuale',
        'no_client_company_expense' => 'Nessun cliente — spesa aziendale',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessuna manutenzione trovata',
        'load_error' => 'Errore di caricamento',
        'server_error' => 'Errore di connessione',
        'delete_error' => 'Errore di eliminazione',
        'save_error' => 'Errore di salvataggio',
        'save_success' => 'Manutenzione salvata con successo',
        'no_items' => 'Nessun elemento aggiunto',
        'no_pending_items' => 'Nessun elemento in sospeso',
        'select_product' => 'Seleziona un prodotto',
        'cannot_remove_paid' => 'Non è possibile rimuovere elementi pagati',
        'cannot_edit_paid' => 'Non è possibile modificare elementi pagati',
        'provide_description' => 'Inserire la descrizione o selezionare un prodotto',
        'product_out_of_stock' => 'Prodotto esaurito.',
        'stock_insufficient' => 'Solo :qty disponibile(i). Quantità adeguata.',
        'discount_exceeds_subtotal' => 'Lo sconto non può essere maggiore del subtotale dell\'elemento.',
        'select_at_least_one' => 'Seleziona almeno un elemento',
        'entry_created' => 'Registrazione creata con successo',
        'generic_error' => 'Errore',
        'odometer_required' => 'Inserire il contachilometri di ritorno',
        'saved_title' => 'Manutenzione Salvata',
        'saved_go_to_list' => 'Tornare all\'elenco?',
        'financial_desc' => 'Seleziona gli elementi in sospeso per creare una registrazione finanziaria parziale o clicca su "Crea Registrazione Completa" per includerli tutti.',
        'payer_confirm_title' => 'Conferma cliente responsabile del pagamento',
        'payer_confirm_selected' => 'Attenzione! Il cliente :client sarà responsabile del pagamento dei nuovi movimenti finanziari di questa manutenzione. Confermare?',
        'payer_confirm_removed' => 'Attenzione! Nessun cliente sarà responsabile dei nuovi movimenti finanziari di questa manutenzione; saranno registrati come spesa aziendale. Confermare?',
        'payer_existing_financial_warning' => 'I movimenti finanziari già creati non saranno modificati.',
        'payer_confirm_action' => 'Conferma e salva',
        'payer_confirmation_required' => 'Conferma il cliente responsabile del pagamento prima di salvare.',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzando :start-:end di :total registrazioni',
        'page_navigation' => 'Navigazione pagine',
    ],

    // Stampa
    'print' => [
        'title' => 'Ordine di Lavoro',
        'action' => 'Stampa',
        'cpf_cnpj_label' => 'CPF/CNPJ:',
    ],

    // Tipo di record (per modale di eliminazione)
    'record_type' => 'manutencao',

    // Opzioni del modale di eliminazione
    'delete_options' => [
        'financial_linked' => 'Elimina registrazione finanziaria collegata',
        'restore_stock' => 'Ripristina scorte utilizzate',
    ],

    // Audit finanziario
    'audit_financial' => [
        'section' => 'Registrazione Finanziaria',
        'type' => 'Tipo',
        'complete' => 'Completa',
        'partial' => 'Parziale',
        'payment_method' => 'Metodo di Pagamento',
        'installments' => 'Rate',
        'first_due_date' => '1ª Scadenza',
        'interval' => 'Intervallo',
        'days' => 'giorni',
        'total_value' => 'Valore Totale',
        'selected_items' => 'Elementi Selezionati',
        'item' => 'Elemento',
        'value' => 'Valore',
    ],

    'audit_payer' => [
        'confirmation_label' => 'Conferma del cliente pagatore',
        'confirmed' => 'L’utente è stato informato e ha confermato l’assegnazione o la modifica del cliente pagatore.',
        'confirmed_existing_financial' => 'L’utente è stato informato e ha confermato la modifica del cliente pagatore; i movimenti finanziari esistenti non saranno modificati.',
    ],
];
