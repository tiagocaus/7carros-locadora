<?php

/**
 * Traduções do módulo Configurações - Português (Brasil)
 */

return [
    // Templates de Mensagem (index)
    'templates_title' => 'Templates de Mensagem',
    'templates_description' => 'Personalize os templates de e-mail, WhatsApp e SMS enviados aos clientes.',

    // Categorias
    'categories' => [
        'all' => 'Todos',
        'onboarding' => 'Onboarding',
        'rental' => 'Locação',
        'reminder' => 'Lembretes',
        'billing' => 'Financeiro',
    ],

    // Category labels (usados em badges no JS)
    'category_labels' => [
        'onboarding' => 'Onboarding',
        'rental' => 'Locação',
        'reminder' => 'Lembrete',
        'billing' => 'Financeiro',
    ],

    // Editar template
    'edit_title' => 'Editar Template',
    'edit_title_prefix' => 'Editar template:',

    // Labels
    'labels' => [
        'customized' => 'Customizado',
        'using_default' => 'Usando padrão do sistema',
        'email_subject' => 'Assunto do e-mail',
        'content' => 'Conteúdo',
        'characters' => 'caracteres',
        'available_variables' => 'Variáveis Disponíveis',
        'click_to_insert' => 'Clique para inserir no editor',
        'subject' => 'Assunto:',
        'no_subject' => '(sem assunto)',
        'content_label' => 'Conteúdo:',
    ],

    // Placeholders
    'placeholders' => [
        'email_subject' => 'Ex: Confirmação de Locação #{{locação.número}}',
        'message_content' => 'Digite o conteúdo da mensagem...',
    ],

    // Warnings
    'warnings' => [
        'sms_split' => 'SMS com mais de 160 caracteres será dividido',
    ],

    // Botões
    'buttons' => [
        'preview' => 'Preview',
        'restore_default' => 'Restaurar Padrão',
    ],

    // Modais
    'modals' => [
        'attention' => 'Atenção',
        'unsaved_changes' => 'Você tem alterações não salvas. Deseja continuar?',
        'continue' => 'Continuar',
        'restore_title' => 'Restaurar Template Padrão',
        'restore_confirm' => 'Tem certeza que deseja restaurar este template para o padrão do sistema?',
        'restore_warning' => 'Suas customizações serão perdidas.',
        'restore_btn' => 'Restaurar',
        'preview_title' => 'Preview do Template',
        'close' => 'Fechar',
    ],

    // Mensagens
    'messages' => [
        'loading' => 'Carregando templates...',
        'loading_page' => 'Carregando...',
        'load_error' => 'Erro ao carregar templates.',
        'no_templates' => 'Nenhum template encontrado.',
        'no_variables' => 'Nenhuma variável disponível',
        'saving' => 'Salvando...',
        'save_success' => 'Template salvo com sucesso!',
        'save_error' => 'Erro ao salvar template',
        'preview_error' => 'Erro ao gerar preview',
        'restoring' => 'Restaurando...',
        'restore_success' => 'Template restaurado para o padrão do sistema',
        'restore_error' => 'Erro ao restaurar template',
    ],
];
