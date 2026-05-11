<?php

/**
 * Traduções do módulo Contas Bancárias - Português (Portugal)
 */

return [
    'title' => 'Contas Bancarias/Caixa',
    'title_singular' => 'Conta Bancaria/Caixa',
    'new_title' => 'Nova Conta',
    'edit_title' => 'Editar Conta',

    // Secções
    'sections' => [
        'account_data' => 'Dados da Conta',
        'bank_data' => 'Dados Bancarios',
        'notes' => 'Observações',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Estado',
        'bank' => 'Banco',
        'branch' => 'Agência',
        'account_number' => 'Número de Conta',
        'notes' => 'Observações',
    ],

    // Opcoes de tipo
    'type_options' => [
        'bank_account' => 'Conta Bancaria',
        'cash' => 'Caixa',
    ],

    // Badges
    'badges' => [
        'type_bank' => 'Bancaria',
        'type_cash' => 'Caixa',
        'status_active' => 'Ativo',
        'status_inactive' => 'Inativo',
    ],

    // Marcadores
    'placeholders' => [
        'search' => 'Pesquisar conta...',
        'name_example' => 'Ex: Caixa Principal, Millennium BCP',
        'bank_example' => 'Ex: Millennium BCP, Caixa Geral de Depositos',
        'branch_example' => 'Ex: 1234-5',
        'account_example' => 'Ex: 12345-6',
        'notes_example' => 'Informações adicionais sobre a conta...',
    ],

    // Tabela
    'table' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'bank' => 'Banco',
        'branch' => 'Agência',
        'account' => 'Conta',
        'status' => 'Estado',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma conta encontrada',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar contas',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar conta',
        'this_record' => 'esta conta',
        'not_found' => 'Conta não encontrada',
        'load_account_error' => 'Erro ao carregar dados da conta',
        'name_required' => 'Por favor, indique o nome da conta',
        'saving' => 'A guardar...',
        'save_error' => 'Erro ao guardar conta',
        'saved' => 'Conta guardada com sucesso',
    ],

    // Paginacao
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'conta',
];
