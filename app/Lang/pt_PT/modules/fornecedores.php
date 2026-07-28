<?php

/**
 * Traduções do módulo Fornecedores - Português (Portugal)
 */

return [
    'title' => 'Fornecedores',
    'title_singular' => 'Fornecedor',
    'new_title' => 'Novo Fornecedor',
    'edit_title' => 'Editar Fornecedor',

    // Secções
    'sections' => [
        'basic_data' => 'Dados Basicos',
        'address' => 'Morada',
        'investor' => 'Investidor',
        'observations' => 'Observações',
    ],

    // Campos
    'fields' => [
        'type' => 'Tipo',
        'cpf_cnpj' => 'NIF/NIPC',
        'cpf' => 'NIF',
        'cnpj' => 'NIPC',
        'name' => 'Nome',
        'company_name' => 'Denominação Social',
        'trade_name' => 'Nome Comercial',
        'rg' => 'CC',
        'state_registration' => 'Matrícula Estadual',
        'municipal_registration' => 'Matrícula Municipal',
        'email' => 'Email',
        'phone1' => 'Telemovel 1',
        'phone2' => 'Telemovel 2',
        'zip_code' => 'Código Postal',
        'street' => 'Rua',
        'number' => 'Número',
        'complement' => 'Complemento',
        'neighborhood' => 'Freguesia',
        'city' => 'Cidade',
        'state' => 'Distrito',
        'country' => 'País',
        'supplies_vehicles' => 'Fornece Veículos',
        'is_investor' => 'E Investidor?',
        'split_gateway' => 'Gateway para Split',
        'split_account_id' => 'ID Conta/Wallet',
        'pix_key' => 'Chave PIX',
        'pix_key_type' => 'Tipo de Chave PIX',
        'bank_code' => 'Código do Banco',
        'bank_branch' => 'Agência',
        'bank_account' => 'Conta',
        'bank_account_type' => 'Tipo de Conta',
        'portal_password' => 'Palavra-passe do portal',
        'portal_password_help' => 'Utilize pelo menos 8 caracteres. Ao editar, deixe em branco para manter a palavra-passe atual.',
    ],

    // Opcoes de tipo
    'type_options' => [
        'PJ' => 'Pessoa Colectiva',
        'PF' => 'Pessoa Singular',
    ],

    // Opcoes de gateway
    'gateway_options' => [
        'none' => 'Nenhum (manual)',
        'asaas' => 'Asaas',
        'gerencianet' => 'Gerencianet',
        'stripe' => 'Stripe',
        'inter' => 'Banco Inter',
    ],

    // Opcoes de tipo de chave PIX
    'pix_type_options' => [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'Email',
        'telefone' => 'Telemovel',
        'aleatoria' => 'Chave Aleatoria',
    ],

    // Opcoes de tipo de conta
    'account_type_options' => [
        'corrente' => 'A Ordem',
        'poupanca' => 'Poupanca',
    ],

    'commission_rules' => [
        'title' => 'Regras de comissão',
        'description' => 'A primeira linha, "Regra padrão", vale para todos os grupos do investidor quando não houver uma exceção específica. Para definir uma negociação diferente num grupo, clique em "Adicionar exceção por grupo".',
        'help' => 'A "Regra padrão" é a regra geral do investidor. Use-a quando esse investidor tiver a mesma comissão para todos os seus veículos, independentemente do grupo. Exemplo: se a regra padrão for 20% para a locadora, todos os veículos desse investidor usam essa regra, mesmo que estejam em grupos diferentes. Se algum grupo tiver uma negociação diferente, clique em "Adicionar exceção por grupo", escolha o grupo e informe a comissão específica. Nesse caso, o sistema usa primeiro a exceção do grupo; se não existir exceção, usa a regra padrão do investidor; se também não existir regra padrão, usa a regra cadastrada no grupo do veículo.',
        'add_group_rule' => 'Adicionar exceção por grupo',
        'default_rule' => 'Regra padrão',
        'group_rule' => 'Regra por grupo',
        'group_placeholder' => 'Seleccione o grupo',
        'type_placeholder' => 'Tipo de comissão',
        'value' => 'Valor',
        'remove' => 'Remover',
    ],

    // Marcadores
    'placeholders' => [
        'search' => 'Pesquisar...',
        'split_account' => 'Ex: wal_xxxx',
        'bank_code' => 'Ex: 001',
        'select' => 'Seleccione...',
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
        'cpf_cnpj' => 'NIF/NIPC',
        'phone' => 'Telemovel',
        'investor' => 'Investidor',
        'actions' => 'Acções',
    ],

    // Etiquetas
    'badges' => [
        'investor_yes' => 'Sim',
        'investor_no' => 'Não',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum registo encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar',
        'this_record' => 'este registo',
        'load_data_error' => 'Erro ao carregar dados',
        'load_supplier_error' => 'Erro ao carregar dados do fornecedor',
        'saving' => 'A guardar...',
        'save_error' => 'Erro ao guardar',
        'save_supplier_error' => 'Erro ao guardar fornecedor',
        'created' => 'Fornecedor criado com sucesso!',
        'updated' => 'Fornecedor actualizado com sucesso!',
    ],

    // Paginacao
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'fornecedor',
];
