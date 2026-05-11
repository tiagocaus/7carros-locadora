<?php

/**
 * Traduções do módulo Documentos - Português (Brasil)
 */

return [
    'title' => 'Modelos de Documento',
    'title_singular' => 'Documento',
    'new_title' => 'Novo Documento',
    'edit_title' => 'Editar Documento',

    // Filtros de tipo
    'filters' => [
        'all' => 'Todos',
        'both' => 'Contrato/Locação',
        'contract' => 'Contrato',
        'rental' => 'Locação',
        'fine' => 'Multa',
    ],

    // Tabela
    'table' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Status',
        'updated_at' => 'Atualizado em',
        'actions' => 'Ações',
    ],

    // Badges
    'badges' => [
        'type_both' => 'Contrato/Locação',
        'type_contract' => 'Contrato',
        'type_rental' => 'Locação',
        'type_fine' => 'Multa',
        'status_active' => 'Ativo',
        'status_inactive' => 'Inativo',
    ],

    // Campos do formulário
    'fields' => [
        'title' => 'Título',
        'type' => 'Tipo',
        'status' => 'Status',
        'content' => 'Conteudo',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar documento...',
        'title_example' => 'Ex: Contrato de Locação',
    ],

    // Painel de variáveis
    'variables' => [
        'title' => 'Variaveis Disponíveis',
        'description' => 'Clique para inserir no editor',
        'no_variables' => 'Nenhuma variavel disponível',
        'load_error' => 'Erro ao carregar variaveis',
    ],

    // Descrição
    'description' => 'Crie modelos de documentos com variaveis auto-preenchidas',

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum documento encontrado',
        'no_title' => 'Sem título',
        'load_error' => 'Erro ao carregar documentos',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir documento',
        'this_record' => 'este documento',
        'title_required' => 'O título e obrigatório',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar documento',
        'saved' => 'Documento salvo com sucesso',
        'imported' => 'Documento importado com sucesso!',
        'editor_error' => 'Erro ao carregar editor. Recarregue a página.',
        'content_required' => 'Digite algum conteudo para visualizar',
        'preview_error' => 'Erro ao gerar preview',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'documento',
];
