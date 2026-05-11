<?php

/**
 * Traduções do módulo Gateways de Pagamento - Português (Brasil)
 */

return [
    'title' => 'Gateways de Pagamento',
    'title_singular' => 'Gateway de Pagamento',
    'new_title' => 'Novo Gateway de Pagamento',
    'edit_title' => 'Editar Gateway de Pagamento',

    // Secoes
    'sections' => [
        'gateway_data' => 'Dados do Gateway',
        'payment_methods' => 'Métodos de Pagamento Habilitados',
        'payment_methods_desc' => 'Selecione quais métodos de pagamento estarão disponíveis para este gateway.',
        'credentials' => 'Credenciais',
        'credentials_desc' => 'Configure as credenciais de acesso ao gateway.',
        'webhook' => 'Webhook',
        'webhook_desc' => 'Configure esta URL no painel do gateway para receber notificações de pagamento.',
    ],

    // Campos
    'fields' => [
        'gateway' => 'Gateway',
        'name' => 'Nome de identificação',
        'branches' => 'Filiais',
        'currencies' => 'Moedas Aceitas',
        'environment' => 'Ambiente',
        'status' => 'Status',
        'display_order' => 'Ordem de exibição',
        'methods' => 'Métodos',
        'webhook_url' => 'URL do Webhook',
    ],

    // Metodos de pagamento
    'methods' => [
        'pix' => 'PIX',
        'pix_desc' => 'Pagamento instantâneo',
        'boleto' => 'Boleto',
        'boleto_desc' => 'Boleto bancário',
        'credit_card' => 'Cartão de Crédito',
        'credit_card_desc' => 'Parcelamento disponível',
        'debit_card' => 'Cartão de Débito',
        'debit_card_desc' => 'Débito em conta',
        'none' => 'Nenhum',
    ],

    // Ambiente
    'environment' => [
        'sandbox' => 'Sandbox (Teste)',
        'production' => 'Produção',
    ],

    // Status
    'status_options' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'not_configured' => 'Não configurado',
    ],

    // Paises
    'countries' => [
        'BR' => 'Brasil',
        'PY' => 'Paraguai',
        'INTL' => 'Internacional',
    ],

    // Moedas
    'currencies' => [
        'BRL' => 'Real Brasileiro',
        'USD' => 'Dólar Americano',
        'EUR' => 'Euro',
        'GBP' => 'Libra Esterlina',
        'CAD' => 'Dólar Canadense',
        'AUD' => 'Dólar Australiano',
        'JPY' => 'Iene Japonês',
        'MXN' => 'Peso Mexicano',
        'CHF' => 'Franco Suíço',
        'PYG' => 'Guarani',
        'ARS' => 'Peso Argentino',
        'CLP' => 'Peso Chileno',
        'COP' => 'Peso Colombiano',
        'PEN' => 'Sol Peruano',
        'UYU' => 'Peso Uruguaio',
    ],

    // Dicas (avisos)
    'hints' => [
        'branches' => 'Deixe em branco para disponibilizar em todas as filiais.',
        'currencies' => 'Selecione as moedas que este gateway aceita. Opções disponíveis dependem do gateway selecionado.',
        'display_order' => 'Menor número aparece primeiro na lista de opções.',
        'name_placeholder' => 'Ex: Asaas Principal, Stripe Produção',
    ],

    // Dropdowns
    'dropdowns' => [
        'select_gateway' => 'Selecione um gateway...',
        'select_gateway_first' => 'Selecione um gateway primeiro',
        'all_branches' => 'Todas as filiais',
        'no_branches' => 'Nenhuma filial cadastrada',
        'no_branches_short' => 'Nenhuma filial',
        'no_currencies' => 'Nenhuma moeda selecionada',
        'load_error' => 'Erro ao carregar',
    ],

    // Tabela
    'table' => [
        'gateway' => 'Gateway',
        'branch' => 'Filial',
        'methods' => 'Métodos',
        'environment' => 'Ambiente',
        'status' => 'Status',
        'actions' => 'Ações',
        'all_branches' => 'Todas',
    ],

    // Acoes
    'actions' => [
        'test_connection' => 'Testar Conexão',
        'testing' => 'Testando...',
        'copy_url' => 'Copiar URL',
        'view_docs' => 'Ver documentação',
        'configure' => 'Configurar',
        'deactivate' => 'Desativar',
        'activate' => 'Ativar',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar gateway...',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum gateway configurado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir registro',
        'status_error' => 'Erro ao alterar status',
        'test_success' => 'Conexão bem-sucedida! Credenciais validadas.',
        'test_fail' => 'Falha na conexão. Verifique as credenciais.',
        'test_error' => 'Erro ao testar conexão',
        'not_found' => 'Registro não encontrado',
        'gateway_required' => 'Por favor, selecione um gateway',
        'name_required' => 'Por favor, informe o nome de identificação',
        'currency_required' => 'Por favor, selecione pelo menos uma moeda',
        'save_error' => 'Erro ao salvar',
        'save_success' => 'Salvo com sucesso',
        'load_branches_error' => 'Erro ao carregar filiais',
        'branch_fallback' => 'Filial :id',
    ],

    // Paginacao
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro para modal de exclusao
    'record_type' => 'gateway_pagamento',
];
