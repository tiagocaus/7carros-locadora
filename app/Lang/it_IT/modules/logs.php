<?php

/**
 * Traduzioni del modulo Log di Sistema - Italiano
 */

return [
    'title' => 'Log di Sistema',
    'search_placeholder' => 'Cerca log...',
    'tabs' => [
        'audit' => 'Audit',
        'messages' => 'Invii',
    ],
    'filters' => [
        'all_channels' => 'Tutti i canali',
        'all_statuses' => 'Tutti gli stati',
    ],
    'table' => [
        'date' => 'Data',
        'user' => 'Utente',
        'message' => 'Messaggio',
        'ip' => 'IP',
        'actions' => 'Azioni',
        'channel' => 'Canale',
        'recipient' => 'Destinatario',
        'status' => 'Stato',
        'error' => 'Errore',
        'processed_at' => 'Elaborato il',
    ],
    'channels' => [
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
    ],
    'status' => [
        'pending' => 'In attesa',
        'processing' => 'In elaborazione',
        'sent' => 'Inviato',
        'failed' => 'Fallito',
        'skipped' => 'Ignorato',
    ],
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total registrazioni',
        'showing_lazy' => 'Visualizzazione registrazioni :start-:end',
    ],
    'no_records' => 'Nessun log trovato',
    'details_title' => 'Dettagli della Modifica',
    'payload_title' => 'Dettagli dell\'Invio',
    'empty_value' => '(vuoto)',
    'unrecognized_format' => 'Formato dati non riconosciuto.',
    'view_details' => 'Vedi dettagli',
    'no_details' => 'Nessun dettaglio',
    'messages' => [
        'load_error' => 'Errore durante il caricamento dei log',
        'server_error' => 'Errore di connessione al server',
        'sent_hint' => 'Stato inviato significa che il worker ha elaborato il messaggio e il provider ha accettato la richiesta; non conferma lettura o consegna finale sul dispositivo.',
    ],
];
