# Helper de Porcentagem (Percent)

## Visao Geral

O helper `Percent` fornece formatacao de valores percentuais com mascara em tempo real no frontend. Funciona de forma similar ao `Currency`, mas sem separador de milhares e com suporte a casas decimais configuraveis.

| Caracteristica | Valor |
|----------------|-------|
| **Arquivo** | `public/assets/js/percent.js` |
| **Classe CSS** | `input-percent` |
| **Separador Decimal** | `,` (virgula) |
| **Casas Decimais Padrao** | 2 |
| **Casas Decimais Customizaveis** | Sim, via `data-decimals` |

## Funcoes Disponiveis

### `Percent.format(value, decimals)`

Formata um numero para string com virgula como separador decimal.

```javascript
Percent.format(2.5);        // "2,50"
Percent.format(0.033, 3);   // "0,033"
Percent.format(10);         // "10,00"
```

### `Percent.parse(value)`

Converte uma string formatada para numero (com ponto decimal).

```javascript
Percent.parse("2,50");      // 2.5
Percent.parse("0,033");     // 0.033
Percent.parse("10,00");     // 10
```

### `Percent.applyMask(input, decimals)`

Aplica mascara em tempo real em um input. A formatacao ocorre conforme o usuario digita.

```javascript
Percent.applyMask('#multa');                    // 2 casas decimais
Percent.applyMask('#juros', 3);                 // 3 casas decimais
Percent.applyMask(document.getElementById('taxa')); // Aceita elemento
```

### `Percent.applyMaskToAll(className)`

Aplica mascara em todos os inputs com uma classe especifica.

```javascript
Percent.applyMaskToAll('input-percent');        // Padrao
Percent.applyMaskToAll('minha-classe');         // Classe customizada
```

### `Percent.getValue(input)`

Retorna o valor numerico de um input mascarado (pronto para enviar ao backend).

```javascript
// Input contem "2,50"
Percent.getValue('#multa');                     // 2.5
```

### `Percent.setValue(input, value, decimals)`

Define o valor de um input com formatacao automatica.

```javascript
Percent.setValue('#multa', 2.5);                // Input exibe "2,50"
Percent.setValue('#juros', 0.033, 3);           // Input exibe "0,033"
```

## Uso em HTML

### Basico (2 casas decimais)

```html
<input type="text" id="multa" name="multa"
       class="form-input-group-field input-percent"
       placeholder="0,00">
```

### Com 3 casas decimais

Use o atributo `data-decimals` para configurar casas decimais diferentes:

```html
<input type="text" id="juros_por_dia" name="juros_por_dia"
       class="form-input-group-field input-percent"
       data-decimals="3"
       placeholder="0,000">
```

### Com addon de porcentagem

```html
<div class="input-group-with-addon">
    <input type="text" id="taxa" name="taxa"
           class="form-input-group-field input-percent"
           placeholder="0,00">
    <span class="input-addon">%</span>
</div>
```

## Comportamento da Mascara

A mascara funciona em tempo real enquanto o usuario digita:

| Usuario Digita | Campo Exibe (2 casas) | Campo Exibe (3 casas) |
|----------------|----------------------|----------------------|
| `2` | `0,02` | `0,002` |
| `25` | `0,25` | `0,025` |
| `250` | `2,50` | `0,250` |
| `2500` | `25,00` | `2,500` |

**Funcionamento interno:**
1. Captura apenas digitos do input
2. Divide pelo fator de casas decimais (100 para 2 casas, 1000 para 3 casas)
3. Formata automaticamente com virgula

## Integracao com Formularios

### Carregando dados do backend

```javascript
function preencherFormulario(dados) {
    Percent.setValue('#multa', dados.multa);
    Percent.setValue('#juros_por_dia', dados.juros_por_dia, 3);
    Percent.setValue('#taxa_percentual', dados.taxa_percentual);
}
```

### Enviando dados ao backend

```javascript
const dados = {
    multa: Percent.getValue('#multa'),
    juros_por_dia: Percent.getValue('#juros_por_dia'),
    taxa_percentual: Percent.getValue('#taxa_percentual')
};

await API.post('/endpoint', dados);
```

## Diferenca entre Percent e Currency

| Caracteristica | Percent | Currency |
|----------------|---------|----------|
| **Separador de Milhares** | Nao | Sim (`.`) |
| **Casas Decimais** | Configuraveis (2-3) | Fixas (2) |
| **Uso Tipico** | Taxas, juros, descontos | Valores monetarios |
| **Classe CSS** | `input-percent` | `input-moeda` |

## Inicializacao Automatica

O helper inicializa automaticamente quando o DOM esta pronto:

```javascript
// Executado automaticamente
Percent.init();
Percent.applyMaskToAll('input-percent');
```

Nao e necessario chamar manualmente, a menos que inputs sejam adicionados dinamicamente.

## Inputs Dinamicos

Para inputs adicionados apos o carregamento da pagina:

```javascript
// Apos adicionar novo input ao DOM
const novoInput = document.getElementById('novoInput');
Percent.applyMask(novoInput);
```

## Arquivos Relacionados

| Arquivo | Descricao |
|---------|-----------|
| `public/assets/js/percent.js` | Codigo fonte do helper |
| `public/assets/js/percent.min.js` | Versao minificada |
| `public/assets/js/currency.js` | Helper de moeda (similar) |
| `app/Views/layouts/iframe.php` | Inclui o script |

## Documentacao Relacionada

- **[Currency](./currency.md)** - Helper de moeda (similar)
- **[Helpers](./helpers.md)** - Visao geral de todos os helpers
- **[Formas de Pagamento](./formas-pagamento.md)** - Exemplo de uso
