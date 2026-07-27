# Helpers do Sistema

## Visão Geral

O sistema possui helpers em duas camadas que se complementam:

| Camada | Localização | Uso |
|--------|-------------|-----|
| **PHP** | `app/Helpers/*.php` | Backend, templates, controllers |
| **JavaScript** | `public/assets/js/` | Frontend, renderização dinâmica |

Os helpers JavaScript são carregados automaticamente no layout `iframe.php`.

## Mapeamento PHP ↔ JavaScript

| Categoria | PHP | JavaScript | Arquivo JS |
|-----------|-----|------------|------------|
| **Moeda** | `currency_format()` | `Currency.format()` | `currency.js` |
| **Moeda** | `currency_parse()` | `Currency.parse()` | `currency.js` |
| **Porcentagem** | - | `Percent.format()` | `percent.js` |
| **Porcentagem** | - | `Percent.parse()` | `percent.js` |
| **Data** | `format_date()` | `DateHelper.format()` | `date.js` |
| **Data** | `parse_date()` | `DateHelper.parse()` | `date.js` |
| **String** | `str_limit()` | `Str.limit()` | `components.js` |
| **KM** | - | `Km.format()` | `components.js` |
| **Combustivel** | - | `FuelLabels.isElectric()` | `components.js` |
| **API** | - | `API.get()` / `API.post()` | `api.js` |
| **Codigo** | `CodigoHelper::gerarComPrefixo()` | - | - |
| **Criptografia** | `encrypt()` / `decrypt()` | - | - |

## Helpers de Codigo

### PHP - `CodigoHelper`

Gera codigos publicos curtos no formato prefixo fixo + caracteres alfanumericos maiusculos.
O alfabeto padrao usa `0-9` e `A-Z`; com 7 posicoes, cada prefixo tem `36^7 = 78.364.164.096` combinacoes.

```php
use App\Helpers\CodigoHelper;

CodigoHelper::gerarComPrefixo('L');  // Ex: L9K3P7QA
CodigoHelper::gerarComPrefixo('C');  // Ex: C4Z8M2TN
CodigoHelper::gerarComPrefixo('MA'); // Ex: MA7K2P9XQ
CodigoHelper::gerarComPrefixo('CK'); // Ex: CK8M4Z1PA
```

Os Models que salvam codigos publicos devem verificar colisao na propria tabela antes de retornar o codigo. Tokens de seguranca, links de pagamento e nomes de arquivos continuam usando geradores especificos.

## Helpers de String

### PHP - `str_limit()`

Limita o tamanho de uma string, adicionando sufixo quando truncado.

```php
// Assinatura
str_limit(string $value, int $limit = 100, string $end = '...'): string

// Exemplos
str_limit('Texto muito longo aqui', 10);      // "Texto mui..."
str_limit('Texto curto', 20);                  // "Texto curto"
str_limit('Texto longo', 10, ' [...]');        // "Texto long [...]"
```

### JavaScript - `Str.limit()`

Equivalente JavaScript com mesma assinatura.

```javascript
// Assinatura
Str.limit(value, limit = 100, end = '...')

// Exemplos
Str.limit('Texto muito longo aqui', 10);      // "Texto mui..."
Str.limit('Texto curto', 20);                  // "Texto curto"
Str.limit('Texto longo', 10, ' [...]');        // "Texto long [...]"
```

### Uso em Tabelas (Exemplo)

```javascript
// Em renderização de tabela
const parcelasTexto = item.parcelas ? Str.limit(item.parcelas, 20) : '';
const parcelasBadge = item.parcelas 
    ? `<code title="${escapeHtml(item.parcelas)}">${escapeHtml(parcelasTexto)}</code>` 
    : '-';
```

## Helpers de Moeda

Veja documentação completa em **[currency.md](./currency.md)**.

### PHP

```php
currency_format(1234.56);           // "R$ 1.234,56"
currency_format(1234.56, false);    // "1.234,56"
currency_parse("R$ 1.234,56");      // 1234.56
currency_for_input(1234.56);        // "1.234,56"
```

### JavaScript

```javascript
Currency.format(1234.56);           // "1.234,56"
Currency.format(1234.56, true);     // "R$ 1.234,56"
Currency.parse("R$ 1.234,56");      // 1234.56
Currency.applyMask('#meuInput');
```

## Helpers de Porcentagem

Veja documentacao completa em **[percent.md](./percent.md)**.

### JavaScript

```javascript
Percent.format(2.5);              // "2,50"
Percent.format(0.033, 3);         // "0,033"
Percent.parse("2,50");            // 2.5
Percent.applyMask('#meuInput');
Percent.getValue('#meuInput');    // Retorna numero
Percent.setValue('#meuInput', 2.5);
```

Para inputs, use a classe `input-percent`:

```html
<input type="text" class="form-input input-percent" name="multa">
<!-- Com 3 casas decimais -->
<input type="text" class="form-input input-percent" data-decimals="3" name="juros">
```

## Helpers de Data

Veja documentação completa em **[date.md](./date.md)**.

### PHP

```php
format_date('2024-01-15');              // "15/01/2024"
format_datetime('2024-01-15 14:30:00'); // "15/01/2024 14:30:00"
format_operational_datetime('2024-01-15 14:30:00'); // sem conversao de timezone
parse_date('15/01/2024');               // "2024-01-15"
parse_datetime('15/01/2024 14:30:00');  // "2024-01-15 14:30:00"
today();                                // "2024-01-15"
now();                                  // "2024-01-15 14:30:00"
\App\Helpers\DateHelper::addDaysForDatabase(7);
\App\Helpers\DateHelper::addMonthsForDatabase(1);
```

### JavaScript

```javascript
DateHelper.format('2024-01-15');              // "15/01/2024"
DateHelper.formatDateTime('2024-01-15T14:30:00'); // "15/01/2024 14:30:00"
DateHelper.parse('15/01/2024');               // "2024-01-15"
DateHelper.today();                           // Data atual formatada
DateHelper.todayISO();                        // "2024-01-15"
DateHelper.nowInput();                        // valor para datetime-local
DateHelper.addDays('2024-01-15', 7);          // "2024-01-22"
DateHelper.diffDateTime(inicio, fim);         // diferenca em ms
```

Regra: codigo novo deve usar apenas `DateHelper`/helpers globais para data e hora. `date()`, `time()`, `new DateTime()`, `new Date()` e `NOW()/CURDATE()` so ficam em helpers internos, headers GMT ou componentes de calendario com excecao documentada em [date.md](./date.md).

## Helpers de Quilometragem

### JavaScript - `Km`

```javascript
Km.format(123456);              // "123.456"
Km.parse("123.456");            // 123456
Km.applyMask('#kmInput');       // Aplica máscara
Km.applyMaskToAll('input-km');  // Aplica em todos com classe
```

Para inputs, use a classe `input-km`:

```html
<input type="text" class="form-input input-km" name="km_atual">
```

## Helpers de UI

### JavaScript/CSS - Menu de ações (`ActionMenu`)

Dropdown compacto para agrupar ações relacionadas em um botão, inclusive quando o gatilho exibe apenas um ícone. O comportamento é inicializado automaticamente por `components.min.js`: abre por clique ou teclado, fecha ao selecionar uma ação, clicar fora ou pressionar `Esc`, e mantém `aria-expanded` sincronizado.

```html
<div class="action-menu" data-action-menu>
    <button type="button"
        class="action-menu-trigger"
        aria-label="Ações de importação"
        aria-haspopup="menu"
        aria-expanded="false">
        <i class="fas fa-file-import" aria-hidden="true"></i>
    </button>
    <div class="action-menu-panel" role="menu" aria-label="Ações de importação">
        <button type="button" class="action-menu-item" role="menuitem">
            <i class="fas fa-file-arrow-up" aria-hidden="true"></i>
            <span>Importar</span>
        </button>
        <a href="/modelo.csv" class="action-menu-item" role="menuitem">
            <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
            <span>Baixar modelo</span>
        </a>
    </div>
</div>
```

Use `window.ActionMenu.closeAll()` quando uma ação precisar fechar o menu antes de iniciar um fluxo assíncrono. O componente aceita vários menus na mesma página e mantém somente um aberto.

### PHP - `aviso()`

Gera ícone de ajuda [?] com popover de instrução.

Toda instrução, explicação ou aviso associado a um `input`, `select` ou `textarea` deve ser exibido com `aviso()` dentro do respectivo `<label>`. Nunca renderize esse tipo de texto em parágrafos, `small` ou outros elementos abaixo do campo. Essa regra não se aplica a mensagens de validação, descrições de seções ou informações dinâmicas que não sejam auxiliares de um campo.

```php
{!! aviso('Texto explicativo aqui') !!}
{!! aviso(t('modules.modulo.hints.campo')) !!}

<label class="form-label-group">
    <?= t('modules.modulo.fields.campo') ?>
    <?= aviso(t('modules.modulo.hints.campo')) ?>
</label>
```

### PHP - `e()`

Escapa HTML entities (equivalente a `htmlspecialchars`).

```php
<div><?= e($usuario['nome']) ?></div>
```

## Helpers de Sessão e Cache

### PHP

```php
// Sessão
session('chave');                    // Obtém valor
old('campo', 'default');             // Valor anterior do form
flash('success', 'Salvo!');          // Mensagem flash

// Cache
cache('key');                        // Obtém
cache('key', $valor, 3600);          // Define com TTL
cache_remember('key', 3600, fn() => $dados); // Obtém ou computa
cache_forget('key');                 // Remove
```

## Helpers de Tradução (i18n)

Veja documentação completa em **[i18n.md](./i18n.md)**.

```php
t('common.buttons.save');                    // "Salvar"
t('messages.greeting', ['nome' => 'João']); // "Olá, João!"
t_choice('messages.items', 5);              // "5 itens"
current_locale();                            // "pt_BR"
```

## Helpers de Planos

```php
plano_nome('P4');     // "Plano Premium"
plano_info('P4');     // Array com todas as informações
```

## Helpers de Criptografia

Funções para criptografar e descriptografar dados sensíveis (API keys, tokens, etc).

### PHP - `encrypt()`

Criptografa uma string usando AES-256-CBC.

```php
// Assinatura
encrypt(string $data): string

// Exemplo
$apiKey = 'minha-api-key-secreta';
$encrypted = encrypt($apiKey);
// Retorna: "base64-encoded-iv+ciphertext"
```

**Retorno**: String em Base64 contendo IV (16 bytes) + ciphertext.

### PHP - `decrypt()`

Descriptografa uma string criptografada com `encrypt()`.

```php
// Assinatura
decrypt(string $encrypted): ?string

// Exemplo
$decrypted = decrypt($encrypted);
if ($decrypted === null) {
    // Falha na descriptografia (dados corrompidos ou chave diferente)
}
```

**Retorno**: String original ou `null` se falhar.

### Detalhes Técnicos

| Aspecto | Valor |
|---------|-------|
| **Algoritmo** | AES-256-CBC |
| **Chave** | Derivada de `APP_KEY` via SHA-256 |
| **IV** | 16 bytes aleatórios (random_bytes) |
| **Formato** | Base64(IV + ciphertext) |

### Casos de Uso

```php
// Salvar API key no banco
$dados['api_key'] = encrypt($apiKeyPlainText);
$model->criar($dados);

// Recuperar API key para uso
$conexao = $model->buscarPorId($id);
$apiKey = decrypt($conexao['api_key']);

// Validar descriptografia
if ($apiKey === null) {
    throw new \RuntimeException('Falha ao descriptografar credenciais');
}
```

### Segurança

- **Sempre** use `encrypt()` para armazenar dados sensíveis (API keys, tokens, senhas de terceiros)
- **Nunca** armazene `APP_KEY` no banco de dados
- **Nunca** exponha dados criptografados em logs ou respostas de API
- A chave `APP_KEY` deve ser única por ambiente (dev, staging, prod)

## Arquivos Relacionados

| Arquivo | Descrição |
|---------|-----------|
| `app/Helpers/helpers.php` | Funções globais PHP |
| `app/Helpers/CurrencyHelper.php` | Classe de moeda |
| `app/Helpers/DateHelper.php` | Classe de data |
| `app/Helpers/FileHelper.php` | Manipulação de arquivos |
| `app/Helpers/FilialHelper.php` | Filtros de filial |
| `app/Helpers/PdfHelper.php` | Geração de PDF + resolução de imagens para mPDF; constantes `DOCUMENTO_HTML_*` / `DOCUMENTO_MULTAS_*` para margens do corpo com header/footer HTML |
| `app/Helpers/SequenciaHelper.php` | Geração de sequências |
| `public/assets/js/components.js` | Helpers JS (Str, Km, HelpHint, FuelLabels, ActionMenu) |
| `public/assets/js/currency.js` | Helper JS de moeda |
| `public/assets/js/percent.js` | Helper JS de porcentagem |
| `public/assets/js/date.js` | Helper JS de data |
| `public/assets/js/api.js` | Helper JS de requisições |

## Boas Práticas

1. **Consistência**: Use o helper apropriado para cada camada (PHP no backend, JS no frontend)
2. **Não duplique**: Se precisa em ambas as camadas, use os helpers correspondentes
3. **Formatação no frontend**: Prefira formatar dados no JavaScript quando for para exibição dinâmica
4. **Parsing no backend**: Sempre converta dados formatados antes de salvar no banco
5. **Escape de HTML**: Use `e()` no PHP e `escapeHtml()` no JS para prevenir XSS

## Documentação Relacionada

- **[Currency](./currency.md)** - Formatação de moeda
- **[Percent](./percent.md)** - Formatação de porcentagem
- **[Date](./date.md)** - Formatação de data
- **[API](./api.md)** - Requisições JavaScript
- **[i18n](./i18n.md)** - Internacionalização
- **[FileHelper](./file-helper.md)** - Manipulação de arquivos
- **[Geração de PDF](./pdf.md)** - PdfHelper, output buffering, resolução de imagens
- **[FilialHelper](./filial-helper.md)** - Filtros de filial
