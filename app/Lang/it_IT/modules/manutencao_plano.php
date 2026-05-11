<?php

/**
 * Traduzioni del modulo Piano di Manutenzione - Italiano (Italia)
 *
 * Stringhe specifiche del CRUD dei Piani di Manutenzione
 */

return [
    // Titoli
    'title' => 'Piani di Manutenzione',
    'title_new' => 'Aggiungi Piano di Manutenzione',
    'title_edit' => 'Modifica Piano di Manutenzione',

    // Pulsanti
    'btn_new' => 'Nuovo',
    'btn_save' => 'Salva',
    'btn_cancel' => 'Annulla',
    'btn_back' => 'Indietro',

    // Etichette del modulo
    'field_name' => 'Nome del Piano',
    'field_name_placeholder' => 'Es: Piano Standard, Piano Premium...',
    'field_vehicle_type' => 'Tipo di Veicolo',
    'vehicle_car' => 'Auto',
    'vehicle_motorcycle' => 'Moto',
    'field_status' => 'Stato',
    'field_status_active' => 'Attivo',
    'field_status_inactive' => 'Inattivo',
    'field_interval' => 'Intervallo (km)',
    'field_interval_placeholder' => '0',
    'field_interval_hint' => 'Lascia 0 per disattivare questo elemento',

    // Sezioni del modulo
    'section_basic' => 'Dati di Base',
    'section_intervals' => 'Intervalli di Manutenzione',
    'section_intervals_hint' => 'Configura l\'intervallo in chilometri per ogni elemento di manutenzione. Gli elementi con intervallo 0 verranno ignorati.',

    // Tabella
    'table_name' => 'Nome',
    'table_status' => 'Stato',
    'table_items' => 'Elementi Configurati',
    'table_actions' => 'Azioni',
    'table_empty' => 'Nessun piano di manutenzione trovato',
    'table_loading' => 'Caricamento...',

    // Messaggi
    'messages' => [
        'created' => 'Piano di manutenzione creato con successo!',
        'updated' => 'Piano di manutenzione aggiornato con successo!',
        'deleted' => 'Piano di manutenzione eliminato con successo!',
        'not_found' => 'Piano di manutenzione non trovato.',
        'name_required' => 'Il nome del piano è obbligatorio.',
        'confirm_delete' => 'Vuoi eliminare il piano ":name"?',
        'has_vehicles' => 'Questo piano è collegato a veicoli e non può essere eliminato.',
        'load_error' => 'Errore nel caricamento dei piani di manutenzione.',
        'save_error' => 'Errore nel salvataggio del piano di manutenzione.',
        'delete_error' => 'Errore nell\'eliminazione del piano di manutenzione.',
        'no_name' => 'Senza nome',
        'this_plan' => 'questo piano',
    ],

    // Paginazione
    'pagination_info' => 'Visualizzazione :start-:end di :total record',
    'pagination_per_page' => 'Record per pagina',
    'pagination_page_navigation' => 'Navigazione pagine',

    // Ricerca
    'search_placeholder' => 'Cerca piano...',

    // Tooltip
    'tooltip_edit' => 'Modifica piano',
    'tooltip_delete' => 'Elimina piano',
    'tooltip_interval' => 'Chilometri tra le manutenzioni',
];
