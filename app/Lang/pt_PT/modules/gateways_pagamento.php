<?php

/**
 * Traduções do módulo Gateways de Pagamento - Português (Portugal)
 */

return [
    'title' => 'Gateways de Pagamento',
    'title_singular' => 'Gateway de Pagamento',
    'new_title' => 'Novo Gateway de Pagamento',
    'edit_title' => 'Editar Gateway de Pagamento',

    // Secções
    'sections' => [
        'gateway_data' => 'Dados do Gateway',
        'payment_methods' => 'Métodos de Pagamento Activos',
        'payment_methods_desc' => 'Seleccione quais os métodos de pagamento que estarão disponíveis para este gateway.',
        'credentials' => 'Credenciais',
        'credentials_desc' => 'Configure as credenciais de acesso ao gateway.',
        'webhook' => 'Webhook',
        'webhook_desc' => 'Configure este URL no painel do gateway para receber notificações de pagamento.',
    ],

    // Campos
    'fields' => [
        'gateway' => 'Gateway',
        'name' => 'Nome de identificação',
        'branches' => 'Filiais',
        'currencies' => 'Moedas Aceites',
        'environment' => 'Ambiente',
        'status' => 'Estado',
        'display_order' => 'Ordem de apresentação',
        'methods' => 'Métodos',
        'webhook_url' => 'URL do Webhook',
    ],

    // Métodos de pagamento
    'methods' => [
        'pix' => 'PIX',
        'pix_desc' => 'Pagamento instantâneo',
        'boleto' => 'Boleto',
        'boleto_desc' => 'Boleto bancário',
        'credit_card' => 'Cartão de Crédito',
        'credit_card_desc' => 'Pagamento em prestações disponível',
        'debit_card' => 'Cartão de Débito',
        'debit_card_desc' => 'Débito em conta',
        'none' => 'Nenhum',
    ],

    // Ambiente
    'environment' => [
        'sandbox' => 'Sandbox (Teste)',
        'production' => 'Produção',
    ],

    // Estado
    'status_options' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Países
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
        'CAD' => 'Dólar Canadiano',
        'AUD' => 'Dólar Australiano',
        'JPY' => 'Iene Japonês',
        'MXN' => 'Peso Mexicano',
        'CHF' => 'Franco Suíço',
        'PYG' => 'Guarani Paraguaio',
        'ARS' => 'Peso Argentino',
        'CLP' => 'Peso Chileno',
        'COP' => 'Peso Colombiano',
        'PEN' => 'Sol Peruano',
        'UYU' => 'Peso Uruguaio',
    ],

    // Dicas
    'hints' => [
        'branches' => 'Deixe em branco para disponibilizar em todas as filiais.',
        'currencies' => 'Seleccione as moedas que este gateway aceita. As opções disponíveis dependem do gateway seleccionado.',
        'display_order' => 'O número mais baixo aparece primeiro na lista de opções.',
        'name_placeholder' => 'Ex: Asaas Principal, Stripe Produção',
    ],

    // Menus suspensos
    'dropdowns' => [
        'select_gateway' => 'Seleccione um gateway...',
        'select_gateway_first' => 'Seleccione primeiro um gateway',
        'all_branches' => 'Todas as filiais',
        'no_branches' => 'Nenhuma filial registada',
        'no_branches_short' => 'Nenhuma filial',
        'no_currencies' => 'Nenhuma moeda seleccionada',
        'load_error' => 'Erro ao carregar',
    ],

    // Tabela
    'table' => [
        'gateway' => 'Gateway',
        'branch' => 'Filial',
        'methods' => 'Métodos',
        'environment' => 'Ambiente',
        'status' => 'Estado',
        'actions' => 'Acções',
        'all_branches' => 'Todas',
    ],

    // Acções
    'actions' => [
        'test_connection' => 'Testar Ligação',
        'testing' => 'A testar...',
        'copy_url' => 'Copiar URL',
        'view_docs' => 'Ver documentação',
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
    ],

    // Marcadores de posição
    'placeholders' => [
        'search' => 'Pesquisar gateway...',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum gateway configurado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar registo',
        'status_error' => 'Erro ao alterar estado',
        'test_success' => 'Ligação bem-sucedida! Credenciais validadas.',
        'test_fail' => 'Falha na ligação. Verifique as credenciais.',
        'test_error' => 'Erro ao testar ligação',
        'not_found' => 'Registo não encontrado',
        'gateway_required' => 'Por favor, seleccione um gateway',
        'name_required' => 'Por favor, indique o nome de identificação',
        'currency_required' => 'Por favor, seleccione pelo menos uma moeda',
        'save_error' => 'Erro ao guardar',
        'save_success' => 'Guardado com sucesso',
        'load_branches_error' => 'Erro ao carregar filiais',
        'branch_fallback' => 'Filial :id',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo para modal de eliminação
    'record_type' => 'gateway_pagamento',
];
