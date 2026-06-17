<?php

/**
 * Traduzioni del modulo Ruoli - Italiano (Italia)
 */

return [
    'title' => 'Gestisci Ruoli',
    'title_singular' => 'Ruolo',
    'new_title' => 'Nuovo Ruolo',
    'edit_title' => 'Modifica Ruolo',
    'edit_prefix' => 'Modifica:',

    // Sezioni
    'sections' => [
        'role_data' => 'Dati del Ruolo',
        'permissions' => 'Permessi',
        'permissions_desc' => 'Seleziona i permessi a cui questo ruolo avrà accesso:',
    ],

    // Campi
    'fields' => [
        'name' => 'Nome del Ruolo',
        'description' => 'Descrizione',
    ],

    // Segnaposto
    'placeholders' => [
        'name' => 'Es: Responsabile, Addetto...',
        'name_full' => 'Es: Responsabile, Addetto, Autista...',
        'description' => 'Descrivi le responsabilità...',
        'description_full' => 'Descrivi le responsabilità di questo ruolo...',
    ],

    // Etichette
    'badges' => [
        'system' => 'Sistema',
        'custom' => 'Personalizzato',
    ],

    // Avvisi
    'warnings' => [
        'system_role_title' => 'Ruolo di Sistema',
        'system_role_desc' => 'Questo è un ruolo predefinito del sistema. Salvando le modifiche, verrà creata una <strong>copia personalizzata</strong> esclusiva per la tua azienda. Il ruolo originale del sistema rimarrà invariato.',
        'system_role_short' => 'Questo è un ruolo di sistema. Salvando, verrà creata una copia personalizzata per la tua azienda.',
        'custom_role_title' => 'Ruolo Personalizzato',
        'custom_role_desc' => 'Questa è una versione personalizzata di un ruolo di sistema. Il nome non può essere modificato.',
        'name_locked' => 'Nome bloccato (ruolo personalizzato del sistema)',
        'name_locked_title' => 'Il nome non può essere modificato nei ruoli personalizzati del sistema',
        'irreversible' => 'Questa azione non può essere annullata.',
    ],

    // Azioni
    'actions' => [
        'save_role' => 'Salva Ruolo',
        'save_changes' => 'Salva Modifiche',
        'create_copy' => 'Crea Copia Personalizzata',
        'delete_role' => 'Elimina Ruolo',
        'select_all' => 'Seleziona tutti',
        'select_all_short' => 'Tutti',
    ],

    // Messaggi
    'messages' => [
        'loading_roles' => 'Caricamento ruoli...',
        'loading_permissions' => 'Caricamento permessi...',
        'load_error' => 'Errore nel caricamento dei ruoli.',
        'load_role_error' => 'Errore nel caricamento dei dati del ruolo',
        'load_permissions_error' => 'Errore nel caricamento dei permessi.',
        'no_records' => 'Nessun ruolo registrato.',
        'no_permissions' => 'Nessun permesso disponibile.',
        'not_found' => 'Ruolo non trovato',
        'reserved_name' => 'Questo nome ruolo è riservato dal sistema',
        'save_error' => 'Errore nel salvataggio del ruolo',
        'delete_error' => 'Errore nell\'eliminazione del ruolo',
        'process_error' => 'Errore nell\'elaborazione della richiesta',
        'deleting' => 'Eliminazione in corso...',
        'create_success' => 'Ruolo Creato!',
        'update_success' => 'Ruolo Aggiornato!',
        'copy_created' => 'Copia Personalizzata Creata!',
        'delete_confirm' => 'Sei sicuro di voler eliminare il ruolo ":name"?',
        'closing_countdown' => 'Chiusura in :seconds secondi...',
    ],

    // Nomi dei moduli (per la visualizzazione dei permessi)
    'module_names' => [
        'dashboard' => 'Dashboard',
        'locacoes' => 'Noleggi',
        'contratos' => 'Contratti',
        'veiculos' => 'Veicoli',
        'clientes' => 'Clienti',
        'funcionarios' => 'Dipendenti',
        'financeiro' => 'Finanze',
        'relatorios' => 'Rapporti',
        'configuracoes' => 'Impostazioni',
        'roles' => 'Ruoli',
        'matrizes_filiais' => 'Sedi/Filiali',
        'empresas' => 'Aziende',
        'fornecedores' => 'Fornitori',
        'acessorios' => 'Accessori',
        'grupos' => 'Gruppi di Veicoli',
        'taxas_servicos' => 'Tariffe e Servizi',
        'oficinas' => 'Officine',
        'localizar' => 'Localizza Veicolo',
        'agenda' => 'Agenda',
        'website' => 'Sito Web',
        'logs' => 'Log di Sistema',
        'app_vistoria' => 'App Ispezione',
        'multas' => 'Multe',
        'promocoes' => 'Promozioni',
        'manutencoes' => 'Manutenzioni',
        'manutencao' => 'Manutenzione',
        'manutencoes_planos' => 'Piani di Manutenzione',
        'formas' => 'Metodi di Pagamento',
        'checklists' => 'Checklist',
        'checklist' => 'Checklist',
        'checklists_modelos' => 'Modelli di Checklist',
        'contas' => 'Conti Bancari',
        'cartao' => 'Carta',
        'documentos' => 'Documenti',
        'estoque' => 'Magazzino',
        'acesso' => 'Controllo Accessi',
        'notificacoes' => 'Notifiche',
        'whatsapp' => 'WhatsApp',
        'promissorias' => 'Cambiali',
        'feature_requests' => 'Richiedi nuova funzionalità',
        'reservas' => 'Prenotazioni',
    ],
];
