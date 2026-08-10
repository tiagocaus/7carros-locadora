#!/usr/bin/env node

const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync(require.resolve('../public/assets/js/api.js'), 'utf8');
const messages = [];
const parentWindow = {
    postMessage(message) {
        messages.push(message);
    }
};
const windowObject = {
    parent: parentWindow,
    top: parentWindow,
    APP_I18N: { common: {} }
};

const context = vm.createContext({
    window: windowObject,
    console,
    URLSearchParams,
    Error,
    setInterval,
    clearInterval
});

vm.runInContext(source, context, { filename: 'api.js' });

(async () => {
    const response401 = {
        status: 401,
        clone() {
            return this;
        },
        async json() {
            return {
                session_expired: true,
                session_reason: 'inactivity'
            };
        }
    };

    try {
        await windowObject.API.handleResponse(response401);
        throw new Error('401 deveria interromper a requisicao');
    } catch (error) {
        if (error.message !== 'Não autenticado') throw error;
    }

    const iframeMessage = messages.at(-1);
    if (
        iframeMessage?.action !== 'openSessionExpiredModal'
        || iframeMessage?.reason !== 'inactivity'
    ) {
        throw new Error('401 nao abriu o modal global com o motivo correto');
    }

    let topReason = null;
    windowObject.parent = windowObject;
    windowObject.openSessionExpiredModal = (reason) => {
        topReason = reason;
    };
    windowObject.API.showSessionExpiredModal('fingerprint_mismatch');

    if (topReason !== 'fingerprint_mismatch') {
        throw new Error('Janela principal nao recebeu o motivo da expiracao');
    }

    if (windowObject.location?.href === '/login') {
        throw new Error('API redirecionou automaticamente para o login');
    }

    console.log('OK: API abre o modal global e nao redireciona o iframe automaticamente.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
