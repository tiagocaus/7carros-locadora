<?php

/**
 * Traduções do módulo Configurações - Português (Portugal)
 */

return [
    'templates_title' => 'Modelos de Mensagem',
    'templates_description' => 'Personalize os modelos de email, WhatsApp e SMS enviados aos clientes.',

    'categories' => [
        'all' => 'Todos',
        'onboarding' => 'Onboarding',
        'rental' => 'Locação',
        'reminder' => 'Lembretes',
        'billing' => 'Financeiro',
    ],

    'category_labels' => [
        'onboarding' => 'Onboarding',
        'rental' => 'Locação',
        'reminder' => 'Lembrete',
        'billing' => 'Financeiro',
    ],

    'edit_title' => 'Editar Modelo',
    'edit_title_prefix' => 'Editar modelo:',

    'labels' => [
        'customized' => 'Personalizado',
        'using_default' => 'A utilizar predefinição do sistema',
        'email_subject' => 'Assunto do Email',
        'content' => 'Conteúdo',
        'characters' => 'caracteres',
        'available_variables' => 'Variáveis Disponíveis',
        'click_to_insert' => 'Clique para inserir no editor',
        'subject' => 'Assunto:',
        'no_subject' => '(sem assunto)',
        'content_label' => 'Conteúdo:',
    ],

    'placeholders' => [
        'email_subject' => 'Ex: Confirmação de Locação #{{locação.número}}',
        'message_content' => 'Escreva o conteúdo da mensagem...',
    ],

    'warnings' => [
        'sms_split' => 'SMS com mais de 160 caracteres será dividido',
    ],

    'buttons' => [
        'preview' => 'Pré-visualizar',
        'restore_default' => 'Restaurar Predefinição',
    ],

    'modals' => [
        'attention' => 'Atenção',
        'unsaved_changes' => 'Tem alterações não guardadas. Deseja continuar?',
        'continue' => 'Continuar',
        'restore_title' => 'Restaurar Modelo Predefinido',
        'restore_confirm' => 'Tem a certeza de que deseja restaurar este modelo para a predefinição do sistema?',
        'restore_warning' => 'As suas personalizações serão perdidas.',
        'restore_btn' => 'Restaurar',
        'preview_title' => 'Pré-visualização do Modelo',
        'close' => 'Fechar',
    ],

    'messages' => [
        'loading' => 'A carregar modelos...',
        'loading_page' => 'A carregar...',
        'load_error' => 'Erro ao carregar modelos.',
        'no_templates' => 'Nenhum modelo encontrado.',
        'no_variables' => 'Nenhuma variável disponível',
        'saving' => 'A guardar...',
        'save_success' => 'Modelo guardado com sucesso!',
        'save_error' => 'Erro ao guardar modelo',
        'preview_error' => 'Erro ao gerar pré-visualização',
        'restoring' => 'A restaurar...',
        'restore_success' => 'Modelo restaurado para a predefinição do sistema',
        'restore_error' => 'Erro ao restaurar modelo',
    ],
];
