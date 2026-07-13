<?php

/**
 * Traduções de Templates de Mensagem - Português (Portugal)
 *
 * Contém os nomes e descrições dos tipos de templates disponíveis.
 */

return [
    'installment' => [
        'with_total' => 'Prestação :parcela de :total',
        'without_total' => 'Prestação :parcela',
    ],
    // Tipos de Templates
    'types' => [
        // Onboarding
        'welcome' => 'Boas-vindas',
        'welcome_description' => 'Mensagem enviada ao registar um novo cliente',
        'welcome_desc' => 'Mensagem enviada ao registar um novo cliente',

        'cliente_nova_senha' => 'Redefinição de palavra-passe do cliente',
        'cliente_nova_senha_desc' => 'Enviada ao cliente com uma nova palavra-passe de acesso',
        'cliente_nova_senha_link_desc' => 'Enviada ao cliente com uma ligação segura para redefinir a palavra-passe',

        'funcionario_nova_senha' => 'Redefinição de palavra-passe do funcionário',
        'funcionario_nova_senha_desc' => 'Enviada ao funcionário com uma nova palavra-passe segura de acesso ao painel',

        // Aluguer
        'rental_confirmation' => 'Confirmação de Aluguer',
        'rental_confirmation_description' => 'Enviada quando um aluguer é confirmado',

        'contract_confirmation' => 'Confirmação de Contrato',
        'contract_confirmation_description' => 'Enviada quando um contrato é assinado',

        // Lembretes
        'return_reminder' => 'Lembrete de Devolução',
        'return_reminder_description' => 'Aviso antes da data de devolução prevista',

        'cnh_expiring' => 'Carta de Condução a Expirar',
        'cnh_expiring_description' => 'Aviso quando a carta de condução do cliente está próxima do vencimento',

        // Financeiro
        'payment_reminder' => 'Lembrete de Pagamento',
        'payment_reminder_description' => 'Aviso de fatura próxima do vencimento',

        'invoice_generated' => 'Fatura Gerada',
        'invoice_generated_description' => 'Enviada quando uma nova fatura é gerada',

        'overdue_notice' => 'Aviso de Atraso',
        'overdue_notice_description' => 'Notificação de fatura em atraso',

        'payment_received' => 'Pagamento Recebido',
        'payment_received_description' => 'Confirmação de recebimento de pagamento',

        // Outros
        'general_notification' => 'Notificação Geral',
        'general_notification_description' => 'Template para notificações diversas',
    ],

    // Categorias
    'categories' => [
        'onboarding' => 'Registo',
        'rental' => 'Aluguer',
        'reminder' => 'Lembretes',
        'billing' => 'Financeiro',
        'notification' => 'Notificações',
    ],

    // Canais
    'channels' => [
        'email' => 'E-mail',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    // Mensagens da UI
    'ui' => [
        'title' => 'Templates de Mensagem',
        'subtitle' => 'Personalize as mensagens enviadas aos clientes',
        'search_placeholder' => 'Pesquisar templates...',
        'select_template' => 'Selecione um template para editar',
        'available_variables' => 'Variáveis Disponíveis',
        'preview' => 'Pré-visualização',
        'editor' => 'Editor',
        'restore_default' => 'Restaurar Predefinição',
        'save_changes' => 'Guardar Alterações',
        'unsaved_changes' => 'Tem alterações não guardadas. Deseja sair?',
        'template_saved' => 'Template guardado com sucesso!',
        'template_restored' => 'Template restaurado para a predefinição',
        'no_templates' => 'Nenhum template disponível',
        'custom_template' => 'Personalizado',
        'default_template' => 'Predefinição',
        'subject' => 'Assunto',
        'content' => 'Conteúdo',
        'content_plain' => 'Conteúdo (texto simples)',
        'locale' => 'Idioma',
        'channel' => 'Canal',
        'insert_variable' => 'Clique para inserir',
    ],

    // Validação
    'validation' => [
        'entity_not_allowed' => 'A entidade ":entity" não é permitida neste template',
        'variable_not_found' => 'A variável ":variable" não existe',
        'content_required' => 'O conteúdo do template é obrigatório',
        'subject_required_email' => 'O assunto é obrigatório para templates de e-mail',
    ],
];
