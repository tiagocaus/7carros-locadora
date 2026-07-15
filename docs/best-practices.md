# Boas Práticas e Guidelines de Desenvolvimento

## Segurança

### 1. Prevenção de SQL Injection

**SEMPRE use QueryBuilder com prepared statements. NUNCA concatene input do usuário em queries.**

❌ **ERRADO (vulnerável):**
```php
$rs = $mysqli->query("SELECT * FROM clientes WHERE id = '".$_POST['id']."'");
```

✅ **CORRETO (seguro):**
```php
$resultado = $qb->select('clientes', ['*'], 'id = ?', [$_POST['id']]);
```

**Regras:**
- Todo input do usuário deve ser passado como parâmetro preparado
- Nunca confie em dados de `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`
- Use QueryBuilder para TODAS as operações de banco
- Não construa SQL manualmente, mesmo com escaping

### 2. Isolamento Multi-tenant

**Garanta que o filtro `chave` esteja ativo para dados específicos de tenant.**

❌ **ERRADO (vaza dados entre tenants — cross-tenant aberto):**
```php
// Anti-padrão #1: sem filtro de chave — qualquer ID acessa qualquer tenant
$cliente = $qb->table('clientes')->withoutChave()->where('id', '=', $id)->first();

// Anti-padrão #2: redundante — withoutChave seguido de where('chave') é igual
// a não usar withoutChave, mas pior (código confuso, pode virar bug na manutenção)
$clientes = $qb->table('clientes')->withoutChave()->where('chave', '=', $chave)->get();
```

✅ **CORRETO (isolado por tenant):**
```php
// Filtro automático do QueryBuilder aplica WHERE chave = $_SESSION['chave']
$cliente = $qb->table('clientes')->where('id', '=', $id)->first();
$clientes = $qb->table('clientes')->get();
```

**`withoutChave()` SÓ nos seguintes contextos:**

| Contexto | Motivo |
|----------|--------|
| `app/Crons/Jobs/*` | CRON processa cross-tenant |
| `app/Core/Auth.php` | Login — sessão ainda não existe |
| `app/Database/migrations/**` | Rodam sem sessão |
| `TenantProvisioningService` | Tenant sendo criado |
| Webhook de gateway/Serpro | Servidor externo, sem sessão |
| Rota pública (pagamento, site) | Cliente final sem login |
| Tabelas globais (`chave='0'` ou sem coluna chave) | Recursos do sistema |

**NUNCA para:** CRUD normal de Cliente, Veículo, Contrato, Locação, Financeiro, Manutenção, etc.

> 📖 Detalhes completos em `docs/querybuilder.md` → seção "withoutChave()"

### 3. Filtros de Acesso por Filiais

**Além do isolamento por tenant (`chave`), aplique filtros de acesso por filiais quando necessário.**

O sistema suporta controle de acesso multi-filial, onde funcionários só veem registros das filiais às quais estão vinculados.

```php
use App\Helpers\FilialHelper;

// Em listagens (index)
[$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');
$clientes = $model->listarPaginado($page, $perPage, $search, $filialWhere, $filialParams);

// Em operações individuais (show/update/delete)
if (!FilialHelper::temAcessoFilial($registro['id_matriz_filial'] ?? null)) {
    Response::json(['erro' => 'Acesso negado'], 403);
    return;
}
```

**Métodos especiais para módulos específicos:**
- `FilialHelper::whereLocacoes()` - Filtra por retirada OU devolução
- `FilialHelper::whereContratos()` - Filtra por `id_matriz_filial_retirada`

**Referência completa:** [FilialHelper](filial-helper.md)

### 4. Upload de Arquivos com FileHelper

**Use o FileHelper para todos os uploads. Ele centraliza validação, salvamento e URLs seguras.**

```php
use App\Helpers\FileHelper;

// Salvar arquivo base64 (enviado do frontend)
$fotoBase64 = $request->input('foto_base64', '');
if (!empty($fotoBase64)) {
    $filename = FileHelper::save($fotoBase64, 'foto_cliente');
    if ($filename) {
        $dados['foto'] = $filename;
    }
}

// Gerar URL pública segura (para API responses)
$registro['foto_url'] = !empty($registro['foto'])
    ? FileHelper::url($registro['foto'], Auth::chave())
    : '';

// Deletar arquivo
if (!empty($registro['foto'])) {
    FileHelper::delete($registro['foto'], Auth::chave());
}
```

**Vantagens do FileHelper:**
- URLs não expõem caminho real (`/files/{token}` em vez de `/storage/uploads/...`)
- Validação automática de tipos de arquivo
- Multi-tenancy respeitado (arquivos por chave)
- Tokens assinados com HMAC-SHA256

**Referência:** [file-helper.md](./file-helper.md) para documentação completa.

### 5. Proteção de Variáveis de Ambiente

**NUNCA commite arquivos `.env` com credenciais reais.**

```bash
# .gitignore
.env.development
.env.production
.env
```

**Checklist:**
- ✅ `.env.example` committado (sem valores reais)
- ❌ `.env.development` NÃO committado
- ❌ `.env.production` NÃO committado
- ✅ Usar variáveis de ambiente para credenciais sensíveis

### 6. Output Escaping (XSS Prevention)

**Sempre escape output em views HTML.**

```php
<!-- ❌ VULNERÁVEL a XSS -->
<div><?= $cliente['nome'] ?></div>

<!-- ✅ SEGURO -->
<div><?= htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') ?></div>

<!-- Para JSON responses -->
<?php
header('Content-Type: application/json');
echo json_encode($dados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
```

### 7. Autenticação e Sessões

```php
// Iniciar sessão de forma segura
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true, // HTTPS apenas
    'cookie_samesite' => 'Strict'
]);

// Regenerar ID após login
session_regenerate_id(true);
```

### 8. Requisições AJAX à API

**SEMPRE use o helper `API` para requisições à API. Nunca use `fetch()` direto.**

❌ **ERRADO (retorna erro 419):**
```javascript
const response = await fetch('/api/clientes');
const result = await response.json();
```

✅ **CORRETO (token CSRF automático):**
```javascript
const result = await API.get('/api/clientes');
```

**Por quê?**
- Endpoints `/api/*` exigem token CSRF no header `X-CSRF-TOKEN`
- O helper `API` envia o token automaticamente
- Isso impede acesso direto via navegador aos dados da API

**Métodos do helper:**
- `API.get(url, params)` - Requisições GET
- `API.post(url, data)` - Requisições POST com JSON
- `API.postForm(url, formData)` - Requisições POST com FormData

Veja **[API - Requisições JavaScript](./api.md)** para documentação completa.

### 9. Autocomplete em Formulários Internos

**NÃO habilite autocomplete em telas internas com dados de clientes, contratos, locações, financeiro ou credenciais.**

O sistema carrega `public/assets/js/autocomplete-guard.js` nos layouts internos para aplicar `autocomplete="off"` globalmente e proteger campos criados dinamicamente. Campos de senha internos recebem `autocomplete="new-password"`.

**Regras:**
- Não adicione `autocomplete="email"`, `autocomplete="name"`, `autocomplete="tel"`, `autocomplete="street-address"` ou similares em views autenticadas.
- Não remova o `autocomplete-guard.min.js` de `layouts/iframe.php` nem de `layouts/app.php`.
- Para uma exceção intencional, use `data-allow-autocomplete="true"` no container e documente o motivo.
- Não altere login público nem pagamento público: esses fluxos usam atributos de autocomplete próprios e intencionais.

## Implementação de Features

### Estrutura de APIs (SoC)

**NUNCA** coloque endpoints de uma entidade dentro do controller de outra.

```
Correto:   /api/clientes/buscar    → ClientesController
Correto:   /api/oficinas/buscar    → OficinasController
Incorreto: /api/financeiro/clientes → FinanceiroController
```

**Checklist para novos endpoints de busca:**
- [ ] O endpoint está no controller da própria entidade?
- [ ] A rota segue o padrão `/api/{entidade}/buscar`?
- [ ] O retorno usa formato `{ id, text }` para selects?
- [ ] O filtro de tenant (`chave`) está aplicado?

### Estrutura Recomendada

Ao adicionar uma nova feature, siga esta ordem:

1. **Model** - Camada de dados
2. **Service** (se necessário) - Lógica de negócio
3. **Controller** - Handler de requisições
4. **View** - Template HTML
5. **Router** - Definir rota

### Exemplo: Feature "Criar Cliente"

**1. Model (`app/Models/Cliente.php`):**
```php
namespace App\Models;

use App\Classes\QueryBuilder;

class Cliente
{
    private QueryBuilder $qb;

    public function __construct(QueryBuilder $qb)
    {
        $this->qb = $qb;
    }

    public function create(array $data): int
    {
        // Validação básica
        if (empty($data['nome_rsocial'])) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }

        return $this->qb->insert('clientes', $data);
    }

    public function findAll(): array
    {
        return $this->qb->select('clientes', ['*'], '1=1', [], 'nome_rsocial ASC');
    }
}
```

**2. Controller (`app/Controllers/ClienteController.php`):**
```php
namespace App\Controllers;

use App\Models\Cliente;

class ClienteController
{
    private Cliente $clienteModel;

    public function __construct(Cliente $clienteModel)
    {
        $this->clienteModel = $clienteModel;
    }

    public function store()
    {
        try {
            $data = [
                'nome_rsocial' => $_POST['nome_rsocial'] ?? '',
                'cpf_cnpj' => $_POST['cpf_cnpj'] ?? '',
                'email' => $_POST['email'] ?? ''
            ];

            $id = $this->clienteModel->create($data);

            // Redirecionar ou retornar JSON
            header('Location: /clientes');
            exit;

        } catch (\Exception $e) {
            // Log error e mostrar mensagem
            error_log($e->getMessage());
            $_SESSION['error'] = 'Erro ao criar cliente';
            header('Location: /clientes/new');
            exit;
        }
    }
}
```

**3. View (`app/Views/clientes/new.php`):**
```php
<!DOCTYPE html>
<html>
<head>
    <title>Novo Cliente</title>
</head>
<body>
    <h1>Novo Cliente</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="/clientes">
        <input type="text" name="nome_rsocial" placeholder="Nome" required>
        <input type="text" name="cpf_cnpj" placeholder="CPF/CNPJ" required>
        <input type="email" name="email" placeholder="Email">
        <button type="submit">Salvar</button>
    </form>
</body>
</html>
```

**4. Router (`app/Routers/web.php`):**
```php
$router->get('/clientes/new', [ClienteController::class, 'create']);
$router->post('/clientes', [ClienteController::class, 'store']);
```

## Gerenciamento de Transações

**Use transações para operações que modificam múltiplas tabelas.**

```php
$qb->beginTransaction();

try {
    // Inserir reserva
    $reservaId = $qb->insert('reservas', [
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'data_inicio' => $dataInicio
    ]);

    // Atualizar status do veículo
    $qb->update('veiculos',
        ['situacao' => 'R'], // R = Reservado
        'id = ?',
        [$veiculoId]
    );

    // Gerar contrato
    $contratoId = $qb->insert('contratos', [
        'reserva_id' => $reservaId,
        'valor_total' => $valorTotal
    ]);

    $qb->commit();

    return $reservaId;

} catch (Exception $e) {
    $qb->rollback();
    throw $e;
}
```

## Error Handling

### Logging

```php
// Usar error_log para logging
error_log("Erro ao processar reserva: " . $e->getMessage());

// Para logs estruturados
error_log(json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'level' => 'ERROR',
    'message' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'user_id' => $_SESSION['user_id'] ?? null,
    'chave' => $_SESSION['chave'] ?? null
]));
```

### User-Friendly Messages

```php
try {
    // Operação
} catch (Exception $e) {
    // Log detalhes técnicos
    error_log($e->getMessage());

    // Mostrar mensagem genérica ao usuário
    $_SESSION['error'] = 'Ocorreu um erro. Tente novamente.';

    // Em modo debug, mostrar detalhes
    if ($_ENV['APP_DEBUG'] === 'true') {
        $_SESSION['error'] .= ' (' . $e->getMessage() . ')';
    }
}
```

## Code Style

### PHP Standards

- Seguir PSR-12 para code style
- Usar type hints quando possível (PHP 8.3)
- Documentar métodos complexos com PHPDoc

```php
/**
 * Calcula o valor total de uma reserva
 *
 * @param int $veiculoId ID do veículo
 * @param string $dataInicio Data de início (Y-m-d)
 * @param string $dataFim Data de fim (Y-m-d)
 * @return float Valor total calculado
 * @throws \InvalidArgumentException Se datas forem inválidas
 */
public function calcularValorReserva(
    int $veiculoId,
    string $dataInicio,
    string $dataFim
): float {
    // Implementação
}
```

### Nomenclatura

- Classes: `PascalCase`
- Métodos: `camelCase`
- Constantes: `UPPER_SNAKE_CASE`
- Variáveis: `camelCase` ou `snake_case`

### Namespaces e Imports em Controllers

**Regra de Ouro:** Sempre verifique os `use` statements no topo do arquivo antes de usar uma classe.

#### Classes Comuns e Como Usá-las

| Classe | Uso Correto |
|--------|-------------|
| Database | `\App\Core\Database::fetchOne()` |
| Auth | `Auth::chave()` (já importado) |
| Response | `Response::json()` (já importado) |
| Request | `$request->input()` (já importado) |

#### Padrão no FuncionariosController

O arquivo usa:
```php
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Funcionario;
```

**Database NÃO está importado**, então sempre use o namespace completo: `\App\Core\Database::`

#### Exemplo Correto

```php
// ✅ Correto - namespace completo para classes não importadas
$result = \App\Core\Database::fetchOne("SELECT * FROM ...", $params);
$rows = \App\Core\Database::fetchAll("SELECT * FROM ...", $params);

// ❌ Incorreto - classe não importada causa Fatal Error
$result = Database::fetchOne(...); // Class "App\Controllers\Database" not found!
```

## Performance

### Query Optimization

```php
// ❌ N+1 Query Problem
foreach ($clientes as $cliente) {
    $reservas = $qb->select('reservas', ['*'], 'cliente_id = ?', [$cliente['id']]);
}

// ✅ Single Query with JOIN ou WHERE IN
$clienteIds = array_column($clientes, 'id');
$reservas = $qb->select('reservas', ['*'], 'cliente_id IN (' . implode(',', $clienteIds) . ')');
```

### Caching

```php
// Cache de queries frequentes
$cacheKey = "clientes_ativos_{$_SESSION['chave']}";

if (!$clientes = apcu_fetch($cacheKey)) {
    $clientes = $qb->select('clientes', ['*'], 'situacao = ?', ['A']);
    apcu_store($cacheKey, $clientes, 300); // 5 minutos
}
```

## Migrando Código Legacy

### De mysqli para QueryBuilder

```php
// ❌ LEGACY mysqli
$rs = $mysqli->query("SELECT * FROM clientes WHERE situacao = 'A'");
while ($row = $rs->fetch_assoc()) {
    echo $row['nome_rsocial'];
}
$total = $rs->num_rows;

// ✅ QueryBuilder
$resultado = $qb->select('clientes', ['*'], 'situacao = ?', ['A']);
foreach ($resultado as $row) {
    echo $row['nome_rsocial'];
}
$total = count($resultado);
```

**Diferenças importantes:**
- SELECT retorna `array`, não `mysqli_result`
- Use `count($resultado)` em vez de `$rs->num_rows`
- Acesse linhas via `$resultado[0]` em vez de `fetch_assoc()`
- INSERT retorna `int` (ID) em vez de `bool`

Veja [querybuilder.md](./querybuilder.md) para exemplos completos de migração.

## Checklist de Segurança

Antes de fazer deploy de uma feature, verifique:

- [ ] Todas as queries usam QueryBuilder com prepared statements
- [ ] Filtro de `chave` está ativo para dados de tenant
- [ ] Output em HTML está escapado (`htmlspecialchars`)
- [ ] File uploads validam extensão e tamanho
- [ ] Credenciais não estão hardcoded
- [ ] Variáveis de ambiente não estão committadas
- [ ] Transações são usadas para operações multi-tabela
- [ ] Erros são logged mas não expostos ao usuário
- [ ] Autenticação é verificada em rotas protegidas
- [ ] Autorização verifica permissões específicas
- [ ] Datas usam `DateHelper`/helpers globais; nao ha `date()`, `time()`, `new DateTime()`, `new Date()` ou `NOW()/CURDATE()` novos fora das excecoes de `docs/date.md`

## Helper aviso() - Instruções de Campo

Use `aviso()` para toda instrução, explicação ou aviso associado a um campo.

**REGRA:** Nunca coloque textos auxiliares abaixo de `input`, `select` ou `textarea`, seja com `<p>`, `<small>` ou qualquer outro elemento. O texto deve ficar no popover gerado por `aviso()`, junto ao `<label>`. Mensagens de validação, descrições de seções e informações dinâmicas que não sejam auxiliares do campo não fazem parte dessa regra.

**IMPORTANTE:** Em templates Blade, use a sintaxe `{!! !!}` (raw output, sem escape de HTML). Em views PHP, use `<?= aviso(...) ?>`.

### Uso:

```php
<!-- Com tradução (para textos que precisam de i18n) -->
<label>Nome do Campo {!! aviso(t('modules.modulo.hints.nome_campo')) !!}</label>

<!-- Com texto direto -->
<label>Nome do Campo {!! aviso('Texto explicativo aqui') !!}</label>

<!-- Em view PHP -->
<label class="form-label-group">
    <?= t('modules.modulo.fields.nome_campo') ?>
    <?= aviso(t('modules.modulo.hints.nome_campo')) ?>
</label>
```

### Não fazer:

```php
<label for="campo">Nome do Campo</label>
<input id="campo" name="campo">
<p>Texto explicativo ou aviso sobre o campo.</p>
```

### Quando usar:

- Campos com cálculos automáticos
- Campos com regras de negócio específicas
- Campos que podem gerar dúvidas do usuário
- Campos que precisam exibir instruções, explicações ou avisos

### Estrutura de tradução:

No arquivo de idioma do módulo (`app/Lang/pt_BR/modules/[modulo].php`):

```php
'hints' => [
    'nome_campo' => 'Texto explicativo aqui. Suporta <b>HTML</b>.',
],
```

## Documentação Relacionada

- **[API - Requisições JavaScript](./api.md)** - Helper para requisições HTTP
- **[FilialHelper](./filial-helper.md)** - Filtros de acesso por filiais
- **[QueryBuilder](./querybuilder.md)** - Referência completa de uso
- **[Multi-tenancy](./multi-tenancy.md)** - Isolamento de dados
- **[Architecture](./architecture.md)** - Estrutura do sistema
- **[Database](./database.md)** - Padrões de schema
