# Sistema de Moeda Multi-tenant

O sistema de formatação de moeda permite que cada empresa tenha sua configuração de locale e moeda, enquanto mantém o formato internacional (1234.56) no banco de dados.

## Conceito

```
[BD: 1234.56] → currency_format() → [Front-end: R$ 1.234,56]
[Front-end: R$ 1.234,56] → currency_parse() → [BD: 1234.56]
```

- **Armazenamento**: Sempre no formato internacional (`DECIMAL(10,2)` → `1234.56`)
- **Exibição**: Formatado conforme configuração da empresa (`R$ 1.234,56` para pt_BR)

## Configuração por Empresa

As configurações são armazenadas na tabela `matrizes_filiais` e são obtidas da **filial principal do funcionário logado** (`$_SESSION['id_matriz_filial']`):

| Coluna | Tipo | Padrão | Descrição |
|--------|------|--------|-----------|
| `locale` | VARCHAR(10) | pt_BR | Locale para formatação |
| `currency_code` | VARCHAR(3) | BRL | Código ISO da moeda |

### Locales Suportados

| Locale | Moeda | Símbolo | Separador Decimal | Separador Milhares | Exemplo |
|--------|-------|---------|-------------------|-------------------|---------|
| pt_BR | BRL | R$ | , | . | R$ 1.234,56 |
| en_US | USD | $ | . | , | $1,234.56 |
| pt_PT | EUR | € | , | . | 1.234,56 € |
| es_ES | EUR | € | , | . | 1.234,56 € |

## Uso no PHP (Backend)

### Funções Globais

```php
// Formatar valor para exibição
currency_format(1234.56);          // "R$ 1.234,56"
currency_format(1234.56, false);   // "1.234,56" (sem símbolo)

// Converter valor do front-end para float
currency_parse("R$ 1.234,56");     // 1234.56
currency_parse("1.234,56");        // 1234.56

// Formatar para input HTML (sem símbolo)
currency_for_input(1234.56);       // "1.234,56"

// Formatar com valor por extenso entre parênteses
currency_format_extenso(125.31);
// "R$ 125,31 (cento e vinte e cinco reais e trinta e um centavos)"

// Obter apenas o valor por extenso (sem formatação numérica)
currency_extenso(125.31);
// "cento e vinte e cinco reais e trinta e um centavos"

// Obter configuração da empresa
$config = currency_config();
// ['locale' => 'pt_BR', 'currency' => 'BRL', 'symbol' => 'R$', ...]
```

### Uso no Controller

```php
// Ao salvar no banco de dados
$dados = [
    'salario' => currency_parse($request->input('salario', '0')),
    // ...
];
```

### Classe CurrencyHelper

Para uso avançado, a classe `App\Helpers\CurrencyHelper` oferece:

```php
use App\Helpers\CurrencyHelper;

// Formatar com matriz específica
CurrencyHelper::format(1234.56, true, $matrizId);

// Converter com locale específico
CurrencyHelper::parse("1,234.56", 'en_US');

// Limpar cache de configuração
CurrencyHelper::clearCache();
```

## Uso no JavaScript (Frontend)

### Objeto Global Currency

```javascript
// Formatar número
Currency.format(1234.56);           // "1.234,56"
Currency.format(1234.56, true);     // "R$ 1.234,56" (com símbolo)

// Converter string para número
Currency.parse("R$ 1.234,56");      // 1234.56
Currency.parse("1.234,56");         // 1234.56

// Aplicar máscara em input específico
Currency.applyMask('#meuInput');
Currency.applyMask(document.getElementById('meuInput'));

// Aplicar máscara em todos inputs com classe
Currency.applyMaskToAll('input-moeda');

// Obter valor numérico de input mascarado
Currency.getValue('#meuInput');     // 1234.56

// Definir valor em input mascarado
Currency.setValue('#meuInput', 1234.56);

// Atualizar símbolos de moeda na página
Currency.updateSymbols();
```

### Uso em Formulários

1. Adicione a classe `input-moeda` no input:

```html
<input type="text"
       id="salario"
       name="salario"
       class="form-input-group-field input-moeda"
       placeholder="0,00">
```

2. A máscara é aplicada **automaticamente** quando o DOM carrega. Para aplicar manualmente:

```javascript
// Aplicar em todos os inputs com a classe
Currency.applyMaskToAll('input-moeda');

// OU aplicar em input específico
Currency.applyMask('#salario');
```

3. O valor será formatado automaticamente enquanto o usuário digita.

4. No backend, use `currency_parse()` para converter antes de salvar:

```php
'salario' => currency_parse($request->input('salario', '0')),
```

### Padrão HTML para Campos de Moeda

Use este padrão para campos de moeda com símbolo dinâmico:

```html
<div class="form-input-group">
    <label for="valor" class="form-label-group">Valor</label>
    <div class="relative">
        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
        <input type="text" id="valor" name="valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
    </div>
</div>
```

**Elementos obrigatórios:**

| Elemento | Descrição |
|----------|-----------|
| `currency-symbol` | Classe no span para atualização dinâmica do símbolo |
| `input-moeda` | Classe padrão no input para aplicar máscara de moeda |
| `relative` | Container para posicionamento absoluto do símbolo |

### Atualização Dinâmica do Símbolo

Os símbolos de moeda são atualizados **automaticamente** quando o DOM carrega. O método `Currency.updateSymbols()` é chamado automaticamente.

Para atualizar manualmente (ex: após carregar conteúdo via AJAX):

```javascript
Currency.updateSymbols();
```

### Configuração Automática

As configurações da empresa são passadas automaticamente via `window.APP_CONFIG`:

```html
<!-- No layout iframe.php -->
<script>
    window.APP_CONFIG = {
        currency: <?= json_encode(currency_config()) ?>
    };
</script>
```

O objeto `Currency` usa essas configurações automaticamente.

> **IMPORTANTE**: A classe canônica é `input-moeda`. A classe `currency-mask` foi descontinuada e removida do código (limpeza 2026-05) — `Currency.applyMaskToAll` por padrão processa apenas `input-moeda`.

## Backend: convertendo string monetária para float

Sempre use o helper global **`currency_parse()`** (`app/Helpers/helpers.php:674` → `CurrencyHelper::parse`). Aceita string em formato BR (`"1.500,50"`), formato internacional (`"1500.50"`), float, int ou null.

```php
$valor = currency_parse($request->input('valor'));   // 1500.5
$valor = currency_parse(1500.5);                      // 1500.5
$valor = currency_parse(null);                        // 0.0
```

**NÃO** crie métodos privados `toDecimal()` em Models. Na limpeza de 2026-05 removemos as 16 cópias duplicadas que existiam — todos os Models agora usam `currency_parse()` direto.

A única exceção é `Locacao::toDecimal` que continua como **wrapper local** (`return round(currency_parse($v), 2)`) porque os totais de locação exigem arredondamento monetário explícito em 2 casas. Se algum outro Model precisar do mesmo arredondamento, faça `round(currency_parse($v), 2)` no caller — não recrie o método privado.

## Comportamento da Máscara

- **Tempo real**: Formata enquanto o usuário digita
- **Centavos primeiro**: Digitar `123456` resulta em `1.234,56`
- **Limite**: Máximo 15 dígitos (trilhões)
- **Caracteres inválidos**: Removidos automaticamente
- **Campo vazio**: Ao focar em `0,00`, limpa o campo
- **Ao sair**: Garante formatação correta

## Detecção Automática de Formato

O `currency_parse()` detecta automaticamente o formato:

```php
// Formato brasileiro
currency_parse("1.234,56");    // 1234.56

// Formato americano
currency_parse("1,234.56");    // 1234.56

// Sem formatação
currency_parse("1234.56");     // 1234.56
currency_parse("1234,56");     // 1234.56
```

## Arquivos Relacionados

| Arquivo | Descrição |
|---------|-----------|
| `app/Helpers/CurrencyHelper.php` | Classe PHP principal |
| `app/Helpers/NumberToWordsHelper.php` | Conversão de números para texto por extenso |
| `app/Helpers/helpers.php` | Funções globais |
| `public/assets/js/currency.js` | Helper JavaScript |
| `app/Views/layouts/iframe.php` | Passa configs para front-end |

## Boas Práticas

1. **Sempre use `currency_parse()` ao salvar** valores monetários no banco
2. **Sempre use `currency_format()` ao exibir** valores para o usuário
3. **Use a classe `input-moeda`** em inputs de moeda para máscara automática
4. **O banco armazena DECIMAL(10,2)** - sempre formato internacional
5. **Não armazene símbolos** ou formatação no banco de dados
6. **Use o padrão HTML com `currency-symbol`** para exibir símbolo dinâmico
7. **A configuração de moeda vem da filial principal** do funcionário logado (`id_matriz_filial`)
