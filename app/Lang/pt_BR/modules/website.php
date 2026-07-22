<?php

/**
 * Traduções do módulo Website - Português (Brasil)
 */

return [
    'title' => 'Website',

    // Status
    'status' => [
        'inativo'   => 'Inativo',
        'pendente'  => 'Pendente',
        'ativo'     => 'Ativo',
        'suspenso'  => 'Suspenso',
    ],

    // Ativação
    'activate_title'       => 'Ativar Website',
    'activate_description' => 'Tenha seu próprio site com domínio personalizado',
    'domain'               => 'Domínio',
    'domain_placeholder'   => 'ex: minhalocadora.com.br',
    'domain_required'      => 'Informe o domínio desejado',
    'domain_invalid'       => 'Domínio inválido. Use o formato: exemplo.com.br',
    'domain_available'     => 'Domínio disponível para registro!',
    'domain_taken'         => 'Domínio já está registrado.',
    'domain_check_unknown' => 'Não foi possível confirmar a disponibilidade. Tente novamente.',
    'domain_check_error'   => 'Não foi possível consultar o domínio agora. Tente novamente.',
    'select_domain_option'      => 'Selecione uma opção de domínio',
    'confirm_activation_title'  => 'Confirmar Ativação',
    'confirm_domain'            => 'Domínio',
    'confirm_charge_domain'     => 'Será cobrado o registro do domínio (a partir de R$60,00/ano).',
    'confirm_charge_hosting'    => 'Será cobrada a hospedagem (R$29,90/mês).',
    'confirm_activate'          => 'Confirmar e Ativar',
    'select_domain_option'      => 'Selecione uma opção de domínio',
    'confirm_activation_title'  => 'Confirmar Ativação',
    'confirm_domain'            => 'Domínio',
    'confirm_charge_domain'     => 'Será cobrado o registro do domínio (a partir de R$60,00/ano).',
    'confirm_charge_hosting'    => 'Será cobrada a hospedagem do site (R$29,90/mês).',
    'confirm_activate'          => 'Confirmar e Ativar',
    'want_domain'          => 'Quero registrar o domínio',
    'have_domain'          => 'Já tenho meu domínio (vou alterar o DNS)',
    'want_hosting'         => 'Quero contratar hospedagem',
    'no_hosting'           => 'Não preciso de hospedagem',
    'activate_button'      => 'Ativar seu site',
    'activation_requested' => 'Solicitação enviada! Você receberá a confirmação em breve.',
    'waiting_activation'   => 'Aguardando ativação',
    'waiting_message'      => 'Sua solicitação está sendo processada. Entraremos em contato em breve.',

    // Configurações
    'config_title'         => 'Configurações',
    'maintenance'          => 'Modo manutenção',
    'maintenance_help'     => 'Quando ativado, o site exibe uma página de manutenção',
    'online_reservation'   => 'Reserva online',
    'overbooking'          => 'Permitir overbooking',
    'advance_payment'      => 'Pagamento antecipado',
    'reservation_insurances' => 'Seguros da reserva',
    'vehicle_insurance_required' => 'Seguro do veículo obrigatório',
    'third_party_insurance_required' => 'Seguro para terceiros obrigatório',
    'insurance_required_help' => 'Quando ativo, o seguro será incluído obrigatoriamente nas reservas do website',
    'reserva_requer_confirmacao' => 'Reservas requerem confirmação manual',
    'reserva_requer_confirmacao_help' => 'Quando ativo, pedidos de reserva vindos do site ficam pendentes até você aprovar no painel',
    'precadastro_title'    => 'Pré-cadastro',
    'cadastro_simples'     => 'Cadastro simples',
    'cadastro_simples_help' => 'Quando ativo, o pré-cadastro pede apenas documento, nome, e-mail e celular (sem endereço)',
    'envio_documentos'     => 'Exigir envio de documentos',
    'envio_documentos_help' => 'Habilita upload de CNH, CPF, RG/Passaporte e Comprovante (máx. 5 MB por arquivo)',
    'docs_obrigatorios_help' => 'Marque os documentos obrigatórios. Os demais serão opcionais.',
    'passaporte'           => 'Passaporte',
    'comprovante'          => 'Comprovante de endereço',
    'default_language'     => 'Idioma padrão',
    'whatsapp_floating'    => 'WhatsApp flutuante',
    'whatsapp_number'      => 'Número do WhatsApp',
    'whatsapp_number_help' => 'Com código do país, ex: 5527999999999',
    'whatsapp_message'     => 'Mensagem padrão',

    // Aparência
    'appearance_title'   => 'Aparência',
    'color_preset'       => 'Preset de cores',
    'custom_colors'      => 'Cores customizadas',
    'custom_css'         => 'CSS customizado',
    'css_reset'          => 'Resetar CSS',
    'css_undo'           => 'Desfazer reset',
    'primary_font'       => 'Fonte primária',
    'font_url'           => 'URL Google Fonts',
    'site_logo'          => 'Logo do site',
    'site_logo_help'     => 'Independente do logo do cadastro da empresa',
    'favicon'            => 'Favicon',
    'logo_white_bg'      => 'Fundo branco atrás do logo',
    'logo_alignment'     => 'Posição do logo',
    'logo_left'          => 'Esquerda',
    'logo_center'        => 'Centro',
    'preset_name_reserved' => 'Este nome é reservado para presets do sistema',

    // Conteúdos
    'contents_title' => 'Conteúdos',
    'page'           => 'Página',
    'section'        => 'Seção',
    'language'       => 'Idioma',
    'pages'          => [
        'inicio'   => 'Página Inicial',
        'sobre'    => 'Sobre a Empresa',
        'reserva'  => 'Reserva',
        'contato'  => 'Contato',
        'veiculos' => 'Veículos',
    ],

    // SEO
    'seo_title'          => 'SEO',
    'meta_title'         => 'Título (meta title)',
    'meta_description'   => 'Descrição (meta description)',
    'meta_keywords'      => 'Palavras-chave',
    'og_title'           => 'Título Open Graph',
    'og_description'     => 'Descrição Open Graph',
    'og_image'           => 'Imagem Open Graph',
    'structured_data'    => 'Dados estruturados (JSON-LD)',

    // Banners
    'banners_title'  => 'Banners',
    'add_banner'     => 'Adicionar banner',
    'banner_image'   => 'Imagem',
    'banner_title'   => 'Título',
    'banner_message' => 'Mensagem',
    'banner_alt'     => 'Texto alternativo',
    'banner_link'    => 'URL de destino',
    'banner_target'  => 'Abrir em',
    'same_window'    => 'Mesma janela',
    'new_window'     => 'Nova janela',

    // Integrações
    'integrations_title' => 'Integrações',
    'add_integration'    => 'Adicionar código',
    'code_type'          => 'Posição',
    'code_types'         => [
        'head'         => 'Dentro do <head>',
        'body_inicio'  => 'Após abrir <body>',
        'body_fim'     => 'Antes de fechar </body>',
    ],
    'code_content'    => 'Código HTML/JS',
    'code_description'=> 'Descrição',

    // Links / Redes Sociais
    'social_links' => 'Redes sociais',
    'add_link'     => 'Adicionar rede',

    // Idiomas
    'languages_title' => 'Idiomas',
    'enabled'         => 'Ativo',

    // Deploy
    'publish_title'          => 'Publicar',
    'current_version'        => 'Versão atual',
    'template_version'       => 'Versão do template',
    'update_available'       => 'Atualização disponível',
    'up_to_date'             => 'Atualizado',
    'last_deploy'            => 'Último deploy',
    'deploy_history'         => 'Histórico de deploys',
    'deploy_button'          => 'Publicar site',
    'deploy_update_button'   => 'Atualizar para',
    'deploy_not_available'   => 'Deploy ainda não disponível. Funcionalidade em desenvolvimento.',
    'preview_not_available'  => 'Preview ainda não disponível. Funcionalidade em desenvolvimento.',
    'deploy_types' => [
        'deploy'   => 'Deploy',
        'redeploy' => 'Redeploy',
        'update'   => 'Atualização',
        'rollback' => 'Rollback',
    ],
    'deploy_status' => [
        'iniciado' => 'Iniciado',
        'sucesso'  => 'Sucesso',
        'falha'    => 'Falha',
    ],
];
