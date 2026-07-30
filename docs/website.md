# Módulo Website

## Visão Geral

O módulo Website permite que cada tenant (locadora) tenha seu próprio site público com domínio personalizado. O sistema oferece customização visual (cores, textos, CSS, banners), multi-idioma, SEO otimizado, reserva online integrada, e deploy automatizado via FTP.

**Princípio fundamental**: O template source fica protegido no servidor — apenas o HTML renderizado é enviado ao FTP do cliente. Isso impede cópia/roubo do template.

### Status Atual do Legado

| Métrica | Valor |
|---------|-------|
| Total de registros `site` | 169 |
| Com domínio configurado | 149 |
| Com FTP ativo (ativados) | 67 |
| Com domínio sem FTP (pendentes) | 82 |
| Sem domínio (inativos) | 20 |
| Banners cadastrados | 536 |
| Moedas usadas | BRL (R$), EUR (€) |
| Presets de cor usados | azul, vermelho, verde, preto |

### Funcionalidades do Módulo

| Feature | Descrição |
|---------|-----------|
| Ativação de site | Fluxo de solicitação com verificação DNS real |
| Customização visual | Presets de cor + override individual + CSS customizado |
| Conteúdo editável | Textos por página com WYSIWYG |
| Multi-idioma (i18n) | Conteúdo e UI em múltiplos idiomas |
| SEO | Meta tags, Open Graph, JSON-LD, sitemap.xml |
| GTM/Analytics | Códigos customizados no head/body |
| Banners | Carousel com upload, ordenação e links |
| Reserva online | Integração direta com BD do sistema |
| WhatsApp flutuante | Ícone no canto inferior direito |
| Manutenção | Modo de manutenção com página dedicada |
| Deploy FTP | Build + upload automatizado com versionamento |

---

## Fluxo de Ativação

### Estados do Site

```
inativo → pendente → ativo ↔ suspenso
                       ↕
                   manutencao
```

| Status | Descrição |
|--------|-----------|
| `inativo` | Nunca solicitou ativação |
| `pendente` | Solicitou ativação, aguardando configuração |
| `ativo` | Site funcionando, pode fazer deploy |
| `suspenso` | Desativado pelo admin 7Carros |

### Fluxo Completo

```
1. Tenant acessa menu "Website"
   └─ Se status = inativo → mostra tela "Ativar Website"
   └─ Se status = pendente → mostra "Aguardando ativação"
   └─ Se status = ativo → mostra painel de configurações

2. Tela "Ativar Website":
   ├─ Campo: domínio (ex: minhalocadora.com.br)
   ├─ Opção: "Quero registrar o domínio" / "Já tenho meu domínio (vou alterar o DNS)"
   ├─ Hospedagem contratada automaticamente com a 7Carros
   ├─ Botão "Verificar" → WhoisJSON consulta disponibilidade de registro
   └─ Botão "Ativar seu site" → envia email + muda status para pendente

3. Email para sac@hostcia.net contém:
   ├─ Nome da empresa (tenant)
   ├─ Chave do tenant
   ├─ Username de quem fez a solicitação
   ├─ Domínio solicitado
   ├─ Plano atual (nome e código)
   └─ Opção descritiva de registro de domínio

A hospedagem não é exibida no email porque é contratada automaticamente com a 7Carros.

Na tela de ativação, o site é apresentado como gratuito, mas a hospedagem é sempre cobrada mensalmente. O registro do domínio só é cobrado quando o cliente ainda não possui um domínio registrado.

4. Admin 7Carros configura no WHMCS:
   ├─ Cria conta de hospedagem
   ├─ Configura FTP
   └─ Clica botão no WHMCS → envia callback para o sistema

5. Callback do WHMCS (GET):
   ├─ URL: /api/webhook/whmcs/site-ativacao
   ├─ Parâmetros: chave, dados criptografados, secret
   ├─ Sistema valida TENANT_ONBOARD_SECRET
   ├─ Descriptografa e salva credenciais FTP (encrypt)
   ├─ Muda status para "ativo"
   └─ Executa deploy inicial para o FTP informado
```

### Verificação de Disponibilidade do Domínio (WhoisJSON)

```php
// WhoisJsonService::verificarDisponibilidade(string $dominio): array
// GET https://whoisjson.com/api/v1/domain-availability?domain=example.com
// Authorization: TOKEN={APIWHOISJSON_API_KEY}
// Retorna: ['dominio' => string, 'disponivel' => bool|null]
```

A consulta usa o endpoint de disponibilidade baseado em WHOIS/RDAP. `true` libera a solicitação de registro, `false` informa que o domínio já está registrado e `null` representa resultado inconclusivo, mantendo a ativação bloqueada. A integração usa timeout curto e não expõe a chave nem a resposta bruta ao navegador.

### Endpoint Callback WHMCS

```
GET /api/webhook/whmcs/site-ativacao
```

**Parâmetros:**

| Param | Tipo | Descrição |
|-------|------|-----------|
| `chave` | string | Chave do tenant |
| `secret` | string | TENANT_ONBOARD_SECRET para validação |
| `dados` | string | JSON criptografado (base64) com credenciais FTP |

**Formato do `dados` (após descriptografia):**
```json
{
    "ftp_host": "ftp.example.com",
    "ftp_usuario": "username",
    "ftp_senha": "password",
    "ftp_porta": 21,
    "ftp_diretorio": "/public_html"
}
```

**Segurança do callback:**
- O WHMCS criptografa o JSON dos dados usando `TENANT_ONBOARD_SECRET` como chave (AES-256-CBC)
- Converte para base64 para transporte seguro via GET
- O sistema valida o `secret` e descriptografa os dados
- As credenciais FTP são re-criptografadas com `encrypt()` (APP_KEY) antes de salvar no BD
- Chamadas repetidas após deploy bem-sucedido não atualizam credenciais nem executam novo deploy; apenas redirecionam para o domínio do site
- Rate limit: 10 requisições por minuto por IP
- Log de todas as chamadas para auditoria

**Variável de ambiente necessária:**
```env
TENANT_ONBOARD_SECRET=chave-secreta-compartilhada-com-whmcs
```

---

## Schema do Banco de Dados (Normalizado)

A tabela monolítica `site` será decomposta em tabelas normalizadas. Próxima migration disponível: **00297**.

### Tabela 1: `site_config` (configuração principal)

Substitui as colunas core da tabela `site`. Uma linha por tenant.

```sql
CREATE TABLE site_config (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    dominio                     VARCHAR(255) NULL,
    status                      ENUM('inativo','pendente','ativo','suspenso') DEFAULT 'inativo',
    manutencao                  TINYINT(1) DEFAULT 0,
    reserva_online              TINYINT(1) DEFAULT 1,
    overbooking                 TINYINT(1) DEFAULT 0,
    pagamento_antecipado        TINYINT(1) DEFAULT 0,
    seguro_carro_obrigatorio    TINYINT(1) DEFAULT 0,
    seguro_terceiros_obrigatorio TINYINT(1) DEFAULT 0,
    idioma_padrao               VARCHAR(5) DEFAULT 'pt_BR',
    whatsapp_flutuante          TINYINT(1) DEFAULT 1,
    whatsapp_numero             VARCHAR(20) NULL COMMENT 'Número com código do país, ex: 5527999999999',
    whatsapp_mensagem           VARCHAR(500) NULL COMMENT 'Mensagem padrão do WhatsApp flutuante',
    api_token                   TEXT NULL COMMENT 'Token de autenticação para API pública (encrypted)',
    versao                      VARCHAR(20) NULL COMMENT 'Versão do template no momento do último deploy',
    ultimo_deploy_em            TIMESTAMP NULL,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uniq_chave (chave),
    INDEX idx_dominio (dominio),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notas de migração:**
- `cor` (azul/vermelho/verde/preto) → move para `site_aparencia.preset_cor`
- `moeda`: removida de `site_config` — agora vem de `matrizes_filiais.currency_code` (dinâmico por filial de retirada)
- `manutencao`: `S` → `1`, `N` → `0`
- `reserva_online`, `overbooking`, `pagamento_antecipado`: idem
- `status`: calculado — se tem `login` = `ativo`, se tem `dominio` sem `login` = `pendente`, senão = `inativo`
- `whatsapp_numero` e `whatsapp_mensagem`: extraídos de `links` JSON

### Tabela 2: `site_credenciais` (credenciais FTP - criptografadas)

Separada por segurança. Todos os campos sensíveis são criptografados com `encrypt()`.

```sql
CREATE TABLE site_credenciais (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    tipo                        ENUM('ftp','sftp') DEFAULT 'ftp',
    host                        TEXT NOT NULL COMMENT 'Criptografado com encrypt()',
    porta                       INT UNSIGNED DEFAULT 21,
    usuario                     TEXT NOT NULL COMMENT 'Criptografado com encrypt()',
    senha                       TEXT NOT NULL COMMENT 'Criptografado com encrypt()',
    diretorio                   TEXT NULL COMMENT 'Criptografado com encrypt()',
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uniq_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Migração:**
- Parse `site.login` JSON: `{"username":"xxx","password":"yyy"}`
- Somente 67 registros têm dados
- O legado não armazena host/porta/diretório — definir defaults: host = dominio, porta = 21, diretório = `/public_html`
- Criptografar cada campo individualmente com `encrypt()`

### Tabela 3: `site_aparencia` (tema/aparência)

```sql
CREATE TABLE site_aparencia (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    preset_cor                  VARCHAR(30) DEFAULT 'azul' COMMENT 'Preset: azul, vermelho, verde, preto ou custom',
    cores_customizadas          JSON NULL COMMENT 'Override de CSS vars: {"--cor-1":"#hex",...}',
    css_customizado             TEXT NULL COMMENT 'CSS livre do tenant',
    css_customizado_backup      TEXT NULL COMMENT 'Snapshot para undo',
    fonte_primaria              VARCHAR(100) DEFAULT 'Titillium Web',
    fonte_url                   VARCHAR(500) NULL COMMENT 'URL Google Fonts',
    logo                        VARCHAR(255) NULL COMMENT 'Arquivo de logo do SITE em storage/uploads/{chave}; NULL usa logo_padrao.png',
    logo_fundo_branco           TINYINT(1) DEFAULT 1 COMMENT 'Fundo branco no container do logo (navbar-brand)',
    logo_alinhamento            ENUM('esquerda','centro') DEFAULT 'centro' COMMENT 'Alinhamento do logo na navbar',
    favicon                     VARCHAR(255) NULL COMMENT 'Path do favicon',
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uniq_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela 4: `site_presets` (presets de cor customizados)

Presets criados por tenants (além dos 4 fixos do sistema).

```sql
CREATE TABLE site_presets (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    nome                        VARCHAR(30) NOT NULL COMMENT 'Ex: roxo, laranja, personalizado',
    cores                       JSON NOT NULL COMMENT '{"--cor-1":"#hex","--cor-2":"#hex",...}',
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    UNIQUE INDEX uniq_chave_nome (chave, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Presets fixos do sistema** (definidos em `App\Config\WebsiteThemes.php`, não no BD):

```php
class WebsiteThemes
{
    public const PRESETS = [
        'azul' => [
            '--cor-1' => '#06858a', '--cor-2' => '#10ffc8', '--cor-3' => '#069da2',
            '--cor-4' => '#007254', '--cor-5' => '#04a482', '--cor-6' => '#079fa1',
            '--cor-7' => '#0062cc1a', '--cor-8' => '#ede500', '--cor-9' => '#ffc105',
            '--cor-10' => '#555',
        ],
        'vermelho' => [
            '--cor-1' => '#8a0606', '--cor-2' => '#ff1010', '--cor-3' => '#a20606',
            '--cor-4' => '#720007', '--cor-5' => '#a40404', '--cor-6' => '#a10707',
            '--cor-7' => '#cc00001a', '--cor-8' => '#ede500', '--cor-9' => '#ffc105',
            '--cor-10' => '#555',
        ],
        'verde' => [
            '--cor-1' => '#068a1e', '--cor-2' => '#10ff6e', '--cor-3' => '#06a22e',
            '--cor-4' => '#005a07', '--cor-5' => '#04a43a', '--cor-6' => '#07a13b',
            '--cor-7' => '#00cc1a1a', '--cor-8' => '#ede500', '--cor-9' => '#ffc105',
            '--cor-10' => '#555',
        ],
        'preto' => [
            '--cor-1' => '#333333', '--cor-2' => '#666666', '--cor-3' => '#444444',
            '--cor-4' => '#1a1a1a', '--cor-5' => '#555555', '--cor-6' => '#4a4a4a',
            '--cor-7' => '#0000001a', '--cor-8' => '#ede500', '--cor-9' => '#ffc105',
            '--cor-10' => '#555',
        ],
    ];
}
```

**Fluxo de resolução de cores:**
1. Se `preset_cor` está nos PRESETS fixos → usa `WebsiteThemes::PRESETS[$preset]`
2. Se `preset_cor` não está nos fixos → busca em `site_presets` pelo nome
3. Se `cores_customizadas` não é NULL → merge (override) sobre as cores do preset
4. Resultado final: array completo `--cor-1` a `--cor-10`

### Tabela 5: `site_conteudos` (conteúdo por idioma)

Substitui `texto_inicio`, `texto_sobre`, `texto_reserva`.

```sql
CREATE TABLE site_conteudos (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    idioma                      VARCHAR(5) NOT NULL DEFAULT 'pt_BR',
    pagina                      VARCHAR(30) NOT NULL COMMENT 'inicio, sobre, reserva, contato, veiculos',
    secao                       VARCHAR(30) NOT NULL DEFAULT 'principal' COMMENT 'Seção da página: 1, 2, 3 ou nome',
    conteudo                    LONGTEXT NULL COMMENT 'HTML limpo (sem base64, sem urlencode)',
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    UNIQUE INDEX uniq_chave_idioma_pagina_secao (chave, idioma, pagina, secao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Migração:**
- `texto_inicio` (JSON `{"1":"html","2":"html","3":"html"}`) → 3 registros com pagina=`inicio`, secao=`1`,`2`,`3`
- `texto_sobre` (HTML puro ou com entities) → 1 registro com pagina=`sobre`, secao=`principal`
- `texto_reserva` (JSON similar) → registros com pagina=`reserva`
- Normalizar: `html_entity_decode()` em todos os valores para consistência
- Todos salvos com idioma=`pt_BR`

### Tabela 6: `site_seo` (SEO por página/idioma)

```sql
CREATE TABLE site_seo (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    idioma                      VARCHAR(5) NOT NULL DEFAULT 'pt_BR',
    pagina                      VARCHAR(30) NOT NULL,
    meta_titulo                 VARCHAR(255) NULL,
    meta_descricao              VARCHAR(500) NULL,
    meta_keywords               VARCHAR(500) NULL,
    og_titulo                   VARCHAR(255) NULL,
    og_descricao                VARCHAR(500) NULL,
    og_imagem                   VARCHAR(500) NULL COMMENT 'URL da imagem Open Graph',
    dados_estruturados          JSON NULL COMMENT 'Schema.org JSON-LD',
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    UNIQUE INDEX uniq_chave_idioma_pagina (chave, idioma, pagina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela 7: `site_integracoes` (códigos GTM, Analytics, header/footer)

Substitui as colunas `header` e `footer`. Armazenado como **texto plano** (sem base64+urlencode).

```sql
CREATE TABLE site_integracoes (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    tipo                        ENUM('head','body_inicio','body_fim') NOT NULL
                                COMMENT 'head=dentro do <head>; body_inicio=após <body>; body_fim=antes de </body>',
    codigo                      MEDIUMTEXT NULL COMMENT 'HTML/JS raw',
    descricao                   VARCHAR(100) NULL COMMENT 'Ex: Google Tag Manager, Facebook Pixel',
    ativo                       TINYINT(1) DEFAULT 1,
    ordem                       INT UNSIGNED DEFAULT 0,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    INDEX idx_chave_tipo_ativo (chave, tipo, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Por que texto plano e não base64:**
- `urlencode(base64_encode())` é codificação, NÃO criptografia — não protege nada
- Impossibilita busca e auditoria no BD
- Adiciona complexidade desnecessária (encode/decode em todo read/write)
- Prepared statements do QueryBuilder já protegem contra SQL injection
- A proteção real é: sanitização na saída + auditoria de mudanças + permissão `website.editar`

**Migração:**
- Decodificar: `urldecode(base64_decode($header))` → salvar como texto plano
- `header` → tipo=`head`
- `footer` → tipo=`body_fim`
- Somente 4 registros têm dados

### Tabela 8: `site_idiomas` (idiomas habilitados)

```sql
CREATE TABLE site_idiomas (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    idioma                      VARCHAR(5) NOT NULL COMMENT 'pt_BR, en_US, es_ES, it_IT, pt_PT',
    ativo                       TINYINT(1) DEFAULT 1,
    ordem                       INT UNSIGNED DEFAULT 0,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    UNIQUE INDEX uniq_chave_idioma (chave, idioma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela 9: `site_banners` (aprimorar existente)

A tabela `site_banners` já existe. Adicionar colunas:

```sql
ALTER TABLE site_banners
    ADD COLUMN alt_text VARCHAR(255) NULL COMMENT 'Texto alternativo (acessibilidade)' AFTER mensagem,
    ADD COLUMN link_url VARCHAR(500) NULL COMMENT 'URL de destino ao clicar' AFTER alt_text,
    ADD COLUMN link_target ENUM('_self','_blank') DEFAULT '_blank' AFTER link_url,
    ADD COLUMN idioma VARCHAR(5) DEFAULT 'pt_BR' AFTER link_target,
    ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER idioma,
    ADD COLUMN ordem INT UNSIGNED DEFAULT 0 AFTER ativo;
```

### Tabela 10: `site_links` (redes sociais)

Substitui a coluna `links` (JSON).

```sql
CREATE TABLE site_links (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    tipo                        VARCHAR(30) NOT NULL COMMENT 'whatsapp, instagram, facebook, twitter, youtube, linkedin, tiktok',
    url                         VARCHAR(500) NOT NULL,
    ativo                       TINYINT(1) DEFAULT 1,
    ordem                       INT UNSIGNED DEFAULT 0,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    INDEX idx_chave_ativo (chave, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Migração:**
- Parse `links` JSON: `{"whatsapp":"url","instagram":"url","facebook":"url"}`
- Criar um registro por link (ignorar vazios)
- 167 registros têm dados

### Tabela 11: `site_deploy_log` (histórico de deploys)

```sql
CREATE TABLE site_deploy_log (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                       VARCHAR(45) NOT NULL,
    versao                      VARCHAR(20) NOT NULL,
    tipo                        ENUM('deploy','redeploy','update','rollback') NOT NULL,
    status                      ENUM('iniciado','sucesso','falha') NOT NULL,
    detalhes                    JSON NULL COMMENT '{"arquivos_enviados":12,"tempo_segundos":8,"erro":"..."}',
    funcionario_id              INT UNSIGNED NULL,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chave (chave),
    INDEX idx_chave_created (chave, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Migrations

| Migration | Descrição |
|-----------|-----------|
| `00297_create_website_tables.php` | Cria todas as tabelas novas |
| `00298_migrate_site_data.php` | Migra dados de `site` e `site_banners` para novas tabelas |
| `00299_rename_site_legacy.php` | Renomeia `site` para `_site_legacy` |
| `00300_add_website_permissions.php` | Adiciona novas permissões: `website.visualizar`, `website.configurar`, `website.deploy` |
| `00409_add_seguros_obrigatorios_site_config.php` | Adiciona obrigatoriedade independente para seguro do veículo e de terceiros |

---

## Arquitetura de Templates

### Localização dos Arquivos

```
storage/
  templates/
    website/
      index.php                     # Home (hero, busca, banners, grupos)
      sobre.php                     # Sobre a empresa
      veiculos.php                  # Grupos de veículos
      contato.php                   # Formulário de contato
      reserva.php                   # Wizard de reserva (5 etapas)
      painel.php                    # Portal do Cliente e do Investidor
      ajax-portal-login.php         # Proxy de login do portal
      ajax-portal-api.php           # Proxy autenticado dos recursos do portal
      ajax-portal-logout.php        # Logout e revogação da sessão
      portal-recibo.php             # Proxy autenticado do recibo em PDF
      includes/
        config.exemplo.php          # Modelo do config.php (gerado por tenant no deploy)
        header.php                  # Navbar + seletor de idioma
        footer.php                  # Footer + redes sociais + copyright
        head.php                    # <head> compartilhado (SEO, CSS, integrações)
        whatsapp-float.php          # Widget flutuante WhatsApp
        structured-data.php         # JSON-LD Schema.org
        manutencao.php              # Página de manutenção
        functions.php               # Helpers: t(), moeda(), cache, etc.
        api.php                     # Classe SiteApi — chama API do sistema
        portal-session.php          # Sessão PHP e CSRF locais do portal
      assets/
        css/
          style.css                 # CSS base com :root vars
          portal.css                # Fonte dos estilos do portal
          portal.min.css            # Versão publicada dos estilos do portal
          bg.png
          marcador.png
          v_retorno.png
          v_saida.png
        js/
          custom.js                 # JS (booking, horários, dataLayer)
          portal.js                 # Fonte da interface do portal
          portal.min.js             # Versão publicada da interface do portal
      lang/
        pt_BR.php                   # Traduções português
        en_US.php                   # Traduções inglês
        es_ES.php                   # Traduções espanhol
        it_IT.php                   # Traduções italiano
        pt_PT.php                   # Traduções português (Portugal)
      versao.json                   # {"versao":"1.3.0"}
```

**Por que `storage/templates/`:**
- Está fora do web root (`public/`) — inacessível via URL
- Segue o padrão do projeto para arquivos gerados/templates
- Um único diretório de template (sem versionamento por pasta)

O diretório `storage/templates/website/` é a fonte atual do template. Os
arquivos fonte CSS/JS não devem ser enviados ao FTP; o build publica as versões
minificadas.

### Arquitetura: Site em PHP com API

O site do cliente roda em PHP no hosting dele. Os dados (veículos, preços, filiais, horários) são puxados em tempo real via API do sistema. Reservas são salvas diretamente no BD do sistema via API.

```
┌──────────────────────────┐         ┌──────────────────────────┐
│   Hosting do Cliente     │         │   locadora.7carros.com   │
│   (PHP no FTP)           │         │   (Sistema principal)    │
│                          │  API    │                          │
│   index.php ─────────────┼────────>│ /api/public/dados-site   │
│   reserva.php ───────────┼────────>│ /api/public/disponibilidade│
│   reserva.php ───────────┼────────>│ /api/public/reserva      │
│   contato.php ───────────┼────────>│ /api/public/contato      │
│   painel.php + proxies ───┼────────>│ /api/public/portal/*     │
│                          │         │                          │
│   config.php             │         │  Valida chave + token    │
│   ├─ chave               │         │  Retorna dados do tenant │
│   ├─ api_url             │         │  Salva reserva no BD     │
│   └─ api_token           │         │  Envia email/WhatsApp    │
│                          │         │                          │
│   cache/ (file-based)    │         │                          │
│   └─ dados em disco      │         │                          │
│     (TTL configurável)   │         │                          │
└──────────────────────────┘         └──────────────────────────┘
```

### config.php (gerado por tenant no deploy)

```php
<?php
// Configuração do site — Gerado automaticamente pelo sistema 7Carros
// NÃO EDITAR MANUALMENTE — alterações serão sobrescritas no próximo deploy

return [
    // Identificação
    'chave'              => 'ABC123DEF456...',
    'api_url'            => 'https://locadora.7carros.com',
    'api_token'          => 'token-criptografado-do-tenant',

    // Empresa
    'nome_empresa'       => 'Minha Locadora',
    'dominio'            => 'minhalocadora.com.br',

    // Idioma
    'idioma_padrao'      => 'pt_BR',
    'idiomas_ativos'     => ['pt_BR', 'en_US'],

    // WhatsApp
    'whatsapp_numero'    => '5527999999999',
    'whatsapp_mensagem'  => 'Olá! Vim pelo site.',
    'whatsapp_flutuante' => true,

    // Funcionalidades
    'reserva_online'     => true,
    'overbooking'        => false,
    'pagamento_antecipado' => true,
    'manutencao'         => false,

    // Aparência — paths RELATIVOS (arquivos copiados pro deploy em assets/img/)
    'logo_url'           => 'assets/img/logo.webp',
    'favicon_url'        => 'assets/img/favicon.webp',
    'logo_fundo_branco'  => true,
    'logo_alinhamento'   => 'centro',

    // SEO (por página/idioma — carregado da API)
    // Integrações GTM (carregadas da API)
    // Conteúdos editáveis (carregados da API)

    // Cache
    'cache_ttl'          => 3600,  // 1 hora para dados gerais
    'cache_dir'          => __DIR__ . '/cache/',

    // Versão do template (código) — vem de versao.json, estável entre deploys
    'versao'             => '1.3.0',

    // Token único por deploy — 8 hex chars (ex: 'a1b2c3d4')
    // Regenerado em CADA publicação; usado como ?v={deploy} em assets locais
    'deploy'             => 'a1b2c3d4',
];
```

### Cache-busting por deploy

Todo arquivo estático local copiado pro deploy recebe `?v={deploy}` na URL pra forçar o navegador do visitante a buscar a versão nova após cada publicação.

**Como é gerado:** `WebsiteBuilderService::gerarConfig()` executa `bin2hex(random_bytes(4))` em cada build, gerando 8 caracteres hexadecimais. Esse valor é gravado no `config.php` como `'deploy'`.

**Onde é consumido:**

| Asset | Arquivo do template | Padrão |
|-------|---------------------|--------|
| `style.min.css` | `includes/head.php` | `assets/css/style.min.css?v={deploy}` |
| `custom.min.js` | `includes/footer.php` | `assets/js/custom.min.js?v={deploy}` |
| `portal.min.css` | `painel.php` | `assets/css/portal.min.css?v={deploy}` |
| `portal.min.js` | `painel.php` | `assets/js/portal.min.js?v={deploy}` |
| Favicon | `includes/head.php` | `{favicon_url}?v={deploy}` |
| Logo (header) | `includes/header.php` | `{logo_url}?v={deploy}` |
| Logo (manutenção) | `includes/manutencao.php` | `{logo_url}?v={deploy}` |

**Quando NÃO usar:**
- Imagens dinâmicas de grupos/banners — já vêm por `FileHelper::url()` que gera `/files/{token}` com HMAC; o token muda quando o arquivo muda, cache-busting implícito.
- CDNs externos (Bootstrap, jQuery, Font Awesome) — gerenciados pelo provedor.

**Diferença `versao` × `deploy`:**
- `versao` (vem de `versao.json`) — versão do **código do template**, só muda quando o template em si é atualizado.
- `deploy` — identificador único do **build**; muda a cada clique em "Publicar" mesmo que nada do código tenha mudado.

Para cache-busting de assets do cliente, sempre usar `deploy`, não `versao`.

### SiteApi (includes/api.php)

Classe que faz chamadas à API do sistema com cache local em arquivo. Além dos
métodos públicos do website, `portalRequest()` e `portalDocument()` fazem as
chamadas autenticadas do portal, enviando `X-Site-Token`, `X-Portal-Token` e os
headers do cliente encaminhados pelos proxies. O token do portal nunca é
retornado ao JavaScript.

```php
<?php
class SiteApi
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Dados completos do site — filiais, grupos, preços, horários, empresa
     * Cache: 1 hora (cache_ttl)
     */
    public function getDadosSite(): array
    {
        return $this->get('/api/public/dados-site', [], $this->config['cache_ttl']);
    }

    /**
     * Conteúdos editáveis (textos, SEO, integrações, banners, links)
     * Cache: 1 hora
     */
    public function getConteudos(string $idioma): array
    {
        return $this->get('/api/public/conteudos', ['idioma' => $idioma], $this->config['cache_ttl']);
    }

    /**
     * Disponibilidade e cotacao por grupo/plano — SEM cache.
     * O resultado depende da filial e do periodo selecionado.
     */
    public function getDisponibilidade(array $params): array
    {
        $params['chave'] = $this->config['chave'];
        $url = $this->config['api_url'] . '/api/public/disponibilidade?' . http_build_query($params);
        return $this->request('GET', $url);
    }

    /**
     * Criar reserva — SEM cache, chamada direta
     */
    public function criarReserva(array $dados): array
    {
        return $this->post('/api/public/reserva', $dados);
    }

    /**
     * Enviar formulário de contato — SEM cache
     */
    public function enviarContato(array $dados): array
    {
        return $this->post('/api/public/contato', $dados);
    }

    /**
     * Limpar cache local (chamado quando tenant publica mudanças)
     */
    public function limparCache(): void
    {
        $dir = $this->config['cache_dir'];
        array_map('unlink', glob("$dir*.cache"));
    }

    // --- Métodos internos ---

    private function get(string $endpoint, array $params, int $cacheTtl): array
    {
        $cacheKey = md5($endpoint . json_encode($params));
        $cacheFile = $this->config['cache_dir'] . $cacheKey . '.cache';

        // Verifica cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // Chama API
        $params['chave'] = $this->config['chave'];
        $url = $this->config['api_url'] . $endpoint . '?' . http_build_query($params);
        $response = $this->request('GET', $url);

        // Salva cache
        if (!is_dir($this->config['cache_dir'])) {
            mkdir($this->config['cache_dir'], 0755, true);
        }
        file_put_contents($cacheFile, json_encode($response));

        return $response;
    }

    private function post(string $endpoint, array $dados): array
    {
        $dados['chave'] = $this->config['chave'];
        $url = $this->config['api_url'] . $endpoint;
        return $this->request('POST', $url, $dados);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Site-Token: ' . $this->config['api_token'],
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}
```

### functions.php (includes/functions.php)

```php
<?php
// Carrega config e inicializa API
$config = require __DIR__ . '/config.php';
$api = new SiteApi($config);

// Detecta idioma da URL (/en/, /es/) ou usa padrão
$idioma = detectarIdioma($config);

// Carrega traduções
$traducoes = require __DIR__ . '/../lang/' . $idioma . '.php';

/**
 * Tradução — t('nav.inicio') retorna "Início"
 */
function t(string $key): string
{
    global $traducoes;
    $keys = explode('.', $key);
    $value = $traducoes;
    foreach ($keys as $k) {
        $value = $value[$k] ?? $key;
    }
    return is_string($value) ? $value : $key;
}

/**
 * Detecta idioma pela URL
 */
function detectarIdioma(array $config): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    foreach ($config['idiomas_ativos'] as $lang) {
        $prefix = '/' . substr($lang, 0, 2) . '/';
        if (strpos($uri, $prefix) === 0) {
            return $lang;
        }
    }
    return $config['idioma_padrao'];
}
```

### Como cada página PHP funciona

Exemplo do `index.php` no site do cliente:

```php
<?php
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/functions.php';

// Modo manutenção
if ($config['manutencao']) {
    include __DIR__ . '/includes/manutencao.php';
    exit;
}

// Dados do sistema (cache de 1h)
$dados = $api->getDadosSite();
$conteudos = $api->getConteudos($idioma);
$filiais = $dados['filiais'];
$grupos = $dados['grupos'];
$banners = $conteudos['banners'];
$links = $conteudos['links'];
$seo = $conteudos['seo']['inicio'] ?? [];
$integracoes = $conteudos['integracoes'];
$secoes = $conteudos['paginas']['inicio'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= substr($idioma, 0, 2) ?>">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <?php
    // Códigos GTM (body_inicio)
    foreach ($integracoes['body_inicio'] ?? [] as $code) {
        echo $code['codigo'];
    }
    ?>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- FORMULÁRIO DE RESERVA -->
    <?php if ($config['reserva_online']): ?>
    <div id="reserva">
        <h1><?= t('reserva.titulo') ?></h1>
        <form action="reserva.php" method="GET" id="form-reserva-topo">
            <select id="localRetirada" name="localRetirada" required
                    data-gtm-category="reserva" data-gtm-action="select" data-gtm-label="local-retirada">
                <option value="" disabled selected><?= t('reserva.selecione') ?></option>
                <?php foreach ($filiais as $f): ?>
                <option value="<?= $f['id'] ?>"
                        data-local="<?= $f['cidade'] ?>, <?= $f['estado'] ?>"
                        data-currency="<?= $f['currency_code'] ?>"
                        data-locale="<?= $f['locale'] ?>">
                    <?= $f['nome'] ?>
                </option>
                <?php endforeach; ?>
            </select>
            <!-- ... demais campos ... -->
        </form>
    </div>
    <?php endif; ?>

    <!-- BANNERS -->
    <div class="carousel slide" data-ride="carousel">
        <?php foreach ($banners as $i => $banner): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>"
             data-gtm-category="banner" data-gtm-action="view" data-gtm-label="banner-<?= $i+1 ?>">
            <img src="<?= $banner['foto_url'] ?>" alt="<?= $banner['alt_text'] ?? 'Banner ' . ($i+1) ?>">
        </div>
        <?php endforeach; ?>
    </div>

    <!-- GRUPOS DE VEÍCULOS -->
    <?php foreach ($grupos as $grupo): ?>
    <div class="grupo-card"
         data-gtm-category="veiculos" data-gtm-action="view" data-gtm-label="grupo-<?= $grupo['nome'] ?>">
        <img src="<?= $grupo['foto_url'] ?>" alt="<?= $grupo['nome'] ?>">
        <h2><?= $grupo['nome'] ?></h2>
        <p><?= $grupo['descricao'] ?></p>
    </div>
    <?php endforeach; ?>

    <!-- CONTEÚDO EDITÁVEL (seções customizadas pelo tenant) -->
    <?php foreach ($secoes as $secao): ?>
        <?= $secao['conteudo'] ?>
    <?php endforeach; ?>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php if ($config['whatsapp_flutuante']) include __DIR__ . '/includes/whatsapp-float.php'; ?>

    <?php
    // Códigos GTM (body_fim)
    foreach ($integracoes['body_fim'] ?? [] as $code) {
        echo $code['codigo'];
    }
    ?>
</body>
</html>
```

### Pipeline de Build (WebsiteBuilderService)

O build agora é mais simples — copia PHP, gera config e compila CSS:

```
┌───────────────────────────────────────────────────────────┐
│                      BUILD PIPELINE                        │
├───────────────────────────────────────────────────────────┤
│                                                            │
│  1. Criar diretório temporário                             │
│     storage/temp/website-build-{chave}/                    │
│                                                            │
│  2. COPIAR arquivos PHP do template                        │
│     storage/templates/website/*.php → temp/                │
│     storage/templates/website/includes/*.php → temp/       │
│     (exceto config.exemplo.php)                            │
│                                                            │
│  3. GERAR config.php com dados do tenant                   │
│     ├─ Consulta BD: site_config, site_aparencia            │
│     ├─ Gera api_token para o tenant                        │
│     ├─ Resolve cores (preset + override)                   │
│     └─ Escreve config.php no temp/includes/                │
│                                                            │
│  4. COMPILAR CSS                                           │
│     ├─ Lê style.css do template                            │
│     ├─ Substitui :root vars com cores do tenant            │
│     ├─ Append css_customizado se existir                   │
│     ├─ Minifica                                            │
│     └─ Salva como temp/assets/css/style.min.css            │
│                                                            │
│  5. COPIAR assets estáticos                                │
│     ├─ JS minificado → copia para temp/assets/js/custom.min.js │
│     ├─ portal.min.css → temp/assets/css/portal.min.css     │
│     ├─ portal.min.js → temp/assets/js/portal.min.js        │
│     └─ Imagens CSS → temp/assets/css/                      │
│                                                            │
│  6. COPIAR idiomas habilitados                             │
│     ├─ Consulta site_idiomas                               │
│     └─ Copia apenas lang/{idioma}.php dos ativos           │
│                                                            │
│  7. GERAR sitemap.xml e robots.txt                         │
│                                                            │
│  8. COPIAR versao.json                                     │
│                                                            │
│  9. CRIAR cache/ com .htaccess (Deny from all)             │
│                                                            │
│  10. Upload completo via FTP (phpseclib3)                  │
│                                                            │
│  11. Cleanup diretório temporário                          │
│                                                            │
│  12. Registrar em site_deploy_log                          │
│      Atualizar site_config.versao                          │
│                                                            │
└───────────────────────────────────────────────────────────┘
```

### Estrutura do Output (FTP)

```
/ (diretório FTP do cliente, ex: /public_html/)
├── index.php                   # Página inicial
├── sobre.php                   # Sobre a empresa
├── veiculos.php                # Grupos de veículos
├── contato.php                 # Formulário de contato
├── reserva.php                 # Wizard de reserva (5 etapas)
├── painel.php                  # Portal do Cliente e do Investidor
├── ajax-disponibilidade.php    # Proxy AJAX → /api/public/disponibilidade (mantém X-Site-Token server-side)
├── ajax-reserva.php            # Proxy AJAX → /api/public/reserva (injeta cliente_id de $_SESSION)
├── ajax-cliente-existe.php     # Proxy AJAX → /api/public/cliente-existe (check bool)
├── ajax-cliente-login.php      # Proxy AJAX → /api/public/cliente-login (grava $_SESSION['cliente_id'])
├── ajax-cliente-logout.php     # Destroi $_SESSION
├── ajax-cliente-senha-reset.php # Proxy AJAX → /api/public/cliente-senha-reset
├── ajax-portal-login.php       # Login do portal; guarda token apenas na sessão PHP
├── ajax-portal-api.php         # Proxy JSON autenticado + CSRF
├── ajax-portal-logout.php      # Revoga sessão opaca e limpa a sessão local
├── portal-recibo.php           # Proxy autenticado do recibo
├── sitemap.xml                 # Gerado no build
├── robots.txt                  # Gerado no build
├── versao.json                 # Versão do template
├── includes/
│   ├── config.php              # GERADO: chave, api_url, api_token, cores, flags
│   ├── header.php              # Navbar + seletor de idioma
│   ├── footer.php              # Footer + redes sociais
│   ├── head.php                # <head> SEO, CSS, integrações
│   ├── whatsapp-float.php      # Widget flutuante
│   ├── structured-data.php     # JSON-LD Schema.org
│   ├── manutencao.php          # Página de manutenção
│   ├── functions.php           # Helpers: t(), detectarIdioma()
│   ├── portal-session.php      # Cookie, sessão e CSRF do portal
│   └── api.php                 # Classe SiteApi (chamadas + cache)
├── assets/
│   ├── css/
│   │   ├── style.min.css       # COMPILADO: cores do tenant aplicadas
│   │   ├── portal.min.css      # Estilos minificados do portal
│   │   ├── bg.png
│   │   ├── marcador.png
│   │   ├── v_retorno.png
│   │   └── v_saida.png
│   └── js/
│       ├── custom.min.js       # Minificado
│       ├── portal.min.js       # Interface minificada do portal
│       ├── cep.min.js          # ViaCEP + zippopotam — autofill de endereço
│       └── chosen-select.min.js
├── lang/
│   ├── pt_BR.php               # Só idiomas habilitados
│   └── en_US.php               # (copiados do template)
└── cache/                      # Criado no build, cache de API em runtime
    └── .htaccess               # "Deny from all"
```

### API Pública (endpoints no sistema principal)

Endpoints que o site PHP do cliente chama:

| Endpoint | Método | Cache | Retorna |
|----------|--------|-------|---------|
| `/api/public/dados-site` | GET | 1h | Filiais (com `precos_grupos`, `valores_servicos`, moeda), grupos (metadados), serviços (`onde_usar=SITE`), empresa, flags `overbooking`, `cadastro_simples`, `envio_documentos`, `doc_*_obrigatorio`, `reserva_requer_confirmacao`, `pagamento_antecipado` |
| `/api/public/conteudos` | GET | 1h | Textos, SEO, integrações, banners, links (por idioma) |
| `/api/public/status` | GET | Não | Flags runtime: `manutencao`, `reserva_online`, `whatsapp_flutuante` |
| `/api/public/disponibilidade` | GET | Não | Disponibilidade e cotação por período, grupo e plano, incluindo ajustes de temporada |
| `/api/public/cliente-existe` | GET | Não | `{existe: bool}` — check se CPF/CNPJ já é cliente do tenant (neutro, sem dados pessoais) |
| `/api/public/cliente-login` | POST | Não | Autentica cliente com CPF ou email + senha. Retorna `{id, nome}` do cliente |
| `/api/public/cliente-senha-reset` | POST | Não | Gera **token one-time** em `cliente_password_resets` (hash SHA-256, TTL 60min), envia link `{APP_URL}/public/redefinir-senha?token=XXX` pelo template `cliente_nova_senha`. Resposta sempre neutra |
| `/public/redefinir-senha?token=XXX` | GET | Não | Form HTML standalone (sem depender do site) pra cliente definir nova senha. Valida token; se inválido/expirado, mostra aviso |
| `/api/public/cliente-senha-definir` | POST | Não | Recebe `token` + `senha` (+ `_csrf` da sessão criada no GET). Valida token, aplica `password_hash` Argon2id, marca token `used_at` |
| `/api/public/reserva` | POST | Não | Cria reserva, calcula total **server-side**, retorna `{codigo, total, pagamento_url?}` |
| `/api/public/promocao-validar` | POST | Não | Valida código no canal `SITE` e devolve total original, desconto e total final calculados no servidor |
| `/api/public/contato` | POST | Não | Envia mensagem de contato |
| `/api/public/limpar-cache` | POST | Não | Invalida cache do site (chamado ao publicar) |

As rotas `/api/public/portal/*` também são consumidas pelo website, mas usam
sessão opaca e proxies server-to-server próprios. A lista de endpoints,
recursos e regras de segurança está centralizada em
[Portal do Cliente e do Fornecedor Investidor](./portal-cliente-investidor.md).

O passo Pré-cadastro aceita código digitado ou carregado por `?promo=XXXXX`.
Quando o parâmetro chega pela página inicial, o formulário o preserva até
`reserva.php`.
A validação usa o grupo selecionado na reserva e rejeita promoções restritas a
outras categorias.
A prévia usa `ajax-promocao.php`, mas `/api/public/reserva` sempre repete a
validação antes de criar cliente, locação, financeiro ou cobrança. Consulte
[promocoes.md](./promocoes.md) para as regras de canal, filial, validade e dias.

**Autenticação da API:**
- endpoints comuns usam `X-Site-Token` com token único por tenant;
- endpoints autenticados do portal também usam `X-Portal-Token`, mantido
  exclusivamente na sessão PHP do website.
- Token gerado na ativação, armazenado em `site_config.api_token` (encrypted)
- CORS configurado para aceitar apenas o domínio do tenant

### Proteção do Template

| Camada | Mecanismo |
|--------|-----------|
| Código PHP | Funcional mas simples — a lógica de negócio está na API, não no site |
| API | Sem token válido, o site não funciona — os PHP sozinhos são inúteis |
| CSS | Compilado com cores do tenant, minificado |
| JS | Minificado |
| Cache | `.htaccess` bloqueia acesso direto |
| Propriedade | Footer "Powered by 7Carros.com" |
| Valor real | Está no sistema (API, BD, painel), não nos arquivos do site |

---

## Customização Visual

### Cores: Presets + Override + Presets Customizados

**Fluxo do usuário:**

```
1. Escolher preset base (azul, vermelho, verde, preto)
   └─ Ou criar preset customizado (ex: "roxo")
       └─ Define todas as 10 cores via color picker
       └─ Salva em site_presets com nome personalizado

2. Opcionalmente, sobrescrever cores individuais
   └─ Color picker por variável (--cor-1, --cor-2, etc.)
   └─ Salva em site_aparencia.cores_customizadas (JSON)

3. Preview em tempo real (iframe sandbox)

4. Deploy para aplicar
```

**Resolução de cores no build:**
```
preset base → merge cores_customizadas → :root final
```

### CSS Customizado

- Campo textarea no painel com editor de código (syntax highlight)
- Conteúdo salvo em `site_aparencia.css_customizado`
- No build, é adicionado **após** o CSS base — tem prioridade
- **Reset**: copia `css_customizado` para `css_customizado_backup`, depois limpa
- **Undo**: restaura de `css_customizado_backup`
- Preview disponível antes do deploy

### Logo do Site

**Importante**: O logo do site é **independente** do logo do cadastro da empresa. No sistema legado, ambos eram o mesmo, o que gerava confusão (ex: o cliente trocava o logo do site pensando que era só para o site e afetava o sistema inteiro, ou vice-versa).

**Upload:**
- Campo exclusivo na tela de Aparência para upload do logo do site
- Aceita apenas imagens: `jpg`, `jpeg`, `png`, `gif`, `svg`, `webp`
- Salvo em `storage/uploads/{chave}/`
- Processado via `ImageHelper` — convertido para WebP se aplicável
- Coluna: `site_aparencia.logo`
- Se `site_aparencia.logo` estiver vazio, o build usa `public/assets/img/logo_padrao.png`

**Opções de customização da navbar:**

| Opção | Coluna | Valores | Padrão |
|-------|--------|---------|--------|
| Fundo branco no container do logo | `logo_fundo_branco` | 1 (sim) / 0 (não) | 1 (sim) |
| Alinhamento do logo | `logo_alinhamento` | `esquerda` / `centro` | `centro` |

**Comportamento no CSS:**

```css
/* Fundo branco (padrão atual) */
.navbar-brand {
    background-color: #fff;    /* quando logo_fundo_branco = 1 */
    /* background-color: transparent;  quando logo_fundo_branco = 0 */
}

/* Alinhamento */
.navbar-topbar {
    /* quando logo_alinhamento = 'centro' (padrão atual) */
    justify-content: center;

    /* quando logo_alinhamento = 'esquerda' */
    /* justify-content: flex-start; */
}
```

**Na tela de aparência, mostrar:**
1. Upload de imagem (drag & drop ou botão)
2. Preview do logo na navbar
3. Toggle: "Manter fundo branco atrás do logo?" (sim/não)
4. Seletor: "Posição do logo" (Centro / Esquerda) — com preview em tempo real

### Banners do Site

**Upload:**
- A tela `Website > Banners` usa modal global em `app/Views/layouts/app.php`, aberto pelo iframe via `postMessage`. Não criar modal fullscreen dentro de `app/Views/pages/website/banners.php`.
- Aceita apenas imagens `jpg`, `jpeg`, `png` e `webp`.
- O frontend valida MIME e tamanho máximo de 5MB, processa a imagem em canvas e envia `foto_base64` no payload JSON.
- Ao criar banner, a imagem é obrigatória; ao editar, só é enviada quando o usuário escolhe uma nova imagem.
- O backend salva a imagem com `FileHelper::save($fotoBase64, 'banner')`, grava somente o filename em `site_banners.foto` e expõe a imagem via `FileHelper::url()`.
- Ao substituir ou excluir banner, remover o arquivo antigo com `FileHelper::delete()`.

**Exclusão no painel:**
- A rota `DELETE /api/website/banners/{id}` existe no backend por compatibilidade, mas o painel deve chamar `POST /api/website/banners/{id}/excluir`.
- Em hospedagens com bloqueio de método HTTP, chamadas `DELETE` podem retornar `403` antes de chegar ao PHP.
- Use `API.post('/api/website/banners/' + id + '/excluir')` na tela.
- Erros devem ser exibidos via modal global (`openAlert`/`openAlertModal`), nunca com `alert()` nativo.

### Fontes

- Padrão: Titillium Web (Google Fonts)
- Tenant pode trocar a fonte primária
- Salva nome da fonte + URL do Google Fonts em `site_aparencia`

---

## Multi-idioma (i18n)

### Estratégia de URL

Subdiretório (mais amigável para SEO):
```
https://example.com/          → idioma padrão (pt_BR)
https://example.com/en/       → inglês
https://example.com/es/       → espanhol
```

### Componentes i18n

| Componente | Armazenamento | Descrição |
|------------|---------------|-----------|
| Conteúdo das páginas | `site_conteudos` (por idioma) | Textos editáveis pelo tenant |
| Meta SEO | `site_seo` (por idioma) | Title, description, OG tags |
| Labels de UI do site público | `storage/templates/website/lang/{locale}.php` | Botões, menus, textos fixos — vai para o FTP do cliente |
| Labels de UI do painel (sistema) | `app/Lang/{locale}/modules/website.php` | Traduções do painel de configuração no sistema |
| Banners | `site_banners.idioma` | Banners por idioma |

> **Atenção:** São dois arquivos de tradução distintos. O `storage/templates/website/lang/` contém as strings do site público (o que o visitante vê) e é enviado ao FTP do cliente. O `app/Lang/{locale}/modules/website.php` contém as strings do painel de configuração dentro do sistema (o que o tenant vê ao configurar o site).

### Seletor de Idioma

- Dropdown no navbar com bandeiras
- Gerado em `_header.php`
- Links apontam para a versão no idioma correspondente da mesma página
- Tags `<link rel="alternate" hreflang="...">` no `<head>` para SEO

### Strings de UI do Site Público (lang/{locale}.php)

Estas traduções ficam em `storage/templates/website/lang/` e são enviadas ao FTP do cliente.

```php
// storage/templates/website/lang/pt_BR.php
return [
    'nav' => [
        'inicio' => 'Início',
        'sobre' => 'Sobre a empresa',
        'veiculos' => 'Veículos',
        'contato' => 'Contato',
        'painel_cliente' => 'Painel do cliente',
    ],
    'reserva' => [
        'titulo' => 'Faça sua reserva online',
        'local_retirada' => 'Local de retirada',
        'local_devolucao' => 'Local de devolução',
        'previsao_saida' => 'Previsão de saída',
        'previsao_chegada' => 'Previsão de chegada',
        'selecione' => 'Selecione',
        'continuar' => 'Continuar',
        // sufixo do preço (ex: "/diaria" em PT, "/day" em EN)
        'diaria_sufixo' => 'diaria',
        // planos de locação (tipos: KML/KMC/DIA)
        'plano_km_livre' => 'Km Livre',
        'plano_km_controlado' => 'Km Controlado',
        'plano_km_pago' => 'Km Pago',
        'plano_km_livre_desc' => 'paga apenas o valor da diaria',
        'plano_km_controlado_desc' => 'paga o valor da diaria, tem uma franquia de km por dia; ultrapassando, paga apenas o excedente',
        'plano_km_pago_desc' => 'paga o valor da diaria e cada km rodado',
        // estados do botão do grupo (passo 2)
        'btn_selecione_plano' => 'Selecione o plano',
        'btn_esgotado' => 'Esgotado',
        'btn_selecionar' => 'Selecionar',
        // ...
    ],
    'whatsapp' => [
        'mensagem_padrao' => 'Olá! Vim pelo site e gostaria de mais informações.',
    ],
    'footer' => [
        'direitos' => 'Todos os direitos reservados',
        'powered_by' => 'Powered by',
    ],
    'manutencao' => [
        'titulo' => 'Site em Manutenção',
        'mensagem' => 'Estamos realizando melhorias. Voltamos em breve!',
    ],
];
```

---

## SEO e Integrações

### SEO Implementado

| Feature | Implementação |
|---------|---------------|
| Meta tags | `<title>`, `<meta description>`, `<meta keywords>` por página |
| Open Graph | `og:title`, `og:description`, `og:image`, `og:url`, `og:type` |
| Twitter Cards | `twitter:card`, `twitter:title`, `twitter:description` |
| JSON-LD | `CarRental`, `LocalBusiness`, `WebSite`, `BreadcrumbList` |
| Sitemap.xml | Gerado automaticamente com todas as páginas/idiomas |
| Robots.txt | Gerado com referência ao sitemap |
| Hreflang | Tags `<link rel="alternate">` para cada idioma |
| Canonical | `<link rel="canonical">` por página |
| HTML semântico | `<header>`, `<main>`, `<footer>`, `<nav>`, `<article>` |
| Alt text | Em todas as imagens (banners, grupos, logo) |

### JSON-LD (Schema.org)

Gerado automaticamente a partir dos dados do tenant:

```json
{
    "@context": "https://schema.org",
    "@type": "CarRental",
    "name": "{{COMPANY_NAME}}",
    "url": "https://{{DOMINIO}}",
    "logo": "{{LOGO_URL}}",
    "telephone": "{{TELEFONE}}",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "{{ENDERECO}}",
        "addressLocality": "{{CIDADE}}",
        "addressRegion": "{{ESTADO}}",
        "addressCountry": "{{PAIS}}"
    },
    "openingHoursSpecification": [/* horários das filiais */],
    "priceRange": "$$"
}
```

### GTM / Analytics

O tenant cadastra seus códigos em `site_integracoes`:

| Posição | Uso típico |
|---------|------------|
| `head` | GTM head snippet, Google Analytics, meta verificação |
| `body_inicio` | GTM noscript, Facebook Pixel |
| `body_fim` | Scripts de chat, remarketing |

Cada código tem:
- `descricao` — para o tenant identificar (ex: "Google Tag Manager")
- `ativo` — pode desativar sem deletar
- `ordem` — controla ordem de injeção

### Rastreabilidade (Data Attributes para GTM)

O template vem **100% preparado** para que o cliente configure rastreamento de conversões, funil de reservas, cliques e comportamento de navegação no GTM/Analytics.

**Padrão atual em uso:** atributo `data-track="<evento_estável>"` em todos os botões e links-chave. É estável contra mudanças de tradução (não depende de `textContent`) e usa snake_case para nomes legíveis no GA4.

#### Eventos `data-track` implementados

| `data-track` | Onde |
|---|---|
| `home_buscar_reserva` | `index.php` — submit do form de busca |
| `contato_enviar` | `contato.php` — submit do form |
| `reserva_step1_continuar` | `reserva.php` — `#btn_reserva_inner` |
| `reserva_step2_selecionar_grupo` | `reserva.php` — `.btnSelecionarGrupo` (tem `data-id-grupo` para dimensão extra) |
| `reserva_step3_proximo` | `reserva.php` — ambos os botões Próximo (desktop + abaixo do resumo) |
| `reserva_step4_concluir` | `reserva.php` — ambos os botões Concluir |
| `reserva_pagar` | `reserva.php` — `#btn_pagar` |
| `reserva_nova` / `reserva_imprimir` | `reserva.php` — tela final |
| `nav_inicio` / `nav_sobre` / `nav_veiculos` / `nav_contato` / `nav_painel_cliente` | `header.php` |
| `footer_sobre` / `footer_veiculos` / `footer_contato` / `footer_painel_cliente` | `footer.php` |
| `social_<tipo>` | `footer.php` — dinâmico por `site_links.tipo` (whatsapp, instagram, etc.) |
| `whatsapp_flutuante` | `whatsapp-float.php` — ícone flutuante |

#### Como configurar no GTM

1. **Trigger**: `Click - All Elements` com filtro `Click Element matches CSS selector: [data-track]`.
2. **Variable**: *Data Layer Variable* ou *Custom JavaScript* que retorna `{{Click Element}}.getAttribute('data-track')`.
3. **Tag**: GA4 Event com `event_name = {{dataTrack}}` (ou um nome fixo `click_btn` e parâmetro `button_id = {{dataTrack}}` para agrupar).

> **Padrão legado `data-gtm-*`** (descrito abaixo) continua disponível em alguns elementos para compatibilidade com GTM antigos que já referenciem. Novos elementos seguem apenas `data-track`.

Também estão disponíveis:

1. **Data Attributes (`data-gtm-*`)** — em elementos clicáveis e formulários
2. **IDs semânticos** — em botões, formulários e seções-chave
3. **dataLayer.push()** — eventos JS disparados em ações importantes

#### Data Attributes no Template

Todos os elementos interativos devem ter atributos `data-gtm-*` para que o GTM consiga criar triggers facilmente:

**Navbar / Navegação:**
```html
<a class="nav-link" href="sobre.php"
   data-gtm-category="navegacao"
   data-gtm-action="click"
   data-gtm-label="sobre">Sobre a empresa</a>

<a class="nav-link" href="veiculos.php"
   data-gtm-category="navegacao"
   data-gtm-action="click"
   data-gtm-label="veiculos">Veículos</a>

<a class="nav-link" href="contato.php"
   data-gtm-category="navegacao"
   data-gtm-action="click"
   data-gtm-label="contato">Contato</a>
```

**Formulário de Reserva (Hero):**
```html
<form id="form-reserva-topo"
      data-gtm-category="reserva"
      data-gtm-action="submit"
      data-gtm-label="formulario-topo">

    <select id="localRetirada"
            data-gtm-category="reserva"
            data-gtm-action="select"
            data-gtm-label="local-retirada">

    <button type="submit" id="btn-reserva-topo"
            data-gtm-category="reserva"
            data-gtm-action="click"
            data-gtm-label="continuar-reserva">Continuar</button>
</form>
```

**Wizard de Reserva (5 etapas):**
```html
<!-- Cada etapa do wizard -->
<div id="reserva-etapa-1" data-gtm-step="1" data-gtm-step-name="local-datas">
<div id="reserva-etapa-2" data-gtm-step="2" data-gtm-step-name="veiculo">
<div id="reserva-etapa-3" data-gtm-step="3" data-gtm-step-name="servicos">
<div id="reserva-etapa-4" data-gtm-step="4" data-gtm-step-name="dados-pessoais">
<div id="reserva-etapa-5" data-gtm-step="5" data-gtm-step-name="confirmacao">

<!-- Seleção de veículo -->
<div class="grupo-veiculo"
     data-gtm-category="reserva"
     data-gtm-action="select"
     data-gtm-label="grupo-{{GRUPO_NOME}}"
     data-gtm-value="{{GRUPO_PRECO}}">

<!-- Botões de navegação entre etapas -->
<button id="btn-reserva-proximo"
        data-gtm-category="reserva"
        data-gtm-action="click"
        data-gtm-label="proximo-etapa-{{N}}">Próximo</button>

<button id="btn-reserva-voltar"
        data-gtm-category="reserva"
        data-gtm-action="click"
        data-gtm-label="voltar-etapa-{{N}}">Voltar</button>
```

**Contato:**
```html
<form id="form-contato"
      data-gtm-category="contato"
      data-gtm-action="submit"
      data-gtm-label="formulario-contato">

    <button type="submit" id="btn-enviar-contato"
            data-gtm-category="contato"
            data-gtm-action="click"
            data-gtm-label="enviar-mensagem">Enviar</button>
</form>
```

**WhatsApp / Telefone / Social:**
```html
<a href="tel:..."
   data-gtm-category="contato"
   data-gtm-action="click"
   data-gtm-label="telefone">

<a class="whatsapp-float"
   data-gtm-category="contato"
   data-gtm-action="click"
   data-gtm-label="whatsapp-flutuante">

<a href="https://instagram.com/..."
   data-gtm-category="social"
   data-gtm-action="click"
   data-gtm-label="instagram">
```

**Banners:**
```html
<div class="carousel-item"
     data-gtm-category="banner"
     data-gtm-action="view"
     data-gtm-label="banner-{{POSICAO}}"
     data-gtm-banner-titulo="{{TITULO}}">
```

**Veículos:**
```html
<div class="grupo-card"
     data-gtm-category="veiculos"
     data-gtm-action="view"
     data-gtm-label="grupo-{{GRUPO_NOME}}">
```

#### DataLayer Events (JS)

O template inclui um script que dispara eventos no `dataLayer` para ações importantes:

```javascript
// Inicialização do dataLayer
window.dataLayer = window.dataLayer || [];

// ---- EVENTOS AUTOMÁTICOS ----

// Página carregada
dataLayer.push({
    'event': 'page_view',
    'page_type': '{{PAGINA}}',       // inicio, sobre, veiculos, contato, reserva
    'site_language': '{{IDIOMA}}'
});

// Seleção de local de retirada
document.getElementById('localRetirada').addEventListener('change', function() {
    dataLayer.push({
        'event': 'reserva_local_selecionado',
        'local_retirada': this.options[this.selectedIndex].text,
        'filial_id': this.value
    });
});

// Submissão do formulário de reserva (topo)
document.getElementById('form-reserva-topo').addEventListener('submit', function() {
    dataLayer.push({
        'event': 'reserva_iniciada',
        'local_retirada': document.getElementById('localRetirada').value,
        'data_saida': document.getElementById('dataSaida').value,
        'data_chegada': document.getElementById('dataPrevista').value
    });
});

// Avanço de etapa no wizard
function avancarEtapa(etapaAtual, proximaEtapa) {
    dataLayer.push({
        'event': 'reserva_etapa_avancou',
        'etapa_de': etapaAtual,
        'etapa_para': proximaEtapa
    });
}

// Abandono de etapa (saiu da página sem completar)
window.addEventListener('beforeunload', function() {
    var etapaAtual = document.querySelector('[data-gtm-step].active');
    if (etapaAtual) {
        dataLayer.push({
            'event': 'reserva_abandono',
            'etapa_abandonada': etapaAtual.getAttribute('data-gtm-step'),
            'etapa_nome': etapaAtual.getAttribute('data-gtm-step-name')
        });
    }
});

// Seleção de local de devolução
document.getElementById('localDevolucao').addEventListener('change', function() {
    dataLayer.push({
        'event': 'reserva_local_devolucao_selecionado',
        'local_devolucao': this.options[this.selectedIndex].text,
        'filial_id': this.value
    });
});

// Seleção de data/hora
document.getElementById('dataSaida').addEventListener('change', function() {
    dataLayer.push({ 'event': 'reserva_data_saida', 'data': this.value });
});
document.getElementById('dataPrevista').addEventListener('change', function() {
    dataLayer.push({ 'event': 'reserva_data_chegada', 'data': this.value });
});

// Seleção de grupo de veículo
function selecionarGrupo(grupoNome, grupoPreco) {
    dataLayer.push({
        'event': 'reserva_veiculo_selecionado',
        'grupo_nome': grupoNome,
        'grupo_preco': grupoPreco
    });
}

// Seleção de plano (KM Livre, Controlado, Diária)
document.querySelectorAll('input[name="plano"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var partes = this.value.split('|');
        dataLayer.push({
            'event': 'reserva_plano_selecionado',
            'plano_tipo': partes[0],     // KML, KMC, DIA
            'grupo_id': partes[1]
        });
    });
});

// Toggle de seguro (veículo e terceiros)
function toggleSeguro(seguroTipo, ativo, valor) {
    dataLayer.push({
        'event': ativo ? 'seguro_adicionado' : 'seguro_removido',
        'seguro_tipo': seguroTipo,   // 'veiculo' ou 'terceiros'
        'seguro_valor': valor
    });
}

// Seleção de serviço adicional (GPS, cadeirinha, etc.)
function toggleServico(servicoNome, ativo, valor) {
    dataLayer.push({
        'event': ativo ? 'servico_adicionado' : 'servico_removido',
        'servico_nome': servicoNome,
        'servico_valor': valor
    });
}

// Alteração no valor total do resumo
function resumoAtualizado(valorTotal, qtdDiarias) {
    dataLayer.push({
        'event': 'reserva_resumo_atualizado',
        'valor_total': valorTotal,
        'qtd_diarias': qtdDiarias
    });
}

// Início do preenchimento de dados cadastrais (first interaction)
var cadastroIniciado = false;
document.querySelectorAll('#reserva-etapa-4 input').forEach(function(input) {
    input.addEventListener('focus', function() {
        if (!cadastroIniciado) {
            cadastroIniciado = true;
            dataLayer.push({ 'event': 'reserva_cadastro_iniciado' });
        }
    });
});

// Tipo de documento selecionado (CPF vs Passaporte)
document.querySelectorAll('input[name="tipo"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        dataLayer.push({
            'event': 'reserva_tipo_documento',
            'tipo_documento': this.value   // 'PF' ou 'ES'
        });
    });
});

// Reserva concluída (conversão principal)
function reservaConcluida(codigoReserva, valorTotal, grupoNome, planoTipo, qtdDiarias) {
    dataLayer.push({
        'event': 'reserva_concluida',
        'transaction_id': codigoReserva,
        'value': valorTotal,
        'currency': moedaAtual,       // dinâmico da filial
        'grupo_nome': grupoNome,
        'plano_tipo': planoTipo,
        'qtd_diarias': qtdDiarias
    });
}

// Formulário de contato enviado
document.getElementById('form-contato')?.addEventListener('submit', function() {
    dataLayer.push({
        'event': 'contato_enviado'
    });
});

// Click no WhatsApp flutuante
document.querySelector('.whatsapp-float')?.addEventListener('click', function() {
    dataLayer.push({
        'event': 'whatsapp_click',
        'origem': 'flutuante'
    });
});

// Click em telefone
document.querySelectorAll('[data-gtm-label="telefone"]').forEach(function(el) {
    el.addEventListener('click', function() {
        dataLayer.push({ 'event': 'telefone_click' });
    });
});
```

#### Mapa de Eventos para GTM

Tabela de referência para o cliente configurar triggers no GTM:

| Evento | Quando dispara | Variáveis disponíveis |
|--------|---------------|----------------------|
| **Geral** | | |
| `page_view` | Página carregada | `page_type`, `site_language` |
| **Reserva — Etapa 1: Local e Data** | | |
| `reserva_local_selecionado` | Selecionou local de retirada | `local_retirada`, `filial_id` |
| `reserva_local_devolucao_selecionado` | Selecionou local de devolução | `local_devolucao`, `filial_id` |
| `reserva_data_saida` | Selecionou data de saída | `data` |
| `reserva_data_chegada` | Selecionou data de chegada | `data` |
| `reserva_iniciada` | Clicou "Continuar" no formulário topo | `local_retirada`, `data_saida`, `data_chegada` |
| `reserva_etapa_avancou` | Avançou etapa no wizard | `etapa_de`, `etapa_para` |
| `reserva_abandono` | Saiu da página durante reserva | `etapa_abandonada`, `etapa_nome` |
| **Reserva — Etapa 2: Veículo** | | |
| `reserva_veiculo_selecionado` | Escolheu grupo de veículo | `grupo_nome`, `grupo_preco` |
| `reserva_plano_selecionado` | Escolheu plano (KM Livre/Controlado/Diária) | `plano_tipo`, `grupo_id` |
| **Reserva — Etapa 3: Adicionais** | | |
| `seguro_adicionado` | Marcou seguro | `seguro_tipo`, `seguro_valor` |
| `seguro_removido` | Desmarcou seguro | `seguro_tipo`, `seguro_valor` |
| `servico_adicionado` | Marcou serviço adicional (GPS, cadeirinha, etc.) | `servico_nome`, `servico_valor` |
| `servico_removido` | Desmarcou serviço adicional | `servico_nome`, `servico_valor` |
| `reserva_resumo_atualizado` | Valor total do resumo mudou | `valor_total`, `qtd_diarias` |
| **Reserva — Etapa 4: Dados Cadastrais** | | |
| `reserva_cadastro_iniciado` | Primeiro campo do formulário focado | — |
| `reserva_tipo_documento` | Selecionou CPF ou Passaporte | `tipo_documento` |
| **Reserva — Etapa 5: Confirmação** | | |
| `reserva_concluida` | Reserva finalizada (CONVERSÃO PRINCIPAL) | `transaction_id`, `value`, `currency`, `grupo_nome`, `plano_tipo`, `qtd_diarias` |
| **Contato / Social** | | |
| `contato_enviado` | Enviou formulário de contato | — |
| `whatsapp_click` | Clicou no WhatsApp flutuante | `origem` |
| `telefone_click` | Clicou em link de telefone | — |

#### IDs Semânticos Obrigatórios

Todos estes IDs devem existir no template para que o GTM consiga criar triggers por Element ID:

| ID | Elemento | Página | Etapa |
|----|----------|--------|-------|
| **Index (Hero)** | | | |
| `form-reserva-topo` | Formulário de reserva hero | index | — |
| `btn-reserva-topo` | Botão "Continuar" do hero | index | — |
| **Reserva — Etapa 1** | | | |
| `reserva-etapa-1` | Container da etapa 1 | reserva | 1 |
| `localRetirada` | Select local de retirada | index, reserva | 1 |
| `localDevolucao` | Select local de devolução | index, reserva | 1 |
| `dataSaida` | Input data de saída | index, reserva | 1 |
| `horaSaida` | Select hora de saída | index, reserva | 1 |
| `dataPrevista` | Input data de chegada | index, reserva | 1 |
| `horaDevolucao` | Select hora de devolução | index, reserva | 1 |
| `btn-reserva-etapa1` | Botão "Continuar" etapa 1 | reserva | 1 |
| **Reserva — Etapa 2** | | | |
| `reserva-etapa-2` | Container da etapa 2 | reserva | 2 |
| `plano-grupo-{ID}` | Radio buttons de plano por grupo | reserva | 2 |
| `btn-selecionar-grupo-{ID}` | Botão "Selecionar" do grupo | reserva | 2 |
| **Reserva — Etapa 3** | | | |
| `reserva-etapa-3` | Container da etapa 3 | reserva | 3 |
| `seguro-veiculo` | Checkbox seguro do veículo | reserva | 3 |
| `seguro-terceiros` | Checkbox seguro para terceiros | reserva | 3 |
| `servico-{NOME}` | Checkbox de cada serviço adicional | reserva | 3 |
| `btn-reserva-etapa3` | Botão "Próximo" etapa 3 | reserva | 3 |
| **Reserva — Etapa 4** | | | |
| `reserva-etapa-4` | Container da etapa 4 | reserva | 4 |
| `cpf_cnpj` | Input documento | reserva | 4 |
| `nome_rsocial` | Input nome completo | reserva | 4 |
| `email` | Input email | reserva | 4 |
| `tel_cel` | Input celular | reserva | 4 |
| `cep` | Input CEP | reserva | 4 |
| `concluir_reserva` | Botão "Concluir reserva" | reserva | 4 |
| **Reserva — Etapa 5** | | | |
| `reserva-etapa-5` | Container da etapa 5 (confirmação) | reserva | 5 |
| `codigo-reserva` | Span com o código gerado | reserva | 5 |
| **Contato** | | | |
| `form-contato` | Formulário de contato | contato | — |
| `btn-enviar-contato` | Botão enviar contato | contato | — |

### WhatsApp Flutuante

```html
<!-- _whatsapp_float.php -->
<a href="https://api.whatsapp.com/send?phone={{WHATSAPP}}&text={{WHATSAPP_MSG}}"
   target="_blank"
   class="whatsapp-float"
   aria-label="Fale conosco pelo WhatsApp">
    <i class="fa fa-whatsapp"></i>
</a>
```

CSS do widget:
```css
.whatsapp-float {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background: #25d366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 9999;
    transition: transform 0.3s;
}
.whatsapp-float:hover {
    transform: scale(1.1);
    color: white;
    text-decoration: none;
}
```

Controlado por `site_config.whatsapp_flutuante` (1/0).

---

## Reserva Online

### Integração Direta com o BD

O site faz chamada API ao sistema para criar a reserva. O fluxo:

```
1. Cliente preenche formulário de reserva no site
   ├─ Local de retirada/devolução (filial)
   ├─ Data/hora saída e chegada
   ├─ Grupo/categoria de veículo desejado
   ├─ Serviços adicionais (seguro, GPS, etc.)
   └─ Dados pessoais (nome, CPF/passaporte, email, telefone)

2. Site faz POST para API pública do sistema
   └─ POST /api/public/reserva

3. Sistema processa:
   ├─ Valida dados
   ├─ Verifica disponibilidade (overbooking se permitido)
   ├─ Cria registro de reserva no BD por grupo/categoria
   ├─ Gera código de reserva
   ├─ Envia email de confirmação ao cliente
   └─ Envia WhatsApp de notificação (se tenant tem recurso ativo)

4. Retorna ao site:
   └─ Código de reserva + mensagem de confirmação
```

### API Pública de Reserva

```
POST /api/public/reserva
Content-Type: application/json
X-Site-Token: {token_do_site}

{
    "chave": "...",
    "filial_retirada_id": 1,
    "filial_devolucao_id": 1,
    "data_saida": "2026-04-15",
    "hora_saida": "08:00",
    "data_chegada": "2026-04-20",
    "hora_chegada": "18:00",
    "grupo_id": 2,
    "plano": "KMC",
    "servicos": [1, 3],
    "cliente": {
        "nome": "João Silva",
        "documento": "123.456.789-00",
        "tipo_documento": "cpf",
        "email": "joao@email.com",
        "telefone": "+5527999999999"
    }
}
```

Reserva online trabalha por grupo/categoria (`grupo_id`), nao por veiculo especifico. A locadora aloca um veiculo fisico disponivel daquele grupo apenas no momento da retirada/registro de saida.

O backend recalcula a quantidade de diárias e o preço usando `data_saida`,
`data_chegada`, filial, grupo e plano. A temporada é aplicada por diária antes
de seguros, serviços e promoção; qualquer preço ou total enviado pelo navegador
é ignorado.

**Segurança da API pública:**
- Token por site armazenado em `site_config` (gerado na ativação)
- Rate limit: 20 requisições por minuto
- CORS configurado para o domínio do tenant
- Validação de todos os campos
- Honeypot anti-spam no formulário

### Dados Dinâmicos no Site

O PHP do site puxa dados via `SiteApi::getDadosSite()` com cache de 1 hora. Quando o visitante interage (ex: seleciona filial, avança etapa), chamadas adicionais podem ser feitas via AJAX para dados que dependem de seleção (ex: disponibilidade por filial).

**Dados carregados no page load (PHP, cache de 1h):**
- Filiais com horários de funcionamento, moeda, `precos_grupos`, `valores_servicos`
- Grupos de veículos visíveis (`visivel_no_site = 1`) — metadados
- Serviços adicionais disponíveis (filtrados por `FIND_IN_SET('SITE', onde_usar)`)
- Banners, links sociais, textos editáveis, SEO
- Flag `overbooking` do tenant

**Dados carregados sob demanda (JS, sem cache):**
- Disponibilidade e preços efetivos por filial/data (`/api/public/disponibilidade` via proxy `ajax-disponibilidade.php`)

### Disponibilidade de Grupos (passo 2 do fluxo)

Ao clicar em "Continuar" no passo 1, o JS consulta **em tempo real** quais grupos têm veículos livres no período escolhido e marca cada botão de grupo com o estado correto. A disponibilidade exibida e do grupo/categoria; ela nao promete uma placa ou veiculo especifico.

**Endpoint:** `GET /api/public/disponibilidade?chave=&id_matriz_filial=&data_saida=&hora_saida=&data_prevista=&hora_devolucao=`

**Resposta:**
```json
{
    "success": true,
    "overbooking": false,
    "dias": 5,
    "grupos": { "1": true, "18": false },
    "precos": {
        "1": {
            "KMC": {
                "valor_dia": 210.00,
                "valor_base_dia": 140.00,
                "subtotal": 1050.00,
                "tem_ajuste_temporada": true,
                "temporadas": [
                    {"id": 65, "nome": "Final de Ano", "ajuste_percentual": 50, "dias_aplicados": 5}
                ]
            }
        }
    }
}
```

`precos` contém as chaves `KML`, `KMC` e `DIA` para cada grupo visível. A
tarifa progressiva é resolvida primeiro e o ajuste de temporada é aplicado por
diária. Como a resposta depende do período, ela não utiliza o cache de uma hora
de `/api/public/dados-site`.

**Lógica no backend** (`Veiculo::gruposDisponiveisPorFilial($filialId, $dataSaida, $dataDevolucao)`):

1. Se `site_config.overbooking = 1` → todos os grupos visíveis retornam `true` (sem consultar veículos/reservas).
2. Caso contrário, roda uma única query com `NOT EXISTS` em `locacoes_veiculos + locacoes` e `contratos_veiculos + contratos`:
   - Ignora veículos com `disponibilidade IN ('V','RO','E')` (vendido/roubado/excluído).
   - Locações com veículo específico contam se `status IN ('R','P','A')` e `locacoes_veiculos.data_entrada IS NULL`.
   - Reservas por grupo (`id_veiculo IS NULL`) contam se `status IN ('R','P')` e subtraem disponibilidade do `id_grupo`.
   - Contratos contam se `status = 'A'` e `contratos_veiculos.data_entrada IS NULL`.
   - **Fórmula de overlap:** `nova_saida < existente_fim AND nova_devolucao > existente_inicio`.
   - Agrupa por `id_grupo` → `{idGrupo: qtd_livres}`. Grupos com 0 livres **não aparecem no mapa** (front trata como esgotado).

**Proxy HTTP** (`ajax-disponibilidade.php` no root do template): recebe o GET do JS e usa `SiteApi::getDisponibilidade()` para repassar ao backend mantendo o `X-Site-Token` server-side.

**Teste:** `tests/test_website_moeda_filial.php` cobre os cenários (overlap real com locações/contratos do tenant, esgotamento forçado via INSERT em transação rollback, overbooking=true).

### Estados do botão do grupo (passo 2)

Cada grupo tem um botão `<button class="btnSelecionarGrupo" data-id-grupo="...">`. Estados controlados pelo `custom.js`:

| Estado | Condição | Texto (i18n) | `disabled` |
|---|---|---|---|
| Indisponível no período | `carregarDisponibilidadeGrupos()` retornou o grupo ausente/false | `Esgotado` (`btn_esgotado`) | sim |
| Disponível, sem plano escolhido | default após AJAX de disponibilidade | `Selecione o plano` (`btn_selecione_plano`) | sim |
| Disponível, plano escolhido | `change` no radio de plano do próprio card | `Selecionar` (`btn_selecionar`) | não |

Flag `data('esgotado')` é gravada no elemento via `aplicarDisponibilidadeNosBotoes()`; o handler do radio de plano consulta essa flag antes de habilitar.

Os radios de plano são agrupados por card. Marcar Km Livre, Km Controlado ou Km Pago atualiza somente a prévia de preço daquele grupo e não altera os demais cards. A escolha efetiva da reserva (`grupo_id`, plano e valor diário usado no resumo) só é confirmada ao clicar em **Selecionar** no card correspondente.

Os inputs radio são controles internos e permanecem ocultos com `hidden` e
`display: none !important`; somente os labels estilizados dos planos aparecem ao
visitante. Funções criadas dentro de `inicializarFormReserva()` e consumidas na
inicialização geral devem ser expostas explicitamente em `window` e chamadas com
verificação de tipo, evitando `ReferenceError` por escopo.

### Responsividade dos botões de navegação entre passos

Nos passos 3 ("Serviços adicionais") e 4 ("Pré-cadastro") existem **dois botões** para avançar:

- **Botão original** à esquerda do conteúdo — classes `d-none d-md-flex` (visível só em desktop ≥ 768px).
- **Botão abaixo do resumo** — `btn-block btn-lg py-4 d-flex align-items-center justify-content-center` (largura do resumo, alto, texto centralizado). Visível sempre.

No mobile só o botão abaixo do resumo aparece, evitando duplicação. O handler `.btnConcluirReserva` (passo 4) escuta a classe para cobrir ambas as cópias (não pode usar ID porque duplicaria).

### Login de cliente no passo 4 (pré-cadastro)

No passo 4 do fluxo o visitante digita CPF/CNPJ. O JS consulta `/api/public/cliente-existe` em blur:

- **existe = true** → mostra bloco `#blocoLoginCliente` com senha + botões "Entrar" / "Esqueci minha senha". Ao submeter login, o proxy `ajax-cliente-login.php` chama `/api/public/cliente-login`, valida `password_verify` contra `clientes.senha` e, em sucesso, grava `$_SESSION['cliente_id']` no **servidor do site do cliente** (não no backend). Cookie de sessão: **apenas do navegador** (sem `setcookie expires`).
- **existe = false** → mantém form de pré-cadastro (novo cliente). Ao submeter, `criarReserva` cria um `clientes` com `senha = password_hash(cpf_cnpj)` e insere email/telefone em `contatos_emails`/`contatos_telefones`.

**"Esqueci minha senha":** proxy `ajax-cliente-senha-reset.php` → `/api/public/cliente-senha-reset`.

Fluxo (**token one-time**, sem senha trafegando por email):
1. Controller valida CPF/CNPJ e gera token (`bin2hex(random_bytes(32))` = 64 hex chars, ~256 bits).
2. Grava hash SHA-256 em `cliente_password_resets` (`expires_at` = agora+60min, `used_at` = null). Invalida tokens pendentes anteriores do mesmo cliente.
3. Envia template `cliente_nova_senha` com `{{outros.reset_url}} = {APP_URL}/public/redefinir-senha?token=XXX` e `{{outros.reset_expira_em}}`.
4. Cliente clica no link → `PublicWebsiteController::exibirFormResetSenha` renderiza HTML standalone (com CSRF próprio em `Session['reset_csrf_token']`). Se token inválido/expirado/usado, mostra aviso.
5. Submit do form → `POST /api/public/cliente-senha-definir` valida CSRF + token, chama `password_hash($senha, PASSWORD_ARGON2ID)`, marca `used_at`.

Resposta do reset inicial sempre `success=true` — mitiga enumeration. Senha mínima 8 caracteres (validado no submit).

**Cliente logado:** o template `reserva.php` lê `$_SESSION['cliente_id']` no topo e, se presente:
- Passo 4 **não** mostra form de CPF/nome/email/endereço/documentos — exibe apenas um alerta "Você está logado como **Nome** · Sair".
- O botão "Concluir reserva" envia payload sem `cliente` / `documentos`; o proxy `ajax-reserva.php` injeta `cliente_id` do `$_SESSION` server-side (JS não controla isso).

### Documentos enviados no pré-cadastro

Quando `site_config.envio_documentos` está ativo, o visitante novo pode enviar
CNH, CPF, RG/Passaporte e comprovante de endereço. O backend valida o conteúdo
real do arquivo, aceita imagens e PDF com até 5 MB e salva o arquivo físico em
`storage/uploads/{chave}/`.

Os registros pertencem ao cadastro do cliente e são gravados em
`clientes_arquivos`, usando os mesmos tipos da aba **Arquivos**:

| Documento do site | `clientes_arquivos.tipo` |
|-------------------|----------------------------|
| CNH | `1` |
| CPF | `2` |
| RG/Passaporte | `3` |
| Comprovante de endereço | `4` |

Uploads feitos pelo site entram com `status = NULL` (**Aguardando**) para revisão
da locadora. A reserva não deve retornar sucesso se um anexo informado não puder
ser validado, salvo fisicamente ou vinculado ao cliente. Documentos não são
vinculados a `locacoes`: a fonte única para consulta e gestão é
`clientes_arquivos`.

### Cálculo server-side do total (segurança)

O total da reserva é **calculado no backend**, ignorando qualquer valor enviado pelo JS. Serviço: `App\Services\WebsiteReservaCalcService::calcular()`.

**Inputs:** `filial_id, grupo_id, plano (KML|KMC|DIA), dias, servicos[], seguro_carro, seguro_terceiros`.

**Fórmula:**
- **Plano:** `grupos_precos_filiais.valor_plano_<plano>` × dias
- **Seguros:** `valor_seguro_carro × dias` e/ou `valor_seguro_terceiros × dias` quando marcados. Se `site_config.seguro_carro_obrigatorio` ou `site_config.seguro_terceiros_obrigatorio` estiver ativo, o backend força a inclusão correspondente mesmo que o navegador omita ou altere o campo.
- **Serviços MON:** valor de `taxaseservicos_valores_filiais[filial]` (ou `taxaseservicos.valor` global se não houver por filial). `base_calculo=PER` → × dias; `FIX` ou `VLT` → valor único.
- **Serviços POR:** `taxaseservicos.valor%` sobre o plano × dias (PER/VLT) ou sobre plano-dia (FIX).

**Persiste** em `locacoes.total_fatura` e `total_pagar`. O `breakdown` vai para `locacoes.obs` (JSON) para auditoria, e `locacoes_veiculos` recebe o snapshot dos flags e valores diários dos seguros calculados no servidor.

### Fluxo pós-reserva (pagamento)

Em `PublicWebsiteController::criarReserva`:

- **`site_config.pagamento_antecipado = 0`:** resposta `{success, codigo, total, pagamento_url: null, requer_confirmacao}`. JS exibe tab de confirmação com o código.
- **`pagamento_antecipado = 1`:** resposta inclui `pagamento_url = APP_URL + '/pagar/' + codigo`. JS faz `window.location = pagamento_url`. A tela `/pagar/{codigo}` (controller `PagamentoPublicoController`) lê o valor do BD (`locacoes.total_pagar`) — **nunca do JS**, garantindo que não é falsificável.

### Notificacoes por email de uma nova reserva do site

Depois que a reserva e persistida, o sistema envia um aviso interno individual
para cada **funcionario** que atenda simultaneamente a estes criterios:

1. esta ativo (`funcionarios.status = 'A'`);
2. pertence a filial selecionada para retirada (`id_matriz_filial`);
3. possui email preenchido no cadastro de funcionario;
4. sua role possui a permissao `notificacoes.novas_reservas`.

O destinatario nao e o email geral da empresa. A role tambem nao possui email:
ela apenas concede a permissao, e o endereco usado e
`funcionarios.email`. Enderecos repetidos sao deduplicados, portanto o mesmo
email recebe apenas uma mensagem por reserva.

A migration `00403_add_novas_reservas_notification_permission.php` concede a
permissao inicialmente as roles `Proprietário` e `Gerente` existentes. Depois
disso, a locadora pode controlar os destinatarios atribuindo ou removendo essa
permissao nas roles e vinculando cada funcionario a role apropriada.

O email e enfileirado por `WebsiteReservationNotificationService` com
`queue_message()`, no contexto da filial de retirada. Falha ao enfileirar um
destinatario e registrada em log e nao desfaz a reserva; os demais
destinatarios continuam sendo processados.

Este aviso interno e separado das mensagens ao cliente (`pedido_reserva` e
`confirmacao_reserva`) e do WhatsApp enviado ao celular da empresa matriz.

### Cálculo de totais e armadilhas

- `#dias` é um `<input hidden>` único; usar `$('#dias').val()` em vez de `$('.dias').text()` porque há vários spans `.dias` nos resumos duplicados das tabs 3 e 4 e `.text()` concatena todos.
- `calcTotal()` deve iterar `.somar` **apenas dentro da tab ativa** (`$('.tabs_.active .resumo-detalhes .somar')`) para não contar o resumo duplicado duas vezes.
- Ao selecionar plano, `window.__precoPlanoAtual` guarda o valor numérico para cálculos de serviços % (`base_calculo = PER/VLT`).
- Ao trocar filial, `resetResumoValores()` desmarca radios de plano, checkboxes de serviços/seguros, zera totais e chama `aplicarDisponibilidadeNosBotoes()` para reaplicar "Esgotado"/"Selecione o plano" conforme a última disponibilidade conhecida.
- Depois do reset, seguros configurados como obrigatórios são marcados novamente. O bloqueio do checkbox é apenas UX; cálculo, promoção, pagamento e persistência usam a política consultada no backend.
- O passo 3 sempre exibe Seguro do veículo e Seguro para terceiros. Valores zero aparecem como gratuitos e continuam selecionados quando obrigatórios.

---

## Moeda Dinâmica por Filial

A moeda exibida no site **não é uma configuração fixa do site**. Ela é determinada dinamicamente pela filial selecionada como **local de retirada**.

**Fonte dos dados**: `matrizes_filiais.currency_code` (BRL, EUR, USD, GBP) e `matrizes_filiais.locale` (pt_BR, en_US, etc.)

### Como funciona

```
1. Cliente seleciona "Local de retirada" no formulário
   └─ Ex: "ES - Vila Velha" (filial_id = 2, currency_code = BRL)

2. JS do site faz lookup no objeto de filiais (carregado via API)
   └─ Obtém currency_code e locale da filial selecionada

3. Todos os valores monetários da página atualizam automaticamente:
   ├─ Preços dos grupos de veículos: "R$ 79,90/dia"
   ├─ Serviços adicionais: "R$ 25,00/dia"
   ├─ Resumo do pedido: "Total: R$ 399,50"
   └─ Formatação segue CurrencyHelper (separador, casas decimais)
```

### Dados retornados pela API pública

O payload de `/api/public/dados-site` injeta um map **por filial** com símbolo, separadores e os **preços de grupos/serviços na moeda daquela filial** (vindos de `grupos_precos_filiais` e `taxaseservicos_valores_filiais`). Isso permite ao JS do site re-renderizar preços ao trocar a filial de retirada sem nova ida ao servidor.

```json
GET /api/public/dados-site?chave={chave}

{
    "filiais": [
        {
            "id": 14,
            "nome": "Vila Velha",
            "label": "ES - Vila Velha",
            "currency_code": "EUR",
            "locale": "pt_BR",
            "simbolo_moeda": "€",
            "separador_decimal": ",",
            "separador_milhar": ".",
            "precos_grupos": {
                "1":  {"valor_plano_km_livre": 108, "valor_plano_km_controlado": 96, "valor_plano_km_pago": 102, "valor_seguro_carro": 18, "valor_seguro_terceiros": 12, /* ... */},
                "18": {"valor_plano_km_livre": 108, /* ... */}
            },
            "valores_servicos": {
                "2167": 50,
                "2": 5
            }
        }
    ],
    "grupos":   [ /* metadados: id, nome, descricao, foto_url, etc — SEM preço */ ],
    "servicos": [ /* metadados: id, nome, tipo_valor (MON|POR), base_calculo, valor (só usado em POR) */ ],
    "overbooking": false
}
```

**Fonte dos dados:**

| Campo | Fonte |
|---|---|
| `currency_code`, `locale` | `matrizes_filiais` (via `MatrizFilial::listar()` — **precisa incluir essas colunas no SELECT**) |
| `simbolo_moeda`, `separador_*` | Mapa estático `$currencyMap` em `PublicWebsiteController::dadosSite()` (BRL→R$, EUR→€, USD→US$, GBP→£) |
| `precos_grupos[idGrupo]` | `grupos_precos_filiais` via `Veiculo` / `GrupoPrecoFilial::listarPorFilial($idFilial)` |
| `valores_servicos[idTaxa]` | `taxaseservicos_valores_filiais` via `TaxaServicoValorFilial::listarPorFilial($idFilial)` (tipo MON; POR usa `valor` global) |
| `overbooking` | `site_config.overbooking` (flag do tenant) |

**Filtro de serviços exibidos:** apenas `taxaseservicos.onde_usar` contendo `SITE` aparecem no passo 3 do fluxo. Query: `WHERE FIND_IN_SET('SITE', onde_usar)`.

### No JS do site

Os helpers (`custom.js`) lêem `window.FILIAIS_DATA[idFilial]` — um objeto indexado injetado pelo `footer.php`:

```javascript
// filialAtiva / formatarMoeda / precosGrupoAtual / servicoValorAtual
function filialAtiva() {
    var id = window.__locValFilialId($('#localRetirada'));
    return (window.FILIAIS_DATA || {})[id] || null;
}
function formatarMoeda(valor) {
    var f = filialAtiva();
    var sym = f?.simbolo_moeda || 'R$';
    // aplica separadores da filial e prefixa símbolo
}
```

Ao trocar a filial, o handler `$locRet.on('change', ...)` chama:
- `renderPrecosServicos()` — re-renderiza preços dos serviços (taxas MON pegam de `valores_servicos`)
- `resetResumoValores()` — limpa seleções de plano/adicionais e reseta totais na nova moeda
- `carregarDisponibilidadeGrupos()` — consulta disponibilidade real ao avançar (ver "Disponibilidade de Grupos" abaixo)

Isso elimina a necessidade de uma coluna `moeda` no `site_config` — a moeda é sempre contextual à filial de retirada escolhida pelo visitante do site.

### i18n no JS via `window.I18N_WEBSITE`

Strings dinâmicas usadas pelo JS (ex: sufixo `/diaria`, rótulos dos planos, textos dos botões) são injetadas no `footer.php` como JSON traduzido via `t()`:

```html
<script>
window.I18N_WEBSITE = {
    diaria: "diaria",          // t('reserva.diaria_sufixo')
    plano_km_livre: "Km Livre",
    plano_km_controlado: "Km Controlado",
    plano_km_pago: "Km Pago",
    btn_selecione_plano: "Selecione o plano",
    btn_esgotado: "Esgotado",
    btn_selecionar: "Selecionar"
};
</script>
```

---

## Modo Manutenção

Quando `site_config.manutencao = 1`:

- O `index.php` detecta `$config['manutencao'] === true` e exibe a página de manutenção (`includes/manutencao.php`)
- Todas as outras páginas redirecionam para index
- A página exibe mensagem configurável + logo da empresa
- O tenant pode personalizar a mensagem de manutenção via `site_conteudos` (pagina=`manutencao`)

---

## Sistema de Deploy FTP

### FTP Library

Instalar via Composer (já existe em vendor/ mas falta no composer.json):
```bash
composer require phpseclib/phpseclib:^3.0
```

### FtpService

```php
// app/Services/FtpService.php
class FtpService
{
    public function connect(string $host, int $porta, string $usuario, string $senha): bool;
    public function upload(string $localPath, string $remotePath): bool;
    public function uploadDirectory(string $localDir, string $remoteDir): array;
    public function deleteRemoteFile(string $remotePath): bool;
    public function disconnect(): void;
    public function testConnection(): bool;
}
```

Todos os parâmetros são descriptografados com `decrypt()` no momento da conexão.

### Processo de Upload para o FTP

**Estratégia: upload completo** — envia todos os arquivos a cada publicação.

Justificativa: o pacote é leve (~20 arquivos PHP + CSS + JS + assets). Upload completo leva poucos segundos e é 100% confiável — sem risco de arquivos desatualizados.

**Fluxo detalhado:**

```
1. WebsiteBuilderService::deploy($chave) é chamado

2. BUILD — monta output em diretório temporário:
   storage/temp/website-build-{chave}/
   ├── index.php                    # Copiado do template
   ├── sobre.php
   ├── veiculos.php
   ├── contato.php
   ├── reserva.php
   ├── includes/
   │   ├── config.php               # GERADO com dados do tenant
   │   ├── header.php               # Copiado
   │   ├── footer.php               # Copiado
   │   ├── head.php                 # Copiado
   │   ├── whatsapp-float.php       # Copiado
   │   ├── structured-data.php      # Copiado
   │   ├── manutencao.php           # Copiado
   │   ├── functions.php            # Copiado
   │   └── api.php                  # Copiado
   ├── assets/
   │   ├── css/
   │   │   ├── style.min.css        # COMPILADO (cores do tenant)
   │   │   └── [imagens]
   │   └── js/
   │       └── custom.min.js        # MINIFICADO previamente com Terser e copiado sem alterações
   ├── lang/
   │   ├── pt_BR.php                # Só idiomas ativos
   │   └── en_US.php
   ├── cache/
   │   └── .htaccess                # Deny from all
   ├── sitemap.xml                  # GERADO
   ├── robots.txt                   # GERADO
   └── versao.json                  # Copiado

3. FTP CONNECT — descriptografa credenciais com decrypt()
   ├── host = decrypt(site_credenciais.host)
   ├── usuario = decrypt(site_credenciais.usuario)
   ├── senha = decrypt(site_credenciais.senha)
   └── diretório = decrypt(site_credenciais.diretorio)

4. UPLOAD — envia pasta inteira recursivamente
   FtpService::uploadDirectory(
       localDir:  'storage/temp/website-build-{chave}/',
       remoteDir: '/public_html/'
   )
   ├── Cria diretórios remotos se não existem
   ├── Sobrescreve arquivos existentes
   ├── Modo binário para imagens, ASCII para PHP/CSS/JS
   └── Retorna: ['arquivos_enviados' => N, 'erros' => []]

5. LIMPAR CACHE — invalida cache do site remoto
   └── Chama /api/public/limpar-cache (ou deleta arquivos em cache/)

6. VERIFICAÇÃO — confirma que os arquivos chegaram
   └── Checa se index.php existe no remoto

7. CLEANUP — remove diretório temporário local

8. REGISTRO
   ├── Atualiza site_config.versao
   ├── Atualiza site_config.ultimo_deploy_em
   └── Cria registro em site_deploy_log
```

**O que NÃO vai para o FTP (servido do sistema principal):**
- Banners → `locadora.7carros.com/files/{token}`
- Logo/favicon enviados pelo cliente → `locadora.7carros.com/files/{token}` em APIs runtime
- Imagens de veículos → `locadora.7carros.com/files/{token}`
- Dados dinâmicos → vêm da API em runtime

**O que VAI para o FTP:**
- Arquivos PHP (páginas + includes)
- `config.php` gerado com dados do tenant
- CSS compilado e minificado (cores aplicadas)
- JS minificado
- Imagens estáticas do CSS (bg.png, marcador.png, etc.)
- Logo padrão (`assets/img/logo_padrao.png`) quando o tenant ainda não enviou logo
- Arquivos de idioma (apenas os ativos)
- sitemap.xml, robots.txt, versao.json
- Diretório cache/ vazio com .htaccess

### WebsiteBuilderService

```php
// app/Services/WebsiteBuilderService.php
class WebsiteBuilderService
{
    public function build(string $chave): string;  // Retorna path do diretório com output
    public function deploy(
        string $chave,
        ?int $funcionarioId = null,
        string $tipo = 'deploy',
        array $metadata = []
    ): array; // Build + FTP upload
    public function preview(string $chave): string; // Build sem deploy, retorna path temporário
    public function getVersaoArquivo(): string;     // Lê versao.json do template
}
```

### Versionamento e Atualização

> **Regra obrigatória:** qualquer alteração em `storage/templates/website/`,
> independentemente do tipo ou quantidade de arquivos, deve incrementar
> `storage/templates/website/versao.json`. A mudança só pode ser considerada
> concluída ou enviada ao FTP quando o `versao.json` atualizado também estiver
> incluído no envio. Correções pequenas usam incremento de patch; mudanças
> maiores de template usam incremento minor.

Sistema simples de comparação entre duas fontes:

```
┌─────────────────────────────────────┐     ┌─────────────────────────────────────┐
│     versao.json (arquivo)           │     │     site_config (BD)                │
│                                     │     │                                     │
│     {"versao": "1.1.0"}            │     │     versao: "1.0.0"                 │
│                                     │     │                                     │
│     Fonte: 7Carros atualiza ao      │     │     Fonte: gravado no momento       │
│     modificar os templates          │     │     do último deploy do tenant      │
└─────────────────────────────────────┘     └─────────────────────────────────────┘
                    │                                        │
                    └──────────── COMPARA ───────────────────┘
                                     │
                          versao.json > site_config.versao?
                                     │
                              ┌──────┴──────┐
                              │ SIM         │ NÃO
                              ▼             ▼
                    Mostra botão        Tudo atualizado
                    "Atualizar"         (sem ação)
```

**Fluxo:**
1. 7Carros modifica arquivos do template em `storage/templates/website/`
2. 7Carros atualiza `versao.json` com a nova versão (ex: `{"versao":"1.1.0"}`)
3. Tenant acessa o painel Website → sistema lê `versao.json` e compara com `site_config.versao`
4. Se versão do arquivo > versão do BD → mostra botão "Atualizar para v1.1.0"
5. Tenant clica "Atualizar" → re-deploy (build com template atualizado + dados do tenant no BD)
6. `site_config.versao` é atualizado para `1.1.0`

### Publicação administrativa em lote

Atualizações do template também podem ser distribuídas pela equipe 7Carros sem
depender do acesso de cada cliente ao painel. O comando é sempre *dry-run* por
padrão:

```bash
php scripts/publicar-atualizacao-websites.php --env=production
```

O fluxo operacional obrigatório é:

```bash
# 1. Simular e conferir candidatos/ignorados
php scripts/publicar-atualizacao-websites.php --env=production

# 2. Publicar primeiro em um tenant piloto
php scripts/publicar-atualizacao-websites.php \
  --env=production \
  --usuario-ftp=USUARIO_FTP_DO_PILOTO \
  --apply \
  --confirm=VERSAO_ATUAL

# 3. Depois da validação do piloto, publicar nos demais sites elegíveis
php scripts/publicar-atualizacao-websites.php \
  --env=production \
  --apply \
  --confirm=VERSAO_ATUAL
```

`--confirm` deve coincidir exatamente com a versão de
`storage/templates/website/versao.json`. O piloto pode ser selecionado por
`--chave=CHAVE` ou por `--usuario-ftp=USUARIO`; o filtro por usuário só prossegue
quando encontra exatamente um site. Também existem `--limit=N`, útil para lotes
menores, e `--stop-on-error`, quando for desejável interromper na primeira falha.

Um site só é candidato quando:

- `site_config.status = ativo`;
- existem credenciais em `site_credenciais`;
- existe `site_config.api_token`;
- a versão publicada está vazia ou é menor que a versão atual do template.

A execução é sequencial. Por padrão, uma falha é registrada e os sites seguintes
continuam sendo processados. Cada sucesso atualiza `site_config.versao` e cria um
registro `update` em `site_deploy_log`, com versão anterior, versão de destino e
origem `cli_bulk_template`. Isso torna a retomada segura: ao executar novamente,
os sites já atualizados são ignorados pela comparação de versão.

O método `SiteConfig::listarParaAtualizacaoEmLote()` usa `withoutChave()` apenas
na listagem administrativa cross-tenant sem filtro. Quando `--chave` é
informado, a consulta usa `withChave($chave)` e preserva o isolamento normal do
QueryBuilder.

A publicação individual pelo painel permanece disponível e continua sendo
registrada como `deploy`.

**Customizações do cliente NÃO se perdem** porque:
- Cores, textos, CSS, banners, SEO, links → tudo vem do BD
- O template é apenas a **estrutura/esqueleto**
- O build sempre mescla template + dados do BD
- Atualizar o template é como trocar o esqueleto mantendo o conteúdo

---

## Estrutura de Arquivos (Implementação)

### Models

```
app/Models/
  SiteConfig.php
  SiteCredencial.php
  SiteAparencia.php
  SitePreset.php
  SiteConteudo.php
  SiteSeo.php
  SiteIntegracao.php
  SiteIdioma.php
  SiteDeployLog.php
  SiteLink.php
  SiteBanner.php            # Criar ou aprimorar existente
```

### Controllers

```
app/Controllers/
  WebsiteController.php     # Painel de configuração do tenant
```

### Services

```
app/Services/
  WebsiteBuilderService.php # Pipeline de build
  WebsiteCssService.php     # Processamento de CSS/cores
  FtpService.php            # Upload via FTP/SFTP
  WebsiteService.php        # Lógica de negócio (ativação, verificação DNS, etc.)
```

### Config

```
app/Config/
  WebsiteThemes.php         # Presets de cores fixos
```

### Views

```
app/Views/pages/website/
  index.php                 # Roteador: ativar ou configurações
  ativar.php                # Fluxo de ativação
  configuracoes.php         # Config geral (domínio, flags, manutenção)
  aparencia.php             # Cores, CSS, logo, favicon
  conteudos.php             # Editor WYSIWYG por página/idioma
  seo.php                   # Meta tags por página/idioma
  banners.php               # Gestão de banners
  integracoes.php           # Códigos GTM, Analytics
  publicar.php              # Status, versão, botão publicar, histórico
```

### Rotas

```php
// app/Routes/web.php (adicionar)

// Imports no topo do arquivo:
use App\Controllers\WebsiteController;
use App\Controllers\PublicWebsiteController;

// Dentro do grupo autenticado: $router->group(['middleware' => 'auth'], function ($router) { ... })

// Views
$router->get('/pages/website/configuracoes', [WebsiteController::class, 'configuracoes']);
$router->get('/pages/website/banners', [WebsiteController::class, 'banners']);
$router->get('/pages/website/integracoes', [WebsiteController::class, 'integracoes']);
$router->get('/pages/website/aparencia', [WebsiteController::class, 'aparencia']);
$router->get('/pages/website/conteudos', [WebsiteController::class, 'conteudos']);
$router->get('/pages/website/seo', [WebsiteController::class, 'seo']);
$router->get('/pages/website/publicar', [WebsiteController::class, 'deploy']);
$router->get('/pages/website/ativar', [WebsiteController::class, 'ativar']);

// APIs internas (autenticadas, com CSRF nos POSTs)
$router->get('/api/website/config', [WebsiteController::class, 'getConfig']);
$router->post('/api/website/config', [WebsiteController::class, 'updateConfig'], ['csrf']);
$router->get('/api/website/aparencia', [WebsiteController::class, 'getAparencia']);
$router->post('/api/website/aparencia', [WebsiteController::class, 'updateAparencia'], ['csrf']);
$router->post('/api/website/aparencia/reset', [WebsiteController::class, 'resetAparencia'], ['csrf']);
$router->get('/api/website/conteudos/{pagina}', [WebsiteController::class, 'getConteudos']);
$router->post('/api/website/conteudos/{pagina}', [WebsiteController::class, 'updateConteudos'], ['csrf']);
$router->get('/api/website/seo/{pagina}', [WebsiteController::class, 'getSeo']);
$router->post('/api/website/seo/{pagina}', [WebsiteController::class, 'updateSeo'], ['csrf']);
$router->get('/api/website/integracoes', [WebsiteController::class, 'getIntegracoes']);
$router->post('/api/website/integracoes', [WebsiteController::class, 'saveIntegracao'], ['csrf']);
$router->post('/api/website/integracoes/{id}/excluir', [WebsiteController::class, 'deleteIntegracao'], ['csrf']); // usar no painel
$router->delete('/api/website/integracoes/{id}', [WebsiteController::class, 'deleteIntegracao'], ['csrf']);
$router->get('/api/website/banners', [WebsiteController::class, 'getBanners']);
$router->post('/api/website/banners', [WebsiteController::class, 'saveBanner'], ['csrf']);
$router->put('/api/website/banners/{id}', [WebsiteController::class, 'updateBanner'], ['csrf']);
$router->post('/api/website/banners/{id}/excluir', [WebsiteController::class, 'deleteBanner'], ['csrf']); // usar no painel
$router->delete('/api/website/banners/{id}', [WebsiteController::class, 'deleteBanner'], ['csrf']);
$router->post('/api/website/banners/reordenar', [WebsiteController::class, 'reordenarBanners'], ['csrf']);
$router->get('/api/website/links', [WebsiteController::class, 'getLinks']);
$router->post('/api/website/links', [WebsiteController::class, 'saveLinks'], ['csrf']);
$router->get('/api/website/idiomas', [WebsiteController::class, 'getIdiomas']);
$router->post('/api/website/idiomas', [WebsiteController::class, 'saveIdiomas'], ['csrf']);
$router->post('/api/website/ativar', [WebsiteController::class, 'submitAtivacao'], ['csrf']);
$router->get('/api/website/verificar-dominio', [WebsiteController::class, 'verificarDominio']);
$router->post('/api/website/deploy', [WebsiteController::class, 'executarDeploy'], ['csrf']);
$router->get('/api/website/deploy/status', [WebsiteController::class, 'deployStatus']);
$router->get('/api/website/deploy/log', [WebsiteController::class, 'deployLog']);
$router->post('/api/website/preview', [WebsiteController::class, 'preview'], ['csrf']);
$router->post('/api/website/presets', [WebsiteController::class, 'savePreset'], ['csrf']);
$router->post('/api/website/presets/{id}/excluir', [WebsiteController::class, 'deletePreset'], ['csrf']); // usar no painel
$router->delete('/api/website/presets/{id}', [WebsiteController::class, 'deletePreset'], ['csrf']);

// Webhook WHMCS (público, sem auth de sessão)
$router->get('/api/webhook/whmcs/site-ativacao', [WebsiteController::class, 'webhookWhmcsAtivacao']);

// API pública do site (sem auth de sessão, com rate limit)
$router->post('/api/public/reserva', [PublicWebsiteController::class, 'criarReserva'], ['rate_limit']);
$router->get('/api/public/dados-site', [PublicWebsiteController::class, 'dadosSite'], ['rate_limit']);
$router->get('/api/public/conteudos', [PublicWebsiteController::class, 'conteudos'], ['rate_limit']);
$router->post('/api/public/contato', [PublicWebsiteController::class, 'contato'], ['rate_limit']);
$router->post('/api/public/limpar-cache', [PublicWebsiteController::class, 'limparCache'], ['rate_limit']);
```

### Permissões

| Permissão | Descrição |
|-----------|-----------|
| `website.visualizar` | Ver configurações do website |
| `website.editar` | Modificar configurações (já existe) |
| `website.configurar` | Alterar flags, manutenção, overbooking |
| `website.deploy` | Executar deploy para FTP |

### Menu (navbar.php)

Atualizar `app/Views/partials/navbar.php` (linhas 267-277). O menu muda conforme o status do site:

**Site NÃO ativado (status = inativo):**
```
WebSite ▾
└─ Ativar
```

**Site pendente (status = pendente):**
```
WebSite ▾
└─ Ativar (mostra status "aguardando ativação")
```

**Site ativado (status = ativo):**
```
WebSite ▾
├─ Configurações
├─ Aparência
├─ Conteúdos
├─ Banners
├─ SEO
├─ Integrações
└─ Publicar
```

**Implementação no navbar.php:**
```html
<!-- WebSite -->
<div class="main-nav-item">
    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
        {{ t('menu.website.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
    </a>
    <div class="submenu">
        @php $siteStatus = \App\Models\SiteConfig::getStatus(); @endphp
        @if($siteStatus === 'ativo')
            <a href="/pages/website/configuracoes">{{ t('menu.website.settings') }}</a>
            <a href="/pages/website/aparencia">{{ t('menu.website.appearance') }}</a>
            <a href="/pages/website/conteudos">{{ t('menu.website.contents') }}</a>
            <a href="/pages/website/banners">{{ t('menu.website.banners') }}</a>
            <a href="/pages/website/seo">{{ t('menu.website.seo') }}</a>
            <a href="/pages/website/integracoes">{{ t('menu.website.integrations') }}</a>
            <a href="/pages/website/publicar">{{ t('menu.website.publish') }}</a>
        @else
            <a href="/pages/website/ativar">{{ t('menu.website.activate') }}</a>
        @endif
    </div>
</div>
```

**Traduções necessárias em `app/Lang/*/menu.php`:**

> **Nota:** O menu atual usa a chave `menu.website.codes` (linha 275 do navbar.php). Na reestruturação, essa chave será substituída por `menu.website.integrations`. Remover a chave `codes` das traduções.

```php
'website' => [
    'title'        => 'WebSite',
    'activate'     => 'Ativar',
    'settings'     => 'Configurações',
    'appearance'   => 'Aparência',
    'contents'     => 'Conteúdos',
    'banners'      => 'Banners',
    'seo'          => 'SEO',
    'integrations' => 'Integrações',   // substitui 'codes'
    'publish'      => 'Publicar',
],
```

---

## Migração de Dados Legados

### De-Para Detalhado

| Campo legado (`site`) | Destino | Transformação |
|------------------------|---------|---------------|
| `chave` | `site_config.chave` | Direto |
| `dominio` | `site_config.dominio` | Direto |
| (calculado) | `site_config.status` | login→ativo, dominio sem login→pendente, senão→inativo |
| `manutencao` | `site_config.manutencao` | S→1, N→0 |
| `reserva_online` | `site_config.reserva_online` | S→1, N→0 |
| `overbooking` | `site_config.overbooking` | S→1, N→0 |
| `pagamento_antecipado` | `site_config.pagamento_antecipado` | S→1, N→0 |
| `moeda` | Removida | Agora dinâmico via `matrizes_filiais.currency_code` |
| `cor` | `site_aparencia.preset_cor` | Direto (azul, vermelho, verde, preto) |
| `login` | `site_credenciais` | Parse JSON, encrypt cada campo |
| `header` | `site_integracoes` (tipo=head) | `urldecode(base64_decode())` |
| `footer` | `site_integracoes` (tipo=body_fim) | `urldecode(base64_decode())` |
| `texto_inicio` | `site_conteudos` (pagina=inicio) | Parse JSON, 1 registro por key |
| `texto_sobre` | `site_conteudos` (pagina=sobre) | `html_entity_decode()` |
| `texto_reserva` | `site_conteudos` (pagina=reserva) | Parse JSON, 1 registro por key |
| `links` | `site_links` | Parse JSON, 1 registro por rede social |
| (whatsapp de links) | `site_config.whatsapp_numero` | Extrair número do URL |
| `site_banners.*` | `site_banners.*` + novas colunas | Adicionar ativo=1, ordem=id order |

### Tabela legada

Após migração e validação: `RENAME TABLE site TO _site_legacy;`

---

## Variáveis de Ambiente

Adicionar ao `.env`:

```env
# Website Module
TENANT_ONBOARD_SECRET=chave-secreta-compartilhada-com-whmcs
WEBSITE_TEMPLATE_PATH=storage/templates/website
WEBSITE_DEFAULT_FTP_PORT=21
WEBSITE_DEFAULT_FTP_DIR=/public_html
WEBSITE_DEPLOY_RATE_LIMIT=5
WEBSITE_SAC_EMAIL=sac@hostcia.net
```

---

## Verificação / Testes

### Checklist de Testes

- [ ] Migration 00297: criar tabelas → verificar schema
- [ ] Migration 00298: migrar dados → verificar contagem e integridade
- [ ] Migration 00299: renomear tabela legada
- [ ] Verificação DNS: testar com domínio válido e inválido
- [ ] Callback WHMCS: testar com secret válido/inválido
- [ ] FTP connect: testar conexão com credenciais válidas
- [ ] Build: gerar output para tenant de teste (chave=1111111111111)
- [ ] Deploy: upload para FTP de teste
- [ ] Versionamento: simular update de template
- [ ] CSS customização: preset + override + reset
- [ ] Multi-idioma: gerar para pt_BR + en_US
- [ ] SEO: validar meta tags, JSON-LD, sitemap
- [ ] Reserva: criar reserva via API pública
- [ ] Manutenção: ativar/desativar
- [ ] Permissões: testar acesso com/sem permissão
- [ ] Portal: login e recursos dos perfis Cliente e Investidor
- [ ] Portal: sessão, CSRF, link de pagamento e recibo
