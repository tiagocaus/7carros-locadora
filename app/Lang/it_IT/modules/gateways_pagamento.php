<?php

/**
 * Traduzioni del modulo Gateway di Pagamento - Italiano (Italia)
 */

return [
    'title' => 'Gateway di Pagamento',
    'title_singular' => 'Gateway di Pagamento',
    'new_title' => 'Nuovo Gateway di Pagamento',
    'edit_title' => 'Modifica Gateway di Pagamento',

    // Sezioni
    'sections' => [
        'gateway_data' => 'Dati del Gateway',
        'payment_methods' => 'Metodi di Pagamento Abilitati',
        'payment_methods_desc' => 'Seleziona quali metodi di pagamento saranno disponibili per questo gateway.',
        'credentials' => 'Credenziali',
        'credentials_desc' => 'Configura le credenziali di accesso al gateway.',
        'webhook' => 'Webhook',
        'webhook_desc' => 'Configura questo URL nel pannello del gateway per ricevere le notifiche di pagamento.',
    ],

    // Campi
    'fields' => [
        'gateway' => 'Gateway',
        'name' => 'Nome di identificazione',
        'branches' => 'Filiali',
        'currencies' => 'Valute Accettate',
        'environment' => 'Ambiente',
        'status' => 'Stato',
        'display_order' => 'Ordine di visualizzazione',
        'methods' => 'Metodi',
        'webhook_url' => 'URL del Webhook',
    ],

    // Metodi di pagamento
    'methods' => [
        'pix' => 'PIX',
        'pix_desc' => 'Pagamento istantaneo',
        'boleto' => 'Boleto',
        'boleto_desc' => 'Bollettino bancario',
        'credit_card' => 'Carta di Credito',
        'credit_card_desc' => 'Rateizzazione disponibile',
        'debit_card' => 'Carta di Debito',
        'debit_card_desc' => 'Addebito in conto',
        'none' => 'Nessuno',
    ],

    // Ambiente
    'environment' => [
        'sandbox' => 'Sandbox (Test)',
        'production' => 'Produzione',
    ],

    // Stato
    'status_options' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    // Paesi
    'countries' => [
        'BR' => 'Brasile',
        'PY' => 'Paraguay',
        'INTL' => 'Internazionale',
    ],

    // Valute
    'currencies' => [
        'BRL' => 'Real Brasiliano',
        'USD' => 'Dollaro Statunitense',
        'EUR' => 'Euro',
        'GBP' => 'Sterlina Britannica',
        'CAD' => 'Dollaro Canadese',
        'AUD' => 'Dollaro Australiano',
        'JPY' => 'Yen Giapponese',
        'MXN' => 'Peso Messicano',
        'CHF' => 'Franco Svizzero',
        'PYG' => 'Guaranì Paraguaiano',
        'ARS' => 'Peso Argentino',
        'CLP' => 'Peso Cileno',
        'COP' => 'Peso Colombiano',
        'PEN' => 'Sol Peruviano',
        'UYU' => 'Peso Uruguaiano',
    ],

    // Suggerimenti
    'hints' => [
        'branches' => 'Lascia vuoto per renderlo disponibile in tutte le filiali.',
        'currencies' => 'Seleziona le valute accettate da questo gateway. Le opzioni disponibili dipendono dal gateway selezionato.',
        'display_order' => 'Il numero più basso appare per primo nell\'elenco delle opzioni.',
        'name_placeholder' => 'Es: Asaas Principale, Stripe Produzione',
    ],

    // Menu a discesa
    'dropdowns' => [
        'select_gateway' => 'Seleziona un gateway...',
        'select_gateway_first' => 'Seleziona prima un gateway',
        'all_branches' => 'Tutte le filiali',
        'no_branches' => 'Nessuna filiale registrata',
        'no_branches_short' => 'Nessuna filiale',
        'no_currencies' => 'Nessuna valuta selezionata',
        'load_error' => 'Errore durante il caricamento',
    ],

    // Tabella
    'table' => [
        'gateway' => 'Gateway',
        'branch' => 'Filiale',
        'methods' => 'Metodi',
        'environment' => 'Ambiente',
        'status' => 'Stato',
        'actions' => 'Azioni',
        'all_branches' => 'Tutte',
    ],

    // Azioni
    'actions' => [
        'test_connection' => 'Testa Connessione',
        'testing' => 'Test in corso...',
        'copy_url' => 'Copia URL',
        'view_docs' => 'Visualizza documentazione',
        'deactivate' => 'Disattiva',
        'activate' => 'Attiva',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca gateway...',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun gateway configurato',
        'no_name' => 'Senza nome',
        'load_error' => 'Errore durante il caricamento dei dati',
        'server_error' => 'Errore durante la connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione del record',
        'status_error' => 'Errore durante la modifica dello stato',
        'test_success' => 'Connessione riuscita! Credenziali validate.',
        'test_fail' => 'Connessione fallita. Verifica le credenziali.',
        'test_error' => 'Errore durante il test della connessione',
        'not_found' => 'Record non trovato',
        'gateway_required' => 'Seleziona un gateway',
        'name_required' => 'Inserisci il nome di identificazione',
        'currency_required' => 'Seleziona almeno una valuta',
        'save_error' => 'Errore durante il salvataggio',
        'save_success' => 'Salvato con successo',
        'load_branches_error' => 'Errore durante il caricamento delle filiali',
        'branch_fallback' => 'Filiale :id',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record per il modale di eliminazione
    'record_type' => 'gateway_pagamento',
];
