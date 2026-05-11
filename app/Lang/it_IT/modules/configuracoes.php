<?php

/**
 * Traduzioni del modulo Configurazioni - Italiano (Italia)
 */

return [
    'templates_title' => 'Modelli di Messaggio',
    'templates_description' => 'Personalizza i modelli di email, WhatsApp e SMS inviati ai clienti.',

    'categories' => [
        'all' => 'Tutti',
        'onboarding' => 'Onboarding',
        'rental' => 'Noleggio',
        'reminder' => 'Promemoria',
        'billing' => 'Finanziario',
    ],

    'category_labels' => [
        'onboarding' => 'Onboarding',
        'rental' => 'Noleggio',
        'reminder' => 'Promemoria',
        'billing' => 'Finanziario',
    ],

    'edit_title' => 'Modifica Modello',
    'edit_title_prefix' => 'Modifica modello:',

    'labels' => [
        'customized' => 'Personalizzato',
        'using_default' => 'Utilizzo predefinito del sistema',
        'email_subject' => 'Oggetto dell\'Email',
        'content' => 'Contenuto',
        'characters' => 'caratteri',
        'available_variables' => 'Variabili Disponibili',
        'click_to_insert' => 'Clicca per inserire nell\'editor',
        'subject' => 'Oggetto:',
        'no_subject' => '(senza oggetto)',
        'content_label' => 'Contenuto:',
    ],

    'placeholders' => [
        'email_subject' => 'Es: Conferma Noleggio #{{noleggio.numero}}',
        'message_content' => 'Digita il contenuto del messaggio...',
    ],

    'warnings' => [
        'sms_split' => 'SMS con più di 160 caratteri verrà diviso',
    ],

    'buttons' => [
        'preview' => 'Anteprima',
        'restore_default' => 'Ripristina Predefinito',
    ],

    'modals' => [
        'attention' => 'Attenzione',
        'unsaved_changes' => 'Hai modifiche non salvate. Vuoi continuare?',
        'continue' => 'Continua',
        'restore_title' => 'Ripristina Modello Predefinito',
        'restore_confirm' => 'Sei sicuro di voler ripristinare questo modello al predefinito del sistema?',
        'restore_warning' => 'Le tue personalizzazioni andranno perse.',
        'restore_btn' => 'Ripristina',
        'preview_title' => 'Anteprima del Modello',
        'close' => 'Chiudi',
    ],

    'messages' => [
        'loading' => 'Caricamento modelli...',
        'loading_page' => 'Caricamento...',
        'load_error' => 'Errore nel caricamento dei modelli.',
        'no_templates' => 'Nessun modello trovato.',
        'no_variables' => 'Nessuna variabile disponibile',
        'saving' => 'Salvataggio...',
        'save_success' => 'Modello salvato con successo!',
        'save_error' => 'Errore nel salvataggio del modello',
        'preview_error' => 'Errore nella generazione dell\'anteprima',
        'restoring' => 'Ripristino...',
        'restore_success' => 'Modello ripristinato al predefinito del sistema',
        'restore_error' => 'Errore nel ripristino del modello',
    ],
];
