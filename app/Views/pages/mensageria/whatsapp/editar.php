@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.edit_whatsapp'))

@section('content')
<div class="p-4">
    <div id="loadingContainer" class="flex items-center justify-center py-8">
        <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
    </div>

    <form id="formEditarConexaoWhatsApp" class="hidden">
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.common.branches_label') ?> *</label>
            <p class="text-xs text-slate-500 mb-2"><?= t('modules.mensageria.common.branches_desc') ?></p>
            <div id="filiaisContainer" class="border border-slate-300 rounded-md max-h-48 overflow-y-auto p-2 bg-slate-50">
                <div class="text-center text-slate-500 py-2"><?= t('common.labels.loading') ?></div>
            </div>
        </div>
        <button type="submit" class="btn-blue w-full py-2" id="btnSubmit">
            <i class="fas fa-save mr-2"></i><?= t('common.buttons.save_changes') ?>
        </button>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        connectionIdMissing: '<?= addslashes(t("modules.mensageria.common.connection_id_missing")) ?>',
        loadConnectionError: '<?= addslashes(t("modules.mensageria.common.load_connection_error")) ?>',
        loadBranchesError: '<?= addslashes(t("modules.mensageria.common.load_branches_error")) ?>',
        loadError: '<?= addslashes(t("modules.mensageria.common.load_error")) ?>',
        noBranches: '<?= addslashes(t("modules.mensageria.common.no_branches")) ?>',
        selectBranch: '<?= addslashes(t("modules.mensageria.common.select_branch")) ?>',
        saving: '<?= addslashes(t("common.labels.saving")) ?>',
        whatsappUpdated: '<?= addslashes(t("modules.mensageria.messages.whatsapp_updated")) ?>',
        whatsappUpdateError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_update_error")) ?>',
        saveChanges: '<?= addslashes(t("common.buttons.save_changes")) ?>',
    };

    const urlParams = new URLSearchParams(window.location.search);
    const conexaoId = urlParams.get('id');

    if (!conexaoId) {
        mostrarToast(i18n.connectionIdMissing, 'error');
        return;
    }

    let conexao = null;
    let filiais = [];
    let filiaisSelecionadas = [];

    async function carregarDados() {
        try {
            const [conexaoResult, filiaisResult] = await Promise.all([
                API.get(`/api/whatsapp/${conexaoId}`),
                API.get('/api/matrizes-filiais/buscar', { q: '' })
            ]);

            if (!conexaoResult.success) {
                mostrarToast(i18n.loadConnectionError, 'error');
                return;
            }
            if (!filiaisResult.success) {
                mostrarToast(i18n.loadBranchesError, 'error');
                return;
            }

            conexao = conexaoResult.data;
            filiais = filiaisResult.data;
            filiaisSelecionadas = conexao.filiais.map(f => f.id);

            // Popular filiais
            const filiaisContainer = document.getElementById('filiaisContainer');
            if (filiais.length === 0) {
                filiaisContainer.innerHTML = `<div class="text-center text-slate-500 py-2">${i18n.noBranches}</div>`;
            } else {
                filiaisContainer.innerHTML = filiais.map(f => `
                    <label class="flex items-center p-2 hover:bg-slate-100 rounded cursor-pointer">
                        <input type="checkbox" name="filiais_ids[]" value="${f.id}"
                               ${filiaisSelecionadas.includes(f.id) ? 'checked' : ''} class="mr-3">
                        <span class="text-sm text-slate-700">${escapeHtml(f.text || f.razao_social)}</span>
                    </label>
                `).join('');
            }

            // Mostrar formulario
            document.getElementById('loadingContainer').classList.add('hidden');
            document.getElementById('formEditarConexaoWhatsApp').classList.remove('hidden');

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
    document.getElementById('formEditarConexaoWhatsApp')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const filiaisCheckboxes = this.querySelectorAll('input[name="filiais_ids[]"]:checked');
        const filiaisIds = Array.from(filiaisCheckboxes).map(cb => parseInt(cb.value));

        if (filiaisIds.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        const btnSubmit = document.getElementById('btnSubmit');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const result = await API.post(`/whatsapp/${conexaoId}/atualizar`, { filiais_ids: filiaisIds });

            if (result.success) {
                mostrarToast(i18n.whatsappUpdated, 'success');
                window.parent.postMessage({ action: 'reloadMensageriaData' }, '*');
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
            } else {
                mostrarToast(result.message || i18n.whatsappUpdateError, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.saveChanges}`;
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarToast(i18n.whatsappUpdateError, 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.saveChanges}`;
        }
    });

    carregarDados();
})();
</script>
@endsection
