# Sistema de Data Multi-tenant

O sistema de formatacao de data permite que cada empresa tenha sua configuracao de formato, enquanto mantem o formato internacional (Y-m-d) no banco de dados.

## Conceito

```
[BD: 2024-01-15] -> format_date() -> [Front-end: 15/01/2024]
[Front-end: 15/01/2024] -> parse_date() -> [BD: 2024-01-15]
```

- **Armazenamento**: Sempre no formato internacional (`DATE` -> `Y-m-d`)
- **Exibicao**: Formatado conforme configuracao da empresa (`d/m/Y` para pt_BR)

## Configuracao por Empresa

As configuracoes sao armazenadas na tabela `matrizes_filiais`:

| Coluna | Tipo | Padrao | Descricao |
|--------|------|--------|-----------|
| `date_format` | VARCHAR(20) | d/m/Y | Formato de data |
| `datetime_format` | VARCHAR(20) | d/m/Y H:i:s | Formato de data/hora |

### Formatos Suportados

| Caractere | Descricao | Exemplo |
|-----------|-----------|---------|
| `d` | Dia com zero | 01-31 |
| `j` | Dia sem zero | 1-31 |
| `m` | Mes com zero | 01-12 |
| `n` | Mes sem zero | 1-12 |
| `Y` | Ano 4 digitos | 2024 |
| `y` | Ano 2 digitos | 24 |
| `H` | Hora 24h com zero | 00-23 |
| `G` | Hora 24h sem zero | 0-23 |
| `i` | Minutos | 00-59 |
| `s` | Segundos | 00-59 |

### Exemplos de Formatos

| Locale | Formato Data | Formato Data/Hora | Exemplo |
|--------|--------------|-------------------|---------|
| Brasil | d/m/Y | d/m/Y H:i:s | 15/01/2024 14:30:00 |
| EUA | m/d/Y | m/d/Y H:i:s | 01/15/2024 14:30:00 |
| ISO | Y-m-d | Y-m-d H:i:s | 2024-01-15 14:30:00 |

## Uso no PHP (Backend)

### Funcoes Globais

```php
// Formatar para exibicao
format_date('2024-01-15');              // "15/01/2024"
format_datetime('2024-01-15 14:30:00'); // "15/01/2024 14:30:00"

// Converter para salvar no BD
parse_date('15/01/2024');               // "2024-01-15"
parse_datetime('15/01/2024 14:30:00');  // "2024-01-15 14:30:00"

// Obter configuracao da empresa
$config = date_config();
// ['date_format' => 'd/m/Y', 'datetime_format' => 'd/m/Y H:i:s']
```

### Uso no Controller

```php
// Ao exibir dados do banco
$cliente['nascimento_formatado'] = format_date($cliente['nascimento']);

// Ao salvar no banco de dados
$dados = [
    'data_venci' => parse_date($request->input('data_vencimento')),
    // ...
];
```

### Classe DateHelper

Para uso avancado, a classe `App\Helpers\DateHelper` oferece:

```php
use App\Helpers\DateHelper;

// Formatar
DateHelper::format('2024-01-15');
DateHelper::formatDateTime('2024-01-15 14:30:00');

// Converter
DateHelper::parse('15/01/2024');
DateHelper::parseDateTime('15/01/2024 14:30:00');

// Validar formato
DateHelper::isValidFormat('15/01/2024');          // true
DateHelper::isValidDateTimeFormat('15/01/2024 14:30:00'); // true

// Limpar cache de configuracao
DateHelper::clearCache();
```

## Uso no JavaScript (Frontend)

### Objeto Global DateHelper

```javascript
// Formatar para exibicao
DateHelper.format('2024-01-15');              // "15/01/2024"
DateHelper.formatDateTime('2024-01-15T14:30:00'); // "15/01/2024 14:30:00"

// Converter para enviar ao servidor
DateHelper.parse('15/01/2024');               // "2024-01-15"
DateHelper.parseDateTime('15/01/2024 14:30:00'); // "2024-01-15 14:30:00"

// Data/hora atual
DateHelper.today();  // "15/01/2024"
DateHelper.now();    // "15/01/2024 14:30:00"

// Aplicar mascara em input
DateHelper.applyMask('#dataInput');
DateHelper.applyMask('#dataHoraInput', true); // com hora

// Aplicar mascara em todos inputs com classe
DateHelper.applyMaskToAll('date-mask');
DateHelper.applyMaskToAll('datetime-mask', true);
```

### Uso em Formularios

1. Adicione a classe `date-mask` no input:

```html
<input type="text"
       id="dataVencimento"
       name="data_vencimento"
       class="form-input-group-field date-mask"
       placeholder="dd/mm/aaaa">
```

2. Inicialize a mascara no JavaScript:

```javascript
// Aplicar em todos os inputs com a classe
DateHelper.applyMaskToAll('date-mask');

// OU aplicar em input especifico
DateHelper.applyMask('#dataVencimento');
```

3. A data sera formatada automaticamente enquanto o usuario digita.

4. No backend, use `parse_date()` para converter antes de salvar:

```php
'data_venci' => parse_date($request->input('data_vencimento')),
```

### Configuracao Automatica

As configuracoes da empresa sao passadas automaticamente via `window.APP_CONFIG`:

```html
<!-- No layout iframe.php -->
<script>
    window.APP_CONFIG = {
        currency: <?= json_encode(currency_config()) ?>,
        date: <?= json_encode(date_config()) ?>
    };
</script>
```

O objeto `DateHelper` usa essas configuracoes automaticamente.

## Uso na Aba de Faturas (Exemplo)

```javascript
// Ao renderizar datas do banco
tbody.innerHTML = faturas.map(f => {
    const dataFormatada = DateHelper.format(f.data_venci);
    const valorFormatado = Currency.format(f.valor, true);

    return `<tr>
        <td>${dataFormatada}</td>
        <td>${valorFormatado}</td>
    </tr>`;
}).join('');
```

## Arquivos Relacionados

| Arquivo | Descricao |
|---------|-----------|
| `app/Helpers/DateHelper.php` | Classe PHP principal |
| `app/Helpers/helpers.php` | Funcoes globais |
| `public/assets/js/date.js` | Helper JavaScript |
| `app/Views/layouts/iframe.php` | Passa configs para front-end |

## Boas Praticas

1. **Sempre use `parse_date()` ao salvar** datas no banco
2. **Sempre use `format_date()` ao exibir** datas para o usuario
3. **Use a classe `date-mask`** em inputs de data para mascara automatica
4. **O banco armazena DATE/DATETIME** - sempre formato internacional
5. **Nao armazene formatos locais** no banco de dados

## Comparacao com Currency

| Aspecto | Currency | Date |
|---------|----------|------|
| Config | `currency_config()` | `date_config()` |
| Formatar | `currency_format()` | `format_date()` |
| Converter | `currency_parse()` | `parse_date()` |
| JS Object | `Currency` | `DateHelper` |
| Classe CSS | `input-moeda` | `date-mask` |
