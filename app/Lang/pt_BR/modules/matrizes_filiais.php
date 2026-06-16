<?php

/**
 * Traduções do módulo Matrizes e Filiais - Português (Brasil)
 */

return [
    // Títulos
    'title' => 'Matrizes e Filiais',
    'title_singular' => 'Matriz/Filial',
    'new_title' => 'Adicionar Matriz/Filial',
    'edit_title' => 'Editar Matriz/Filial',
    'view_title' => 'Visualizar Matriz/Filial',
    'list_title' => 'Lista de Matrizes e Filiais',

    // Abas
    'tabs' => [
        'company_data' => 'Dados da Empresa',
        'settings' => 'Configurações',
        'nfse' => 'NFS-e',
        'locations' => 'Locais',
    ],

    // Seções
    'sections' => [
        'company_data' => 'Dados da Empresa',
        'address' => 'Endereço',
        'contact' => 'Contato',
        'business_hours' => 'Horários de Funcionamento',
        'schedule_exceptions' => 'Exceções de Horário',
        'holidays_suggestions' => 'Próximos Feriados (sugestões)',
        'locale_formatting' => 'Localização e Formatação',
        'numbering_sequences' => 'Sequências de Numeração',
        'numbering_sequences_desc' => 'Defina o próximo número a ser usado para cada tipo de documento.',
        'notifications' => 'Notificações',
        'print_settings' => 'Configurações de Impressão',
        'locations' => 'Locais de atendimento',
    ],

    // Descrições
    'descriptions' => [
        'locations' => 'Pontos adicionais onde essa matriz/filial atende. Ao escolher no site, a reserva é criada em nome desta matriz/filial (alias lógico).',
        'location_name' => 'Se vazio, exibe "Bairro, Cidade/UF" na listagem do site.',
        'cep_autofill' => 'Preenche rua, bairro, cidade e estado automaticamente.',
    ],

    // Botões
    'buttons' => [
        'add_location' => 'Adicionar local',
        'save_location' => 'Salvar local',
    ],

    // Modais
    'modals' => [
        'new_location' => 'Novo local',
        'edit_location' => 'Editar local',
    ],

    // Campos
    'fields' => [
        'type' => 'Tipo',
        'status' => 'Status',
        'trade_name' => 'Nome Fantasia',
        'company_name' => 'Razão Social',
        'cpf_cnpj' => 'CPF/CNPJ',
        'municipal_registration' => 'Inscrição Municipal',
        'state_registration' => 'Inscrição Estadual',
        'zip_code' => 'CEP',
        'street' => 'Rua',
        'number' => 'Número',
        'complement' => 'Complemento',
        'neighborhood' => 'Bairro',
        'city' => 'Cidade',
        'state' => 'Estado',
        'country' => 'País',
        'site' => 'Site',
        'locale' => 'Idioma/Região',
        'currency' => 'Moeda',
        'date_format' => 'Formato de Data',
        'datetime_format' => 'Formato de Data e Hora',
        'next_rental_number' => 'Próximo Nº Locação',
        'next_contract_number' => 'Próximo Nº Contrato',
        'next_financial_number' => 'Próximo Nº Financeiro',
        'notification_title' => 'Título padrão para notificações',
        'notification_title_placeholder' => 'Ex: Locadora XYZ - Notificação',
        'cep' => 'CEP',
        'location_name' => 'Nome (opcional)',
    ],

    // Placeholders
    'placeholders' => [
        'location_name' => 'Ex: Aeroporto de Vitória',
    ],

    // Opções de formatação
    'format_options' => [
        'currency_brl' => 'Real (R$)',
        'currency_usd' => 'Dólar (US$)',
        'currency_eur' => 'Euro (€)',
        'date_dmy' => 'DD/MM/AAAA',
        'date_mdy' => 'MM/DD/AAAA',
        'date_ymd' => 'AAAA-MM-DD',
        'datetime_dmy' => 'DD/MM/AAAA HH:MM:SS',
        'datetime_mdy' => 'MM/DD/AAAA HH:MM:SS',
        'datetime_ymd' => 'AAAA-MM-DD HH:MM:SS',
    ],

    // Interface NFS-e desta tela
    'nfse_ui' => [
        'choose_file' => 'Escolher arquivo',
        'no_file_selected' => 'Nenhum arquivo selecionado',
        'certificate_password_placeholder' => 'Senha do .pfx',
        'activate' => 'Ativar',
        'service_description_placeholder' => 'Locação de veículo automotor sem condutor.',
        'iss_due_1' => '1 - Exigível',
        'iss_due_2' => '2 - Não incidência',
        'iss_due_3' => '3 - Isenção',
        'iss_due_4' => '4 - Exportação',
        'iss_due_5' => '5 - Imunidade',
        'iss_due_6' => '6 - Susp. Judicial',
        'iss_due_7' => '7 - Susp. Adm.',
        'testing' => 'Testando...',
    ],

    // Locais de atendimento
    'locations' => [
        'no_address' => '(sem endereço)',
    ],

    // Confirmações
    'confirm' => [
        'title' => 'Confirmar',
    ],

    // Opções de tipo
    'type_options' => [
        'parent' => 'Matriz',
        'branch' => 'Filial',
    ],

    'status_options' => [
        'active' => 'Ativa',
        'inactive' => 'Inativa',
    ],

    // Contato
    'contact' => [
        'emails_title' => 'E-mails',
        'phones_title' => 'Telefones',
        'add' => 'Adicionar',
        'no_emails' => 'Nenhum e-mail cadastrado',
        'no_phones' => 'Nenhum telefone cadastrado',
        'email_placeholder' => 'email@exemplo.com',
        'description_placeholder' => 'Descrição (ex: Comercial)',
        'description_short' => 'Descrição',
        'main_email' => 'E-mail principal',
        'main_phone' => 'Telefone Principal',
        'remove' => 'Remover',
    ],

    // Horários
    'hours' => [
        'closed' => 'Fechado',
        'add_period' => 'Adicionar período',
        'remove_period' => 'Remover período',
        'copy_mon_to_weekdays' => 'Copiar Seg para Seg-Sex',
        'clear_all' => 'Limpar Todos',
        'confirm_clear_all' => 'Deseja realmente limpar todos os horários?',
        'configure_monday_first' => 'Configure primeiro os horários de Segunda-feira',
    ],

    // Exceções
    'exceptions' => [
        'new_exception' => 'Nova Exceção',
        'description_text' => 'Cadastre datas específicas com horário diferente ou fechamento (Black Friday, feriados locais, etc).',
        'no_exceptions' => 'Nenhuma exceção cadastrada',
        'closed' => 'Fechado',
        'special_hours' => 'Horário Especial',
        'description_placeholder' => 'Descrição (ex: Natal)',
        'already_registered' => 'Já cadastrado',
        'exception_added' => 'Exceção para :name adicionada',
        'already_exists' => 'Já existe uma exceção para esta data',
    ],

    // Feriados
    'holidays' => [
        'closed_btn' => 'Fechado',
        'special_hours_btn' => 'Horário Especial',
    ],

    // Notificações
    'notifications' => [
        'sms_title' => 'SMS',
        'sms_desc' => 'Enviar notificações por SMS',
        'email_title' => 'E-mail',
        'email_desc' => 'Enviar notificações por e-mail',
        'whatsapp_title' => 'WhatsApp',
        'whatsapp_desc' => 'Enviar notificações por WhatsApp',
    ],

    // Impressão
    'print' => [
        'bold_variables' => 'Variáveis em Negrito',
        'bold_variables_desc' => 'Destacar variáveis nos documentos impressos',
        'remove_yellow_stripe' => 'Remover Tarja Amarela',
        'remove_yellow_stripe_desc' => 'Remover destaque amarelo das variáveis',
    ],

    // Logo
    'logo' => [
        'alt' => 'Logo da Empresa',
        'change' => 'Alterar Logo',
    ],

    // Tabela
    'table' => [
        'type' => 'Tipo',
        'trade_name' => 'Nome Fantasia',
        'company_name' => 'Razão Social',
        'cpf_cnpj' => 'CPF/CNPJ',
        'city_state' => 'Cidade/UF',
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    // Ações
    'actions' => [
        'add' => 'Adicionar',
        'view' => 'Ver',
        'edit' => 'Editar',
        'delete' => 'Excluir',
    ],

    // Mensagens
    'messages' => [
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'no_records' => 'Nenhum registro encontrado',
        'delete_error' => 'Erro ao excluir: :message',
        'delete_has_links_title' => 'Matriz/Filial com vínculos',
        'delete_has_links_confirm' => 'Não é possível excluir esse registro, porque existe vínculos. Deseja desativá-la?',
        'deactivate_button' => 'Desativar matriz/filial',
        'deactivated' => 'Matriz/Filial desativada com sucesso',
        'deactivate_error' => 'Erro ao desativar matriz/filial',
        'save_error' => 'Erro ao salvar: :message',
        'id_not_found' => 'Erro: ID não encontrado',
        'format_not_supported' => 'Formato não suportado. Use apenas JPEG, PNG ou WebP.',
        'image_too_large' => 'Imagem muito grande. Máximo 5MB.',
        'this_record' => 'este registro',
        'nfse_save_first' => 'Salve primeiro para poder configurar a nota fiscal de serviço (NFS-e).',
        'locations_save_first' => 'Salve primeiro os dados da empresa para cadastrar locais de atendimento.',
        'no_locations' => 'Nenhum local cadastrado.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro (para modal de exclusão)
    'record_type' => 'matriz/filial',
];
