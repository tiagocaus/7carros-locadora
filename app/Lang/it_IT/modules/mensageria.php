<?php

/**
 * Traduzioni del modulo Mensageria - Italiano (Italia)
 */

return [
    'title' => 'Messaggistica WhatsApp, SMS e SMTP',
    'subtitle' => 'Messaggistica: WhatsApp, SMS e SMTP(Mail)',

    // Tipi di connessione
    'types' => [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'smtp' => 'SMTP (Mail)',
    ],

    // Comune (condiviso tra le sotto-viste)
    'common' => [
        'connection' => 'Connessione',
        'branches_label' => 'Aziende/Filiali',
        'branches_desc' => 'Seleziona le aziende che utilizzeranno questa connessione',
        'no_branches' => 'Nessuna azienda disponibile',
        'already_linked' => 'Già collegata',
        'none' => 'Nessuna',
        'load_error' => 'Errore durante il caricamento dei dati',
        'load_branches_error' => 'Errore durante il caricamento delle aziende',
        'load_connection_error' => 'Errore durante il caricamento della connessione',
        'fill_required' => 'Compila tutti i campi obbligatori',
        'select_branch' => 'Seleziona almeno un\'azienda',
        'connection_id_missing' => 'ID della connessione non fornito',
    ],

    // Tabella
    'table' => [
        'type' => 'Tipo',
        'linked_branches' => 'Aziende Collegate',
        'identifier' => 'Identificatore',
        'status' => 'Stato',
        'actions' => 'Azioni',
        'no_records' => 'Nessuna connessione trovata',
        'load_error_branches' => 'Errore durante il caricamento',
    ],

    // Pulsanti
    'buttons' => [
        'new_whatsapp' => 'Nuovo WhatsApp',
        'new_sms' => 'Nuovo SMS',
        'new_smtp' => 'Nuovo SMTP',
    ],

    // Ricerca
    'search_placeholder' => 'Cerca connessione...',

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Badge di stato
    'status' => [
        'connected' => 'Connesso',
        'connecting' => 'Connessione in corso',
        'disconnected' => 'Disconnesso',
        'validated' => 'Validato',
        'pending' => 'In attesa',
        'invalid' => 'Non valido',
        'unknown' => 'Sconosciuto',
    ],

    // Titoli delle azioni (pulsanti nella tabella)
    'actions' => [
        'test' => 'Testare',
        'restart' => 'Riavviare',
        'disconnect' => 'Disconnettere',
        'connect' => 'Connettere',
        'recreate' => 'Ricreare connessione',
        'test_sms' => 'Testare SMS',
        'check_balance' => 'Verifica Saldo',
        'validate_credentials' => 'Validare Credenziali',
        'test_email' => 'Testare Email',
        'validate_connection' => 'Validare Connessione',
    ],

    // Titoli offcanvas
    'offcanvas' => [
        'new_whatsapp' => 'Nuova Connessione WhatsApp',
        'edit_whatsapp' => 'Modifica Connessione WhatsApp',
        'connect_whatsapp' => 'Connetti WhatsApp',
        'test_whatsapp' => 'Testa WhatsApp',
        'new_sms' => 'Nuova Connessione SMS',
        'edit_sms' => 'Modifica Connessione SMS',
        'test_sms' => 'Testa SMS',
        'new_smtp' => 'Nuova Connessione SMTP',
        'edit_smtp' => 'Modifica Connessione SMTP',
        'test_smtp' => 'Testa SMTP',
    ],

    // Conferme
    'confirms' => [
        'delete' => 'Vuoi eliminare la connessione ":name"?',
        'disconnect' => 'Vuoi davvero disconnettere questa connessione?',
        'restart' => 'Vuoi riavviare questa connessione? La connessione verra ristabilita.',
    ],

    // Messaggi
    'messages' => [
        // SMTP
        'smtp_created' => 'Connessione SMTP creata con successo!',
        'smtp_updated' => 'Connessione aggiornata con successo!',
        'smtp_deleted' => 'Connessione SMTP eliminata con successo',
        'smtp_validated' => 'Connessione SMTP validata con successo!',
        'smtp_validation_failed' => 'Validazione fallita',
        'smtp_create_error' => 'Errore durante la creazione della connessione',
        'smtp_update_error' => 'Errore durante l\'aggiornamento',
        'smtp_delete_error' => 'Errore durante l\'eliminazione della connessione',
        'smtp_validate_error' => 'Errore durante la validazione',

        // WhatsApp
        'whatsapp_created' => 'Connessione creata! Scansiona il QR Code per connetterti.',
        'whatsapp_created_short' => 'Connessione creata! Scansiona il QR Code.',
        'whatsapp_updated' => 'Connessione aggiornata con successo!',
        'whatsapp_deleted' => 'Connessione WhatsApp eliminata con successo',
        'whatsapp_disconnected' => 'Disconnesso con successo',
        'whatsapp_restarted' => 'Connessione riavviata. Attendere la riconnessione...',
        'whatsapp_recreated' => 'Istanza ricreata! Apertura QR Code...',
        'whatsapp_disconnect_error' => 'Errore durante la disconnessione',
        'whatsapp_restart_error' => 'Errore durante il riavvio',
        'whatsapp_recreate_error' => 'Errore durante la ricreazione',
        'whatsapp_create_error' => 'Errore durante la creazione della connessione',
        'whatsapp_update_error' => 'Errore durante l\'aggiornamento della connessione',
        'whatsapp_delete_error' => 'Errore durante l\'eliminazione della connessione',

        // SMS
        'sms_created' => 'Connessione SMS creata con successo!',
        'sms_updated' => 'Connessione SMS aggiornata con successo!',
        'sms_deleted' => 'Connessione SMS eliminata con successo',
        'sms_validated' => 'Credenziali validate con successo!',
        'sms_validation_failed' => 'Credenziali non valide',
        'sms_create_error' => 'Errore durante la creazione della connessione',
        'sms_update_error' => 'Errore durante l\'aggiornamento della connessione',
        'sms_delete_error' => 'Errore durante l\'eliminazione della connessione',
        'sms_validate_error' => 'Errore durante la validazione',
        'sms_balance' => 'Saldo: :currency :balance',
        'sms_balance_error' => 'Errore durante la verifica del saldo',

        // Test
        'test_sent' => 'Test inviato!',
        'test_success' => 'Inviato con successo!',
        'test_error' => 'Errore durante l\'invio',
        'email_sent' => 'Email inviata!',
        'email_test_success' => 'Email di test inviata con successo!',
        'email_test_error' => 'Invio dell\'email di test fallito',
        'email_test_send_error' => 'Errore durante l\'invio dell\'email di test',
        'sms_sent' => 'SMS inviato!',
        'sms_test_success' => 'SMS di test inviato con successo!',
        'sms_test_error' => 'Invio dell\'SMS di test fallito',
        'sms_test_send_error' => 'Errore durante l\'invio dell\'SMS di test',
        'provide_email' => 'Inserisci un\'email per il test',
        'provide_valid_email' => 'Inserisci un\'email valida',
        'provide_phone' => 'Inserisci un telefono per il test',
        'provide_valid_phone' => 'Inserisci un telefono valido',
        'sending_email' => 'Invio email...',
        'sending_sms' => 'Invio SMS...',

        // QR Code
        'qr_generating' => 'Generazione QR Code...',
        'qr_scan' => 'Scansiona il QR Code con il tuo WhatsApp',
        'qr_error' => 'Errore durante la generazione del QR Code',
        'qr_connect_error' => 'Errore durante la connessione',
        'qr_waiting' => 'In attesa della connessione...',
        'qr_connected' => 'Connesso!',
        'server_error' => 'Errore durante la connessione al server',
    ],

    // SMTP specifico
    'smtp' => [
        'provider' => 'Provider',
        'connection_name' => 'Nome della Connessione',
        'server' => 'Server SMTP',
        'port' => 'Porta',
        'encryption' => 'Crittografia',
        'encryption_none' => 'Nessuna',
        'auth_email' => 'Email di Autenticazione',
        'password' => 'Password / App Password',
        'from_email' => 'Email Mittente',
        'from_name' => 'Nome Mittente',
        'reply_to' => 'Email di Risposta (opzionale)',
        'daily_limit' => 'Limite Giornaliero (opzionale)',
        'daily_limit_hint' => 'Lascia vuoto per nessun limite',
        'password_hint_gmail' => 'Per Gmail, usa una <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="text-blue-600 hover:underline">password per le app</a>',
        'password_hint_custom' => 'Consulta la documentazione del tuo provider SMTP',
        'password_hint_default' => 'Usa la password o App Password del provider',
        'password_change_hint' => 'La modifica della password rivalidera la connessione',
        'keep_blank' => 'Lascia vuoto per mantenere',
        'provider_settings' => 'Impostazioni del provider:',
        'create_validate' => 'Crea e Valida Connessione',
        'test_email_label' => 'Email per il test',
        'test_email_hint' => 'Un\'email di test verra inviata a questo indirizzo',
        'send_test' => 'Invia Email di Test',
    ],

    // Placeholder SMTP
    'smtp_placeholders' => [
        'name' => 'Es: Email Principale',
        'server' => 'smtp.tuoserver.com',
        'auth_email' => 'tuo@email.com',
        'password' => 'Password o password per le app',
        'from_email' => 'noreply@tuaazienda.com',
        'from_name' => 'La Tua Azienda',
        'reply_to' => 'contatto@tuaazienda.com',
        'daily_limit' => 'Es: 500',
    ],

    // WhatsApp specifico
    'whatsapp' => [
        'create_connection' => 'Crea Connessione WhatsApp',
        'send_text' => 'Invia Testo',
        'send_image' => 'Invia Immagine',
        'send_document' => 'Invia Documento',
        'instance_label' => 'Istanza',
    ],

    // SMS specifico
    'sms' => [
        'provider' => 'Provider',
        'sender_id' => 'Sender ID (Mittente)',
        'sender_id_hint' => 'Max 11 caratteri alfanumerici',
        'username' => 'Username ClickSend',
        'api_key' => 'API Key',
        'api_credentials_hint' => 'Disponibile su: ClickSend Dashboard > Developers > API Credentials',
        'api_key_change_hint' => 'La modifica dell\'API Key rivalidera le credenziali',
        'create_validate' => 'Crea e Valida',
        'test_phone_label' => 'Telefono per il test',
        'test_phone_hint' => 'Formato: prefisso internazionale + prefisso locale + numero',
        'test_phone_placeholder' => '39 333 1234567',
        'send_test' => 'Invia SMS di Test',
        'sender_id_short' => 'Sender ID',
    ],
];
