/**
 * Sistema de Toast - Notificacoes visuais
 *
 * Uso:
 *   toast.success('Mensagem de sucesso')
 *   toast.warning('Mensagem de aviso')
 *   toast.error('Mensagem de erro')
 *   toast.info('Mensagem informativa')
 */
window.toast = {
    show(message, type = 'info') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toastEl = document.createElement('div');
        toastEl.className = `toast toast-${type}`;
        toastEl.textContent = message;
        container.appendChild(toastEl);

        setTimeout(() => {
            toastEl.classList.add('toast-hiding');
            setTimeout(() => toastEl.remove(), 300);
        }, 3000);
    },
    success(msg) { this.show(msg, 'success'); },
    warning(msg) { this.show(msg, 'warning'); },
    error(msg) { this.show(msg, 'error'); },
    info(msg) { this.show(msg, 'info'); }
};

// Alias para compatibilidade com chamadas usando Toast (maiusculo)
window.Toast = window.toast;
