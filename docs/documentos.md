# Documentos (modelos)

Modelos customizáveis de documentos por tenant — usados como PDF anexo em contratos, locações e multas. Cada modelo tem título, conteúdo HTML rico, tipo (qual fluxo usa) e status (ativo/inativo).

Modelos padrão do sistema usam `chave = '0'` e aparecem junto com os documentos do tenant. Ao editar um modelo padrão, o sistema cria uma cópia com a `chave` do tenant e preserva o original global.
Modelos padrão do sistema não podem ser excluídos por usuários; a interface oculta o botão de exclusão e o backend bloqueia a tentativa por API.

## Tabela `documentos`

```sql
CREATE TABLE documentos (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    chave        VARCHAR(45) NOT NULL,           -- multi-tenancy
    titulo       VARCHAR(100) NOT NULL,
    texto        LONGTEXT     NOT NULL,          -- HTML rico do TinyMCE
    tipo         TINYINT(1)   NOT NULL DEFAULT 0
                 COMMENT '[0]Contrato/Locação [1]Contrato [2]Locação [3]Multa',
    status       INT(1)       NOT NULL,          -- 0=Inativo, 1=Ativo
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

## A coluna `tipo`

| Valor | Rótulo | Onde aparece |
|---|---|---|
| `0` | Contrato/Locação | Disponível tanto na impressão de **contratos** quanto **locações** (compartilhado) |
| `1` | Contrato | Apenas na impressão de **contratos** |
| `2` | Locação | Apenas na impressão de **locações** |
| `3` | Multa | Apenas na impressão de **multas** |

### Histórico

- **2026-04-30**: rótulo `0` renomeado de "Ambos" para "Contrato/Locação" (a palavra "Ambos" virou ambígua quando entrou o tipo Multa). Valor numérico inalterado — apenas mudança de label nas 5 línguas.
- **2026-04-30**: adicionado `3` (Multa) para suportar a nova feature de impressão do módulo Multas.
- A migration `00169_alter_documentos_tipo.php` faz o remapeamento dos valores legados (sistema antigo usava `1=Ambos, 2=Contrato, 3=Locação, 4=Multas` → novo `0/1/2/3`).

## Model `app/Models/Documento.php`

```php
public const TIPOS = [
    0 => 'Contrato/Locação',
    1 => 'Contrato',
    2 => 'Locação',
    3 => 'Multa',
];

public const STATUS = [
    0 => 'Inativo',
    1 => 'Ativo',
];
```

Métodos principais:

| Método | Função |
|---|---|
| `buscarPorId(int $id)` | Busca completa de um modelo |
| `listar(...)`, `listarPaginado(...)`, `contar(...)` | CRUD de listagem com filtros opcionais (`search`, `tipo`, `status`) |
| `listarParaSelect($search, $tipo)` | Lista enxuta para popular `<select>` (apenas ativos, incluindo globais `chave = '0'`) |
| `criar($dados)`, `atualizar($id, $dados)`, `excluir($id)` | CRUD |
| `criarCopiaTenant($documentoGlobal, $chave, $dados)` | Copia um modelo global para o tenant antes da edição |
| `getNomeTipo(int $tipo): string` | Resolve label do tipo (pt_BR fixo, não i18n por enquanto) |

## Onde cada tipo é usado

```
ContratosController::offcanvasImpressao        →  filtra documentos com tipo IN (0, 1)
LocacoesController::offcanvasImpressao         →  filtra documentos com tipo IN (0, 2)
MultasController::offcanvasImpressao           →  filtra documentos com tipo === 3
```

A filtragem ocorre via `array_filter` sobre o resultado de `listarParaSelect()`. Se o tenant não tem modelo próprio, modelos globais (`chave = '0'`) do tipo apropriado podem aparecer como padrão do sistema; se não houver tenant nem global aplicável, o select aparece vazio com aviso.

## Telas

### Listagem `/pages/documentos` (`app/Views/pages/documentos/index.php`)

- Filtros: tipo (4 opções + "Todos") e busca por título
- Tabela: título, tipo (badge colorido), status, atualizado em, ações (editar/excluir)
- JS `tipoLabels` mapeia `0→roxo`, `1→azul`, `2→verde`, `3→vermelho` (badges)

### Adicionar/Editar `/pages/documentos/adicionar` (`adicionar.php`)

- Form com título, tipo (`<select>` populado a partir de `Documento::TIPOS` via Controller), status, e editor TinyMCE para o conteúdo HTML
- Painel lateral com lista de variáveis disponíveis (insere placeholders no editor)

## Variáveis dinâmicas (placeholders)

O conteúdo do documento aceita placeholders `{{cliente.nome}}`, `{{empresa.cnpj}}`, etc. Nos três fluxos, antes do PDF o `App\I18n\TemplateRenderer` substitui os placeholders: contratos/locações montam o contexto nos respectivos controllers; multas usam `MultasController::buildDocumentoContextMulta()` em `imprimir` e em `enviarMulta`.

Variável especial para contratos com múltiplos veículos:

| Variável | Uso |
|---|---|
| `{{contrato.veiculos_anexo}}` | Tabela HTML completa para anexo contratual, com identificação do veículo, fornecedor/investidor, plano, valores, seguros, odômetro e combustível/carga de saída |

O modelo padrão global de contrato usa `{{contrato.veiculos_anexo}}` em vez de `{{contrato.veiculos_tabela}}`, porque o anexo é mais completo para contratos com múltiplos veículos e veículos de terceiros/investidores.

Modelos globais padrão criados por migration:

| Título | Tipo |
|---|---|
| `Contrato de Locacao de Veiculo(s) - Padrao do Sistema` | `1` Contrato |
| `Termo de Locacao de Veiculo - Padrao do Sistema` | `2` Locação |

Para **layout** do PDF tipo documento (cabeçalho/rodapé HTML e margens do corpo), ver [Geração de PDF](./pdf.md) (secção *Cabeçalhos e rodapés HTML*).

## Permissões

| Chave | Descrição |
|---|---|
| `documentos.visualizar` | Listar e abrir |
| `documentos.criar` | Adicionar |
| `documentos.editar` | Editar |
| `documentos.excluir` | Excluir |

Inseridas pela migration `00170_add_documentos_permissions.php`. Atribuídas automaticamente à role "Proprietário".

## i18n

`app/Lang/{pt_BR,pt_PT,en_US,es_ES,it_IT}/modules/documentos.php`. Estrutura:
- `title`, `title_singular`, `new_title`, `edit_title`
- `filters.{all,both,contract,rental,fine}` — labels do filtro de tipo
- `badges.{type_both,type_contract,type_rental,type_fine,status_active,status_inactive}` — badges da listagem
- `table.*`, `fields.*`, `placeholders.*`, `messages.*`, `pagination.*`

## Documentação relacionada

- [Contratos](./contratos.md) — usa modelos tipo 0 e 1
- [Locações](./locacoes.md) — usa modelos tipo 0 e 2
- [Multas](./multas.md) — usa modelos tipo 3
- [Geração de PDF](./pdf.md) — como o conteúdo HTML vira PDF
- [i18n](./i18n.md) — sistema de tradução e variáveis
