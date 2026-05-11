# Documentação QueryBuilder - API Fluente

## Índice
1. [Introdução](#introdução)
2. [Uso Básico](#uso-básico)
3. [Operações SELECT](#operações-select)
4. [JOINs](#joins)
5. [Condições WHERE](#condições-where)
6. [Ordenação e Paginação](#ordenação-e-paginação)
7. [Agregações](#agregações)
8. [INSERT, UPDATE, DELETE](#insert-update-delete)
9. [Transações](#transações)
10. [Multi-tenancy](#multi-tenancy)
11. [Exemplos Completos](#exemplos-completos)

---

## Introdução

O **QueryBuilder** fornece uma interface fluente e segura para construir queries SQL complexas no projeto locadora.7carros.com.

### Benefícios

- **Segurança:** Proteção automática contra SQL Injection via prepared statements
- **Legibilidade:** API encadeada/fluente com métodos descritivos
- **Multi-tenancy:** Filtragem automática por `chave` do tenant
- **Flexibilidade:** Suporte a JOINs, subqueries, UNION, GROUP BY, HAVING
- **Consistência:** Todos os métodos de consulta retornam arrays associativos

**Localização:** `/app/Classes/QueryBuilder.php`

---

## Uso Básico

### Através de Models (recomendado)

```php
// Todos os Models herdam de Model, que já instancia o QueryBuilder
class MeuModel extends Model
{
    public function buscar(): array
    {
        return $this->qb
            ->table('minha_tabela')
            ->where('status', '=', 'A')
            ->get();
    }
}
```

### Instanciação direta

```php
use App\Classes\QueryBuilder;

$qb = new QueryBuilder($mysqli);
$resultados = $qb->table('clientes')->get();
```

---

## Operações SELECT

### Seleção básica

```php
// Selecionar todas as colunas
$clientes = $this->qb
    ->table('clientes')
    ->get();

// Selecionar colunas específicas
$clientes = $this->qb
    ->table('clientes')
    ->select(['id', 'nome_rsocial', 'cpf_cnpj'])
    ->get();
```

### Alias de tabela

```php
$resultado = $this->qb
    ->table('clientes', 'c')
    ->select(['c.id', 'c.nome_rsocial'])
    ->get();
```

### Select com expressões

```php
$resultado = $this->qb
    ->table('financeiro', 'f')
    ->select([
        'f.*',
        'c.nome_rsocial AS cliente_nome',
        'COALESCE(f.valor_pago, 0) AS valor_pago_safe'
    ])
    ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
    ->get();
```

### Select com subquery

```php
// Subquery para contar itens relacionados
$resultado = $this->qb
    ->table('financeiro', 'f')
    ->select(['f.*'])
    ->selectSubquery(function ($q) {
        $q->table('financeiro_itens', 'fi')
          ->selectRaw('COUNT(*)')
          ->whereRaw('fi.id_financeiro = f.id');
    }, 'total_itens')
    ->get();
```

### Obter primeiro resultado

```php
// Retorna array ou null
$cliente = $this->qb
    ->table('clientes')
    ->where('id', '=', 123)
    ->first();
```

### Obter valor único

```php
// Retorna valor escalar
$nome = $this->qb
    ->table('clientes')
    ->where('id', '=', 123)
    ->value('nome_rsocial');
```

### Obter lista de valores (pluck)

```php
// Retorna array simples de valores
$ids = $this->qb
    ->table('clientes')
    ->where('situacao', '=', 'A')
    ->pluck('id');
// Resultado: [1, 2, 3, 4, ...]
```

---

## JOINs

### LEFT JOIN

```php
$resultado = $this->qb
    ->table('financeiro', 'f')
    ->select(['f.*', 'c.nome_rsocial AS cliente_nome'])
    ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
    ->get();
```

### INNER JOIN

```php
$resultado = $this->qb
    ->table('temporadas_grupos', 'tg')
    ->select(['tg.*', 'g.nome AS grupo_nome'])
    ->innerJoin('grupos', 'g', 'g.id', '=', 'tg.id_grupo')
    ->get();
```

### RIGHT JOIN

```php
$resultado = $this->qb
    ->table('veiculos', 'v')
    ->rightJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
    ->get();
```

### Múltiplos JOINs

```php
$resultado = $this->qb
    ->table('financeiro', 'f')
    ->select([
        'f.*',
        'c.nome_rsocial AS cliente_nome',
        'fo.nome_rsocial AS fornecedor_nome',
        'pc.descricao AS plano_conta_descricao',
        'fp.nome AS forma_pagamento_descricao'
    ])
    ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
    ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
    ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
    ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
    ->get();
```

### JOIN com condição complexa (leftJoinRaw)

```php
$resultado = $this->qb
    ->table('logs', 'l')
    ->leftJoinRaw('funcionarios', 'f', 'l.id_funcionario = f.id AND l.chave = f.chave')
    ->get();
```

---

## Condições WHERE

### Comparações básicas

```php
$resultado = $this->qb
    ->table('clientes')
    ->where('situacao', '=', 'A')
    ->where('cidade', '=', 'São Paulo')
    ->get();
```

### Operadores de comparação

```php
// Igual
->where('status', '=', 'A')

// Diferente
->where('status', '!=', 'I')

// Maior/Menor
->where('valor', '>', 100)
->where('valor', '>=', 100)
->where('valor', '<', 500)
->where('valor', '<=', 500)

// LIKE
->where('nome', 'LIKE', '%João%')
```

### WHERE OR

```php
$resultado = $this->qb
    ->table('clientes')
    ->where('cidade', '=', 'São Paulo')
    ->orWhere('cidade', '=', 'Rio de Janeiro')
    ->get();
```

### WHERE IN / NOT IN

```php
$resultado = $this->qb
    ->table('clientes')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

$resultado = $this->qb
    ->table('clientes')
    ->whereNotIn('situacao', ['I', 'X'])
    ->get();
```

### WHERE BETWEEN

```php
$resultado = $this->qb
    ->table('financeiro')
    ->whereBetween('data_venci', '2025-01-01', '2025-12-31')
    ->get();
```

### WHERE NULL / NOT NULL

```php
$resultado = $this->qb
    ->table('clientes')
    ->whereNull('cnh_validade')
    ->get();

$resultado = $this->qb
    ->table('financeiro')
    ->whereNotNull('data_pagamento')
    ->get();
```

### WHERE aninhado (nested)

Usado para agrupar condições com parênteses:

```php
// WHERE situacao = 'A' AND (nome LIKE '%João%' OR email LIKE '%joao%')
$resultado = $this->qb
    ->table('clientes')
    ->where('situacao', '=', 'A')
    ->whereNested(function ($q) use ($searchTerm) {
        $q->where('nome', 'LIKE', $searchTerm)
          ->orWhere('email', 'LIKE', $searchTerm);
    })
    ->get();
```

### WHERE aninhado com OR

```php
// Incluir feriados nacionais OU (estaduais do estado) OU (municipais da cidade)
$query->whereNested(function ($q) use ($estado, $cidade) {
    $q->where('tipo', '=', 'nacional');

    if ($estado) {
        $q->orWhereNested(function ($sub) use ($estado) {
            $sub->where('tipo', '=', 'estadual')
                ->where('estado', '=', $estado);
        });
    }

    if ($estado && $cidade) {
        $q->orWhereNested(function ($sub) use ($estado, $cidade) {
            $sub->where('tipo', '=', 'municipal')
                ->where('estado', '=', $estado)
                ->where('cidade', '=', $cidade);
        });
    }
});
```

### WHERE raw (SQL direto)

Para condições complexas que não podem ser expressas com os métodos padrão:

```php
$resultado = $this->qb
    ->table('temporadas')
    ->whereRaw('(mes_inicio < ? OR (mes_inicio = ? AND dia_inicio <= ?))', [$mes, $mes, $dia])
    ->get();
```

---

## Ordenação e Paginação

### Ordenação simples

```php
$resultado = $this->qb
    ->table('clientes')
    ->orderBy('nome_rsocial', 'ASC')
    ->get();

// Ordem descendente
$resultado = $this->qb
    ->table('financeiro')
    ->orderByDesc('data_venci')
    ->get();
```

> **⚠️ Segurança — validação de `orderBy()`**
>
> `orderBy($column, $direction)` **valida** ambos os argumentos (ORDER BY não aceita prepared statement, então a defesa é uma allowlist):
> - `$column` deve casar `^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$` (identificador simples ou `alias.coluna`, sem backticks/espaços).
> - `$direction` deve ser exatamente `ASC` ou `DESC` (case-insensitive).
>
> Valor inválido → `InvalidArgumentException`. **Nunca** passe `$_GET['sort']` direto; use whitelist no Controller ou troque por `orderByRaw(...)` se precisar de expressão (ex.: `CASE WHEN ...`).

### Múltiplas ordenações

```php
$resultado = $this->qb
    ->table('feriados')
    ->orderBy('mes', 'ASC')
    ->orderBy('dia', 'ASC')
    ->get();
```

### Paginação

```php
// Página 1, 10 registros por página
$resultado = $this->qb
    ->table('clientes')
    ->orderBy('nome_rsocial', 'ASC')
    ->paginate(1, 10)
    ->get();

// Página 3, 20 registros por página
$resultado = $this->qb
    ->table('clientes')
    ->paginate(3, 20)
    ->get();
```

### Limit e Offset manuais

```php
$resultado = $this->qb
    ->table('clientes')
    ->limit(10)
    ->offset(20)
    ->get();
```

---

## Agregações

### COUNT

```php
$total = $this->qb
    ->table('clientes')
    ->where('situacao', '=', 'A')
    ->count();
```

### SUM

```php
$totalValor = $this->qb
    ->table('financeiro_itens')
    ->where('id_financeiro', '=', $idFinanceiro)
    ->sum('valor');
```

### AVG, MIN, MAX

```php
$mediaValor = $this->qb->table('financeiro')->avg('valor');
$menorValor = $this->qb->table('financeiro')->min('valor');
$maiorValor = $this->qb->table('financeiro')->max('valor');
```

### EXISTS

```php
$existe = $this->qb
    ->table('clientes')
    ->where('cpf_cnpj', '=', '12345678900')
    ->exists();
// Retorna: true ou false
```

### GROUP BY e HAVING

```php
$resultado = $this->qb
    ->table('financeiro')
    ->selectRaw('id_cliente, COUNT(*) as total, SUM(valor) as soma')
    ->groupBy('id_cliente')
    ->having('total', '>', 5)
    ->get();
```

---

## INSERT, UPDATE, DELETE

### INSERT

```php
// Retorna o ID inserido
$clienteId = $this->qb
    ->table('clientes')
    ->insert([
        'chave' => $chave,
        'nome_rsocial' => 'João Silva',
        'cpf_cnpj' => '12345678900',
        'email' => 'joao@email.com'
    ]);
```

### UPDATE

```php
// Retorna número de linhas afetadas
$linhas = $this->qb
    ->table('clientes')
    ->where('id', '=', 123)
    ->update([
        'nome_rsocial' => 'João da Silva',
        'email' => 'joao.silva@email.com'
    ]);
```

### DELETE

```php
// Retorna número de linhas afetadas
$linhas = $this->qb
    ->table('clientes')
    ->where('id', '=', 123)
    ->delete();
```

---

## Transações

```php
$this->qb->beginTransaction();

try {
    // Inserir cliente
    $clienteId = $this->qb
        ->table('clientes')
        ->insert(['nome_rsocial' => 'Maria Santos']);

    // Inserir financeiro vinculado
    $this->qb
        ->table('financeiro')
        ->insert([
            'id_cliente' => $clienteId,
            'valor' => 100.00
        ]);

    // Confirmar
    $this->qb->commit();

} catch (\Exception $e) {
    // Reverter
    $this->qb->rollback();
    throw $e;
}
```

---

## Multi-tenancy

### Filtro automático por chave (PADRÃO)

Por padrão, o QueryBuilder adiciona `WHERE chave = $_SESSION['chave']` em **TODAS** as queries. Este é o comportamento correto para **95% dos casos** - garante isolamento de dados entre tenants.

```php
// Exemplo CORRETO - usa filtro automático de chave
$clientes = $this->qb
    ->table('clientes')
    ->where('situacao', '=', 'A')
    ->get();
// Gera: SELECT * FROM clientes WHERE chave = 'ABC123' AND situacao = 'A'
```

**Com alias de tabela, o filtro qualifica automaticamente:**

```php
// table('veiculos', 'v') — alias 'v' aplicado
$veiculos = $this->qb
    ->table('veiculos', 'v')
    ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
    ->get();
// Gera: SELECT * FROM veiculos v LEFT JOIN grupos g ... WHERE v.chave = 'ABC123'
//                                                          ^^^^^^^^
//                             qualificado automaticamente pelo alias
```

> ⚠️ **Atenção:** sempre use `table('tabela', 'alias')` — **NUNCA** `table('tabela alias')` (alias embutido no nome). O QueryBuilder só detecta o alias quando passado no **segundo parâmetro**, e sem isso o filtro automático gera `WHERE chave = ?` sem prefixo, o que pode dar "ambiguous column" em queries com JOIN.

### ❌ Anti-padrão COMUM (não fazer)

```php
// ❌ ERRADO — redundante e viola regra do CLAUDE.md
return $this->qb
    ->table('clientes')
    ->withoutChave()
    ->where('chave', '=', $chave)
    ->get();

// ❌ PIOR ainda — withoutChave SEM where('chave') é bug de cross-tenant.
// Permite usuário do tenant A acessar registro do tenant B via ID:
return $this->qb
    ->table('clientes')
    ->withoutChave()
    ->where('id', '=', $id)   // ID vem da URL — cross-tenant aberto!
    ->first();
```

**Como corrigir:** simplesmente remover `withoutChave()` — o filtro automático cuida.

```php
// ✅ CORRETO
return $this->qb
    ->table('clientes')
    ->where('id', '=', $id)
    ->first();
// Gera: WHERE id = ? AND chave = $_SESSION['chave']
// → retorna null se o ID pertencer a outro tenant (isolamento OK)
```

**Parâmetro `$chave` na assinatura do método:** se existe só para construir o `where('chave', '=', $chave)` redundante, pode ser removido depois. Por compatibilidade, manter a assinatura e só apagar o uso interno.

### withGlobals() - Incluir registros globais

Para tabelas que têm registros globais (`chave = '0'`) junto com registros do tenant:

```php
// Buscar feriados do tenant + feriados globais do sistema
$feriados = $this->qb
    ->table('feriados')
    ->withGlobals()
    ->get();
// Gera: SELECT * FROM feriados WHERE (chave = 'ABC123' OR chave = '0')
```

**Quando usar:**
- `feriados` - nacionais (globais) + personalizados (tenant)
- `temporadas` - templates (globais) + customizadas (tenant)
- `roles` - sistema (globais) + customizadas (tenant)

### withoutChave() - Sem filtro (casos raros)

Desabilita completamente o filtro de chave. **NÃO é um atalho genérico** — só faz sentido nos casos abaixo, e usá-lo fora desses cenários é um **anti-padrão** que cria bugs de cross-tenant (ver seção anterior).

**Casos LEGÍTIMOS de uso:**

| Caso | Exemplos reais |
|------|----------------|
| **CRON jobs** que operam cross-tenant | `app/Crons/Jobs/ProcessMessageQueueJob.php`, `GerarEncargosFinanceiroJob.php` |
| **Autenticação** (antes de existir sessão) | `app/Core/Auth.php` (login, recuperação de senha) |
| **Migrations** (rodam sem sessão) | `app/Database/migrations/**` |
| **Provisioning de tenant** (o próprio tenant ainda não existe) | `app/Services/TenantProvisioningService.php` |
| **Webhooks externos** sem sessão do tenant | `app/Controllers/SerproWebhookController.php` |
| **Rotas públicas** (pagamento, assinatura, verificação) | `app/Controllers/PagamentoPublicoController.php`, `PublicWebsiteController.php` |
| **Templates globais do sistema** (`chave = '0'`) | `MessageTemplateService.php`, `PromissoriaTemplateService.php` |
| **Tabelas sem coluna `chave`** (segurança global, changelog) | `security_login_attempts`, `changelog` |

**Casos ILEGÍTIMOS (não usar):**

- ❌ CRUD normal de entidade do tenant (Cliente, Veículo, Fornecedor, Contrato, Locação, Financeiro, etc.)
- ❌ `withoutChave()->where('chave', '=', $chave)` — redundante, remova ambos
- ❌ `withoutChave()->where('id', '=', $id)` — bug de cross-tenant, permite acessar outro tenant

**Exemplo legítimo (CRON):**

```php
// app/Crons/Jobs/ProcessMessageQueueJob.php
$this->qb->withoutChave(); // CRON processa mensagens de TODOS os tenants
$mensagens = $this->qb->table('queue_messages')->get();
```

**Importante:** Tanto `withGlobals()` quanto `withoutChave()` são resetados após execução.

### withChave() - Reativar filtro de chave

Usado para reativar o filtro de `chave` em contextos onde ele foi desabilitado (como subqueries):

```php
// Subquery com filtro de chave explícito (raro)
->selectSubquery(function ($q) {
    $q->table('tabela')
      ->withChave()  // Reativa filtro automático
      ->selectRaw('COUNT(*)');
}, 'total')

// Especificar chave diferente
->selectSubquery(function ($q) use ($outraChave) {
    $q->table('tabela')
      ->withChave($outraChave)
      ->selectRaw('COUNT(*)');
}, 'total')
```

### Subqueries e Multi-tenancy

Subqueries criadas com `selectSubquery()` têm `useChave = false` por design. Isso porque:

1. **Relação implícita:** Se a subquery está relacionada com a query principal via FK (ex: `WHERE fi.id_financeiro = f.id`), e a query principal já filtra por `chave`, os dados já estão isolados implicitamente.

2. **IDs globais únicos:** Os IDs são auto-increment global, então um `id = 5` só existe em um tenant.

**Exemplo - NÃO precisa de filtro adicional:**
```php
// Query principal filtra grupos por chave
// Subquery relaciona veiculos via id_grupo - isolamento implícito
$this->qb
    ->table('grupos', 'g')  // <- chave aplicada aqui
    ->selectSubquery(function ($q) {
        $q->table('veiculos', 'v')
          ->selectRaw('COUNT(*)')
          ->whereRaw('v.id_grupo = g.id');  // <- relacionado via FK
    }, 'qtd_veiculos')
    ->get();
```

**Quando usar `withChave()` na subquery:**
- Quando a subquery NÃO está relacionada com a query principal via FK
- Quando precisa filtrar por um tenant específico diferente do atual

> **Nota técnica (implementação interna):**
> - O `useChave = false` é aplicado DEPOIS do callback, pois `table()` chama `reset()` que redefine `useChave = true`
> - Parâmetros de subqueries são armazenados em `$selectParams` (separado de `$whereParams`)
> - A ordem dos bindings é: `selectParams` → `whereParams` → `havingParams` (corresponde à ordem no SQL)

---

## Exemplos Completos

### Model Financeiro - Buscar por ID com JOINs

```php
public function buscarPorId(int $id): ?array
{
    // SEM withoutChave() - o filtro automático de chave é o correto aqui
    return $this->qb
        ->table('financeiro', 'f')
        ->select([
            'f.*',
            'c.nome_rsocial AS cliente_nome',
            'fo.nome_rsocial AS fornecedor_nome',
            'pc.descricao AS plano_conta_descricao',
            'fp.nome AS forma_pagamento_descricao',
            'mf.razao_social AS filial_nome'
        ])
        ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
        ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
        ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
        ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
        ->leftJoin('matrizes_filiais', 'mf', 'f.id_matriz_filial', '=', 'mf.id')
        ->where('f.id', '=', $id)
        ->first();
}
```

### Model Cliente - Listar paginado com busca

```php
public function listarPaginado(
    int $page = 1,
    int $perPage = 10,
    ?string $search = null
): array {
    $query = $this->qb
        ->table('clientes')
        ->select(['id', 'nome_rsocial', 'cpf_cnpj', 'email', 'tel_cel', 'situacao']);

    if (!empty($search)) {
        $searchTerm = "%{$search}%";
        $query->whereNested(function ($q) use ($searchTerm) {
            $q->where('nome_rsocial', 'LIKE', $searchTerm)
              ->orWhere('email', 'LIKE', $searchTerm)
              ->orWhere('cpf_cnpj', 'LIKE', $searchTerm)
              ->orWhere('tel_cel', 'LIKE', $searchTerm);
        });
    }

    return $query
        ->orderBy('nome_rsocial', 'ASC')
        ->paginate($page, $perPage)
        ->get();
}
```

### Model Feriado - Usando withGlobals()

```php
public function listarAplicaveis(?string $estado, ?string $cidade): array
{
    // withGlobals() inclui automaticamente tenant + globais (chave='0')
    $query = $this->qb
        ->table('feriados')
        ->select(['id', 'nome', 'mes', 'dia', 'tipo', 'estado', 'cidade'])
        ->withGlobals();  // WHERE (chave = SESSION OR chave = '0')

    // Filtrar por tipo de feriado aplicável
    $query->whereNested(function ($q) use ($estado, $cidade) {
        // Nacionais sempre aplicam
        $q->where('tipo', '=', 'nacional');

        // Estaduais do estado
        if ($estado) {
            $q->orWhereNested(function ($sub) use ($estado) {
                $sub->where('tipo', '=', 'estadual')
                    ->where('estado', '=', $estado);
            });
        }

        // Municipais da cidade
        if ($estado && $cidade) {
            $q->orWhereNested(function ($sub) use ($estado, $cidade) {
                $sub->where('tipo', '=', 'municipal')
                    ->where('estado', '=', $estado)
                    ->where('cidade', '=', $cidade);
            });
        }
    });

    return $query
        ->orderBy('mes', 'ASC')
        ->orderBy('dia', 'ASC')
        ->get();
}
```

---

## Referência de Métodos

| Categoria | Métodos |
|-----------|---------|
| **Tabela** | `table($table, $alias)` |
| **Select** | `select()`, `selectRaw()`, `selectSubquery()` |
| **JOINs** | `leftJoin()`, `rightJoin()`, `innerJoin()`, `leftJoinRaw()` |
| **WHERE** | `where()`, `orWhere()`, `whereIn()`, `whereNotIn()`, `whereBetween()`, `whereNull()`, `whereNotNull()`, `whereNested()`, `orWhereNested()`, `whereRaw()` |
| **Ordenação** | `orderBy()`, `orderByDesc()`, `orderByRaw()` |
| **Limite** | `limit()`, `offset()`, `paginate()` |
| **Agrupamento** | `groupBy()`, `having()`, `havingRaw()` |
| **Agregação** | `count()`, `sum()`, `avg()`, `min()`, `max()`, `exists()` |
| **Execução** | `get()`, `first()`, `value()`, `pluck()` |
| **CRUD** | `insert()`, `update()`, `delete()` |
| **Transações** | `beginTransaction()`, `commit()`, `rollback()` |
| **Multi-tenancy** | `withGlobals()`, `withoutChave()`, `withChave()` |
| **Debug** | `toSql()`, `getBindings()` |
