<?php

/**
 * Traduções do módulo Documentos - Português (Portugal)
 */

return [
    'title' => 'Modelos de Documento',
    'title_singular' => 'Documento',
    'new_title' => 'Novo Documento',
    'edit_title' => 'Editar Documento',

    // Filtros de tipo
    'filters' => [
        'all' => 'Todos',
        'both' => 'Contrato/Aluguer',
        'contract' => 'Contrato',
        'rental' => 'Aluguer',
        'fine' => 'Multa',
    ],

    // Tabela
    'table' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Estado',
        'updated_at' => 'Atualizado em',
        'actions' => 'Ações',
    ],

    // Emblemas
    'badges' => [
        'type_both' => 'Contrato/Aluguer',
        'type_contract' => 'Contrato',
        'type_rental' => 'Aluguer',
        'type_fine' => 'Multa',
        'status_active' => 'Ativo',
        'status_inactive' => 'Inativo',
    ],

    // Campos do formulário
    'fields' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Estado',
        'content' => 'Conteúdo',
    ],

    // Marcadores de posição
    'placeholders' => [
        'search' => 'Pesquisar documento...',
        'title_example' => 'Ex: Contrato de Aluguer',
    ],

    // Painel de variáveis
    'variables' => [
        'title' => 'Variáveis Disponíveis',
        'description' => 'Clique para inserir no editor',
        'no_variables' => 'Nenhuma variável disponível',
        'load_error' => 'Erro ao carregar variáveis',
    ],

    // Descrição
    'description' => 'Crie modelos de documentos com variáveis preenchidas automaticamente',

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum documento encontrado',
        'no_title' => 'Sem título',
        'load_error' => 'Erro ao carregar documentos',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar documento',
        'this_record' => 'este documento',
        'title_required' => 'O título é obrigatório',
        'saving' => 'A guardar...',
        'save_error' => 'Erro ao guardar documento',
        'saved' => 'Documento guardado com sucesso',
        'imported' => 'Documento importado com sucesso!',
        'editor_error' => 'Erro ao carregar o editor. Recarregue a página.',
        'content_required' => 'Introduza algum conteúdo para pré-visualizar',
        'preview_error' => 'Erro ao gerar pré-visualização',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'documento',
];
