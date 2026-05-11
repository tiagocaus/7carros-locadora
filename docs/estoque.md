# Módulo de Estoque

Inventário de peças e produtos para manutenção de veículos, com baixa automática e controle de estoque negativo.

## Tabela `estoque`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT PK | ID auto-incremento |
| `chave` | VARCHAR | Tenant key (multi-tenancy) |
| `id_matriz_filial` | INT FK | Filial dona do produto |
| `id_fornecedor` | INT FK | Fornecedor (opcional) |
| `produto_codigo` | VARCHAR | Código interno do produto |
| `produto_nome` | VARCHAR | Nome do produto |
| `produto_marca` | VARCHAR | Marca |
| `produto_modelo` | VARCHAR | Modelo |
| `produto_unidade` | VARCHAR | Unidade de medida (UN, L, KG, etc.) |
| `produto_local` | VARCHAR | Localização física (opcional) |
| `produto_estoque_atual` | INT | Quantidade em estoque |
| `produto_estoque_minimo` | INT | Quantidade mínima para alerta |
| `valor_compra` | DECIMAL | Valor de compra |
| `valor_venda` | DECIMAL | Valor de venda |
| `status` | CHAR(1) | `A` = Ativo, `I` = Inativo |
| `baixa_automatica` | CHAR(1) | `S`/`N` — default `N` |
| `permitir_estoque_negativo` | CHAR(1) | `S`/`N` — default `N` |

**Multi-tenancy:** filtro por `chave` + `id_matriz_filial`.

## Rotas

### Páginas (iframe)

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/pages/estoque` | `view()` | Listagem paginada |
| GET | `/pages/estoque/adicionar` | `viewAdicionar()` | Formulário de criação |
| GET | `/pages/estoque/{id}/editar` | `viewAdicionar($id)` | Formulário de edição |

### API (protegidas com `api_csrf`, `rate_limit`, `throttle`)

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/api/estoque` | `index()` | Lista paginada com filtros |
| GET | `/api/estoque/buscar` | `buscar()` | Busca para selects (limit 50) |
| GET | `/api/estoque/{id}` | `show()` | Detalhe de um produto |

**Query params de `/api/estoque`:** `page`, `perPage`, `search`, `filial`, `status`

**Query params de `/api/estoque/buscar`:** `q` (termo de busca)

### POST (protegidas com `csrf`, `rate_limit`)

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| POST | `/estoque/salvar` | `store()` | Criar produto |
| POST | `/estoque/{id}/atualizar` | `update()` | Editar produto |
| POST | `/estoque/{id}/excluir` | `destroy()` | Excluir/inativar produto |
| POST | `/estoque/{id}/reativar` | `reativar()` | Reativar produto inativo |

## Funcionalidades CRUD

### Campos obrigatórios
`produto_codigo`, `produto_nome`, `produto_marca`, `produto_modelo`, `produto_unidade`, `produto_estoque_atual`, `valor_compra`, `id_matriz_filial`

### Busca e filtros
- Busca textual em: `produto_codigo`, `produto_nome`, `produto_marca`, `produto_modelo`
- Filtro por filial (`id_matriz_filial`)
- Filtro por status (`A` ou `I`)
- Paginação server-side (max 100 por página)

### Exclusão vs Inativação
- **Sem vínculos:** produto é excluído permanentemente (`DELETE`)
- **Com vínculos em `manutencoes_itens`:** produto é **inativado** (`status = 'I'`), nunca excluído
- Produtos já inativos não podem ser inativados novamente (retorna erro)

### Reativação
Produtos inativos podem ser reativados via `POST /estoque/{id}/reativar`, voltando `status = 'A'`.

### Endpoint `/api/estoque/buscar`
Usado por selects em outros módulos (ex: tela de manutenções). Retorna dados formatados para Chosen:
```json
{
  "id": 1,
  "text": "COD001 - Óleo Motor 5W30",
  "unidade": "L",
  "valor_venda": 45.00,
  "estoque_atual": 12,
  "baixa_automatica": "S",
  "permitir_estoque_negativo": "N"
}
```

## Baixa Automática

### Configuração
Campo `baixa_automatica` por produto (`S`/`N`, default `N`). Quando ativo, o estoque é decrementado/incrementado automaticamente ao manipular itens de OS.

### Fluxo
A baixa ocorre em `ManutencaoItem::ajustarEstoque()`, que é chamado em três gatilhos:

| Gatilho | Operação | Descrição |
|---------|----------|-----------|
| `criar()` — novo item na OS | `baixa` | Subtrai `quantidade` do estoque |
| `atualizar()` — mudança de quantidade | `baixa` ou `repor` | Ajusta pela diferença (aumento = baixa, redução = reposição) |
| `deletar()` — remoção do item | `repor` | Devolve `quantidade` ao estoque |

### UPDATE Atômico
Para evitar race conditions, o ajuste usa prepared statement direto:
```sql
-- Baixa
UPDATE estoque SET produto_estoque_atual = produto_estoque_atual - ? WHERE id = ?

-- Reposição
UPDATE estoque SET produto_estoque_atual = produto_estoque_atual + ? WHERE id = ?
```

### Regras
- Só ajusta se `baixa_automatica = 'S'` no produto
- Quantidade ≤ 0 é ignorada
- Itens pagos (`pago = 'S'`) **não podem** ser editados nem deletados — logo não geram ajuste de estoque

## Permitir Estoque Negativo

### Configuração
Campo `permitir_estoque_negativo` por produto (`S`/`N`, default `N`).

### Comportamento

| Config | Seleção do produto | Quantidade |
|--------|--------------------|------------|
| `N` | Bloqueada se `estoque_atual ≤ 0` | Limitada ao disponível |
| `S` | Sempre permitida | Sem restrição |

### Validação
A validação é feita **apenas no frontend** (JavaScript na view de manutenções), usando os dados retornados por `/api/estoque/buscar`.

## Indicadores Visuais de Estoque

| Cor | Condição | Significado |
|-----|----------|-------------|
| Vermelho | `estoque_atual ≤ 0` | Sem estoque |
| Âmbar | `estoque_atual > 0` e `≤ estoque_minimo` | Estoque baixo |
| Verde | `estoque_atual > estoque_minimo` | Estoque OK |

**Nota:** `produto_estoque_minimo = 0` desativa o alerta âmbar (só vermelho e verde).

## Integração com Manutenções

O módulo de estoque se integra com Ordens de Serviço (OS) via `manutencoes_itens`:

1. Ao adicionar item a uma OS, o usuário pode selecionar um produto do estoque ou informar uma descrição manual
2. Itens manuais são salvos com `id_estoque = NULL` e não movimentam estoque
3. Se houver produto selecionado e `baixa_automatica = 'S'`, o estoque é decrementado atomicamente
4. Ao remover ou alterar quantidade de item vinculado ao estoque, o estoque é ajustado proporcionalmente
5. A baixa **não ocorre no CRON** — ocorre em tempo real via `ManutencaoItem`

Ver também: [Manutenção Preventiva](preventive-maintenance.md)

## Auditoria

O Model `Estoque` usa o trait `Auditable`:
- Entidade: `'Estoque'`
- Identificador: `produto_nome`
- Operações logadas: criar, editar, excluir, inativar, reativar

## Arquivos de Referência

| Tipo | Arquivo |
|------|---------|
| Model | `app/Models/Estoque.php` |
| Controller | `app/Controllers/EstoqueController.php` |
| ManutencaoItem | `app/Models/ManutencaoItem.php` (método `ajustarEstoque`) |
| View Lista | `app/Views/pages/estoque/index.php` |
| View Form | `app/Views/pages/estoque/adicionar.php` |
| Rotas | `app/Routes/web.php` (linhas ~762-776) |
| Traduções | `app/Lang/{locale}/modules/estoque.php` |

### Migrations Relevantes

| Migration | Descrição |
|-----------|-----------|
| `00038_standardize_estoque_columns.php` | Padronização de colunas |
| `00280_add_status_to_estoque.php` | Campo `status` (A/I) |
| `00282_add_baixa_automatica_to_estoque.php` | Campo `baixa_automatica` |
| `00283_add_permitir_estoque_negativo_to_estoque.php` | Campo `permitir_estoque_negativo` |
