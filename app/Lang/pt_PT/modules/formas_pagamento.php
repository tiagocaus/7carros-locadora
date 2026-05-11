<?php

/**
 * Traduções do módulo Formas de Pagamento - Português (Portugal)
 */

return [
    // Títulos
    'title' => 'Formas de Pagamento',
    'title_singular' => 'Forma de Pagamento',
    'new_title' => 'Nova Forma de Pagamento',
    'edit_title' => 'Editar Forma de Pagamento',

    // Seções
    'sections' => [
        'payment_data' => 'Dados da Forma de Pagamento',
        'penalty_interest' => 'Multa e Juros por Atraso',
        'billing_fees' => 'Taxas da Cobrança',
        'billing_fees_desc' => 'Configure as taxas que serão descontadas/adicionadas ao valor. Deixe 0,00 para desativar.',
        'early_discount' => 'Desconto por Antecipação',
        'early_discount_desc' => 'Configure um desconto para pagamentos realizados antes do vencimento. Deixe os valores a zero para desativar.',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'branches' => 'Filiais',
        'branches_hint' => 'Selecione em quais empresas esta forma de pagamento estará disponível.',
        'where_to_show' => 'Onde Exibir',
        'where_to_show_hint' => 'Selecione onde esta forma de pagamento estará disponível.',
        'post_as_paid' => 'Lançar como pago',
        'payment_gateways' => 'Gateways de Pagamento',
        'payment_gateways_hint' => 'Selecione os gateways de pagamento vinculados. Se nenhum gateway for selecionado, esta forma de pagamento não processará pagamentos online automaticamente.',
        'penalty_percent' => 'Multa (%)',
        'penalty_hint' => 'Percentual de multa aplicado em caso de atraso.',
        'interest_per_day' => 'Juros por Dia (%)',
        'interest_hint' => 'Percentual de juros cobrado por dia de atraso.',
        'fixed_fee_total' => 'Taxa Fixa Total',
        'fixed_fee_total_hint' => 'Valor fixo diluído entre as prestações.<br>Ex: R$ 10 em 2x = R$ 5 por prestação.',
        'fixed_fee_installment' => 'Taxa Fixa por Prestação',
        'fixed_fee_installment_hint' => 'Valor cobrado em cada prestação.<br>Ex: R$ 2,50 em 2x = R$ 5 total.',
        'percent_fee_installment' => 'Taxa % por Prestação',
        'percent_fee_installment_hint' => 'Percentual sobre cada prestação.<br>Ex: 5% de R$ 100 = R$ 5 por prestação.',
        'days_before_due' => 'Dias Antes do Vencimento',
        'days_before_due_hint' => 'Quantidade de dias antes do vencimento para aplicar o desconto.',
        'discount_percent' => 'Desconto (%)',
        'discount_percent_hint' => 'Percentual de desconto.<br>Ex: 3% de R$ 100 = R$ 3 de desconto.',
    ],

    // Opções onde exibir
    'where_options' => [
        'site' => 'Site',
        'system' => 'Sistema',
        'app' => 'Aplicativo',
        'all' => 'Todos',
    ],

    // Tabela
    'table' => [
        'name' => 'Nome',
        'fees' => 'Taxas',
        'early_discount' => 'Desconto Antecip.',
        'post_as_paid' => 'Lançar Pago',
        'status' => 'Estado',
        'actions' => 'Ações',
    ],

    // Ações
    'actions' => [
        'new' => 'Novo',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'installment_commands' => 'Comandos de Prestações',
    ],

    // Badges e labels
    'badges' => [
        'fixed' => 'Fixa',
        'fixed_installment' => 'Fixa/prest',
        'percent_installment' => '%/prest',
        'no_fees' => 'Sem taxas',
        'yes' => 'Sim',
        'no' => 'Não',
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'no_name' => 'Sem nome',
        'in_days' => 'em :daysd',
    ],

    // Dropdowns
    'dropdowns' => [
        'select_branches' => 'Selecione as filiais...',
        'loading_branches' => 'A carregar filiais...',
        'error_loading_branches' => 'Erro ao carregar filiais',
        'error_loading' => 'Erro ao carregar',
        'no_branches' => 'Nenhuma filial registada',
        'no_branches_short' => 'Nenhuma filial',
        'no_gateway_selected' => 'Nenhum gateway selecionado (opcional)',
        'loading_gateways' => 'A carregar gateways...',
        'error_loading_gateways' => 'Erro ao carregar gateways',
        'no_gateways' => 'Nenhum gateway registado',
        'no_gateways_available' => 'Nenhum gateway disponível',
        'no_active_gateways' => 'Nenhum gateway ativo registado',
        'select' => 'Selecione...',
    ],

    // Exemplo de desconto
    'discount_example' => [
        'label' => 'Exemplo:',
        'text' => 'Pagando :days dias antes do vencimento, uma prestação de R$ :amount terá desconto de :percent% (R$ :discount), ficando R$ :final.',
    ],

    // Mensagens
    'messages' => [
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'no_records' => 'Nenhuma forma de pagamento encontrada',
        'delete_error' => 'Erro ao eliminar registo',
        'delete_confirm' => 'Deseja eliminar a forma de pagamento ":name"?',
        'this_record' => 'esta forma de pagamento',
        'not_found' => 'Registo não encontrado',
        'name_required' => 'Nome é obrigatório',
        'branches_required' => 'Por favor, selecione pelo menos uma filial',
        'save_success' => 'Guardado com sucesso',
        'save_error' => 'Erro ao guardar',
        'saving' => 'A guardar...',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar forma...',
    ],

    // Tipo de registro
    'record_type' => 'forma_pagamento',

    // ===== Comandos de Prestações =====
    'commands' => [
        'title' => 'Comandos de Prestações',
        'new_title' => 'Novo Comando',
        'edit_title' => 'Editar Comando',

        // Campos
        'fields' => [
            'command' => 'Comando',
            'command_hint' => 'Exemplos de uso:<br><br> <b>0</b> - Pagamento à vista. <br><br> <b>15</b> - Pagamento para daqui a 15 dias. <br><br> <b>1-12</b> - Gera prestação mensal de 1 a 12x. <br><br> <b>7/14/21/28</b> - Nesse exemplo é gerado 4 prestações com prazos estabelecidos. <br><br> <b>Dom, Seg, Ter, Qua, Qui, Sex, Sab</b> - Informe qual o dia da semana será o vencimento. <br><br> <b>d5, d10, d15, ...</b> - Qual dia do mês será o vencimento.<br><br> <b>w36</b> - Será criado 36 prestações semanais.<br><br> <b>w36-Seg</b> - Será criado 36 prestações semanais com o vencimento toda Segunda-feira.',
            'description' => 'Descrição',
            'active' => 'Ativo',
        ],

        // Tabela
        'table' => [
            'command' => 'Comando',
            'description' => 'Descrição',
            'origin' => 'Origem',
            'status' => 'Estado',
            'actions' => 'Ações',
        ],

        // Badges
        'badges' => [
            'system' => 'Sistema',
            'custom' => 'Personalizado',
            'system_command' => 'Comando do sistema',
        ],

        // Ações
        'actions' => [
            'new' => 'Novo Comando',
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ],

        // Placeholders
        'placeholders' => [
            'search' => 'Pesquisar comando...',
            'command' => 'Ex: 0, 1-12, 7/14/21/28',
            'description' => 'Descrição opcional do comando',
        ],

        // Mensagens
        'messages' => [
            'no_records' => 'Nenhum comando de prestação encontrado',
            'load_error' => 'Erro ao carregar dados',
            'server_error' => 'Erro ao conectar com o servidor',
            'command_required' => 'O campo Comando é obrigatório.',
            'save_success' => 'Comando guardado com sucesso!',
            'save_error' => 'Erro ao guardar comando.',
            'load_command_error' => 'Erro ao carregar comando',
            'not_found' => 'Registo não encontrado',
            'delete_error' => 'Erro ao eliminar registo.',
            'delete_confirm' => 'Deseja eliminar o comando ":name"?',
            'this_record' => 'este comando',
        ],

        // Paginação
        'pagination' => [
            'rows_per_page' => 'Registos por página:',
            'showing' => 'A mostrar :start-:end de :total registos',
        ],
    ],
];
