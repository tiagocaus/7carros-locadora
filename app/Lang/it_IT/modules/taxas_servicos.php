<?php

/**
 * Traduzioni del modulo Tasse e Servizi - Italiano (Italia)
 */

return [
    'title' => 'Tasse e Servizi',
    'title_singular' => 'Tassa/Servizio',
    'new_title' => 'Nuova Tassa/Servizio',
    'edit_title' => 'Modifica Tassa/Servizio',

    // Sezioni
    'sections' => [
        'fee_data' => 'Dati della Tassa/Servizio',
    ],

    // Campi
    'fields' => [
        'name' => 'Nome',
        'branches' => 'Filiali',
        'calculation_base' => 'Base di Calcolo',
        'value_type' => 'Tipo di Valore',
        'value' => 'Valore',
        'auto_apply' => 'Applica Automaticamente',
        'where_to_use' => 'Dove Utilizzare',
    ],

    // Tooltip
    'tooltips' => [
        'auto_apply' => 'Quando attivo, la tassa verrà aggiunta automaticamente ai nuovi contratti.',
        'where_to_use' => 'Seleziona dove sarà disponibile questa tassa.',
    ],

    // Opzioni base di calcolo
    'calculation_options' => [
        'fixed' => 'Fisso (valore unico)',
        'per_period' => 'Per Periodo (calcolato al giorno)',
        'total_value' => 'Valore Totale',
    ],

    // Opzioni tipo di valore
    'value_type_options' => [
        'monetary' => 'Monetario (€)',
        'percentage' => 'Percentuale (%)',
    ],

    // Opzioni di applicazione
    'apply_options' => [
        'no' => 'No (richiede selezione manuale)',
        'yes' => 'Sì (applicata automaticamente)',
    ],

    // Opzioni dove usare
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Sito Web',
        'app' => 'App',
        'all' => 'Tutti',
    ],

    // Badge
    'badges' => [
        'base_fixed' => 'Fisso',
        'base_per_period' => 'Per Periodo',
        'base_total_value' => 'Valore Totale',
        'apply_yes' => 'Sì',
        'apply_no' => 'No',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca tassa...',
        'select_branches' => 'Seleziona le filiali...',
        'all_branches' => 'Tutte le filiali',
        'select' => 'Seleziona...',
        'name_example' => 'Es: Tassa di pulizia',
    ],

    // Tabella
    'table' => [
        'name' => 'Nome',
        'calculation_base' => 'Base Calcolo',
        'value' => 'Valore',
        'auto_apply' => 'Applica Auto',
        'branches' => 'Filiali',
        'actions' => 'Azioni',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessuna tassa o servizio trovato',
        'no_name' => 'Senza nome',
        'all_branches' => 'Tutte',
        'load_error' => 'Errore nel caricamento dei dati',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore nell\'eliminazione del record',
        'this_record' => 'questa tassa/servizio',
        'not_found' => 'Tassa/servizio non trovato',
        'load_branches_error' => 'Errore nel caricamento delle filiali',
        'load_branches_text' => 'Errore nel caricamento',
        'no_branches' => 'Nessuna filiale registrata',
        'no_branches_text' => 'Nessuna filiale',
        'loading_branches' => 'Caricamento filiali...',
        'required_fields' => 'Compilare i campi obbligatori:',
        'saving' => 'Salvataggio...',
        'save_error' => 'Errore nel salvataggio',
        'created' => 'Tassa/servizio creato con successo!',
        'updated' => 'Tassa/servizio aggiornato con successo!',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'taxa_servico',
];
