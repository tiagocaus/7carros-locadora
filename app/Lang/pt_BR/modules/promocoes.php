<?php

/**
 * Traduções do módulo Promoções - Português (Brasil)
 */

return [
    'title' => 'Promoções',
    'title_singular' => 'Promoção',
    'new_title' => 'Nova Promoção',
    'edit_title' => 'Editar Promoção',

    // Seções
    'sections' => [
        'promotion_data' => 'Dados da Promoção',
        'values_by_branch' => 'Valor do desconto por filial',
    ],

    'descriptions' => [
        'values_by_branch' => 'Como é desconto fixo em dinheiro, cada filial tem seu valor na sua moeda.',
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
        'where_to_show' => 'Onde Exibir',
        'status' => 'Status',
    ],

    // Tooltips
    'tooltips' => [
        'validity' => 'Data limite para uso da promoção. Deixe em branco para não ter prazo.',
        'minimum_days' => 'Quantidade mínima de dias de locação para a promoção ser valida.',
        'where_to_show' => 'Selecione onde esta promoção estará disponível.',
    ],

    // Opções de tipo
    'type_options' => [
        'fixed' => 'Fixo',
        'percentage' => 'Porcentagem (%)',
    ],

    // Opções de status
    'status_options' => [
        'active' => 'Ativo',
        'disabled' => 'Desativado',
    ],

    // Opções de onde exibir
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Site',
        'app' => 'App',
        'all' => 'Todos',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar promoção...',
        'select_branches' => 'Selecione as filiais...',
        'select' => 'Selecione...',
        'code_example' => 'Ex: PROMO2024',
        'name_example' => 'Ex: Desconto Verao',
    ],

    // Badges
    'badges' => [
        'type_percentage' => 'Porcentagem',
        'type_fixed' => 'Fixo',
        'status_active' => 'Ativo',
        'status_inactive' => 'Inativo',
    ],

    // Tabela
    'table' => [
        'code' => 'Código',
        'name' => 'Nome',
        'type' => 'Tipo',
        'value' => 'Valor',
        'min_days' => 'Dias Min',
        'branches' => 'Filiais',
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma promoção encontrada',
        'no_name' => 'Sem nome',
        'all_branches' => 'Todas',
        'days_suffix' => 'dias',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir registro',
        'this_record' => 'esta promoção',
        'not_found' => 'Promoção não encontrada',
        'select_branches_first' => 'Selecione ao menos uma filial participante para definir os valores.',
        'load_branches_error' => 'Erro ao carregar filiais',
        'load_branches_text' => 'Erro ao carregar',
        'no_branches' => 'Nenhuma filial cadastrada',
        'no_branches_text' => 'Nenhuma filial',
        'loading_branches' => 'Carregando filiais...',
        'required_fields' => 'Preencha os campos obrigatorios:',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar',
        'created' => 'Promoção criada com sucesso!',
        'updated' => 'Promoção atualizada com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'promoção',
];
