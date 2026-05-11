<?php

/**
 * Traduções do módulo Plano de Manutenção - Português (Portugal)
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
    'btn_save' => 'Guardar',
    'btn_cancel' => 'Cancelar',
    'btn_back' => 'Voltar',

    // Labels do formulário
    'field_name' => 'Nome do Plano',
    'field_name_placeholder' => 'Ex: Plano Padrão, Plano Premium...',
    'field_vehicle_type' => 'Tipo de Veículo',
    'vehicle_car' => 'Carro',
    'vehicle_motorcycle' => 'Moto',
    'field_status' => 'Estado',
    'field_status_active' => 'Ativo',
    'field_status_inactive' => 'Inativo',
    'field_interval' => 'Intervalo (km)',
    'field_interval_placeholder' => '0',
    'field_interval_hint' => 'Deixe 0 para desativar este item',

    // Secções do formulário
    'section_basic' => 'Dados Básicos',
    'section_intervals' => 'Intervalos de Manutenção',
    'section_intervals_hint' => 'Configure o intervalo em quilómetros para cada item de manutenção. Itens com intervalo 0 serão ignorados.',

    // Tabela
    'table_name' => 'Nome',
    'table_status' => 'Estado',
    'table_items' => 'Itens Configurados',
    'table_actions' => 'Ações',
    'table_empty' => 'Nenhum plano de manutenção encontrado',
    'table_loading' => 'A carregar...',

    // Mensagens
    'messages' => [
        'created' => 'Plano de manutenção criado com sucesso!',
        'updated' => 'Plano de manutenção atualizado com sucesso!',
        'deleted' => 'Plano de manutenção eliminado com sucesso!',
        'not_found' => 'Plano de manutenção não encontrado.',
        'name_required' => 'O nome do plano é obrigatório.',
        'confirm_delete' => 'Deseja eliminar o plano ":name"?',
        'has_vehicles' => 'Este plano está vinculado a veículos e não pode ser eliminado.',
        'load_error' => 'Erro ao carregar planos de manutenção.',
        'save_error' => 'Erro ao guardar plano de manutenção.',
        'delete_error' => 'Erro ao eliminar plano de manutenção.',
        'no_name' => 'Sem nome',
        'this_plan' => 'este plano',
    ],

    // Paginação
    'pagination_info' => 'A mostrar :start-:end de :total registos',
    'pagination_per_page' => 'Registos por página',
    'pagination_page_navigation' => 'Navegação de páginas',

    // Pesquisa
    'search_placeholder' => 'Pesquisar plano...',

    // Tooltips
    'tooltip_edit' => 'Editar plano',
    'tooltip_delete' => 'Eliminar plano',
    'tooltip_interval' => 'Quilómetros entre manutenções',
];
