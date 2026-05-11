<?php

/**
 * Traduzioni del modulo Magazzino - Italiano (Italia)
 */

return [
    'title' => 'Magazzino',
    'title_singular' => 'Prodotto',
    'new_title' => 'Nuovo Prodotto',
    'edit_title' => 'Modifica Prodotto',

    // Sezioni
    'sections' => [
        'product_data' => 'Dati del Prodotto',
        'stock' => 'Magazzino',
        'values' => 'Valori',
    ],

    // Campi
    'fields' => [
        'code' => 'Codice',
        'name' => 'Nome',
        'brand' => 'Marca',
        'model' => 'Modello',
        'unit' => 'Unità',
        'storage_location' => 'Posizione di Stoccaggio',
        'branch' => 'Sede/Filiale',
        'supplier' => 'Fornitore',
        'current_stock' => 'Stock Attuale',
        'minimum_stock' => 'Stock Minimo',
        'purchase_value' => 'Valore di Acquisto',
        'sale_value' => 'Valore di Vendita',
        'auto_deduct' => 'Scarico automatico',
        'auto_deduct_enable' => 'Attivare',
        'allow_negative_stock' => 'Permettere stock negativo',
        'allow_negative_stock_enable' => 'Attivare',
    ],

    // Opzioni unità
    'unit_options' => [
        'UN' => 'UN - Unità',
        'PC' => 'PC - Pezzo',
        'CX' => 'CX - Scatola',
        'KG' => 'KG - Chilogrammo',
        'L' => 'L - Litro',
        'M' => 'M - Metro',
        'M2' => 'M2 - Metro Quadrato',
        'M3' => 'M3 - Metro Cubico',
        'JG' => 'JG - Set',
        'KIT' => 'KIT - Kit',
        'PAR' => 'PAR - Paio',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca...',
        'select' => 'Seleziona...',
        'storage_location' => 'Es: Scaffale A3',
        'search_branch' => 'Digita per cercare...',
        'search_supplier' => 'Digita per cercare...',
        'none' => 'Nessuno',
    ],

    // Stato
    'status' => [
        'label' => 'Stato',
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    // Filtri
    'filters' => [
        'all_branches' => 'Tutte le filiali',
        'all_status' => 'Tutti gli stati',
    ],

    // Suggerimenti (tooltip)
    'tooltips' => [
        'minimum_stock' => 'Avviso quando si raggiunge questo valore. 0 = disabilitato.',
        'auto_deduct' => 'Quando attivato, lo stock verra decrementato automaticamente quando questo prodotto viene utilizzato in un ordine di manutenzione.',
        'allow_negative_stock' => 'Quando attivato, consente di utilizzare il prodotto anche senza stock disponibile. Quando disattivato, impedisce la selezione con stock zero e limita la quantità allo stock disponibile.',
    ],

    // Tabella
    'table' => [
        'code' => 'Codice',
        'product' => 'Prodotto',
        'brand_model' => 'Marca/Modello',
        'unit' => 'Unità',
        'stock' => 'Stock',
        'purchase_value' => 'Valore Acquisto',
        'branch' => 'Filiale',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun record trovato',
        'no_name' => 'Senza nome',
        'load_error' => 'Errore durante il caricamento',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione',
        'inactivated' => 'Prodotto disattivato. È collegato a una manutenzione e non può essere eliminato.',
        'reactivated' => 'Prodotto riattivato con successo!',
        'already_inactive' => 'Il prodotto e già inattivo',
        'reactivate_error' => 'Errore durante la riattivazione',
        'this_record' => 'questo record',
        'load_data_error' => 'Errore durante il caricamento dei dati',
        'load_product_error' => 'Errore durante il caricamento dei dati del prodotto',
        'saving' => 'Salvataggio in corso...',
        'save_error' => 'Errore durante il salvataggio',
        'save_product_error' => 'Errore durante il salvataggio del prodotto',
        'created' => 'Prodotto creato con successo!',
        'updated' => 'Prodotto aggiornato con successo!',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Record per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'magazzino',
];
