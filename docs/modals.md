# Sistema de Modais

## REGRAS CRITICAS

> **LEIA ANTES DE IMPLEMENTAR QUALQUER MODAL**

### 1. NUNCA use `alert()` nativo do JavaScript

```javascript
// ERRADO - NUNCA FACA ISSO
alert('Mensagem de erro');

// CORRETO - Use o modal de alerta global
window.parent.postMessage({
    action: 'openAlert',
    message: 'Mensagem de erro'
}, '*');
```

O `alert()` nativo:
- Bloqueia a thread do navegador
- Tem aparencia inconsistente entre navegadores
- Nao segue o design system do projeto
- Pode causar problemas em contexto de iframe

> Em paginas publicas standalone que nao passam pelo layout `app.php` (ex: `/assinar/{codigo}`), crie modal local equivalente na propria pagina. Ainda assim, **nunca** use `alert()` ou `confirm()` nativos.

### 2. Modais Fullscreen DEVEM estar no `app.php`

Se o modal precisa aparecer na **tela completa** (cobrindo toda a aplicacao), ele DEVE:
- Ter o HTML no arquivo `app/Views/layouts/app.php`
- Ser aberto via `postMessage` do iframe
- Ter as funcoes JavaScript no `app.php`

```
ERRADO: Modal no arquivo do iframe (ex: contratos/index.php)
        -> Modal aparece apenas dentro do iframe, nao fullscreen

CORRETO: Modal no app.php, aberto via postMessage
        -> Modal aparece fullscreen, cobrindo toda a tela
```

### 3. Comunicacao Iframe <-> Parent via postMessage

```javascript
// Iframe envia mensagem para o parent
window.parent.postMessage({
    action: 'nomeDoModal',
    // dados necessarios...
}, '*');

// Parent (app.php) escuta e trata
window.addEventListener('message', function(event) {
    if (event.data && event.data.action === 'nomeDoModal') {
        // Abrir modal...
    }
});
```

---

## Arquivos Principais

| Arquivo | Descricao |
|---------|-----------|
| `app/Views/layouts/app.php` | Layout principal - contem todos os modais globais |
| `public/assets/css/components.css` | CSS dos modais (classes `.modal-*`) |
| `_backup/v2/componentes.html` | Exemplos visuais de componentes |

---

## Classes CSS de Modais

| Classe | Descricao |
|--------|-----------|
| `.modal-overlay` | Container externo (overlay escuro) - `display: none` por padrao |
| `.modal-overlay.open` | Classe para exibir o modal (`display: flex`) |
| `.modal-box` | Container branco do modal |
| `.modal-title` | Titulo do modal |
| `.modal-message` | Conteudo/corpo do modal |
| `.modal-actions` | Container dos botoes de acao |

### Estrutura HTML Basica

```html
<div id="meuModal" class="modal-overlay">
    <div class="modal-box">
        <h3 class="modal-title">Titulo do Modal</h3>
        <p class="modal-message">Conteudo aqui</p>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button class="btn-blue" onclick="confirmar()">Confirmar</button>
        </div>
    </div>
</div>
```

### Abrir/Fechar Modal

```javascript
// Abrir
document.getElementById('meuModal').classList.add('open');
document.body.classList.add('modal-open'); // Previne scroll

// Fechar
document.getElementById('meuModal').classList.remove('open');
document.body.classList.remove('modal-open');
```

---

## Modais Principais

### 1. alertModal - Alerta Simples

**Substitui:** `alert()` nativo

```
+----------------------------------+
|           Atencao                |
|                                  |
|  [Mensagem de alerta aqui]       |
|                                  |
|            [ OK ]                |
+----------------------------------+
```

**Uso:**
```javascript
window.parent.postMessage({
    action: 'openAlert',
    message: 'Operacao realizada com sucesso!'
}, '*');
```

---

### 2. genericConfirmModal - Confirmacao Generica

**Substitui:** `confirm()` nativo

```
+----------------------------------+
|           Confirmar              |
|                                  |
|  Deseja continuar com a acao?    |
|                                  |
|    [Cancelar]  [Confirmar]       |
+----------------------------------+
```

**Uso:**
```javascript
window.parent.postMessage({
    action: 'openGenericConfirmModal',
    title: 'Confirmar Acao',
    message: 'Deseja continuar?',
    confirmText: 'Sim, continuar'
}, '*');
```

---

### 3. deleteConfirmationModal - Exclusao com Protecao

```
+----------------------------------+
|       Confirmar Exclusao         |
|                                  |
|  Deseja excluir "Joao Silva"?    |
|                                  |
|  Digite EXCLUIR: [__________]    |
|                                  |
|  [Cancelar] [Confirmar Exclusao] |
+----------------------------------+
```

**Modos de confirmacao:**
- `none` - Sem confirmacao de texto
- `text` - Digite "EXCLUIR"
- `name` - Digite o nome do registro

**Uso:**
```javascript
window.parent.postMessage({
    action: 'openDeleteModal',
    recordId: '123',
    recordName: 'Joao Silva',
    recordType: 'cliente',
    confirmType: 'text'
}, '*');
```

---

### 4. validationModal - Erros de Formulario

```
+----------------------------------+
|  ! Campos Obrigatorios           |
|                                  |
|  Preencha os campos:             |
|  +----------------------------+  |
|  | Aba Dados Pessoais         |  |
|  | x Nome                     |  |
|  | x CPF                      |  |
|  | Aba Endereco               |  |
|  | x CEP                      |  |
|  +----------------------------+  |
|          [Entendi]               |
+----------------------------------+
```

**Uso interno** - Chamado automaticamente pelo sistema de validacao de formularios

---

## Acoes postMessage Disponiveis

### Alertas e Confirmacoes

| Acao | Descricao | Parametros |
|------|-----------|------------|
| `openAlert` | Alerta simples (substitui `alert()`) | `message` |
| `openDeleteModal` | Confirmacao de exclusao | `recordId`, `recordName`, `recordType`, `confirmType` |
| `openGenericConfirmModal` | Confirmacao generica | `title`, `message`, `confirmText`, `confirmColor` |

### Modais Especializados

| Acao | Descricao | Parametros |
|------|-----------|------------|
| `openPrintModal` | Visualizacao de PDF fullscreen | `url`, `title` |
| `openAssinaturaModal` | Visualizacao de assinatura de contrato/locacao | `tipo`, `contratoId` ou `locacaoId`, `codigo`, `data_assinatura`, `ip`, `url` |
| `openInputModal` | Input de texto | `title`, `label`, `value`, `maxLength`, `callback` |
| `openEditBatchModal` | Edicao em lote | `title`, `fields`, `callbackId` |
| `openAddCartaoLocacaoModal` | Adicionar cartao de credito (locacao) | `id_cliente`, `gateways` |
| `openPromissoriaParcelaModal` | Adicionar/editar parcela de promissoria | `mode`, `id`, `valor_parcela`, `data_vencimento` |
| `openClienteImportacaoModal` | Selecionar filial, enviar CSV e acompanhar importacao de clientes | - |

### Modal de importacao de clientes

O modal `clienteImportacaoModal` fica em `app/Views/layouts/app.php` porque deve cobrir toda a aplicacao. A lista de clientes solicita sua abertura pelo iframe:

```javascript
window.parent.postMessage({ action: 'openClienteImportacaoModal' }, '*');
```

O documento pai carrega apenas as filiais ativas permitidas ao usuario, envia `id_matriz_filial` e `arquivo` para `/api/clientes/importar` e mantem no proprio modal os estados de envio, processamento, erro e sucesso. Durante a requisicao, o modal nao pode ser fechado.

Documentos ja cadastrados no tenant e repeticoes posteriores do mesmo documento
no CSV sao ignorados sem bloquear os demais clientes. No sucesso, a API retorna
as quantidades `importados` e `ignorados`, alem de `ignorados_detalhes` com a
linha e o motivo; o modal deve apresentar esse resumo ao usuario.

Quando a importacao termina, o pai notifica exclusivamente o iframe que iniciou a operacao:

```javascript
sourceIframe.postMessage({
    action: 'clienteImportacaoConcluida',
    importados: quantidade
}, '*');
```

Ao fechar, o pai envia `clienteImportacaoModalClosed` para o iframe restaurar o foco no botao que abriu o modal.

Os erros do backend devem ser renderizados com `textContent` e agrupados por linha. Nunca injete mensagens do CSV com `innerHTML`.

### Modais de Midia

| Acao | Descricao | Parametros |
|------|-----------|------------|
| `openFotoModal` | Escolha de foto (arquivo/camera) | - |
| `openCameraModal` | Captura de foto pela camera | - |
| `openVideoModal` | Visualizacao de video | `videoUrl` |

---

## Exemplos de Uso

### Alerta Simples (Substitui alert())

```javascript
// NO IFRAME
window.parent.postMessage({
    action: 'openAlert',
    message: 'Operacao realizada com sucesso!'
}, '*');
```

### Modal de Exclusao

```javascript
// NO IFRAME
window.parent.postMessage({
    action: 'openDeleteModal',
    recordId: '123',
    recordName: 'Joao Silva',
    recordType: 'cliente',
    confirmType: 'text' // 'none', 'text', 'name'
}, '*');

// Escutar confirmacao
window.addEventListener('message', function(event) {
    if (event.data && event.data.action === 'confirmDelete') {
        // Executar exclusao
        excluirRegistro(event.data.recordId);
    }
});
```

### Modal de Impressao

```javascript
// NO IFRAME
window.parent.postMessage({
    action: 'openPrintModal',
    url: '/contratos/123/imprimir',
    title: 'Contrato de Locacao'
}, '*');
```

### Parcela de Promissoria

O formulario de parcela e um modal global em `app.php`. O iframe abre com
`openPromissoriaParcelaModal` e recebe `promissoriaParcelaModalConfirmado` com
`id`, `valor_parcela` e `data_vencimento`. Durante o POST, o modal permanece
aberto e bloqueado; o iframe responde com `promissoriaParcelaModalResultado`,
fechando no sucesso ou liberando os campos no erro.

---

## Como Criar um Novo Modal Global

### Passo 1: Adicionar HTML no app.php

```html
<!-- Em app/Views/layouts/app.php, apos outros modais -->
<div id="meuNovoModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 500px;">
        <h3 class="modal-title">Titulo</h3>
        <div class="modal-message">
            <!-- Conteudo dinamico -->
            <p id="meuNovoModalConteudo"></p>
        </div>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeMeuNovoModal()">Fechar</button>
            <button class="btn-blue" onclick="confirmMeuNovoModal()">Confirmar</button>
        </div>
    </div>
</div>
```

### Passo 2: Adicionar Funcoes JavaScript no app.php

```javascript
// Em app.php, na secao de scripts
let meuNovoModalIframeSource = null;

window.openMeuNovoModal = function(data, source) {
    meuNovoModalIframeSource = source;

    document.getElementById('meuNovoModalConteudo').textContent = data.conteudo || '';

    document.getElementById('meuNovoModal').classList.add('open');
    document.body.classList.add('modal-open');
};

window.closeMeuNovoModal = function() {
    document.getElementById('meuNovoModal').classList.remove('open');
    document.body.classList.remove('modal-open');
    meuNovoModalIframeSource = null;
};

window.confirmMeuNovoModal = function() {
    if (meuNovoModalIframeSource) {
        meuNovoModalIframeSource.postMessage({
            action: 'meuNovoModalConfirmado',
            // dados de retorno...
        }, '*');
    }
    closeMeuNovoModal();
};

// Fechar ao clicar no overlay
document.getElementById('meuNovoModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMeuNovoModal();
    }
});
```

### Passo 3: Adicionar Handler no Event Listener

```javascript
// Em app.php, dentro do window.addEventListener('message', ...)
} else if (event.data && event.data.action === 'openMeuNovoModal') {
    openMeuNovoModal(event.data, event.source);
}
```

### Passo 4: Usar no Iframe

```javascript
// No arquivo do iframe (ex: minha-pagina/index.php)
window.parent.postMessage({
    action: 'openMeuNovoModal',
    conteudo: 'Texto do modal'
}, '*');

// Escutar resposta
window.addEventListener('message', function(event) {
    if (event.data && event.data.action === 'meuNovoModalConfirmado') {
        // Tratar confirmacao
    }
});
```

---

## Documentacao Relacionada

- **[Sistema de Iframes](./iframe-system.md)** - Comunicacao iframe <-> parent
- **[Lista de Componentes](/_backup/v2/componentes.html)** - Exemplos visuais
