# FilialHelper - Filtros de Acesso por Filiais

Helper para filtrar queries por filiais permitidas do funcionário logado, possibilitando controle de acesso multi-filial.

## Visão Geral

O sistema possui duas camadas de isolamento de dados:
1. **Multi-tenancy via `chave`** - Isolamento automático entre empresas/tenants
2. **Filtro de Filiais** - Restrição de acesso por filiais dentro do mesmo tenant

O FilialHelper gerencia a segunda camada, permitindo que funcionários acessem apenas registros das filiais às quais estão vinculados.

## Como Funciona

1. No login, o sistema carrega as filiais permitidas do funcionário na sessão (`filiais_permitidas`)
2. Ao fazer queries, o FilialHelper adiciona cláusulas WHERE para filtrar por essas filiais
3. Se o array estiver vazio, o funcionário tem acesso total (admin/proprietário)

## Métodos Disponíveis

### `whereFiliais()`

Retorna cláusula WHERE para filtrar por `id_matriz_filial`.

```php
public static function whereFiliais(
    string $coluna = 'id_matriz_filial',
    ?string $alias = null
): array
```

**Parâmetros:**
- `$coluna` - Nome da coluna de filial (padrão: `'id_matriz_filial'`)
- `$alias` - Alias da tabela (ex: `'c'` para `'clientes c'`)

**Retorno:** `[whereClause, params]`

**Exemplo:**
```php
use App\Helpers\FilialHelper;

// Uso básico
[$where, $params] = FilialHelper::whereFiliais('id_matriz_filial');
// Resultado: ['id_matriz_filial IN (?,?)', [1, 2]]

// Com alias de tabela
[$where, $params] = FilialHelper::whereFiliais('id_matriz_filial', 'c');
// Resultado: ['c.id_matriz_filial IN (?,?)', [1, 2]]

// Usuário sem restrição (admin)
// Resultado: ['1=1', []]
```

---

### `whereLocacoes()`

Retorna cláusula WHERE especial para locações, filtrando por filial de **retirada OU devolução**.

```php
public static function whereLocacoes(?string $alias = null): array
```

**Parâmetros:**
- `$alias` - Alias da tabela

**Retorno:** `[whereClause, params]`

**Exemplo:**
```php
[$where, $params] = FilialHelper::whereLocacoes();
// Resultado: ['(id_matriz_filial_retirada IN (?,?) OR id_matriz_filial_devolucao IN (?,?))', [1, 2, 1, 2]]

[$where, $params] = FilialHelper::whereLocacoes('l');
// Resultado: ['(l.id_matriz_filial_retirada IN (?,?) OR l.id_matriz_filial_devolucao IN (?,?))', [1, 2, 1, 2]]
```

---

### `whereContratos()`

Retorna cláusula WHERE para contratos, filtrando por `id_matriz_filial_retirada`.

```php
public static function whereContratos(?string $alias = null): array
```

**Parâmetros:**
- `$alias` - Alias da tabela

**Retorno:** `[whereClause, params]`

---

### `temAcessoFilial()`

Verifica se o usuário logado tem acesso a uma filial específica.

```php
public static function temAcessoFilial(?int $filialId): bool
```

**Parâmetros:**
- `$filialId` - ID da filial a verificar

**Retorno:** `true` se tem acesso, `false` caso contrário

**Exemplo:**
```php
// Verificar antes de editar/excluir
$cliente = $clienteModel->buscarPorId($id);

if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
    Response::json(['erro' => 'Acesso negado'], 403);
    return;
}
```

---

### `getFiliaisPermitidas()`

Retorna array de IDs das filiais permitidas do usuário logado.

```php
public static function getFiliaisPermitidas(): array
```

**Retorno:** Array de IDs (ex: `[1, 2, 5]`) ou array vazio se acesso total

---

### `temRestricaoFiliais()`

Verifica se o usuário tem restrição de filiais.

```php
public static function temRestricaoFiliais(): bool
```

**Retorno:** `true` se há restrição, `false` se acesso total

---

## Padrão de Uso em Controllers

### Listagem (index)

```php
use App\Helpers\FilialHelper;

public function index(Request $request): void
{
    // Obter filtro de filiais
    [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

    // Passar para o model
    $items = $this->model->listarPaginado($page, $perPage, $search, $filialWhere, $filialParams);
    $total = $this->model->contar($search, $filialWhere, $filialParams);
}
```

### Visualização/Edição/Exclusão (show/update/destroy)

```php
public function show(Request $request, int $id): void
{
    $registro = $this->model->buscarPorId($id);

    if (!$registro) {
        Response::json(['erro' => 'Não encontrado'], 404);
        return;
    }

    // Verificar acesso à filial
    if (!FilialHelper::temAcessoFilial($registro['id_matriz_filial'] ?? null)) {
        Response::json(['erro' => 'Acesso negado'], 403);
        return;
    }

    Response::json(['data' => $registro]);
}
```

---

## Padrão de Uso em Models

### Modificar `listarPaginado()` para aceitar filtro extra

```php
public function listarPaginado(
    int $page = 1,
    int $perPage = 10,
    ?string $search = null,
    ?string $extraWhere = null,
    array $extraParams = []
): array {
    $conditions = [];
    $params = [];

    // Busca
    if (!empty($search)) {
        $searchTerm = "%{$search}%";
        $conditions[] = '(nome LIKE ? OR email LIKE ?)';
        $params = array_merge($params, [$searchTerm, $searchTerm]);
    }

    // Filtro extra (filiais)
    if (!empty($extraWhere) && $extraWhere !== '1=1') {
        $conditions[] = $extraWhere;
        $params = array_merge($params, $extraParams);
    }

    $where = !empty($conditions) ? implode(' AND ', $conditions) : null;

    return $this->qb->select('tabela', ['*'], $where, $params, 'nome ASC', $perPage, $offset);
}
```

---

## Tabela de Referência por Módulo

| Módulo | Método FilialHelper | Coluna(s) |
|--------|---------------------|-----------|
| Clientes | `whereFiliais()` | `id_matriz_filial` |
| Veículos | `whereFiliais()` | `id_matriz_filial` |
| Financeiro | `whereFiliais()` | `id_matriz_filial` |
| Manutenções | `whereFiliais()` | `id_matriz_filial` |
| Estoque | `whereFiliais()` | `id_matriz_filial` |
| Matrizes/Filiais | `whereFiliais('id')` | `id` |
| Contratos | `whereContratos()` | `id_matriz_filial_retirada` |
| Locações | `whereLocacoes()` | `id_matriz_filial_retirada` + `id_matriz_filial_devolucao` |

---

## Relacionamento com Auth

O FilialHelper usa internamente `Auth::filiaisPermitidas()` para obter as filiais do usuário logado.

### Métodos relacionados no Auth:

- `Auth::filiaisPermitidas()` - Retorna array de filiais permitidas
- `Auth::refreshFiliais()` - Recarrega filiais após alteração

### Carregamento automático:

As filiais são carregadas automaticamente no login via `Auth::login()` e armazenadas em `$_SESSION['filiais_permitidas']`.

---

## Permissão Especial: Listar Todas

A permissão `matrizes_filiais.listar_todas` permite que usuários vejam todas as matrizes/filiais do tenant, ignorando o filtro de filiais vinculadas.

### Uso em Controllers (Matrizes/Filiais):

```php
// Verificar se tem permissão de listar todas as matrizes/filiais
if (Auth::can('matrizes_filiais.listar_todas')) {
    $filialWhere = null;
    $filialParams = [];
} else {
    // Obter filtro de filiais permitidas
    [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id');
}

$model = new MatrizFilial();
$registros = $model->buscar($termo, $filialWhere, $filialParams);
```

---

## Troubleshooting

### Usuário vê todos os registros mesmo com restrição

**Causa:** Filiais não foram carregadas no login.

**Solução:** Verificar se `Auth::filiaisPermitidas()` retorna o array correto.

### Usuário não vê nenhum registro

**Causa:** Nenhuma filial vinculada ao funcionário.

**Solução:** Configurar filiais permitidas no cadastro do funcionário.

### Erro "id_matriz_filial IN ()"

**Causa:** Array de filiais vazio sendo usado em query.

**Solução:** O FilialHelper retorna `'1=1'` para arrays vazios, então não deve haver query com `IN ()`. Verificar implementação.

---

## Links Relacionados

- [Multi-tenancy](multi-tenancy.md) - Isolamento por tenant (chave)
- [QueryBuilder](querybuilder.md) - Camada de abstração de queries
- [Best Practices](best-practices.md) - Boas práticas de segurança
