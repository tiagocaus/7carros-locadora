# Sistema de Iframes e Navegação por Abas

## Visão Geral

O sistema utiliza uma arquitetura de **navegação por abas com iframes**, onde cada página é carregada dentro de um iframe no layout principal. Isso permite:

- Múltiplas páginas abertas simultaneamente em abas
- Isolamento de contexto entre páginas
- Loading visual durante carregamento de conteúdo

## Arquivos Principais

| Arquivo | Descrição |
|---------|-----------|
| `app/Views/layouts/app.php` | Layout principal com sidebar de abas |
| `app/Views/layouts/iframe.php` | Layout das páginas carregadas em iframes |
| `public/assets/js/dashboard.js` | Lógica de navegação e comunicação |
| `public/assets/css/components.css` | Estilos do spinner de loading |

## Fluxo de Navegação

```
1. Usuário clica em item do menu
2. dashboard.js chama openOrSwitchToTab()
3. Nova aba é criada na sidebar
4. Spinner de loading aparece
5. Iframe é criado com src apontando para /pages/...
6. Página carrega usando layout iframe.php
7. Quando pronto, iframe envia postMessage('iframeReady')
8. dashboard.js escuta a mensagem e remove o spinner
9. Iframe fica visível com conteúdo carregado
```

## Helper `pageLoading`

Disponível globalmente em todas as páginas que usam `layouts.iframe`.

### Métodos

| Método | Descrição |
|--------|-----------|
| `pageLoading.start()` | Indica início de carregamento assíncrono |
| `pageLoading.done()` | Indica fim de carregamento assíncrono |

### Como Funciona

```javascript
window.pageLoading = {
    _pending: 0,    // Contador de carregamentos pendentes
    _sent: false,   // Flag para evitar envio duplicado

    start() { this._pending++; },
    done()  { this._pending--; this._notify(); },

    _notify() {
        if (this._pending <= 0 && !this._sent) {
            this._sent = true;
            window.parent.postMessage({ action: 'iframeReady' }, '*');
        }
    }
};
```

### Comportamento Automático

Para páginas **sem carregamento AJAX**, o `iframeReady` é enviado automaticamente após `DOMContentLoaded`:

```javascript
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.pageLoading._notify();
    }, 10);
});
```

## Páginas com Carregamento AJAX

Para páginas que fazem requisições AJAX para carregar dados (como listas, tabelas, etc.), é necessário usar `start()` e `done()` manualmente.

### Exemplo: Página de Logs

```javascript
(function() {
    // 1. Indicar que há carregamento assíncrono pendente
    window.pageLoading.start();

    let isFirstLoad = true;

    async function carregarDados() {
        try {
            const result = await API.get('/api/dados');

            if (result.success) {
                renderizarDados(result.data);

                // 2. Na primeira carga, indicar que terminou
                if (isFirstLoad) {
                    isFirstLoad = false;
                    window.pageLoading.done();
                }
            }
        } catch (error) {
            mostrarErro(error.message);

            // 3. Mesmo com erro, liberar o loading
            if (isFirstLoad) {
                isFirstLoad = false;
                window.pageLoading.done();
            }
        }
    }

    // Carregar dados ao inicializar
    carregarDados();
})();
```

### Por que usar `isFirstLoad`?

A flag evita que `done()` seja chamado múltiplas vezes em paginações ou buscas subsequentes. O loading deve aparecer apenas na **primeira carga** da página.

## Comunicação Iframe ↔ Parent

O sistema usa `postMessage` para comunicação entre iframes e o layout principal.

### Ações Disponíveis

| Action | Direção | Descrição |
|--------|---------|-----------|
| `iframeReady` | iframe → parent | Indica que página terminou de carregar |
| `navigate` | iframe → parent | Navegar para outra página no mesmo iframe |
| `openDeleteModal` | iframe → parent | Abrir modal de confirmação de exclusão |
| `openOffcanvasContent` | iframe → parent | Abrir painel lateral com conteúdo HTML |
| `openPrintModal` | iframe → parent | Abrir modal de impressão fullscreen |
| `confirmDelete` | parent → iframe | Confirmar exclusão de registro |

### Exemplo: Navegar para Outra Página

```javascript
// Dentro do iframe
window.parent.postMessage({
    action: 'navigate',
    page: '/pages/clientes/editar/123'
}, '*');
```

### Exemplo: Abrir Painel Lateral

```javascript
// Dentro do iframe
window.parent.postMessage({
    action: 'openOffcanvasContent',
    content: '<div>Conteúdo HTML aqui</div>',
    title: 'Detalhes',
    width: '500px'
}, '*');
```

## Modal de Impressao Fullscreen

Modal responsivo para visualizacao e impressao de documentos (promissorias, contratos, faturas, etc.).

### Comportamento Responsivo

| Dispositivo | Tamanho | Caracteristicas |
|-------------|---------|-----------------|
| Desktop/Tablet | 90% da tela | Centralizado, bordas arredondadas |
| Mobile | 100% da tela | Fullscreen, sem bordas |

### Como Usar

```javascript
// Dentro do iframe
window.parent.postMessage({
    action: 'openPrintModal',
    url: '/promissorias/123/imprimir',
    title: 'Nota Promissoria'
}, '*');
```

### Parametros

| Parametro | Tipo | Obrigatorio | Descricao |
|-----------|------|-------------|-----------|
| `action` | string | Sim | Deve ser `'openPrintModal'` |
| `url` | string | Sim | URL do documento/PDF a ser carregado |
| `title` | string | Nao | Titulo exibido no header (padrao: 'Visualizar Impressao') |

### Funcoes JavaScript Globais

| Funcao | Descricao |
|--------|-----------|
| `openPrintModal(url, title)` | Abre o modal com a URL especificada |
| `closePrintModal()` | Fecha o modal e limpa o iframe |
| `executePrint()` | Aciona window.print() no iframe |

### Interacoes do Usuario

- **ESC**: Fecha o modal
- **Clique fora**: Fecha o modal
- **Botao X**: Fecha o modal
- **Botao Fechar**: Fecha o modal
- **Botao Imprimir**: Aciona impressao do documento

### Exemplo Completo

```javascript
window.imprimirDocumento = function(tipo, codigo) {
    if (window.parent !== window) {
        window.parent.postMessage({
            action: 'openPrintModal',
            url: `/${tipo}/${codigo}/imprimir`,
            title: 'Visualizar Documento'
        }, '*');
    } else {
        // Fallback se nao estiver em iframe
        window.open(`/${tipo}/${codigo}/imprimir`, '_blank');
    }
};
```

### CSS Classes

Localizacao: `public/assets/css/components.css`

| Classe | Descricao |
|--------|-----------|
| `.print-modal-overlay` | Overlay escuro de fundo |
| `.print-modal-container` | Container do modal |
| `.print-modal-header` | Header com titulo e botao fechar |
| `.print-modal-body` | Corpo com iframe |
| `.print-modal-footer` | Footer com botoes de acao |

## CSS do Spinner de Loading

Localização: `public/assets/css/components.css`

```css
/* Tab Loading Spinner */
.tab-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: calc(100vh - 200px);
    min-height: 600px;
}

.tab-loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: #0ea5e9;
    border-radius: 50%;
    animation: tab-spin 0.8s linear infinite;
}

@keyframes tab-spin {
    to { transform: rotate(360deg); }
}
```

## Checklist para Novas Páginas

### Página sem AJAX (formulários, páginas estáticas)

- [x] Usar `@extends('layouts.iframe')`
- [x] Loading funciona automaticamente

### Página com AJAX (listas, tabelas com dados do servidor)

- [x] Usar `@extends('layouts.iframe')`
- [x] Chamar `window.pageLoading.start()` no início do script
- [x] Chamar `window.pageLoading.done()` após carregar dados
- [x] Usar flag `isFirstLoad` para evitar chamadas duplicadas
- [x] Tratar erros e chamar `done()` mesmo em caso de falha

## Ordem de Carregamento no iframe.php

```
1. CSS (Tailwind, FontAwesome, custom)
2. Conteúdo HTML (@yield('content'))
3. Scripts base (api.js, currency.js, etc.)
4. Helper pageLoading ← DEVE vir antes de @yield('scripts')
5. Scripts da página (@yield('scripts'))
```

**Importante:** O helper `pageLoading` deve ser definido ANTES do `@yield('scripts')` para que as páginas possam usá-lo.

## Documentação Relacionada

- **[Sistema de Modais](./modals.md)** - Modais globais, alertas e comunicação iframe-parent
- **[API](./api.md)** - Helper para requisições AJAX
- **[Arquitetura](./architecture.md)** - Estrutura de views e layouts
