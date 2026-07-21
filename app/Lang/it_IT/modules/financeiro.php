<?php

/**
 * Traduzioni del modulo Finanziario - Italiano (Italia)
 */

return [
    // Titoli
    'title' => 'Registrazioni Finanziarie',
    'title_singular' => 'Registrazione Finanziaria',
    'new_title' => 'Nuova Registrazione',
    'edit_title' => 'Modifica Registrazione',

    // Campi
    'fields' => [
        'type' => 'Tipo',
        'type_expense' => 'Uscita (da Pagare)',
        'type_revenue' => 'Entrata (da Ricevere)',
        'bank_account' => 'Conto bancario',
        'payment_method' => 'Metodo di Pagamento',
        'chart_of_accounts' => 'Piano dei Conti',
        'description' => 'Descrizione',
        'document' => 'Documento',
        'creation_date' => 'Data Creazione',
        'due_date' => 'Data Scadenza',
        'is_paid' => 'Registrazione Pagata',
        'payment_date' => 'Data del Pagamento',
        'branch' => 'Sede/Filiale',
        'client' => 'Cliente',
        'supplier' => 'Fornitore',
        'employee' => 'Dipendente',
        'vehicle' => 'Veicolo',
        'subtotal' => 'Subtotale',
        'interest' => 'Interessi',
        'penalty' => 'Penale',
        'discount' => 'Sconto',
        'total_value' => 'Valore Totale',
        'installment_count' => 'Numero di Rate',
        'first_installment_date' => 'Data 1ª Rata',
        'interval' => 'Intervallo',
        'interval_type' => 'Tipo di Intervallo',
        'original_invoice_value' => 'Valore originale della fattura',
        'amount_received' => 'Valore ricevuto',
        'difference_to_create' => 'Differenza da creare',
        'difference_due_date' => 'Scadenza della differenza',
    ],

    // Sezioni
    'sections' => [
        'basic_data' => 'Dati di Base',
        'links' => 'Collegamento/i',
        'links_hint' => 'compilare almeno uno: Cliente, Fornitore, Dipendente o Veicolo',
        'values' => 'Valori',
        'items' => 'Voci della Registrazione',
        'items_hint' => 'facoltativo - se indicato, il Subtotale sarà calcolato automaticamente',
        'generate_installments' => 'Genera Rate',
        'installments_preview' => 'Anteprima delle Rate',
        'installments_list' => 'Rate della Registrazione',
        'partial_payment' => 'Pagamento parziale',
    ],

    // Schede
    'tabs' => [
        'main_data' => 'Dati Principali',
        'installments' => 'Rateizzazione',
    ],

    // Filtri
    'filters' => [
        'branch' => 'Filiale',
        'all_branches' => 'Tutte',
        'year' => 'Anno',
        'all_years' => 'Tutti',
        'month' => 'Mese',
        'all_months' => 'Tutti',
        'status' => 'Stato',
        'all_statuses' => 'Tutti gli stati',
        'status_paid' => 'Pagato',
        'status_due_today' => 'Scade oggi',
        'status_open' => 'Aperto',
        'status_overdue' => 'Scaduto',
        'clear_title' => 'Azzera filtri',
        'search_placeholder' => 'Cerca registrazione...',
    ],

    // Tabella
    'table' => [
        'seq' => 'Seq.',
        'description' => 'Descrizione',
        'client_supplier_employee' => 'Cliente/Fornit/Dip',
        'client_supplier_employee_full' => 'Cliente/Fornitore/Dipendente',
        'due_date' => 'Scadenza',
        'value' => 'Valore',
        'vehicle_plates_label' => 'Targa/e',
        'installment' => 'Rata',
    ],

    // Stato
    'status' => [
        'paid' => 'Pagato',
        'partial_paid' => 'Pagamento parziale',
        'pending' => 'In sospeso',
        'due_in' => 'Scade in :days',
        'due_today' => 'Scade oggi',
        'overdue' => 'Scaduto',
        'day_singular' => '1 giorno',
        'days_plural' => ':count giorni',
    ],

    // Tipi di intervallo
    'interval_types' => [
        'days' => 'Giorni',
        'weeks' => 'Settimane',
        'months' => 'Mesi',
        'years' => 'Anni',
    ],

    // Pulsanti specifici del modulo
    'buttons' => [
        'add_item' => 'Aggiungi Voce',
        'generate_preview' => 'Genera Anteprima',
        'edit_selected' => 'Modifica Selezionati',
        'delete_selected' => 'Elimina Selezionati',
        'delete_selected_count' => 'Elimina selezionati (:count)',
        'select_all_visible' => 'Seleziona tutti i movimenti visibili',
        'payment_link' => 'Link di Pagamento',
        'print_send' => 'Stampa / Invia Fattura',
        'remove_item' => 'Rimuovi voce',
        'create_difference' => 'Crea differenza',
    ],

    'print' => [
        'title' => 'Stampa Fattura',
        'entry_label' => 'Registrazione',
        'value_label' => 'Valore',
        'due_label' => 'Scadenza',
        'print_type' => 'Tipo di Stampa',
        'invoice' => 'Fattura',
        'generate_pdf' => 'Genera PDF',
        'send_via' => 'Invia tramite',
        'no_channels_available' => 'Cliente senza e-mail o telefono registrato, oppure canali di invio non abilitati nel piano.',
        'expense_send_unavailable' => 'Le spese possono essere stampate in PDF, ma non vengono inviate come addebito al fornitore.',
        'sending' => 'Invio...',
        'send_success' => 'Fattura inviata con successo',
        'send_error' => 'Errore durante l\'invio della fattura',
        'send_connection_error' => 'Errore di connessione durante l\'invio',
    ],

    'print_pdf' => [
        'title' => 'Fattura :number',
        'invoice' => 'FATTURA',
        'default_company' => 'Noleggio',
        'company_tax_id' => 'P. IVA/Codice fiscale',
        'zip' => 'CAP',
        'phone_short' => 'Tel',
        'number' => 'Numero',
        'issue_date' => 'Emissione',
        'due_date' => 'Scadenza',
        'paid_at' => 'Pagato il',
        'customer' => 'Cliente',
        'supplier' => 'Fornitore',
        'name' => 'Nome',
        'tax_id' => 'Codice fiscale/P. IVA',
        'address' => 'Indirizzo',
        'city_state' => 'Città/Provincia',
        'email' => 'E-mail',
        'phone' => 'Telefono',
        'description' => 'Descrizione',
        'vehicles' => 'Veicolo/i',
        'items' => 'Voci',
        'value' => 'Valore',
        'subtotal' => 'Subtotale',
        'interest' => 'Interessi',
        'penalty' => 'Penale',
        'discount' => 'Sconto',
        'total' => 'TOTALE',
        'observations' => 'Osservazioni',
        'online_payment_link' => 'Link per pagamento online',
        'generated_at' => 'Generato il :date',
        'status_paid' => 'PAGATO',
        'status_overdue' => 'SCADUTO',
        'status_open' => 'APERTO',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessuna registrazione trovata',
        'no_description' => 'Senza descrizione',
        'load_error' => 'Errore durante il caricamento delle registrazioni: :message',
        'connection_error' => 'Errore di connessione al server',
        'delete_confirm' => 'Eliminare la registrazione ":name"?',
        'delete_error' => "Errore durante l'eliminazione della registrazione",
        'selected_entries' => ':count movimento/i selezionato/i',
        'batch_delete_error' => "Errore durante l'eliminazione dei movimenti selezionati",
        'batch_delete_partial_title' => 'Eliminazione completata parzialmente',
        'save_error' => 'Errore durante il salvataggio della registrazione',
        'not_found' => 'Registrazione non trovata',
        'load_single_error' => 'Errore durante il caricamento della registrazione',
        'this_entry' => 'questa registrazione',
        'no_items' => 'Nessuna voce aggiunta',
        'item_description_placeholder' => 'Descrizione della voce...',
        'subtotal_converted' => 'Subtotale (convertito)',
        'no_installments' => 'Questa registrazione non ha rate associate',
        'inform_first_date' => 'Indicare la data della prima rata',
        'value_must_be_positive' => 'Il valore totale deve essere maggiore di zero',
        'installment_count_range' => 'Il numero di rate deve essere compreso tra :min e :max',
        'select_installment' => 'Selezionare almeno una rata',
        'inform_field_update' => 'Indicare almeno un campo da aggiornare',
        'installments_updated' => ':count rata/e aggiornata/e',
        'installments_update_error' => "Errore durante l'aggiornamento delle rate",
        'installments_deleted' => ':count rata/e eliminata/e',
        'installments_delete_error' => "Errore durante l'eliminazione delle rate",
        'payment_link_error' => 'Errore durante la generazione del link di pagamento',
        'partial_difference_hint' => 'La differenza verrà creata come una nuova fattura in sospeso.',
        'save_before_partial' => 'Salvare la registrazione prima di registrare un pagamento parziale',
        'partial_value_invalid' => 'Indicare un valore ricevuto maggiore di zero e minore del valore totale',
        'partial_payment_date_required' => 'Indicare la data del pagamento',
        'partial_difference_due_required' => 'Indicare la scadenza della differenza',
        'partial_success' => 'Pagamento parziale registrato con successo',
        'partial_error' => 'Errore durante la registrazione del pagamento parziale',
        'partial_use_button' => 'Usare il pulsante Crea differenza per registrare un pagamento parziale',
        // Validazione
        'required_field' => 'Campo obbligatorio: :field',
        'fill_at_least_one_link' => 'Compilare almeno uno: Cliente, Fornitore, Dipendente o Veicolo',
        'vehicle_link_item_mismatch' => 'Il veicolo del collegamento è diverso dal veicolo indicato in una voce. Rimuovere il veicolo dal collegamento o usare lo stesso veicolo nelle voci.',
        'inform_value_or_item' => 'Indicare il Subtotale o aggiungere almeno una voce',
        'payment_date_required' => 'La Data del Pagamento è obbligatoria quando la registrazione è contrassegnata come pagata',
    ],

    // Modale di modifica in blocco delle rate
    'installment_modal' => [
        'edit_title' => 'Modifica :count Rata/e',
        'new_due_date' => 'Nuova Data di Scadenza',
        'due_date_hint' => 'Lasciare vuoto per mantenere le date attuali',
        'payment_status' => 'Stato del Pagamento',
        'keep_current' => 'Mantieni attuale',
    ],

    // Informazioni sulla rateizzazione
    'installment_info' => [
        'title' => 'Come usare la rateizzazione:',
        'step_1' => 'Compilare i dati della registrazione nella scheda "Dati Principali"',
        'step_2' => 'Indicare il Subtotale o aggiungere voci',
        'step_3' => 'Configurare il numero di rate e la data della prima rata',
        'step_4' => "Definire l'intervallo (es: 1 mese, 15 giorni, 2 settimane)",
        'step_5' => 'Cliccare su "Genera Anteprima" per visualizzare le rate',
        'step_6' => 'Salvare la registrazione - tutte le rate saranno create automaticamente',
        'tip' => "Il valore sarà diviso equamente tra le rate. Le differenze di centesimi saranno corrette nell'ultima rata.",
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total registrazioni',
    ],

    // Hints (istruzioni dei campi)
    'hints' => [
        'valor_subtotal' => 'Se ci sono voci, verrà calcolato automaticamente dalla somma dei valori. Altrimenti, inserire manualmente. Dopo il salvataggio, non può essere modificato.',
        'valor_total' => 'Somma automatica: Subtotale + Interessi + Penale - Sconto.',
    ],

    // Voci - intestazioni
    'items_header' => [
        'description' => 'Descrizione',
        'vehicle' => 'Veicolo',
        'chart_of_accounts' => 'Piano dei Conti',
        'value' => 'Valore',
    ],

    // Rate - tipi di record
    'record_types' => [
        'entry' => 'registrazione',
        'entries' => 'registrazioni',
        'installments' => 'rate',
    ],
];
