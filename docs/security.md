# Segurança

Sistema de proteção multi-camada para o 7Carros Locadora.

## Visão Geral

O sistema implementa defesa em profundidade com múltiplas camadas de proteção:

```
┌─────────────────────────────────────────────────────────────┐
│                    CAMADA 1: REDE                           │
│  BlockedIpMiddleware → IPs banidos não passam               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  CAMADA 2: ARMADILHAS                       │
│  HoneypotMiddleware → Bots caem em endpoints falsos         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  CAMADA 3: RATE LIMITING                    │
│  RateLimitMiddleware → Limite de requisições por IP/usuário │
│  ThrottlingMiddleware → Delay baseado em score de suspeita  │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  CAMADA 4: AUTENTICAÇÃO                     │
│  AuthMiddleware → Verifica sessão válida                    │
│  CsrfMiddleware → Proteção contra CSRF                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  CAMADA 5: AUTORIZAÇÃO                      │
│  PermissionMiddleware → Verifica permissões RBAC            │
│  FilialHelper → Restringe acesso por filiais                │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  CAMADA 6: ISOLAMENTO                       │
│  QueryBuilder → Filtra automaticamente por tenant (chave)   │
│  CrossTenantDetection → Detecta tentativas cross-tenant     │
└─────────────────────────────────────────────────────────────┘
```

---

## Middlewares de Segurança

### BlockedIpMiddleware

**Arquivo:** `app/Middleware/BlockedIpMiddleware.php`

Bloqueia IPs maliciosos antes de qualquer processamento.

| Tipo | Descrição |
|------|-----------|
| Bloqueio Permanente | Lista em `Security::BLOCKED_IP['permanent_blocks']` |
| Bloqueio Temporário | Armazenado no banco, com expiração |

```php
// Bloquear IP programaticamente
BlockedIpMiddleware::blockIp($ip, 'Motivo', 3600); // 1 hora
```

---

### HoneypotMiddleware

**Arquivo:** `app/Middleware/HoneypotMiddleware.php`

Armadilhas para bots que tentam acessar endpoints inexistentes.

**Endpoints armadilha:**
```php
'/api/v2/clientes',
'/api/v2/users',
'/api/clientes/export-all',
'/api/backup',
'/.env',
'/wp-admin',
'/phpinfo.php'
```

**Comportamento:** Bot acessa → IP banido por 24h → Log com score 100

---

### RateLimitMiddleware

**Arquivo:** `app/Middleware/RateLimitMiddleware.php`

Limita requisições por IP/usuário em janela de tempo.

| Método | Limite | Janela |
|--------|--------|--------|
| GET | 60 req | 60s |
| POST | 20 req | 60s |
| PUT | 20 req | 60s |
| DELETE | 10 req | 60s |

**Headers retornados:**
- `X-RateLimit-Limit` - Limite máximo
- `X-RateLimit-Remaining` - Requisições restantes
- `X-RateLimit-Reset` - Segundos até reset

---

### ThrottlingMiddleware

**Arquivo:** `app/Middleware/ThrottlingMiddleware.php`

Adiciona delay nas respostas baseado no score de suspeita.

| Score | Classificação | Delay |
|-------|---------------|-------|
| 0-30 | Normal | 0ms |
| 31-50 | Suspeito | 500ms |
| 51-70 | Muito suspeito | 2000ms |
| 71+ | Bot | 5000ms |

---

### CsrfMiddleware / ApiCsrfMiddleware

**Arquivos:** `app/Middleware/CsrfMiddleware.php`, `app/Middleware/ApiCsrfMiddleware.php`

Proteção contra Cross-Site Request Forgery.

#### Geração e Expiração do Token

| Aspecto | Valor |
|---------|-------|
| **Tamanho** | 64 caracteres (32 bytes em hex) |
| **Geração** | `bin2hex(random_bytes(32))` |
| **Rotação** | A cada 15 minutos ao renderizar novo HTML autenticado, ou via heartbeat/refresh |
| **Armazenamento** | `$_SESSION['csrf_token']` |

O token é regenerado automaticamente a cada 15 minutos ao renderizar HTML autenticado (`Template.php:247-276`) e também pelo endpoint `GET /api/session/refresh`. Quando o token é renovado no JavaScript, `api.js` sincroniza a `<meta name="csrf-token">` e todos os `input[name="_token"]` já abertos na página.

#### Uso em Formulários HTML

```php
// Usando helper
<?= Template::csrf() ?>

// Ou diretamente
<input type="hidden" name="_token" value="<?= csrf_token() ?>">
```

#### Uso em Requisições AJAX

```javascript
// O helper API já envia automaticamente via header
const result = await API.post('/api/clientes', data);
```

**Header esperado:** `X-CSRF-TOKEN`

#### Padrao de Middlewares por Rota

| Tipo de rota | Middlewares |
|--------------|-------------|
| API interna `GET /api/*` | `['api_csrf', 'rate_limit', 'throttle']` |
| API interna mutavel `POST/PUT/DELETE /api/*` | `['api_csrf', 'rate_limit', 'throttle']` |
| Acao HTML/form fora de `/api` | `['csrf', 'rate_limit']` |
| Pagina autenticada `/pages/*` | `auth` herdado do grupo |
| Webhook publico | autenticacao propria por assinatura/token + `rate_limit` |
| Rota publica por codigo/token | `rate_limit` |

`/api/session/refresh` eh excecao intencional: nao usa `api_csrf`, pois existe para renovar o token quando ele expira.

#### Exceções

| Rota | Middleware | Motivo |
|------|------------|--------|
| `POST /logout` | `['auth']` (sem csrf) | Ação que beneficia o usuário; funciona mesmo com token expirado |
| `GET /api/session/refresh` | `['auth']` herdado | Renova token CSRF expirado |

#### Tratamento de Token Expirado

**Em formulários HTML:**
- Retorna erro 403 com mensagem "Token CSRF inválido ou expirado"
- Formulários abertos por muito tempo são atualizados automaticamente quando o heartbeat renova o token, desde que a página tenha `api.js` carregado

**Em requisições AJAX:**
- Retorna status HTTP 419
- O helper `API.js` intercepta, chama `/api/session/refresh`, atualiza o CSRF e repete a requisição uma vez
- Se `/api/session/refresh` retornar 401, a sessão PHP realmente acabou e o usuário é enviado para `/login`
- Se a renovação falhar por outro motivo, o modal oferece botão "Recarregar Página"

```javascript
// Fluxo interno do api.js
if (response.status === 419) {
    await this.refreshCsrfToken();
    return retryRequest();
}
```

**Arquivos envolvidos:**
- `public/assets/js/api.js` - Método `showSessionExpiredModal()`
- `app/Views/layouts/app.php` - Modal HTML `#sessionExpiredModal`

---

### Proteção Contra Autocomplete em Formulários Internos

Formulários autenticados do sistema não devem acionar autocomplete do navegador nem solicitar salvamento de dados sensíveis (clientes, contratos, locações, financeiro, funcionários, fornecedores, etc.).

**Arquivo:** `public/assets/js/autocomplete-guard.js`

**Carregamento:**
- `app/Views/layouts/iframe.php`
- `app/Views/layouts/app.php`

**Comportamento:**
- Aplica `autocomplete="off"` em formulários internos.
- Aplica `autocomplete="off"` em `input`, `textarea` e `select`.
- Aplica `autocomplete="new-password"` em campos `type="password"` internos.
- Aplica `autocorrect="off"`, `autocapitalize="off"` e `spellcheck="false"` em campos de texto.
- Observa campos criados dinamicamente via `MutationObserver`, cobrindo inputs adicionados por JavaScript.

**Exceções:**
- Login público/autenticador mantém `autocomplete="username"` e `autocomplete="current-password"`.
- Pagamento público mantém atributos de cartão (`cc-name`, `cc-number`, `cc-exp`, `cc-csc`).
- Se uma tela interna realmente precisar permitir autocomplete, envolva o trecho com `data-allow-autocomplete="true"` e documente o motivo no PR/alteração.

**Limitação:** navegadores e extensões de gerenciadores de senha podem ignorar parcialmente `autocomplete="off"`. A proteção reduz prompts e sugestões nativas, mas não substitui políticas de endpoint, criptografia, RBAC e isolamento de sessão.

---

### Continuidade de Sessão (Heartbeat)

`session.gc_maxlifetime` e `session.cookie_lifetime` são 4h (`Session.php:24-25`). A política atual é expiração por inatividade: não há expiração absoluta baseada no horário do login. Cada hit HTTP válido no servidor renova a janela de sessão PHP. "Atividade" significa requisição HTTP, não movimento de mouse/teclado.

Para que usuários preenchendo formulários longos (locação, contrato, multa, promissória) não percam dados por timeout silencioso, o `api.js` mantém a sessão viva via heartbeat:

| Aspecto | Valor |
|---|---|
| **Endpoint** | `GET /api/session/refresh` |
| **Intervalo** | 10 minutos |
| **Condição** | Apenas com `document.visibilityState === 'visible'` (não estende sessão de aba abandonada) |
| **Escopo** | Janela principal (`window.top === window`); iframes filhos compartilham a sessão via cookie |
| **Auto-start** | Sim, ao carregar `api.js` |

Se a aba ficar em segundo plano, o navegador for fechado ou o computador suspender por mais de 4h sem hits no servidor, a sessão normal expira. Se o login foi feito com "lembrar-me", o `remember_token` pode reautenticar o usuário por até 30 dias, desde que o token ainda exista e o funcionário esteja ativo.

**API pública:**
```javascript
API.startHeartbeat(intervalMs = 600000); // inicia (idempotente)
API.stopHeartbeat();                      // para o ping
```

**Por que não enfraquece a segurança:**
- Cookie de sessão é `HttpOnly` + `Secure` + `SameSite=Lax` — atacante remoto sem o cookie não consegue chamar `/api/session/refresh` em nome da vítima.
- Heartbeat só roda enquanto a aba está visível; se o usuário fecha o navegador ou minimiza por muito tempo, a sessão expira normalmente pelo `gc_maxlifetime`.
- O fingerprint da sessão (`Session.php:48`) ainda valida user-agent — qualquer divergência ainda destrói a sessão.
- O heartbeat renova também o token CSRF e sincroniza `<meta name="csrf-token">` e `input[name="_token"]` para evitar falsos "Sessão expirada" em formulários longos.

**Arquivos:**
- `public/assets/js/api.js` — métodos `startHeartbeat`, `stopHeartbeat`, `_heartbeatTick` + auto-start no fim do arquivo.

---

### AuthMiddleware

**Arquivo:** `app/Middleware/AuthMiddleware.php`

Verifica se o usuário está autenticado.

**Proteções de sessão:**
- `cookie_httponly: true` - Previne acesso via JavaScript
- `cookie_secure: true` - Apenas HTTPS
- `cookie_samesite: Strict` - Previne CSRF
- `session_regenerate_id()` - Regenera ID após login

#### Cookie `remember_token` (Auth.php)

Usa a sintaxe de array do `setcookie` com todas as flags de segurança:

| Flag | Valor |
|------|-------|
| `httponly` | `true` |
| `samesite` | `Lax` |
| `secure` | `true` se `HTTPS=on` OU `HTTP_X_FORWARDED_PROTO=https` |
| `expires` | +30 dias |

TTL: 30 dias. Token em claro no cookie; hash SHA-256 no BD (`funcionarios_tokens.token`). Logout + `deleteRememberToken()` usam as mesmas flags para garantir remoção correta no navegador.

---

### PermissionMiddleware

**Arquivo:** `app/Middleware/PermissionMiddleware.php`

Sistema de autorização baseado em roles (RBAC).

```php
// Verificar permissão no controller
if (!Auth::can('clientes.editar')) {
    Response::json(['error' => 'Sem permissão'], 403);
}
```

---

### WebSystemAccessMiddleware

**Arquivo:** `app/Middleware/WebSystemAccessMiddleware.php`

Executado depois do `AuthMiddleware` em todas as rotas do sistema
administrativo. Exige `dashboard.visualizar`; quando a permissao nao existe,
remove a sessao e o token `remember_token` e retorna ao login. Requisicoes JSON
recebem HTTP 403 com `redirect: /login`.

Esse bloqueio e exclusivo do sistema web. O aplicativo React Native de
vistoria usa `app_vistoria.visualizar` e um fluxo de autenticacao separado.

---

## Services de Segurança

### SecurityLogService

**Arquivo:** `app/Services/SecurityLogService.php`

Registra todos os eventos de segurança na tabela `security_logs`.

| Evento | Descrição | Score |
|--------|-----------|-------|
| `rate_limit` | Limite excedido | 0 |
| `fingerprint` | Padrão suspeito | Variável |
| `quota` | Quota excedida | 0 |
| `honeypot` | Acesso a armadilha | 100 |
| `block` | IP bloqueado | 100 |
| `suspicious` | Comportamento suspeito | Variável |
| `cross_tenant_attempt` | Tentativa cross-tenant | 15/tentativa |

**Documentação completa:** [logs.md](./logs.md#securitylogservice)

---

### CrossTenantDetectionService

**Arquivo:** `app/Services/CrossTenantDetectionService.php`

Detecta quando um usuário tenta acessar IDs de outros tenants.

```php
// Uso manual
$result = CrossTenantDetectionService::check('clientes', $id);

// Uso via trait no Model
$cliente = $model->buscarPorIdComDeteccao($id);
```

**Tabelas monitoradas:** clientes, contratos, veiculos, financeiro, funcionarios, reservas, manutencoes

**Documentação completa:** [logs.md](./logs.md#crosstenantdetectionservice)

---

### RequestFingerprintService

**Arquivo:** `app/Services/RequestFingerprintService.php`

Analisa padrões de requisição para detectar bots.

**Fatores analisados:**

| Fator | Peso | Descrição |
|-------|------|-----------|
| Missing headers | 20 | Falta Accept-Language, etc. |
| Suspicious UA | 30 | curl, wget, python-requests |
| Timing anomaly | 25 | Intervalos muito regulares |
| Sequential pages | 15 | Acessa page=1,2,3,4... rápido |
| Datacenter IP | 40 | AWS, Google Cloud, etc. |

---

## Proteções Automáticas

### Multi-tenancy (QueryBuilder)

**Arquivo:** `app/Classes/QueryBuilder.php`

Todas as queries são filtradas automaticamente por `$_SESSION['chave']`.

```php
// Query automaticamente filtrada
$clientes = $qb->select('clientes'); // WHERE chave = $_SESSION['chave']

// Desabilitar para tabelas compartilhadas
$estados = $qb->withoutChave()->select('estados');
```

**Documentação completa:** [multi-tenancy.md](./multi-tenancy.md)

---

### SQL Injection (Prepared Statements)

Todas as queries usam prepared statements automaticamente.

```php
// Seguro - parâmetros são escapados
$cliente = $qb->where('cpf', '=', $_POST['cpf'])->first();
```

---

### XSS Prevention

**Output escaping em views:**
```php
<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>
```

**JSON responses:**
```php
json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
```

---

### Hashing de senhas (Argon2id)

**Arquivo:** `app/Core/Auth.php`, `app/Models/Funcionario.php`,
`app/Controllers/PublicWebsiteController.php`,
`app/Controllers/FornecedoresController.php`,
`app/Services/PortalAuthService.php` e
`app/Services/TenantProvisioningService.php`

Todas as senhas novas (funcionários, clientes, fornecedores investidores e
provisionamento WHMCS) usam `password_hash($senha, PASSWORD_ARGON2ID)`.
Argon2id é resistente a GPU/ASIC (Memory-Hard), superior a bcrypt para ataques
offline pós-vazamento.

**Rehash transparente (zero downtime):** no `Auth::attempt()` (funcionários),
`PublicWebsiteController::clienteLogin()` (clientes na reserva) e
`PortalAuthService::login()` (clientes e investidores no portal), após
`password_verify()` bem-sucedido o código chama
`password_needs_rehash($hashAtual, PASSWORD_ARGON2ID)` e, se necessário,
re-hashea com Argon2id e atualiza o BD. Usuários legados migram automaticamente
no próximo login, sem pedir para trocar senha.

### Reset de senha de cliente (token one-time)

**Arquivos:** `app/Models/ClientePasswordReset.php`, `app/Controllers/PublicWebsiteController.php`, tabela `cliente_password_resets` (migration 00328).

Endpoint `/api/public/cliente-senha-reset` **não** envia senha em texto plano por email. Gera token de 64 hex chars (~256 bits), grava hash SHA-256 no BD com `expires_at = agora+60min` e `used_at = null`, e envia link por email via template `cliente_nova_senha`. Cliente clica → form HTML standalone (`/public/redefinir-senha?token=...`) → POST `/api/public/cliente-senha-definir` valida token + CSRF da sessão e aplica nova senha com Argon2id. Token marcado `used_at` após uso (single-use) e todos os tokens pendentes anteriores do mesmo cliente são invalidados ao gerar um novo.

Ver [website.md](./website.md#esqueci-minha-senha) para detalhes do fluxo.

### Segurança do Portal do Cliente e do Investidor

O portal publicado no website possui uma sessão própria, independente da
sessão de funcionários e do login usado durante a reserva.

O acesso usa duas credenciais server-to-server:

- `X-Site-Token` autentica o website ativo e estabelece o tenant;
- `X-Portal-Token` identifica perfil e entidade da sessão.

O token opaco do portal tem 32 bytes aleatórios. Somente seu hash SHA-256 é
armazenado em `portal_sessions`; o valor original permanece na sessão PHP do
website e não é exposto ao JavaScript. A API sempre deriva `id_cliente` ou
`id_fornecedor` da sessão, impedindo IDOR por identificadores enviados pelo
navegador.

| Controle | Regra |
|----------|-------|
| Inatividade | 30 minutos |
| Expiração absoluta | 12 horas |
| Navegador | Sessão vinculada ao hash do user-agent |
| Logout | Grava `revoked_at` |
| Troca/reset de senha | Revoga todas as sessões da entidade |
| Tentativas de login | 5 falhas bloqueiam por 15 minutos |
| Senha | Argon2id, mínimo de 8 caracteres |
| Reset | Token one-time, hash SHA-256, validade de 60 minutos |

Login e reset aceitam e-mail ou CPF/CNPJ apenas quando existe exatamente um
cadastro compatível no tenant e perfil selecionados. Resultado inexistente ou
duplicado recebe resposta neutra. Cliente precisa estar ativo e fornecedor
precisa ter `investidor = 1`.

Os proxies do website usam cookie local `HttpOnly` e `SameSite=Lax`, com
`Secure` quando o site está em HTTPS, regeneram o ID após login e exigem CSRF
nas alterações. IP e user-agent do navegador são encaminhados em
`X-Portal-Client-IP` e `X-Portal-Client-Agent`; a API só considera esses
headers depois de validar o site token.

Atualizações cadastrais usam whitelist e são registradas em
`portal_audit_logs`. Alterações de senha nunca registram valor ou hash.

Consulte [Portal do Cliente e do Fornecedor Investidor](./portal-cliente-investidor.md)
para rotas, perfis e fluxo completo.

### IP do cliente (TRUSTED_PROXIES)

**Arquivo:** `app/Core/Request.php::ip()`

`$request->ip()` só confia em `X-Forwarded-For` ou `Client-IP` se `$_SERVER['REMOTE_ADDR']` estiver na env `TRUSTED_PROXIES` (CSV). Valida o IP extraído com `FILTER_VALIDATE_IP`. Atrás de Cloudflare/Nginx sem `TRUSTED_PROXIES` preenchido, o rate limit degrada para ver só o IP do proxy — configure a env para cada ambiente de produção.

### HTTP Security Headers (public/.htaccess)

| Header | Valor | Motivo |
|--------|-------|--------|
| `X-Frame-Options` | `SAMEORIGIN` | Anti-clickjacking |
| `X-Content-Type-Options` | `nosniff` | Impede MIME sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Vaza só origin em cross-site |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (só em HTTPS via `expr`) | Força HTTPS por 1 ano |
| `Permissions-Policy` | `camera=(self), microphone=(self), geolocation=(self), payment=(), usb=(), interest-cohort=()` | Bloqueia APIs sensíveis por padrão |
| `Content-Security-Policy-Report-Only` | *(comentada — descomentar após mapear CDNs)* | Preparação para rollout CSP |

### Upload: defesa em profundidade (storage/uploads/.htaccess)

Arquivos ficam em `storage/uploads/{chave}/` — fora de `public/` por design, servidos via Controller. Como segunda camada, `storage/uploads/.htaccess` desabilita `mod_php`, `RemoveHandler` de extensões perigosas e força `text/plain` para `.php/.phtml/.phar`. Blinda caso algum deploy futuro exponha o diretório por engano.

### Cache seguro (Redis)

**Arquivo:** `app/Core/Cache.php`

`unserialize()` dos valores do Redis usa `['allowed_classes' => false]` — bloqueia gadget chains de deserialização mesmo se um atacante conseguir escrever no Redis interno. Payload só deserializa tipos escalares/array; objetos viram `__PHP_Incomplete_Class`.

### Webhook Stripe — assinatura obrigatória

**Arquivo:** `app/Controllers/SerproWebhookController.php::webhookStripe`

Se `STRIPE_WEBHOOK_SECRET` não estiver configurado, o endpoint retorna **500** e loga alerta — **não** aceita payload sem assinatura. Qualquer POST sem cabeçalho `Stripe-Signature` válido é rejeitado com 401. Logs de webhook foram reduzidos a metadata (contadores) para evitar PII em `error_log`.

### Filtro de Filiais

**Arquivo:** `app/Helpers/FilialHelper.php`

Segunda camada de controle de acesso dentro do tenant.

```php
// Verificar acesso individual
if (!FilialHelper::temAcessoFilial($registro['id_matriz_filial'])) {
    Response::json(['error' => 'Acesso negado'], 403);
}

// Filtrar listagens
[$where, $params] = FilialHelper::whereFiliais('id_matriz_filial');
```

**Documentação completa:** [filial-helper.md](./filial-helper.md)

---

## Configuração

**Arquivo:** `app/Config/Security.php`

### Constantes Disponíveis

| Constante | Descrição |
|-----------|-----------|
| `RATE_LIMIT` | Limites por endpoint/método |
| `FINGERPRINT` | Pesos e thresholds de suspeita |
| `THROTTLE` | Delays por faixa de score |
| `QUOTA` | Limites por plano de assinatura |
| `HONEYPOT` | Endpoints armadilha |
| `BLOCKED_IP` | IPs bloqueados permanentemente |
| `CROSS_TENANT` | Detecção cross-tenant |
| `LOGGING` | Eventos a serem logados |

---

## Monitoramento

### Consultar Logs de Segurança

```php
// Estatísticas por período
$stats = SecurityLogService::getStats('2025-01-01', '2025-01-31');

// IPs mais suspeitos
$ips = SecurityLogService::getTopSuspiciousIps(10, 7);

// Limpar logs antigos (30 dias)
$removed = SecurityLogService::cleanup();
```

### Verificar Usuário Suspeito

```php
if (CrossTenantDetectionService::isUserSuspicious()) {
    // Usuário fez 5+ tentativas cross-tenant
}

$stats = CrossTenantDetectionService::getUserAttemptStats();
```

---

## Arquivos Relacionados

| Categoria | Arquivos |
|-----------|----------|
| **Middlewares** | `app/Middleware/RateLimitMiddleware.php`, `HoneypotMiddleware.php`, `ThrottlingMiddleware.php`, `CsrfMiddleware.php`, `ApiCsrfMiddleware.php`, `BlockedIpMiddleware.php`, `AuthMiddleware.php`, `WebSystemAccessMiddleware.php`, `PermissionMiddleware.php` |
| **Services** | `app/Services/SecurityLogService.php`, `CrossTenantDetectionService.php`, `RequestFingerprintService.php` |
| **Models** | `app/Models/Security/SecurityLog.php`, `RateLimit.php`, `BlockedIp.php`, `UserQuota.php`, `RequestFingerprint.php` |
| **Config** | `app/Config/Security.php` |
| **Helpers** | `app/Helpers/FilialHelper.php` |
| **Core** | `app/Classes/QueryBuilder.php`, `app/Core/Auth.php`, `app/Core/Session.php` |

---

## Documentação Relacionada

- **[Logs](./logs.md)** - AuditLogService, SecurityLogService, CrossTenantDetectionService
- **[Multi-tenancy](./multi-tenancy.md)** - Isolamento por chave
- **[FilialHelper](./filial-helper.md)** - Controle de acesso por filiais
- **[Best Practices](./best-practices.md)** - Boas práticas de segurança
- **[API](./api.md)** - Helper para requisições com CSRF
- **[Portal Cliente/Investidor](./portal-cliente-investidor.md)** - Autenticação
  pública em duas camadas, sessão opaca e isolamento dos perfis
