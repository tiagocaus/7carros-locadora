<?php

/**
 * Traduções do módulo Planos de Contas - Português (Portugal)
 */

return [
    // Títulos
    'title' => 'Plano de Contas',
    'title_singular' => 'Plano de Contas',
    'list_title' => 'Lista de Planos de Contas',
    'new_title' => 'Novo Plano de Contas',
    'edit_title' => 'Editar Plano de Contas',

    // Campos do formulário
    'fields' => [
        'hierarquia' => 'Código',
        'descricao' => 'Descrição',
        'tipo' => 'Tipo',
        'tipo_ativo' => 'Ativo',
        'tipo_passivo' => 'Passivo',
        'tipo_despesa' => 'Gasto',
        'tipo_receita' => 'Receita',
        'conta_pai' => 'Conta Pai',
        'descricao_pt_BR' => 'Português (Brasil)',
        'descricao_en_US' => 'Inglês (EUA)',
        'descricao_es_ES' => 'Espanhol',
        'descricao_it_IT' => 'Italiano',
        'descricao_pt_PT' => 'Português (Portugal)',
    ],

    // Seções do formulário
    'sections' => [
        'basic_info' => 'Informações Básicas',
        'translations' => 'Descrições por Idioma',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar plano de contas...',
        'descricao' => 'Ex: Caixa geral',
        'descricao_optional' => 'Opcional - utilizará pt_BR se vazio',
        'conta_pai' => 'Selecione a conta pai (opcional para conta raiz)',
        'selecione_tipo' => 'Selecione o tipo primeiro',
        'hierarquia' => 'Ex: 1.1.1.01',
    ],

    // Filtros
    'filters' => [
        'all_types' => 'Todos os tipos',
    ],

    // Tooltips
    'tooltips' => [
        'hierarquia' => 'Código hierárquico único. Ex: 1.1.1.01',
        'tipo' => 'Classificação contabilística do plano de contas.',
    ],

    // Mensagens
    'messages' => [
        'created' => 'Plano de contas criado com sucesso!',
        'updated' => 'Plano de contas atualizado com sucesso!',
        'deleted' => 'Plano de contas eliminado com sucesso!',
        'saved' => 'Plano de contas guardado com sucesso!',
        'not_found' => 'Plano de contas não encontrado.',
        'has_transactions' => 'Este plano de contas possui lançamentos financeiros e não pode ser eliminado.',
        'hierarquia_required' => 'O código hierárquico é obrigatório.',
        'hierarquia_exists' => 'Já existe um plano de contas com este código.',
        'tipo_invalid' => 'Tipo de conta inválido.',
        'descricao_required' => 'A descrição em Português (Brasil) é obrigatória.',
        'cannot_edit_system' => 'Planos de contas do sistema não podem ser editados.',
        'cannot_delete_system' => 'Planos de contas do sistema não podem ser eliminados.',
        'system_readonly' => 'Este é um plano de contas do sistema e não pode ser alterado.',
        'no_records' => 'Nenhum plano de contas encontrado.',
        'translations_help' => 'Preencha a descrição em Português (Brasil). Os outros idiomas são opcionais e utilizarão o valor em pt_BR se deixados em branco.',
        'error_list' => 'Erro ao listar planos de contas',
        'error_load' => 'Erro ao carregar plano de contas',
        'error_create' => 'Erro ao criar plano de contas',
        'error_update' => 'Erro ao atualizar plano de contas',
        'error_delete' => 'Erro ao eliminar plano de contas',
        'error_save' => 'Erro ao guardar plano de contas',
        'codigo_disponivel' => 'Código disponível',
        'codigo_em_uso' => 'Este código já está em uso',
        'codigo_sugerido' => 'Código sugerido automaticamente',
        'conta_raiz' => 'Conta raiz (nível principal)',
        'formato_invalido' => 'Formato inválido. Utilize apenas números e pontos (ex: 1.1.01)',
        'this_record' => 'este plano de contas',
    ],
];
