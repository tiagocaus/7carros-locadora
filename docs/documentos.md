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
| `0` | Contrato/Locação | **Legado**. Disponível tanto na impressão de **contratos** quanto **locações** para documentos antigos ainda não reclassificados |
| `1` | Contrato | Apenas na impressão de **contratos** |
| `2` | Locação | Apenas na impressão de **locações** |
| `3` | Multa | Apenas na impressão de **multas** |

O valor `0` permanece suportado para leitura, listagem, filtros e impressão de documentos existentes, mas não deve ser usado em novos cadastros. A tela de adicionar/editar documentos exibe apenas `Contrato`, `Locação` e `Multa`; ao editar um documento legado, o salvamento reclassifica o documento para um desses tipos ativos.

### Histórico

- **2026-06-24**: tipo `0` mantido apenas como legado. A tela de adicionar/editar passou a permitir somente `1=Contrato`, `2=Locação` e `3=Multa`; registros antigos `0` continuam aparecendo nos fluxos de contrato e locação até serem editados/reclassificados.
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

public const TIPOS_FORM = [
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

- Form com título, tipo (`<select>` populado a partir de `Documento::TIPOS_FORM` via Controller), status, e editor TinyMCE para o conteúdo HTML
- O tipo legado `0=Contrato/Locação` não aparece no formulário. Ao editar um documento legado, o usuário escolhe `Contrato`, `Locação` ou `Multa`, e o documento passa ao novo padrão no salvamento.
- O editor TinyMCE permite ajustar tamanho da fonte em `pt`, preservando o estilo inline no HTML salvo em `documentos.texto`
- Painel lateral com lista de variáveis disponíveis (insere placeholders no editor)

## Variáveis dinâmicas (placeholders)

O conteúdo do documento aceita placeholders `{{cliente.nome}}`, `{{empresa.cnpj}}`, `{{contrato.valor.parcela}}`, etc. Nos três fluxos, antes do PDF o `App\I18n\TemplateRenderer` substitui os placeholders: contratos/locações montam o contexto nos respectivos controllers; multas usam `MultasController::buildDocumentoContextMulta()` em `imprimir` e em `enviarMulta`.

No fluxo de locações, `LocacoesController::buildDocumentoContext()` alimenta variáveis como `{{locacao.data_retirada}}`, `{{locacao.valor_total}}`, `{{locacao.km_saida}}`, `{{locacao.tanque_saida}}` e `{{locacao.bloqueio_valor}}` a partir dos dados da locação e do último vínculo de veículo, com fallback para campos legados quando necessário. Os campos de tanque (`{{locacao.tanque_saida}}` e `{{locacao.tanque_chegada}}`) são exibidos como frações legíveis (`Reserva`, `1/2`, `Cheio`, etc.). Para caução em locações, `{{locacao.caucao_valor}}` e `{{locacao.deposito_valor}}` apontam para o mesmo valor de garantia.

No fluxo de contratos, `ContratosController::buildDocumentoContext()` alimenta os campos escalares do veículo ativo principal: `{{contrato.km_saida}}`, `{{contrato.km_chegada}}`, `{{contrato.tanque_saida}}` e `{{contrato.tanque_chegada}}`. Para dados cadastrais do veículo, use variáveis como `{{veiculo.combustivel_tipo}}` e `{{veiculo.valor_compra}}`; o valor de compra vem de `veiculos.valor_compra` e é formatado como moeda pelo `TemplateRenderer`. Para garantias em contratos, use `{{contrato.caucao_valor}}`, `{{contrato.deposito_valor}}`, `{{contrato.caucao_status}}`, `{{contrato.caucao_data_devolucao}}`, `{{contrato.caucao_prazo_devolucao}}`, `{{contrato.caucao_data_prevista_devolucao}}`, `{{contrato.bloqueio_valor}}`, `{{contrato.bloqueio_status}}`, `{{contrato.bloqueio_valor_capturado}}` e `{{contrato.bloqueio_expira_em}}`.

`{{contrato.contagem}}` exibe a unidade localizada no formato singular/plural entre parênteses (`dia(s)`, `semana(s)`, `mês(es)` ou `ano(s)` em pt_BR). Quando `impressao_variavel_negrito = 'S'` na matriz/filial usada na impressão, os valores substituídos ficam em negrito. Variáveis que geram tabelas preservam o corpo com peso normal e mantêm somente os cabeçalhos em negrito.

Variável especial para contratos com múltiplos veículos:

| Variável | Uso |
|---|---|
| `{{contrato.veiculos_anexo}}` | Tabela HTML completa para anexo contratual, com identificação do veículo, fornecedor/investidor, plano, valores, seguros, odômetro e combustível/carga de saída |
| `{{contrato.parcelas_tabela}}` | Tabela HTML das parcelas financeiras com as colunas Parcela, Vencimento e Valor |
| `{{contrato.parcelas_tabela_status}}` | Tabela HTML das parcelas financeiras com as colunas Parcela, Vencimento, Valor e Status |
| `{{contrato.valor.parcela}}` | Valor mais comum entre as parcelas financeiras do contrato, formatado como moeda. Em empate, usa o primeiro valor encontrado na ordem das parcelas |
| `{{contrato.comando_parcela}}` | Condição de parcelamento em texto amigável, sem expor códigos técnicos como `w4-Seg` ou `d15` |

`{{contrato.comando_parcela}}` interpreta o comando vinculado ao contrato. Comandos semanais sem dia retornam `semanal`; comandos semanais com dia retornam o dia no plural (`segundas-feiras`); um dia isolado retorna o dia no singular (`terça-feira`). Prazos, faixas mensais e listas de vencimentos são descritos por extenso. Contratos sem comando retornam `Não informado`; formatos desconhecidos usam a descrição cadastrada e nunca exibem o código técnico como fallback.

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
