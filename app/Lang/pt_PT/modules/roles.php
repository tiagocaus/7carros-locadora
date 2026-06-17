<?php

/**
 * Traduções do módulo Roles (Funções) - Português (Portugal)
 */

return [
    'title' => 'Gerir Funções',
    'title_singular' => 'Função',
    'new_title' => 'Nova Função',
    'edit_title' => 'Editar Função',
    'edit_prefix' => 'Editar:',

    // Secções
    'sections' => [
        'role_data' => 'Dados da Função',
        'permissions' => 'Permissões',
        'permissions_desc' => 'Selecione as permissões a que esta função terá acesso:',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome da Função',
        'description' => 'Descrição',
    ],

    // Marcadores
    'placeholders' => [
        'name' => 'Ex: Gerente, Atendente...',
        'name_full' => 'Ex: Gerente, Atendente, Motorista...',
        'description' => 'Descreva as responsabilidades...',
        'description_full' => 'Descreva as responsabilidades desta função...',
    ],

    // Etiquetas
    'badges' => [
        'system' => 'Sistema',
        'custom' => 'Personalizada',
    ],

    // Avisos
    'warnings' => [
        'system_role_title' => 'Função de Sistema',
        'system_role_desc' => 'Esta é uma função predefinida do sistema. Ao guardar as suas alterações, será criada uma <strong>cópia personalizada</strong> exclusiva para a sua empresa. A função original do sistema permanecerá inalterada.',
        'system_role_short' => 'Esta é uma função de sistema. Ao guardar, será criada uma cópia personalizada para a sua empresa.',
        'custom_role_title' => 'Função Personalizada',
        'custom_role_desc' => 'Esta é uma versão personalizada de uma função de sistema. O nome não pode ser alterado.',
        'name_locked' => 'Nome bloqueado (função personalizada do sistema)',
        'name_locked_title' => 'O nome não pode ser alterado em funções personalizadas do sistema',
        'irreversible' => 'Esta ação não pode ser desfeita.',
    ],

    // Ações
    'actions' => [
        'save_role' => 'Guardar Função',
        'save_changes' => 'Guardar Alterações',
        'create_copy' => 'Criar Cópia Personalizada',
        'delete_role' => 'Eliminar Função',
        'select_all' => 'Selecionar todos',
        'select_all_short' => 'Todos',
    ],

    // Mensagens
    'messages' => [
        'loading_roles' => 'A carregar funções...',
        'loading_permissions' => 'A carregar permissões...',
        'load_error' => 'Erro ao carregar funções.',
        'load_role_error' => 'Erro ao carregar dados da função',
        'load_permissions_error' => 'Erro ao carregar permissões.',
        'no_records' => 'Nenhuma função registada.',
        'no_permissions' => 'Nenhuma permissão disponível.',
        'not_found' => 'Função não encontrada',
        'reserved_name' => 'Este nome de função é reservado pelo sistema',
        'save_error' => 'Erro ao guardar função',
        'delete_error' => 'Erro ao eliminar função',
        'process_error' => 'Erro ao processar pedido',
        'deleting' => 'A eliminar...',
        'create_success' => 'Função Criada!',
        'update_success' => 'Função Atualizada!',
        'copy_created' => 'Cópia Personalizada Criada!',
        'delete_confirm' => 'Tem a certeza de que pretende eliminar a função ":name"?',
        'closing_countdown' => 'A fechar em :seconds segundos...',
    ],

    // Nomes dos módulos (para visualização de permissões)
    'module_names' => [
        'dashboard' => 'Dashboard',
        'locacoes' => 'Locações',
        'contratos' => 'Contratos',
        'veiculos' => 'Veículos',
        'clientes' => 'Clientes',
        'funcionarios' => 'Funcionários',
        'financeiro' => 'Financeiro',
        'relatorios' => 'Relatórios',
        'configuracoes' => 'Configurações',
        'roles' => 'Funções',
        'matrizes_filiais' => 'Matrizes/Filiais',
        'empresas' => 'Empresas',
        'fornecedores' => 'Fornecedores',
        'acessorios' => 'Acessórios',
        'grupos' => 'Grupos de Veículos',
        'taxas_servicos' => 'Taxas e Serviços',
        'oficinas' => 'Oficinas',
        'localizar' => 'Localizar Veículo',
        'agenda' => 'Agenda',
        'website' => 'Website',
        'logs' => 'Registos do Sistema',
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
        'contas' => 'Contas Bancárias',
        'cartao' => 'Cartão',
        'documentos' => 'Documentos',
        'estoque' => 'Stock',
        'acesso' => 'Controlo de Acesso',
        'notificacoes' => 'Notificações',
        'whatsapp' => 'WhatsApp',
        'promissorias' => 'Livranças',
        'feature_requests' => 'Pedir nova funcionalidade',
        'reservas' => 'Reservas',
    ],
];
