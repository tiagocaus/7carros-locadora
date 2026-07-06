# Sistema de Assinatura Digital

Sistema de assinatura digital para contratos, locacoes e promissorias, com armazenamento em tabela dedicada e arquivos WebP.

## Visao Geral

| Aspecto | Descricao |
|---------|-----------|
| **Tabela** | `assinaturas` - dedicada para todas as assinaturas |
| **Formato** | WebP (convertido automaticamente via ImageHelper) |
| **Armazenamento** | `/storage/uploads/{chave}/{arquivo}` |
| **URL publica** | `/files/{token}` (via FileHelper) |
| **Auditoria** | IP, user_agent, geolocalizacao, hash SHA256 |
| **Fundo** | Assinaturas devem ser compostas sobre fundo branco |

## Estrutura de Arquivos

```
app/
├── Models/
│   └── Assinatura.php          # Model principal
├── Controllers/
│   └── AssinaturaController.php # Pagina publica de assinatura
└── Views/
    └── public/assinatura/
        └── index.php            # Pagina de assinatura (canvas)
```

## Tabela `assinaturas`

```sql
CREATE TABLE assinaturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(45) NOT NULL,

    -- Vinculos (apenas um preenchido: contrato, locacao OU promissoria)
    id_contrato INT UNSIGNED NULL,
    id_locacao INT UNSIGNED NULL,
    codigo_promissoria VARCHAR(20) NULL,
    id_cliente INT UNSIGNED NULL,

    -- Dados da assinatura
    arquivo VARCHAR(255) NOT NULL,    -- ex: assinatura_abc123.webp
    hash_arquivo VARCHAR(64) NULL,    -- SHA256 para integridade

    -- Auditoria
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,

    -- Verificacao
    token_verificacao VARCHAR(64) NULL,
    verificado_em DATETIME NULL,

    -- Metadados
    tipo ENUM('cliente', 'testemunha', 'fiador', 'avalista') DEFAULT 'cliente',
    observacao TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Indices
    INDEX idx_assinaturas_chave (chave),
    INDEX idx_assinaturas_contrato (id_contrato),
    INDEX idx_assinaturas_locacao (id_locacao),
    INDEX idx_assinaturas_promissoria (chave, codigo_promissoria),
    INDEX idx_assinaturas_cliente (id_cliente),

    -- FKs
    FOREIGN KEY (id_contrato) REFERENCES contratos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_locacao) REFERENCES locacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE SET NULL
);
```

## Rotas

### Publicas (nao requerem autenticacao)

| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| GET | /assinar/{codigo} | AssinaturaController@view | Pagina publica de assinatura de contrato (`C...`), locacao (`L...`) ou promissoria (`PRO...`) |
| POST | /assinar/{codigo} | AssinaturaController@assinar | Salvar assinatura vinculando `id_contrato`, `id_locacao` ou `codigo_promissoria` |

> A rota publica resolve o tipo pelo codigo: contratos normalmente usam prefixo `C`, locacoes usam prefixo `L` e promissorias usam codigo base `PRO...`. Como a pagina pode ser aberta sem sessao autenticada (ou com sessao de outro tenant no mesmo navegador), a busca publica deve resolver o registro por codigo e usar a `chave` do proprio registro para salvar a assinatura.

## Model Assinatura.php

### Metodos de Busca

```php
use App\Models\Assinatura;

$model = new Assinatura();

// Buscar por ID
$assinatura = $model->buscarPorId($id);

// Buscar assinatura do cliente em um contrato
$assinatura = $model->buscarPorContrato($idContrato);
$assinatura = $model->buscarPorContrato($idContrato, 'testemunha');

// Buscar assinatura em locacao
$assinatura = $model->buscarPorLocacao($idLocacao);

// Buscar assinatura em promissoria agrupada
$assinatura = $model->buscarPorPromissoria($codigoBase);

// Listar todas assinaturas de um contrato
$assinaturas = $model->listarPorContrato($idContrato);

// Listar assinaturas de um cliente
$assinaturas = $model->listarPorCliente($idCliente);
```

### Verificacoes

```php
// Verificar se contrato tem assinatura
if ($model->contratoTemAssinatura($idContrato)) {
    // ja assinado
}

// Verificar se locacao tem assinatura
if ($model->locacaoTemAssinatura($idLocacao)) {
    // ja assinado
}

// Verificar se promissoria tem assinatura
if ($model->promissoriaTemAssinatura($codigoBase)) {
    // ja assinado
}

// Verificar integridade do arquivo (hash SHA256)
$integro = $model->verificarIntegridade($id);
```

### Salvar Assinatura

```php
$id = $model->salvar([
    'base64' => $imagemBase64,      // Obrigatorio
    'id_contrato' => $contratoId,   // OU id_locacao
    'codigo_promissoria' => null,   // OU codigo base da promissoria
    'id_cliente' => $clienteId,     // Opcional
    'ip_address' => $ip,            // Obrigatorio
    'user_agent' => $userAgent,     // Opcional
    'latitude' => $lat,             // Opcional
    'longitude' => $lng,            // Opcional
    'tipo' => 'cliente',            // cliente|testemunha|fiador|avalista
    'observacao' => 'Obs',          // Opcional
    'chave' => $chave               // Opcional (usa sessao)
]);
```

### Excluir Assinatura

```php
// Excluir uma assinatura (registro + arquivo)
$model->excluir($id);

// Excluir todas assinaturas de um contrato
$count = $model->excluirPorContrato($idContrato);

// Excluir todas assinaturas de uma locacao
$count = $model->excluirPorLocacao($idLocacao);

// Excluir todas assinaturas de uma promissoria
$count = $model->excluirPorPromissoria($codigoBase);
```

### Verificacao Externa

```php
// Gerar token para verificacao externa
$token = $model->gerarTokenVerificacao($id);
// Resultado: token de 64 caracteres

// Buscar assinatura por token
$assinatura = $model->buscarPorToken($token);

// Registrar que foi verificada
$model->registrarVerificacao($id);
```

## Fluxo de Assinatura

### 1. Funcionario envia link

Na listagem de contratos, locacoes e promissorias, o modal "Link de Assinatura" permite copiar,
abrir ou enviar o link por WhatsApp.

O envio por WhatsApp usa `queue_template_message('signature_request', 'whatsapp', ...)`.
O template `signature_request` tambem existe para email e SMS, usando a variavel
`{{outros.link_assinatura}}`.

### 2. Cliente acessa pagina publica

A pagina `/assinar/{codigo}` exibe:
- Resumo do contrato, locacao ou promissoria (cliente, periodo, valor e veiculo quando aplicavel)
- Canvas para desenhar assinatura
- Botoes limpar/assinar
- Modais locais de alerta/confirmacao (pagina publica pode abrir fora do `app.php`)

O canvas da assinatura deve ser inicializado/exportado com fundo branco. Nunca salve assinatura com transparencia, pois conversoes para JPEG/PDF podem renderizar o fundo transparente como preto.

### 3. Cliente desenha e confirma

```javascript
// Frontend coleta dados
const signatureData = canvas.toDataURL('image/png');

// Obtem geolocalizacao obrigatoria para auditoria juridica
navigator.geolocation.getCurrentPosition(...);

// Envia para servidor
fetch(window.location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        assinatura: signatureData,
        latitude: lat,
        longitude: lng
    })
});
```

### 4. Servidor processa

```php
// AssinaturaController::assinar()
$tipo = $documento['tipo']; // contrato, locacao ou promissoria
$registro = $documento['registro'];

$assinaturaModel->salvar([
    'base64' => $dados['assinatura'],
    'id_contrato' => $tipo === 'contrato' ? $registro['id'] : null,
    'id_locacao' => $tipo === 'locacao' ? $registro['id'] : null,
    'codigo_promissoria' => $tipo === 'promissoria' ? $registro['codigo_base'] : null,
    'id_cliente' => $registro['id_cliente'] ?? null,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'latitude' => $dados['latitude'],
    'longitude' => $dados['longitude'],
    'tipo' => 'cliente',
    'chave' => $registro['chave']
]);
```

`ImageHelper::save()` compoe imagens com prefixo `assinatura*` sobre branco antes de converter/salvar. Isso cobre assinatura publica e checklist (`assinatura_checklist`).

### 5. Visualizacao interna da assinatura

As listagens de contratos e locacoes usam o modal global `openAssinaturaModal`.

Endpoints internos retornam o mesmo formato:

```json
{
  "success": true,
  "data": {
    "id": 123,
    "url": "/files/token",
    "data_assinatura": "04/05/2026 14:30",
    "ip": "203.0.113.10"
  }
}
```

- Contratos: `GET /api/contratos/{id}/assinatura`
- Locacoes: `GET /api/locacoes/{id}/assinatura`
- Reset: o modal global devolve `contratoId` ou `locacaoId` no `postMessage` `resetarAssinatura`.

## Exibicao em Templates de Impressao

```php
// No Controller (ContratosController::imprimir)
$assinaturaModel = new Assinatura();
$assinatura = $assinaturaModel->buscarPorContrato($id);

// Passar para view
$this->view('pages/contratos/imprimir/documento', [
    'contrato' => $contrato,
    'assinatura' => $assinatura
]);
```

```php
// Na View (documento.php)
<?php if (!empty($assinatura['url'])): ?>
<img src="<?= htmlspecialchars($assinatura['url']) ?>" alt="Assinatura" style="max-height: 50px;">
<?php endif; ?>
```

Nos rodapes de impressao de contratos e locacoes (`_footer_assinatura.php`), quando existe
`$assinaturaPath`, a linha de assinatura deve ficar imediatamente abaixo da imagem. Quando
nao houver assinatura digital, mantenha o espacamento vertical para assinatura manual.

## Tipos de Assinatura

| Tipo | Descricao |
|------|-----------|
| `cliente` | Assinatura do locatario (padrao) |
| `testemunha` | Assinatura de testemunha |
| `fiador` | Assinatura do fiador |
| `avalista` | Assinatura do avalista |

## Seguranca

- **Hash SHA256**: Verificacao de integridade do arquivo
- **Auditoria completa**: IP, user_agent, geolocalizacao, timestamp
- **Token de verificacao**: Para verificacao externa por terceiros
- **Multi-tenancy**: Isolamento por chave do tenant
- **URLs seguras**: Tokens HMAC via FileHelper

## Permissoes

| Permissao | Descricao |
|-----------|-----------|
| `contratos.assinatura` | Gerenciar assinatura digital de contratos |
| `locacoes.assinatura` | Gerenciar assinatura digital de locacoes |

## Migracao de Dados

A migracao `00154_create_assinaturas_table.php` migrou dados das colunas antigas:
- `contratos.assinatura` -> tabela `assinaturas`
- `locacoes.assinatura` -> tabela `assinaturas`

A migracao `00155_convert_base64_assinaturas.php` converteu assinaturas base64 legadas para arquivos.

A migracao `00156_convert_signatures_to_webp.php` converte assinaturas PNG para WebP.
