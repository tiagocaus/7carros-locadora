<?php

/**
 * Traduções do módulo Mensageria - Português (Brasil)
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

    // Comum (compartilhado entre sub-views)
    'common' => [
        'connection' => 'Conexão',
        'branches_label' => 'Empresas/Filiais',
        'branches_desc' => 'Selecione as empresas que utilizarão esta conexão',
        'no_branches' => 'Nenhuma empresa disponível',
        'already_linked' => 'Já vinculada',
        'none' => 'Nenhuma',
        'load_error' => 'Erro ao carregar dados',
        'load_branches_error' => 'Erro ao carregar empresas',
        'load_connection_error' => 'Erro ao carregar conexão',
        'fill_required' => 'Preencha todos os campos obrigatorios',
        'select_branch' => 'Selecione pelo menos uma empresa',
        'connection_id_missing' => 'ID da conexão não informado',
    ],

    // Tabela
    'table' => [
        'type' => 'Tipo',
        'linked_branches' => 'Empresas Vinculadas',
        'identifier' => 'Identificador',
        'status' => 'Status',
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

    // Busca
    'search_placeholder' => 'Buscar conexão...',

    // Paginacao
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Status badges
    'status' => [
        'connected' => 'Conectado',
        'connecting' => 'Conectando',
        'disconnected' => 'Desconectado',
        'validated' => 'Validado',
        'pending' => 'Pendente',
        'invalid' => 'Inválido',
        'unknown' => 'Desconhecido',
    ],

    // Titulos de acoes (botoes na tabela)
    'actions' => [
        'test' => 'Testar',
        'restart' => 'Reiniciar',
        'disconnect' => 'Desconectar',
        'connect' => 'Conectar',
        'recreate' => 'Recriar conexão',
        'test_sms' => 'Testar SMS',
        'check_balance' => 'Consultar Saldo',
        'validate_credentials' => 'Validar Credenciais',
        'test_email' => 'Testar E-mail',
        'validate_connection' => 'Validar Conexão',
    ],

    // Titulos de offcanvas
    'offcanvas' => [
        'new_whatsapp' => 'Nova Conexão WhatsApp',
        'edit_whatsapp' => 'Editar Conexão WhatsApp',
        'connect_whatsapp' => 'Conectar WhatsApp',
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
        'delete' => 'Deseja excluir a conexão ":name"?',
        'disconnect' => 'Deseja realmente desconectar esta conexão?',
        'restart' => 'Deseja reiniciar esta conexão? A conexão será restabelecida.',
    ],

    // Mensagens
    'messages' => [
        // SMTP
        'smtp_created' => 'Conexão SMTP criada com sucesso!',
        'smtp_updated' => 'Conexão atualizada com sucesso!',
        'smtp_deleted' => 'Conexão SMTP excluída com sucesso',
        'smtp_validated' => 'Conexão SMTP validada com sucesso!',
        'smtp_validation_failed' => 'Falha na validação',
        'smtp_create_error' => 'Erro ao criar conexão',
        'smtp_update_error' => 'Erro ao atualizar',
        'smtp_delete_error' => 'Erro ao excluir conexão',
        'smtp_validate_error' => 'Erro ao validar',

        // WhatsApp
        'whatsapp_created' => 'Conexão criada! Escaneie o QR Code para conectar.',
        'whatsapp_created_short' => 'Conexão criada! Escaneie o QR Code.',
        'whatsapp_updated' => 'Conexão atualizada com sucesso!',
        'whatsapp_deleted' => 'Conexão WhatsApp excluída com sucesso',
        'whatsapp_disconnected' => 'Desconectado com sucesso',
        'whatsapp_restarted' => 'Conexão reiniciada. Aguarde a reconexão...',
        'whatsapp_recreated' => 'Instância recriada! Abrindo QR Code...',
        'whatsapp_disconnect_error' => 'Erro ao desconectar',
        'whatsapp_restart_error' => 'Erro ao reiniciar',
        'whatsapp_recreate_error' => 'Erro ao recriar',
        'whatsapp_create_error' => 'Erro ao criar conexão',
        'whatsapp_update_error' => 'Erro ao atualizar conexão',
        'whatsapp_delete_error' => 'Erro ao excluir conexão',

        // SMS
        'sms_created' => 'Conexão SMS criada com sucesso!',
        'sms_updated' => 'Conexão SMS atualizada com sucesso!',
        'sms_deleted' => 'Conexão SMS excluída com sucesso',
        'sms_validated' => 'Credenciais validadas com sucesso!',
        'sms_validation_failed' => 'Credenciais inválidas',
        'sms_create_error' => 'Erro ao criar conexão',
        'sms_update_error' => 'Erro ao atualizar conexão',
        'sms_delete_error' => 'Erro ao excluir conexão',
        'sms_validate_error' => 'Erro ao validar',
        'sms_balance' => 'Saldo: :currency :balance',
        'sms_balance_error' => 'Erro ao consultar saldo',

        // Testes
        'test_sent' => 'Teste enviado!',
        'test_success' => 'Enviado com sucesso!',
        'test_error' => 'Erro ao enviar',
        'email_sent' => 'E-mail enviado!',
        'email_test_success' => 'E-mail de teste enviado com sucesso!',
        'email_test_error' => 'Falha ao enviar e-mail de teste',
        'email_test_send_error' => 'Erro ao enviar e-mail de teste',
        'sms_sent' => 'SMS enviado!',
        'sms_test_success' => 'SMS de teste enviado com sucesso!',
        'sms_test_error' => 'Falha ao enviar SMS de teste',
        'sms_test_send_error' => 'Erro ao enviar SMS de teste',
        'provide_email' => 'Informe um e-mail para teste',
        'provide_valid_email' => 'Informe um e-mail válido',
        'provide_phone' => 'Informe um telefone para teste',
        'provide_valid_phone' => 'Informe um telefone válido',
        'sending_email' => 'Enviando e-mail...',
        'sending_sms' => 'Enviando SMS...',

        // QR Code
        'qr_generating' => 'Gerando QR Code...',
        'qr_scan' => 'Escaneie o QR Code com seu WhatsApp',
        'qr_error' => 'Erro ao gerar QR Code',
        'qr_connect_error' => 'Erro ao conectar',
        'qr_waiting' => 'Aguardando conexão...',
        'qr_connected' => 'Conectado!',
        'server_error' => 'Erro ao conectar com o servidor',
    ],

    // SMTP especifico
    'smtp' => [
        'provider' => 'Provedor',
        'connection_name' => 'Nome da Conexão',
        'server' => 'Servidor SMTP',
        'port' => 'Porta',
        'encryption' => 'Criptografia',
        'encryption_none' => 'Nenhuma',
        'auth_email' => 'E-mail de Autenticação',
        'password' => 'Senha / App Password',
        'from_email' => 'E-mail remetente',
        'from_name' => 'Nome Remetente',
        'reply_to' => 'E-mail de resposta (opcional)',
        'daily_limit' => 'Limite Diário (opcional)',
        'daily_limit_hint' => 'Deixe vazio para sem limite',
        'password_hint_gmail' => 'Para Gmail, use uma <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="text-blue-600 hover:underline">senha de aplicativo</a>',
        'password_hint_custom' => 'Consulte a documentação do seu provedor SMTP',
        'password_hint_default' => 'Use a senha ou App Password do provedor',
        'password_change_hint' => 'Alterar a senha irá revalidar a conexão',
        'keep_blank' => 'Deixe em branco para manter',
        'provider_settings' => 'Configurações do provedor:',
        'create_validate' => 'Criar e Validar Conexão',
        'test_email_label' => 'E-mail para teste',
        'test_email_hint' => 'Um e-mail de teste será enviado para este endereço',
        'send_test' => 'Enviar e-mail de teste',
    ],

    // Placeholders SMTP
    'smtp_placeholders' => [
        'name' => 'Ex.: E-mail principal',
        'server' => 'smtp.seuservidor.com',
        'auth_email' => 'seu@email.com',
        'password' => 'Senha ou senha de aplicativo',
        'from_email' => 'noreply@suaempresa.com',
        'from_name' => 'Sua Empresa',
        'reply_to' => 'contato@suaempresa.com',
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
        'provider' => 'Provedor',
        'sender_id' => 'Sender ID (Remetente)',
        'sender_id_hint' => 'Max 11 caracteres alfanumericos',
        'username' => 'Username ClickSend',
        'api_key' => 'API Key',
        'api_credentials_hint' => 'Encontre em: ClickSend Dashboard > Developers > API Credentials',
        'api_key_change_hint' => 'Alterar a API Key irá revalidar as credenciais',
        'create_validate' => 'Criar e Validar',
        'test_phone_label' => 'Telefone para teste',
        'test_phone_hint' => 'Formato: código do país + DDD + número',
        'test_phone_placeholder' => '55 (11) 99999-9999',
        'send_test' => 'Enviar SMS de Teste',
        'sender_id_short' => 'Sender ID',
    ],
];
