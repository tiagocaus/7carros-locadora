<?php

/**
 * Traduções do módulo Feature Requests - Português (Brasil)
 */

return [
    'title' => 'Pedidos de Recursos',
    'new_title' => 'Novo Pedido de Recurso',
    'details_title' => 'Detalhes do Pedido',
    'edit_title' => 'Editar Pedido',
    'new_request' => 'Novo Pedido',

    // Campos
    'fields' => [
        'title' => 'Título do Pedido',
        'module' => 'Modulo/Área',
        'description' => 'Descrição Detalhada',
        'phone' => 'Telefone/WhatsApp (opcional)',
        'follow_auto' => 'Quero ser notificado quando este pedido for concluido',
    ],

    // Filtros
    'filters' => [
        'status' => 'Status',
        'module' => 'Modulo',
        'sort' => 'Ordenar',
        'all' => 'Todos',
        'my_requests' => 'Meus pedidos',
        'sort_recent' => 'Mais Recentes',
        'sort_votes' => 'Mais Votados',
        'sort_oldest' => 'Mais Antigos',
    ],

    // Status
    'status' => [
        'pending' => 'Pendente',
        'in_review' => 'Em Análise',
        'in_development' => 'Em Desenvolvimento',
        'completed' => 'Concluido',
        'rejected' => 'Recusado',
        'awaiting_info' => 'Aguardando Info',
        'awaiting_info_full' => 'Aguardando Informações',
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
        'module' => 'Modulo',
        'status' => 'Status',
        'votes' => 'Votos',
        'actions' => 'Ações',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar pedido...',
        'title_input' => 'Descreva brevemente o que você precisa...',
        'description_input' => 'Explique com detalhes o que você precisa, qual problema quer resolver, como imagina a solução...',
        'phone_input' => '(11) 99999-9999',
        'select_module' => 'Selecione...',
        'admin_response' => 'Adicione uma resposta ou feedback sobre o pedido...',
    ],

    // Dicas
    'hints' => [
        'title' => 'Seja claro e objetivo no título',
        'module' => 'A qual parte do sistema se refere?',
        'description' => 'Quanto mais detalhes, melhor entenderemos sua necessidade',
        'phone' => 'Para recebermos notificações por WhatsApp',
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
        'sending' => 'Enviando...',
        'save_changes' => 'Salvar Alterações',
    ],

    // Informações
    'info' => [
        'voted' => 'Você votou neste pedido',
        'following' => 'Você será notificado quando concluido',
        'vote_priority' => 'Votar aumenta a prioridade do pedido',
        'follow_updates' => 'Seguir para receber notificações quando houver atualizações',
        'requested_by' => 'Solicitado por',
        'not_categorized' => 'Não categorizado',
        'votes_label' => 'votos',
        'followers_label' => 'seguidores',
        'responded_at' => 'Respondido em',
    ],

    // Similares
    'similar' => [
        'found' => 'Encontramos pedidos similares:',
        'follow_existing' => 'Você pode seguir um pedido existente e será notificado quando for concluido.',
        'follow_btn' => 'Seguir',
    ],

    // Detalhes
    'details' => [
        'description' => 'Descrição',
        'admin_response' => 'Resposta da Equipe 7Carros',
        'additional_info' => 'Informações Adicionais',
        'id' => 'ID:',
        'priority' => 'Prioridade:',
        'updated' => 'Atualizado:',
        'email' => 'E-mail:',
    ],

    // Admin
    'admin' => [
        'panel_title' => 'Painel Administrativo',
        'change_status' => 'Alterar Status',
        'priority' => 'Prioridade',
        'response' => 'Resposta/Feedback',
        'notify_creator' => 'Notificar ao criador sobre essa alteração',
        'notify_followers' => 'Notificar aos seguidores',
        'followers_title' => 'Seguidores',
        'no_followers' => 'Nenhum seguidor ainda',
        'notify_email' => 'Notificar e-mail',
        'notify_whatsapp' => 'Notificar WhatsApp',
    ],

    // Modal edição
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
        'server_error' => 'Erro ao conectar com o servidor',
        'vote_error' => 'Erro ao processar voto',
        'follow_error' => 'Erro ao seguir pedido',
        'process_error' => 'Erro ao processar',
        'follow_success' => 'Você agora segue este pedido e será notificado quando for concluido!',
        'now_following' => 'Você agora segue este pedido!',
        'unfollowed' => 'Você deixou de seguir este pedido',
        'vote_added' => 'Voto registrado!',
        'vote_removed' => 'Voto removido',
        'title_required' => 'Informe o título do pedido',
        'module_required' => 'Selecione o modulo/área',
        'description_required' => 'Informe a descrição detalhada',
        'title_required_edit' => 'Informe o título',
        'description_required_edit' => 'Informe a descrição',
        'submit_success' => 'Pedido enviado com sucesso!',
        'submit_error' => 'Erro ao enviar pedido',
        'update_success' => 'Pedido atualizado com sucesso!',
        'update_error' => 'Erro ao atualizar',
        'update_request_error' => 'Erro ao atualizar pedido',
        'not_found' => 'Pedido não encontrado',
        'id_not_found' => 'ID do pedido não informado',
        'load_request_error' => 'Erro ao carregar pedido',
        'admin_save_success' => 'Alterações salvas com sucesso!',
        'admin_save_error' => 'Erro ao salvar',
        'admin_save_changes_error' => 'Erro ao salvar alterações',
        'saving' => 'Salvando...',
        'back_to_list' => 'Voltar para lista',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Módulos do sistema (categorias)
    'sistema_inicial' => 'Sistema - Inicial',
    'sistema_locacoes' => 'Sistema - Locações',
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
    'sistema_checklist_modelos' => 'Sistema - Checklist modelos',
    'sistema_relatorios' => 'Sistema - Relatórios',
    'sistema_financeiro' => 'Sistema - Financeiro',
    'sistema_site' => 'Sistema - Site',
    'sistema_clientes' => 'Sistema - Clientes',
    'sistema_whatsapp' => 'Sistema - WhatsApp',
    'sistema_documentos' => 'Sistema - Documentos',
    'sistema_estoque' => 'Sistema - Estoque',
    'sistema_agenda' => 'Sistema - Agenda',

    // Website e Aplicativo
    'website_site' => 'Website - Site',
    'aplicativo_checklist' => 'Aplicativo - Checklist',

    // Outros
    'outros' => 'Outros',
];
