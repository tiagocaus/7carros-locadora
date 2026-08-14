# Guia Tecnico: Desenvolvimento de Relatorios

Documentacao tecnica para criar novos relatorios no sistema. Para a especificacao funcional (o que cada relatorio deve exibir), consulte `relatorios.md`.

---

## Arquitetura

Cada categoria de relatorios (KPIs, Financeiro, Veicular, etc.) segue a estrutura:

```
Controller (1 por categoria)  →  Model (1 por categoria)  →  View (1 por relatorio)
     ↓                              ↓                           ↓
BaseRelatorioController        BaseReportModel              Partials compartilhados
     ↓                                                         ↓
  Metodo PDF  →  Template PDF  →  PdfHelper::outputInline()
```

### Camadas

| Camada | Responsabilidade | Exemplo |
|--------|-----------------|---------|
| **Controller** | Permissoes, filtros, resposta JSON/PDF | `KpisController` |
| **Model** | Queries de agregacao, calculos | `KpiReport` |
| **View** | HTML da pagina (iframe), filtros, tabela, graficos | `kpis/taxa-ocupacao.php` |
| **JS** | AJAX, Chart.js, interacao | `report-utils.js` + inline |
| **Template PDF** | HTML para mPDF (tabelas, sem flexbox) | `imprimir/kpis/taxa-ocupacao.php` |
| **i18n** | Traducoes | `lang/pt_BR/modules/relatorios.php` |

---

## Estrutura de Diretorios

```
app/
├── Controllers/Relatorios/
│   ├── BaseRelatorioController.php    # Classe abstrata compartilhada
│   ├── KpisController.php            # 8 relatorios KPI
│   ├── FinanceiroController.php      # (futuro)
│   └── ...
├── Models/Relatorios/
│   ├── BaseReportModel.php            # Helpers: pct(), safeDivide(), daysBetween()
│   ├── KpiReport.php                  # Queries de agregacao KPIs
│   └── ...
├── Views/pages/relatorios/
│   ├── _partials/
│   │   ├── filters.php                # Barra de filtros (periodo + filial + grupo)
│   │   ├── totalizadores.php          # Container para KPI cards
│   │   ├── export-buttons.php         # Botao Exportar PDF
│   │   ├── pagination.php             # Controles de paginacao
│   │   └── empty-state.php            # Estado vazio / instrucao inicial
│   ├── kpis/
│   │   ├── taxa-ocupacao.php          # View do relatorio
│   │   └── ...
│   └── imprimir/kpis/
│       ├── _css.php                   # CSS base para PDFs
│       ├── _header.php                # Cabecalho PDF (logo, titulo, periodo)
│       ├── _footer.php                # Rodape PDF (gerado por, data)
│       ├── taxa-ocupacao.php          # Template PDF
│       └── ...
├── lang/pt_BR/modules/
│   └── relatorios.php                 # Traducoes
└── Routes/web.php                     # Rotas (view + API + PDF)

public/assets/js/
├── report-utils.js                    # Helpers JS compartilhados
└── report-utils.min.js                # Versao minificada
```

---

## Como Criar um Novo Relatorio

### Checklist

1. **Model** — Adicionar metodo publico no model da categoria (ex: `KpiReport::meuRelatorio()`)
   - Retornar `['totals' => [...], 'details' => [...], 'chart' => [...]]`
   - Usar auto-filter do QueryBuilder (NAO usar `withoutChave()`)
   - Usar `$this->pct()`, `$this->safeDivide()`, `$this->daysBetween()`

2. **Controller** — Adicionar 3 metodos no controller da categoria:
   - `viewMeuRelatorio()` — renderiza a view via `Template::render()`
   - `meuRelatorio()` — API JSON com dados
   - `meuRelatorioPdf()` — gera PDF via `renderPdf()`

3. **View** — Criar arquivo em `Views/pages/relatorios/{categoria}/{slug}.php`
   - Usar `@extends('layouts.iframe')` e `@include` para partials
   - Conectar `btnExportPdf` via `ReportUtils.exportPdf()`
   - Usar `Currency.format()` para moeda e `DateHelper.format()` para datas

4. **Template PDF** — Criar arquivo em `Views/pages/relatorios/imprimir/{categoria}/{slug}.php`
   - Incluir `_css.php`, `_header.php`, `_footer.php`
   - Usar tabelas HTML (mPDF nao suporta flexbox/grid)
   - Usar `currency_format()` (PHP) para moeda e `format_date()` para datas

5. **Rotas** — Adicionar 3 rotas em `web.php`:
   ```php
   $router->get('/pages/relatorios/{cat}/{slug}', [Controller::class, 'viewMethod']);
   $router->get('/api/relatorios/{cat}/{slug}', [Controller::class, 'apiMethod'], ['api_csrf', 'rate_limit', 'throttle']);
   $router->get('/relatorios/{cat}/{slug}/pdf', [Controller::class, 'pdfMethod']);
   ```

6. **Navbar** — Atualizar link em `Views/partials/navbar.php`:
   ```php
   <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/{cat}/{slug}', 'Titulo', 'fas fa-icon'); return false;">
   ```

7. **Permissoes** — Criar migration:
   - Inserir em `permissions` com key `relatorios.{cat}.{slug}`
   - Atribuir a Proprietario e Gerente via `funcionarios_role_permissions`

8. **i18n** — Adicionar traducoes em `lang/pt_BR/modules/relatorios.php`:
   - `title`, `description`, campos de totalizadores, colunas da tabela

9. **Minificar** — Se editou `report-utils.js`:
   ```bash
   npx terser public/assets/js/report-utils.js -o public/assets/js/report-utils.min.js --compress --mangle
   ```

---

## Padroes Obrigatorios

### Relatórios pelo regime de caixa

O relatório Financeiro > Resultado Gerencial por Caixa usa `pago = 'S'` e
`data_pago` como critérios obrigatórios. Não use `data_criada` ou `data_venci`
como fallback para lançamentos pagos sem data: esses registros devem ser
sinalizados como não alocados. O filtro automático de tenant e o filtro de
filial aplicam-se tanto aos totais quanto ao diagnóstico de dados incompletos.

### QueryBuilder
- **NUNCA** usar `withoutChave()` — o auto-filter cuida do multi-tenancy
- Unico caso para `$chave` explicito: subqueries dentro de `selectRaw()` (auto-filter nao atua em subqueries)
- Usar `whereRaw()` com `?` para parametros nos `WHERE`
- **NUNCA** usar `?` dentro de `selectRaw()` — interpolar valores validados

### Contrato Model ↔ View (CRITICO)

O erro mais comum em relatorios e o desalinhamento entre as chaves que o Model retorna (PHP) e as que a View consome (JavaScript). Antes de finalizar qualquer relatorio, verificar:

1. **Ler a View JS primeiro** — Abrir a View e identificar todos os `row.campo` usados em `renderTable()` e `chartData.campo` usados em `renderChart()`. As chaves no `array_map` do Model devem ser **identicas** a esses nomes
2. **Chart: conferir a estrutura esperada** — Verificar se a View le `chartData.datasets` (Chart.js padrao) ou chaves diretas como `chartData.receitas`. O Model deve retornar na estrutura que a View consome, nao assumir uma das duas
3. **Enums: mapear valores do BD para a View** — Se a View compara strings legiveis (ex: `=== 'pago'`), o Model deve converter valores do banco (ex: `'S'`/`'N'`) para as strings esperadas pela View
4. **Traducoes: usar caminho completo** — Toda chave `t()` usada no Model deve apontar para uma string folha no array de traducoes, nunca para um array intermediario. Se `t()` retorna a propria chave, esta errado
5. **Pie/doughnut charts: usar `maintainAspectRatio: true`** — Graficos circulares nao renderizam com `maintainAspectRatio: false` em containers com largura limitada. Apenas `bar`/`line` podem usar `false`
6. **GROUP BY: tratar NULLs com COALESCE** — Se os totais (cards) nao filtram por um campo, o details tambem nao deve filtrar via `whereNotNull()`, senao cards e tabela ficam divergentes. Usar `COALESCE(campo, 'Outros')` para agrupar registros sem dimensao

### Formatacao (JavaScript)
- Moeda: `Currency.format(valor, true)` — NAO usar `currency_format()` ou `toFixed()`
- Data: `DateHelper.format(data)` — NAO usar formatacao manual
- Data/hora: `DateHelper.formatDateTime(data)`
- Numeros: `Number(v).toLocaleString((window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-'))`

### Formatacao (PHP / Templates PDF)
- Moeda: `currency_format($valor)` — funcao global do helpers.php
- Data: `format_date($data)` — funcao global
- Data/hora tecnica: `format_datetime($data)` — funcao global com conversao de timezone
- Data/hora operacional: `format_operational_datetime($data)` — retirada/devolucao, checklist, multa, agenda e manutencao sem conversao de timezone

### Views
- Usar `@include('pages.relatorios._partials.X')` — NAO usar `include __DIR__`
- Usar `@extends('layouts.iframe')`
- Titulo + descricao fora do `<div class="flex">`

### Templates PDF
- Usar `include __DIR__ . '/_partial.php'` (PDF nao passa pelo Template engine)
- Usar tabelas HTML, NAO flexbox/grid
- Nos Controllers de relatorios, usar `resolveReportPdfCompany()` para preparar `$empresa` e `outputReportPdf()` para exibir no modal
- Nao assumir que todo funcionario possui `id_matriz_filial`; o fallback deve continuar tenant-scoped
- Output buffering: `ob_start()` → `include` → `ob_get_clean()`

---

## Componentes Reutilizaveis

### BaseRelatorioController

| Metodo | Funcao |
|--------|--------|
| `parseFilters(Request)` | Extrai data_inicio, data_fim, filial |
| `validatePeriodo(inicio, fim)` | Valida datas (max 2 anos) |
| `getFilialFilter()` | Retorna FilialHelper::whereFiliais() |
| `checkPermission(key)` | Verifica Auth::can(), retorna 403 |
| `validateFilialAccess(id)` | Verifica acesso a filial especifica |
| `resolveReportPdfCompany(user)` | Resolve a unidade do cabecalho; sem filial na sessao, usa a matriz ou a primeira unidade do tenant |
| `outputReportPdf(html, filename, options, context)` | Gera o PDF inline e registra contexto tecnico em caso de falha |
| `reportResponse(data, totals, chart)` | JSON padronizado |
| `reportPaginatedResponse(...)` | JSON com paginacao |

### BaseReportModel

| Metodo | Funcao |
|--------|--------|
| `pct(part, total, decimals)` | Percentual seguro (evita divisao por zero) |
| `safeDivide(num, den, decimals)` | Divisao segura |
| `daysBetween(start, end)` | Diferenca em dias |

### report-utils.js (ReportUtils)

| Metodo | Funcao |
|--------|--------|
| `setDefaultPeriod()` | Define periodo padrao (mes atual) |
| `loadFiliais(selectId)` | Carrega filiais no dropdown |
| `loadGrupos(selectId)` | Carrega grupos no dropdown |
| `buildTotalCards(totals, config)` | Renderiza cards KPI |
| `getOccupancyColor(taxa)` | Classes CSS por faixa de ocupacao |
| `showLoading()` / `showError()` | Estados de loading/erro |
| `showContent()` / `hideContent()` | Toggle visibilidade |
| `renderPagination(pagination, cb)` | Controles de paginacao |
| `exportPdf(url, title)` | Abre PDF no modal de impressao |
| `COLORS` / `COLORS_ALPHA` | Paleta para Chart.js |

### Padrao de Datas

Relatorios devem usar `DateHelper`/helpers globais em filtros padrao, exibicao, PDF e calculos de periodo. Em JS, use `DateHelper.todayISO()`, `DateHelper.startOfCurrentMonthISO()`, `DateHelper.endOfCurrentMonthISO()`, `DateHelper.format()` e `DateHelper.formatDateTime()` para instantes tecnicos. Para horarios operacionais, use `DateHelper.formatOperationalDateTime()`. Em PHP, use `today()`, `now()`, `format_date()`, `format_datetime()`, `format_operational_datetime()` e os metodos de soma do `DateHelper`.

Nao introduza `date()`, `time()`, `new DateTime()`, `new Date()` ou `NOW()/CURDATE()` em relatorios novos. Consultas legadas com `DATEDIFF`, `DATE_ADD` e `DATE_SUB` devem ser migradas para parametros calculados no helper em etapa propria, mantendo a semantica original.

### Partials (Views)

| Partial | Props | Funcao |
|---------|-------|--------|
| `filters.php` | `showGrupoFilter` (bool), `extraFiltersAfterFilial` (HTML opcional), `extraFiltersAfterFilialView` (partial opcional) | Barra de filtros |
| `totalizadores.php` | — | Container `#reportTotals` |
| `export-buttons.php` | — | Botao PDF `#btnExportPdf` |
| `pagination.php` | — | Paginacao `#reportPagination` |
| `empty-state.php` | — | Estado vazio `#reportEmptyState` |

---

## Fluxo de Dados

```
1. Usuario seleciona filtros e clica "Aplicar"
2. JS faz API.get('/api/relatorios/{cat}/{slug}', params)
3. Controller verifica permissao e valida filtros
4. Controller chama Model para buscar dados
5. Model executa queries (auto-filter por chave)
6. Controller retorna JSON: { success, data, totals, chart, pagination }
7. JS renderiza: cards (totals) + grafico (chart) + tabela (data)

PDF:
1. Usuario clica "Exportar PDF"
2. JS envia postMessage { action: 'openPrintModal', url, title }
3. Parent abre modal fullscreen com iframe
4. Iframe carrega rota PDF
5. Controller busca dados + ob_start() + include template + ob_get_clean()
6. PdfHelper::outputInline() gera PDF inline no iframe
```

---

## Permissoes

### Padrao de nomenclatura
```
relatorios.{categoria}.{nome_do_relatorio}
```

Exemplos:
- `relatorios.kpis.taxa_ocupacao`
- `relatorios.financeiro.movimentacoes`
- `relatorios.veicular.manutencoes`

### Migration padrao
```php
// 1. Inserir permissao
$this->db()->table('permissions')->insert([
    'key' => 'relatorios.cat.nome',
    'name' => 'Relatorio Nome',
    'description' => 'Descricao',
    'module' => 'relatorios',
]);

// 2. Atribuir a Proprietario e Gerente
$roles = $this->db()->table('funcionarios_roles')
    ->where('name', '=', $roleName)->get();
// INSERT IGNORE INTO funcionarios_role_permissions ...
```
