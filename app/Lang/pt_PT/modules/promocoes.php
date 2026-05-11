<?php

/**
 * Traduções do módulo Promoções - Português (Portugal)
 */

return [
    'title' => 'Promoções',
    'title_singular' => 'Promoção',
    'new_title' => 'Nova Promoção',
    'edit_title' => 'Editar Promoção',

    // Secções
    'sections' => [
        'promotion_data' => 'Dados da Promoção',
    ],

    // Campos
    'fields' => [
        'branches' => 'Filiais',
        'code' => 'Código',
        'name' => 'Nome da Promoção',
        'validity' => 'Validade',
        'minimum_days' => 'Diária Mínima',
        'discount_type' => 'Tipo de Desconto',
        'discount_value' => 'Valor do Desconto',
        'where_to_show' => 'Onde Apresentar',
        'status' => 'Estado',
    ],

    // Tooltips
    'tooltips' => [
        'validity' => 'Data limite para utilização da promoção. Deixe em branco para não ter prazo.',
        'minimum_days' => 'Número mínimo de dias de aluguer para a promoção ser válida.',
        'where_to_show' => 'Seleccione onde esta promoção estará disponível.',
    ],

    // Opções de tipo
    'type_options' => [
        'fixed' => 'Fixo',
        'percentage' => 'Percentagem (%)',
    ],

    // Opções de estado
    'status_options' => [
        'active' => 'Activo',
        'disabled' => 'Desactivado',
    ],

    // Opções de apresentação
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Site',
        'app' => 'App',
        'all' => 'Todos',
    ],

    // Marcadores
    'placeholders' => [
        'search' => 'Pesquisar promoção...',
        'select_branches' => 'Seleccione as filiais...',
        'select' => 'Seleccione...',
        'code_example' => 'Ex: PROMO2024',
        'name_example' => 'Ex: Desconto Verão',
    ],

    // Etiquetas
    'badges' => [
        'type_percentage' => 'Percentagem',
        'type_fixed' => 'Fixo',
        'status_active' => 'Activo',
        'status_inactive' => 'Inactivo',
    ],

    // Tabela
    'table' => [
        'code' => 'Código',
        'name' => 'Nome',
        'type' => 'Tipo',
        'value' => 'Valor',
        'min_days' => 'Dias Mín',
        'branches' => 'Filiais',
        'status' => 'Estado',
        'actions' => 'Acções',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma promoção encontrada',
        'no_name' => 'Sem nome',
        'all_branches' => 'Todas',
        'days_suffix' => 'dias',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar registo',
        'this_record' => 'esta promoção',
        'not_found' => 'Promoção não encontrada',
        'load_branches_error' => 'Erro ao carregar filiais',
        'load_branches_text' => 'Erro ao carregar',
        'no_branches' => 'Nenhuma filial registada',
        'no_branches_text' => 'Nenhuma filial',
        'loading_branches' => 'A carregar filiais...',
        'required_fields' => 'Preencha os campos obrigatórios:',
        'saving' => 'A guardar...',
        'save_error' => 'Erro ao guardar',
        'created' => 'Promoção criada com sucesso!',
        'updated' => 'Promoção actualizada com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'promoção',
];
