/**
 * API Helper - Requisições HTTP com proteção CSRF
 *
 * Este helper garante que todas as requisições à API incluam
 * o token CSRF no header X-CSRF-TOKEN para segurança.
 * Quando o token CSRF expira (419), tenta renovar silenciosamente
 * e repetir a requisição sem interromper o usuário.
 */
const API = {
    _refreshing: null,
    _heartbeatTimer: null,

    /**
     * Obtém o token CSRF da meta tag
     */
    getToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    /**
     * Retorna os headers padrão para requisições
     */
    getHeaders() {
        return {
            'X-CSRF-TOKEN': this.getToken(),
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    },

    /**
     * Requisição GET
     * @param {string} url - URL do endpoint
     * @param {Object} params - Parâmetros de query string
     * @returns {Promise<Object>} - Resposta JSON
     */
    async get(url, params = {}) {
        const query = new URLSearchParams(params).toString();
        const fullUrl = query ? `${url}?${query}` : url;

        const response = await fetch(fullUrl, {
            method: 'GET',
            headers: this.getHeaders()
        });

        return this.handleResponse(response, () => fetch(fullUrl, {
            method: 'GET',
            headers: this.getHeaders()
        }));
    },

    /**
     * Requisição POST
     * @param {string} url - URL do endpoint
     * @param {Object} data - Dados a enviar no body
     * @returns {Promise<Object>} - Resposta JSON
     */
    async post(url, data = {}) {
        const body = JSON.stringify(data);

        const response = await fetch(url, {
            method: 'POST',
            headers: this.getHeaders(),
            body
        });

        return this.handleResponse(response, () => fetch(url, {
            method: 'POST',
            headers: this.getHeaders(),
            body
        }));
    },

    /**
     * Requisição POST com FormData (para uploads)
     * @param {string} url - URL do endpoint
     * @param {FormData} formData - FormData com os dados
     * @returns {Promise<Object>} - Resposta JSON
     */
    async postForm(url, formData) {
        const makeRequest = () => fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.getToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        const response = await makeRequest();
        return this.handleResponse(response, makeRequest);
    },

    /**
     * Requisição PUT
     * @param {string} url - URL do endpoint
     * @param {Object} data - Dados a enviar no body
     * @returns {Promise<Object>} - Resposta JSON
     */
    async put(url, data = {}) {
        const body = JSON.stringify(data);

        const response = await fetch(url, {
            method: 'PUT',
            headers: this.getHeaders(),
            body
        });

        return this.handleResponse(response, () => fetch(url, {
            method: 'PUT',
            headers: this.getHeaders(),
            body
        }));
    },

    /**
     * Requisição DELETE
     * @param {string} url - URL do endpoint
     * @param {Object} data - Dados opcionais a enviar
     * @returns {Promise<Object>} - Resposta JSON
     */
    async delete(url, data = {}) {
        const body = Object.keys(data).length ? JSON.stringify(data) : undefined;

        const response = await fetch(url, {
            method: 'DELETE',
            headers: this.getHeaders(),
            body
        });

        return this.handleResponse(response, () => fetch(url, {
            method: 'DELETE',
            headers: this.getHeaders(),
            body
        }));
    },

    /**
     * Processa a resposta do servidor
     * @param {Response} response - Objeto Response do fetch
     * @param {Function|null} retryFn - Função para repetir a requisição (null = sem retry)
     * @returns {Promise<Object>} - Dados JSON ou erro
     */
    async handleResponse(response, retryFn = null) {
        // Token CSRF expirado - tenta renovar silenciosamente
        if (response.status === 419) {
            if (retryFn) {
                try {
                    await this.refreshCsrfToken();
                    const retryResponse = await retryFn();
                    return this.handleResponse(retryResponse, null);
                } catch (e) {
                    if (e.message === 'session_dead') {
                        window.location.href = '/login';
                    } else {
                        this.showSessionExpiredModal();
                    }
                    throw new Error('Sessão expirada. Por favor, recarregue a página.');
                }
            }
            this.showSessionExpiredModal();
            throw new Error('Sessão expirada. Por favor, recarregue a página.');
        }

        // Verifica se não está autenticado
        if (response.status === 401) {
            console.error('Não autenticado');
            window.location.href = '/login';
            throw new Error('Não autenticado');
        }

        // Tenta parsear JSON
        try {
            return await response.json();
        } catch (e) {
            throw new Error('Erro ao processar resposta do servidor');
        }
    },

    /**
     * Renova o token CSRF chamando endpoint sem validação CSRF.
     * Usa lock para evitar múltiplas chamadas simultâneas.
     */
    async refreshCsrfToken() {
        if (this._refreshing) {
            return this._refreshing;
        }

        this._refreshing = (async () => {
            try {
                const response = await fetch('/api/session/refresh', {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.status === 401) {
                    throw new Error('session_dead');
                }

                if (!response.ok) {
                    throw new Error('refresh_failed');
                }

                const data = await response.json();
                if (data.success && data.csrf_token) {
                    this.updateCsrfToken(data.csrf_token);
                } else {
                    throw new Error('refresh_failed');
                }
            } finally {
                this._refreshing = null;
            }
        })();

        return this._refreshing;
    },

    /**
     * Atualiza o token CSRF na meta tag local e broadcast para parent/iframes
     */
    updateCsrfToken(newToken) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.content = newToken;
        }

        // Notifica parent (se estamos num iframe)
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'csrfTokenRefreshed',
                csrfToken: newToken
            }, '*');
        }

        // Notifica iframes filhos (se somos o parent)
        document.querySelectorAll('iframe').forEach(function(iframe) {
            try {
                if (iframe.contentWindow) {
                    iframe.contentWindow.postMessage({
                        action: 'csrfTokenRefreshed',
                        csrfToken: newToken
                    }, '*');
                }
            } catch (e) { /* cross-origin */ }
        });
    },

    /**
     * Exibe modal de sessão expirada (fallback)
     * Usa postMessage para comunicar com o parent (sistema de iframes)
     */
    showSessionExpiredModal() {
        if (window.parent !== window) {
            // Estamos em um iframe, notificar parent
            window.parent.postMessage({ action: 'openSessionExpiredModal' }, '*');
        } else {
            // Estamos no document principal
            const modal = document.getElementById('sessionExpiredModal');
            if (modal) {
                modal.classList.add('open');
                document.body.classList.add('modal-open');
            } else {
                // Fallback: alert simples
                alert('Sua sessão expirou. A página será recarregada.');
                window.location.reload();
            }
        }
    },

    /**
     * Mantém a sessão viva enquanto a aba estiver aberta.
     * Pinga /api/session/refresh em intervalos regulares (apenas com a aba visivel)
     * para resetar session.gc_maxlifetime do PHP e renovar o token CSRF.
     * Auto-iniciado apenas na janela principal — iframes filhos compartilham a sessão.
     *
     * @param {number} intervalMs - Intervalo entre pings (padrão 10 min)
     */
    startHeartbeat(intervalMs = 600000) {
        if (this._heartbeatTimer) return;
        this._heartbeatTimer = setInterval(() => this._heartbeatTick(), intervalMs);
    },

    stopHeartbeat() {
        if (this._heartbeatTimer) {
            clearInterval(this._heartbeatTimer);
            this._heartbeatTimer = null;
        }
    },

    async _heartbeatTick() {
        // Só pinga com aba visivel — não estende sessão de aba abandonada/em background.
        if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
            return;
        }
        try {
            await this.refreshCsrfToken();
        } catch (e) {
            // Sessão morta no servidor — desliga heartbeat para não loopar.
            // O fluxo normal (próxima ação do usuário) cuidará do redirect.
            if (e && e.message === 'session_dead') {
                this.stopHeartbeat();
            }
        }
    }
};

// Exporta para uso global
window.API = API;

// Inicia heartbeat de sessão APENAS na janela principal — iframes filhos
// herdam a sessão renovada via cookie compartilhado.
if (typeof window !== 'undefined' && window.top === window) {
    if (typeof document !== 'undefined' && document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => API.startHeartbeat());
    } else {
        API.startHeartbeat();
    }
}
