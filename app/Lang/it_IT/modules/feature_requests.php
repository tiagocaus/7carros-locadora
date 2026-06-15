<?php

/**
 * Traduzioni del modulo Feature Requests - Italiano (Italia)
 */

return [
    'title' => 'Richieste di Funzionalità',
    'new_title' => 'Nuova Richiesta di Funzionalità',
    'details_title' => 'Dettagli della Richiesta',
    'edit_title' => 'Modifica Richiesta',
    'new_request' => 'Nuova Richiesta',

    // Campi
    'fields' => [
        'title' => 'Titolo della Richiesta',
        'module' => 'Modulo/Area',
        'description' => 'Descrizione Dettagliata',
        'phone' => 'Telefono/WhatsApp (opzionale)',
        'follow_auto' => 'Voglio essere notificato quando questa richiesta viene completata',
    ],

    // Filtri
    'filters' => [
        'status' => 'Stato',
        'module' => 'Modulo',
        'sort' => 'Ordina',
        'all' => 'Tutti',
        'my_requests' => 'Le mie richieste',
        'sort_recent' => 'Più Recenti',
        'sort_votes' => 'Più Votate',
        'sort_oldest' => 'Più Vecchie',
    ],

    // Stato
    'status' => [
        'pending' => 'In Attesa',
        'in_review' => 'In Revisione',
        'in_development' => 'In Sviluppo',
        'completed' => 'Completata',
        'rejected' => 'Rifiutata',
        'awaiting_info' => 'In Attesa di Info',
        'awaiting_info_full' => 'In Attesa di Informazioni',
    ],

    // Priorità
    'priorities' => [
        'low' => 'Bassa',
        'normal' => 'Normale',
        'high' => 'Alta',
        'critical' => 'Critica',
    ],

    // Tabella
    'table' => [
        'title' => 'Titolo',
        'module' => 'Modulo',
        'status' => 'Stato',
        'votes' => 'Voti',
        'actions' => 'Azioni',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca richiesta...',
        'title_input' => 'Descrivi brevemente ciò di cui hai bisogno...',
        'description_input' => 'Spiega in dettaglio cosa ti serve, quale problema vuoi risolvere e come immagini la soluzione...',
        'phone_input' => '+39 999 999 9999',
        'select_module' => 'Seleziona...',
        'admin_response' => 'Aggiungi una risposta o un feedback sulla richiesta...',
    ],

    // Suggerimenti
    'hints' => [
        'title' => 'Sii chiaro e conciso nel titolo',
        'module' => 'A quale parte del sistema si riferisce?',
        'description' => 'Più dettagli fornisci, meglio comprenderemo le tue esigenze',
        'phone' => 'Per ricevere notifiche tramite WhatsApp',
    ],

    // Pulsanti e azioni
    'actions' => [
        'vote' => 'Vota questa richiesta',
        'remove_vote' => 'Rimuovi voto',
        'follow' => 'Segui',
        'unfollow' => 'Smetti di seguire',
        'view_details' => 'Vedi dettagli',
        'view' => 'Vedi',
        'submit' => 'Invia Richiesta',
        'sending' => 'Invio in corso...',
        'save_changes' => 'Salva Modifiche',
    ],

    // Informazioni
    'info' => [
        'voted' => 'Hai votato questa richiesta',
        'following' => 'Sarai notificato quando completata',
        'vote_priority' => 'Votare aumenta la priorità della richiesta',
        'follow_updates' => 'Segui per ricevere notifiche quando ci sono aggiornamenti',
        'requested_by' => 'Richiesto da',
        'not_categorized' => 'Non categorizzato',
        'votes_label' => 'voti',
        'followers_label' => 'follower',
        'responded_at' => 'Risposto il',
    ],

    // Simili
    'similar' => [
        'found' => 'Abbiamo trovato richieste simili:',
        'follow_existing' => 'Puoi seguire una richiesta esistente e sarai notificato quando viene completata.',
        'follow_btn' => 'Segui',
    ],

    // Dettagli
    'details' => [
        'description' => 'Descrizione',
        'admin_response' => 'Risposta del Team 7Carros',
        'additional_info' => 'Informazioni Aggiuntive',
        'id' => 'ID:',
        'priority' => 'Priorità:',
        'updated' => 'Aggiornato:',
        'email' => 'Email:',
    ],

    // Admin
    'admin' => [
        'panel_title' => 'Pannello di Amministrazione',
        'change_status' => 'Cambia Stato',
        'priority' => 'Priorità',
        'response' => 'Risposta/Feedback',
        'notify_creator' => 'Notifica il creatore di questa modifica',
        'notify_followers' => 'Notifica i follower',
        'followers_title' => 'Follower',
        'no_followers' => 'Ancora nessun follower',
        'notify_email' => 'Notifica via email',
        'notify_whatsapp' => 'Notifica via WhatsApp',
    ],

    // Modal di modifica
    'edit' => [
        'title_label' => 'Titolo',
        'description_label' => 'Descrizione',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessuna richiesta trovata',
        'no_title' => 'Senza titolo',
        'other_module' => 'Altro',
        'load_error' => 'Errore nel caricamento delle richieste',
        'server_error' => 'Errore di connessione al server',
        'vote_error' => 'Errore nell\'elaborazione del voto',
        'follow_error' => 'Errore nel seguire la richiesta',
        'process_error' => 'Errore nell\'elaborazione',
        'follow_success' => 'Ora stai seguendo questa richiesta e sarai notificato quando viene completata!',
        'now_following' => 'Ora stai seguendo questa richiesta!',
        'unfollowed' => 'Hai smesso di seguire questa richiesta',
        'vote_added' => 'Voto registrato!',
        'vote_removed' => 'Voto rimosso',
        'title_required' => 'Inserisci il titolo della richiesta',
        'module_required' => 'Seleziona il modulo/area',
        'description_required' => 'Inserisci la descrizione dettagliata',
        'title_required_edit' => 'Inserisci il titolo',
        'description_required_edit' => 'Inserisci la descrizione',
        'submit_success' => 'Richiesta inviata con successo!',
        'submit_error' => 'Errore nell\'invio della richiesta',
        'update_success' => 'Richiesta aggiornata con successo!',
        'update_error' => 'Errore nell\'aggiornamento',
        'update_request_error' => 'Errore nell\'aggiornamento della richiesta',
        'not_found' => 'Richiesta non trovata',
        'id_not_found' => 'ID della richiesta non fornito',
        'load_request_error' => 'Errore nel caricamento della richiesta',
        'admin_save_success' => 'Modifiche salvate con successo!',
        'admin_save_error' => 'Errore nel salvataggio',
        'admin_save_changes_error' => 'Errore nel salvataggio delle modifiche',
        'saving' => 'Salvataggio in corso...',
        'back_to_list' => 'Torna alla lista',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Moduli del sistema (categorie)
    'sistema_inicial' => 'Sistema - Iniziale',
    'sistema_locacoes' => 'Sistema - Noleggi',
    'sistema_contratos' => 'Sistema - Contratti',
    'sistema_matriz_filiais' => 'Sistema - Sede e filiali',
    'sistema_funcionarios' => 'Sistema - Dipendenti',
    'sistema_taxas_servicos' => 'Sistema - Tariffe e servizi',
    'sistema_oficinas' => 'Sistema - Officine',
    'sistema_promocoes' => 'Sistema - Promozioni',
    'sistema_multas' => 'Sistema - Multe',
    'sistema_contas_caixa' => 'Sistema - Conti bancari/cassa',
    'sistema_formas_pagamento' => 'Sistema - Metodi di pagamento',
    'sistema_fornecedores' => 'Sistema - Fornitori',
    'sistema_veiculos' => 'Sistema - Veicoli',
    'sistema_grupos' => 'Sistema - Gruppi',
    'sistema_acessorios_itens' => 'Sistema - Accessori e articoli',
    'sistema_manutencoes' => 'Sistema - Manutenzioni',
    'sistema_plano_manutencoes' => 'Sistema - Piano di manutenzione',
    'sistema_checklist' => 'Sistema - Checklist',
    'sistema_checklist_modelos' => 'Sistema - Modelli checklist',
    'sistema_relatorios' => 'Sistema - Report',
    'sistema_financeiro' => 'Sistema - Finanziario',
    'sistema_site' => 'Sistema - Sito',
    'sistema_clientes' => 'Sistema - Clienti',
    'sistema_whatsapp' => 'Sistema - WhatsApp',
    'sistema_documentos' => 'Sistema - Documenti',
    'sistema_estoque' => 'Sistema - Magazzino',
    'sistema_agenda' => 'Sistema - Agenda',

    // Website e App
    'website_site' => 'Website - Sito',
    'aplicativo_checklist' => 'App - Checklist',

    // Altri
    'outros' => 'Altri',
];
