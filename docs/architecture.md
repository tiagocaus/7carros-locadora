# Arquitetura do Sistema

## Visão Geral

7Carros Locadora utiliza uma **arquitetura MVC-like customizada** com componentes adicionais para serviços, middleware e camada de abstração de dados.

## Estrutura de Diretórios

```
locadora.7carros.com/
├── app/
│   ├── Classes/              # Utilitários e classes auxiliares
│   ├── Config/               # Constantes e configurações
│   ├── Controllers/          # Controladores HTTP
│   ├── Core/                 # Framework core
│   ├── Crons/                # Tarefas agendadas
│   ├── Database/
│   │   ├── migrations/       # Migrações de schema
│   │   └── seeds/            # Arquivos SQL de dados iniciais
│   ├── Helpers/              # Funções auxiliares
│   ├── Middleware/           # Pipeline de processamento
│   ├── Models/               # Camada de dados
│   ├── Routers/              # Definições de rotas
│   ├── Services/             # Lógica de negócio
│   └── Views/                # Templates HTML
├── public/                   # Web root
│   ├── index.php             # Entry point
│   └── assets/               # CSS, JS, imagens
├── storage/                  # Arquivos gerados
│   ├── cache/                # Cache da aplicação
│   ├── logs/                 # Logs do sistema
│   ├── temp/                 # Arquivos temporários
│   └── uploads/{chave}/      # Uploads de arquivos por tenant (via FileHelper)
├── tests/                    # Test suite
├── vendor/                   # Dependências Composer
├── .env.development          # Config dev (não commitado)
├── .env.production           # Config prod (não commitado)
├── .env.example              # Template de configuração
├── composer.json             # Dependências
├── migrate.php               # Script de migrations
└── cron.php                  # Executor de cron jobs
```

## Camadas da Aplicação

### 1. Core (`app/Core/`)

Componentes fundamentais do framework:

- **Router** - Sistema de roteamento de URLs
- **Request** - Wrapper para requisições HTTP
- **Response** - Gerenciamento de respostas
- **Database** - Gerenciamento de conexões
- **Session** - Gerenciamento de sessões

### 2. Controllers (`app/Controllers/`)

Handlers de requisições HTTP e lógica de apresentação:

```php
namespace App\Controllers;

class ClienteController
{
    public function index()
    {
        // Lógica de listagem de clientes
    }

    public function store()
    {
        // Lógica de criação de cliente
    }
}
```

**Responsabilidades:**
- Receber e validar input do usuário
- Chamar Services/Models para processar dados
- Retornar Views ou JSON responses

#### Estrutura de APIs (Separation of Concerns)

**Regra**: Cada entidade deve ter sua própria API. Não misture endpoints de entidades diferentes no mesmo controller.

| Entidade | Controller | Endpoint Base |
|----------|------------|---------------|
| Clientes | ClientesController | `/api/clientes` |
| Fornecedores | FornecedoresController | `/api/fornecedores` |
| Oficinas | OficinasController | `/api/oficinas` |
| Veículos | VeiculosController | `/api/veiculos` |
| Grupos | GruposController | `/api/grupos` |
| Estoque | EstoqueController | `/api/estoque` |
| Contratos | ContratosController | `/api/contratos` |
| Financeiro | FinanceiroController | `/api/financeiro` |
| Matrizes/Filiais | MatrizFilialController | `/api/matrizes-filiais` |
| Formas de Pagamento | FormasPagamentoController | `/api/formas-pagamento` |
| Contas Bancárias | ContasBancariasController | `/api/contas-bancarias` |

**Correto:**
```
/api/clientes/buscar       → ClientesController::buscar()
/api/oficinas/buscar       → OficinasController::buscar()
/api/fornecedores/buscar   → FornecedoresController::fornecedoresSelect()
```

**Incorreto:**
```
/api/financeiro/clientes   → FinanceiroController (violação SoC)
/api/fornecedores/oficinas → FornecedoresController (violação SoC)
```

### 3. Models (`app/Models/`)

Camada de acesso a dados usando QueryBuilder. Todos os Models herdam da classe base `Model` que fornece conexão mysqli Singleton.

**Classe Base (`app/Models/Model.php`):**
```php
abstract class Model
{
    protected QueryBuilder $qb;  // QueryBuilder disponível para subclasses

    protected function getMysqli(): mysqli;        // Acesso direto para transações
    public static function closeConnection(): void; // Para CLI/cron
}
```

**Exemplo de Model:**
```php
namespace App\Models;

class Cliente extends Model
{
    public function buscarPorId(int $id): ?array
    {
        $result = $this->qb->select('clientes', ['*'], 'id = ?', [$id]);
        return $result[0] ?? null;
    }

    public function criar(array $dados): int
    {
        return $this->qb->insert('clientes', $dados);
    }
}
```

**Model com Transações:**
```php
class ContatoTelefone extends Model
{
    public function salvar(int $id, array $telefones): bool
    {
        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            // Operações de banco...
            $mysqli->commit();
            return true;
        } catch (\Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
    }
}
```

**Model com Auditoria (trait Auditable):**
```php
class MatrizFilial extends Model
{
    use Auditable;

    protected function getEntidadeAuditoria(): string
    {
        return 'a matriz/filial';
    }
}
```

**Responsabilidades:**
- Queries de banco de dados via QueryBuilder (`$this->qb`)
- Transações via `$this->getMysqli()`
- Validação de dados em nível de model
- Lógica de negócio relacionada a entidades

### 4. Services (`app/Services/`)

Lógica de negócio complexa separada de Controllers:

```php
namespace App\Services;

class ReservaService
{
    public function criarReserva(array $dados): array
    {
        // Lógica complexa de criação de reserva
        // - Validar disponibilidade do veículo
        // - Calcular valores
        // - Gerar contrato
        // - Enviar notificações
    }
}
```

**Responsabilidades:**
- Orquestrar múltiplos Models
- Implementar regras de negócio complexas
- Gerenciar transações
- Integrar com serviços externos

### 5. Views (`app/Views/`)

Templates HTML com PHP embarcado:

```php
<!-- app/Views/clientes/index.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Clientes</title>
</head>
<body>
    <h1>Lista de Clientes</h1>
    <?php foreach ($clientes as $cliente): ?>
        <div><?= htmlspecialchars($cliente['nome_rsocial']) ?></div>
    <?php endforeach; ?>
</body>
</html>
```

### 6. Classes (`app/Classes/`)

Utilitários e classes auxiliares customizadas:

- **QueryBuilder** - Abstração de banco de dados
- Helpers customizados
- Bibliotecas internas

### 7. Middleware (`app/Middleware/`)

Pipeline de processamento de requisições:

```php
namespace App\Middleware;

class AuthMiddleware
{
    public function handle($request, $next)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        return $next($request);
    }
}
```

**Casos de uso:**
- Autenticação (`AuthMiddleware`)
- Autorização (`PermissionMiddleware`)
- Proteção CSRF em APIs (`ApiCsrfMiddleware`) - Veja [api.md](./api.md)
- Rate limiting (`RateLimitMiddleware`)
- Logging
- CORS

### 8. Routers (`app/Routers/`)

Definições de rotas da aplicação:

```php
// app/Routers/web.php
$router->get('/clientes', [ClienteController::class, 'index']);
$router->post('/clientes', [ClienteController::class, 'store']);
$router->get('/clientes/{id}', [ClienteController::class, 'show']);
```

### 9. Config (`app/Config/`)

Constantes e configurações da aplicação:

- **Planos.php** - Definição dos planos de assinatura
- **Database.php** - Configurações de banco
- Constantes globais

### 10. Database/migrations (`app/Database/migrations/`)

Scripts de migração de schema usando a API fluente:

```php
// app/Database/migrations/00001_create_clientes_table.php
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('clientes', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->string('nome_rsocial', 255);
            $table->timestamps();
            $table->index('chave');
        });
    }

    public function down(): void
    {
        $this->drop('clientes');
    }
};
```

Veja **[migrations.md](./migrations.md)** para documentação completa.

### 11. Helpers (`app/Helpers/`)

Funções auxiliares globais disponíveis em PHP e JavaScript.

**Arquivos PHP:**
- `helpers.php` - Funções globais (`str_limit`, `currency_format`, `format_date`, etc.)
- `CurrencyHelper.php` - Classe de formatação de moeda
- `DateHelper.php` - Classe de formatação de data
- `FileHelper.php` - Manipulação de arquivos
- `FilialHelper.php` - Filtros de filial

**Arquivos JavaScript:**
- `public/assets/js/components.js` - `Str.limit()`, `Km.format()`, `HelpHint`
- `public/assets/js/currency.js` - `Currency.format()`, `Currency.parse()`
- `public/assets/js/date.js` - `DateHelper.format()`, `DateHelper.parse()`
- `public/assets/js/api.js` - `API.get()`, `API.post()`

Veja **[helpers.md](./helpers.md)** para documentação completa.

### 12. Crons (`app/Crons/`)

Tarefas agendadas e background jobs:

```php
namespace App\Crons;

class EnviarLembretesReserva
{
    public function execute()
    {
        // Enviar lembretes de reservas próximas
    }
}
```

## Autoloading PSR-4

O projeto utiliza autoloading PSR-4 via Composer:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

**Mapeamento de namespaces:**

| Namespace | Diretório | Exemplo |
|-----------|-----------|---------|
| `App\Controllers\` | `app/Controllers/` | `App\Controllers\ClienteController` |
| `App\Models\` | `app/Models/` | `App\Models\Cliente` |
| `App\Services\` | `app/Services/` | `App\Services\ReservaService` |
| `App\Classes\` | `app/Classes/` | `App\Classes\QueryBuilder` |

**Exemplo de uso:**

```php
<?php
// Não precisa de require/include!
use App\Models\Cliente;

// Models já têm conexão Singleton - sem necessidade de injeção
$cliente = new Cliente();
$dados = $cliente->buscarPorId(123);
```

**Após adicionar novas classes:**

```bash
composer dump-autoload
```

## Padrões de Design

### Repository Pattern (via Models)

Models atuam como repositories para acesso a dados:

```php
$clienteModel = new Cliente();
$cliente = $clienteModel->buscarPorId(123);
```

### Service Layer Pattern

Lógica de negócio complexa isolada em Services:

```php
$reservaService = new ReservaService();
$reserva = $reservaService->criarReserva($dados);
```

### Dependency Injection

Injetar dependências via construtor ou instanciar diretamente:

```php
class ClienteController
{
    private Cliente $clienteModel;

    public function __construct()
    {
        // Models não precisam de injeção - têm conexão Singleton
        $this->clienteModel = new Cliente();
    }
}
```

## Fluxo de Requisição

```
1. Browser → public/index.php
2. index.php → Router
3. Router → Middleware Pipeline
4. Middleware → Controller
5. Controller → Service/Model
6. Model → QueryBuilder → Database
7. Database → QueryBuilder → Model
8. Model → Controller
9. Controller → View
10. View → Browser
```

## Convenções de Nomenclatura

### Classes
- **PascalCase**: `ClienteController`, `ReservaService`
- Sufixos: `Controller`, `Service`, `Model`, `Middleware`

### Métodos
- **camelCase**: `findById()`, `criarReserva()`
- Verbos descritivos: `get`, `find`, `create`, `update`, `delete`

### Arquivos
- **PascalCase** para classes: `ClienteController.php`
- **snake_case** para views: `clientes_index.php`
- **kebab-case** para migrations: `2024-01-15-create-clientes-table.php`

### Tabelas de Banco
- **snake_case**: `clientes`, `reservas`, `veiculos`
- Plural para tabelas de entidades
- Sempre incluir coluna `chave` para multi-tenancy

## Entry Point

**public/index.php** é o único arquivo acessível via web:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

// Carregar configurações
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Inicializar aplicação
$app = new App\Core\Application();
$app->run();
```

## Documentação Relacionada

- **[QueryBuilder](./querybuilder.md)** - Camada de abstração de banco
- **[Multi-tenancy](./multi-tenancy.md)** - Isolamento de tenants
- **[Best Practices](./best-practices.md)** - Guidelines de desenvolvimento
- **[Database](./database.md)** - Padrões de schema
