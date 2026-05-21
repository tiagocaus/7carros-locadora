<?php

/**
 * Traduzioni Modelli di Messaggio - Italiano
 *
 * Contiene i nomi e le descrizioni dei tipi di modelli disponibili.
 */

return [
    // Tipi di Modelli
    'types' => [
        // Onboarding
        'welcome' => 'Benvenuto',
        'welcome_description' => 'Messaggio inviato alla registrazione di un nuovo cliente',
        'welcome_desc' => 'Messaggio inviato alla registrazione di un nuovo cliente',

        'cliente_nova_senha' => 'Reimpostazione password cliente',
        'cliente_nova_senha_desc' => 'Inviato al cliente con una nuova password di accesso',
        'cliente_nova_senha_link_desc' => 'Inviato al cliente con un link sicuro per reimpostare la password',

        'funcionario_nova_senha' => 'Reimpostazione password dipendente',
        'funcionario_nova_senha_desc' => 'Inviato al dipendente con una nuova password sicura per accedere al pannello',

        // Noleggio
        'rental_confirmation' => 'Conferma Noleggio',
        'rental_confirmation_description' => 'Inviato quando un noleggio viene confermato',

        'contract_confirmation' => 'Conferma Contratto',
        'contract_confirmation_description' => 'Inviato quando un contratto viene firmato',

        // Promemoria
        'return_reminder' => 'Promemoria Restituzione',
        'return_reminder_description' => 'Avviso prima della data di restituzione prevista',

        'cnh_expiring' => 'Patente in Scadenza',
        'cnh_expiring_description' => 'Avviso quando la patente del cliente sta per scadere',

        // Fatturazione
        'payment_reminder' => 'Promemoria Pagamento',
        'payment_reminder_description' => 'Avviso di fattura in scadenza',

        'invoice_generated' => 'Fattura Generata',
        'invoice_generated_description' => 'Inviato quando viene generata una nuova fattura',

        'overdue_notice' => 'Avviso di Ritardo',
        'overdue_notice_description' => 'Notifica di fattura scaduta',

        'payment_received' => 'Pagamento Ricevuto',
        'payment_received_description' => 'Conferma di ricezione del pagamento',

        // Altri
        'general_notification' => 'Notifica Generale',
        'general_notification_description' => 'Modello per notifiche varie',
    ],

    // Categorie
    'categories' => [
        'onboarding' => 'Registrazione',
        'rental' => 'Noleggio',
        'reminder' => 'Promemoria',
        'billing' => 'Fatturazione',
        'notification' => 'Notifiche',
    ],

    // Canali
    'channels' => [
        'email' => 'E-mail',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    // Messaggi UI
    'ui' => [
        'title' => 'Modelli di Messaggio',
        'subtitle' => 'Personalizza i messaggi inviati ai clienti',
        'search_placeholder' => 'Cerca modelli...',
        'select_template' => 'Seleziona un modello da modificare',
        'available_variables' => 'Variabili Disponibili',
        'preview' => 'Anteprima',
        'editor' => 'Editor',
        'restore_default' => 'Ripristina Predefinito',
        'save_changes' => 'Salva Modifiche',
        'unsaved_changes' => 'Hai modifiche non salvate. Vuoi uscire?',
        'template_saved' => 'Modello salvato con successo!',
        'template_restored' => 'Modello ripristinato ai valori predefiniti',
        'no_templates' => 'Nessun modello disponibile',
        'custom_template' => 'Personalizzato',
        'default_template' => 'Predefinito',
        'subject' => 'Oggetto',
        'content' => 'Contenuto',
        'content_plain' => 'Contenuto (testo semplice)',
        'locale' => 'Lingua',
        'channel' => 'Canale',
        'insert_variable' => 'Clicca per inserire',
    ],

    // Validazione
    'validation' => [
        'entity_not_allowed' => 'L\'entità ":entity" non è consentita in questo modello',
        'variable_not_found' => 'La variabile ":variable" non esiste',
        'content_required' => 'Il contenuto del modello è obbligatorio',
        'subject_required_email' => 'L\'oggetto è obbligatorio per i modelli e-mail',
    ],
];
