# Integração WHMCS - Provisionamento de Tenants

## Visão Geral

O WHMCS gerencia o ciclo de vida dos tenants (locadoras) no sistema através de webhooks HTTP. Quando um cliente compra, suspende, reativa, muda de plano, atualiza senha ou cancela o serviço no WHMCS, este envia um POST para os endpoints abaixo.

**Base URL:** `https://locadora.7carros.com`

---

## Autenticação

Todas as requisições devem incluir o campo `accesshash` no body do POST com o valor de `TENANT_ONBOARD_SECRET` (configurado em `.env`).

```json
{
    "accesshash": "<TENANT_ONBOARD_SECRET>",
    "chave": "...",
    ...
}
```

O WHMCS envia automaticamente o `accesshash` do Server Module em cada chamada. A comparação usa `hash_equals()` (timing-safe). Rate limit: 10 requisições/minuto por IP.

**Respostas de erro de autenticação:**
- `401` — `accesshash` inválido ou ausente
- `503` — `TENANT_ONBOARD_SECRET` não configurado no servidor

---

## Endpoints

### 1. Criar Tenant

Cria o usuário admin, a matriz (filial principal), a role "Proprietário" com todas as permissões e vincula tudo.

```
POST /webhook/whmcs/criar
```

**Parâmetros:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `chave` | string | Sim | Identificador único do tenant (ex: hash MD5) |
| `nomeCompleto` | string | Sim | Nome completo do proprietário |
| `email` | string | Sim | Email do proprietário |
| `usuario` | string | Sim | Nome de usuário para login (único globalmente) |
| `senha` | string | Sim | Senha (será hasheada com bcrypt) |
| `plano` | string | Sim | Código do plano (ver [Planos Válidos](#planos-válidos)) |
| `razao_social` | string | Não | Razão social da empresa (default: nomeCompleto) |
| `nome_fantasia` | string | Não | Nome fantasia (default: nomeCompleto) |
| `cpf_cnpj` | string | Não | CPF ou CNPJ |

**Exemplo:**
```bash
curl -X POST https://locadora.7carros.com/webhook/whmcs/criar \
  -H "Content-Type: application/json" \
  -d '{
    "accesshash": "<TENANT_ONBOARD_SECRET>",
    "chave": "9B9B05072DD20D1CC3E54607B84C889B",
    "nomeCompleto": "Tiago Pereira Caus",
    "email": "tiago_caus@hotmail.com",
    "usuario": "tiago2793",
    "senha": "o75ae9Jh0Z",
    "plano": "P4"
  }'
```

**Resposta (201 — criado):**
```json
{
    "success": true,
    "already_existed": false,
    "chave": "9B9B05072DD20D1CC3E54607B84C889B",
    "id_funcionario": 123,
    "id_matriz_filial": 456,
    "usuario": "tiago2793"
}
```

**Idempotência:** Se a `chave` já existe, retorna `200` com `"already_existed": true` e os dados do tenant existente. Não duplica.

**Erros:**
- `400` — Campos obrigatórios ausentes ou plano inválido
- `409` — Usuário já em uso por outro tenant

---

### 2. Suspender Tenant

Muda o status de todos os funcionários do tenant de `A` (Ativo) para `S` (Suspenso). Usuários suspensos não conseguem fazer login.

```
POST /webhook/whmcs/suspender
```

**Parâmetros:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `chave` | string | Sim | Identificador do tenant |

**Exemplo:**
```bash
curl -X POST https://locadora.7carros.com/webhook/whmcs/suspender \
  -H "Content-Type: application/json" \
  -d '{"accesshash": "<TENANT_ONBOARD_SECRET>", "chave": "9B9B05072DD20D1CC3E54607B84C889B"}'
```

**Resposta (200):**
```json
{
    "success": true,
    "affected_users": 3
}
```

**Idempotência:** Suspender um tenant já suspenso retorna `affected_users: 0`.

---

### 3. Reativar Tenant

Muda o status de todos os funcionários suspensos de `S` (Suspenso) para `A` (Ativo).

```
POST /webhook/whmcs/reativar
```

**Parâmetros:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `chave` | string | Sim | Identificador do tenant |

**Exemplo:**
```bash
curl -X POST https://locadora.7carros.com/webhook/whmcs/reativar \
  -H "Content-Type: application/json" \
  -d '{"accesshash": "<TENANT_ONBOARD_SECRET>", "chave": "9B9B05072DD20D1CC3E54607B84C889B"}'
```

**Resposta (200):**
```json
{
    "success": true,
    "affected_users": 3
}
```

---

### 4. Mudar Plano

Atualiza o plano de todos os funcionários do tenant.

```
POST /webhook/whmcs/mudar-pacote
```

**Parâmetros:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `chave` | string | Sim | Identificador do tenant |
| `plano` | string | Sim | Novo código do plano |

**Exemplo:**
```bash
curl -X POST https://locadora.7carros.com/webhook/whmcs/mudar-pacote \
  -H "Content-Type: application/json" \
  -d '{"accesshash": "<TENANT_ONBOARD_SECRET>", "chave": "9B9B05072DD20D1CC3E54607B84C889B", "plano": "P4"}'
```

**Resposta (200):**
```json
{
    "success": true,
    "plano_anterior": "P2",
    "plano_novo": "P4",
    "affected_users": 3
}
```

---

### 5. Atualizar Senha

Atualiza a senha de um funcionário específico (identificado por `chave` + `usuario`).

```
POST /webhook/whmcs/atualizar-senha
```

**Parâmetros:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `chave` | string | Sim | Identificador do tenant |
| `usuario` | string | Sim | Nome de usuário do funcionário |
| `senha` | string | Sim | Nova senha (será hasheada com bcrypt) |

**Exemplo:**
```bash
curl -X POST https://locadora.7carros.com/webhook/whmcs/atualizar-senha \
  -H "Content-Type: application/json" \
  -d '{
    "accesshash": "<TENANT_ONBOARD_SECRET>",
    "chave": "9B9B05072DD20D1CC3E54607B84C889B",
    "usuario": "tiago2793",
    "senha": "novaSenha123"
  }'
```

**Resposta (200):**
```json
{
    "success": true,
    "usuario": "tiago2793"
}
```

---

### 6. Terminar/Apagar Tenant

Apaga **todos** os dados do tenant: registros no banco de dados e pasta de uploads (`storage/uploads/{chave}`).

```
POST /webhook/whmcs/terminar
```

**Parâmetros:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `chave` | string | Sim | Identificador do tenant |

**Exemplo:**
```bash
curl -X POST https://locadora.7carros.com/webhook/whmcs/terminar \
  -H "Content-Type: application/json" \
  -d '{"accesshash": "<TENANT_ONBOARD_SECRET>", "chave": "9B9B05072DD20D1CC3E54607B84C889B"}'
```

**Resposta (200):**
```json
{
    "success": true,
    "deleted_tables": {
        "funcionarios": 3,
        "matrizes_filiais": 1,
        "veiculos": 15,
        "clientes": 42
    }
}
```

> **Atenção:** Esta acao e irreversivel. Remove TODOS os registros com a chave do tenant e a pasta `storage/uploads/{chave}`.

---

## Planos Válidos

| Código | Nome | Veículos | Filiais |
|--------|------|----------|---------|
| `G` | Gratuito | 3 | 1 |
| `P0` | Junior | 3 | 1 |
| `P1` | Iniciante | 5 | 1 |
| `P2` | Intermediário | 10 | 1 |
| `P3` | Avançado | 20 | 3 |
| `P4` | Ilimitado | Ilimitado | Ilimitado |

Configuração completa em `app/Config/Planos.php`.

---

## Códigos de Resposta HTTP

| Código | Significado |
|--------|-------------|
| `200` | Operação realizada com sucesso |
| `201` | Tenant criado com sucesso |
| `400` | Erro de validação (campos ausentes, plano inválido) |
| `401` | Autenticação falhou (token inválido) |
| `404` | Tenant não encontrado (chave inexistente) |
| `409` | Conflito (usuário já em uso) |
| `429` | Rate limit excedido (máx 10 req/min) |
| `500` | Erro interno do servidor |
| `503` | TENANT_ONBOARD_SECRET não configurado |

---

## Status de Funcionários

| Código | Significado |
|--------|-------------|
| `A` | Ativo — pode fazer login |
| `S` | Suspenso — não pode fazer login |

---

## Configuração no WHMCS

### Server Module

No WHMCS, configure um **Server** apontando para o sistema:

1. **Hostname:** `locadora.7carros.com`
2. **Access Hash:** O valor de `TENANT_ONBOARD_SECRET` do `.env` (enviado automaticamente como campo `accesshash` em cada POST)

### Product/Service Configuration

Configure cada ação do produto para chamar o endpoint correspondente:

| Ação WHMCS | Endpoint | Método |
|------------|----------|--------|
| Create | `/webhook/whmcs/criar` | POST |
| Suspend | `/webhook/whmcs/suspender` | POST |
| Unsuspend | `/webhook/whmcs/reativar` | POST |
| Change Package | `/webhook/whmcs/mudar-pacote` | POST |
| Change Password | `/webhook/whmcs/atualizar-senha` | POST |
| Terminate | `/webhook/whmcs/terminar` | POST |

### Mapeamento de Campos WHMCS → Sistema

| Campo WHMCS | Campo do Endpoint |
|-------------|-------------------|
| `customfield[chave]` ou gerado | `chave` |
| `clientsdetails.firstname + lastname` | `nomeCompleto` |
| `clientsdetails.email` | `email` |
| `username` (gerado) | `usuario` |
| `password` (gerado) | `senha` |
| `configoption[plano]` | `plano` |

---

## Auditoria

Todas as ações são registradas na tabela `logs` com prefixo `[WHMCS]`:

```
[WHMCS] Tenant criado. Plano: P4, Usuário: tiago2793
[WHMCS] Tenant suspenso via WHMCS. 3 usuário(s) afetado(s).
[WHMCS] Tenant reativado via WHMCS. 3 usuário(s) afetado(s).
[WHMCS] Plano alterado via WHMCS: P2 → P4. 3 usuário(s) afetado(s).
[WHMCS] Senha atualizada via WHMCS para usuário: tiago2793
[WHMCS] Tenant terminado via WHMCS. 3 usuário(s) afetado(s).
```

Para filtrar logs WHMCS no banco:
```sql
SELECT * FROM logs WHERE mensagem LIKE '[WHMCS]%' ORDER BY data DESC;
```

---

## Arquitetura Técnica

### Arquivos

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/Middleware/WhmcsAuthMiddleware.php` | Valida Bearer token |
| `app/Controllers/WhmcsController.php` | Validação de input, delegação ao Service |
| `app/Services/TenantProvisioningService.php` | Lógica de negócio (CRUD de tenant) |
| `app/Routes/web.php` | Definição das 6 rotas POST |
| `app/Config/Security.php` | Rate limit para `/webhook/whmcs` |

### Multi-tenancy

Os endpoints WHMCS operam **sem sessão** (`$_SESSION['chave']` não existe). Por isso, todas as queries usam `withoutChave()` — exceção legítima para operações administrativas cross-tenant.

### Segurança

- **accesshash** via POST com comparação timing-safe (`hash_equals`)
- **Rate limiting** — 10 req/min por IP
- **Senhas** hasheadas com `password_hash(PASSWORD_DEFAULT)` (bcrypt)
- **Logs** de tentativas de autenticação falhas via `error_log()`
- **Idempotência** no endpoint de criação (evita duplicatas)

---

## Troubleshooting

### "Service unavailable" (503)
`TENANT_ONBOARD_SECRET` não está definido no `.env`. Verificar `.env.production`.

### "Unauthorized" (401)
O `accesshash` enviado não confere com o `TENANT_ONBOARD_SECRET`. Verificar:
- Campo `accesshash` presente no body do POST
- Valor confere com `TENANT_ONBOARD_SECRET` do `.env`
- Access Hash configurado corretamente no Server do WHMCS

### "Tenant não encontrado" (404)
A `chave` enviada não existe na tabela `funcionarios`.

### "Usuário já está em uso" (409)
O `usuario` enviado no criar já existe para outro tenant. Gerar um username diferente.

### Erros no error_log
Buscar por `[WHMCS]` no log do PHP:
```bash
grep '\[WHMCS\]' /var/log/php/error.log
```
