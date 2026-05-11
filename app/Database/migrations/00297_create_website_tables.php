<?php

/**
 * Migration: Criar tabelas normalizadas do modulo Website
 *
 * Decompoe a tabela monolitica `site` em tabelas normalizadas:
 * site_config, site_credenciais, site_aparencia, site_presets,
 * site_conteudos, site_seo, site_integracoes, site_idiomas,
 * site_links, site_deploy_log
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. site_config - configuracao principal por tenant
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_config (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                dominio                 VARCHAR(255) NULL,
                status                  ENUM('inativo','pendente','ativo','suspenso') DEFAULT 'inativo',
                manutencao              TINYINT(1) DEFAULT 0,
                reserva_online          TINYINT(1) DEFAULT 1,
                overbooking             TINYINT(1) DEFAULT 0,
                pagamento_antecipado    TINYINT(1) DEFAULT 0,
                idioma_padrao           VARCHAR(5) DEFAULT 'pt_BR',
                whatsapp_flutuante      TINYINT(1) DEFAULT 1,
                whatsapp_numero         VARCHAR(20) NULL COMMENT 'Numero com codigo do pais, ex: 5527999999999',
                whatsapp_mensagem       VARCHAR(500) NULL COMMENT 'Mensagem padrao do WhatsApp flutuante',
                api_token               TEXT NULL COMMENT 'Token de autenticacao para API publica (encrypted)',
                versao                  VARCHAR(20) NULL COMMENT 'Versao do template no momento do ultimo deploy',
                ultimo_deploy_em        TIMESTAMP NULL,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_chave (chave),
                INDEX idx_dominio (dominio),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. site_credenciais - credenciais FTP (apenas senha criptografada)
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_credenciais (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                tipo                    ENUM('ftp','sftp') DEFAULT 'ftp',
                host                    VARCHAR(255) NOT NULL,
                porta                   INT UNSIGNED DEFAULT 21,
                usuario                 VARCHAR(255) NOT NULL,
                senha                   TEXT NOT NULL COMMENT 'Criptografado com encrypt()',
                diretorio               VARCHAR(255) NULL,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_chave (chave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. site_aparencia - tema/aparencia
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_aparencia (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                preset_cor              VARCHAR(30) DEFAULT 'azul' COMMENT 'Preset: azul, vermelho, verde, preto ou custom',
                cores_customizadas      JSON NULL COMMENT 'Override de CSS vars: {\"--cor-1\":\"#hex\",...}',
                css_customizado         TEXT NULL COMMENT 'CSS livre do tenant',
                css_customizado_backup  TEXT NULL COMMENT 'Snapshot para undo',
                fonte_primaria          VARCHAR(100) DEFAULT 'Titillium Web',
                fonte_url               VARCHAR(500) NULL COMMENT 'URL Google Fonts',
                logo                    VARCHAR(255) NULL COMMENT 'Nome do arquivo do logo (uploads/{chave}/)',
                logo_fundo_branco       TINYINT(1) DEFAULT 1 COMMENT 'Fundo branco no container do logo (navbar-brand)',
                logo_alinhamento        ENUM('esquerda','centro') DEFAULT 'centro' COMMENT 'Alinhamento do logo na navbar',
                favicon                 VARCHAR(255) NULL COMMENT 'Path do favicon',
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_chave (chave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. site_presets - presets de cor customizados do tenant
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_presets (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                nome                    VARCHAR(30) NOT NULL COMMENT 'Ex: roxo, laranja, personalizado',
                cores                   JSON NOT NULL COMMENT '{\"--cor-1\":\"#hex\",\"--cor-2\":\"#hex\",...}',
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                UNIQUE INDEX uniq_chave_nome (chave, nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 5. site_conteudos - conteudo por pagina/idioma
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_conteudos (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                idioma                  VARCHAR(5) NOT NULL DEFAULT 'pt_BR',
                pagina                  VARCHAR(30) NOT NULL COMMENT 'inicio, sobre, reserva, contato, veiculos',
                secao                   VARCHAR(30) NOT NULL DEFAULT 'principal' COMMENT 'Secao da pagina: 1, 2, 3 ou nome',
                conteudo                LONGTEXT NULL COMMENT 'HTML limpo (sem base64, sem urlencode)',
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                UNIQUE INDEX uniq_chave_idioma_pagina_secao (chave, idioma, pagina, secao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 6. site_seo - SEO por pagina/idioma
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_seo (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                idioma                  VARCHAR(5) NOT NULL DEFAULT 'pt_BR',
                pagina                  VARCHAR(30) NOT NULL,
                meta_titulo             VARCHAR(255) NULL,
                meta_descricao          VARCHAR(500) NULL,
                meta_keywords           VARCHAR(500) NULL,
                og_titulo               VARCHAR(255) NULL,
                og_descricao            VARCHAR(500) NULL,
                og_imagem               VARCHAR(500) NULL COMMENT 'URL da imagem Open Graph',
                dados_estruturados      JSON NULL COMMENT 'Schema.org JSON-LD',
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                UNIQUE INDEX uniq_chave_idioma_pagina (chave, idioma, pagina)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 7. site_integracoes - codigos GTM, Analytics, header/footer
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_integracoes (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                tipo                    ENUM('head','body_inicio','body_fim') NOT NULL
                                        COMMENT 'head=dentro do <head>; body_inicio=apos <body>; body_fim=antes de </body>',
                codigo                  MEDIUMTEXT NULL COMMENT 'HTML/JS raw',
                descricao               VARCHAR(100) NULL COMMENT 'Ex: Google Tag Manager, Facebook Pixel',
                ativo                   TINYINT(1) DEFAULT 1,
                ordem                   INT UNSIGNED DEFAULT 0,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                INDEX idx_chave_tipo_ativo (chave, tipo, ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 8. site_idiomas - idiomas habilitados por tenant
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_idiomas (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                idioma                  VARCHAR(5) NOT NULL COMMENT 'pt_BR, en_US, es_ES, it_IT, pt_PT',
                ativo                   TINYINT(1) DEFAULT 1,
                ordem                   INT UNSIGNED DEFAULT 0,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                UNIQUE INDEX uniq_chave_idioma (chave, idioma)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 9. site_links - redes sociais
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_links (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                tipo                    VARCHAR(30) NOT NULL COMMENT 'whatsapp, instagram, facebook, twitter, youtube, linkedin, tiktok',
                url                     VARCHAR(500) NOT NULL,
                ativo                   TINYINT(1) DEFAULT 1,
                ordem                   INT UNSIGNED DEFAULT 0,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                INDEX idx_chave_ativo (chave, ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 10. site_deploy_log - historico de deploys
        $this->execute("
            CREATE TABLE IF NOT EXISTS site_deploy_log (
                id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                   VARCHAR(45) NOT NULL,
                versao                  VARCHAR(20) NOT NULL,
                tipo                    ENUM('deploy','redeploy','update','rollback') NOT NULL,
                status                  ENUM('iniciado','sucesso','falha') NOT NULL,
                detalhes                JSON NULL COMMENT '{\"arquivos_enviados\":12,\"tempo_segundos\":8,\"erro\":\"...\"}',
                funcionario_id          INT UNSIGNED NULL,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                INDEX idx_chave_created (chave, created_at DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $tables = [
            'site_deploy_log',
            'site_links',
            'site_idiomas',
            'site_integracoes',
            'site_seo',
            'site_conteudos',
            'site_presets',
            'site_aparencia',
            'site_credenciais',
            'site_config',
        ];

        foreach ($tables as $table) {
            $this->drop($table);
        }
    }
};
