<?php

/**
 * Traduções do módulo Funcionários - Português (Brasil)
 */

return [
    // Títulos
    'title' => 'Funcionários',
    'title_singular' => 'Funcionário',
    'new_title' => 'Adicionar Novo Funcionário',
    'edit_title' => 'Editar Funcionário',
    'view_title' => 'Visualizar Funcionário',
    'list_title' => 'Lista de Funcionários',

    // Seções
    'sections' => [
        'employee_data' => 'Dados do Funcionário',
        'personal_data' => 'Dados Pessoais',
        'drivers_license' => 'Carteira de Motorista',
        'employment_data' => 'Dados Trabalhistas',
        'compensation' => 'Remuneração',
        'address' => 'Endereço',
        'contact' => 'Contato',
    ],

    // Campos do formulário
    'fields' => [
        'branch' => 'Matriz/Filial',
        'full_name' => 'Nome Completo',
        'email' => 'E-mail',
        'username' => 'Usuário',
        'password' => 'Senha',
        'new_password' => 'Nova Senha',
        'confirm_password' => 'Confirmar Senha',
        'confirm_new_password' => 'Confirmar Nova Senha',
        'password_hint' => '(deixe vazio para manter)',
        'role' => 'Função/Role',
        'cpf' => 'CPF',
        'nationality' => 'Nacionalidade',
        'gender' => 'Sexo',
        'marital_status' => 'Estado Civil',
        'cnh_number' => 'Nº da CNH',
        'cnh_registry' => 'Registro CNH',
        'cnh_expiry' => 'Validade da CNH',
        'work_card' => 'Carteira de Trabalho',
        'pis' => 'PIS',
        'salary' => 'Salário',
        'salary_type' => 'Tipo de Salário',
        'payment_day' => 'Dia de Pagamento',
        'zip_code' => 'CEP',
        'street' => 'Rua',
        'number' => 'Nº',
        'complement' => 'Complemento',
        'neighborhood' => 'Bairro',
        'city' => 'Cidade',
        'state' => 'Estado (UF)',
        'country' => 'País',
        'landline' => 'Tel. Fixo',
        'mobile' => 'Tel. Celular',
    ],

    // Opções de status
    'status_options' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ],

    // Opções de sexo
    'gender_options' => [
        'male' => 'Masculino',
        'female' => 'Feminino',
    ],

    // Opções de estado civil
    'marital_options' => [
        'single' => 'Solteiro(a)',
        'married' => 'Casado(a)',
        'divorced' => 'Divorciado(a)',
        'widowed' => 'Viúvo(a)',
    ],

    // Opções de tipo de salário
    'salary_type_options' => [
        'monthly' => 'Mensal',
        'biweekly' => 'Quinzenal',
        'weekly' => 'Semanal',
        'daily' => 'Diário',
    ],

    // Foto
    'photo' => [
        'alt' => 'Foto do Funcionário',
        'take_photo' => 'Tirar foto',
        'change_photo' => 'Alterar foto',
        'choose_title' => 'Escolher Foto',
        'choose_method' => 'Como deseja adicionar a foto?',
        'upload_file' => 'Enviar Arquivo',
        'use_camera' => 'Usar Câmera',
        'camera_title' => 'Tirar Foto',
        'capture' => 'Capturar',
    ],

    // Tabela de listagem
    'table' => [
        'name' => 'Nome',
        'username' => 'Usuário',
        'email' => 'E-mail',
        'role' => 'Função',
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    // Ações
    'actions' => [
        'add' => 'Adicionar Funcionário',
        'view' => 'Ver Funcionário',
        'edit' => 'Editar Funcionário',
        'delete' => 'Excluir Funcionário',
        'manage_roles' => 'Gerenciar Funções',
        'set_as_main' => 'Definir como principal',
    ],

    // Botões específicos
    'buttons' => [
        'save' => 'Salvar Funcionário',
        'save_changes' => 'Salvar Alterações',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar funcionário...',
        'select_option' => 'Selecione uma opção...',
        'select_role' => 'Selecione uma função...',
        'nationality' => 'Brasileira',
        'payment_day' => 'Ex: 5',
    ],

    // Dropdown de filiais
    'branch_dropdown' => [
        'loading' => 'Carregando...',
        'loading_branches' => 'Carregando filiais...',
        'load_error' => 'Erro ao carregar',
        'load_error_detail' => 'Erro ao carregar filiais',
        'no_branches' => 'Nenhuma filial cadastrada',
        'no_branches_short' => 'Nenhuma filial',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum funcionário encontrado',
        'unnamed' => 'Funcionário sem nome',
        'this_employee' => 'este funcionário',
        'id_not_found' => 'Erro: ID do funcionário não encontrado',
        'load_error' => 'Erro ao carregar funcionários',
        'server_error' => 'Erro ao conectar com o servidor',
        'not_found' => 'Funcionário não encontrado',
        'delete_error' => 'Erro ao excluir funcionário: :message',
        'save_error' => 'Erro ao salvar funcionário: :message',
        'update_error' => 'Erro ao atualizar funcionário: :message',
        'password_required' => 'A senha é obrigatória para novos funcionários.',
        'password_mismatch' => 'As senhas não coincidem. Por favor, verifique.',
        'passwords_dont_match' => 'As senhas não coincidem',
        'name_support_error' => 'O nome não pode conter o termo "suporte".',
        'username_support_error' => 'Nome de usuário não pode conter o termo "suporte".',
        'username_in_use' => 'Usuário já está em uso',
        'format_not_supported' => 'Formato não suportado. Use apenas JPEG, PNG ou WebP.',
        'image_too_large' => 'A imagem é muito grande. Por favor, selecione uma imagem menor que 5MB.',
        'camera_not_supported' => 'Seu navegador não suporta acesso à câmera. Use a opção de enviar arquivo.',
        'camera_access_denied' => 'Permissão de acesso à câmera negada. Por favor, permita o acesso e tente novamente.',
        'camera_not_found' => 'Nenhuma câmera encontrada. Use a opção de enviar arquivo.',
        'camera_error' => 'Não foi possível acessar a câmera.',
        'camera_initializing' => 'Aguarde a câmera inicializar completamente.',
    ],

    // Modal de exclusão (fallback local)
    'delete_modal' => [
        'title' => 'Confirmar Exclusão',
        'confirm_text' => 'EXCLUIR',
        'this_record' => 'este registro',
        'message' => 'Deseja realmente excluir o :type (:name)?',
        'type_placeholder' => 'Digite :text para confirmar',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro (para modal de exclusão)
    'record_type' => 'funcionário',
];
