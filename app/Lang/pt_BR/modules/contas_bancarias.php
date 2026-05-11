<?php

/**
 * Traduções do módulo Contas Bancárias - Português (Brasil)
 */

return [
    'title' => 'Contas Bancarias/Caixa',
    'title_singular' => 'Conta Bancaria/Caixa',
    'new_title' => 'Nova Conta',
    'edit_title' => 'Editar Conta',

    // Seções
    'sections' => [
        'account_data' => 'Dados da Conta',
        'bank_data' => 'Dados Bancarios',
        'notes' => 'Observações',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Status',
        'bank' => 'Banco',
        'branch' => 'Agência',
        'account_number' => 'Número da Conta',
        'notes' => 'Observações',
    ],

    // Opções de tipo
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

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar conta...',
        'name_example' => 'Ex: Caixa Principal, Banco do Brasil',
        'bank_example' => 'Ex: Banco do Brasil, Itau',
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
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma conta encontrada',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar contas',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir conta',
        'this_record' => 'esta conta',
        'not_found' => 'Conta não encontrada',
        'load_account_error' => 'Erro ao carregar dados da conta',
        'name_required' => 'Por favor, informe o nome da conta',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar conta',
        'saved' => 'Conta salva com sucesso',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'conta',
];
