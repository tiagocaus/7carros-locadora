<?php

/**
 * Traduzioni del modulo Dipendenti - Italiano (Italia)
 */

return [
    // Titoli
    'title' => 'Dipendenti',
    'title_singular' => 'Dipendente',
    'new_title' => 'Aggiungi Nuovo Dipendente',
    'edit_title' => 'Modifica Dipendente',
    'view_title' => 'Visualizza Dipendente',
    'list_title' => 'Elenco Dipendenti',

    // Sezioni
    'sections' => [
        'employee_data' => 'Dati del Dipendente',
        'personal_data' => 'Dati Personali',
        'drivers_license' => 'Patente di Guida',
        'employment_data' => 'Dati Lavorativi',
        'compensation' => 'Retribuzione',
        'address' => 'Indirizzo',
        'contact' => 'Contatto',
    ],

    // Campi del modulo
    'fields' => [
        'branch' => 'Sede/Filiale',
        'full_name' => 'Nome Completo',
        'email' => 'E-mail',
        'username' => 'Utente',
        'password' => 'Password',
        'new_password' => 'Nuova Password',
        'confirm_password' => 'Conferma Password',
        'confirm_new_password' => 'Conferma Nuova Password',
        'password_hint' => '(lascia vuoto per mantenere)',
        'role' => 'Ruolo/Funzione',
        'cpf' => 'CPF',
        'nationality' => 'Nazionalità',
        'gender' => 'Sesso',
        'marital_status' => 'Stato Civile',
        'cnh_number' => 'N° della CNH',
        'cnh_registry' => 'Registro CNH',
        'cnh_expiry' => 'Scadenza CNH',
        'work_card' => 'Libretto di Lavoro',
        'pis' => 'PIS',
        'salary' => 'Stipendio',
        'salary_type' => 'Tipo di Stipendio',
        'payment_day' => 'Giorno di Pagamento',
        'zip_code' => 'CAP',
        'street' => 'Via',
        'number' => 'N°',
        'complement' => 'Complemento',
        'neighborhood' => 'Quartiere',
        'city' => 'Città',
        'state' => 'Regione/Stato',
        'country' => 'Paese',
        'landline' => 'Tel. Fisso',
        'mobile' => 'Tel. Cellulare',
    ],

    // Opzioni di stato
    'status_options' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    // Opzioni di sesso
    'gender_options' => [
        'male' => 'Maschile',
        'female' => 'Femminile',
    ],

    // Opzioni di stato civile
    'marital_options' => [
        'single' => 'Celibe/Nubile',
        'married' => 'Sposato(a)',
        'divorced' => 'Divorziato(a)',
        'widowed' => 'Vedovo(a)',
    ],

    // Opzioni di tipo di stipendio
    'salary_type_options' => [
        'monthly' => 'Mensile',
        'biweekly' => 'Bisettimanale',
        'weekly' => 'Settimanale',
        'daily' => 'Giornaliero',
    ],

    // Foto
    'photo' => [
        'alt' => 'Foto del Dipendente',
        'take_photo' => 'Scatta foto',
        'change_photo' => 'Cambia foto',
        'choose_title' => 'Scegli Foto',
        'choose_method' => 'Come desideri aggiungere la foto?',
        'upload_file' => 'Carica File',
        'use_camera' => 'Usa Fotocamera',
        'camera_title' => 'Scatta Foto',
        'capture' => 'Cattura',
    ],

    // Tabella di elenco
    'table' => [
        'name' => 'Nome',
        'username' => 'Utente',
        'email' => 'Email',
        'role' => 'Ruolo',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    // Azioni
    'actions' => [
        'add' => 'Aggiungi Dipendente',
        'view' => 'Vedi Dipendente',
        'edit' => 'Modifica Dipendente',
        'delete' => 'Elimina Dipendente',
        'manage_roles' => 'Gestisci Ruoli',
        'set_as_main' => 'Imposta come principale',
    ],

    // Pulsanti specifici
    'buttons' => [
        'save' => 'Salva Dipendente',
        'save_changes' => 'Salva Modifiche',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca dipendente...',
        'select_option' => 'Seleziona un\'opzione...',
        'select_role' => 'Seleziona un ruolo...',
        'nationality' => 'Italiana',
        'payment_day' => 'Es: 5',
    ],

    // Menu a tendina filiali
    'branch_dropdown' => [
        'loading' => 'Caricamento...',
        'loading_branches' => 'Caricamento filiali...',
        'load_error' => 'Errore di caricamento',
        'load_error_detail' => 'Errore nel caricamento delle filiali',
        'no_branches' => 'Nessuna filiale registrata',
        'no_branches_short' => 'Nessuna filiale',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun dipendente trovato',
        'unnamed' => 'Dipendente senza nome',
        'this_employee' => 'questo dipendente',
        'id_not_found' => 'Errore: ID del dipendente non trovato',
        'load_error' => 'Errore nel caricamento dei dipendenti',
        'server_error' => 'Errore di connessione con il server',
        'not_found' => 'Dipendente non trovato',
        'delete_error' => 'Errore nell\'eliminazione del dipendente: :message',
        'save_error' => 'Errore nel salvataggio del dipendente: :message',
        'update_error' => 'Errore nell\'aggiornamento del dipendente: :message',
        'password_required' => 'La password è obbligatoria per i nuovi dipendenti.',
        'password_mismatch' => 'Le password non corrispondono. Per favore, verifica.',
        'passwords_dont_match' => 'Le password non corrispondono',
        'name_support_error' => 'Il nome non può contenere il termine "suporte".',
        'username_support_error' => 'Il nome utente non può contenere il termine "suporte".',
        'username_in_use' => 'Utente già in uso',
        'format_not_supported' => 'Formato non supportato. Usa solo JPEG, PNG o WebP.',
        'image_too_large' => 'L\'immagine è troppo grande. Seleziona un\'immagine inferiore a 5MB.',
        'camera_not_supported' => 'Il tuo browser non supporta l\'accesso alla fotocamera. Usa l\'opzione di caricamento file.',
        'camera_access_denied' => 'Permesso di accesso alla fotocamera negato. Per favore, consenti l\'accesso e riprova.',
        'camera_not_found' => 'Nessuna fotocamera trovata. Usa l\'opzione di caricamento file.',
        'camera_error' => 'Impossibile accedere alla fotocamera.',
        'camera_initializing' => 'Attendi che la fotocamera si inizializzi completamente.',
    ],

    // Modal di eliminazione (fallback locale)
    'delete_modal' => [
        'title' => 'Conferma Eliminazione',
        'confirm_text' => 'ELIMINA',
        'this_record' => 'questo record',
        'message' => 'Vuoi davvero eliminare il :type (:name)?',
        'type_placeholder' => 'Digita :text per confermare',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record (per modal di eliminazione)
    'record_type' => 'dipendente',
];
