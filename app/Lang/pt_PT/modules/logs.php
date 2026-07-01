<?php

/**
 * Traducoes do modulo Logs do Sistema - Portugues (Portugal)
 */

return [
    'title' => 'Logs do Sistema',
    'search_placeholder' => 'Pesquisar log...',
    'tabs' => [
        'audit' => 'Auditoria',
        'messages' => 'Envios',
    ],
    'filters' => [
        'all_channels' => 'Todos os canais',
        'all_statuses' => 'Todos os estados',
    ],
    'table' => [
        'date' => 'Data',
        'user' => 'Utilizador',
        'message' => 'Mensagem',
        'ip' => 'IP',
        'actions' => 'Ações',
        'channel' => 'Canal',
        'recipient' => 'Destinatário',
        'status' => 'Estado',
        'error' => 'Erro',
        'processed_at' => 'Processado em',
    ],
    'channels' => [
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
    ],
    'status' => [
        'pending' => 'Pendente',
        'processing' => 'A processar',
        'sent' => 'Enviado',
        'failed' => 'Falhou',
        'skipped' => 'Ignorado',
    ],
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
        'showing_lazy' => 'A mostrar registos :start-:end',
    ],
    'no_records' => 'Nenhum log encontrado',
    'details_title' => 'Detalhes da Alteração',
    'payload_title' => 'Detalhes do Envio',
    'empty_value' => '(vazio)',
    'unrecognized_format' => 'Formato de dados não reconhecido.',
    'view_details' => 'Ver detalhes',
    'no_details' => 'Sem detalhes',
    'messages' => [
        'load_error' => 'Erro ao carregar logs',
        'server_error' => 'Erro ao ligar ao servidor',
        'sent_hint' => 'Estado enviado significa que o worker processou e o fornecedor aceitou a chamada; não confirma leitura ou entrega final no aparelho.',
    ],
];
