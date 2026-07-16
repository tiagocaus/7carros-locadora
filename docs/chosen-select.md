# Chosen Select - Componente de Select com Busca

## Visão Geral

O **Chosen Select** é um componente JavaScript que transforma selects HTML nativos em selects customizados com funcionalidade de busca. Suporta dois modos de operação: client-side (normal) e server-side.

**Localização:** `public/assets/js/chosen-select.js`

**Segurança/privacidade:** o input interno de busca do componente força `autocomplete="off"`, `autocorrect="off"`, `autocapitalize="off"` e `spellcheck="false"` para evitar sugestões do navegador em buscas de clientes, veículos, funcionários e outros dados internos.

---

## Tipos de Select

### 1. Normal (Client-Side)
Carrega todas as opções do select HTML e filtra localmente.

```html
<select class="chosen-select">
    <option value="">Selecione...</option>
    <option value="1">Opção 1</option>
    <option value="2">Opção 2</option>
</select>
```

### 2. Server-Side
Carrega automaticamente até 50 registros ao abrir o dropdown. Busca filtrada requer mínimo de 3 caracteres.

```html
<select class="chosen-select"
        data-chosen-type="server-side"
        data-chosen-search-url="/api/endpoint"
        data-chosen-placeholder="Digite para buscar...">
    <option value="">Selecione...</option>
</select>
```

#### Comportamento:
- **Ao abrir**: Carrega 50 registros automaticamente (preload)
- **0 caracteres**: Mostra registros do preload
- **1-2 caracteres**: Mostra mensagem "Digite pelo menos 3 letras..."
- **3+ caracteres**: Faz busca filtrada no servidor
- **Reabrir**: Recarrega do servidor (pega novos cadastros)

---

## Atributos HTML (data-*)

| Atributo | Tipo | Padrão | Descrição |
|----------|------|--------|-----------|
| `data-chosen-type` | `'normal'` \| `'server-side'` | `'normal'` | Modo de operação |
| `data-chosen-search-url` | `string` | `null` | URL da API para busca server-side |
| `data-chosen-min-chars` | `number` | `3` | Mínimo de caracteres para iniciar busca |
| `data-chosen-placement` | `'auto'` \| `'bottom'` | `'auto'` | Posicionamento do dropdown; `bottom` mantém a lista imediatamente abaixo do select |
| `data-chosen-placeholder` | `string` | `'Selecione uma opção...'` | Texto quando nenhuma opção selecionada |
| `data-chosen-no-results` | `string` | `'Nenhum resultado encontrado'` | Texto quando não há resultados |
| `data-chosen-min-chars-text` | `string` | `'Digite pelo menos {min} letras para buscar...'` | Texto de instrução |
| `data-chosen-allow-clear` | `boolean` | `true` | Exibe botão "×" para limpar seleção |

---

## Formato de Resposta da API (Server-Side)

A API deve retornar JSON no formato:

```json
{
    "success": true,
    "data": [
        { "id": 1, "text": "Opção 1" },
        { "id": 2, "text": "Opção 2" }
    ]
}
```

Ou array direto:

```json
[
    { "id": 1, "text": "Opção 1" },
    { "id": 2, "text": "Opção 2" }
]
```

### Campos Suportados

O componente extrai automaticamente os valores dos seguintes campos:

#### Para `value` (ID da opção):
1. `value`
2. `id`
3. índice do array (fallback)

#### Para `text` (Texto exibido):
1. `text`
2. `name`
3. `nome` (português)
4. `label`
5. `String(item)` (fallback)

**Exemplo de respostas válidas:**
```json
// Formato recomendado
{ "id": 1, "text": "Cliente ABC" }

// Alternativas válidas
{ "id": 1, "name": "Cliente ABC" }
{ "id": 1, "nome": "Cliente ABC" }
{ "value": 1, "label": "Cliente ABC" }
```

---

## Exemplos de Uso

### Select Normal com Opções Estáticas
```html
<select id="status" name="status" class="chosen-select form-input-group-field">
    <option value="">Selecione...</option>
    <option value="A">Ativo</option>
    <option value="I">Inativo</option>
</select>
```

### Select Server-Side para Clientes
```html
<select id="cliente" name="id_cliente" class="chosen-select form-input-group-field"
        data-chosen-type="server-side"
        data-chosen-search-url="/api/clientes/buscar"
        data-chosen-placeholder="Digite o nome do cliente...">
    <option value="">Selecione...</option>
</select>
```

### Select Server-Side com Mínimo de Caracteres Customizado
```html
<select id="produto" name="id_produto" class="chosen-select"
        data-chosen-type="server-side"
        data-chosen-search-url="/api/produtos"
        data-chosen-min-chars="2"
        data-chosen-placeholder="Buscar produto..."
        data-chosen-no-results="Produto não encontrado">
    <option value="">Selecione...</option>
</select>
```

---

## API JavaScript

### Inicialização Automática
Selects com classe `chosen-select` são inicializados automaticamente no DOMContentLoaded.

### Inicialização Manual
```javascript
// Reinicializar todos os selects (útil após carregar conteúdo dinâmico)
window.initChosenSelects();

// Instanciar manualmente
const select = document.getElementById('meuSelect');
const chosen = new ChosenSelect(select, {
    type: 'server-side',
    searchUrl: '/api/endpoint',
    minChars: 2,
    placement: 'bottom',
    placeholder: 'Buscar...'
});
```

### Métodos Disponíveis

| Método | Descrição |
|--------|-----------|
| `refresh()` | Recarrega opções do select original. Útil quando opções são adicionadas dinamicamente |
| `open()` | Abre o dropdown |
| `close()` | Fecha o dropdown |
| `clear()` | Limpa a seleção atual e dispara evento `change` |
| `destroy()` | Remove o componente e restaura o select original |

### Acessando a Instância
```javascript
// Via elemento select
const chosenInstance = document.getElementById('meuSelect').chosenSelect;
chosenInstance.refresh();

// Via container
const container = document.querySelector('.chosen-select-container');
container.chosenSelect.close();
```

---

## Implementando Endpoint Server-Side

### Controller (PHP)
```php
public function buscarClientes(Request $request): void
{
    $search = $request->query('q', '');
    $chave = Auth::chave();

    $model = new Cliente();
    $clientes = $model->listarParaSelect($chave, $search);

    Response::json([
        'success' => true,
        'data' => $clientes
    ]);
}
```

### Model (PHP)
```php
public function listarParaSelect(string $chave, string $search = ''): array
{
    $query = $this->qb
        ->table('clientes')
        ->select(['id', 'nome_rsocial AS nome'])  // 'nome' é suportado
        ->where('situacao', '=', 'A');

    if (!empty($search)) {
        $query->where('nome_rsocial', 'LIKE', "%{$search}%");
    }

    return $query->orderBy('nome_rsocial', 'ASC')->limit(50)->get();
}
```

---

## Classes CSS

| Classe | Descrição |
|--------|-----------|
| `.chosen-select-container` | Container principal |
| `.chosen-select-wrapper` | Wrapper do select customizado |
| `.chosen-select` | Display do valor selecionado |
| `.chosen-select-dropdown` | Dropdown com opções |
| `.chosen-select-search` | Container do campo de busca |
| `.chosen-select-options` | Container das opções |
| `.chosen-select-option` | Cada opção individual |
| `.chosen-select-selected` | Opção atualmente selecionada |
| `.chosen-select-highlighted` | Opção destacada (navegação por teclado) |
| `.chosen-select-no-results` | Mensagem de nenhum resultado |
| `.chosen-select-loading` | Indicador de carregamento |
| `.chosen-select-open` | Classe adicionada quando dropdown está aberto |

---

## Eventos

O componente dispara o evento `change` no select original quando uma opção é selecionada:

```javascript
document.getElementById('meuSelect').addEventListener('change', function(e) {
    console.log('Valor selecionado:', this.value);
});
```

---

## Navegação por Teclado

| Tecla | Ação |
|-------|------|
| `↓` (ArrowDown) | Destaca próxima opção |
| `↑` (ArrowUp) | Destaca opção anterior |
| `Escape` | Fecha o dropdown |
| Click | Seleciona a opção |

---

## Notas Técnicas

1. **Portal Pattern**: O dropdown é movido para o `<body>` quando aberto, evitando corte por `overflow: hidden` de containers pais.

   Use `data-chosen-placement="bottom"` quando o dropdown precisar permanecer imediatamente abaixo do campo, inclusive dentro de tabelas ou containers roláveis. O padrão `auto` pode abrir acima quando não houver espaço suficiente na janela.

2. **CSRF Token**: Requisições server-side incluem automaticamente o token CSRF do meta tag `<meta name="csrf-token">`.

3. **Preload Automático**: Selects server-side carregam 50 registros ao abrir, sem necessidade de digitar.

4. **Debounce**: Não há debounce implementado. Cada caractere digitado (após mínimo de 3) dispara uma requisição.

5. **Cache**: Não há cache de resultados. Cada abertura do dropdown faz nova requisição ao servidor.

6. **Busca Accent-Insensitive**: A busca ignora acentuação tanto no modo client-side quanto server-side. Digitar "mao" encontra "mão", "veiculo" encontra "veículo", etc.
   - **Client-side**: O JS usa `String.normalize('NFD')` para remover acentos antes de comparar (função `normalizeText()` em `chosen-select.js`).
   - **Server-side (SQL)**: Colunas com collation `utf8mb4_unicode_ci` já são accent-insensitive nativamente. Para campos JSON (`JSON_EXTRACT`), é necessário adicionar `COLLATE utf8mb4_unicode_ci` na query pois `JSON_EXTRACT` retorna collation `utf8mb4_bin`.
   - **Server-side (PHP)**: Se o filtro for feito no PHP (ex: `stripos`), usar `Normalizer::normalize()` com `FORM_D` + regex para remover acentos antes da comparação.
