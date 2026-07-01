<?php

/**
 * Traducoes do modulo Logs do Sistema - Portugues (Brasil)
 */

return [
    'title' => 'Logs do Sistema',
    'search_placeholder' => 'Buscar log...',
    'tabs' => [
        'audit' => 'Auditoria',
        'messages' => 'Envios',
    ],
    'filters' => [
        'all_channels' => 'Todos os canais',
        'all_statuses' => 'Todos os status',
    ],
    'table' => [
        'date' => 'Data',
        'user' => 'Usuário',
        'message' => 'Mensagem',
        'ip' => 'IP',
        'actions' => 'Ações',
        'channel' => 'Canal',
        'recipient' => 'Destinatário',
        'status' => 'Status',
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
        'processing' => 'Processando',
        'sent' => 'Enviado',
        'failed' => 'Falhou',
        'skipped' => 'Ignorado',
    ],
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
        'showing_lazy' => 'Mostrando registros :start-:end',
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
        'server_error' => 'Erro ao conectar com o servidor',
        'sent_hint' => 'Status enviado significa que o worker processou e o provedor aceitou a chamada; não confirma leitura ou entrega final no aparelho.',
    ],
];
