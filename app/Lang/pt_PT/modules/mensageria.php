<?php

/**
 * Traduções do módulo Mensageria - Português (Portugal)
 */

return [
    'title' => 'Mensageria WhatsApp, SMS e SMTP',
    'subtitle' => 'Mensageria: WhatsApp, SMS e SMTP(Mail)',

    // Tipos de conexao
    'types' => [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'smtp' => 'SMTP (Mail)',
    ],

    // Comum (partilhado entre sub-views)
    'common' => [
        'connection' => 'Conexão',
        'branches_label' => 'Empresas/Filiais',
        'branches_desc' => 'Selecione as empresas que utilizarao esta conexão',
        'no_branches' => 'Nenhuma empresa disponível',
        'already_linked' => 'Já vinculada',
        'none' => 'Nenhuma',
        'load_error' => 'Erro ao carregar dados',
        'load_branches_error' => 'Erro ao carregar empresas',
        'load_connection_error' => 'Erro ao carregar conexão',
        'fill_required' => 'Preencha todos os campos obrigatórios',
        'select_branch' => 'Selecione pelo menos uma empresa',
        'connection_id_missing' => 'ID da conexão não informado',
    ],

    // Tabela
    'table' => [
        'type' => 'Tipo',
        'linked_branches' => 'Empresas Vinculadas',
        'identifier' => 'Identificador',
        'status' => 'Estado',
        'actions' => 'Ações',
        'no_records' => 'Nenhuma conexão encontrada',
        'load_error_branches' => 'Erro ao carregar',
    ],

    // Botoes
    'buttons' => [
        'new_whatsapp' => 'Novo WhatsApp',
        'new_sms' => 'Novo SMS',
        'new_smtp' => 'Novo SMTP',
    ],

    // Pesquisa
    'search_placeholder' => 'Pesquisar conexão...',

    // Paginacao
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Estado badges
    'status' => [
        'connected' => 'Ligado',
        'connecting' => 'A ligar',
        'disconnected' => 'Desligado',
        'validated' => 'Validado',
        'pending' => 'Pendente',
        'invalid' => 'Inválido',
        'unknown' => 'Desconhecido',
    ],

    // Titulos de acoes (botoes na tabela)
    'actions' => [
        'test' => 'Testar',
        'restart' => 'Reiniciar',
        'disconnect' => 'Desligar',
        'connect' => 'Ligar',
        'recreate' => 'Recriar conexão',
        'test_sms' => 'Testar SMS',
        'check_balance' => 'Consultar Saldo',
        'validate_credentials' => 'Validar Credenciais',
        'test_email' => 'Testar Email',
        'validate_connection' => 'Validar Conexão',
    ],

    // Titulos de offcanvas
    'offcanvas' => [
        'new_whatsapp' => 'Nova Conexão WhatsApp',
        'edit_whatsapp' => 'Editar Conexão WhatsApp',
        'connect_whatsapp' => 'Ligar WhatsApp',
        'test_whatsapp' => 'Testar WhatsApp',
        'new_sms' => 'Nova Conexão SMS',
        'edit_sms' => 'Editar Conexão SMS',
        'test_sms' => 'Testar SMS',
        'new_smtp' => 'Nova Conexão SMTP',
        'edit_smtp' => 'Editar Conexão SMTP',
        'test_smtp' => 'Testar SMTP',
    ],

    // Confirmacoes
    'confirms' => [
        'delete' => 'Deseja eliminar a conexão ":name"?',
        'disconnect' => 'Deseja realmente desligar esta conexão?',
        'restart' => 'Deseja reiniciar esta conexão? A conexão será restabelecida.',
    ],

    // Mensagens
    'messages' => [
        // SMTP
        'smtp_created' => 'Conexão SMTP criada com sucesso!',
        'smtp_updated' => 'Conexão atualizada com sucesso!',
        'smtp_deleted' => 'Conexão SMTP eliminada com sucesso',
        'smtp_validated' => 'Conexão SMTP validada com sucesso!',
        'smtp_validation_failed' => 'Falha na validação',
        'smtp_create_error' => 'Erro ao criar conexão',
        'smtp_update_error' => 'Erro ao atualizar',
        'smtp_delete_error' => 'Erro ao eliminar conexão',
        'smtp_validate_error' => 'Erro ao validar',

        // WhatsApp
        'whatsapp_created' => 'Conexão criada! Digitalize o QR Code para ligar.',
        'whatsapp_created_short' => 'Conexão criada! Digitalize o QR Code.',
        'whatsapp_updated' => 'Conexão atualizada com sucesso!',
        'whatsapp_deleted' => 'Conexão WhatsApp eliminada com sucesso',
        'whatsapp_disconnected' => 'Desligado com sucesso',
        'whatsapp_restarted' => 'Conexão reiniciada. Aguarde a reconexão...',
        'whatsapp_recreated' => 'Instância recriada! A abrir QR Code...',
        'whatsapp_disconnect_error' => 'Erro ao desligar',
        'whatsapp_restart_error' => 'Erro ao reiniciar',
        'whatsapp_recreate_error' => 'Erro ao recriar',
        'whatsapp_create_error' => 'Erro ao criar conexão',
        'whatsapp_update_error' => 'Erro ao atualizar conexão',
        'whatsapp_delete_error' => 'Erro ao eliminar conexão',

        // SMS
        'sms_created' => 'Conexão SMS criada com sucesso!',
        'sms_updated' => 'Conexão SMS atualizada com sucesso!',
        'sms_deleted' => 'Conexão SMS eliminada com sucesso',
        'sms_validated' => 'Credenciais validadas com sucesso!',
        'sms_validation_failed' => 'Credenciais inválidas',
        'sms_create_error' => 'Erro ao criar conexão',
        'sms_update_error' => 'Erro ao atualizar conexão',
        'sms_delete_error' => 'Erro ao eliminar conexão',
        'sms_validate_error' => 'Erro ao validar',
        'sms_balance' => 'Saldo: :currency :balance',
        'sms_balance_error' => 'Erro ao consultar saldo',

        // Testes
        'test_sent' => 'Teste enviado!',
        'test_success' => 'Enviado com sucesso!',
        'test_error' => 'Erro ao enviar',
        'email_sent' => 'Email enviado!',
        'email_test_success' => 'Email de teste enviado com sucesso!',
        'email_test_error' => 'Falha ao enviar email de teste',
        'email_test_send_error' => 'Erro ao enviar email de teste',
        'sms_sent' => 'SMS enviado!',
        'sms_test_success' => 'SMS de teste enviado com sucesso!',
        'sms_test_error' => 'Falha ao enviar SMS de teste',
        'sms_test_send_error' => 'Erro ao enviar SMS de teste',
        'provide_email' => 'Indique um email para teste',
        'provide_valid_email' => 'Indique um email valido',
        'provide_phone' => 'Indique um telemovel para teste',
        'provide_valid_phone' => 'Indique um telemovel valido',
        'sending_email' => 'A enviar email...',
        'sending_sms' => 'A enviar SMS...',

        // QR Code
        'qr_generating' => 'A gerar QR Code...',
        'qr_scan' => 'Digitalize o QR Code com o seu WhatsApp',
        'qr_error' => 'Erro ao gerar QR Code',
        'qr_connect_error' => 'Erro ao ligar',
        'qr_waiting' => 'A aguardar conexão...',
        'qr_connected' => 'Ligado!',
        'server_error' => 'Erro ao ligar ao servidor',
    ],

    // SMTP especifico
    'smtp' => [
        'provider' => 'Fornecedor',
        'connection_name' => 'Nome da Conexão',
        'server' => 'Servidor SMTP',
        'port' => 'Porta',
        'encryption' => 'Encriptação',
        'encryption_none' => 'Nenhuma',
        'auth_email' => 'Email de Autenticação',
        'password' => 'Palavra-passe / App Password',
        'from_email' => 'Email Remetente',
        'from_name' => 'Nome Remetente',
        'reply_to' => 'Email de Resposta (opcional)',
        'daily_limit' => 'Limite Diário (opcional)',
        'daily_limit_hint' => 'Deixe vazio para sem limite',
        'password_hint_gmail' => 'Para Gmail, utilize uma <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="text-blue-600 hover:underline">palavra-passe de aplicação</a>',
        'password_hint_custom' => 'Consulte a documentação do seu fornecedor SMTP',
        'password_hint_default' => 'Utilize a palavra-passe ou App Password do fornecedor',
        'password_change_hint' => 'Alterar a palavra-passe irá revalidar a conexão',
        'keep_blank' => 'Deixe em branco para manter',
        'provider_settings' => 'Configurações do fornecedor:',
        'create_validate' => 'Criar e Validar Conexão',
        'test_email_label' => 'Email para teste',
        'test_email_hint' => 'Um email de teste será enviado para este endereço',
        'send_test' => 'Enviar Email de Teste',
    ],

    // Placeholders SMTP
    'smtp_placeholders' => [
        'name' => 'Ex: Email Principal',
        'server' => 'smtp.oseuservidor.com',
        'auth_email' => 'seu@email.com',
        'password' => 'Palavra-passe ou palavra-passe de aplicação',
        'from_email' => 'noreply@suaempresa.com',
        'from_name' => 'A Sua Empresa',
        'reply_to' => 'contacto@suaempresa.com',
        'daily_limit' => 'Ex: 500',
    ],

    // WhatsApp especifico
    'whatsapp' => [
        'create_connection' => 'Criar Conexão WhatsApp',
        'send_text' => 'Enviar Texto',
        'send_image' => 'Enviar Imagem',
        'send_document' => 'Enviar Documento',
        'instance_label' => 'Instância',
    ],

    // SMS especifico
    'sms' => [
        'provider' => 'Fornecedor',
        'sender_id' => 'Sender ID (Remetente)',
        'sender_id_hint' => 'Max 11 caracteres alfanumericos',
        'username' => 'Username ClickSend',
        'api_key' => 'API Key',
        'api_credentials_hint' => 'Encontre em: ClickSend Dashboard > Developers > API Credentials',
        'api_key_change_hint' => 'Alterar a API Key irá revalidar as credenciais',
        'create_validate' => 'Criar e Validar',
        'test_phone_label' => 'Telemovel para teste',
        'test_phone_hint' => 'Formato: código do país + indicativo + número',
        'test_phone_placeholder' => '351 912 345 678',
        'send_test' => 'Enviar SMS de Teste',
        'sender_id_short' => 'Sender ID',
    ],
];
