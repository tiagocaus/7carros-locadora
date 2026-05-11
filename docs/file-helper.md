# Sistema de Upload de Arquivos

O sistema possui dois helpers para gerenciar uploads:

| Helper | Quando Usar |
|--------|-------------|
| `FileHelper` | Arquivos que NAO precisam de conversao (PDFs, arquivos ja otimizados) |
| `ImageHelper` | Imagens que devem ser convertidas para WebP (fotos, logos, assinaturas) |

---

## FileHelper

O `FileHelper` e um helper centralizado para gerenciar uploads de arquivos no sistema, ocultando o caminho real atraves de URLs com tokens seguros.

## Metodos Principais

| Metodo | O que faz |
|--------|-----------|
| `save($base64, $prefix)` | Salva arquivo base64 no storage, retorna nome do arquivo |
| `url($filename, $chave)` | Gera URL publica segura `/files/{token}` |
| `delete($filename, $chave)` | Deleta arquivo do storage |
| `exists($filename, $chave)` | Verifica se arquivo existe |
| `getPath($filename, $chave)` | Retorna caminho absoluto do arquivo |
| `getMimeType($filepath)` | Detecta tipo MIME do arquivo |
| `generateToken($filename, $chave)` | Gera token HMAC para URL segura |
| `decodeToken($token)` | Decodifica token para obter chave/filename |

## Visao Geral

| Aspecto | Descricao |
|---------|-----------|
| **URL publica** | `/files/{token}` - Token unico, impossivel adivinhar |
| **Tipos de arquivo** | Qualquer arquivo (sem restricao) |
| **Armazenamento** | `/storage/uploads/{chave}/{filename}` |
| **Token** | HMAC-SHA256 do payload + chave secreta |
| **Multi-tenancy** | Validacao da chave do tenant antes de servir arquivo |

## Arquitetura

### Fluxo de Upload

```
[Frontend] -> base64 -> [Controller] -> FileHelper::save() -> /storage/uploads/{chave}/file.ext
                                                           |
                                              Retorna: nome do arquivo
```

### Fluxo de Exibicao

```
[View] -> FileHelper::url($filename, $chave) -> /files/{token}
                                                     |
[Router] -> FileController::serve($token)
                                                     |
                            Decodifica token -> Valida chave -> Serve arquivo
```

## Uso do FileHelper

### Salvar arquivo base64

```php
use App\Helpers\FileHelper;

// Salvar imagem base64
$filename = FileHelper::save($base64, 'logo');
// Retorna: logo_6748a1b2c3d4e.jpg

// Salvar em banco (apenas o nome do arquivo)
$dados['logo'] = $filename;
```

### Gerar URL publica

```php
// Gerar URL com token seguro
$url = FileHelper::url($registro['logo'], $registro['chave']);
// Retorna: /files/Y2hhdmUxMjN8bG9nb18xMjMuanBn1a2b3c4d5e6f7g8h

// Usar na API
$registro['logo_url'] = FileHelper::url($registro['logo'], $registro['chave']);
```

### Deletar arquivo

```php
// Deletar arquivo do storage
FileHelper::delete($registro['logo'], $registro['chave']);
```

### Verificar existencia

```php
// Verificar se arquivo existe
if (FileHelper::exists($filename, $chave)) {
    // arquivo existe
}
```

### Obter caminho completo

```php
// Obter caminho absoluto do arquivo
$path = FileHelper::getPath($filename, $chave);
// Retorna: /var/www/.../storage/uploads/{chave}/{filename}
```

> ⚠️ **Para imagens em PDFs do mPDF**, NÃO use `FileHelper::url()` (URL com token HMAC, mPDF não consegue baixar) nem `FileHelper::getPath()` direto (perde conversão WebP→JPEG, deixa o PDF ~90× mais lento). Use sempre `PdfHelper::resolveImagePath($filename, $chave)` — ver [pdf.md → Imagens no PDF](./pdf.md#imagens-no-pdf-logos-fotos-assinaturas).

### Obter MIME type

```php
// Obter tipo MIME do arquivo
$mimeType = FileHelper::getMimeType($filepath);
// Retorna: image/jpeg, application/pdf, etc.
```

## Metodos Disponiveis

| Metodo | Descricao | Retorno |
|--------|-----------|---------|
| `save(string $base64, string $prefix)` | Salva arquivo base64 | `string\|null` filename |
| `url(?string $filename, ?string $chave)` | Gera URL com token | `string` URL |
| `delete(string $filename, ?string $chave)` | Deleta arquivo | `bool` |
| `exists(string $filename, ?string $chave)` | Verifica existencia | `bool` |
| `getPath(string $filename, ?string $chave)` | Caminho absoluto | `string` |
| `getMimeType(string $filepath)` | MIME type | `string` |
| `generateToken(string $filename, string $chave)` | Gera token | `string` |
| `decodeToken(string $token)` | Decodifica token | `array\|null` |

## Estrutura do Token

O token e composto por:

```
[base64url(chave|filename)][assinatura_hmac_16_chars]
```

Exemplo:
- Payload: `chave123|logo_abc.jpg`
- Encoded: `Y2hhdmUxMjN8bG9nb19hYmMuanBn`
- Signature: `1a2b3c4d5e6f7g8h`
- Token final: `Y2hhdmUxMjN8bG9nb19hYmMuanBn1a2b3c4d5e6f7g8h`

## Seguranca

- **Token nao expoe caminho real** - O caminho e codificado e assinado
- **Validacao de assinatura** - HMAC-SHA256 usando `APP_KEY`
- **Validacao de tenant** - Multi-tenancy respeitado via chave
- **Headers seguros** - Content-Type, X-Content-Type-Options: nosniff
- **Cache otimizado** - Cache-Control, ETag, Last-Modified

## Tipos de Arquivo Suportados

O helper detecta automaticamente o tipo do arquivo pelo prefixo base64 ou magic bytes:

| Tipo | Extensao | MIME Type |
|------|----------|-----------|
| Imagens | jpg, jpeg, png, gif, webp, svg | image/* |
| Documentos | pdf, doc, docx, xls, xlsx | application/* |
| Texto | txt, csv | text/* |
| Compactados | zip | application/zip |

## Exemplo de Uso em Controller

```php
use App\Helpers\FileHelper;

class MatrizFilialController
{
    public function index(Request $request): void
    {
        $registros = $model->listarPaginado($page, $perPage, $search);

        // Adiciona logo_url para cada registro
        foreach ($registros as &$registro) {
            $registro['logo_url'] = !empty($registro['logo'])
                ? FileHelper::url($registro['logo'], $registro['chave'])
                : '';
        }

        Response::json(['success' => true, 'data' => $registros]);
    }

    public function store(Request $request): void
    {
        $logoBase64 = $request->input('logo_base64', '');
        if (!empty($logoBase64)) {
            $filename = FileHelper::save($logoBase64, 'logo');
            if ($filename) {
                $dados['logo'] = $filename;
            }
        }
    }

    public function destroy(Request $request, int $id): void
    {
        // Apagar arquivo antes de deletar registro
        if (!empty($registro['logo'])) {
            FileHelper::delete($registro['logo'], $registro['chave']);
        }
    }
}
```

## Exemplo de Uso em View (JavaScript)

```javascript
// A API retorna logo_url pronta para uso
const result = await API.get('/api/matrizes-filiais');

result.data.forEach(item => {
    // Usar logo_url diretamente
    const logo = item.logo_url || '/assets/img/logo_padrao.png';

    // Renderizar
    tableRow += `<img src="${logo}" alt="Logo">`;
});
```

## Rota Publica

A rota `/files/{token}` e publica (nao requer autenticacao) para permitir exibicao de imagens em qualquer contexto.

```php
// app/Routes/web.php
$router->get('/files/{token}', [FileController::class, 'serve']);
```

## Configuracao

**Obrigatório:** `APP_KEY` deve estar definido no `.env`. Se ausente (ou igual ao default `default_secret_key_change_me`), `FileHelper::generateToken()` e `decodeToken()` lançam `RuntimeException` — qualquer URL de arquivo quebra.

```env
APP_KEY=sua_chave_secreta_muito_segura_aqui
```

Esta chave e usada como segredo HMAC-SHA256 para assinar/validar os tokens. Se for alterada, todos os tokens existentes serao invalidados.

---

## ImageHelper

O `ImageHelper` e uma extensao do FileHelper especializada em imagens, com conversao automatica para WebP (30% menor que PNG).

### Metodos

| Metodo | Descricao |
|--------|-----------|
| `save($base64, $prefix)` | Salva imagem convertendo para WebP |
| `validate($base64)` | Valida imagem (tipo, tamanho, dimensoes) |
| `url($filename, $chave)` | Gera URL (delega para FileHelper) |
| `delete($filename, $chave)` | Deleta arquivo (delega para FileHelper) |
| `exists($filename, $chave)` | Verifica existencia (delega para FileHelper) |

### Uso Basico

```php
use App\Helpers\ImageHelper;

// Salvar imagem (converte automaticamente para WebP)
$filename = ImageHelper::save($base64, 'assinatura');
// Resultado: assinatura_6748a1b2c3d4e.webp

// Forcar PNG (quando necessario)
$filename = ImageHelper::save($base64, 'logo', format: 'png');
// Resultado: logo_6748a1b2c3d4e.png

// PDF mantem extensao original
$filename = ImageHelper::save($pdfBase64, 'documento');
// Resultado: documento_6748a1b2c3d4e.pdf
```

### Parametros Opcionais

```php
ImageHelper::save(
    base64: $dados,
    prefix: 'foto',
    format: 'webp',    // 'webp' (padrao), 'png', ou 'original'
    quality: 80,       // Qualidade 0-100 (padrao 80)
    chave: $chave      // Chave do tenant (padrao: sessao)
);
```

### Validacao

```php
$result = ImageHelper::validate($base64);
// ['valid' => true, 'error' => null, 'mime' => 'image/png']

if (!$result['valid']) {
    throw new Exception($result['error']);
}
```

### Tipos Suportados

| Entrada | Saida |
|---------|-------|
| PNG, JPG, GIF, WebP | WebP (ou PNG se especificado) |
| PDF | PDF (mantem original) |
| Outros | Rejeitado |

### Beneficios do WebP

- ~30% menor que PNG com mesma qualidade
- Suporte a transparencia
- Compativel com todos os navegadores modernos

### Exemplo de Uso em Controller

```php
use App\Helpers\ImageHelper;

class FotoController
{
    public function store(Request $request): void
    {
        $fotoBase64 = $request->input('foto_base64', '');

        // Validar antes de salvar
        $validation = ImageHelper::validate($fotoBase64);
        if (!$validation['valid']) {
            Response::json(['success' => false, 'message' => $validation['error']]);
            return;
        }

        // Salvar (converte para WebP automaticamente)
        $filename = ImageHelper::save($fotoBase64, 'foto_cliente');

        if ($filename) {
            $dados['foto'] = $filename;
            // Salvar no banco...
        }
    }
}
```
