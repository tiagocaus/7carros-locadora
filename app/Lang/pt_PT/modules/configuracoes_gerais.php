<?php

/**
 * Traduções do módulo Configurações Gerais - Português (Portugal)
 */

return [
    'title' => 'Configurações Gerais',

    'sections' => [
        'locale' => 'Localização e Formatação',
        'notifications' => 'Notificações',
        'print' => 'Impressão',
        'sequences' => 'Sequências de Numeração',
        'sequences_desc' => 'Defina o próximo número a ser utilizado em cada tipo de documento. O valor não pode ser inferior ao atual.',
    ],

    'fields' => [
        'locale' => 'Idioma',
        'currency' => 'Moeda',
        'date_format' => 'Formato de Data',
        'datetime_format' => 'Formato de Data/Hora',
        'notification_title' => 'Título das Notificações',
        'notification_title_placeholder' => 'Ex: Nome da sua empresa de aluguer',
        'next_rental_number' => 'Próximo Aluguer',
        'next_contract_number' => 'Próximo Contrato',
        'next_financial_number' => 'Próximo Financeiro',
    ],

    'notifications' => [
        'sms_title' => 'SMS',
        'sms_desc' => 'Enviar notificações por SMS',
        'email_title' => 'E-mail',
        'email_desc' => 'Enviar notificações por e-mail',
        'whatsapp_title' => 'WhatsApp',
        'whatsapp_desc' => 'Enviar notificações por WhatsApp',
    ],

    'print' => [
        'bold_variables' => 'Variáveis em Negrito',
        'bold_variables_desc' => 'Destacar variáveis nos documentos impressos',
        'remove_yellow_stripe' => 'Remover Tarja Amarela',
        'remove_yellow_stripe_desc' => 'Remover destaque amarelo dos campos',
    ],

    'messages' => [
        'save_success' => 'Configurações guardadas com sucesso!',
        'save_error' => 'Erro ao guardar configurações',
        'load_error' => 'Erro ao carregar configurações',
    ],
];
