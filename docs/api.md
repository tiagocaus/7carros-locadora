# API - Requisições JavaScript

## Visão Geral

Todos os endpoints da API (`/api/*`) são protegidos por validação de token CSRF.
Requisições diretas via navegador são bloqueadas com erro 419.

Para fazer requisições à API, use o helper JavaScript `API` disponível globalmente.

## Helper JavaScript API

Localização: `/public/assets/js/api.js`

Carregado automaticamente em todas as páginas que usam o layout `iframe.php`.

### Métodos Disponíveis

| Método | Uso |
|--------|-----|
| `API.get(url, params)` | Requisições GET |
| `API.post(url, data)` | Requisições POST com JSON |
| `API.postForm(url, formData)` | Requisições POST com FormData |
| `API.put(url, data)` | Requisições PUT |
| `API.delete(url, data)` | Requisições DELETE |

### Exemplos de Uso

#### GET - Listar registros

```javascript
// Listar clientes com paginação
const result = await API.get('/api/clientes', {
    page: 1,
    perPage: 10,
    search: 'João'
});

if (result.success) {
    console.log(result.data);        // Array de clientes
    console.log(result.pagination);  // Info de paginação
}
```

#### POST - Criar registro

```javascript
const result = await API.post('/clientes/salvar', {
    nome_rsocial: 'João Silva',
    email: 'joao@email.com'
});

if (result.success) {
    console.log('Cliente criado com ID:', result.id);
}
```

#### POST com FormData - Upload de arquivos

```javascript
const formData = new FormData(document.getElementById('meuForm'));
const result = await API.postForm('/clientes/salvar', formData);
```

### Tratamento de Erros

O helper trata automaticamente erros comuns:

| Status | Comportamento |
|--------|---------------|
| **419** | Token CSRF inválido → Lança erro "Sessão expirada" |
| **401** | Não autenticado → Redireciona para `/login` |

```javascript
try {
    const result = await API.get('/api/clientes');
    // Sucesso
} catch (error) {
    console.error(error.message);
    // "Sessão expirada. Por favor, recarregue a página."
}
```

## Modo Antigo vs Modo Correto

### ❌ ERRADO - `fetch()` direto não funciona

```javascript
// ERRO 419: Token CSRF inválido
const response = await fetch('/api/clientes');
const result = await response.json();
```

### ✅ CORRETO - Usar helper `API`

```javascript
// Token CSRF enviado automaticamente no header
const result = await API.get('/api/clientes');
```

## Por Que Esta Proteção?

1. **Segurança**: Impede que dados sejam acessados diretamente via navegador
2. **Proteção CSRF**: Valida que a requisição vem do próprio sistema
3. **Controle**: Apenas código JavaScript interno consegue acessar a API

## Detalhes Técnicos

### Middleware: ApiCsrfMiddleware

Localização: `/app/Middleware/ApiCsrfMiddleware.php`

Valida o header `X-CSRF-TOKEN` em todas as requisições para `/api/*`.

```php
<?php
namespace App\Middleware;

class ApiCsrfMiddleware
{
    public function handle(Request $request): bool
    {
        $token = $request->header('X-CSRF-TOKEN');
        $sessionToken = Session::get('csrf_token');

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            Response::json([
                'success' => false,
                'message' => 'Token CSRF inválido ou ausente'
            ], 419);
            return false;
        }

        return true;
    }
}
```

### Rotas Protegidas

Todas as rotas que começam com `/api/` usam o middleware `api_csrf`:

```php
// web.php
$router->get('/api/clientes', [...], ['api_csrf', 'rate_limit', 'throttle']);
$router->get('/api/funcionarios', [...], ['api_csrf', 'rate_limit', 'throttle']);
$router->get('/api/roles', [...], ['api_csrf', 'rate_limit', 'throttle']);
```

### Token CSRF no HTML

O token é disponibilizado via meta tag nos layouts:

```html
<meta name="csrf-token" content="<?= Session::get('csrf_token') ?>">
```

A diretiva `@csrfMeta` no template Blade gera esta tag automaticamente.

O helper `API` lê automaticamente este token via:

```javascript
document.querySelector('meta[name="csrf-token"]')?.content
```

## Checklist para Novas Features

Ao criar novas páginas que fazem requisições à API:

- [ ] Usar layout que carrega `api.js` (ex: `layouts.iframe`)
- [ ] Usar `API.get()` em vez de `fetch()` para endpoints `/api/*`
- [ ] Tratar erros com try/catch
- [ ] Verificar `result.success` antes de usar os dados

## Documentação Relacionada

- **[Arquitetura](./architecture.md)** - Estrutura de middlewares
- **[Boas Práticas](./best-practices.md)** - Guidelines de segurança
