# Sistema de Grupos de Veículos

## Índice
1. [Visão Geral](#visão-geral)
2. [Hierarquia de Cálculo de Preços](#hierarquia-de-cálculo-de-preços)
3. [Estrutura de Banco de Dados](#estrutura-de-banco-de-dados)
4. [Relacionamentos](#relacionamentos)
5. [Endpoints de API](#endpoints-de-api)
6. [Models](#models)
7. [Sistema de Preços Progressivos](#sistema-de-preços-progressivos)
8. [Integração com Temporadas](#integração-com-temporadas)
9. [Regras de Negócio](#regras-de-negócio)
10. [Frontend e Interface](#frontend-e-interface)
11. [Exemplos de Código](#exemplos-de-código)

---

## Visão Geral

O **Sistema de Grupos** é o núcleo da precificação da locadora. Grupos representam categorias de veículos (ex: "Econômico", "SUV", "Luxo") e centralizam todas as configurações de preço.

### Propósito
- Categorizar veículos por tipo/segmento
- Servir como unidade de reserva: a locadora pode reservar um grupo/categoria e alocar qualquer veiculo disponivel desse grupo na retirada
- Definir preços base (km pago, km controlado, km livre)
- Configurar preços progressivos por quantidade de dias
- Gerenciar seguros e coberturas por categoria
- Aplicar ajustes sazonais via temporadas

### Arquivos Principais

| Tipo | Arquivo |
|------|---------|
| Model | `app/Models/Grupo.php` |
| Model | `app/Models/GrupoPrecoDia.php` |
| Model | `app/Models/TemporadaGrupo.php` |
| Controller | `app/Controllers/GruposController.php` |
| View Lista | `app/Views/pages/grupos/index.php` |
| View Form | `app/Views/pages/grupos/adicionar.php` |

---

## Hierarquia de Cálculo de Preços

Esta é a lógica central de precificação do sistema. Entender essa hierarquia é fundamental para compreender como os valores são calculados nas locações.

### Fluxo de Decisão

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        CÁLCULO DO PREÇO DA DIÁRIA                           │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PASSO 1: Determinar o VALOR BASE                                           │
│                                                                             │
│  Onde está configurado?                                                     │
│  └─ Tabela `grupos` → campos `valor_plano_km_pago`, `valor_plano_km_livre`  │
│     ou `valor_plano_km_controlado` (conforme tipo de plano escolhido)       │
│                                                                             │
│  Este é o preço "padrão" do grupo, usado quando NÃO há preços progressivos. │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PASSO 2: Verificar PREÇOS PROGRESSIVOS POR DIAS                            │
│                                                                             │
│  Existe faixa de preço configurada para a quantidade de dias da locação?    │
│                                                                             │
│  ┌─────────────────┐     ┌─────────────────────────────────────────────┐   │
│  │  SIM, existe    │ ──► │ SUBSTITUI o valor base pelo valor da faixa │   │
│  └─────────────────┘     └─────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────┐     ┌─────────────────────────────────────────────┐   │
│  │  NÃO existe     │ ──► │ MANTÉM o valor base do grupo (Passo 1)     │   │
│  └─────────────────┘     └─────────────────────────────────────────────┘   │
│                                                                             │
│  Onde está configurado?                                                     │
│  └─ Tabela `grupos_precos_dias` → faixas com `dia_inicio`, `dia_fim`        │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PASSO 3: Verificar AJUSTE DE TEMPORADA                                     │
│                                                                             │
│  A data da locação está dentro de uma temporada ativa?                      │
│  E essa temporada tem ajuste configurado para este grupo?                   │
│                                                                             │
│  ┌─────────────────┐     ┌─────────────────────────────────────────────┐   │
│  │  SIM, tem       │ ──► │ APLICA o ajuste percentual sobre o valor   │   │
│  │  ajuste         │     │ Fórmula: valor × (1 + ajuste/100)          │   │
│  └─────────────────┘     └─────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────┐     ┌─────────────────────────────────────────────┐   │
│  │  NÃO tem        │ ──► │ MANTÉM o valor sem alteração               │   │
│  │  ajuste         │     │                                             │   │
│  └─────────────────┘     └─────────────────────────────────────────────┘   │
│                                                                             │
│  Onde está configurado?                                                     │
│  └─ Tabela `temporadas_grupos` → campo `ajuste_percentual`                  │
│     (+30 = aumenta 30%, -15 = desconto de 15%)                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                        ┌───────────────────┐
                        │   VALOR FINAL     │
                        │   DA DIÁRIA       │
                        └───────────────────┘
```

### Resumo da Hierarquia

| Prioridade | Fonte | Condição | Ação |
|------------|-------|----------|------|
| 1º | Preço Progressivo | Se existe faixa para X dias | **Substitui** o valor base |
| 2º | Valor Base | Se NÃO existe preço progressivo | Usa `valor_plano_*` do grupo |
| 3º | Temporada | Se existe ajuste para o grupo | **Aplica** percentual sobre o valor |

### Exemplos Práticos

#### Exemplo 1: Sem preços progressivos, sem temporada
```
Grupo: Econômico
├─ valor_plano_km_pago: R$ 100,00
├─ Preços progressivos: (nenhum configurado)
└─ Temporada: (nenhuma ativa)

Locação de 5 dias:
└─ Valor do plano: R$ 100,00 (usa valor base)
└─ Total: R$ 500,00
```

#### Exemplo 2: Com preços progressivos, sem temporada
```
Grupo: SUV
├─ valor_plano_km_pago: R$ 200,00 (valor base)
├─ Preços progressivos:
│   ├─ 1-3 dias: R$ 200,00
│   ├─ 4-7 dias: R$ 170,00
│   └─ 8+ dias: R$ 150,00
└─ Temporada: (nenhuma ativa)

Locação de 5 dias:
├─ Faixa encontrada: 4-7 dias → R$ 170,00
└─ Valor do plano: R$ 170,00 (preço progressivo SUBSTITUI o base)
└─ Total: R$ 850,00
```

#### Exemplo 3: Com preços progressivos E temporada
```
Grupo: SUV
├─ valor_plano_km_pago: R$ 200,00 (valor base)
├─ Preços progressivos:
│   ├─ 1-3 dias: R$ 200,00
│   ├─ 4-7 dias: R$ 170,00
│   └─ 8+ dias: R$ 150,00
└─ Temporada Carnaval: +30%

Locação de 5 dias no Carnaval:
├─ Faixa encontrada: 4-7 dias → R$ 170,00
├─ Ajuste temporada: R$ 170,00 × 1.30 = R$ 221,00
└─ Valor do plano: R$ 221,00
└─ Total: R$ 1.105,00
```

#### Exemplo 4: Sem preços progressivos, com temporada (baixa)
```
Grupo: Luxo
├─ valor_plano_km_pago: R$ 500,00 (valor base)
├─ Preços progressivos: (nenhum configurado)
└─ Temporada Baixa Estação: -20%

Locação de 3 dias:
├─ Preço progressivo: não existe → usa valor base R$ 500,00
├─ Ajuste temporada: R$ 500,00 × 0.80 = R$ 400,00
└─ Valor do plano: R$ 400,00
└─ Total: R$ 1.200,00
```

### Pontos Importantes

1. **Preço progressivo SUBSTITUI**, não soma
   - Se existe faixa para X dias, o valor base é ignorado completamente

2. **Temporada AJUSTA**, não substitui
   - O percentual é aplicado sobre o valor (seja base ou progressivo)

3. **Sem configuração = valor base**
   - Se não há faixas progressivas → usa `valor_plano_*`
   - Se não há temporada ativa → não aplica ajuste

4. **Cada tipo de plano é independente**
   - `km_pago`, `km_controlado` e `km_livre` têm faixas separadas
   - Um grupo pode ter progressivos só para km_pago e não para km_livre

---

## Estrutura de Banco de Dados

> **Refactor multi-moeda (migrations 00307–00314):** os valores monetários de grupos agora vivem por filial, já que cada filial opera em sua própria moeda (`matrizes_filiais.currency_code`). A tabela `grupos` foi reduzida a metadados + comissão de investidor; os preços migraram pra `grupos_precos_filiais` (1 linha por grupo×filial) e as faixas progressivas pra `grupos_precos_dias_filiais`. A tabela antiga `grupos_precos_dias` foi dropada.

### Tabela: `grupos`

Armazena apenas metadados do grupo e configuração de comissão do investidor (sem valores monetários de locação — ver `grupos_precos_filiais`).

```sql
CREATE TABLE grupos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chave VARCHAR(45) NOT NULL,           -- Multi-tenancy
  nome VARCHAR(45) NOT NULL,            -- Nome do grupo
  descricao VARCHAR(255),               -- Descrição opcional
  imagem VARCHAR(255),                  -- Caminho da imagem

  -- Comissão de investidor (aplicada à locadora/investidor, sem moeda por filial)
  comissao_investidor_tipo ENUM('percentual_locadora','fixo_locadora','fixo_locadora_mensal','fixo_investidor_mensal'),
  comissao_investidor_valor DECIMAL(10,2),

  visivel_no_site TINYINT(1) DEFAULT 1, -- Exibir no site público

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_grupos_chave (chave)
);
```

---

### Tabela: `grupos_precos_filiais`

Uma linha por `(id_grupo, id_matriz_filial)` com todos os valores monetários do grupo naquela filial, na moeda dela. Criada automaticamente pelo Model `Grupo::criar()` e `MatrizFilial::criar()` com valores zerados (via `GrupoPrecoFilial::garantirEntriesParaGrupo/Filial`).

```sql
CREATE TABLE grupos_precos_filiais (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chave VARCHAR(45) NOT NULL,
  id_grupo INT UNSIGNED NOT NULL,
  id_matriz_filial INT UNSIGNED NOT NULL,

  -- Planos
  valor_plano_km_pago DECIMAL(10,2),
  valor_plano_km_controlado DECIMAL(10,2),
  valor_plano_km_livre DECIMAL(10,2),
  valor_km_excedente DECIMAL(10,2),
  km_franquia INT,

  -- Seguros
  valor_seguro_carro DECIMAL(10,2),
  valor_seguro_terceiros DECIMAL(10,2),
  cobertura_carro DECIMAL(10,2),
  cobertura_terceiros DECIMAL(10,2),

  -- Tolerância e extras
  minutos_tolerancia INT,
  valor_tolerancia DECIMAL(10,2),
  valor_km_retorno DECIMAL(10,2),
  valor_condutor_adicional DECIMAL(10,2),

  UNIQUE KEY uk_grupo_filial (id_grupo, id_matriz_filial),
  FOREIGN KEY (id_grupo) REFERENCES grupos(id) ON DELETE CASCADE,
  FOREIGN KEY (id_matriz_filial) REFERENCES matrizes_filiais(id) ON DELETE CASCADE
);
```

---

### Tabela: `grupos_precos_dias_filiais`

Faixas progressivas de preço por quantidade de dias, por filial (substitui a antiga `grupos_precos_dias`).

```sql
CREATE TABLE grupos_precos_dias_filiais (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chave VARCHAR(45) NOT NULL,
  id_grupo INT UNSIGNED NOT NULL,
  id_matriz_filial INT UNSIGNED NOT NULL,
  tipo_plano ENUM('km_pago','km_controlado','km_livre') NOT NULL,
  dia_inicio INT UNSIGNED NOT NULL,
  dia_fim INT UNSIGNED,
  valor DECIMAL(10,2) NOT NULL,

  FOREIGN KEY (id_grupo) REFERENCES grupos(id) ON DELETE CASCADE,
  FOREIGN KEY (id_matriz_filial) REFERENCES matrizes_filiais(id) ON DELETE CASCADE
);
```

**Conceito de Faixas:**
- `dia_inicio = 1, dia_fim = 3` → Diária de 1 a 3 dias
- `dia_inicio = 4, dia_fim = 7` → Diária de 4 a 7 dias
- `dia_inicio = 8, dia_fim = NULL` → Diária de 8+ dias (sem limite)

**Leitura (snapshot em contratos/locações):** `LocacaoVeiculo::carregarValoresGrupo(grupoId, filialId)` e `ContratoVeiculo::carregarValoresGrupo(grupoId, filialId)` buscam em `grupos_precos_filiais` usando a filial de retirada. O resultado é copiado pro registro em `locacoes_veiculos`/`contratos_veiculos` (que continuam com as colunas de snapshot — por isso essas tabelas não mudaram).

---

### Tabela: `temporadas_grupos`

Armazena ajustes percentuais de preço por temporada.

```sql
CREATE TABLE temporadas_grupos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chave VARCHAR(45) NOT NULL,
  id_temporada INT UNSIGNED NOT NULL,
  id_grupo INT UNSIGNED NOT NULL,
  ajuste_percentual DECIMAL(5,2),        -- Ex: 30.00 = +30%, -15.00 = -15%

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_tg_chave (chave),
  INDEX idx_tg_temporada (id_temporada),
  INDEX idx_tg_grupo (id_grupo),
  UNIQUE KEY uk_temporada_grupo (id_temporada, id_grupo),
  FOREIGN KEY (id_temporada) REFERENCES temporadas(id) ON DELETE CASCADE,
  FOREIGN KEY (id_grupo) REFERENCES grupos(id) ON DELETE CASCADE
);
```

---

## Relacionamentos

### Diagrama de Relacionamentos

```
                              ┌─────────────────┐
                              │    GRUPOS       │
                              │ ├─ id           │
                              │ ├─ chave        │
                              │ ├─ nome         │
                              │ └─ preços base  │
                              └────────┬────────┘
                                       │
        ┌──────────────────────────────┼──────────────────────────────┐
        │                              │                              │
        ▼                              ▼                              ▼
   SET NULL                       CASCADE                        CASCADE
        │                              │                              │
┌───────┴───────┐           ┌──────────┴──────────┐       ┌───────────┴───────────┐
│   VEÍCULOS    │           │ GRUPOS_PRECOS_DIAS  │       │  TEMPORADAS_GRUPOS    │
│ (N por grupo) │           │ (faixas progressivas)│       │  (ajustes sazonais)   │
└───────────────┘           └─────────────────────┘       └───────────┬───────────┘
        │                                                             │
        │                                                             │
┌───────┴───────┐                                                     ▼
│ SET NULL      │                                              ┌──────────────┐
├───────────────┤                                              │  TEMPORADAS  │
│  CONTRATOS    │                                              │ (períodos)   │
├───────────────┤                                              └──────────────┘
│   LOCAÇÕES    │
└───────────────┘
```

### Comportamento ON DELETE

| Tabela Relacionada | Foreign Key | ON DELETE | Descrição |
|--------------------|-------------|-----------|-----------|
| `veiculos` | `id_grupo` | SET NULL | Veículo fica sem grupo |
| `contratos` | `id_grupo` | SET NULL | Histórico preservado (NULL) |
| `locacoes` | `id_grupo` | SET NULL | Histórico preservado (NULL) |
| `grupos_precos_filiais` | `id_grupo` | CASCADE | Valores por filial deletados |
| `grupos_precos_dias_filiais` | `id_grupo` | CASCADE | Faixas por filial deletadas |
| `temporadas_grupos` | `id_grupo` | CASCADE | Ajustes deletados |

**Implicação:** Excluir um grupo NÃO impede a operação. Veículos, contratos e locações perdem a referência (ficam com `id_grupo = NULL`).

---

## Endpoints de API

### Rotas de Leitura

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/pages/grupos` | Renderiza página de lista |
| GET | `/pages/grupos/adicionar` | Renderiza formulário |
| GET | `/api/grupos` | Lista grupos paginados |
| GET | `/api/grupos/{id}` | Detalhes de um grupo |
| GET | `/api/grupos/{id}/precos-filial/{idFilial}` | Valores + faixas progressivas do grupo numa filial |

### Rotas de Escrita

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/grupos/salvar` | Criar novo grupo (só metadados) |
| POST | `/grupos/{id}/atualizar` | Atualizar metadados do grupo |
| POST | `/grupos/{id}/excluir` | Excluir grupo |
| POST | `/grupos/{id}/precos-filial/{idFilial}` | Salvar valores + faixas do grupo numa filial |

---

### GET /api/grupos

Lista grupos com paginação e busca.

**Query Parameters:**
- `page` (int): Página atual (default: 1)
- `perPage` (int): Registros por página (default: 10, max: 100)
- `search` (string): Busca por nome ou descrição

**Resposta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "Econômico",
      "descricao": "Carros compactos",
      "imagem_url": "http://...",
      "visivel_no_site": 1
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 10,
    "total": 5,
    "totalPages": 1,
    "hasNext": false,
    "hasPrev": false
  }
}
```

---

### GET /api/grupos/{id}/precos-filial/{idFilial}

Retorna valores monetários + faixas progressivas do grupo na filial informada. Se a entry ainda não existe em `grupos_precos_filiais`, é criada zerada on-the-fly.

**Resposta:**
```json
{
  "success": true,
  "data": {
    "valores": {
      "valor_plano_km_pago": "120.00", "valor_plano_km_livre": "90.00",
      "valor_km_excedente": "0.45", "km_franquia": 120,
      "valor_seguro_carro": "15.00", "valor_seguro_terceiros": "10.00"
    },
    "precos_dias": {
      "km_pago": [
        { "id": 1, "dia_inicio": 1, "dia_fim": 3, "valor": "150.00" },
        { "id": 2, "dia_inicio": 8, "dia_fim": null, "valor": "100.00" }
      ],
      "km_controlado": [...],
      "km_livre": [...]
    },
    "filial": { "id": 13, "nome_fantasia": "Filial de teste", "currency_code": "USD", "locale": "en_US" }
  }
}
```

---

## Models

### Grupo.php

**Localização:** `app/Models/Grupo.php`

#### Métodos Principais

| Método | Retorno | Descrição |
|--------|---------|-----------|
| `listar()` | array | Lista todos os grupos do tenant |
| `listarPaginado($page, $perPage, $search)` | array | Lista com paginação |
| `buscarPorId($id)` | array\|null | Busca grupo por ID |
| `contar($search)` | int | Conta grupos |
| `criar($dados)` | int | Cria grupo, retorna ID |
| `atualizar($id, $dados)` | bool | Atualiza grupo |
| `excluir($id)` | bool | Exclui grupo |

#### Conversão de Valores

Use o helper canônico **`currency_parse()`** ao salvar/atualizar valores monetários (aceita formato BR `"1.234,56"`, internacional `"1234.56"`, float, int ou null):

```php
$dados['valor_compra'] = currency_parse($input['valor_compra'] ?? '0');
```

> O método privado `toDecimal()` que existia em vários Models foi removido na limpeza de 2026-05. Detalhes em [currency.md](./currency.md).

---

### GrupoPrecoDia.php

**Localização:** `app/Models/GrupoPrecoFilial.php` + `app/Models/GrupoPrecoDiaFilial.php`

Ambos substituem o antigo `GrupoPrecoDia` (removido no cleanup da fase 4).

#### GrupoPrecoFilial — valores monetários por filial

| Método | Retorno | Descrição |
|--------|---------|-----------|
| `buscarPorGrupoFilial($grupoId, $filialId)` | array\|null | Valores do grupo naquela filial |
| `listarPorFilial($filialId)` | array | Todos os grupos com valores na filial |
| `upsert($dados)` | int | Insere ou atualiza a linha |
| `garantirEntriesParaGrupo($grupoId)` | void | Cria entries zeradas pras filiais existentes |
| `garantirEntriesParaFilial($filialId)` | void | Cria entries zeradas pros grupos existentes |

#### GrupoPrecoDiaFilial — faixas progressivas por filial

| Método | Retorno | Descrição |
|--------|---------|-----------|
| `listarPorGrupoFilial($grupoId, $filialId)` | array | Faixas agrupadas por tipo_plano |
| `calcularValor($grupoId, $filialId, $tipo, $dias)` | float\|null | Preço para X dias |
| `salvarTodos($grupoId, $filialId, $chave, $dados)` | void | Persiste faixas dos 3 tipos |

---

### TemporadaGrupo.php

**Localização:** `app/Models/TemporadaGrupo.php`

#### Métodos Principais

| Método | Retorno | Descrição |
|--------|---------|-----------|
| `listarPorTemporada($idTemporada)` | array | Lista ajustes da temporada |
| `listarPorGrupo($idGrupo)` | array | Lista ajustes do grupo |
| `salvar($idTemporada, $idGrupo, $ajuste)` | int\|bool | Upsert de ajuste |
| `salvarEmLote($idTemporada, $ajustes)` | int | Salva múltiplos ajustes |
| `excluirPorTemporada($idTemporada)` | bool | Remove ajustes da temporada |

---

## Sistema de Preços Progressivos

### Conceito

Permite definir valores de diária que variam conforme a quantidade de dias da locação.

**Exemplo Prático:**
- Dias 1-3: R$ 150,00/dia (cliente casual)
- Dias 4-7: R$ 130,00/dia (desconto por período)
- Dias 8+: R$ 100,00/dia (tarifa mensal)

### Tipos de Plano

O sistema suporta 3 tipos de plano independentes:

1. **km_pago** - Preço paga por km (KP)
2. **km_controlado** - Preço com franquia de km (KMC)
3. **km_livre** - Preço com km ilimitado (KL)

Cada tipo pode ter suas próprias faixas de preço.

### Validações

#### 1. Dia Início Obrigatório
```php
if (!isset($faixa['dia_inicio']) || $faixa['dia_inicio'] < 1) {
    throw new InvalidArgumentException("dia_inicio deve ser >= 1");
}
```

#### 2. Dia Fim Válido
```php
if ($faixa['dia_fim'] !== null && $faixa['dia_fim'] < $faixa['dia_inicio']) {
    throw new InvalidArgumentException("dia_fim deve ser >= dia_inicio");
}
```

#### 3. Sobreposição de Faixas

O sistema rejeita faixas que se sobrepõem:

```
✅ VÁLIDO:
├─ Faixa 1: dias 1-3
├─ Faixa 2: dias 4-7
└─ Faixa 3: dias 8+ (NULL)

❌ INVÁLIDO:
├─ Faixa 1: dias 1-5
└─ Faixa 2: dias 3-10  ← Conflito com faixa 1!
```

### Cálculo de Preço

```php
public function calcularValor(int $grupoId, string $tipo, int $dias): ?float
{
    $sql = "SELECT valor FROM grupos_precos_dias
            WHERE id_grupo = ?
            AND tipo_plano = ?
            AND dia_inicio <= ?
            AND (dia_fim IS NULL OR dia_fim >= ?)";

    return $this->qb->getValue(...) ?: null;
}
```

**Lógica:** Busca a faixa onde `dia_inicio <= dias` E (`dia_fim >= dias` OU `dia_fim = NULL`).

**Fallback:** Se não encontrar faixa, retorna `null` → usar `valor_plano_*` base.

---

## Integração com Temporadas

### Conceito

Temporadas permitem aplicar ajustes percentuais nos preços durante períodos específicos (Carnaval, Natal, Baixa Estação).

### Ordem de Aplicação de Preços

```
1. Obter valor BASE do grupo
   └─ valor_plano_diaria, valor_plano_km_controlado, etc.

2. Verificar preço PROGRESSIVO
   └─ Se existir faixa para X dias → substituir valor base

3. Aplicar ajuste de TEMPORADA
   └─ Para cada diária dentro da temporada ativa → multiplicar por (1 + ajuste/100)
```

O período faturado considera a data de retirada inclusiva e a data de devolução
exclusiva. Se uma locação atravessar o início ou o fim de uma temporada, somente
as diárias abrangidas recebem o reajuste. Períodos recorrentes que cruzam o ano,
como `15/12 a 20/01`, são tratados normalmente.

No website, a tarifa média exibida é `subtotal do plano / quantidade de diárias`.
O subtotal preserva o cálculo exato de cada dia e é sempre recalculado no backend;
valores enviados pelo navegador não são usados para criar a reserva.

### Exemplo de Cálculo

```
Grupo: SUV Premium
├─ Valor base diária: R$ 200,00
├─ Preço progressivo (10 dias): R$ 150,00
└─ Temporada Carnaval: +30%

Cálculo:
R$ 150,00 × (1 + 30/100) = R$ 150,00 × 1.30 = R$ 195,00/dia
```

### Estrutura de Ajustes

```php
// Ajuste positivo (alta temporada)
['id_grupo' => 1, 'ajuste_percentual' => 30.00]  // +30%

// Ajuste negativo (baixa temporada)
['id_grupo' => 1, 'ajuste_percentual' => -15.00] // -15%

// Sem ajuste (ajuste = 0 ou registro não existe)
```

Quando houver temporadas sobrepostas, prevalece a temporada ativa de menor ID
que possua ajuste para o grupo, mantendo a precedência histórica do módulo.

---

## Regras de Negócio

### Multi-tenancy

Todos os registros são isolados pela coluna `chave`:

```php
// Todas as queries filtram automaticamente por tenant
$grupos = $grupoModel->listar();
// WHERE chave = Auth::chave()

// Para queries sem filtro (admin global):
$qb->withoutChave()->select('grupos');
```

### Validação de Ownership

O controller verifica se o grupo pertence ao tenant autenticado:

```php
$grupo = $grupoModel->buscarPorId($id);
if ($grupo['chave'] !== Auth::chave()) {
    Response::json(['message' => 'Grupo não encontrado'], 404);
    return;
}
```

### Conversão de Valores

Frontend usa formato brasileiro (1.234,56), backend usa decimal (1234.56):

```javascript
// Frontend: formatar para exibição
formatMoney(1234.56) → "1.234,56"

// Frontend: converter para envio
parseMoney("1.234,56") → 1234.56
```

```php
// Backend: helper canonico (aceita formato BR, internacional, float, int, null)
currency_parse("1.234,56") → 1234.56
```

### Comportamento de Exclusão

Ao excluir um grupo:

1. **Imagem** → Deletada manualmente via FileHelper
2. **Faixas de preço** → Deletadas automaticamente (CASCADE)
3. **Ajustes de temporada** → Deletados automaticamente (CASCADE)
4. **Veículos** → Campo `id_grupo` vira NULL
5. **Contratos/Locações** → Campo `id_grupo` vira NULL (histórico preservado)

### Campo Nome Obrigatório

```php
if (empty($dados['nome'])) {
    Response::json(['message' => 'Nome é obrigatório'], 400);
    return;
}
```

### Upload de Imagem

- Formatos aceitos: JPG, PNG, WebP
- Tamanho máximo: 5MB
- Envio: Base64 no campo `imagem`
- Armazenamento: Processado por `FileHelper`
- Remoção: Flag `remover_imagem = true` no update

---

## Frontend e Interface

### Estrutura de Abas

O formulário é dividido em 2 abas:

**Aba 1: Dados do Grupo**
- Seção Dados Básicos (nome, descrição, imagem, visibilidade)
- Seção Planos de Locação (valores dos 3 planos, km)
- Seção Seguros (valores e coberturas)
- Seção Tolerância e Extras

**Aba 2: Preços por Dias**
- Sub-tab Diária
- Sub-tab KM Controlado
- Sub-tab KM Livre
- Interface dinâmica para adicionar/remover faixas

### Máscaras de Entrada

Todos os campos monetários usam máscara em tempo real:

```javascript
// Input: 1234.56
// Exibição: 1.234,56

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}
```

### Comunicação via postMessage

Como as páginas rodam em iframes, usam postMessage para navegação:

```javascript
// Navegar para lista de grupos
window.parent.postMessage({
    action: 'navigate',
    url: '/pages/grupos'
}, '*');

// Abrir modal de confirmação de exclusão
window.parent.postMessage({
    action: 'openDeleteModal',
    recordId: 123,
    recordName: 'Econômico',
    recordType: 'grupo'
}, '*');
```

### Fluxo de Edição

```
1. GET /pages/grupos/adicionar?id=5
2. JavaScript detecta query param
3. GET /api/grupos/5 → preenche campos
4. GET /api/grupos/5/precos → preenche faixas
5. Usuário modifica dados
6. POST /grupos/5/atualizar
7. Volta para lista
```

---

## Exemplos de Código

### Criar Grupo Programaticamente

```php
use App\Models\Grupo;
use App\Models\GrupoPrecoDia;

$grupoModel = new Grupo();
$precosModel = new GrupoPrecoDia();

// Criar grupo
$id = $grupoModel->criar([
    'nome' => 'SUV Premium',
    'descricao' => 'Veículos SUV de alto padrão',
    'valor_plano_km_pago' => 200.00,
    'valor_plano_km_controlado' => 180.00,
    'valor_plano_km_livre' => 250.00,
    'valor_km_excedente' => 0.50,
    'km_franquia' => 200,
    'valor_seguro_carro' => 35.00,
    'valor_seguro_terceiros' => 25.00,
    'cobertura_carro' => 50000.00,
    'cobertura_terceiros' => 100000.00,
    'visivel_no_site' => 1
]);

// Adicionar preços progressivos
$precosModel->salvarTodos($id, [
    'km_pago' => [
        ['dia_inicio' => 1, 'dia_fim' => 3, 'valor' => 200.00],
        ['dia_inicio' => 4, 'dia_fim' => 7, 'valor' => 180.00],
        ['dia_inicio' => 8, 'dia_fim' => null, 'valor' => 150.00]
    ],
    'km_controlado' => [
        ['dia_inicio' => 1, 'dia_fim' => null, 'valor' => 160.00]
    ],
    'km_livre' => [
        ['dia_inicio' => 1, 'dia_fim' => 7, 'valor' => 250.00],
        ['dia_inicio' => 8, 'dia_fim' => null, 'valor' => 220.00]
    ]
]);
```

---

### Calcular Preço Dinâmico

```php
use App\Models\Grupo;
use App\Models\GrupoPrecoDia;
use App\Models\TemporadaGrupo;

function calcularPrecoLocacao(int $grupoId, string $tipoPlano, int $dias, ?int $temporadaId = null): float
{
    $grupoModel = new Grupo();
    $precosModel = new GrupoPrecoDia();
    $temporadaModel = new TemporadaGrupo();

    // 1. Obter dados do grupo
    $grupo = $grupoModel->buscarPorId($grupoId);

    // 2. Obter valor base
    $valorBase = match($tipoPlano) {
        'km_pago' => (float) $grupo['valor_plano_km_pago'],
        'km_controlado' => (float) $grupo['valor_plano_km_controlado'],
        'km_livre' => (float) $grupo['valor_plano_km_livre'],
        default => 0.0
    };

    // 3. Verificar preço progressivo
    $valorProgressivo = $precosModel->calcularValor($grupoId, $tipoPlano, $dias);
    $valorFinal = $valorProgressivo ?? $valorBase;

    // 4. Aplicar ajuste de temporada (se houver)
    if ($temporadaId) {
        $ajuste = $temporadaModel->buscarPorTemporadaGrupo($temporadaId, $grupoId);
        if ($ajuste && $ajuste['ajuste_percentual'] != 0) {
            $valorFinal *= (1 + $ajuste['ajuste_percentual'] / 100);
        }
    }

    return round($valorFinal, 2);
}

// Exemplo de uso
$preco = calcularPrecoLocacao(
    grupoId: 5,
    tipoPlano: 'km_pago',
    dias: 10,
    temporadaId: 2  // Carnaval
);
echo "Preço por dia: R$ " . number_format($preco, 2, ',', '.');
```

---

### Buscar Grupos com Preços via JavaScript

```javascript
// Listar grupos
const response = await API.get('/api/grupos', {
    page: 1,
    perPage: 20,
    search: 'SUV'
});

if (response.success) {
    response.data.forEach(grupo => {
        console.log(`${grupo.nome}: R$ ${grupo.valor_plano_diaria}`);
    });
}

// Carregar preços de um grupo
const precos = await API.get(`/api/grupos/${grupoId}/precos`);

if (precos.success) {
    // Faixas de km pago
    precos.data.km_pago.forEach(faixa => {
        const ate = faixa.dia_fim ? faixa.dia_fim : '∞';
        console.log(`${faixa.dia_inicio}-${ate} dias: R$ ${faixa.valor}`);
    });
}
```

---

### Salvar Grupo com Preços via JavaScript

```javascript
// Preparar dados do grupo
const dadosGrupo = {
    nome: 'Econômico',
    descricao: 'Carros compactos',
    valor_plano_km_pago: '120,00',
    valor_plano_km_controlado: '100,00',
    valor_plano_km_livre: '150,00',
    valor_km_excedente: '0,50',
    km_franquia: 200,
    visivel_no_site: 1,
    // Imagem em base64 (opcional)
    imagem: 'data:image/jpeg;base64,...'
};

// Criar grupo
const response = await API.post('/grupos/salvar', dadosGrupo);

if (response.success) {
    const grupoId = response.id;

    // Salvar preços progressivos
    await API.post(`/grupos/${grupoId}/precos`, {
        precos_dias: {
            km_pago: [
                { dia_inicio: 1, dia_fim: 3, valor: '120,00' },
                { dia_inicio: 4, dia_fim: null, valor: '100,00' }
            ],
            km_controlado: [],
            km_livre: []
        }
    });
}
```
