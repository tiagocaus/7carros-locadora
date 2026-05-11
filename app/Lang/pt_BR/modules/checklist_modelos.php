<?php

/**
 * Traduções do módulo Checklist Modelos - Português (Brasil)
 */

return [
    'title' => 'Checklist Modelos',
    'title_singular' => 'Checklist Modelo',
    'new_title' => 'Novo Modelo de Checklist',
    'edit_title' => 'Editar Modelo de Checklist',

    // Seções
    'sections' => [
        'model_data' => 'Dados do Modelo',
        'questions' => 'Questão',
        'inspection' => 'Vistoria',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Status',
        'item_name' => 'Nome:',
    ],

    // Opções de tipo
    'type_options' => [
        'digital' => 'Digital',
        'printed' => 'Impresso',
    ],

    // Opções de status
    'status_options' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ],

    // Badges
    'badges' => [
        'type_printed' => 'Impresso',
        'type_digital' => 'Digital',
        'status_active' => 'Ativo',
        'status_inactive' => 'Inativo',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar modelo...',
        'name_example' => 'Ex: Modelo padrão',
    ],

    // Tabela
    'table' => [
        'name' => 'Nome',
        'type' => 'Tipo',
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum modelo de checklist encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'not_found' => 'Modelo não encontrado',
        'delete_error' => 'Erro ao excluir registro',
        'save_error' => 'Erro ao salvar',
        'saving' => 'Salvando...',
        'saved' => 'Modelo salvo com sucesso',
        'required_fields' => 'Preencha os campos obrigatorios:',
        'required_name' => '- Nome',
    ],

    // Modais nestable
    'nestable' => [
        'add_question' => 'Adicionar Questao',
        'edit_question' => 'Editar Questao',
        'add_inspection' => 'Adicionar Vistoria',
        'edit_inspection' => 'Editar Vistoria',
        'question' => 'Questao',
        'inspection' => 'Vistoria',
        'item' => 'item',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'checklist_modelo',
];
