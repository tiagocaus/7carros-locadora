<?php

/**
 * Traduções do módulo Feature Requests - Português (Portugal)
 */

return [
    'title' => 'Pedidos de Funcionalidades',
    'new_title' => 'Novo Pedido de Funcionalidade',
    'details_title' => 'Detalhes do Pedido',
    'edit_title' => 'Editar Pedido',
    'new_request' => 'Novo Pedido',

    // Campos
    'fields' => [
        'title' => 'Título do Pedido',
        'module' => 'Módulo/Área',
        'description' => 'Descrição Detalhada',
        'phone' => 'Telefone/WhatsApp (opcional)',
        'follow_auto' => 'Quero ser notificado quando este pedido for concluído',
    ],

    // Filtros
    'filters' => [
        'status' => 'Estado',
        'module' => 'Módulo',
        'sort' => 'Ordenar',
        'all' => 'Todos',
        'my_requests' => 'Os meus pedidos',
        'sort_recent' => 'Mais Recentes',
        'sort_votes' => 'Mais Votados',
        'sort_oldest' => 'Mais Antigos',
    ],

    // Estado
    'status' => [
        'pending' => 'Pendente',
        'in_review' => 'Em Análise',
        'in_development' => 'Em Desenvolvimento',
        'completed' => 'Concluído',
        'rejected' => 'Recusado',
        'awaiting_info' => 'A Aguardar Info',
        'awaiting_info_full' => 'A Aguardar Informações',
    ],

    // Prioridades
    'priorities' => [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ],

    // Tabela
    'table' => [
        'title' => 'Título',
        'module' => 'Módulo',
        'status' => 'Estado',
        'votes' => 'Votos',
        'actions' => 'Ações',
    ],

    // Marcadores de posição
    'placeholders' => [
        'search' => 'Pesquisar pedido...',
        'title_input' => 'Descreva brevemente o que precisa...',
        'description_input' => 'Explique com detalhe o que precisa, que problema quer resolver e como imagina a solução...',
        'phone_input' => '+351 999 999 999',
        'select_module' => 'Selecione...',
        'admin_response' => 'Adicione uma resposta ou comentário sobre o pedido...',
    ],

    // Sugestões
    'hints' => [
        'title' => 'Seja claro e objetivo no título',
        'module' => 'A que parte do sistema se refere?',
        'description' => 'Quanto mais detalhes fornecer, melhor compreenderemos a sua necessidade',
        'phone' => 'Para receber notificações por WhatsApp',
    ],

    // Botões e ações
    'actions' => [
        'vote' => 'Votar neste pedido',
        'remove_vote' => 'Remover voto',
        'follow' => 'Seguir',
        'unfollow' => 'Deixar de seguir',
        'view_details' => 'Ver detalhes',
        'view' => 'Ver',
        'submit' => 'Enviar Pedido',
        'sending' => 'A enviar...',
        'save_changes' => 'Guardar Alterações',
    ],

    // Informações
    'info' => [
        'voted' => 'Votou neste pedido',
        'following' => 'Será notificado quando concluído',
        'vote_priority' => 'Votar aumenta a prioridade do pedido',
        'follow_updates' => 'Seguir para receber notificações quando houver atualizações',
        'requested_by' => 'Solicitado por',
        'not_categorized' => 'Não categorizado',
        'votes_label' => 'votos',
        'followers_label' => 'seguidores',
        'responded_at' => 'Respondido em',
    ],

    // Semelhantes
    'similar' => [
        'found' => 'Encontrámos pedidos semelhantes:',
        'follow_existing' => 'Pode seguir um pedido existente e será notificado quando for concluído.',
        'follow_btn' => 'Seguir',
    ],

    // Detalhes
    'details' => [
        'description' => 'Descrição',
        'admin_response' => 'Resposta da Equipa 7Carros',
        'additional_info' => 'Informações Adicionais',
        'id' => 'ID:',
        'priority' => 'Prioridade:',
        'updated' => 'Atualizado:',
        'email' => 'Email:',
    ],

    // Admin
    'admin' => [
        'panel_title' => 'Painel Administrativo',
        'change_status' => 'Alterar Estado',
        'priority' => 'Prioridade',
        'response' => 'Resposta/Comentário',
        'notify_followers' => 'Notificar seguidores sobre a alteração',
        'followers_title' => 'Seguidores',
        'no_followers' => 'Ainda sem seguidores',
        'notify_email' => 'Notificar por email',
        'notify_whatsapp' => 'Notificar por WhatsApp',
    ],

    // Modal de edição
    'edit' => [
        'title_label' => 'Título',
        'description_label' => 'Descrição',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum pedido encontrado',
        'no_title' => 'Sem título',
        'other_module' => 'Outro',
        'load_error' => 'Erro ao carregar pedidos',
        'server_error' => 'Erro ao ligar ao servidor',
        'vote_error' => 'Erro ao processar voto',
        'follow_error' => 'Erro ao seguir pedido',
        'process_error' => 'Erro ao processar',
        'follow_success' => 'Está agora a seguir este pedido e será notificado quando for concluído!',
        'now_following' => 'Está agora a seguir este pedido!',
        'unfollowed' => 'Deixou de seguir este pedido',
        'vote_added' => 'Voto registado!',
        'vote_removed' => 'Voto removido',
        'title_required' => 'Indique o título do pedido',
        'module_required' => 'Selecione o módulo/área',
        'description_required' => 'Indique a descrição detalhada',
        'title_required_edit' => 'Indique o título',
        'description_required_edit' => 'Indique a descrição',
        'submit_success' => 'Pedido enviado com sucesso!',
        'submit_error' => 'Erro ao enviar pedido',
        'update_success' => 'Pedido atualizado com sucesso!',
        'update_error' => 'Erro ao atualizar',
        'update_request_error' => 'Erro ao atualizar pedido',
        'not_found' => 'Pedido não encontrado',
        'id_not_found' => 'ID do pedido não indicado',
        'load_request_error' => 'Erro ao carregar pedido',
        'admin_save_success' => 'Alterações guardadas com sucesso!',
        'admin_save_error' => 'Erro ao guardar',
        'admin_save_changes_error' => 'Erro ao guardar alterações',
        'saving' => 'A guardar...',
        'back_to_list' => 'Voltar à lista',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Módulos do sistema (categorias)
    'sistema_inicial' => 'Sistema - Inicial',
    'sistema_locacoes' => 'Sistema - Alugueres',
    'sistema_contratos' => 'Sistema - Contratos',
    'sistema_matriz_filiais' => 'Sistema - Matriz e filiais',
    'sistema_funcionarios' => 'Sistema - Funcionários',
    'sistema_taxas_servicos' => 'Sistema - Taxas e serviços',
    'sistema_oficinas' => 'Sistema - Oficinas',
    'sistema_promocoes' => 'Sistema - Promoções',
    'sistema_multas' => 'Sistema - Multas',
    'sistema_contas_caixa' => 'Sistema - Contas bancárias/caixa',
    'sistema_formas_pagamento' => 'Sistema - Formas de pagamento',
    'sistema_fornecedores' => 'Sistema - Fornecedores',
    'sistema_veiculos' => 'Sistema - Veículos',
    'sistema_grupos' => 'Sistema - Grupos',
    'sistema_acessorios_itens' => 'Sistema - Acessórios e itens',
    'sistema_manutencoes' => 'Sistema - Manutenções',
    'sistema_plano_manutencoes' => 'Sistema - Plano de manutenções',
    'sistema_checklist' => 'Sistema - Checklist',
    'sistema_checklist_modelos' => 'Sistema - Modelos de checklist',
    'sistema_relatorios' => 'Sistema - Relatórios',
    'sistema_financeiro' => 'Sistema - Financeiro',
    'sistema_site' => 'Sistema - Site',
    'sistema_clientes' => 'Sistema - Clientes',
    'sistema_whatsapp' => 'Sistema - WhatsApp',
    'sistema_documentos' => 'Sistema - Documentos',
    'sistema_estoque' => 'Sistema - Stock',
    'sistema_agenda' => 'Sistema - Agenda',

    // Website e Aplicação
    'website_site' => 'Website - Site',
    'aplicativo_checklist' => 'Aplicação - Checklist',

    // Outros
    'outros' => 'Outros',
];
