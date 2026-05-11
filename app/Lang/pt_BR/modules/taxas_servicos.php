<?php

/**
 * Traduções do módulo Taxas e Serviços - Português (Brasil)
 */

return [
    'title' => 'Taxas e Serviços',
    'title_singular' => 'Taxa/Serviço',
    'new_title' => 'Nova Taxa/Serviço',
    'edit_title' => 'Editar Taxa/Serviço',

    // Seções
    'sections' => [
        'fee_data' => 'Dados da Taxa/Serviço',
        'values_by_branch' => 'Valores por filial',
    ],

    'descriptions' => [
        'values_by_branch' => 'Como e valor monetario fixo, cada filial tem o valor na sua moeda.',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'branches' => 'Filiais',
        'calculation_base' => 'Base de Cálculo',
        'value_type' => 'Tipo de Valor',
        'value' => 'Valor',
        'auto_apply' => 'Aplicar Automaticamente',
        'where_to_use' => 'Onde Usar',
    ],

    // Tooltips
    'tooltips' => [
        'auto_apply' => 'Quando ativo, a taxa será adicionada automaticamente em novos contratos.',
        'where_to_use' => 'Selecione onde esta taxa estará disponível.',
    ],

    // Opções de base de cálculo
    'calculation_options' => [
        'fixed' => 'Fixo (valor único)',
        'per_period' => 'Por Período (calculado por dia)',
        'total_value' => 'Valor Total',
    ],

    // Opções de tipo de valor
    'value_type_options' => [
        'monetary' => 'Monetario (R$)',
        'percentage' => 'Porcentagem (%)',
    ],

    // Opções de aplicar
    'apply_options' => [
        'no' => 'Não (requer seleção manual)',
        'yes' => 'Sim (aplicada automaticamente)',
    ],

    // Opções de onde usar
    'display_options' => [
        'system' => 'Sistema',
        'site' => 'Site',
        'app' => 'App',
        'all' => 'Todos',
    ],

    // Badges
    'badges' => [
        'base_fixed' => 'Fixo',
        'base_per_period' => 'Por Período',
        'base_total_value' => 'Valor Total',
        'apply_yes' => 'Sim',
        'apply_no' => 'Não',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar taxa...',
        'select_branches' => 'Selecione as filiais...',
        'all_branches' => 'Todas as filiais',
        'select' => 'Selecione...',
        'name_example' => 'Ex: Taxa de limpeza',
    ],

    // Tabela
    'table' => [
        'name' => 'Nome',
        'calculation_base' => 'Base Cálculo',
        'value' => 'Valor',
        'auto_apply' => 'Aplicar Auto',
        'branches' => 'Filiais',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma taxa ou serviço encontrado',
        'no_name' => 'Sem nome',
        'all_branches' => 'Todas',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir registro',
        'this_record' => 'esta taxa/serviço',
        'not_found' => 'Taxa/serviço não encontrado',
        'load_branches_error' => 'Erro ao carregar filiais',
        'load_branches_text' => 'Erro ao carregar',
        'no_branches' => 'Nenhuma filial cadastrada',
        'no_branches_text' => 'Nenhuma filial',
        'loading_branches' => 'Carregando filiais...',
        'select_branches_first' => 'Selecione ao menos uma filial para definir os valores.',
        'required_fields' => 'Preencha os campos obrigatorios:',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar',
        'created' => 'Taxa/serviço criado com sucesso!',
        'updated' => 'Taxa/serviço atualizado com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'taxa_servico',
];
