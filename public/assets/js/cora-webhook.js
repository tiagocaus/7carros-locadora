/** Ação explícita de cadastro da URL na Cora; usa a configuração já salva. */
window.CoraWebhookButton = function (getState) {
    const button = document.getElementById('btnActivateCoraWebhook');
    const form = document.getElementById('formPrincipal');
    const description = document.getElementById('webhookDescription');
    const originalDescription = description.textContent;
    let savedSignature = null;
    let busy = false;

    function signature() {
        return JSON.stringify(Array.from(form.querySelectorAll('#ambiente, #sectionCredenciais input, #sectionCertificadoGateway input')).map(input => [
            input.id, input.type === 'radio' || input.type === 'checkbox' ? input.checked : input.value,
            input.files ? Array.from(input.files).map(file => [file.name, file.size, file.lastModified]) : null
        ]));
    }

    function sync() {
        const state = getState();
        button.hidden = state.code !== 'cora';
        button.style.display = state.code === 'cora' ? '' : 'none';
        button.disabled = busy || !state.id || !state.certificate || !state.clientId
            || savedSignature === null || signature() !== savedSignature;
        button.setAttribute('aria-busy', String(busy));
        description.textContent = state.code === 'cora' ? button.dataset.description : originalDescription;
    }

    button.addEventListener('click', async function () {
        sync();
        if (button.disabled) return;
        busy = true;
        sync();
        const icon = button.querySelector('i');
        icon.classList.add('fa-spin');
        try {
            const result = await API.post(`/api/gateways-pagamento/${getState().id}/webhook/ativar`);
            window.parent.postMessage({action: 'openAlert', message: result.message || button.dataset.error}, '*');
        } catch (error) {
            window.parent.postMessage({action: 'openAlert', message: button.dataset.error}, '*');
        } finally {
            busy = false;
            icon.classList.remove('fa-spin');
            sync();
        }
    });
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    return {
        sync,
        saved() { savedSignature = signature(); sync(); }
    };
};
