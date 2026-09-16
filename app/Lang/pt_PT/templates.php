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
        'confirmacao_reserva' => 'Confirmação de Reserva',
        'confirmacao_reserva_description' => 'Enviada ao cliente quando a empresa confirma o pedido no painel',
        'confirmacao_reserva_desc' => 'Enviada ao cliente quando a empresa confirma o pedido no painel',
        'pedido_reserva' => 'Pedido de Reserva',
        'pedido_reserva_description' => 'Enviada ao cliente quando faz um pedido de reserva no site',
        'pedido_reserva_desc' => 'Enviada ao cliente quando faz um pedido de reserva no site',
        'signature_request' => 'Pedido de Assinatura',
        'signature_request_description' => 'Enviada ao cliente com a ligação para assinatura digital',
        'signature_request_desc' => 'Enviada ao cliente com a ligação para assinatura digital',
        'reserva_nao_confirmada' => 'Reserva não confirmada',
        'reserva_nao_confirmada_desc' => 'Notificação opcional ao eliminar um pedido de reserva pendente',
        'reserva_nao_confirmada_description' => 'Notificação opcional ao eliminar um pedido de reserva pendente',
        // Onboarding
        'welcome' => 'Boas-vindas',
        'welcome_description' => 'Mensagem enviada ao registar um novo cliente',
        'welcome_desc' => 'Mensagem enviada ao registar um novo cliente',

        'cliente_nova_senha' => 'Redefinição de palavra-passe do cliente',
        'cliente_nova_senha_desc' => 'Enviada ao cliente com uma nova palavra-passe de acesso',
        'cliente_nova_senha_link_desc' => 'Enviada ao cliente com uma ligação segura para redefinir a palavra-passe',

        'funcionario_nova_senha' => 'Redefinição de palavra-passe do funcionário',
        'funcionario_nova_senha_desc' => 'Enviada ao funcionário com uma nova palavra-passe segura de acesso ao painel',
        'funcionario_nova_senha_link_desc' => 'Enviada ao funcionário com uma ligação segura para redefinir a palavra-passe',

        // Aluguer
        'rental_confirmation' => 'Confirmação de Aluguer',
        'rental_confirmation_description' => 'Enviada quando um aluguer é confirmado',
        'rental_confirmation_desc' => 'Enviada quando um aluguer é confirmado',

        'contract_confirmation' => 'Confirmação de Contrato',
        'contract_confirmation_description' => 'Enviada quando um contrato é assinado',
        'contract_confirmation_desc' => 'Enviada quando um contrato é assinado',

        // Lembretes
        'return_reminder' => 'Lembrete de Devolução',
        'return_reminder_description' => 'Aviso antes da data de devolução prevista',
        'return_reminder_desc' => 'Aviso antes da data de devolução prevista',

        'cnh_expiring' => 'Carta de Condução a Expirar',
        'cnh_expiring_description' => 'Aviso quando a carta de condução do cliente está próxima do vencimento',
        'cnh_expiring_desc' => 'Aviso quando a carta de condução do cliente está próxima do vencimento',

        // Financeiro
        'payment_reminder' => 'Lembrete de Pagamento',
        'payment_reminder_description' => 'Aviso de fatura próxima do vencimento',
        'payment_reminder_desc' => 'Aviso de fatura próxima do vencimento',

        'invoice_generated' => 'Fatura Gerada',
        'invoice_generated_description' => 'Enviada quando uma nova fatura é gerada',
        'invoice_generated_desc' => 'Enviada quando uma nova fatura é gerada',

        'overdue_notice' => 'Aviso de Atraso',
        'overdue_notice_description' => 'Notificação de fatura em atraso',
        'overdue_notice_desc' => 'Notificação de fatura em atraso',

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
