<?php

return [
    'title' => 'Commissioni Investitori',

    'filters' => [
        'investor' => 'Investitore',
        'status' => 'Stato',
        'type' => 'Tipo',
        'date_start' => 'Data Inizio',
        'date_end' => 'Data Fine',
    ],

    'status_options' => [
        'all' => 'Tutti',
        'pending' => 'In Attesa',
        'paid' => 'Pagato',
        'cancelled' => 'Annullato',
    ],

    'type_options' => [
        'all' => 'Tutti',
        'rental' => 'Noleggio',
        'contract' => 'Contratto',
        'monthly' => 'Mensile',
    ],

    'totals' => [
        'pending' => 'In Attesa',
        'paid' => 'Pagate',
        'cancelled' => 'Annullate',
        'commissions_count' => 'commissione/i',
    ],

    'table' => [
        'date_ref' => 'Data Rif.',
        'investor' => 'Investitore',
        'vehicle' => 'Veicolo',
        'type' => 'Tipo',
        'base_value' => 'Valore Base',
        'rental_company' => 'Società di Noleggio',
        'investor_value' => 'Investitore',
        'status' => 'Stato',
        'actions' => 'Azioni',
    ],

    'actions' => [
        'mark_paid' => 'Segna come Pagato',
        'cancel' => 'Annulla',
    ],

    'pagination' => [
        'rows_per_page' => 'Righe per pagina:',
        'showing' => 'Visualizzazione :start-:end di :total record',
    ],

    'messages' => [
        'no_records' => 'Nessun record trovato',
        'load_error' => 'Errore durante il caricamento',
        'server_error' => 'Errore di connessione al server',
        'confirm_payment' => 'Confermare il pagamento di questa commissione all\'investitore?',
        'paid_success' => 'Commissione contrassegnata come pagata!',
        'cancel_reason' => 'Motivo dell\'annullamento (facoltativo):',
        'cancelled_success' => 'Commissione annullata!',
    ],
];
