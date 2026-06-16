# Sistema de Roles e Permissoes

Sistema RBAC (Role-Based Access Control) multi-tenant para controle de acesso.

## Estrutura de Tabelas

### permissions (global)

Armazena todas as permissoes disponiveis no sistema. Tabela global, sem coluna `chave`.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT | ID unico |
| key | VARCHAR | Identificador unico (ex: `clientes.visualizar`) |
| name | VARCHAR | Nome legivel (ex: `Visualizar Clientes`) |
| description | TEXT | Descricao detalhada |
| module | VARCHAR | Modulo (ex: `clientes`, `financeiro`) |
| created_at | TIMESTAMP | Data de criacao |

**Formato de permissoes:** `{modulo}.{acao}`

Acoes padrao:
- `visualizar` - Listar/visualizar registros
- `criar` - Adicionar novos registros
- `editar` - Modificar registros existentes
- `excluir` - Remover registros

### funcionarios_roles (por tenant)

Armazena as roles do sistema com suporte a customizacao por tenant.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT | ID unico |
| chave | VARCHAR(45) | Tenant - `'0'` = role de sistema |
| name | VARCHAR | Nome da role (ex: `Gerente`) |
| description | TEXT | Descricao |
| is_system | TINYINT | Flag de role de sistema (nao editavel) |
| parent_id | INT | Referencia a role de sistema quando customizada |
| created_at | TIMESTAMP | Data de criacao |

**Importante:** Roles de sistema tem `chave = '0'`, nao `'system'`.

### funcionarios_role_permissions (pivot)

Tabela N:N que associa permissoes a roles.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT | ID unico |
| role_id | INT | FK para funcionarios_roles.id |
| permission_id | INT | FK para permissions.id |
| created_at | TIMESTAMP | Data de criacao |

Indice UNIQUE `(role_id, permission_id)` evita duplicatas.

## Fluxo de Atribuicao de Permissoes

```
1. CRIAR PERMISSAO (global, uma vez)
   INSERT INTO permissions (key, name, module)

2. CRIAR ROLE (por tenant ou sistema)
   INSERT INTO funcionarios_roles (chave, name)
   - chave = '0' para role de sistema
   - chave = 'TENANT123' para role customizada

3. ATRIBUIR PERMISSAO A ROLE
   INSERT INTO funcionarios_role_permissions (role_id, permission_id)

4. ATRIBUIR ROLE AO FUNCIONARIO
   UPDATE funcionarios SET id_role = ?
```

## Fluxo de Verificacao em Runtime

```php
// 1. Auth::can() verifica cache primeiro (TTL 1 hora)
// 2. Se nao em cache, executa JOIN de 4 tabelas
// 3. Retorna array de permission keys
// 4. Faz in_array($permission, $permissions)

if (!Auth::can('clientes.visualizar')) {
    Response::forbidden('Sem permissao');
}
```

**Query executada (simplificada):**
```sql
SELECT DISTINCT p.key
FROM funcionarios f
JOIN funcionarios_roles r ON f.id_role = r.id
JOIN funcionarios_role_permissions rp ON r.id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
WHERE f.id = ? AND f.chave = ?
```

## Roles de Sistema

Roles padrao do sistema (todas com `chave = '0'`):

| Role | Descricao |
|------|-----------|
| Proprietario | Acesso total a todas as funcionalidades |
| Gerente | Gerenciamento operacional completo |
| Coordenador Administrativo | Coordenacao administrativa |
| Assistente Administrativo | Suporte administrativo |
| Atendente | Atendimento ao cliente |

### Atendente

A role `Atendente` pode criar contratos e locacoes, operar devolucao/fechamento
e substituir/adicionar veiculos nesses fluxos. Para isso, deve possuir:
`contratos.editar`, `contratos.devolver`, `contratos.substituir`,
`locacoes.editar` e `locacoes.devolucao`.

Essa liberacao nao inclui exclusao, cancelamento de locacoes nem edicao especial
de valores/taxas.

**Filtragem correta em migrations:**
```php
// CORRETO - filtrar por name
$roles = $this->db()
    ->table('funcionarios_roles')
    ->select(['id'])
    ->where('name', '=', 'Proprietario')
    ->get();

// INCORRETO - chave = 'system' nao existe
// WHERE chave = 'system' -- ERRADO!
```

## Cache de Permissoes

- **TTL:** 1 hora por usuario
- **Namespace:** Por tenant (chave)
- **Chave:** `user_permissions:{userId}`

**Metodos de invalidacao:**
```php
Auth::invalidateUserPermissionsCache()  // Permissoes do usuario atual
Auth::invalidateUserCache()             // Todos dados do usuario
Auth::invalidateTenantCache()           // Todos dados do tenant
```

## Padrao para Migrations de Permissoes

### Exemplo Completo

```php
<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        // Inserir permissoes (verificando duplicatas)
        foreach ($permissions as $permission) {
            $existing = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Definir mapeamento de roles -> permissoes
        $rolePermissions = [
            'Proprietario' => [
                'meumodulo.visualizar',
                'meumodulo.criar',
                'meumodulo.editar',
                'meumodulo.excluir'
            ],
            'Gerente' => [
                'meumodulo.visualizar',
                'meumodulo.criar',
                'meumodulo.editar'
            ],
            'Atendente' => [
                'meumodulo.visualizar'
            ]
        ];

        foreach ($rolePermissions as $roleName => $permKeys) {
            // Buscar roles com esse nome (pode haver multiplos por tenant)
            $roles = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->where('name', '=', $roleName)
                ->get();

            foreach ($roles as $role) {
                foreach ($permKeys as $permKey) {
                    $permission = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$permKey])
                        ->first();

                    if ($permission) {
                        // Verificar se ja existe
                        $exists = $this->db()
                            ->table('funcionarios_role_permissions')
                            ->select(['id'])
                            ->whereRaw("role_id = ? AND permission_id = ?",
                                [$role['id'], $permission['id']])
                            ->first();

                        if (!$exists) {
                            $this->db()->table('funcionarios_role_permissions')->insert([
                                'role_id' => $role['id'],
                                'permission_id' => $permission['id']
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            $perm = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if ($perm) {
                // Remover associacoes
                $this->db()
                    ->table('funcionarios_role_permissions')
                    ->whereRaw("permission_id = ?", [$perm['id']])
                    ->delete();

                // Remover permissao
                $this->db()
                    ->table('permissions')
                    ->whereRaw("id = ?", [$perm['id']])
                    ->delete();
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'meumodulo.visualizar',
                'name' => 'Visualizar Meu Modulo',
                'description' => 'Listar e visualizar registros',
                'module' => 'meumodulo'
            ],
            [
                'key' => 'meumodulo.criar',
                'name' => 'Criar Meu Modulo',
                'description' => 'Criar novos registros',
                'module' => 'meumodulo'
            ],
            // ...
        ];
    }
};
```

## Uso em Controllers

```php
class ClientesController extends Controller
{
    public function index()
    {
        if (!Auth::can('clientes.visualizar')) {
            Response::forbidden('Voce nao tem permissao para visualizar clientes.');
        }

        $clientes = $this->clienteModel->listar();
        return Response::json($clientes);
    }

    public function store()
    {
        if (!Auth::can('clientes.criar')) {
            Response::forbidden('Voce nao tem permissao para criar clientes.');
        }

        // ...
    }
}
```

## Metodos Auxiliares em Auth

```php
Auth::can('permissao.acao')     // Verifica permissao
Auth::hasRole('NomeDaRole')     // Verifica role por nome
Auth::getRole()                 // Obtem dados da role atual
Auth::getPermissions()          // Obtem todas permissoes do usuario
Auth::check()                   // Verifica se autenticado
Auth::id()                      // Obtem ID do usuario
Auth::chave()                   // Obtem chave (tenant)
Auth::user()                    // Obtem dados do usuario
```

## Arquivos Principais

| Arquivo | Responsabilidade |
|---------|------------------|
| `app/Core/Auth.php` | Verificacao de autenticacao e permissoes |
| `app/Models/Permission.php` | CRUD de permissoes |
| `app/Models/Role.php` | CRUD de roles |
| `app/Models/RolePermission.php` | Gerencia relacao role-permission |
| `app/Middleware/PermissionMiddleware.php` | Valida permissoes em requisicoes |

## Multi-tenancy

- Roles sao isoladas por `chave` (tenant)
- Roles de sistema tem `chave = '0'` (compartilhadas)
- Tenants podem customizar roles de sistema (criar copia com mesmo nome)
- Permissoes sao globais (mesma para todos os tenants)
