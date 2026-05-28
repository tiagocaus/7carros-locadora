# Geração de PDF com mPDF

## Visão Geral

O sistema utiliza a biblioteca **mPDF 8.2.5** para gerar documentos PDF a partir de HTML. Esta documentação explica o padrão correto para evitar erros comuns.

## OBRIGATÓRIO: Use PdfHelper

**SEMPRE** use `PdfHelper::create()` para criar instâncias do mPDF. Este helper:
- Configura opções padrão automaticamente
- Adiciona watermark lateral "Sistema 7Carros.com.br" em todos os PDFs
- Garante consistência visual em todos os documentos

```php
use App\Helpers\PdfHelper;

// ✅ CORRETO - Usa PdfHelper
$mpdf = PdfHelper::create();
PdfHelper::writeHtml($mpdf, $html);
$mpdf->Output('documento.pdf', 'I');

// ❌ ERRADO - Não use mPDF diretamente
$mpdf = new Mpdf([...]); // Falta a watermark!
```

### Opções do PdfHelper

```php
// Com opções customizadas
$mpdf = PdfHelper::create([
    'format' => 'A5',
    'orientation' => 'L',
    'margin_left' => 20,
    'margin_top' => 15
]);

// Desabilitar watermark (casos especiais apenas)
$mpdf = PdfHelper::create(['watermark' => false]);

// Customizar texto da watermark
$mpdf = PdfHelper::create(['watermark_text' => 'Texto Customizado']);
```

### Métodos Utilitários

```php
// Gerar e retornar como string
$pdfContent = PdfHelper::generateAsString($html);

// Gerar e exibir inline
PdfHelper::outputInline($html, 'documento.pdf');

// Gerar e forçar download
PdfHelper::outputDownload($html, 'documento.pdf');

// Salvar em arquivo
PdfHelper::saveToFile($html, '/caminho/documento.pdf');
```

## IMPORTANTE: Não Use Template::render()

**NUNCA** use `Template::render()` quando precisar capturar HTML para gerar PDF.

### Por que isso não funciona?

```php
// ❌ ERRADO - NÃO FUNCIONA
$html = Template::render('pages/documento/imprimir', $data);
$mpdf->WriteHTML($html);  // Esta linha NUNCA é executada
```

O método `Template::render()` internamente chama `Response::html()` que faz:

```php
public static function html(string $content, int $status = 200): void
{
    // ...
    echo $content;  // Envia HTML para o navegador
    exit;           // TERMINA a execução do script
}
```

Resultado: O HTML é enviado diretamente ao navegador e o script termina antes do código do mPDF ser executado.

## Padrão Correto: Output Buffering

Use `ob_start()` e `ob_get_clean()` para capturar o HTML sem enviá-lo ao navegador:

```php
// ✅ CORRETO - Captura HTML sem enviar ao navegador
ob_start();
$viewPath = __DIR__ . '/../Views/pages/documento/imprimir/template.php';
extract(['dados' => $dados, 'empresa' => $empresa]);
include $viewPath;
$html = ob_get_clean();

// Agora o mPDF recebe o HTML corretamente
PdfHelper::writeHtml($mpdf, $html);
```

## Exemplo Completo

```php
<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Documento;
use App\Helpers\PdfHelper;

class DocumentosController
{
    public function imprimir(Request $request, string $codigo): void
    {
        try {
            // 1. Buscar dados do documento
            $documentoModel = new Documento();
            $documento = $documentoModel->buscarPorCodigo($codigo);

            if (!$documento) {
                Response::html('<h1>Documento não encontrado</h1>', 404);
                return;
            }

            // 2. Validações de acesso (tenant, filial, etc.)
            // ... suas validações aqui ...

            // 3. Capturar HTML do template usando output buffering
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/documentos/imprimir/template.php';
            extract([
                'documento' => $documento,
                'empresa' => $empresa
            ]);
            include $viewPath;
            $html = ob_get_clean();

            // 4. Criar instância do mPDF com PdfHelper (inclui watermark)
            $mpdf = PdfHelper::create([
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5
            ]);

            // 5. Gerar e enviar PDF
            PdfHelper::writeHtml($mpdf, $html);
            $mpdf->Output('documento-' . $codigo . '.pdf', 'I');
            exit;

        } catch (\Exception $e) {
            Response::html('<h1>Erro ao gerar PDF: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }
}
```

## Template HTML para PDF

O template deve ser um arquivo PHP que gera HTML válido:

```php
<!-- app/Views/pages/documentos/imprimir/template.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .box {
            border: 1px solid #333;
            padding: 20px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            border: 1px solid #ccc;
            padding: 8px;
        }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($documento['titulo']) ?></h1>

    <div class="box">
        <p><strong>Código:</strong> <?= htmlspecialchars($documento['codigo']) ?></p>
        <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($documento['data'])) ?></p>
    </div>

    <!-- Conteúdo do documento -->
    <p><?= nl2br(htmlspecialchars($documento['conteudo'])) ?></p>
</body>
</html>
```

## Imagens no PDF (logos, fotos, assinaturas)

**SEMPRE** use `PdfHelper::resolveImagePath()` para resolver caminhos de imagens em templates PDF. Ele resolve três problemas de uma vez:

```php
use App\Helpers\PdfHelper;

// Logo da empresa (no método imprimir do controller)
$logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);

// Fotos de vistoria/checklist (várias)
foreach ($vistoria as &$item) {
    $item['img_path'] = PdfHelper::resolveImagePath($item['img'] ?? null, $chave);
}

// Assinatura digital
$assinaturaPath = PdfHelper::resolveImagePath($checklist['assinatura'] ?? null, $chave);
```

### Assinaturas em PDF: fundo branco obrigatorio

Assinaturas podem chegar como PNG/WebP com transparencia. Ao converter WebP para JPEG temporario, o `PdfHelper::resolveImagePath()` compoe a imagem sobre fundo branco antes de entregar ao mPDF. Isso evita fundo preto em faturas, contratos, locacoes e checklists.

Nos templates, continue usando `PdfHelper::resolveImagePath()` e nunca monte `<img>` com URL publica (`FileHelper::url()`) para PDF.

No template, basta `<img src="<?= $logoPath ?>">` (path local absoluto, mPDF abre direto).

### Por que usar o helper

1. **NUNCA passe URL HTTP** (ex: `FileHelper::url()`) para mPDF. URLs com token HMAC não são acessíveis pelo servidor onde o mPDF roda — `WriteHTML()` morre silenciosamente, gera erro 500 genérico.
2. **WebP é ~90× mais lento** no mPDF que JPEG (medido: 10 imagens 2000×1500 → WebP=1.93s vs JPEG=0.02s). O helper converte WebP→JPEG temp via GD antes de passar pro mPDF.
3. **Cleanup automático** dos JPEG temporários ao final do request via `register_shutdown_function`. Caller não precisa gerenciar `$tmpFiles`.
4. **Cache** — JPEG temp é reusado se já existe e é mais recente que o WebP de origem.

### O que o helper faz internamente

```
filename + chave
   ↓
FileHelper::getPath()  →  path absoluto local
   ↓
mime_content_type()
   ↓
├── não-WebP (JPG/PNG/GIF)  →  retorna o path direto
└── WebP                     →  imagecreatefromwebp + imagejpeg(q=85)
                                em sys_get_temp_dir() + register cleanup
                                retorna o path do JPEG temp
```

### Padrões anti

```php
// ❌ NUNCA: URL HTTP com token
$logoPath = FileHelper::url($empresa['logo'], $chave);

// ❌ NUNCA: path direto sem o helper (perde conversão WebP, deixa PDF lento)
$logoPath = FileHelper::getPath($empresa['logo'], $chave);

// ❌ NUNCA: hardcode do storage path
$logoPath = APP_ROOT . '/storage/uploads/' . $chave . '/' . $empresa['logo'];

// ❌ NUNCA: criar `converterWebpParaJpeg` privado no controller
// (já foi removido em todos os controllers — use o helper centralizado)
```

### Onde o helper é usado hoje

- `MultasController::imprimir`
- `ContratosController::imprimir` (logo + fotos checklist)
- `LocacoesController::imprimir` (logo + fotos checklist)
- `ChecklistsController::imprimir` (logo + fotos vistoria + assinaturas)
- `ManutencoesController::imprimir` (logo)
- `Relatorios/BaseRelatorioController::resolveLogoPath` (todos os 13 controllers de relatório)

## Configurações do mPDF

### Opções Comuns (PdfHelper)

| Opção | Descrição | Valor Padrão |
|-------|-----------|--------------|
| `mode` | Encoding do documento | `'utf-8'` |
| `format` | Tamanho do papel | `'A4'` |
| `margin_left` | Margem esquerda (mm) | `10` |
| `margin_right` | Margem direita (mm) | `10` |
| `margin_top` | Margem superior (mm) | `5` |
| `margin_bottom` | Margem inferior (mm) | `5` |
| `default_font` | Fonte padrão | `'Arial'` |
| `orientation` | Orientação | `'P'` (Portrait) ou `'L'` (Landscape) |

### Formatos de Papel

- `'A4'` - 210 x 297 mm (padrão)
- `'A5'` - 148 x 210 mm
- `'Letter'` - 216 x 279 mm
- `[width, height]` - Tamanho customizado em mm

### Modos de Output

```php
// Exibir no navegador (inline)
$mpdf->Output('arquivo.pdf', 'I');

// Forçar download
$mpdf->Output('arquivo.pdf', 'D');

// Salvar em arquivo
$mpdf->Output('/caminho/arquivo.pdf', 'F');

// Retornar como string
$pdfContent = $mpdf->Output('arquivo.pdf', 'S');
```

## Cabeçalhos e rodapés HTML (documentos personalizados)

Para o tipo de impressão **`documento`** (modelo da tabela `documentos` escolhido no offcanvas), contratos, locações e multas usam o **Method 2** do mPDF: `SetHTMLHeader()` / `SetHTMLFooter()` com HTML gerado a partir dos partials (`_header.php`, `_footer_assinatura.php` ou rodapé só com numeração nas multas).

Referência oficial: [Headers & Footers – Method 2 (Runtime HTML)](https://mpdf.github.io/headers-footers/method-2.html).

### Margens do corpo (`PdfHelper`)

O cabeçalho e o rodapé HTML são desenhados **na área de margem** da página. A área útil do texto precisa começar **abaixo** do header e terminar **acima** do footer. Isso é feito passando `margin_top` e `margin_bottom` **no construtor** via `PdfHelper::create([...])`:

- **Não confiar só em `@page { margin-top; margin-bottom }` no CSS do template** para isso: em fluxos com `WriteHTML()`, o mPDF pode restaurar `tMargin`/`bMargin` para `orig_tMargin`/`orig_bMargin` (valores do construtor). Se o construtor estiver em **5 mm** e o header ocupar ~35 mm, o corpo **sobrepõe** o cabeçalho.

Constantes públicas em `App\Helpers\PdfHelper` (única fonte de verdade para ajuste fino):

| Constante | mm | Uso |
|-----------|---:|-----|
| `DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM` | `35` | Topo do **corpo** quando há header HTML (contratos, locações, multas — mesmo layout de cabeçalho). |
| `DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM` | `65` | Base do **corpo** quando o rodapé inclui bloco de **assinaturas** (`_footer_assinatura.php`) — contratos e locações. |
| `DOCUMENTO_MULTAS_HTML_FOOTER_MARGIN_BOTTOM_MM` | `20` | Base do **corpo** no tipo **documento** de multas (rodapé só com `{PAGENO}` / `{nbpg}`). |

Nos controllers, o padrão é:

```php
$mpdf = PdfHelper::create(array_merge($pdfOptions, [
    'margin_top' => PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM,
    'margin_bottom' => PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM, // ou DOCUMENTO_MULTAS_* nas multas
]));
$mpdf->SetHTMLHeader($headerHtml, 'O', true); // terceiro true = aplicar também na página 1
$mpdf->SetHTMLFooter($footerHtml, 'O');
PdfHelper::writeHtml($mpdf, $html);
```

Templates `documento.php` mantêm no `@page` apenas `margin-header` / `margin-footer` (extensões mPDF), **sem** duplicar `margin-top`/`margin-bottom` do corpo — evita duas fontes conflitantes.

### Onde está implementado

| Módulo | Controller | Observação |
|--------|------------|------------|
| Contratos | `ContratosController::imprimir` (`tipo === 'documento'`) | Rodapé com assinaturas. |
| Locações | `LocacoesController::imprimir` (`tipo === 'documento'`) | Idem. |
| Multas | `MultasController::imprimir` e `enviarMulta` (`tipo === 'documento'`) | Mesmo header; envio por mensageria usa o mesmo fluxo de header/footer que a impressão inline. |

Partials: `app/Views/pages/contratos/imprimir/_partials/`, `locacoes/.../`, `multas/.../`. Estilos do header/footer: **inline** — o mPDF não processa blocos `<style>` no contexto de header/footer da mesma forma que no corpo.

## Integração com Modal de Impressão

O PDF é exibido dentro do modal de impressão fullscreen do sistema:

```javascript
// No iframe da página
window.parent.postMessage({
    action: 'openPrintModal',
    url: '/documentos/ABC123/imprimir',
    title: 'Documento'
}, '*');
```

Ver documentação: [Sistema de Iframes](./iframe-system.md#modal-de-impressao-fullscreen)

## Troubleshooting

### PDF não aparece, mostra HTML

**Causa:** Usando `Template::render()` que faz `echo + exit`

**Solução:** Use output buffering conforme documentado acima.

### Caracteres especiais não aparecem

**Causa:** Encoding incorreto

**Solução:** Certifique-se de usar `mode => 'utf-8'` e que o template tenha `<meta charset="UTF-8">`.

### Imagens não aparecem no PDF / erro 500 ao gerar PDF com imagens

**Causa comum:** URL HTTP com token (`FileHelper::url()`) ou path relativo. mPDF não consegue baixar URL HMAC-assinada — falha silenciosamente em `WriteHTML()`, fica 500 genérico.

**Solução:** Use sempre `PdfHelper::resolveImagePath($filename, $chave)` — ver seção "Imagens no PDF (logos, fotos, assinaturas)" acima. Para imagens estáticas do projeto (não do tenant), use path absoluto direto.

### PDF com várias fotos demora muito (>3s)

**Causa:** Imagens em WebP. mPDF é ~90× mais lento processando WebP que JPEG.

**Solução:** O helper já resolve isso automaticamente — ele converte WebP→JPEG temp antes de passar pro mPDF. Se ainda estiver lento, confirme que o controller usa `PdfHelper::resolveImagePath` e não `FileHelper::getPath` direto.

### Estilos CSS não funcionam

O mPDF suporta um subconjunto de CSS. Limitações:
- Não suporta `flexbox` ou `grid`
- Use `float` e `table` para layouts
- Algumas propriedades CSS3 não funcionam

### Erro `pcre.backtrack_limit` ao gerar PDF

**Sintoma:** mensagem `The HTML code size is larger than pcre.backtrack_limit 1000000` (comum em contratos/locações com documento personalizado grande).

**Causa:** o HTML final (TinyMCE em `documentos.texto`, checklists extensos, CSS + fotos) excede o limite PCRE do PHP (~1 MB por chamada a `WriteHTML()`).

**Solução:** usar sempre `PdfHelper::writeHtml($mpdf, $html)` em vez de `$mpdf->WriteHTML($html)` direto. O helper aumenta os limites PCRE e divide HTML grande em chunks nos fechamentos de tag. Os métodos `outputInline`, `generateAsString`, `outputDownload` e `saveToFile` já usam isso internamente.

**Nota:** evite `$mpdf->WriteHTML()` direto em código novo — a watermark interna do helper é a única exceção (HTML minúsculo).

### Corpo do PDF sobrepõe cabeçalho ou rodapé HTML

**Causa:** `margin_top` / `margin_bottom` do `PdfHelper::create()` menores que a altura real do HTML passado a `SetHTMLHeader` / `SetHTMLFooter`, ou dependência só de `@page` no CSS.

**Solução:** Passar as margens no construtor usando as constantes `PdfHelper::DOCUMENTO_HTML_*` (e `DOCUMENTO_MULTAS_*` para rodapé curto em multas). Ver a secção **Cabeçalhos e rodapés HTML (documentos personalizados)** neste arquivo.

## Checklist para Novos PDFs

- [ ] **USAR** `PdfHelper::create()` (ou `outputInline`/`generateAsString`) em vez de `new Mpdf()`
- [ ] **USAR** `PdfHelper::writeHtml($mpdf, $html)` para enviar HTML ao mPDF — nunca `$mpdf->WriteHTML()` direto
- [ ] **NÃO** usar `Template::render()` — usar `ob_start()` + `include` + `ob_get_clean()`
- [ ] **USAR** `PdfHelper::resolveImagePath($filename, $chave)` para qualquer imagem do tenant (logo, fotos, assinaturas) — nunca `FileHelper::url()` nem path montado na mão
- [ ] Se usar **`SetHTMLHeader` / `SetHTMLFooter`**: definir `margin_top`/`margin_bottom` no `create()` compatíveis com a altura do header/footer (constantes `DOCUMENTO_*` quando for o fluxo de documento personalizado)
- [ ] Adicionar `<meta charset="UTF-8">` no template
- [ ] Testar caracteres especiais (acentos, símbolos)
- [ ] Validar layout em diferentes tamanhos

## Watermark Lateral

Todos os PDFs gerados com `PdfHelper` incluem automaticamente a watermark rotacionada 90 graus na lateral esquerda da página.

**Características:**
- Texto rotacionado verticalmente (de baixo para cima)
- Posicionado na margem esquerda, um pouco abaixo do centro
- Fonte 8pt em cor cinza claro (#aaa)
- Aparece em todas as páginas do documento
- **Traduzido automaticamente** conforme o idioma do sistema (i18n)

**Traduções disponíveis:**

| Idioma | Texto |
|--------|-------|
| pt_BR | Sistema 7Carros.com.br |
| en_US | 7Carros.com.br System |
| es_ES | Sistema 7Carros.com.br |
| it_IT | Sistema 7Carros.com.br |
| pt_PT | Sistema 7Carros.com.br |

Para alterar o texto, edite `app/Lang/{idioma}/common.php` na chave `pdf.watermark`.

## Documentação Relacionada

- **[Documentos (modelos)](./documentos.md)** — modelos HTML usados no tipo `documento` de impressão
- **[Sistema de Iframes](./iframe-system.md)** - Modal de impressão fullscreen
- **[Arquitetura](./architecture.md)** - Estrutura de Controllers e Views
- **[Helpers](./helpers.md)** - Outros helpers disponíveis
