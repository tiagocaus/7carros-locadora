<?php

/**
 * Traduções do módulo Funcionários - Português (Portugal)
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
        'contact' => 'Contacto',
    ],

    // Campos do formulário
    'fields' => [
        'branch' => 'Matriz/Filial',
        'full_name' => 'Nome Completo',
        'email' => 'E-mail',
        'username' => 'Utilizador',
        'password' => 'Palavra-passe',
        'new_password' => 'Nova Palavra-passe',
        'confirm_password' => 'Confirmar Palavra-passe',
        'confirm_new_password' => 'Confirmar Nova Palavra-passe',
        'password_hint' => '(deixe vazio para manter)',
        'role' => 'Função/Role',
        'cpf' => 'CPF',
        'nationality' => 'Nacionalidade',
        'gender' => 'Sexo',
        'marital_status' => 'Estado Civil',
        'cnh_number' => 'Nº da CNH',
        'cnh_registry' => 'Registo CNH',
        'cnh_expiry' => 'Validade da CNH',
        'work_card' => 'Carteira de Trabalho',
        'pis' => 'PIS',
        'salary' => 'Salário',
        'salary_type' => 'Tipo de Salário',
        'payment_day' => 'Dia de Pagamento',
        'zip_code' => 'Código Postal',
        'street' => 'Rua',
        'number' => 'Nº',
        'complement' => 'Complemento',
        'neighborhood' => 'Bairro',
        'city' => 'Cidade',
        'state' => 'Distrito',
        'country' => 'País',
        'landline' => 'Tel. Fixo',
        'mobile' => 'Telemóvel',
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
        'upload_file' => 'Enviar Ficheiro',
        'use_camera' => 'Usar Câmara',
        'camera_title' => 'Tirar Foto',
        'capture' => 'Capturar',
    ],

    // Tabela de listagem
    'table' => [
        'name' => 'Nome',
        'username' => 'Utilizador',
        'email' => 'Email',
        'role' => 'Função',
        'status' => 'Estado',
        'actions' => 'Ações',
    ],

    // Ações
    'actions' => [
        'add' => 'Adicionar Funcionário',
        'view' => 'Ver Funcionário',
        'edit' => 'Editar Funcionário',
        'delete' => 'Eliminar Funcionário',
        'manage_roles' => 'Gerir Funções',
        'set_as_main' => 'Definir como principal',
    ],

    // Botões específicos
    'buttons' => [
        'save' => 'Guardar Funcionário',
        'save_changes' => 'Guardar Alterações',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar funcionário...',
        'select_option' => 'Selecione uma opção...',
        'select_role' => 'Selecione uma função...',
        'nationality' => 'Portuguesa',
        'payment_day' => 'Ex: 5',
    ],

    // Dropdown de filiais
    'branch_dropdown' => [
        'loading' => 'A carregar...',
        'loading_branches' => 'A carregar filiais...',
        'load_error' => 'Erro ao carregar',
        'load_error_detail' => 'Erro ao carregar filiais',
        'no_branches' => 'Nenhuma filial registada',
        'no_branches_short' => 'Nenhuma filial',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum funcionário encontrado',
        'unnamed' => 'Funcionário sem nome',
        'this_employee' => 'este funcionário',
        'id_not_found' => 'Erro: ID do funcionário não encontrado',
        'load_error' => 'Erro ao carregar funcionários',
        'server_error' => 'Erro ao ligar ao servidor',
        'not_found' => 'Funcionário não encontrado',
        'delete_error' => 'Erro ao eliminar funcionário: :message',
        'save_error' => 'Erro ao guardar funcionário: :message',
        'update_error' => 'Erro ao atualizar funcionário: :message',
        'password_required' => 'A palavra-passe é obrigatória para novos funcionários.',
        'password_mismatch' => 'As palavras-passe não coincidem. Por favor, verifique.',
        'passwords_dont_match' => 'As palavras-passe não coincidem',
        'name_support_error' => 'O nome não pode conter o termo "suporte".',
        'username_support_error' => 'Nome de utilizador não pode conter o termo "suporte".',
        'username_in_use' => 'Utilizador já está em uso',
        'format_not_supported' => 'Formato não suportado. Use apenas JPEG, PNG ou WebP.',
        'image_too_large' => 'A imagem é muito grande. Por favor, selecione uma imagem menor que 5MB.',
        'camera_not_supported' => 'O seu navegador não suporta acesso à câmara. Use a opção de enviar ficheiro.',
        'camera_access_denied' => 'Permissão de acesso à câmara negada. Por favor, permita o acesso e tente novamente.',
        'camera_not_found' => 'Nenhuma câmara encontrada. Use a opção de enviar ficheiro.',
        'camera_error' => 'Não foi possível aceder à câmara.',
        'camera_initializing' => 'Aguarde a câmara inicializar completamente.',
    ],

    // Modal de exclusão (fallback local)
    'delete_modal' => [
        'title' => 'Confirmar Eliminação',
        'confirm_text' => 'ELIMINAR',
        'this_record' => 'este registo',
        'message' => 'Deseja realmente eliminar o :type (:name)?',
        'type_placeholder' => 'Digite :text para confirmar',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registro (para modal de exclusão)
    'record_type' => 'funcionário',
];
