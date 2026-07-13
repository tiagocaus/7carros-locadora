<?php

/**
 * Traduções de Templates de Mensagem - Português (Brasil)
 *
 * Contém os nomes e descrições dos tipos de templates disponíveis.
 */

return [
    'installment' => [
        'with_total' => 'Parcela :parcela de :total',
        'without_total' => 'Parcela :parcela',
    ],
    // Tipos de Templates
    'types' => [
        // Onboarding
        'welcome' => 'Boas-vindas',
        'welcome_description' => 'Mensagem enviada ao cadastrar um novo cliente',
        'welcome_desc' => 'Mensagem enviada ao cadastrar um novo cliente',

        'cliente_nova_senha' => 'Redefinição de senha do cliente',
        'cliente_nova_senha_desc' => 'Enviada ao cliente com uma nova senha de acesso',
        'cliente_nova_senha_link_desc' => 'Enviada ao cliente com link seguro para redefinir a senha',

        'funcionario_nova_senha' => 'Redefinição de senha do funcionário',
        'funcionario_nova_senha_desc' => 'Enviada ao funcionário com uma nova senha segura de acesso ao painel',

        // Locação
        'rental_confirmation' => 'Confirmação de Locação',
        'rental_confirmation_description' => 'Enviada quando uma locação é confirmada',

        'contract_confirmation' => 'Confirmação de Contrato',
        'contract_confirmation_description' => 'Enviada quando um contrato é assinado',

        'signature_request' => 'Pedido de Assinatura',
        'signature_request_description' => 'Enviada ao cliente com o link para assinatura digital',
        'signature_request_desc' => 'Enviada ao cliente com o link para assinatura digital',

        // Lembretes
        'return_reminder' => 'Lembrete de Devolução',
        'return_reminder_description' => 'Aviso antes da data de devolução prevista',

        'cnh_expiring' => 'CNH Vencendo',
        'cnh_expiring_description' => 'Aviso quando a CNH do cliente está próxima do vencimento',

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

        // Reserva vinda do site publico
        'pedido_reserva' => 'Pedido de Reserva',
        'pedido_reserva_description' => 'Enviada ao cliente quando ele faz um pedido de reserva no site',
        'pedido_reserva_desc' => 'Enviada ao cliente quando ele faz um pedido de reserva no site',
        'confirmacao_reserva' => 'Confirmação de Reserva',
        'confirmacao_reserva_description' => 'Enviada ao cliente quando a locadora confirma o pedido no painel',
        'confirmacao_reserva_desc' => 'Enviada ao cliente quando a locadora confirma o pedido no painel',
    ],

    // Categorias
    'categories' => [
        'onboarding' => 'Cadastro',
        'rental' => 'Locação',
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
        'search_placeholder' => 'Buscar templates...',
        'select_template' => 'Selecione um template para editar',
        'available_variables' => 'Variáveis Disponíveis',
        'preview' => 'Pré-visualização',
        'editor' => 'Editor',
        'restore_default' => 'Restaurar Padrão',
        'save_changes' => 'Salvar Alterações',
        'unsaved_changes' => 'Você tem alterações não salvas. Deseja sair?',
        'template_saved' => 'Template salvo com sucesso!',
        'template_restored' => 'Template restaurado para o padrão',
        'no_templates' => 'Nenhum template disponível',
        'custom_template' => 'Customizado',
        'default_template' => 'Padrão',
        'subject' => 'Assunto',
        'content' => 'Conteúdo',
        'content_plain' => 'Conteúdo (texto puro)',
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
