@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.new_sms'))

@section('content')
<div class="p-4">
    <div id="loadingContainer" class="flex items-center justify-center py-8">
        <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
    </div>

    <form id="formNovaConexaoSMS" class="hidden">
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.sms.provider') ?> *</label>
            <select name="provider" class="form-input-focus w-full">
                <option value="clicksend">ClickSend</option>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.sms.sender_id') ?> *</label>
            <input type="text" name="sender_id" class="form-input-focus w-full" placeholder="7CARROS" maxlength="11">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.sms.sender_id_hint') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.sms.username') ?> *</label>
            <input type="text" name="username" class="form-input-focus w-full" placeholder="seu_username">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.sms.api_credentials_hint') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.sms.api_key') ?> *</label>
            <input type="password" name="api_key" class="form-input-focus w-full" placeholder="Sua API Key">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.sms.api_credentials_hint') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.common.branches_label') ?> *</label>
            <p class="text-xs text-slate-500 mb-2"><?= t('modules.mensageria.common.branches_desc') ?></p>
            <div id="filiaisContainer" class="border border-slate-300 rounded-md max-h-48 overflow-y-auto p-2 bg-slate-50">
                <div class="text-center text-slate-500 py-2"><?= t('common.labels.loading') ?></div>
            </div>
        </div>
        <button type="submit" class="btn-blue w-full py-2" id="btnSubmit">
            <i class="fas fa-sms mr-2"></i><?= t('modules.mensageria.sms.create_validate') ?>
        </button>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loadBranchesError: '<?= addslashes(t("modules.mensageria.common.load_branches_error")) ?>',
        loadError: '<?= addslashes(t("modules.mensageria.common.load_error")) ?>',
        noBranches: '<?= addslashes(t("modules.mensageria.common.no_branches")) ?>',
        alreadyLinked: '<?= addslashes(t("modules.mensageria.common.already_linked")) ?>',
        fillRequired: '<?= addslashes(t("modules.mensageria.common.fill_required")) ?>',
        selectBranch: '<?= addslashes(t("modules.mensageria.common.select_branch")) ?>',
        creating: '<?= addslashes(t("common.labels.creating")) ?>',
        smsCreated: '<?= addslashes(t("modules.mensageria.messages.sms_created")) ?>',
        smsCreateError: '<?= addslashes(t("modules.mensageria.messages.sms_create_error")) ?>',
        createValidate: '<?= addslashes(t("modules.mensageria.sms.create_validate")) ?>',
    };

    let filiais = [];
    let filiaisOcupadas = [];

    async function carregarDados() {
        try {
            const [filiaisResult, ocupadasResult] = await Promise.all([
                API.get('/api/matrizes-filiais/buscar', { q: '' }),
                API.get('/api/sms/filiais-ocupadas')
            ]);

            if (!filiaisResult.success) {
                mostrarToast(i18n.loadBranchesError, 'error');
                return;
            }

            filiais = filiaisResult.data;
            filiaisOcupadas = ocupadasResult.success ? ocupadasResult.data : [];

            // Popular filiais
            const filiaisContainer = document.getElementById('filiaisContainer');
            if (filiais.length === 0) {
                filiaisContainer.innerHTML = `<div class="text-center text-slate-500 py-2">${i18n.noBranches}</div>`;
            } else {
                filiaisContainer.innerHTML = filiais.map(f => {
                    const ocupada = filiaisOcupadas.includes(f.id);
                    return `
                        <label class="flex items-center p-2 hover:bg-slate-100 rounded ${ocupada ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}">
                            <input type="checkbox" name="filiais_ids[]" value="${f.id}" class="mr-3" ${ocupada ? 'disabled' : ''}>
                            <span class="text-sm text-slate-700">${escapeHtml(f.text || f.razao_social)}</span>
                            ${ocupada ? `<span class="ml-auto text-xs text-amber-600"><i class="fas fa-link mr-1"></i>${i18n.alreadyLinked}</span>` : ''}
                        </label>
                    `;
                }).join('');
            }

            // Mostrar formulario
            document.getElementById('loadingContainer').classList.add('hidden');
            document.getElementById('formNovaConexaoSMS').classList.remove('hidden');

        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            mostrarToast(i18n.loadError, 'error');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function mostrarToast(mensagem, tipo = 'info') {
        if (typeof window.toast !== 'undefined') {
            window.toast[tipo](mensagem);
        } else if (window.parent !== window) {
            window.parent.postMessage({ action: 'openAlert', message: mensagem }, '*');
        }
    }

    // Submit
    document.getElementById('formNovaConexaoSMS')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const dados = Object.fromEntries(formData.entries());

        const filiaisCheckboxes = this.querySelectorAll('input[name="filiais_ids[]"]:checked');
        dados.filiais_ids = Array.from(filiaisCheckboxes).map(cb => parseInt(cb.value));

        // Validar campos obrigatorios
        if (!dados.sender_id || !dados.username || !dados.api_key) {
            mostrarToast(i18n.fillRequired, 'error');
            return;
        }

        if (dados.filiais_ids.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        const btnSubmit = document.getElementById('btnSubmit');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.creating}`;

        try {
            const result = await API.post('/sms/salvar', dados);

            if (result.success) {
                mostrarToast(i18n.smsCreated, 'success');
                window.parent.postMessage({ action: 'reloadMensageriaData' }, '*');
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
            } else {
                mostrarToast(result.message || i18n.smsCreateError, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="fas fa-sms mr-2"></i>${i18n.createValidate}`;
            }
        } catch (error) {
            console.error('Erro ao criar conexao:', error);
            mostrarToast(i18n.smsCreateError, 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="fas fa-sms mr-2"></i>${i18n.createValidate}`;
        }
    });

    carregarDados();
})();
</script>
@endsection
