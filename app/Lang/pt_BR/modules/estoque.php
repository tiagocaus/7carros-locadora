<?php

/**
 * Traduções do módulo Estoque - Português (Brasil)
 */

return [
    'title' => 'Estoque',
    'title_singular' => 'Produto',
    'new_title' => 'Novo Produto',
    'edit_title' => 'Editar Produto',

    // Seções
    'sections' => [
        'product_data' => 'Dados do Produto',
        'stock' => 'Estoque',
        'values' => 'Valores',
    ],

    // Campos
    'fields' => [
        'code' => 'Código',
        'name' => 'Nome',
        'brand' => 'Marca',
        'model' => 'Modelo',
        'unit' => 'Unidade',
        'storage_location' => 'Local de Armazenamento',
        'branch' => 'Matriz/Filial',
        'supplier' => 'Fornecedor',
        'current_stock' => 'Estoque Atual',
        'minimum_stock' => 'Estoque Mínimo',
        'purchase_value' => 'Valor de Compra',
        'sale_value' => 'Valor de Venda',
        'auto_deduct' => 'Baixa automática',
        'auto_deduct_enable' => 'Ativar',
        'allow_negative_stock' => 'Permitir estoque negativo',
        'allow_negative_stock_enable' => 'Ativar',
    ],

    // Opções de unidade
    'unit_options' => [
        'UN' => 'UN - Unidade',
        'PC' => 'PC - Peca',
        'CX' => 'CX - Caixa',
        'KG' => 'KG - Quilograma',
        'L' => 'L - Litro',
        'M' => 'M - Metro',
        'M2' => 'M2 - Metro Quadrado',
        'M3' => 'M3 - Metro Cubico',
        'JG' => 'JG - Jogo',
        'KIT' => 'KIT - Kit',
        'PAR' => 'PAR - Par',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar...',
        'select' => 'Selecione...',
        'storage_location' => 'Ex: Prateleira A3',
        'search_branch' => 'Digite para buscar...',
        'search_supplier' => 'Digite para buscar...',
        'none' => 'Nenhum',
    ],

    // Status
    'status' => [
        'label' => 'Status',
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ],

    // Filtros
    'filters' => [
        'all_branches' => 'Todas filiais',
        'all_status' => 'Todos os status',
    ],

    // Avisos (tooltips)
    'tooltips' => [
        'minimum_stock' => 'Alerta quando atingir este valor. 0 = desativado.',
        'auto_deduct' => 'Quando ativado, o estoque será decrementado automaticamente ao usar este produto em uma OS de manutenção.',
        'allow_negative_stock' => 'Quando ativado, permite usar o produto mesmo sem estoque disponível. Quando desativado, impede a seleção com estoque zerado e limita a quantidade ao disponível.',
    ],

    // Tabela
    'table' => [
        'code' => 'Código',
        'product' => 'Produto',
        'brand_model' => 'Marca/Modelo',
        'unit' => 'Unidade',
        'stock' => 'Estoque',
        'purchase_value' => 'Valor Compra',
        'branch' => 'Filial',
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum registro encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir',
        'inactivated' => 'Produto inativado. Possui vínculo com manutenção e não pode ser excluído.',
        'reactivated' => 'Produto reativado com sucesso!',
        'already_inactive' => 'Produto já está inativo',
        'reactivate_error' => 'Erro ao reativar',
        'this_record' => 'este registro',
        'load_data_error' => 'Erro ao carregar dados',
        'load_product_error' => 'Erro ao carregar dados do produto',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar',
        'save_product_error' => 'Erro ao salvar produto',
        'created' => 'Produto criado com sucesso!',
        'updated' => 'Produto atualizado com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'estoque',
];
