<?php

/**
 * Traduções do módulo Stock - Português (Portugal)
 */

return [
    'title' => 'Stock',
    'title_singular' => 'Produto',
    'new_title' => 'Novo Produto',
    'edit_title' => 'Editar Produto',

    // Secções
    'sections' => [
        'product_data' => 'Dados do Produto',
        'stock' => 'Stock',
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
        'branch' => 'Sede/Filial',
        'supplier' => 'Fornecedor',
        'current_stock' => 'Stock Actual',
        'minimum_stock' => 'Stock Mínimo',
        'purchase_value' => 'Valor de Compra',
        'sale_value' => 'Valor de Venda',
        'auto_deduct' => 'Baixa automática',
        'auto_deduct_enable' => 'Activar',
        'allow_negative_stock' => 'Permitir stock negativo',
        'allow_negative_stock_enable' => 'Activar',
    ],

    // Opções de unidade
    'unit_options' => [
        'UN' => 'UN - Unidade',
        'PC' => 'PC - Peça',
        'CX' => 'CX - Caixa',
        'KG' => 'KG - Quilograma',
        'L' => 'L - Litro',
        'M' => 'M - Metro',
        'M2' => 'M2 - Metro Quadrado',
        'M3' => 'M3 - Metro Cúbico',
        'JG' => 'JG - Jogo',
        'KIT' => 'KIT - Kit',
        'PAR' => 'PAR - Par',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar...',
        'select' => 'Seleccione...',
        'storage_location' => 'Ex: Prateleira A3',
        'search_branch' => 'Digite para pesquisar...',
        'search_supplier' => 'Digite para pesquisar...',
        'none' => 'Nenhum',
    ],

    // Estado
    'status' => [
        'label' => 'Estado',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Filtros
    'filters' => [
        'all_branches' => 'Todas as filiais',
        'all_status' => 'Todos os estados',
    ],

    // Avisos (tooltips)
    'tooltips' => [
        'minimum_stock' => 'Alerta quando atingir este valor. 0 = desactivado.',
        'auto_deduct' => 'Quando activado, o stock será decrementado automaticamente ao usar este produto numa ordem de manutenção.',
        'allow_negative_stock' => 'Quando activado, permite usar o produto mesmo sem stock disponível. Quando desactivado, impede a selecção com stock zero e limita a quantidade ao stock disponível.',
    ],

    // Tabela
    'table' => [
        'code' => 'Código',
        'product' => 'Produto',
        'brand_model' => 'Marca/Modelo',
        'unit' => 'Unidade',
        'stock' => 'Stock',
        'purchase_value' => 'Valor Compra',
        'branch' => 'Filial',
        'status' => 'Estado',
        'actions' => 'Acções',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum registo encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar',
        'inactivated' => 'Produto inactivado. Possui vínculo com manutenção e não pode ser eliminado.',
        'reactivated' => 'Produto reactivado com sucesso!',
        'already_inactive' => 'Produto já está inactivo',
        'reactivate_error' => 'Erro ao reactivar',
        'this_record' => 'este registo',
        'load_data_error' => 'Erro ao carregar dados',
        'load_product_error' => 'Erro ao carregar dados do produto',
        'saving' => 'A guardar...',
        'save_error' => 'Erro ao guardar',
        'save_product_error' => 'Erro ao guardar produto',
        'created' => 'Produto criado com sucesso!',
        'updated' => 'Produto actualizado com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'stock',
];
