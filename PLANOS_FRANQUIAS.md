# Sistema de Gestao de Franquias - 7Carros Locadora

> Blueprint tecnico completo para implementacao do modulo de franquias no sistema 7Carros Locadora.

---

## Indice

1. [Visao Geral](#1-visao-geral)
2. [Planos de Franquia (F1-F4)](#2-planos-de-franquia-f1-f4)
3. [Alteracoes em Planos.php](#3-alteracoes-em-planosphp)
4. [Schema do Banco de Dados](#4-schema-do-banco-de-dados)
5. [Seguranca Cross-Tenant](#5-seguranca-cross-tenant)
6. [Dashboard do Franqueador](#6-dashboard-do-franqueador)
7. [Visao do Franqueado](#7-visao-do-franqueado)
8. [Sistema de Royalties](#8-sistema-de-royalties)
9. [Provisioning de Franqueados](#9-provisioning-de-franqueados)
10. [Permissoes e Roles](#10-permissoes-e-roles)
11. [Controllers e Rotas](#11-controllers-e-rotas)
12. [Integracao com Modulos Existentes](#12-integracao-com-modulos-existentes)
13. [Estrutura de Arquivos](#13-estrutura-de-arquivos)
14. [Faseamento de Implementacao](#14-faseamento-de-implementacao)
15. [Riscos e Mitigacoes](#15-riscos-e-mitigacoes)

---

## 1. Visao Geral

### 1.1 Objetivo

Adicionar uma camada de **gestao de franquias** ao sistema existente, permitindo que um tenant com plano de franquia (F1-F4) atue como **franqueador** — criando, gerenciando e monitorando outros tenants (**franqueados**) dentro da plataforma.

### 1.2 Decisao Arquitetural Fundamental

O desafio central e que **franquias sao uma relacao ENTRE tenants**, enquanto o sistema atual isola completamente cada tenant via `chave`. A solucao cria uma **camada supra-tenant** sem quebrar o isolamento existente.

**Principio**: O franqueador opera como um tenant normal (sua propria `chave`) mas tem **permissao controlada e auditada** para consultar dados de franqueados especificos via uma tabela de relacionamento dedicada.

### 1.3 Diagrama de Relacionamento

```
FRANQUEADOR (chave='AAA', plano='F2')
    |
    +--- tabela "franquias" (cross-tenant, SEM coluna chave)
    |       |
    |       +--- FRANQUEADO 1 (chave='BBB', plano='P2')
    |       +--- FRANQUEADO 2 (chave='CCC', plano='P3')
    |       +--- FRANQUEADO 3 (chave='DDD', plano='P2')
    |
    +--- Opera normalmente como locadora (sua propria frota, clientes, etc.)
```

A tabela `franquias` nao possui coluna `chave` — e uma tabela de metadados cross-tenant acessada sempre via `withoutChave()`, seguindo o mesmo padrao de `permissions` e `funcionarios_roles` (chave='0').

### 1.4 Premissas

- **Zero impacto em tenants existentes**: Planos G/P0-P4 nao ativam nenhuma funcionalidade de franquia.
- **Franqueador tambem e locadora**: O franqueador opera sua propria locadora normalmente, alem de gerenciar a rede.
- **Franqueado e transparente**: O franqueado sabe que pertence a uma rede, ve seus royalties e contrato.
- **Royalties por percentual**: Modelo percentual sobre receita bruta + fundo de marketing opcional (referencia: Unidas 7% + 2%).
- **Dados somente leitura**: O franqueador consulta dados dos franqueados, mas NAO modifica.
- **Franqueado nao cria contas**: Apenas o franqueador pode provisionar novas empresas/contas na rede. O franqueado NAO tem acesso a nenhuma funcionalidade de criacao de contas — ele opera exclusivamente o tenant que o franqueador criou para ele.
- **Sem sub-franquias**: Um franqueado NAO pode receber plano de franquia (F1-F4). Nao existem cadeias hierarquicas — a relacao e sempre **franqueador -> franqueado** em um unico nivel. Validar tanto no provisioning quanto na troca de plano.

### 1.5 Ativacao de Franqueador (Tenant Existente)

Um tenant que ja opera como locadora pode se tornar franqueador quando seu plano e alterado de P* para F* via WHMCS.

#### Como o plano e alterado hoje

O WHMCS chama `POST /webhook/whmcs/mudar-pacote` (ver `docs/whmcs.md`) que:
1. Valida o plano com `Planos::existe()` (`WhmcsController.php:125`)
2. Atualiza `funcionarios.plano` de todos os funcionarios do tenant (`TenantProvisioningService.php:259`)
3. Loga a mudanca com plano anterior e novo

Atualmente, o `mudarPacote()` **so faz o update no banco**. Para franquias funcionar, precisa ser estendido.

#### Problema: Sessao desatualizada

O plano e carregado do banco **uma vez no login** e armazenado em `$_SESSION['user_plano']` (`Auth.php:101`). Quando o WHMCS muda o plano via webhook, a sessao dos usuarios logados **nao e atualizada**. O metodo `Auth::refresh()` (`Auth.php:428`) atualiza a sessao do banco, mas nao e chamado automaticamente.

| Camada | Onde vive | Atualizada pelo webhook? |
|--------|----------|------------------------|
| Banco (`funcionarios.plano`) | BD | Sim — `TenantProvisioningService::mudarPacote()` |
| Sessao (`$_SESSION['user_plano']`) | Memoria do servidor | **Nao** — stale ate re-login ou `Auth::refresh()` |
| Cache de permissoes | Cache com TTL 1h | **Nao** — stale ate expirar |

#### Solucao: Estender `TenantProvisioningService::mudarPacote()` + `PlanoChangeService`

**Componente 1**: Estender o metodo `mudarPacote()` em `TenantProvisioningService.php` para chamar o `PlanoChangeService` apos o update.

```php
// Em TenantProvisioningService::mudarPacote() — APOS o update existente:

// Detectar transicao de plano envolvendo franquia
$eraFranquia = Planos::isFranquia($planoAnterior);
$agoraFranquia = Planos::isFranquia($plano);

if ($eraFranquia !== $agoraFranquia || $eraFranquia) {
    (new PlanoChangeService())->handle($chave, $planoAnterior, $plano);
}
```

**Componente 2**: Novo `PlanoChangeService` com logica de ativacao/desativacao.

**Arquivo**: `app/Services/PlanoChangeService.php`

```php
class PlanoChangeService
{
    /**
     * Processa transicao de plano envolvendo franquia.
     * Chamado pelo TenantProvisioningService::mudarPacote().
     */
    public function handle(string $chave, string $planoAntigo, string $planoNovo): void
    {
        $eraFranquia = Planos::isFranquia($planoAntigo);
        $agoraFranquia = Planos::isFranquia($planoNovo);

        // Caso 1: P* → F* (ativacao de franqueador)
        if (!$eraFranquia && $agoraFranquia) {
            $this->ativarFranqueador($chave);
        }

        // Caso 2: F* → P* (desativacao de franqueador)
        if ($eraFranquia && !$agoraFranquia) {
            $this->desativarFranqueador($chave);
        }

        // Caso 3: F1 → F3 (upgrade/downgrade de tier de franquia)
        if ($eraFranquia && $agoraFranquia) {
            $this->validarTransicaoFranquia($chave, $planoAntigo, $planoNovo);
        }

        // Forcar refresh de sessao na proxima requisicao de qualquer usuario do tenant
        $this->marcarRefreshPendente($chave);

        // Invalidar caches
        FranquiaAccessHelper::invalidateCache();
    }

    private function ativarFranqueador(string $chave): void
    {
        // 1. Validar: tenant NAO e franqueado de outra rede
        $eFranqueado = (new Franquia())->withoutChave()
            ->where('chave_franqueado', $chave)
            ->whereIn('status', ['ativo', 'suspenso'])
            ->first();

        if ($eFranqueado) {
            // Conflito: WHMCS ativou franquia para um tenant que e franqueado
            // Logar alerta critico para admin resolver
            SecurityLogService::log('plano_conflict_franqueado_to_franqueador', [
                'chave' => $chave,
                'franqueador_atual' => $eFranqueado['chave_franqueador']
            ]);
            return; // NAO ativar roles — situacao invalida
        }

        // 2. Criar roles de franquia para o tenant (se nao existirem)
        //    Copiar roles "Franqueador" e "Gerente de Rede" de chave='0' para chave=$chave
        $this->criarRolesFranquia($chave);

        // 3. Atribuir role "Franqueador" ao owner do tenant
        //    Buscar funcionario com funcao='owner' ou o primeiro admin
        $this->atribuirRoleFranqueadorAoOwner($chave);
    }

    private function desativarFranqueador(string $chave): void
    {
        // Validar: nao tem franqueados ativos
        $franqueadosAtivos = (new Franquia())->contarFranqueadosAtivos($chave);

        if ($franqueadosAtivos > 0) {
            // WHMCS nao deveria ter permitido — logar alerta critico
            SecurityLogService::log('plano_conflict_downgrade_com_franqueados', [
                'chave' => $chave,
                'franqueados_ativos' => $franqueadosAtivos
            ]);
            return; // NAO reverter roles — admin precisa resolver
        }

        // Reverter role do owner para "Proprietario"
        $this->reverterRoleProprietario($chave);
    }

    private function validarTransicaoFranquia(string $chave, string $antigo, string $novo): void
    {
        // Verificar downgrade: se novo plano tem menos franqueados que os ativos
        $maxNovo = Planos::getMaxFranqueados($novo);
        $ativos = (new Franquia())->contarFranqueadosAtivos($chave);

        if ($ativos > $maxNovo) {
            SecurityLogService::log('plano_conflict_downgrade_excede_limite', [
                'chave' => $chave,
                'plano_antigo' => $antigo,
                'plano_novo' => $novo,
                'franqueados_ativos' => $ativos,
                'max_novo_plano' => $maxNovo
            ]);
            // Nao bloqueia — WHMCS ja mudou. Log para admin resolver.
        }
    }

    /**
     * Marca que todos os usuarios do tenant precisam de refresh na proxima requisicao.
     * Usa coluna `session_refresh_at` em funcionarios OU flag no cache.
     */
    private function marcarRefreshPendente(string $chave): void
    {
        // Opcao A: Setar flag no cache por chave do tenant
        Cache::set("plano_refresh:{$chave}", time(), 86400); // 24h TTL

        // O middleware PlanoRefreshMiddleware (ver 1.7) checa esta flag
        // e chama Auth::refresh() se necessario
    }
}
```

**Componente 3**: Middleware leve para refresh de sessao.

**Arquivo**: `app/Middleware/PlanoRefreshMiddleware.php`

O middleware roda em **toda requisicao autenticada** e verifica se ha um refresh pendente para o tenant:

```php
class PlanoRefreshMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            return;
        }

        $chave = Auth::chave();
        $cacheKey = "plano_refresh:{$chave}";
        $refreshAt = Cache::get($cacheKey);

        if (!$refreshAt) {
            return; // Nenhum refresh pendente
        }

        // Verificar se a sessao ja foi refreshed apos o timestamp
        $lastRefresh = Session::get('last_plan_refresh', 0);
        if ($lastRefresh >= $refreshAt) {
            return; // Ja foi refreshed
        }

        // Forcar refresh da sessao (atualiza plano, permissoes, etc.)
        Auth::refresh();
        Session::set('last_plan_refresh', time());
    }
}
```

**Performance**: O middleware faz apenas 1 leitura de cache (`Cache::get`) por requisicao. So quando ha refresh pendente e que chama `Auth::refresh()` (que faz query no BD). Impacto negligivel.

#### Fluxo Completo: WHMCS muda P3 → F2

```
1. WHMCS chama POST /webhook/whmcs/mudar-pacote { chave: 'AAA', plano: 'F2' }
   |
   v
2. WhmcsController::mudarPacote() valida plano com Planos::existe('F2')
   |
   v
3. TenantProvisioningService::mudarPacote() atualiza BD + chama PlanoChangeService
   |
   v
4. PlanoChangeService::handle('AAA', 'P3', 'F2')
   |-- Detecta: P3 (nao-franquia) → F2 (franquia) → ativarFranqueador()
   |-- Valida: tenant 'AAA' nao e franqueado de ninguem ✓
   |-- Cria roles "Franqueador" e "Gerente de Rede" para o tenant
   |-- Atribui role "Franqueador" ao owner
   |-- Marca refresh pendente no cache: plano_refresh:AAA = timestamp
   |-- Invalida cache de chaves de franquia
   |
   v
5. Webhook retorna { success: true, plano_anterior: 'P3', plano_novo: 'F2' }
   |
   v
6. Usuario faz qualquer requisicao na aplicacao
   |
   v
7. PlanoRefreshMiddleware detecta flag plano_refresh:AAA no cache
   |-- Chama Auth::refresh() → sessao atualizada com plano='F2'
   |-- Limpa flag de refresh
   |
   v
8. Tudo funciona:
   - Auth::user()['plano'] = 'F2' ✓
   - Planos::isFranquia('F2') = true ✓
   - FranquiaAccessHelper::isFranqueador() = true ✓
   - Auth::can('franquias.visualizar') = true ✓ (role ja atribuida)
   - Menu "Franquias" aparece ✓
   - Tab "Minha Rede" no dashboard ✓
   - PlanoLimiteHelper usa limites do F2 ✓
```

#### Cenarios de conflito (WHMCS nao valida regras de franquia)

O WHMCS nao tem conhecimento das regras de franquia. Se ocorrer uma transicao invalida, o `PlanoChangeService` **NAO bloqueia** (o WHMCS ja mudou o plano) — mas **loga um alerta critico** em `security_logs` para o admin 7Carros resolver manualmente:

| Situacao | O que o PlanoChangeService faz |
|----------|-------------------------------|
| Franqueado recebe plano F1-F4 | Log `plano_conflict_franqueado_to_franqueador`. NAO cria roles. |
| Franqueador com 20 ativos recebe F1 (max 5) | Log `plano_conflict_downgrade_excede_limite`. Limites ficam inconsistentes ate admin resolver. |
| Franqueador com ativos recebe P* | Log `plano_conflict_downgrade_com_franqueados`. NAO reverte role. |

### 1.6 Regras de Transicao de Plano

| Transicao | Permitida? | Condicao | Validada por |
|-----------|-----------|----------|-------------|
| G/P0-P4 → F1-F4 | Sim | Tenant NAO e franqueado de outra rede | `PlanoChangeService::ativarFranqueador()` |
| F1 → F2/F3/F4 (upgrade) | Sim | Sempre permitido | Nenhuma acao extra |
| F3 → F1 (downgrade) | Condicional | `franqueados_ativos <= max_franqueados_novo_plano` | `PlanoChangeService::validarTransicaoFranquia()` |
| F1-F4 → P0-P4 (sair) | Condicional | `franqueados_ativos == 0` | `PlanoChangeService::desativarFranqueador()` |
| Franqueado → F1-F4 | **Nunca** | Nao pode ser franqueador e franqueado | `PlanoChangeService::ativarFranqueador()` |

**Nota sobre WHMCS**: O WHMCS nao valida essas regras. Se ocorrer transicao invalida, o sistema loga alerta critico mas NAO reverte o plano (WHMCS e o source of truth para billing). O admin 7Carros deve resolver manualmente.

### 1.7 Novos Arquivos de Infraestrutura

| Arquivo | Tipo | Fase | Descricao |
|---------|------|------|-----------|
| `app/Services/PlanoChangeService.php` | NOVO | Fase 1 | Logica de ativacao/desativacao de franqueador |
| `app/Middleware/PlanoRefreshMiddleware.php` | NOVO | Fase 1 | Forca refresh de sessao apos mudanca de plano |
| `app/Services/TenantProvisioningService.php` | MODIFICAR | Fase 1 | Chamar `PlanoChangeService` apos `mudarPacote()` |
| `app/Controllers/WhmcsController.php` | SEM MUDANCA | — | Ja valida plano com `Planos::existe()` |

Estes arquivos devem ser criados na **Fase 1 (Fundacao)**, pois sao pre-requisito para tudo funcionar.

---

## 2. Planos de Franquia (F1-F4)

### 2.1 Tabela Comparativa

| Recurso | F1 Iniciante | F2 Crescimento | F3 Profissional | F4 Enterprise |
|---------|-------------|----------------|-----------------|---------------|
| **Max Franqueados** | 5 | 15 | 50 | Ilimitado |
| **Plano Padrao Franqueado** | P2 | P3 | P3 | P4 |
| **Veiculos (proprio)** | Ilimitado | Ilimitado | Ilimitado | Ilimitado |
| **Filiais (proprio)** | Ilimitado | Ilimitado | Ilimitado | Ilimitado |
| **WhatsApp** | 1 | 1 | 1 | 1 |
| **SMS** | Ilimitado | Ilimitado | Ilimitado | Ilimitado |
| **SMTP** | Ilimitado | Ilimitado | Ilimitado | Ilimitado |
| **Dashboard Rede** | Basico | Completo | Completo | Completo |
| **Relatorios Rede** | Resumo | Completo | Completo + Export | Completo + Export + API |
| **Copiar Config p/ Franqueado** | Sim | Sim | Sim | Sim |
| **Comunicados em Massa** | Sim | Sim | Sim | Sim |
| **Ranking de Franqueados** | Sim | Sim | Sim | Sim |

#### Descricao dos Recursos

- **Max Franqueados** — Quantidade maxima de franqueados que o franqueador pode criar na rede.
- **Plano Padrao Franqueado** — Plano de assinatura (P1-P4) atribuido automaticamente a cada novo franqueado provisionado.
- **Veiculos / Filiais (proprio)** — Limites de veiculos e filiais do proprio franqueador (todos ilimitados nos planos de franquia).
- **WhatsApp / SMS / SMTP** — Canais de comunicacao disponiveis para o franqueador.
- **Dashboard Rede** — Painel do franqueador com indicadores consolidados da rede. Basico mostra totais e royalties; Completo inclui ranking, financeiro, evolucao mensal e alertas.
- **Relatorios Rede** — Relatorios agregados com dados de todos os franqueados. Resumo = visualizacao basica; Completo = todos os relatorios; Export = PDF/Excel; API = acesso via endpoint REST.
- **Copiar Config p/ Franqueado** — Ao provisionar um novo franqueado, clonar configuracoes do franqueador (grupos de veiculos, templates de mensagem, formas de pagamento, taxas e servicos, planos de conta).
- **Comunicados em Massa** — Envio de comunicados por email para todos os franqueados da rede de uma so vez.
- **Ranking de Franqueados** — Tabela comparativa de performance entre franqueados, com metricas como receita, taxa de ocupacao da frota e status de royalties.

### 2.2 Detalhamento dos Planos

**F1 - Franquia Iniciante**
- Ideal para redes pequenas em fase inicial (ate 5 unidades)
- Dashboard basico: totais da rede, royalties pendentes
- Franqueados criados com plano P2 (10 veiculos, 1 filial) por padrao

**F2 - Franquia Crescimento**
- Para redes em expansao (ate 15 unidades)
- Dashboard completo com KPIs, ranking e evolucao mensal
- Franqueados criados com plano P3 (20 veiculos, 3 filiais) por padrao
- Comunicados em massa para toda a rede

**F3 - Franquia Profissional**
- Para redes consolidadas (ate 50 unidades)
- Tudo do F2 + exportacao de relatorios (PDF, Excel)
- Franqueados com plano P3 por padrao (configuravel)

**F4 - Franquia Enterprise**
- Para grandes redes sem limite de unidades
- Tudo do F3 + acesso via API para integracao com sistemas proprios
- Franqueados com plano P4 (ilimitado) por padrao

### 2.3 Plano do Franqueado

O franqueado recebe um plano regular (P1-P4) atribuido pelo franqueador no momento do provisioning. O franqueador pode alterar o plano do franqueado a qualquer momento. Os limites do plano do franqueado funcionam normalmente (veiculos, filiais, etc.) — o sistema de `PlanoLimiteHelper` continua operando sem alteracoes.

---

## 3. Alteracoes em Planos.php

### 3.1 Novos Planos no Array PLANOS

```php
// Arquivo: app/Config/Planos.php

// Adicionar apos o plano "P4":

"F1" => [
    "plano_nome"                   => "Franquia Iniciante",
    "matrizfilial"                 => 9999999,
    "veiculos"                     => 9999999,
    "whatsapp"                     => 1,
    "sms"                          => 9999999,
    "smtp"                         => 9999999,
    // Campos de franquia
    "franquia"                     => true,
    "max_franqueados"              => 5,
    "plano_padrao_franqueado"      => "P2",
    "dashboard_rede_completo"      => false,
    "comunicados_massa"            => true,
    "ranking_franqueados"          => true,
    "relatorios_rede"              => "resumo",
    "relatorios_rede_exportacao"   => false,
    "relatorios_rede_api"          => false,
],
"F2" => [
    "plano_nome"                   => "Franquia Crescimento",
    "matrizfilial"                 => 9999999,
    "veiculos"                     => 9999999,
    "whatsapp"                     => 1,
    "sms"                          => 9999999,
    "smtp"                         => 9999999,
    "franquia"                     => true,
    "max_franqueados"              => 15,
    "plano_padrao_franqueado"      => "P3",
    "dashboard_rede_completo"      => true,
    "comunicados_massa"            => true,
    "ranking_franqueados"          => true,
    "relatorios_rede"              => "completo",
    "relatorios_rede_exportacao"   => false,
    "relatorios_rede_api"          => false,
],
"F3" => [
    "plano_nome"                   => "Franquia Profissional",
    "matrizfilial"                 => 9999999,
    "veiculos"                     => 9999999,
    "whatsapp"                     => 1,
    "sms"                          => 9999999,
    "smtp"                         => 9999999,
    "franquia"                     => true,
    "max_franqueados"              => 50,
    "plano_padrao_franqueado"      => "P3",
    "dashboard_rede_completo"      => true,
    "comunicados_massa"            => true,
    "ranking_franqueados"          => true,
    "relatorios_rede"              => "completo",
    "relatorios_rede_exportacao"   => true,
    "relatorios_rede_api"          => false,
],
"F4" => [
    "plano_nome"                   => "Franquia Enterprise",
    "matrizfilial"                 => 9999999,
    "veiculos"                     => 9999999,
    "whatsapp"                     => 1,
    "sms"                          => 9999999,
    "smtp"                         => 9999999,
    "franquia"                     => true,
    "max_franqueados"              => 9999999,
    "plano_padrao_franqueado"      => "P4",
    "dashboard_rede_completo"      => true,
    "comunicados_massa"            => true,
    "ranking_franqueados"          => true,
    "relatorios_rede"              => "completo",
    "relatorios_rede_exportacao"   => true,
    "relatorios_rede_api"          => true,
],
```

### 3.2 Novos Metodos Estaticos

```php
/**
 * Verifica se um plano e de franquia
 */
public static function isFranquia(string $codigo): bool
{
    return (self::PLANOS[$codigo]['franquia'] ?? false) === true;
}

/**
 * Retorna apenas os planos de franquia
 */
public static function getPlanosFranquia(): array
{
    return array_filter(self::PLANOS, fn($p) => ($p['franquia'] ?? false) === true);
}

/**
 * Retorna apenas os planos regulares (nao-franquia)
 */
public static function getPlanosRegulares(): array
{
    return array_filter(self::PLANOS, fn($p) => ($p['franquia'] ?? false) === false);
}

/**
 * Retorna o limite de franqueados para um plano
 */
public static function getMaxFranqueados(string $codigo): int
{
    return self::PLANOS[$codigo]['max_franqueados'] ?? 0;
}

/**
 * Retorna o plano padrao para novos franqueados
 */
public static function getPlanoPadraoFranqueado(string $codigo): string
{
    return self::PLANOS[$codigo]['plano_padrao_franqueado'] ?? 'P1';
}

/**
 * Verifica se o plano permite uma feature especifica de franquia
 */
public static function franquiaTemFeature(string $codigo, string $feature): bool
{
    return (self::PLANOS[$codigo][$feature] ?? false) === true;
}
```

### 3.3 Extensao do PlanoLimiteHelper

Adicionar o recurso `'franqueados'` ao array `RECURSOS` em `app/Helpers/PlanoLimiteHelper.php`:

```php
'franqueados' => [
    'indice' => 'max_franqueados',
    'label'  => 'franqueados',
    'label_singular' => 'franqueado'
]
```

No metodo `contarRegistros()`:

```php
case 'franqueados':
    return (new Franquia())->contarFranqueadosAtivos(Auth::chave());
```

---

## 4. Schema do Banco de Dados

### 4.1 Tabela `franquias`

Tabela principal de relacionamento entre franqueador e franqueado. **NAO possui coluna `chave`** — e cross-tenant.

```sql
CREATE TABLE franquias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave_franqueador VARCHAR(45) NOT NULL COMMENT 'Chave do tenant franqueador',
    chave_franqueado VARCHAR(45) NOT NULL COMMENT 'Chave do tenant franqueado',
    status ENUM('ativo','suspenso','cancelado') NOT NULL DEFAULT 'ativo',
    data_adesao DATE NOT NULL COMMENT 'Data de adesao a franquia',
    data_suspensao DATE NULL COMMENT 'Data da ultima suspensao',
    data_cancelamento DATE NULL COMMENT 'Data de cancelamento definitivo',
    motivo_cancelamento TEXT NULL,
    plano_franqueado VARCHAR(10) NULL COMMENT 'Plano atribuido (override do padrao)',
    observacoes TEXT NULL COMMENT 'Notas internas do franqueador',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_franqueado (chave_franqueado),
    INDEX idx_franqueador_status (chave_franqueador, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Regras**:
- Um franqueado so pode pertencer a UM franqueador (`UNIQUE KEY uk_franqueado (chave_franqueado)` garante isso a nivel de banco)
- Status `ativo` = operacao normal
- Status `suspenso` = franqueado continua operando mas com banner de aviso e royalties acumulando
- Status `cancelado` = vinculo encerrado, franqueado se torna tenant independente (ver fluxo de cancelamento abaixo)

#### Fluxo de Cancelamento de Franquia

Quando o franqueador cancela um franqueado (ou o franqueado solicita desvinculo):

| Item | O que acontece |
|------|---------------|
| **Registro em `franquias`** | `status` atualizado para `'cancelado'`, `data_cancelamento` preenchido, `motivo_cancelamento` registrado |
| **Contrato** | `franquias_contratos.status` atualizado para `'cancelado'` automaticamente |
| **Royalties pendentes** | Royalties com `status='pendente'` sao **cancelados** (nunca foram faturados, nao ha lancamento financeiro) |
| **Royalties faturados** | Royalties com `status='faturado'` sao **mantidos** — ja geraram lancamentos financeiros em ambos os tenants. Devem ser pagos ou cancelados manualmente pelo franqueador |
| **Royalties pagos** | Inalterados — registros historicos |
| **Plano do franqueado** | **Mantido** no plano atual (ex: P3). O franqueado continua operando normalmente, apenas sem vinculo com a rede. Se necessario, o admin 7Carros pode alterar o plano depois |
| **Menu "Minha Franquia"** | Desaparece automaticamente — `FranquiaAccessHelper::isFranqueado()` retorna `false` quando `status='cancelado'` |
| **Dados do franqueado** | **Inalterados** — veiculos, clientes, financeiro, tudo permanece. O franqueado vira um tenant independente |
| **Cache** | `FranquiaAccessHelper::invalidateCache()` chamado apos o cancelamento |
| **Notificacao** | Email enviado ao owner do franqueado informando o desvinculo |

**Nota**: Um registro cancelado na tabela `franquias` NAO impede que o mesmo tenant seja convidado novamente por outro franqueador no futuro. O `UNIQUE KEY uk_franqueado` deve considerar apenas registros com `status IN ('ativo','suspenso')`. Para isso, a validacao de unicidade deve ser feita **no codigo** (query com filtro de status), e o UNIQUE no banco deve ser removido ou substituido por um indice nao-unico. Alternativa: manter o UNIQUE e fazer `DELETE` em vez de `status='cancelado'` (porem perde historico).

**Decisao recomendada**: Manter UNIQUE KEY e, ao cancelar, fazer soft-delete marcando `status='cancelado'` + remover o UNIQUE KEY, substituindo por validacao no codigo:
```sql
-- Substituir:
-- UNIQUE KEY uk_franqueado (chave_franqueado)
-- Por:
INDEX idx_franqueado (chave_franqueado)
```
E validar no `FranquiaProvisioningService` e `FranquiasController`:
```php
// Antes de vincular, verificar que nao existe vinculo ativo/suspenso
$existente = (new Franquia())->withoutChave()
    ->where('chave_franqueado', $chave)
    ->whereIn('status', ['ativo', 'suspenso'])
    ->first();
if ($existente) {
    throw new \RuntimeException('Tenant ja e franqueado de outra rede');
}
```

### 4.2 Tabela `franquias_contratos`

Termos comerciais do acordo de franquia.

```sql
CREATE TABLE franquias_contratos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_franquia INT UNSIGNED NOT NULL COMMENT 'FK -> franquias.id',
    numero_contrato VARCHAR(50) NULL COMMENT 'Numero do contrato formal',
    data_inicio DATE NOT NULL COMMENT 'Inicio da vigencia',
    data_fim DATE NULL COMMENT 'Fim da vigencia (NULL = indeterminado)',
    -- Taxa de adesao
    valor_taxa_franquia DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa unica de adesao',
    taxa_franquia_paga CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'S/N',
    -- Royalties
    royalty_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentual sobre receita bruta (ex: 7.00 = 7%)',
    royalty_minimo DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor minimo mensal de royalty',
    -- Fundo de marketing
    fundo_marketing_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentual p/ fundo marketing (ex: 2.00 = 2%)',
    -- Cobranca
    dia_cobranca INT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Dia do mes para gerar cobranca de royalty',
    -- Status e documentos
    status ENUM('vigente','expirado','cancelado') NOT NULL DEFAULT 'vigente',
    documento_url VARCHAR(500) NULL COMMENT 'URL/path do contrato PDF assinado',
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_franquia (id_franquia),
    INDEX idx_status (status),
    CONSTRAINT fk_fc_franquia FOREIGN KEY (id_franquia)
        REFERENCES franquias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos de royalty**:
- `royalty_percentual`: Percentual cobrado sobre a receita bruta do franqueado (ex: 7.00 = 7%)
- `royalty_minimo`: Valor minimo mensal (se receita * percentual < minimo, cobra o minimo)
- `fundo_marketing_percentual`: Percentual adicional para fundo de marketing coletivo

### 4.3 Tabela `franquias_royalties`

Registro mensal de royalties calculados. Inspirado em `comissoes_investidores`.

```sql
CREATE TABLE franquias_royalties (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_franquia INT UNSIGNED NOT NULL COMMENT 'FK -> franquias.id',
    id_contrato INT UNSIGNED NOT NULL COMMENT 'FK -> franquias_contratos.id',
    mes_referencia VARCHAR(7) NOT NULL COMMENT 'Formato YYYY-MM',
    -- Receita base
    receita_bruta DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Receita bruta do franqueado no periodo',
    -- Royalty calculado
    royalty_percentual_aplicado DECIMAL(5,2) NOT NULL COMMENT '% aplicado',
    royalty_valor_calculado DECIMAL(15,2) NOT NULL COMMENT 'receita_bruta * percentual',
    royalty_minimo_aplicado DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimo contratual',
    royalty_valor_final DECIMAL(15,2) NOT NULL COMMENT 'MAX(calculado, minimo)',
    -- Fundo de marketing
    fundo_marketing_percentual_aplicado DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    fundo_marketing_valor DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    -- Total
    valor_total DECIMAL(15,2) NOT NULL COMMENT 'royalty_valor_final + fundo_marketing_valor',
    -- Status e rastreamento
    status ENUM('pendente','faturado','pago','cancelado') NOT NULL DEFAULT 'pendente',
    id_financeiro_franqueador INT UNSIGNED NULL COMMENT 'ID lancamento financeiro no tenant franqueador (receita)',
    id_financeiro_franqueado INT UNSIGNED NULL COMMENT 'ID lancamento financeiro no tenant franqueado (despesa)',
    data_faturamento DATE NULL,
    data_pagamento DATE NULL,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_franquia_mes (id_franquia, mes_referencia),
    INDEX idx_status (status),
    INDEX idx_mes (mes_referencia),
    CONSTRAINT fk_fr_franquia FOREIGN KEY (id_franquia)
        REFERENCES franquias(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fr_contrato FOREIGN KEY (id_contrato)
        REFERENCES franquias_contratos(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 Tabela `franquias_metricas_snapshot`

Snapshots de performance de cada franqueado, atualizado por CRON diario. Evita queries cross-tenant pesadas em tempo real no dashboard.

```sql
CREATE TABLE franquias_metricas_snapshot (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_franquia INT UNSIGNED NOT NULL COMMENT 'FK -> franquias.id',
    mes_referencia VARCHAR(7) NOT NULL COMMENT 'Formato YYYY-MM',
    -- Frota
    total_veiculos INT UNSIGNED NOT NULL DEFAULT 0,
    veiculos_disponiveis INT UNSIGNED NOT NULL DEFAULT 0,
    veiculos_locados INT UNSIGNED NOT NULL DEFAULT 0,
    veiculos_manutencao INT UNSIGNED NOT NULL DEFAULT 0,
    taxa_ocupacao DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentual de ocupacao',
    -- Operacional
    total_locacoes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Locacoes iniciadas no periodo',
    total_contratos INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Contratos ativos',
    locacoes_atrasadas INT UNSIGNED NOT NULL DEFAULT 0,
    -- Financeiro
    receita_bruta DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    receita_liquida DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    despesas DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    inadimplencia DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Valores vencidos e nao pagos',
    -- Clientes
    total_clientes INT UNSIGNED NOT NULL DEFAULT 0,
    novos_clientes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Novos no periodo',
    ticket_medio DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_franquia_mes (id_franquia, mes_referencia),
    INDEX idx_franquia (id_franquia),
    CONSTRAINT fk_fms_franquia FOREIGN KEY (id_franquia)
        REFERENCES franquias(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.5 Migracoes

| # | Arquivo | Descricao |
|---|---------|-----------|
| 00287 | `00287_create_franquias.php` | Tabela `franquias` |
| 00288 | `00288_create_franquias_contratos.php` | Tabela `franquias_contratos` |
| 00289 | `00289_create_franquias_royalties.php` | Tabela `franquias_royalties` |
| 00290 | `00290_create_franquias_metricas_snapshot.php` | Tabela `franquias_metricas_snapshot` |
| 00291 | `00291_add_franquia_permissions.php` | 9 novas permissions |
| 00292 | `00292_add_franquia_roles.php` | 2 novas roles de sistema |
| 00293 | `00293_create_franquias_convites.php` | Tabela `franquias_convites` (convites para vincular tenants existentes) |

> **Nota**: Verificar o numero da ultima migracao existente antes de criar. Os numeros acima sao estimados.

---

## 5. Seguranca Cross-Tenant

### 5.1 FranquiaAccessHelper

Componente **critico** que centraliza TODO acesso cross-tenant do franqueador. Nenhum controller ou service deve fazer `withoutChave()` diretamente para dados de franqueados — tudo passa por este helper.

**Arquivo**: `app/Helpers/FranquiaAccessHelper.php`

```php
class FranquiaAccessHelper
{
    /**
     * Verifica se o usuario logado e franqueador (tem plano F1-F4)
     */
    public static function isFranqueador(): bool
    {
        $plano = Auth::user()['plano'] ?? 'G';
        return Planos::isFranquia($plano);
    }

    /**
     * Retorna array de chaves de todos os franqueados ativos
     * Cache: 15 minutos por chave do franqueador
     */
    public static function getChavesFranqueados(): array
    {
        if (!self::isFranqueador()) {
            return [];
        }
        // Cache key: franchise_chaves:{chave_franqueador}
        // Query: SELECT chave_franqueado FROM franquias
        //        WHERE chave_franqueador = ? AND status = 'ativo'
        // Usa withoutChave() pois tabela nao tem coluna chave
    }

    /**
     * Verifica se uma chave especifica e franqueado do franqueador logado
     */
    public static function isMeuFranqueado(string $chaveFranqueado): bool
    {
        return in_array($chaveFranqueado, self::getChavesFranqueados(), true);
    }

    /**
     * Retorna todas as chaves da rede (franqueador + franqueados)
     * Util para queries agregadas do dashboard
     */
    public static function getChavesRede(): array
    {
        $chaves = self::getChavesFranqueados();
        array_unshift($chaves, Auth::chave());
        return $chaves;
    }

    /**
     * Verifica se o usuario logado e franqueado de alguma rede
     */
    public static function isFranqueado(): bool
    {
        // Query: SELECT id FROM franquias
        //        WHERE chave_franqueado = ? AND status IN ('ativo','suspenso')
        //        LIMIT 1
    }

    /**
     * Retorna dados da franquia do franqueado logado
     * (nome do franqueador, status, contrato, royalties pendentes)
     */
    public static function getDadosFranquia(): ?array
    {
        if (!self::isFranqueado()) {
            return null;
        }
        // Query cross-tenant para buscar dados da franquia
        // + nome fantasia do franqueador (de matrizes_filiais)
        // + contrato vigente (de franquias_contratos)
        // + royalties pendentes (de franquias_royalties WHERE status='pendente')
    }

    /**
     * Invalida cache de chaves de franqueados
     * Chamar apos: criar, suspender, reativar ou cancelar franqueado
     */
    public static function invalidateCache(): void
    {
        // Remove cache franchise_chaves:{chave}
    }
}
```

### 5.2 Padrao de Query Cross-Tenant Seguro

**Regra absoluta**: Toda query cross-tenant DEVE seguir este padrao:

```php
// 1. Obter chaves autorizadas via helper
$chaves = FranquiaAccessHelper::getChavesFranqueados();
if (empty($chaves)) {
    return [];
}

// 2. Query com withoutChave() + filtro explicito
$resultado = $this->qb
    ->table('veiculos')
    ->withoutChave()                    // Desabilita filtro automatico
    ->whereIn('chave', $chaves)         // Filtro explicito por chaves autorizadas
    ->selectRaw('chave, COUNT(*) as total')
    ->groupBy('chave')
    ->get();

// 3. Log de auditoria
SecurityLogService::log('franchise_cross_tenant_query', [
    'franqueador' => Auth::chave(),
    'tabela' => 'veiculos',
    'franqueados_consultados' => count($chaves)
]);
```

### 5.3 Restricoes de Seguranca

| Regra | Descricao |
|-------|-----------|
| **Somente leitura** | Franqueador NUNCA modifica dados de franqueados (SELECT only) |
| **Whitelist de chaves** | Apenas chaves da tabela `franquias` com `status='ativo'` |
| **Auditoria obrigatoria** | Toda query cross-tenant logada em `security_logs` |
| **Cache com TTL** | Lista de chaves cacheada por 15 min (nao em tempo real) |
| **Sem session swap** | Franqueador NUNCA troca `$_SESSION['chave']` — opera sempre no seu tenant |
| **Franqueado nao ve outros** | Franqueado so sabe que pertence a uma rede, nao ve dados de outros franqueados |
| **Franqueado sem provisioning** | Franqueado NAO pode criar novas contas/empresas na rede — apenas o franqueador tem essa capacidade. Bloquear via controller se tenant for franqueado |
| **Sem sub-franquias** | Franqueado NAO pode receber plano F1-F4. Validar no provisioning (rejeitar F1-F4 como plano do franqueado) e na troca de plano (se tenant e franqueado, bloquear upgrade para F1-F4) |

### 5.4 Excecao: CRON Jobs

CRON jobs (royalties mensais, snapshots) precisam acessar dados de franqueados. Seguem o padrao existente do `ProcessMessageQueueJob`:

```php
// Dentro do CRON job:
$_SESSION['chave'] = $chaveFranqueado;  // Temporario
$receita = $financeiroModel->somarReceitaMes($mesReferencia);
$_SESSION['chave'] = $chaveFranqueador; // Restaura
```

---

## 6. Dashboard do Franqueador

### 6.1 Integracao no Dashboard Existente

O dashboard do franqueador **NAO substitui** o dashboard normal da locadora. O franqueador tambem opera sua propria locadora (premissa 1.4), entao precisa ver ambas as informacoes.

**Abordagem**: Adicionar uma **tab condicional "Minha Rede"** no dashboard existente, visivel apenas para tenants com plano F1-F4.

```php
// Em DashboardController ou na view do dashboard:
// NAO redirecionar para view separada
// Apenas adicionar dados de rede se franqueador

$dadosRede = null;
if (FranquiaAccessHelper::isFranqueador()) {
    $dadosRede = (new FranquiaDashboardController())->getResumoRede();
}

// Na view: renderizar tab "Minha Rede" se $dadosRede !== null
```

**Estrutura de tabs do dashboard para franqueador**:
1. **Minha Locadora** (tab padrao) — Dashboard normal com os KPIs da propria operacao
2. **Minha Rede** (tab condicional) — Resumo da rede com cards de KPI + link para o dashboard completo

A tab "Minha Rede" mostra apenas um resumo:
- Total de franqueados (ativos/suspensos)
- Royalties pendentes (valor total)
- Receita total da rede no mes
- Taxa de ocupacao media da rede
- Botao "Ver Dashboard Completo" → `/pages/franquias/dashboard`

O **dashboard completo da rede** continua acessivel em `/pages/franquias/dashboard` como uma pagina dedicada (dentro do iframe), com todas as tabs detalhadas (Visao Geral, Franqueados, Royalties, Ranking, Financeiro).

### 6.2 Endpoint de Dados

**Rota**: `GET /api/franquias/dashboard/stats`

**Controller**: `FranquiaDashboardController::stats()`

### 6.3 Estrutura de Dados do Dashboard

```json
{
    "rede": {
        "total_franqueados": 12,
        "franqueados_ativos": 10,
        "franqueados_suspensos": 2,
        "capacidade_plano": 15,
        "ocupacao_rede_pct": 80.0
    },
    "frota_rede": {
        "total_veiculos": 350,
        "veiculos_locados": 240,
        "veiculos_disponiveis": 85,
        "veiculos_manutencao": 25,
        "taxa_ocupacao_media": 68.5
    },
    "financeiro_rede": {
        "receita_bruta_mes": 450000.00,
        "royalties_pendentes": 12500.00,
        "royalties_pagos_mes": 31500.00,
        "royalties_atrasados": 4500.00,
        "fundo_marketing_acumulado": 9000.00,
        "inadimplencia_rede": 15000.00
    },
    "ranking_franqueados": [
        {
            "id_franquia": 1,
            "nome_fantasia": "Locadora Speed - SP",
            "cidade": "Sao Paulo",
            "receita_mes": 85000.00,
            "taxa_ocupacao": 72.5,
            "total_veiculos": 45,
            "status_royalty": "pago",
            "tendencia": "alta"
        }
    ],
    "alertas": [
        {
            "severity": "warning",
            "icon": "fas fa-exclamation-triangle",
            "message": "3 franqueados com royalty pendente ha mais de 15 dias"
        },
        {
            "severity": "critical",
            "icon": "fas fa-file-contract",
            "message": "1 contrato de franquia expira em 30 dias"
        },
        {
            "severity": "info",
            "icon": "fas fa-chart-line",
            "message": "Taxa de ocupacao da rede subiu 5% em relacao ao mes anterior"
        }
    ],
    "evolucao_mensal": [
        {
            "mes": "2026-01",
            "receita_rede": 420000.00,
            "royalties": 29400.00,
            "taxa_ocupacao": 65.2,
            "total_locacoes": 580
        }
    ]
}
```

### 6.4 Fonte dos Dados

| Dado | Fonte | Tempo Real? |
|------|-------|-------------|
| Total franqueados / status | Tabela `franquias` | Sim |
| Royalties pendentes/pagos | Tabela `franquias_royalties` | Sim |
| Frota da rede | `franquias_metricas_snapshot` | CRON diario |
| Receita da rede | `franquias_metricas_snapshot` | CRON diario |
| Ranking | `franquias_metricas_snapshot` | CRON diario |
| Evolucao mensal | `franquias_metricas_snapshot` | CRON diario |
| Alertas | Calculados em tempo real | Sim |

### 6.5 Tabs do Dashboard Franqueador

1. **Visao Geral** - Cards KPI + grafico de evolucao + alertas
2. **Franqueados** - Lista com status, metricas resumidas, acoes rapidas
3. **Royalties** - Resumo mensal, status de pagamento, totais
4. **Ranking** - Tabela comparativa de performance (F2+)
5. **Financeiro da Rede** - Receita agregada, inadimplencia, projecoes (F2+)

### 6.6 Dashboard Basico vs Completo

**Basico (F1)**:
- Tab "Visao Geral": Total franqueados, frota total, royalties pendentes/pagos
- Tab "Franqueados": Lista simples
- Tab "Royalties": Lista de royalties

**Completo (F2+)**:
- Tudo do basico +
- Tab "Ranking" com comparativo
- Tab "Financeiro da Rede" com graficos
- Evolucao mensal com graficos
- Alertas inteligentes

---

## 7. Visao do Franqueado

### 7.1 O que o Franqueado Ve

O franqueado opera o sistema normalmente (como qualquer tenant), mas com estas adicoes:

#### 7.1.1 Banner de Rede

No topo do dashboard do franqueado, exibir um banner informativo:

```
+---------------------------------------------------------------+
|  [Logo Franqueador]  Rede: Locadora XYZ Franquias             |
|  Status: Ativo | Contrato vigente ate: 12/2028                |
+---------------------------------------------------------------+
```

Implementacao: No layout principal (`app.php` ou `iframe.php`), verificar `FranquiaAccessHelper::isFranqueado()` e renderizar banner com dados de `FranquiaAccessHelper::getDadosFranquia()`.

#### 7.1.2 Menu "Minha Franquia"

Novo item no menu lateral, visivel apenas para franqueados:

- **Minha Franquia**
  - Dados da Rede (nome, contato do franqueador)
  - Meu Contrato (termos, documento PDF)
  - Royalties (lista de royalties com status)

#### 7.1.3 Tela de Royalties do Franqueado

**Rota**: `GET /pages/franquias/meus-royalties`

Exibe tabela com:
| Mes Ref. | Receita Base | Royalty (%) | Valor Royalty | Fundo Mkt | Total | Status |
|----------|-------------|-------------|---------------|-----------|-------|--------|
| 2026-02  | R$ 85.000   | 7%          | R$ 5.950      | R$ 1.700  | R$ 7.650 | Pago |
| 2026-03  | R$ 92.000   | 7%          | R$ 6.440      | R$ 1.840  | R$ 8.280 | Pendente |

#### 7.1.4 Integracao no Financeiro

Os royalties faturados aparecem no modulo financeiro do franqueado como despesas normais:
- Tipo: D (Despesa)
- Descricao: "Royalty Franquia - MM/YYYY"
- Plano de Conta: "Royalties de Franquia" (criado no provisioning)

### 7.2 O que o Franqueado NAO Ve

- **Criacao de novas contas/empresas** — apenas o franqueador provisiona novos tenants na rede
- Dados de outros franqueados da rede
- Dashboard do franqueador
- Funcoes de gestao de rede
- Possibilidade de alterar termos do contrato

---

## 8. Sistema de Royalties

### 8.0 Definicao de Receita Bruta

A **receita bruta** utilizada como base de calculo dos royalties e definida como:

> Soma de todos os lancamentos financeiros com `tipo = 'R'` (Receita) e `pago = 'S'` (Pago) no periodo de referencia, **independente do plano de conta**.

**Inclui**:
- Receita de locacoes (diarias)
- Receita de contratos (mensalidades)
- Taxas e servicos adicionais (seguro, GPS, cadeirinha, etc.)
- Multas e juros recebidos
- Qualquer outro lancamento classificado como receita paga no periodo

**Exclui**:
- Receita nao-paga (status `pago = 'N'`) — nao entra no calculo
- Estornos — lancamentos de estorno com `tipo = 'D'` nao sao considerados (sao despesas)
- Receita de royalties recebidos (para evitar calculo circular, caso o franqueador tambem seja franqueado de outra rede no futuro — cenario teoricamente bloqueado, mas seguro por precaucao)

**Periodo**: Mes de referencia completo (dia 1 ate ultimo dia). A `data_pago` do lancamento financeiro determina em qual mes a receita se encaixa.

**Query base**:
```sql
SELECT COALESCE(SUM(valor_total), 0) as receita_bruta
FROM financeiro
WHERE chave = :chave_franqueado
  AND tipo = 'R'
  AND pago = 'S'
  AND data_pago BETWEEN :primeiro_dia_mes AND :ultimo_dia_mes
```

### 8.1 Fluxo Completo

```
1. CRON mensal (dia 1, 07:00)
   |
   v
2. GerarRoyaltiesMensaisJob
   |-- Para cada franquia ativa com contrato vigente:
   |   |-- Define $_SESSION['chave'] = franqueado
   |   |-- Consulta receita bruta do mes anterior (financeiro.tipo='R', pago='S')
   |   |-- Restaura $_SESSION['chave'] = franqueador
   |   |-- Calcula royalty: MAX(receita * percentual, minimo)
   |   |-- Calcula fundo marketing: receita * percentual_marketing
   |   |-- Cria registro em franquias_royalties (status='pendente')
   |
   v
3. Franqueador acessa dashboard
   |-- Ve royalties pendentes
   |-- Clica "Faturar" (ou auto-fatura via CRON no dia_cobranca)
   |
   v
4. Faturamento
   |-- Cria lancamento financeiro no FRANQUEADOR (receita)
   |-- Cria lancamento financeiro no FRANQUEADO (despesa)
   |-- Atualiza status royalty para 'faturado'
   |
   v
5. Pagamento
   |-- Franqueado paga (manual ou via gateway)
   |-- Atualiza status para 'pago'
   |-- Registra data_pagamento
```

### 8.2 RoyaltyService

**Arquivo**: `app/Services/RoyaltyService.php`

Segue padrao do `ComissaoInvestidorService`.

```php
class RoyaltyService
{
    /**
     * Calcula royalty mensal para uma franquia
     *
     * @param int    $idFranquia    ID na tabela franquias
     * @param string $mesReferencia Formato 'YYYY-MM'
     * @return array Dados do royalty criado
     */
    public function calcularRoyaltyMensal(int $idFranquia, string $mesReferencia): array
    {
        // 1. Verificar se ja existe royalty para este mes (idempotencia)
        // 2. Buscar franquia e contrato vigente
        // 3. Buscar receita bruta do franqueado no periodo
        //    - Query cross-tenant em financeiro WHERE chave = franqueado
        //      AND tipo = 'R' AND pago = 'S' AND data_pago BETWEEN inicio/fim do mes
        // 4. Calcular valores
        // 5. Criar registro em franquias_royalties
        // 6. Retornar dados
    }

    /**
     * Fatura um royalty pendente
     * Cria lancamentos no financeiro de ambos os tenants
     */
    public function faturarRoyalty(int $idRoyalty): array
    {
        // 1. Buscar royalty com status = 'pendente'
        // 2. Criar receita no financeiro do FRANQUEADOR
        //    - tipo='R', descricao="Royalty - [NomeFranqueado] - MM/YYYY"
        //    - plano de conta: "Receita de Royalties"
        // 3. Criar despesa no financeiro do FRANQUEADO
        //    - Temporariamente: $_SESSION['chave'] = chave_franqueado
        //    - tipo='D', descricao="Royalty Franquia - MM/YYYY"
        //    - plano de conta: "Royalties de Franquia"
        //    - Restaurar: $_SESSION['chave'] = chave_franqueador
        // 4. Atualizar royalty: status='faturado', data_faturamento=hoje
        // 5. Salvar IDs dos lancamentos financeiros
    }

    /**
     * Marca royalty como pago
     */
    public function pagarRoyalty(int $idRoyalty): array
    {
        // 1. Atualizar status para 'pago'
        // 2. Atualizar data_pagamento
        // 3. Marcar financeiro do franqueador como pago
    }

    /**
     * Cancela um royalty (e estorna financeiros se existirem)
     */
    public function cancelarRoyalty(int $idRoyalty, string $motivo): array
    {
        // 1. Atualizar status para 'cancelado'
        // 2. Se faturado: excluir lancamentos financeiros de ambos os lados
    }

    /**
     * Calcula a receita bruta de um franqueado em um periodo
     * ACESSO CROSS-TENANT
     */
    private function calcularReceitaBruta(string $chaveFranqueado, string $mesReferencia): float
    {
        // Query em financeiro WHERE chave = chaveFranqueado
        //   AND tipo = 'R' AND pago = 'S'
        //   AND data_pago BETWEEN primeiro_dia AND ultimo_dia do mes
        // SUM(valor_total)
    }
}
```

### 8.3 CRON Job: GerarRoyaltiesMensaisJob

**Arquivo**: `app/Crons/Jobs/GerarRoyaltiesMensaisJob.php`

**Agendamento**: `->monthlyOn(1, '07:00')` (dia 1, 07:00)

```php
class GerarRoyaltiesMensaisJob
{
    public function handle(): void
    {
        $mesAnterior = date('Y-m', strtotime('-1 month'));

        // Buscar todas as franquias ativas com contrato vigente
        $franquias = (new Franquia())
            ->withoutChave()
            ->listarAtivasComContrato();

        foreach ($franquias as $franquia) {
            try {
                $royaltyService = new RoyaltyService();
                $royaltyService->calcularRoyaltyMensal(
                    $franquia['id'],
                    $mesAnterior
                );
            } catch (\Exception $e) {
                // Log erro, continua para proxima franquia
                Logger::error("Erro royalty franquia #{$franquia['id']}: " . $e->getMessage());
            }
        }

        // Enviar email resumo para cada franqueador
        $this->enviarResumoFranqueadores($mesAnterior);
    }
}
```

### 8.4 CRON Job: AtualizarMetricasFranquiaJob

**Arquivo**: `app/Crons/Jobs/AtualizarMetricasFranquiaJob.php`

**Agendamento**: `->dailyAt('02:00')`

```php
class AtualizarMetricasFranquiaJob
{
    public function handle(): void
    {
        $mesAtual = date('Y-m');

        $franquias = (new Franquia())
            ->withoutChave()
            ->listarAtivas();

        foreach ($franquias as $franquia) {
            try {
                $metricas = $this->coletarMetricas(
                    $franquia['chave_franqueado'],
                    $mesAtual
                );

                (new FranquiaMetricaSnapshot())->upsert(
                    $franquia['id'],
                    $mesAtual,
                    $metricas
                );
            } catch (\Exception $e) {
                Logger::error("Erro metricas franquia #{$franquia['id']}: " . $e->getMessage());
            }
        }
    }

    private function coletarMetricas(string $chave, string $mes): array
    {
        // Temporariamente setar $_SESSION['chave'] = franqueado
        // Consultar: veiculos, locacoes, financeiro, clientes
        // Restaurar $_SESSION['chave']
        // Retornar array de metricas
    }
}
```

---

## 9. Provisioning de Franqueados

> **Nota sobre WHMCS**: O WHMCS gerencia apenas o onboarding inicial dos tenants (ver `docs/whmcs.md`).
> **Franqueadores** sao criados pelo WHMCS com plano F1-F4 (ou upgradados via `POST /webhook/whmcs/mudar-pacote`).
> **Franqueados** sao criados pelo franqueador **dentro do sistema**, sem envolver o WHMCS.
> Franqueados nao aparecem no WHMCS e nao tem billing separado — o custo esta embutido no plano do franqueador.
>
> **Protecao no terminar**: Quando o WHMCS chama `POST /webhook/whmcs/terminar` para um franqueador que tem franqueados ativos, o `TenantProvisioningService::terminar()` deve:
> 1. Cancelar todos os vinculos de franquia (`franquias.status = 'cancelado'`) do franqueador
> 2. Cancelar contratos vigentes (`franquias_contratos.status = 'cancelado'`)
> 3. Cancelar royalties pendentes (`franquias_royalties.status = 'cancelado'`)
> 4. Notificar os owners dos franqueados por email sobre o desvinculo
> 5. Somente entao prosseguir com a exclusao dos dados do franqueador
>
> Os franqueados continuam operando normalmente como tenants independentes — seus dados NAO sao apagados.

> **REGRA FUNDAMENTAL**: Apenas o **franqueador** pode criar novas empresas/contas na rede. O franqueado **NAO** tem acesso a nenhuma funcionalidade de provisioning. Essa restricao deve ser aplicada tanto no controller (verificar `FranquiaAccessHelper::isFranqueador()`) quanto na UI (menu/botoes invisiveis para franqueados). Se um franqueado tentar acessar a rota de provisioning, retornar 403.

### 9.1 Fluxo de Criacao

1. Franqueador acessa `Franquias > Adicionar Franqueado`
2. Preenche formulario com dados da empresa franqueada
3. Define termos do contrato (royalty %, fundo marketing %, etc.)
4. Opcao: "Copiar configuracoes do franqueador" (templates, grupos, etc.)
5. Sistema provisiona atomicamente todo o tenant
6. Email de boas-vindas enviado ao franqueado com credenciais

### 9.2 FranquiaProvisioningService

**Arquivo**: `app/Services/FranquiaProvisioningService.php`

```php
class FranquiaProvisioningService
{
    /**
     * Provisiona um novo franqueado de forma atomica
     *
     * @param array $dadosEmpresa  Dados da empresa (razao_social, cnpj, email, cidade, estado)
     * @param array $dadosContrato Termos (royalty_percentual, fundo_marketing_percentual, etc.)
     * @param bool  $copiarConfig  Copiar configuracoes do franqueador
     * @return array ['success' => true, 'chave' => '...', 'id_franquia' => int]
     */
    public function provisionar(array $dadosEmpresa, array $dadosContrato, bool $copiarConfig = false): array
    {
        // Verificar que o franqueador NAO e ele mesmo um franqueado (impedir sub-franquias)
        if (FranquiaAccessHelper::isFranqueado()) {
            throw new \RuntimeException('Franqueado nao pode criar sub-franquias');
        }

        // Verificar limite de franqueados no plano
        if (!PlanoLimiteHelper::podeAdicionar('franqueados')) {
            throw new LimitExceededException('Limite de franqueados atingido');
        }

        $this->qb->beginTransaction();

        try {
            // 1. Gerar chave unica para o novo tenant
            $novaChave = $this->gerarChaveUnica();

            // 2. Determinar plano do franqueado (NUNCA F1-F4)
            $planoFranqueador = Auth::user()['plano'];
            $planoFranqueado = $dadosContrato['plano_franqueado']
                ?? Planos::getPlanoPadraoFranqueado($planoFranqueador);

            if (Planos::isFranquia($planoFranqueado)) {
                throw new \RuntimeException('Franqueado nao pode receber plano de franquia (F1-F4)');
            }

            // 3. Criar usuario owner (funcionarios)
            $senhaTemp = $this->gerarSenhaTemporaria();
            $idFuncionario = $this->criarOwner($novaChave, $dadosEmpresa, $planoFranqueado, $senhaTemp);

            // 4. Criar matriz/filial (tipo='M')
            $this->criarMatriz($novaChave, $dadosEmpresa);

            // 5. Criar vinculo na tabela franquias
            $idFranquia = $this->criarVinculoFranquia($novaChave);

            // 6. Criar contrato de franquia
            $this->criarContrato($idFranquia, $dadosContrato);

            // 7. Criar roles padrao para o novo tenant
            $this->criarRolesPadrao($novaChave);

            // 8. Criar planos de conta padrao
            $this->criarPlanoDeContasPadrao($novaChave);

            // 9. Copiar configuracoes se solicitado
            if ($copiarConfig) {
                $this->copiarConfiguracoes($novaChave);
            }

            $this->qb->commit();

            // 10. Enviar email de boas-vindas (fora da transacao)
            $this->enviarEmailBoasVindas($dadosEmpresa['email'], $dadosEmpresa['nome_fantasia'], $senhaTemp);

            // 11. Invalidar cache de chaves
            FranquiaAccessHelper::invalidateCache();

            return [
                'success' => true,
                'chave' => $novaChave,
                'id_franquia' => $idFranquia,
                'id_funcionario' => $idFuncionario,
            ];

        } catch (\Exception $e) {
            $this->qb->rollBack();
            throw $e;
        }
    }
}
```

### 9.3 Dados Copiados no Provisioning

Quando "Copiar configuracoes do franqueador" esta ativo:

| Dado | Tabela Origem | Acao |
|------|--------------|------|
| Grupos de veiculos | `grupos` | Copia com novos IDs e nova `chave` |
| Templates de mensagem | `message_templates` | Copia com nova `chave` |
| Formas de pagamento | `formas_pagamento` | Copia com nova `chave` |
| Taxas e servicos | `taxaseservicos` | Copia com nova `chave` |
| Planos de conta | `planos_de_contas` | Copia com nova `chave` |
| Configuracoes gerais | `configuracoes` | Copia o `data_array` |

### 9.4 Dados Criados Automaticamente (Sempre)

| Dado | Descricao |
|------|-----------|
| Funcionario owner | Usuario com role "Proprietario" + senha temporaria |
| Matriz | Registro em `matrizes_filiais` com tipo='M' |
| Vinculo franquia | Registro em `franquias` com status='ativo' |
| Contrato | Registro em `franquias_contratos` com termos definidos |
| Roles padrao | Copia das 5 roles de sistema (chave='0') para o tenant |
| Plano de conta "Royalties" | Plano de conta especifico para despesas de royalty |

### 9.5 Vincular Tenant Existente como Franqueado

Permite ao franqueador convidar um tenant que ja opera na plataforma para ingressar na rede. Diferente do provisioning (secao 9.1), que cria um tenant do zero, este fluxo vincula um tenant existente mantendo todos os seus dados.

#### Fluxo

1. Franqueador acessa `Franquias > Convidar Locadora Existente`
2. Informa **CNPJ** do tenant que deseja convidar
3. Sistema verifica:
   - Tenant existe e esta ativo na plataforma
   - Tenant **NAO** e franqueado de outra rede (`franquias WHERE chave_franqueado = ? AND status IN ('ativo','suspenso')`)
   - Tenant **NAO** tem plano de franquia F1-F4 (impedir sub-franquias)
   - Franqueador nao excedeu limite de franqueados do seu plano (`PlanoLimiteHelper::podeAdicionar('franqueados')`)
   - Nao existe convite pendente para o mesmo tenant
4. Franqueador define os termos do contrato (royalty %, fundo marketing %, dia de cobranca, etc.)
5. Sistema gera um token seguro e envia **email-convite** ao owner do tenant com:
   - Nome da rede/franqueador
   - Resumo dos termos do contrato
   - Link com token para aceitar (expira em 7 dias)
6. Owner do tenant acessa o link, visualiza os termos completos e aceita ou recusa
7. **Se aceito**:
   - Cria registro em `franquias` com `status='ativo'`
   - Cria registro em `franquias_contratos` com os termos definidos
   - Cria plano de conta "Royalties de Franquia" no tenant franqueado (se nao existir)
   - Atualiza convite para `status='aceito'`
   - Invalida cache de chaves (`FranquiaAccessHelper::invalidateCache()`)
   - Envia email de confirmacao ao franqueador
8. **Se recusado**: Atualiza convite para `status='recusado'`, notifica franqueador
9. **Se expirado**: CRON diario atualiza convites vencidos para `status='expirado'`

**Importante**: O plano do tenant franqueado **NAO e alterado automaticamente**. O franqueador pode alterar o plano do franqueado apos o vinculo, se desejar.

#### Tabela de Suporte: `franquias_convites`

```sql
CREATE TABLE franquias_convites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave_franqueador VARCHAR(45) NOT NULL COMMENT 'Chave do tenant franqueador',
    chave_franqueado VARCHAR(45) NOT NULL COMMENT 'Chave do tenant convidado',
    token VARCHAR(100) NOT NULL COMMENT 'Token seguro para aceite (bin2hex(random_bytes(32)))',
    dados_contrato JSON NOT NULL COMMENT 'Termos propostos (royalty_percentual, fundo_marketing, etc.)',
    status ENUM('pendente','aceito','recusado','expirado') NOT NULL DEFAULT 'pendente',
    data_expiracao DATETIME NOT NULL COMMENT 'Expira em 7 dias apos criacao',
    motivo_recusa TEXT NULL COMMENT 'Motivo informado pelo franqueado ao recusar',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_token (token),
    INDEX idx_franqueado_status (chave_franqueado, status),
    INDEX idx_franqueador (chave_franqueador),
    INDEX idx_expiracao (data_expiracao, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Migracao**: `00293_create_franquias_convites.php`

#### Novas Rotas

```
GET  /pages/franquias/convidar                   # Formulario de convite
POST /franquias/convite/enviar                    # Enviar convite
GET  /franquias/convite/aceitar/{token}           # Pagina publica de aceite (sem login)
POST /franquias/convite/responder/{token}         # Aceitar ou recusar convite
GET  /api/franquias/convites                      # Listar convites enviados
POST /franquias/convite/{id}/reenviar             # Reenviar convite expirado
POST /franquias/convite/{id}/cancelar             # Cancelar convite pendente
```

#### Diferenca entre Provisioning e Vinculo

| Aspecto | Provisioning (9.1) | Vinculo (9.5) |
|---------|-------------------|---------------|
| Tenant | Criado do zero | Ja existe |
| Dados | Vazios (ou copiados do franqueador) | Mantidos integralmente |
| Plano | Definido pelo franqueador | Mantido (alteravel depois) |
| Aceite | Nao requer (franqueador cria) | Requer aceite do tenant |
| Credenciais | Geradas e enviadas | Nao altera |
| Configuracoes | Opcionalmente copiadas | Nao altera |

---

## 10. Permissoes e Roles

### 10.1 Novas Permissions

Inseridas na tabela `permissions` (global, sem `chave`):

| key | name | module |
|-----|------|--------|
| `franquias.visualizar` | Visualizar Franquias | franquias |
| `franquias.criar` | Criar Franqueado | franquias |
| `franquias.editar` | Editar Dados da Franquia | franquias |
| `franquias.suspender` | Suspender/Reativar Franqueado | franquias |
| `franquias.cancelar` | Cancelar Franquia | franquias |
| `franquias.dashboard` | Dashboard da Rede | franquias |
| `franquias.royalties` | Gerenciar Royalties | franquias |
| `franquias.relatorios` | Relatorios da Rede | franquias |
| `franquias.contratos` | Gerenciar Contratos de Franquia | franquias |

### 10.2 Novas Roles de Sistema

Inseridas em `funcionarios_roles` com `chave = '0'` (roles de sistema):

**Franqueador** (herda todas as permissoes de "Proprietario"):
- Todas as 9 permissoes de franquia listadas acima
- Todas as permissoes existentes do "Proprietario"

**Gerente de Rede** (herda de "Gerente"):
- `franquias.visualizar`
- `franquias.dashboard`
- `franquias.royalties` (somente visualizar)
- `franquias.relatorios`
- Todas as permissoes existentes do "Gerente"

### 10.3 Visibilidade do Menu

O menu "Franquias" so aparece quando AMBAS as condicoes sao verdadeiras:
1. `Auth::can('franquias.visualizar')` retorna `true`
2. `FranquiaAccessHelper::isFranqueador()` retorna `true`

O menu "Minha Franquia" (para franqueados) aparece quando:
1. `FranquiaAccessHelper::isFranqueado()` retorna `true`

Tenants regulares (planos G/P0-P4 que NAO sao franqueados) nao veem nenhum dos dois menus.

---

## 11. Controllers e Rotas

### 11.1 Novos Controllers

| Controller | Arquivo | Responsabilidade |
|-----------|---------|------------------|
| `FranquiasController` | `app/Controllers/FranquiasController.php` | CRUD de franqueados, provisioning |
| `FranquiaDashboardController` | `app/Controllers/FranquiaDashboardController.php` | Dashboard e stats da rede |
| `RoyaltiesController` | `app/Controllers/RoyaltiesController.php` | Gestao de royalties |
| `FranquiaContratosController` | `app/Controllers/FranquiaContratosController.php` | Contratos de franquia |
| `MinhaFranquiaController` | `app/Controllers/MinhaFranquiaController.php` | Visao do franqueado |

### 11.2 Rotas do Franqueador

**Paginas (Views)**:

```
GET /pages/franquias                          # Lista de franqueados
GET /pages/franquias/adicionar                # Formulario novo franqueado
GET /pages/franquias/dashboard                # Dashboard da rede
GET /pages/franquias/royalties                # Gestao de royalties
GET /pages/franquias/contratos                # Contratos de franquia
GET /pages/franquias/{id}/editar              # Editar dados franqueado
GET /pages/franquias/{id}/detalhes            # Detalhes do franqueado
```

**APIs**:

```
GET    /api/franquias                         # Listar franqueados paginado
GET    /api/franquias/{id}                    # Detalhe de um franqueado
POST   /franquias/salvar                      # Provisionar novo franqueado
POST   /franquias/{id}/atualizar              # Atualizar dados da franquia
POST   /franquias/{id}/suspender              # Suspender franqueado
POST   /franquias/{id}/reativar               # Reativar franqueado
POST   /franquias/{id}/cancelar               # Cancelar franquia

GET    /api/franquias/dashboard/stats         # Stats completos da rede
GET    /api/franquias/dashboard/ranking       # Ranking de franqueados
GET    /api/franquias/dashboard/evolucao      # Evolucao mensal (12 meses)

GET    /api/franquias/royalties               # Listar royalties paginado
GET    /api/franquias/royalties/totais        # Totais por status
POST   /franquias/royalties/{id}/faturar      # Faturar royalty
POST   /franquias/royalties/{id}/pagar        # Marcar como pago
POST   /franquias/royalties/{id}/cancelar     # Cancelar royalty
POST   /franquias/royalties/faturar-lote      # Faturar varios royalties

GET    /api/franquias/contratos               # Listar contratos
GET    /api/franquias/contratos/{id}          # Detalhe contrato
POST   /franquias/contratos/salvar            # Criar/atualizar contrato
POST   /franquias/contratos/{id}/cancelar     # Cancelar contrato

GET    /api/franquias/verificar-limite        # Verificar limite de franqueados (PlanoLimiteHelper)
```

### 11.3 Rotas do Franqueado

**Paginas**:

```
GET /pages/minha-franquia                     # Dados da rede
GET /pages/minha-franquia/contrato            # Meu contrato de franquia
GET /pages/minha-franquia/royalties           # Meus royalties
```

**APIs**:

```
GET /api/minha-franquia                       # Dados da franquia
GET /api/minha-franquia/royalties             # Listar meus royalties
GET /api/minha-franquia/contrato              # Dados do contrato vigente
```

---

## 12. Integracao com Modulos Existentes

### 12.1 Compatibilidade Retroativa

| Componente | Impacto | Acao |
|-----------|---------|------|
| `Planos.php` | Novo codigo | Adicionar F1-F4 (nao altera existentes) |
| `PlanoLimiteHelper` | Extensao | Adicionar recurso 'franqueados' |
| `DashboardController` | Extensao | Adicionar tab condicional "Minha Rede" |
| `TenantProvisioningService` | Extensao | Chamar `PlanoChangeService` apos `mudarPacote()` |
| `Auth.php` | Nenhum | Ja suporta planos de qualquer codigo, `refresh()` ja atualiza plano |
| `WhmcsController` | Nenhum | Ja valida plano com `Planos::existe()` |
| `QueryBuilder` | Nenhum | Ja tem `withoutChave()` |
| `FilialHelper` | Nenhum | Nao muda |
| `FinanceiroController` | Nenhum | Lancamentos criados programaticamente |
| Menu lateral | Extensao | Adicionar itens condicionais |
| Rotas (`web.php`) | Extensao | Adicionar novas rotas |
| CRON (`cron.php`) | Extensao | Registrar 2 novos jobs |
| Middleware stack | Extensao | Adicionar `PlanoRefreshMiddleware` |
| `docs/whmcs.md` | Extensao | Adicionar F1-F4 na tabela de planos validos |

### 12.2 Dashboard

O dashboard principal **nao e substituido** — recebe uma tab condicional "Minha Rede" quando `FranquiaAccessHelper::isFranqueador()` retorna `true`. A tab carrega dados via AJAX de `/api/franquias/dashboard/stats`. O dashboard completo da rede fica em `/pages/franquias/dashboard`. Ver detalhes na secao 6.1.

### 12.3 Financeiro

Royalties faturados criam lancamentos normais no modulo financeiro existente:

**No franqueador (receita)**:
```php
[
    'tipo' => 'R',
    'descricao' => 'Royalty - Locadora Speed SP - 02/2026',
    'valor_subtotal' => 5950.00,
    'pago' => 'N',
    'data_venci' => '2026-03-05', // dia_cobranca do contrato
    // plano_de_conta: "Receita de Royalties"
]
```

**No franqueado (despesa)**:
```php
[
    'tipo' => 'D',
    'descricao' => 'Royalty Franquia - 02/2026',
    'valor_subtotal' => 5950.00,
    'pago' => 'N',
    'data_venci' => '2026-03-05',
    // plano_de_conta: "Royalties de Franquia"
]
```

Se houver fundo de marketing, cria lancamento separado com descricao "Fundo de Marketing Franquia - MM/YYYY".

### 12.4 Relatorios

Novos relatorios especificos de franquia:

| Relatorio | Permissao | Disponivel em |
|-----------|-----------|---------------|
| Receita Consolidada da Rede | `franquias.relatorios` | F1+ |
| Royalties por Periodo | `franquias.relatorios` | F1+ |
| Performance Comparativa | `franquias.relatorios` | F2+ |
| Taxa de Ocupacao da Rede | `franquias.relatorios` | F2+ |
| Inadimplencia da Rede | `franquias.relatorios` | F2+ |
| Evolucao Mensal da Rede | `franquias.relatorios` | F2+ |

### 12.5 Mensageria — Comunicados em Massa

**Recurso de notificacao em massa do franqueador para as franquias.**

O franqueador (tenant com plano F2+) pode enviar comunicados em lote para todos os seus franqueados de uma so vez. Apenas o **franqueador** envia; os **franqueados** sao destinatarios. O canal utilizado e **email**, enviado ao owner (administrador principal) de cada franquia vinculada.

```php
// Enviar para todos os franqueados ativos
$chaves = FranquiaAccessHelper::getChavesFranqueados();
foreach ($chaves as $chave) {
    // Buscar email do owner do tenant
    $owner = (new Funcionario())->buscarOwnerPorChave($chave);
    queue_message('email', [
        'to' => $owner['email'],
        'subject' => 'Comunicado da Rede',
        'body' => $mensagem,
        'id_matriz_filial' => Auth::user()['id_matriz_filial']
    ]);
}
```

Disponivel apenas em planos F2+ (`comunicados_massa = true`). Plano F1 **nao** possui este recurso.

### 12.6 Comissoes de Investidores

O modulo de comissoes de investidores continua funcionando normalmente dentro de cada tenant. Nao ha interacao direta com o sistema de franquias — sao conceitos distintos:
- **Comissoes**: Investidor que fornece veiculos para UMA locadora
- **Royalties**: Franqueador que licencia a marca para VARIAS locadoras

### 12.7 Notificacoes Automaticas

Notificacoes enviadas automaticamente pelo sistema em resposta a eventos do modulo de franquias. Todas utilizam `queue_message()` ou `queue_template_message()` conforme o canal.

| Evento | Destinatario | Canal | Quando |
|--------|-------------|-------|--------|
| Royalty mensal gerado | Owner do franqueado | Email | CRON `GerarRoyaltiesMensaisJob` (dia 1 do mes) |
| Resumo de royalties gerados | Owner do franqueador | Email | CRON `GerarRoyaltiesMensaisJob` (apos processar todos) |
| Royalty faturado | Owner do franqueado | Email | Ao faturar royalty (manual ou automatico) |
| Royalty vencido ha 15 dias | Owner do franqueador + franqueado | Email | CRON diario (verificar `data_faturamento + 15 < hoje AND status = 'faturado'`) |
| Contrato expira em 30 dias | Owner do franqueador | Email | CRON diario (verificar `data_fim - 30 <= hoje AND status = 'vigente'`) |
| Franqueado provisionado | Owner do franqueador | Email | Apos provisioning (secao 9.1) |
| Convite enviado | Owner do franqueado convidado | Email | Ao enviar convite (secao 9.5) |
| Convite aceito | Owner do franqueador | Email | Ao aceitar convite (secao 9.5) |
| Convite recusado | Owner do franqueador | Email | Ao recusar convite (secao 9.5) |
| Franqueado suspenso | Owner do franqueado | Email | Ao suspender franqueado |
| Franqueado reativado | Owner do franqueado | Email | Ao reativar franqueado |
| Franquia cancelada | Owner do franqueado | Email | Ao cancelar franquia |

**Observacoes**:
- Todas as notificacoes usam credenciais SMTP do **tenant remetente** (franqueador envia com seu SMTP, sistema envia com credenciais ENV)
- Templates de email devem ser criados como `message_templates` com `tipo = 'email'` e `modulo = 'franquias'`
- Notificacoes de CRON diario (royalty vencido, contrato expirando) devem ter controle de **nao reenviar** — usar flag ou tabela de controle para evitar spam diario
- O CRON de notificacoes pode ser integrado ao `AtualizarMetricasFranquiaJob` (que ja roda diariamente) ou ser um job separado `NotificarEventosFranquiaJob`

---

## 13. Estrutura de Arquivos

### 13.1 Novos Arquivos

```
app/
|-- Config/
|   +-- Planos.php                                    # MODIFICAR: add F1-F4 + metodos
|
|-- Controllers/
|   |-- FranquiasController.php                       # NOVO: CRUD franqueados
|   |-- FranquiaDashboardController.php               # NOVO: Dashboard rede
|   |-- RoyaltiesController.php                       # NOVO: Gestao royalties
|   |-- FranquiaContratosController.php               # NOVO: Contratos franquia
|   |-- MinhaFranquiaController.php                   # NOVO: Visao franqueado
|   +-- DashboardController.php                       # MODIFICAR: add view franqueador
|
|-- Models/
|   |-- Franquia.php                                  # NOVO
|   |-- FranquiaContrato.php                          # NOVO
|   |-- FranquiaRoyalty.php                            # NOVO
|   |-- FranquiaMetricaSnapshot.php                   # NOVO
|   +-- FranquiaConvite.php                           # NOVO
|
|-- Services/
|   |-- FranquiaProvisioningService.php               # NOVO
|   |-- RoyaltyService.php                            # NOVO
|   |-- FranquiaMetricasService.php                   # NOVO
|   |-- PlanoChangeService.php                        # NOVO: logica de ativacao/desativacao de franqueador
|   +-- TenantProvisioningService.php                 # MODIFICAR: chamar PlanoChangeService apos mudarPacote()
|
|-- Helpers/
|   |-- FranquiaAccessHelper.php                      # NOVO
|   +-- PlanoLimiteHelper.php                         # MODIFICAR: add 'franqueados'
|
|-- Middleware/
|   +-- PlanoRefreshMiddleware.php                    # NOVO: forca refresh de sessao apos mudanca de plano
|
|-- Crons/Jobs/
|   |-- GerarRoyaltiesMensaisJob.php                  # NOVO
|   +-- AtualizarMetricasFranquiaJob.php              # NOVO
|
|-- Views/pages/
|   +-- franquias/
|       |-- index.php                                 # NOVO: Lista franqueados
|       |-- adicionar.php                             # NOVO: Form novo franqueado
|       |-- editar.php                                # NOVO: Editar franqueado
|       |-- detalhes.php                              # NOVO: Detalhes franqueado
|       |-- dashboard.php                             # NOVO: Dashboard rede
|       |-- royalties.php                             # NOVO: Gestao royalties
|       +-- contratos.php                             # NOVO: Contratos franquia
|
|-- Views/pages/
|   +-- minha-franquia/
|       |-- index.php                                 # NOVO: Dados da rede (franqueado)
|       |-- contrato.php                              # NOVO: Meu contrato
|       +-- royalties.php                             # NOVO: Meus royalties
|
|-- Views/dashboard/
|   +-- franqueador.php                               # NOVO: Dashboard franqueador
|
|-- Database/migrations/
|   |-- 00287_create_franquias.php                    # NOVO
|   |-- 00288_create_franquias_contratos.php          # NOVO
|   |-- 00289_create_franquias_royalties.php          # NOVO
|   |-- 00290_create_franquias_metricas_snapshot.php  # NOVO
|   |-- 00291_add_franquia_permissions.php            # NOVO
|   |-- 00292_add_franquia_roles.php                  # NOVO
|   +-- 00293_create_franquias_convites.php           # NOVO
|
|-- lang/
|   |-- pt_BR/modules/franquias.php                   # NOVO
|   |-- en_US/modules/franquias.php                   # NOVO
|   |-- es_ES/modules/franquias.php                   # NOVO
|   |-- it_IT/modules/franquias.php                   # NOVO
|   +-- pt_PT/modules/franquias.php                   # NOVO
|
|-- Routes/
|   +-- web.php                                       # MODIFICAR: add rotas
|
+-- cron.php                                          # MODIFICAR: registrar jobs
```

### 13.2 Resumo de Contagem

| Tipo | Novos | Modificados |
|------|-------|-------------|
| Controllers | 5 | 1 |
| Models | 5 | 0 |
| Services | 4 | 1 |
| Helpers | 1 | 1 |
| Middleware | 1 | 0 |
| CRON Jobs | 2 | 0 |
| Views | 10 | 0 |
| Migrations | 7 | 0 |
| Traducoes | 5 | 0 |
| Config/Rotas | 0 | 3 |
| **Total** | **41** | **6** |

---

## 14. Faseamento de Implementacao

### Fase 1: Fundacao

**Objetivo**: Infraestrutura basica — planos, banco, helpers, models, integracao WHMCS.

**Arquivos**:
- `app/Config/Planos.php` — adicionar F1-F4 + novos metodos (`isFranquia`, `getMaxFranqueados`, etc.)
- `app/Database/migrations/00287-00292` — criar 4 tabelas + permissions + roles
- `app/Helpers/FranquiaAccessHelper.php` — helper de seguranca cross-tenant
- `app/Helpers/PlanoLimiteHelper.php` — adicionar recurso 'franqueados'
- `app/Models/Franquia.php` — model principal
- `app/Models/FranquiaContrato.php` — model contratos
- `app/Models/FranquiaRoyalty.php` — model royalties
- `app/Models/FranquiaMetricaSnapshot.php` — model snapshots
- `app/Services/PlanoChangeService.php` — **NOVO**: logica de ativacao/desativacao de franqueador (secao 1.5)
- `app/Services/TenantProvisioningService.php` — **MODIFICAR**: chamar `PlanoChangeService` apos `mudarPacote()`
- `app/Middleware/PlanoRefreshMiddleware.php` — **NOVO**: forca refresh de sessao apos mudanca de plano via WHMCS

**Validacao**:
1. Executar migracoes, verificar tabelas criadas
2. Testar `Planos::isFranquia('F1')` retorna `true`, `Planos::isFranquia('P4')` retorna `false`
3. Simular `POST /webhook/whmcs/mudar-pacote` com `plano: 'F2'` no tenant de teste (chave=1111111111111):
   - Verificar que `PlanoChangeService` cria roles de franquia para o tenant
   - Verificar que o owner recebe a role "Franqueador"
   - Verificar que flag `plano_refresh:{chave}` existe no cache
4. Fazer login como o owner do tenant de teste:
   - `PlanoRefreshMiddleware` deve disparar `Auth::refresh()` na primeira requisicao
   - `Auth::user()['plano']` retorna `'F2'`
   - `FranquiaAccessHelper::isFranqueador()` retorna `true`
   - `Auth::can('franquias.visualizar')` retorna `true`
5. Simular `POST /webhook/whmcs/mudar-pacote` com `plano: 'P3'` (reverter):
   - `PlanoChangeService` reverte role para "Proprietario"
   - `FranquiaAccessHelper::isFranqueador()` retorna `false`

### Fase 2: Provisioning e Vinculo de Franqueados

**Objetivo**: Permitir franqueador criar novos franqueados e convidar tenants existentes.

**Arquivos**:
- `app/Services/FranquiaProvisioningService.php` — orquestrador de provisioning
- `app/Models/FranquiaConvite.php` — model de convites
- `app/Database/migrations/00293_create_franquias_convites.php` — tabela de convites
- `app/Controllers/FranquiasController.php` — CRUD + provisioning + convites
- `app/Views/pages/franquias/index.php` — lista de franqueados
- `app/Views/pages/franquias/adicionar.php` — formulario de criacao
- `app/Views/pages/franquias/convidar.php` — formulario de convite
- `app/Routes/web.php` — rotas de franquias
- Menu lateral — adicionar item "Franquias"

**Validacao — Provisioning (tenant novo)**:
1. Novo tenant criado com chave unica
2. Registro em `franquias` com vinculo correto
3. Contrato criado com termos definidos
4. Franqueado consegue fazer login
5. Franqueador ve franqueado na lista
6. Limite de franqueados respeitado

**Validacao — Vinculo (tenant existente)**:
1. Enviar convite por CNPJ — verificar email recebido pelo owner
2. Aceitar convite — verificar registro em `franquias` e contrato criados
3. Recusar convite — verificar que nenhum vinculo foi criado
4. Convite expirado — verificar que nao pode mais ser aceito
5. Tenant ja franqueado — verificar que convite e bloqueado
6. Limite de franqueados — verificar que convite e bloqueado

### Fase 3: Sistema de Royalties

**Objetivo**: Calculo, faturamento e pagamento de royalties.

**Arquivos**:
- `app/Services/RoyaltyService.php` — calculo e faturamento
- `app/Controllers/RoyaltiesController.php` — gestao via UI
- `app/Crons/Jobs/GerarRoyaltiesMensaisJob.php` — geracao automatica mensal
- `app/Views/pages/franquias/royalties.php` — tela de gestao
- `cron.php` — registrar job

**Validacao**:
1. Executar CRON job manualmente
2. Verificar royalties criados com valores corretos
3. Faturar royalty — verificar lancamentos no financeiro de ambos os tenants
4. Marcar como pago — verificar atualizacao de status

### Fase 4: Dashboard do Franqueador

**Objetivo**: Visao consolidada da rede.

**Arquivos**:
- `app/Controllers/FranquiaDashboardController.php` — stats da rede
- `app/Services/FranquiaMetricasService.php` — coleta de metricas
- `app/Crons/Jobs/AtualizarMetricasFranquiaJob.php` — snapshots diarios
- `app/Views/dashboard/franqueador.php` — dashboard principal
- `app/Views/pages/franquias/dashboard.php` — dashboard detalhado
- `app/Controllers/DashboardController.php` — redirecionar franqueadores

**Validacao**:
1. Executar CRON de metricas manualmente
2. Verificar snapshots criados
3. Acessar dashboard como franqueador — ver KPIs da rede
4. Verificar ranking, evolucao, alertas

### Fase 5: Visao do Franqueado

**Objetivo**: Transparencia para o franqueado.

**Arquivos**:
- `app/Controllers/MinhaFranquiaController.php` — APIs do franqueado
- `app/Views/pages/minha-franquia/index.php` — dados da rede
- `app/Views/pages/minha-franquia/contrato.php` — meu contrato
- `app/Views/pages/minha-franquia/royalties.php` — meus royalties
- Layout (`app.php` ou `iframe.php`) — banner de rede
- Menu lateral — adicionar "Minha Franquia" para franqueados

**Validacao**:
1. Login como franqueado — ver banner de rede no topo
2. Acessar "Minha Franquia" — ver dados do franqueador
3. Ver royalties pendentes e pagos
4. Ver contrato vigente
5. Verificar que NAO ve dados de outros franqueados

### Fase 6: Polimento e Seguranca

**Objetivo**: Testes, traducoes, relatorios, documentacao.

**Arquivos**:
- `app/lang/*/modules/franquias.php` — traducoes (5 idiomas)
- `docs/franquias.md` — documentacao tecnica
- Relatorios especificos de franquia
- Contratos de franquia (CRUD, upload PDF)
- Comunicados em massa (F2+)

**Validacao**:
1. Teste de seguranca: tenant normal NAO ve menu de franquias
2. Teste de seguranca: franqueador NAO modifica dados de franqueados
3. Teste de seguranca: franqueado NAO ve outros franqueados
4. Teste de performance: dashboard com 50 franqueados
5. Teste de traducao: verificar todos os idiomas
6. Teste end-to-end completo: provisioning → royalty → pagamento → dashboard

---

## 15. Riscos e Mitigacoes

| # | Risco | Impacto | Probabilidade | Mitigacao |
|---|-------|---------|---------------|-----------|
| 1 | **Vazamento de dados cross-tenant** | Critico | Baixa | `FranquiaAccessHelper` centraliza TODO acesso; auditoria em `security_logs`; nenhum `withoutChave()` direto em controllers |
| 2 | **Performance do dashboard com muitos franqueados** | Alto | Media | Snapshots via CRON (nunca queries cross-tenant em tempo real para dados pesados); paginacao; cache de 15 min |
| 3 | **Provisioning falha no meio** | Medio | Baixa | Transacao atomica com rollback completo; email enviado APOS commit |
| 4 | **Royalty calculado incorretamente** | Alto | Baixa | Idempotencia (UNIQUE constraint por mes); log detalhado; snapshot de valores no registro |
| 5 | **Franqueado tenta acessar dados do franqueador** | Alto | Baixa | Tabela `franquias` consultada apenas pelo franqueador; franqueado so acessa via `MinhaFranquiaController` com dados filtrados |
| 6 | **Conflito de session em CRON jobs** | Medio | Media | Seguir padrao existente de `ProcessMessageQueueJob`: salvar/restaurar `$_SESSION['chave']` |
| 7 | **Cache desatualizado apos criar/cancelar franqueado** | Baixo | Media | `FranquiaAccessHelper::invalidateCache()` chamado apos todas as operacoes de mudanca de status |
| 8 | **Migracao de tenant existente para franquia** | Medio | Alta | Documentar processo manual: alterar `funcionarios.plano` para F1-F4, nao requer migracao de dados |

---

## Apendice A: Glossario

| Termo | Definicao |
|-------|-----------|
| **Franqueador** | Tenant com plano F1-F4 que gerencia uma rede de franqueados |
| **Franqueado** | Tenant vinculado a um franqueador, opera locadora com marca da rede |
| **Chave** | Identificador unico do tenant (varchar(45)), armazenado em `$_SESSION['chave']` |
| **Royalty** | Valor pago mensalmente pelo franqueado ao franqueador (% da receita bruta) |
| **Fundo de Marketing** | Contribuicao adicional do franqueado para marketing coletivo da rede |
| **Provisioning** | Processo de criacao de um novo tenant franqueado |
| **Snapshot** | Fotografia periodica das metricas de um franqueado (atualizada por CRON) |
| **Cross-tenant** | Acesso a dados de outro tenant (controlado via FranquiaAccessHelper) |

## Apendice B: Referencias do Mercado

- **Unidas Rent a Car**: Royalty 7% + Fundo de marketing 2%
- **Rede Brasil**: Taxa fixa mensal (sem percentual)
- **Lei de Franquias (2019)**: Regulamenta taxas e transparencia no Brasil
- **Padrao SaaS**: Base platform fee + per-location charge (tiered)

## Apendice C: Padrao de Referencia no Codigo Existente

O sistema de **Comissoes de Investidores** (`app/Services/ComissaoInvestidorService.php`) serve como referencia direta para a implementacao de royalties:

| Conceito | Comissoes Investidores | Royalties Franquia |
|----------|----------------------|-------------------|
| Trigger | Fatura paga | CRON mensal |
| Calculo | % da locacao OU fixo | % da receita bruta mensal |
| Financeiro | Cria despesa no tenant | Cria receita no franqueador + despesa no franqueado |
| Status | pendente → pago → cancelado | pendente → faturado → pago → cancelado |
| Model | `ComissaoInvestidor` | `FranquiaRoyalty` |
| Service | `ComissaoInvestidorService` | `RoyaltyService` |
| CRON | `GerarComissoesMensaisJob` | `GerarRoyaltiesMensaisJob` |
