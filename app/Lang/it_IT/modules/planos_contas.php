<?php

/**
 * Traduções do módulo Planos de Contas - Italiano
 */

return [
    // Títulos
    'title' => 'Piano dei Conti',
    'title_singular' => 'Conto',
    'list_title' => 'Piano dei Conti',
    'new_title' => 'Nuovo Conto',
    'edit_title' => 'Modifica Conto',

    // Campos do formulário
    'fields' => [
        'hierarquia' => 'Codice',
        'descricao' => 'Descrizione',
        'tipo' => 'Tipo',
        'tipo_ativo' => 'Attivo',
        'tipo_passivo' => 'Passivo',
        'tipo_despesa' => 'Spesa',
        'tipo_receita' => 'Ricavo',
        'conta_pai' => 'Conto Padre',
        'descricao_pt_BR' => 'Portoghese (Brasile)',
        'descricao_en_US' => 'Inglese (USA)',
        'descricao_es_ES' => 'Spagnolo',
        'descricao_it_IT' => 'Italiano',
        'descricao_pt_PT' => 'Portoghese (Portogallo)',
    ],

    // Seções do formulário
    'sections' => [
        'basic_info' => 'Informazioni di Base',
        'translations' => 'Descrizioni per Lingua',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Cerca conto...',
        'descricao' => 'Es.: Cassa generale',
        'descricao_optional' => 'Opzionale - userà pt_BR se vuoto',
        'conta_pai' => 'Seleziona il conto padre (opzionale per conto radice)',
        'selecione_tipo' => 'Seleziona prima il tipo',
        'hierarquia' => 'Es.: 1.1.1.01',
    ],

    // Filtros
    'filters' => [
        'all_types' => 'Tutti i tipi',
    ],

    // Tooltips
    'tooltips' => [
        'hierarquia' => 'Codice gerarchico univoco. Es.: 1.1.1.01',
        'tipo' => 'Classificazione contabile del conto.',
    ],

    // Mensagens
    'messages' => [
        'created' => 'Conto creato con successo!',
        'updated' => 'Conto aggiornato con successo!',
        'deleted' => 'Conto eliminato con successo!',
        'saved' => 'Conto salvato con successo!',
        'not_found' => 'Conto non trovato.',
        'has_transactions' => 'Questo conto ha transazioni finanziarie e non può essere eliminato.',
        'hierarquia_required' => 'Il codice gerarchico è obbligatorio.',
        'hierarquia_exists' => 'Esiste già un conto con questo codice.',
        'tipo_invalid' => 'Tipo di conto non valido.',
        'descricao_required' => 'La descrizione in Portoghese (Brasile) è obbligatoria.',
        'cannot_edit_system' => 'I conti di sistema non possono essere modificati.',
        'cannot_delete_system' => 'I conti di sistema non possono essere eliminati.',
        'system_readonly' => 'Questo è un conto di sistema e non può essere modificato.',
        'no_records' => 'Nessun conto trovato.',
        'translations_help' => 'Compila la descrizione in Portoghese (Brasile). Le altre lingue sono opzionali e useranno il valore pt_BR se lasciate vuote.',
        'error_list' => 'Errore nel listare i conti',
        'error_load' => 'Errore nel caricare il conto',
        'error_create' => 'Errore nella creazione del conto',
        'error_update' => 'Errore nell\'aggiornamento del conto',
        'error_delete' => 'Errore nell\'eliminazione del conto',
        'error_save' => 'Errore nel salvataggio del conto',
        'codigo_disponivel' => 'Codice disponibile',
        'codigo_em_uso' => 'Questo codice è già in uso',
        'codigo_sugerido' => 'Codice suggerito automaticamente',
        'conta_raiz' => 'Conto radice (livello principale)',
        'formato_invalido' => 'Formato non valido. Usa solo numeri e punti (es: 1.1.01)',
        'this_record' => 'questo conto',
    ],
];
