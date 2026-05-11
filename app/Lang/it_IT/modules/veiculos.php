<?php

/**
 * Traduzioni del modulo Veicoli - Italiano (Italia)
 */

return [
    // Titoli
    'title' => 'Veicoli',
    'title_singular' => 'Veicolo',
    'new_title' => 'Nuovo Veicolo',
    'edit_title' => 'Modifica Veicolo',

    // Campi del modulo
    'fields' => [
        'branch' => 'Filiale',
        'supplier' => 'Fornitore',
        'group' => 'Gruppo',
        'plate' => 'Targa',
        'renavam' => 'Registrazione (Renavam)',
        'chassis' => 'Telaio',
        'odometer' => 'Contachilometri (km)',
        'availability' => 'Disponibilità',
        'brand' => 'Marca',
        'model' => 'Modello',
        'year' => 'Anno',
        'color' => 'Colore',
        'transmission' => 'Trasmissione',
        'engine' => 'Motore',
        'max_weight' => 'Peso Max (kg)',
        'current_location' => 'Posizione Attuale',
        'fuel_type' => 'Tipo Carburante',
        'tank_liters' => 'Serbatoio (L)',
        'tank_fraction' => 'Frazione Serbatoio',
        'fraction_value' => 'Valore per Frazione',
        'battery_kwh' => 'Batteria (kWh)',
        'battery_charge' => 'Carica Batteria',
        'purchase_date' => 'Data Acquisto',
        'purchase_value' => 'Valore Acquisto',
        'for_sale' => 'In Vendita',
        'sale_date' => 'Data Vendita',
        'sale_value' => 'Valore Vendita',
        'charge_name' => 'Nome',
        'charge_description' => 'Descrizione',
        'charge_value' => 'Valore',
        'charge_due_date' => 'Scadenza',
        'charge_recurrence' => 'Ricorrenza',
        'charge_days_advance' => 'Anticipo',
        'add_charge' => 'Aggiungi Onere',
        'no_charges' => 'Nessun onere registrato',
        'recurrence_none' => 'Nessuna',
        'recurrence_monthly' => 'Mensile',
        'recurrence_quarterly' => 'Trimestrale',
        'recurrence_semiannual' => 'Semestrale',
        'recurrence_annual' => 'Annuale',
        'save_vehicle_first' => 'Salva il veicolo prima di aggiungere oneri',
        'charge_name_required' => 'Il nome dell\'onere è obbligatorio',
        'description' => 'Descrizione',
        'accessories' => 'Accessori del Veicolo',
        'photo' => 'Foto del Veicolo',
        'change_photo' => 'Cambia Foto',
        'brand_model' => 'Marca/Modello',
        'branch_short' => 'Filiale',
    ],

    // Sezioni del modulo
    'sections' => [
        'basic_data' => 'Dati Base',
        'characteristics' => 'Caratteristiche',
        'fuel' => 'Carburante',
        'purchase_sale' => 'Acquisto e Vendita',
        'vehicle_charges' => 'Oneri del Veicolo',
        'description' => 'Descrizione',
        'accessories' => 'Accessori',
        'select_plan' => 'Seleziona Piano',
    ],

    // Schede
    'tabs' => [
        'vehicle_data' => 'Dati del Veicolo',
        'maintenance_plan' => 'Piano di Manutenzione',
        'maintenances' => 'Manutenzioni',
    ],

    // Scheda Manutenzioni
    'maintenances' => [
        'no_records' => 'Nessuna manutenzione trovata per questo veicolo.',
        'load_error' => 'Errore nel caricamento delle manutenzioni',
        'table_os' => 'OS',
        'table_workshop' => 'Officina',
        'table_send_date' => 'Data Invio',
        'table_return_date' => 'Data Ritorno',
        'table_total' => 'Totale',
        'table_status' => 'Stato',
        'status_created' => 'Creata',
        'status_open' => 'Aperta',
        'status_closed' => 'Chiusa',
        'action_print' => 'Stampa OdL',
    ],

    // Disponibilità
    'availability' => [
        'available' => 'Disponibile',
        'rented' => 'Noleggiato',
        'reserved' => 'Prenotato',
        'in_shop' => 'In officina',
        'sold' => 'Venduto',
        'for_sale' => 'In vendita',
        'internal_use' => 'Uso interno',
        'stolen' => 'Rubato',
        'excluded' => 'Escluso',
        'maintenance' => 'Manutenzione',
        'unavailable' => 'Non disponibile',
    ],

    // Trasmissione
    'transmission' => [
        'automatic' => 'Automatica',
        'manual' => 'Manuale',
    ],

    // Carburante
    'fuel' => [
        'gasoline_ethanol' => 'Benzina/Etanolo',
        'gasoline' => 'Benzina',
        'ethanol' => 'Etanolo',
        'diesel' => 'Diesel',
        'gas' => 'Gas',
        'electric' => 'Elettrico',
        'hybrid' => 'Ibrido',
    ],

    // Frazione serbatoio
    'tank_fraction' => [
        'full' => 'Pieno',
        'reserve' => 'Riserva',
    ],

    // Manutenzione
    'maintenance' => [
        'plan' => 'Piano di Manutenzione',
        'recalculate' => 'Ricalcola con Contachilometri Attuale',
        'recalculate_hint' => 'Ricalcola: contachilometri + intervallo del piano',
        'engine_section' => 'Motore',
        'engine_hint' => 'Prossimo km per ogni elemento di manutenzione del motore',
        'wheels_section' => 'Pneumatici',
        'wheels_hint' => 'Prossimo km per ogni elemento di manutenzione pneumatici',
        'accessories_section' => 'Accessori',
        'accessories_hint' => 'Prossimo km per ogni elemento di manutenzione accessori',
        // Elementi motore
        'engine_oil' => 'Olio Motore',
        'oil_filter' => 'Filtro Olio',
        'timing_belt' => 'Cinghia di Distribuzione',
        'alternator_belt' => 'Cinghia Alternatore',
        'ac_belt' => 'Cinghia Aria Condizionata',
        'water_pump_belt' => "Cinghia Pompa dell'Acqua",
        'air_filter' => "Filtro dell'Aria",
        'cabin_filter' => 'Filtro Abitacolo',
        'fuel_filter' => 'Filtro Carburante',
        'brake_fluid' => 'Liquido Freni',
        'clutch_fluid' => 'Liquido Frizione',
        'clutch_disc' => 'Disco Frizione',
        'gearbox_fluid' => 'Liquido Cambio',
        'cooling_flush' => 'Pulizia Raffreddamento',
        'spark_plugs' => 'Candele',
        'battery' => 'Batteria',
        // Elementi pneumatici
        'tires' => 'Pneumatici',
        'alignment' => 'Allineamento',
        'brake_pads' => 'Pastiglie Freno',
        'brake_discs' => 'Dischi Freno',
        'tire_rotation' => 'Rotazione Pneumatici',
        // Elementi accessori
        'wiper_blades' => 'Spazzole Tergicristallo',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca per targa, marca o modello...',
        'search_select' => 'Digita per cercare...',
        'select' => 'Seleziona...',
        'select_option' => 'Seleziona',
        'select_plan' => 'Seleziona un piano...',
        'plate' => 'ABC-1234',
        'year' => '2024/2025',
        'engine' => '1.0',
        'description' => 'Informazioni aggiuntive sul veicolo...',
        'select_accessories' => 'Seleziona gli accessori...',
        'same_as_branch' => 'Stessa della filiale',
    ],

    // Messaggi
    'messages' => [
        'created' => 'Veicolo creato con successo!',
        'updated' => 'Veicolo aggiornato con successo!',
        'deleted' => 'Veicolo eliminato con successo!',
        'delete_confirm' => 'Vuoi eliminare il veicolo ":name"?',
        'delete_error' => 'Errore durante l\'eliminazione del veicolo',
        'load_error' => 'Errore durante il caricamento dei veicoli: ',
        'load_data_error' => 'Errore durante il caricamento dei dati del veicolo',
        'save_error' => 'Errore durante il salvataggio del veicolo',
        'save_generic_error' => 'Errore durante il salvataggio',
        'connection_error' => 'Errore di connessione al server',
        'no_vehicles' => 'Nessun veicolo trovato',
        'no_plate' => 'Senza targa',
        'this_vehicle' => 'questo veicolo',
        'select_plan_first' => 'Seleziona prima un piano di manutenzione',
        'invalid_image' => 'Seleziona un\'immagine valida (JPG, PNG o WebP)',
        'image_too_large' => 'L\'immagine deve essere al massimo 5MB',
        'accessories_load_error' => 'Errore durante il caricamento degli accessori',
        'accessories_load_error_short' => 'Errore di caricamento',
        'no_accessories' => 'Nessun accessorio registrato',
        'no_accessories_short' => 'Nessun accessorio',
        'plan_load_error' => 'Errore durante il caricamento dei piani di manutenzione:',
        'plan_fetch_error' => 'Errore durante la ricerca del piano:',
        'recalculate_title' => 'Ricalcola Piano',
        'recalculate_confirm' => 'Vuoi ricalcolare i valori del piano in base al contachilometri attuale?',
        'recalculate_btn' => 'Ricalcola',
        'for_sale_tooltip' => 'Attivando la vendita, il veicolo apparirà sul sito come disponibile per la vendita e non sarà più disponibile per il noleggio o il contratto.',
        'loading_accessories' => 'Caricamento accessori...',
        'plan_limit_reached' => 'Limite di veicoli raggiunto. Il tuo piano (:plano) consente un massimo di :limite veicoli attivi. Per riattivare questo veicolo, rimuovine un altro o aggiorna il tuo piano.',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzando :start-:end di :total record',
        'showing_empty' => 'Visualizzando 0-0 di 0 record',
    ],
];
