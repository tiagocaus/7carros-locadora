<?php

/**
 * Traduções do módulo Roles (Funções) - Português (Brasil)
 */

return [
    'title' => 'Gerenciar Funções',
    'title_singular' => 'Função',
    'new_title' => 'Nova Função',
    'edit_title' => 'Editar Função',
    'edit_prefix' => 'Editar:',

    // Secoes
    'sections' => [
        'role_data' => 'Dados da Função',
        'permissions' => 'Permissões',
        'permissions_desc' => 'Selecione as permissões que esta função terá acesso:',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome da Função',
        'description' => 'Descrição',
    ],

    // Placeholders
    'placeholders' => [
        'name' => 'Ex: Gerente, Atendente...',
        'name_full' => 'Ex: Gerente, Atendente, Motorista...',
        'description' => 'Descreva as responsabilidades...',
        'description_full' => 'Descreva as responsabilidades desta função...',
    ],

    // Badges
    'badges' => [
        'system' => 'Sistema',
        'custom' => 'Personalizada',
    ],

    // Avisos
    'warnings' => [
        'system_role_title' => 'Função de Sistema',
        'system_role_desc' => 'Esta e uma função padrão do sistema. Ao salvar suas alterações, será criada uma <strong>copia personalizada</strong> exclusiva para sua empresa. A função original do sistema permanecera inalterada.',
        'system_role_short' => 'Esta e uma função de sistema. Ao salvar, será criada uma copia personalizada para sua empresa.',
        'custom_role_title' => 'Função Personalizada',
        'custom_role_desc' => 'Esta e uma versão personalizada de uma função de sistema. O nome não pode ser alterado.',
        'name_locked' => 'Nome bloqueado (função personalizada do sistema)',
        'name_locked_title' => 'O nome não pode ser alterado em funções personalizadas do sistema',
        'irreversible' => 'Esta ação não pode ser desfeita.',
    ],

    // Acoes
    'actions' => [
        'save_role' => 'Salvar Função',
        'save_changes' => 'Salvar Alterações',
        'create_copy' => 'Criar Copia Personalizada',
        'delete_role' => 'Excluir Função',
        'select_all' => 'Selecionar todos',
        'select_all_short' => 'Todos',
    ],

    // Mensagens
    'messages' => [
        'loading_roles' => 'Carregando funções...',
        'loading_permissions' => 'Carregando permissões...',
        'load_error' => 'Erro ao carregar funções.',
        'load_role_error' => 'Erro ao carregar dados da função',
        'load_permissions_error' => 'Erro ao carregar permissões.',
        'no_records' => 'Nenhuma função cadastrada.',
        'no_permissions' => 'Nenhuma permissão disponível.',
        'not_found' => 'Função não encontrada',
        'reserved_name' => 'Este nome de função é reservado pelo sistema',
        'save_error' => 'Erro ao salvar função',
        'delete_error' => 'Erro ao excluir função',
        'process_error' => 'Erro ao processar requisição',
        'deleting' => 'Excluindo...',
        'create_success' => 'Função Criada!',
        'update_success' => 'Função Atualizada!',
        'copy_created' => 'Copia Personalizada Criada!',
        'delete_confirm' => 'Tem certeza que deseja excluir a função ":name"?',
        'closing_countdown' => 'Fechando em :seconds segundos...',
    ],

    // Nomes dos modulos (para exibicao de permissoes)
    'module_names' => [
        'dashboard' => 'Dashboard',
        'locacoes' => 'Locações',
        'contratos' => 'Contratos',
        'veiculos' => 'Veículos',
        'clientes' => 'Clientes',
        'funcionarios' => 'Funcionarios',
        'financeiro' => 'Financeiro',
        'relatorios' => 'Relatórios',
        'configuracoes' => 'Configurações',
        'roles' => 'Funções',
        'matrizes_filiais' => 'Matrizes/Filiais',
        'empresas' => 'Empresas',
        'fornecedores' => 'Fornecedores',
        'acessorios' => 'Acessorios',
        'grupos' => 'Grupos de Veículos',
        'taxas_servicos' => 'Taxas e Serviços',
        'oficinas' => 'Oficinas',
        'localizar' => 'Localizar Veículo',
        'agenda' => 'Agenda',
        'website' => 'Website',
        'logs' => 'Logs do Sistema',
        'app_vistoria' => 'App Vistoria',
        'multas' => 'Multas',
        'promocoes' => 'Promoções',
        'manutencoes' => 'Manutenções',
        'manutencao' => 'Manutenção',
        'manutencoes_planos' => 'Planos de Manutenção',
        'formas' => 'Formas de Pagamento',
        'checklists' => 'Checklists',
        'checklist' => 'Checklist',
        'checklists_modelos' => 'Modelos de Checklist',
        'contas' => 'Contas Bancarias',
        'cartao' => 'Cartão',
        'documentos' => 'Documentos',
        'estoque' => 'Estoque',
        'acesso' => 'Controle de Acesso',
        'notificacoes' => 'Notificações',
        'whatsapp' => 'WhatsApp',
        'promissorias' => 'Promissórias',
        'feature_requests' => 'Pedir novo recurso',
        'reservas' => 'Reservas',
    ],
];
