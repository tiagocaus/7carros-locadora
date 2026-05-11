<?php

/**
 * Traduções do módulo Plano de Manutenção - Português (Brasil)
 *
 * Strings específicas do CRUD de Planos de Manutenção
 */

return [
    // Títulos
    'title' => 'Planos de Manutenção',
    'title_new' => 'Adicionar Plano de Manutenção',
    'title_edit' => 'Editar Plano de Manutenção',

    // Botões
    'btn_new' => 'Novo',
    'btn_save' => 'Salvar',
    'btn_cancel' => 'Cancelar',
    'btn_back' => 'Voltar',

    // Labels do formulário
    'field_name' => 'Nome do Plano',
    'field_name_placeholder' => 'Ex: Plano Padrão, Plano Premium...',
    'field_vehicle_type' => 'Tipo de Veículo',
    'vehicle_car' => 'Carro',
    'vehicle_motorcycle' => 'Moto',
    'field_status' => 'Status',
    'field_status_active' => 'Ativo',
    'field_status_inactive' => 'Inativo',
    'field_interval' => 'Intervalo (km)',
    'field_interval_placeholder' => '0',
    'field_interval_hint' => 'Deixe 0 para desativar este item',

    // Seções do formulário
    'section_basic' => 'Dados Básicos',
    'section_intervals' => 'Intervalos de Manutenção',
    'section_intervals_hint' => 'Configure o intervalo em quilômetros para cada item de manutenção. Itens com intervalo 0 serão ignorados.',

    // Tabela
    'table_name' => 'Nome',
    'table_status' => 'Status',
    'table_items' => 'Itens Configurados',
    'table_actions' => 'Ações',
    'table_empty' => 'Nenhum plano de manutenção encontrado',
    'table_loading' => 'Carregando...',

    // Mensagens
    'messages' => [
        'created' => 'Plano de manutenção cadastrado com sucesso!',
        'updated' => 'Plano de manutenção atualizado com sucesso!',
        'deleted' => 'Plano de manutenção excluído com sucesso!',
        'not_found' => 'Plano de manutenção não encontrado.',
        'name_required' => 'O nome do plano é obrigatório.',
        'confirm_delete' => 'Deseja excluir o plano ":name"?',
        'has_vehicles' => 'Este plano está vinculado a veículos e não pode ser excluído.',
        'load_error' => 'Erro ao carregar planos de manutenção.',
        'save_error' => 'Erro ao salvar plano de manutenção.',
        'delete_error' => 'Erro ao excluir plano de manutenção.',
        'no_name' => 'Sem nome',
        'this_plan' => 'este plano',
    ],

    // Paginação
    'pagination_info' => 'Mostrando :start-:end de :total registros',
    'pagination_per_page' => 'Registros por página',
    'pagination_page_navigation' => 'Navegação de páginas',

    // Busca
    'search_placeholder' => 'Buscar plano...',

    // Tooltips
    'tooltip_edit' => 'Editar plano',
    'tooltip_delete' => 'Excluir plano',
    'tooltip_interval' => 'Quilometragem entre manutenções',
];
