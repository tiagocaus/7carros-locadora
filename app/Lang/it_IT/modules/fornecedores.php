<?php

/**
 * Traduzioni del modulo Fornecedores - Italiano (Italia)
 */

return [
    'title' => 'Fornitori',
    'title_singular' => 'Fornitore',
    'new_title' => 'Nuovo Fornitore',
    'edit_title' => 'Modifica Fornitore',

    // Sezioni
    'sections' => [
        'basic_data' => 'Dati di Base',
        'address' => 'Indirizzo',
        'investor' => 'Investitore',
        'observations' => 'Osservazioni',
    ],

    // Campi
    'fields' => [
        'type' => 'Tipo',
        'cpf_cnpj' => 'CPF/CNPJ',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'name' => 'Nome',
        'company_name' => 'Ragione Sociale',
        'trade_name' => 'Nome Commerciale',
        'rg' => 'RG',
        'state_registration' => 'Iscrizione Statale',
        'municipal_registration' => 'Iscrizione Municipale',
        'email' => 'Email',
        'phone1' => 'Telefono 1',
        'phone2' => 'Telefono 2',
        'zip_code' => 'CAP',
        'street' => 'Via',
        'number' => 'Numero',
        'complement' => 'Complemento',
        'neighborhood' => 'Quartiere',
        'city' => 'Città',
        'state' => 'Regione',
        'country' => 'Paese',
        'supplies_vehicles' => 'Fornisce Veicoli',
        'is_investor' => 'E Investitore?',
        'split_gateway' => 'Gateway per Split',
        'split_account_id' => 'ID Conto/Wallet',
        'pix_key' => 'Chiave PIX',
        'pix_key_type' => 'Tipo di Chiave PIX',
        'bank_code' => 'Codice Banca',
        'bank_branch' => 'Filiale',
        'bank_account' => 'Conto',
        'bank_account_type' => 'Tipo di Conto',
        'portal_password' => 'Password del portale',
        'portal_password_help' => 'Usa almeno 8 caratteri. Durante la modifica, lascia vuoto per mantenere la password attuale.',
    ],

    // Opzioni di tipo
    'type_options' => [
        'PJ' => 'Persona Giuridica',
        'PF' => 'Persona Fisica',
    ],

    // Opzioni gateway
    'gateway_options' => [
        'none' => 'Nessuno (manuale)',
        'asaas' => 'Asaas',
        'gerencianet' => 'Gerencianet',
        'stripe' => 'Stripe',
        'inter' => 'Banco Inter',
    ],

    // Opzioni tipo chiave PIX
    'pix_type_options' => [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'Email',
        'telefone' => 'Telefono',
        'aleatoria' => 'Chiave Casuale',
    ],

    // Opzioni tipo di conto
    'account_type_options' => [
        'corrente' => 'Corrente',
        'poupanca' => 'Risparmio',
    ],

    'commission_rules' => [
        'title' => 'Regole di commissione',
        'description' => 'La prima riga, "Regola predefinita", vale per tutti i gruppi dell’investitore quando non esiste un’eccezione specifica. Per definire un accordo diverso per un gruppo, clicca su "Aggiungi eccezione per gruppo".',
        'help' => 'La "Regola predefinita" è la regola generale dell’investitore. Usala quando questo investitore ha la stessa commissione per tutti i suoi veicoli, indipendentemente dal gruppo. Esempio: se la regola predefinita è 20% per la locadora, tutti i veicoli di questo investitore usano questa regola, anche se sono in gruppi diversi. Se un gruppo ha un accordo diverso, clicca su "Aggiungi eccezione per gruppo", scegli il gruppo e inserisci la commissione specifica. In questo caso, il sistema usa prima l’eccezione del gruppo; se non esiste un’eccezione, usa la regola predefinita dell’investitore; se non esiste nemmeno una regola predefinita, usa la regola registrata nel gruppo del veicolo.',
        'add_group_rule' => 'Aggiungi eccezione per gruppo',
        'default_rule' => 'Regola predefinita',
        'group_rule' => 'Regola per gruppo',
        'group_placeholder' => 'Seleziona il gruppo',
        'type_placeholder' => 'Tipo di commissione',
        'value' => 'Valore',
        'remove' => 'Rimuovi',
    ],

    // Segnaposto
    'placeholders' => [
        'search' => 'Cerca...',
        'split_account' => 'Es: wal_xxxx',
        'bank_code' => 'Es: 001',
        'select' => 'Seleziona...',
    ],

    // Filtri
    'filters' => [
        'all' => 'Tutti',
        'suppliers' => 'Fornitori',
        'investors' => 'Investitori',
    ],

    // Tabella
    'table' => [
        'name' => 'Nome',
        'cpf_cnpj' => 'CPF/CNPJ',
        'phone' => 'Telefono',
        'investor' => 'Investitore',
        'actions' => 'Azioni',
    ],

    // Etichette
    'badges' => [
        'investor_yes' => 'Si',
        'investor_no' => 'No',
    ],

    // Messaggi
    'messages' => [
        'no_records' => 'Nessun record trovato',
        'no_name' => 'Senza nome',
        'load_error' => 'Errore durante il caricamento',
        'server_error' => 'Errore di connessione al server',
        'delete_error' => 'Errore durante l\'eliminazione',
        'this_record' => 'questo record',
        'load_data_error' => 'Errore durante il caricamento dei dati',
        'load_supplier_error' => 'Errore durante il caricamento dei dati del fornitore',
        'saving' => 'Salvataggio...',
        'save_error' => 'Errore durante il salvataggio',
        'save_supplier_error' => 'Errore durante il salvataggio del fornitore',
        'created' => 'Fornitore creato con successo!',
        'updated' => 'Fornitore aggiornato con successo!',
    ],

    // Paginazione
    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    // Tipo di record
    'record_type' => 'fornitore',
];
