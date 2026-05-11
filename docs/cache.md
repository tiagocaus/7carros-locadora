# Sistema de Cache com Redis

## Visão Geral

O sistema de cache utiliza Redis para armazenar dados em memória, reduzindo significativamente a carga no banco de dados e melhorando a performance da aplicação.

### Benefícios Implementados

- ✅ **Cache de queries**: Dados da empresa e permissões de usuário
- ✅ **Multi-tenancy**: Isolamento automático por tenant (`chave`)
- ✅ **TTL configurável**: Controle de tempo de vida de cada cache
- ✅ **Invalidação inteligente**: Limpeza automática quando dados mudam
- ✅ **Métricas**: Hit/Miss rate para monitoramento
- ✅ **Fallback gracioso**: Se Redis falhar, a aplicação continua funcionando

---

## Configuração

### 1. Variáveis de Ambiente

Adicione as seguintes variáveis ao seu `.env`:

```bash
# Configurações do Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=          # Deixe vazio se não tiver senha
REDIS_DATABASE=0
REDIS_TIMEOUT=2.5
REDIS_PREFIX=7carros_cache:
CACHE_TTL=3600           # TTL padrão: 1 hora
```

### 2. Verificar Redis

Certifique-se de que o Redis está rodando:

```bash
# Via MAMP Pro: Redis já está ativo
# Via Terminal:
redis-cli ping
# Resposta esperada: PONG
```

---

## Uso Básico

### Métodos Principais

```php
use App\Core\Cache;

// 1. Armazenar valor
Cache::set('minha_chave', $dados, 3600); // 3600 segundos = 1 hora

// 2. Obter valor
$dados = Cache::get('minha_chave');
$dados = Cache::get('minha_chave', 'valor_padrao'); // Com default

// 3. Remember (obtém ou executa callback)
$usuarios = Cache::remember('usuarios_ativos', 900, function() {
    return Database::fetchAll("SELECT * FROM usuarios WHERE status = 'A'");
});

// 4. Remover valor
Cache::forget('minha_chave');

// 5. Limpar cache do tenant
Cache::flushTenant(); // Limpa apenas o tenant atual

// 6. Verificar se existe
if (Cache::has('minha_chave')) {
    // Chave existe
}
```

### Helpers Disponíveis

```php
// Helper cache() - Versão simplificada
cache('usuarios', $dados, 3600);  // Set
$dados = cache('usuarios');        // Get
$dados = cache('usuarios', function() {  // Remember
    return Database::fetchAll("SELECT * FROM usuarios");
}, 900);

// Outros helpers
cache_remember('chave', 900, $callback);
cache_forget('chave');
cache_stats(); // Retorna estatísticas
```

---

## Implementações Atuais

### 1. Cache de Dados da Empresa

**Localização:** `app/Core/Auth.php:272`

```php
Auth::empresa(); // Cacheado por 15 minutos
```

**Detalhes:**
- **TTL:** 900 segundos (15 minutos)
- **Chave:** `tenant:{chave}:empresa_data`
- **Invalidação:** Ao atualizar dados da empresa

**Benefício:** Elimina 1 query por página (em média 5-10 req/s = 50-100 queries/s economizadas)

### 2. Cache de Permissões de Usuário

**Localização:** `app/Core/Auth.php:233`

```php
Auth::can('criar_veiculos'); // Cacheado por 1 hora
```

**Detalhes:**
- **TTL:** 3600 segundos (1 hora)
- **Chave:** `tenant:{chave}:user_permissions:{user_id}`
- **Invalidação:** Ao atualizar permissões do usuário

**Benefício:** Elimina N queries por página (onde N = número de verificações `Auth::can()`)

---

## Invalidação de Cache

### Métodos Disponíveis

```php
use App\Core\Auth;

// Invalidar permissões de um usuário
Auth::invalidateUserPermissionsCache($userId, $chave);

// Invalidar dados da empresa
Auth::invalidateEmpresaCache($chave);

// Invalidar todo cache do usuário atual
Auth::invalidateUserCache();

// Invalidar TODOS os caches de um tenant
Auth::invalidateTenantCache($chave);
```

### Quando Invalidar

#### Ao Atualizar Dados da Empresa

```php
// Exemplo: Controller que atualiza dados da empresa
public function updateEmpresa($data)
{
    Database::update('matriz_filial', $data, ['chave' => Auth::chave()]);

    // Invalida cache da empresa
    Auth::invalidateEmpresaCache();
}
```

#### Ao Atualizar Permissões

```php
// Exemplo: Controller que atualiza permissões de usuário
public function updatePermissions($userId, $permissions)
{
    Database::update('usuarios', [
        'permissoes' => json_encode($permissions)
    ], ['id' => $userId, 'chave' => Auth::chave()]);

    // Invalida cache de permissões
    Auth::invalidateUserPermissionsCache($userId);
}
```

#### Ao Fazer Logout

```php
// Já implementado automaticamente em Auth::logout()
Auth::logout(); // Invalida todo cache do usuário
```

---

## Multi-tenancy

O cache é **automaticamente isolado por tenant** usando a chave da sessão.

### Como Funciona

```php
// Tenant A (chave: 'locadora_a')
Session::set('chave', 'locadora_a');
Cache::set('empresa_data', $dados_a);
// Armazena em: tenant:locadora_a:empresa_data

// Tenant B (chave: 'locadora_b')
Session::set('chave', 'locadora_b');
Cache::set('empresa_data', $dados_b);
// Armazena em: tenant:locadora_b:empresa_data

// Os dados NÃO se misturam!
```

### Forçar Tenant Específico

```php
// Armazenar em tenant específico
Cache::set('chave', $valor, 3600, 'locadora_xyz');

// Obter de tenant específico
$valor = Cache::get('chave', null, 'locadora_xyz');
```

---

## Métricas e Monitoramento

### Visualizar Estatísticas

Acesse: `http://localhost/cache/stats` (apenas em desenvolvimento)

**Ou via código:**

```php
$stats = Cache::stats();
/*
Array(
    'enabled' => true,
    'hits' => 1523,
    'misses' => 247,
    'sets' => 312,
    'deletes' => 45,
    'hit_rate' => '86.05%',
    'total_requests' => 1770
)
*/
```

### Informações do Servidor Redis

```php
$info = Cache::info();
/*
Array(
    'status' => 'connected',
    'version' => '6.1.0',
    'used_memory' => '2.45M',
    'connected_clients' => 3,
    'total_keys' => 1247
)
*/
```

---

## Padrões de Uso

### 1. Cache de Listagens

```php
// Cachear lista de veículos disponíveis
$veiculos = Cache::remember('veiculos_disponiveis', 300, function() {
    return Database::fetchAll(
        "SELECT * FROM veiculos WHERE status = 'disponivel'"
    );
});
```

### 2. Cache de Contagens

```php
// Cachear contagem de recursos (para validação de planos)
$totalVeiculos = Cache::remember('total_veiculos', 600, function() {
    $result = Database::fetchOne(
        "SELECT COUNT(*) as total FROM veiculos"
    );
    return $result['total'];
});
```

### 3. Cache de Configurações

```php
// Cachear configurações da aplicação
$config = Cache::remember('app_config', 3600, function() {
    return Database::fetchAll("SELECT * FROM configuracoes");
});
```

### 4. Cache com Invalidação Automática

```php
class VeiculoController
{
    public function create($data)
    {
        $id = Database::insert('veiculos', $data);

        // Invalida caches relacionados
        Cache::forgetMany([
            'veiculos_disponiveis',
            'total_veiculos',
            'dashboard_stats'
        ]);

        return $id;
    }
}
```

---

## Operações Avançadas

### Incrementar/Decrementar

```php
// Útil para contadores, rate limiting, etc
Cache::increment('login_attempts:' . $ip, 1);
Cache::decrement('creditos_disponiveis', 1);
```

### Múltiplos Valores

```php
// Set múltiplos
Cache::setMany([
    'key1' => 'value1',
    'key2' => 'value2',
    'key3' => 'value3'
], 3600);

// Get múltiplos
$values = Cache::getMany(['key1', 'key2', 'key3']);
```

### Verificar TTL

```php
// Tempo restante em segundos
$ttl = Cache::ttl('minha_chave');

if ($ttl > 0) {
    echo "Expira em $ttl segundos";
} elseif ($ttl === -1) {
    echo "Chave existe mas não tem TTL (nunca expira)";
} else {
    echo "Chave não existe";
}
```

---

## Performance Esperada

### Antes do Cache

```
Requisição típica de dashboard:
- Query empresa: 15ms
- Query permissões: 12ms
- Query veículos: 45ms
- Query reservas: 38ms
- Queries de contagem: 25ms
TOTAL: ~135ms
```

### Depois do Cache

```
Requisição típica de dashboard (com cache quente):
- Cache empresa: <1ms (HIT)
- Cache permissões: <1ms (HIT)
- Query veículos: 45ms
- Query reservas: 38ms
- Cache contagens: <1ms (HIT)
TOTAL: ~86ms (36% mais rápido!)
```

**Ganho adicional:** Redução de 30-50% na carga do banco de dados.

---

## Troubleshooting

### Redis não conecta

```php
// Verifique se Redis está rodando
if (!Cache::isEnabled()) {
    error_log("Redis não está disponível!");
}

// A aplicação continua funcionando sem cache
```

### Cache não invalida

```php
// Forçar limpeza manual
Auth::invalidateTenantCache();
// ou
Cache::flushTenant();
```

### Memory overflow no Redis

```bash
# Ver uso de memória
redis-cli INFO memory

# Limpar database
redis-cli FLUSHDB
```

---

## Próximos Passos

### Implementações Futuras Sugeridas

1. **Migrar sessões para Redis**
   - Melhor performance que sessões em arquivo
   - Suporte a múltiplos servidores (load balancing)

2. **Cache de queries do QueryBuilder**
   - Cache automático de queries frequentes
   - Invalidação por tags

3. **Rate Limiting**
   - Usar cache para limitar tentativas de login
   - Proteção contra ataques de força bruta

4. **Cache de views/templates**
   - Complementar o cache de views já existente
   - Cache de fragmentos de HTML

---

## Segurança

### Isolamento Multi-tenant

**CRÍTICO:** Sempre use o parâmetro `$tenant` ou confie na sessão para garantir isolamento.

```php
// ✅ CORRETO - Usa tenant da sessão automaticamente
$empresa = Cache::get('empresa_data');

// ✅ CORRETO - Especifica tenant explicitamente
$empresa = Cache::get('empresa_data', null, 'locadora_xyz');

// ⚠️ CUIDADO - Cache global (sem tenant)
Cache::set('global:config', $data); // Use apenas para dados não sensíveis
```

### Dados Sensíveis

**Nunca cache dados extremamente sensíveis** como:
- Senhas (mesmo hasheadas)
- Tokens de autenticação
- Dados de cartão de crédito
- Informações bancárias

Para dados sensíveis, use sempre o banco de dados com query direta.

---

## Changelog

### v1.0.0 (2025-01-22)

- ✅ Implementação inicial do sistema de cache
- ✅ Cache de `Auth::empresa()` (TTL: 15 min)
- ✅ Cache de `Auth::can()` (TTL: 1 hora)
- ✅ Sistema de invalidação automática
- ✅ Métricas e estatísticas (hit/miss rate)
- ✅ Helpers globais de cache
- ✅ Controller de visualização de stats
- ✅ Isolamento multi-tenant
- ✅ Fallback gracioso se Redis falhar

---

## Referências

- [Redis Documentation](https://redis.io/docs/)
- [phpredis Extension](https://github.com/phpredis/phpredis)
- [Cache Best Practices](https://redis.io/docs/manual/patterns/)
