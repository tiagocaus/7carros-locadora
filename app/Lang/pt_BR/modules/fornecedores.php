<?php

/**
 * Traduções do módulo Fornecedores - Português (Brasil)
 */

return [
    'title' => 'Fornecedores',
    'title_singular' => 'Fornecedor',
    'new_title' => 'Novo Fornecedor',
    'edit_title' => 'Editar Fornecedor',

    // Seções
    'sections' => [
        'basic_data' => 'Dados Basicos',
        'address' => 'Endereço',
        'investor' => 'Investidor',
        'observations' => 'Observações',
    ],

    // Campos
    'fields' => [
        'type' => 'Tipo',
        'cpf_cnpj' => 'CPF/CNPJ',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'name' => 'Nome',
        'company_name' => 'Razao Social',
        'trade_name' => 'Nome Fantasia',
        'rg' => 'RG',
        'state_registration' => 'Inscrição Estadual',
        'municipal_registration' => 'Inscrição Municipal',
        'email' => 'E-mail',
        'phone1' => 'Telefone 1',
        'phone2' => 'Telefone 2',
        'zip_code' => 'CEP',
        'street' => 'Rua',
        'number' => 'Número',
        'complement' => 'Complemento',
        'neighborhood' => 'Bairro',
        'city' => 'Cidade',
        'state' => 'Estado',
        'country' => 'País',
        'supplies_vehicles' => 'Fornece Veículos',
        'is_investor' => 'E Investidor?',
        'split_gateway' => 'Gateway para Split',
        'split_account_id' => 'ID Conta/Wallet',
        'pix_key' => 'Chave PIX',
        'pix_key_type' => 'Tipo da Chave PIX',
        'bank_code' => 'Código do Banco',
        'bank_branch' => 'Agência',
        'bank_account' => 'Conta',
        'bank_account_type' => 'Tipo de Conta',
    ],

    // Opções de tipo
    'type_options' => [
        'PJ' => 'Pessoa Juridica',
        'PF' => 'Pessoa Fisica',
    ],

    // Opções de gateway
    'gateway_options' => [
        'none' => 'Nenhum (manual)',
        'asaas' => 'Asaas',
        'gerencianet' => 'Gerencianet',
        'stripe' => 'Stripe',
        'inter' => 'Banco Inter',
    ],

    // Opções de tipo PIX
    'pix_type_options' => [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'aleatoria' => 'Chave Aleatoria',
    ],

    // Opções de tipo de conta
    'account_type_options' => [
        'corrente' => 'Corrente',
        'poupanca' => 'Poupanca',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar...',
        'split_account' => 'Ex: wal_xxxx',
        'bank_code' => 'Ex: 001',
        'select' => 'Selecione...',
    ],

    // Filtros
    'filters' => [
        'all' => 'Todos',
        'suppliers' => 'Fornecedores',
        'investors' => 'Investidores',
    ],

    // Tabela
    'table' => [
        'name' => 'Nome',
        'cpf_cnpj' => 'CPF/CNPJ',
        'phone' => 'Telefone',
        'investor' => 'Investidor',
        'actions' => 'Ações',
    ],

    // Badges
    'badges' => [
        'investor_yes' => 'Sim',
        'investor_no' => 'Não',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum registro encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir',
        'this_record' => 'este registro',
        'load_data_error' => 'Erro ao carregar dados',
        'load_supplier_error' => 'Erro ao carregar dados do fornecedor',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar',
        'save_supplier_error' => 'Erro ao salvar fornecedor',
        'created' => 'Fornecedor criado com sucesso!',
        'updated' => 'Fornecedor atualizado com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'fornecedor',
];
