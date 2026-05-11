<?php

/**
 * Traduções do módulo Checklist Modelos - Português (Portugal)
 */

return [
    'title' => 'Modelos de Checklist',
    'title_singular' => 'Modelo de Checklist',
    'new_title' => 'Novo Modelo de Checklist',
    'edit_title' => 'Editar Modelo de Checklist',

    // Secções
    'sections' => [
        'model_data' => 'Dados do Modelo',
        'questions' => 'Questão',
        'inspection' => 'Vistoria',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Estado',
        'item_name' => 'Nome:',
    ],

    // Opções de tipo
    'type_options' => [
        'digital' => 'Digital',
        'printed' => 'Impresso',
    ],

    // Opções de estado
    'status_options' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Badges
    'badges' => [
        'type_printed' => 'Impresso',
        'type_digital' => 'Digital',
        'status_active' => 'Activo',
        'status_inactive' => 'Inactivo',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar modelo...',
        'name_example' => 'Ex: Modelo padrão',
    ],

    // Tabela
    'table' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Estado',
        'actions' => 'Acções',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum modelo de checklist encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao ligar ao servidor',
        'not_found' => 'Modelo não encontrado',
        'delete_error' => 'Erro ao eliminar registo',
        'save_error' => 'Erro ao guardar',
        'saving' => 'A guardar...',
        'saved' => 'Modelo guardado com sucesso',
        'required_fields' => 'Preencha os campos obrigatórios:',
        'required_name' => '- Nome',
    ],

    // Modais nestable
    'nestable' => [
        'add_question' => 'Adicionar Questão',
        'edit_question' => 'Editar Questão',
        'add_inspection' => 'Adicionar Vistoria',
        'edit_inspection' => 'Editar Vistoria',
        'question' => 'Questão',
        'inspection' => 'Vistoria',
        'item' => 'item',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'checklist_modelo',
];
