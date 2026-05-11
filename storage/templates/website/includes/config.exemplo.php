<?php
/**
 * Configuracao do site — Modelo
 * O arquivo config.php real e gerado automaticamente pelo sistema 7Carros no deploy.
 * NAO EDITAR MANUALMENTE — alteracoes serao sobrescritas no proximo deploy.
 */

return [
    // Identificacao
    'chave'              => 'CHAVE_DO_TENANT',
    'api_url'            => 'https://locadora.7carros.com',
    'api_token'          => 'TOKEN_DO_TENANT',

    // Empresa
    'nome_empresa'       => 'Nome da Locadora',
    'dominio'            => 'exemplo.com.br',

    // Idioma
    'idioma_padrao'      => 'pt_BR',
    'idiomas_ativos'     => ['pt_BR'],

    // WhatsApp
    'whatsapp_numero'    => '',
    'whatsapp_mensagem'  => '',
    'whatsapp_flutuante' => true,

    // Funcionalidades
    'reserva_online'       => true,
    'overbooking'          => false,
    'pagamento_antecipado' => false,
    'manutencao'           => false,

    // Aparencia
    'logo_url'           => '',
    'favicon_url'        => '',
    'logo_fundo_branco'  => true,
    'logo_alinhamento'   => 'centro',

    // Cache
    'cache_ttl'          => 3600,
    'cache_dir'          => __DIR__ . '/../cache/',

    // Versao do template (codigo do site) — vem de versao.json
    'versao'             => '1.0.0',

    // Token unico por deploy — usado como ?v={deploy} em CSS, JS, logo e favicon
    // para forçar invalidação de cache do navegador a cada publicação.
    // Gerado automaticamente em cada build (bin2hex(random_bytes(4))).
    'deploy'             => 'a1b2c3d4',
];
