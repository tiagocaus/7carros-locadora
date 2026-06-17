<?php

/**
 * Traduzioni del modulo Grupos - Italiano (Italia)
 */

return [
    'title' => 'Gruppi di Veicoli',
    'title_singular' => 'Gruppo',
    'new_title' => 'Nuovo Gruppo',
    'edit_title' => 'Modifica Gruppo',

    // Schede
    'tabs' => [
        'group_data' => 'Dati del Gruppo',
        'values_by_branch' => 'Valori per filiale',
        'prices_by_days' => 'Prezzi per Giorni',
    ],

    // Sezioni
    'sections' => [
        'basic_data' => 'Dati di Base',
        'rental_plans' => 'Piani di Noleggio',
        'insurance' => 'Assicurazioni',
        'tolerance_extras' => 'Tolleranza ed Extra',
        'investor_commission' => 'Commissione Investitore',
        'progressive_prices' => 'Prezzi Progressivi per Giorni',
    ],

    // Campi
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrizione',
        'visible_on_site' => 'Visibile sul sito',
        'km_paid_value' => 'Tariffa Km a Pagamento',
        'km_controlled_value' => 'Tariffa Km Controllato',
        'km_free_value' => 'Tariffa Km Libero',
        'km_excess_value' => 'Tariffa Km Eccedente',
        'km_franchise' => 'Franchigia Km',
        'car_insurance_value' => 'Tariffa Assicurazione Veicolo (al giorno)',
        'third_party_insurance_value' => 'Tariffa Assicurazione Terzi (al giorno)',
        'car_coverage' => 'Copertura Veicolo',
        'third_party_coverage' => 'Copertura Terzi',
        'tolerance_minutes' => 'Minuti di Tolleranza',
        'tolerance_value' => 'Costo di Tolleranza',
        'return_km_value' => 'Tariffa Km di Rientro',
        'additional_driver_value' => 'Costo Conducente Aggiuntivo',
        'commission_type' => 'Tipo di Commissione',
        'commission_value' => 'Valore',
    ],

    // Opzioni di commissione
    'commission_options' => [
        'none' => 'Nessuno (senza commissione)',
        'percentage_rental' => 'Percentuale per il Noleggiatore',
        'fixed_rental_invoice' => 'Importo Fisso per il Noleggiatore (per fattura)',
        'fixed_rental_monthly' => 'Importo Fisso Mensile per il Noleggiatore',
        'fixed_investor_monthly' => 'Importo Fisso Mensile per l\'Investitore',
    ],

    // Etichette dinamiche di commissione
    'commission_labels' => [
        'rental_percentage' => 'Percentuale del Noleggiatore',
        'fixed_per_invoice' => 'Importo Fisso per Fattura',
        'monthly_rental' => 'Importo Mensile (Noleggiatore)',
        'monthly_investor' => 'Importo Mensile (Investitore)',
    ],

    // Suggerimenti sulla commissione
    'commission_hints' => [
        'percentage_rental' => 'Es.: 20% significa che il noleggiatore trattiene il 20% del valore e l\'investitore riceve l\'80%.',
        'fixed_rental_invoice' => 'Es.: € 50 per fattura significa che il noleggiatore trattiene € 50 fissi da ogni pagamento.',
        'fixed_rental_monthly' => 'Es.: € 300/mese per veicolo. Il noleggiatore riceve questo importo fisso mensile per ogni veicolo dell\'investitore.',
        'fixed_investor_monthly' => 'Es.: € 2.000/mese per veicolo. L\'investitore riceve questo importo fisso mensile, indipendentemente dai noleggi.',
    ],

    // Descrizioni
    'descriptions' => [
        'investor_commission' => 'Configura come verrà calcolata la commissione per i veicoli degli investitori in questo gruppo.',
        'progressive_prices' => 'Configura tariffe differenziate in base al numero di giorni di noleggio. Se nessuna fascia è configurata, verrà utilizzata la tariffa base.',
    ],

    // Sotto-schede del prezzo
    'price_tabs' => [
        'km_paid' => 'Km a Pagamento',
        'km_controlled' => 'Km Controllato',
        'km_free' => 'Km Libero',
    ],

    // Fasce di prezzo
    'ranges' => [
        'from' => 'Da',
        'to' => 'a',
        'days_equals' => 'giorni =',
        'add_range' => 'Aggiungi Fascia',
        'no_ranges' => 'Nessuna fascia configurata. Verrà utilizzata la tariffa base.',
        'infinity' => '(illimitato)',
    ],

    // Immagine
    'image' => [
        'alt' => 'Immagine del Gruppo',
        'change' => 'Cambia Immagine',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca gruppo...',
    ],

    // Tabella
    'table' => [
        'image' => 'Immagine',
        'name' => 'Nome',
        'description' => 'Descrizione',
        'site' => 'Sito',
        'actions' => 'Azioni',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun gruppo trovato',
        'no_name' => 'Senza nome',
        'load_error' => 'Errore nel caricamento dei gruppi',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore nell\'eliminazione del gruppo',
        'this_record' => 'questo gruppo',
        'load_group_error' => 'Errore nel caricamento del gruppo',
        'invalid_image_format' => 'Seleziona un\'immagine valida (JPG, PNG o WebP)',
        'image_too_large' => 'L\'immagine non deve superare i 5 MB',
        'name_required' => 'Il nome è obbligatorio',
        'saving' => 'Salvataggio...',
        'save_error' => 'Errore durante il salvataggio',
        'save_server_error' => 'Errore nel salvataggio del gruppo',
        'created' => 'Gruppo creato con successo!',
        'updated' => 'Gruppo aggiornato con successo!',
    ],

    'buttons' => [
        'save_branch_values' => 'Salva valori di questa filiale',
        'save_branch_prices' => 'Salva prezzi di questa filiale',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'gruppo',
];
