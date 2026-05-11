<?php

/**
 * Traduções do módulo Configurações Gerais - Português (Brasil)
 */

return [
    'title' => 'Configurações Gerais',

    // Seções
    'sections' => [
        'locale' => 'Localização e Formatação',
        'notifications' => 'Notificações',
        'print' => 'Impressão',
        'sequences' => 'Sequências de Numeração',
        'sequences_desc' => 'Defina o próximo número a ser usado em cada tipo de documento. O valor não pode ser menor que o atual.',
    ],

    // Campos
    'fields' => [
        'locale' => 'Idioma',
        'currency' => 'Moeda',
        'date_format' => 'Formato de Data',
        'datetime_format' => 'Formato de Data/Hora',
        'notification_title' => 'Título das Notificações',
        'notification_title_placeholder' => 'Ex: Nome da sua locadora',
        'next_rental_number' => 'Próxima Locação',
        'next_contract_number' => 'Próximo Contrato',
        'next_financial_number' => 'Próximo Financeiro',
    ],

    // Notificações
    'notifications' => [
        'sms_title' => 'SMS',
        'sms_desc' => 'Enviar notificações por SMS',
        'email_title' => 'E-mail',
        'email_desc' => 'Enviar notificações por e-mail',
        'whatsapp_title' => 'WhatsApp',
        'whatsapp_desc' => 'Enviar notificações por WhatsApp',
    ],

    // Impressão
    'print' => [
        'bold_variables' => 'Variáveis em Negrito',
        'bold_variables_desc' => 'Destacar variáveis nos documentos impressos',
        'remove_yellow_stripe' => 'Remover Tarja Amarela',
        'remove_yellow_stripe_desc' => 'Remover destaque amarelo dos campos',
    ],

    // Mensagens
    'messages' => [
        'save_success' => 'Configurações salvas com sucesso!',
        'save_error' => 'Erro ao salvar configurações',
        'load_error' => 'Erro ao carregar configurações',
    ],
];
