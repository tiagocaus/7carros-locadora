@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.edit_smtp'))

@section('content')
<div class="p-4">
    <div id="loadingContainer" class="flex items-center justify-center py-8">
        <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
    </div>

    <form id="formEditarConexaoSMTP" class="hidden">
        <input type="hidden" name="host" id="editSmtpHostHidden" value="">
        <input type="hidden" name="port" id="editSmtpPortHidden" value="587">
        <input type="hidden" name="encryption" id="editSmtpEncryptionHidden" value="tls">

        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.connection_name') ?> *</label>
            <input type="text" name="nome" id="inputNome" class="form-input-focus w-full" maxlength="100">
        </div>
        <div class="mb-4 hidden" id="editSmtpCustomFields">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.server') ?></label>
                <input type="text" id="editSmtpHostVisible" class="form-input-focus w-full">
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.port') ?></label>
                    <select id="editSmtpPortVisible" class="form-input-focus w-full">
                        <option value="587">587 (TLS)</option>
                        <option value="465">465 (SSL)</option>
                        <option value="25">25</option>
                        <option value="2525">2525</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.encryption') ?></label>
                    <select id="editSmtpEncryptionVisible" class="form-input-focus w-full">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none"><?= t('modules.mensageria.smtp.encryption_none') ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div id="editSmtpProviderInfo" class="mb-4 p-3 bg-slate-50 rounded-lg">
            <p class="text-xs text-slate-500"><?= t('modules.mensageria.smtp.provider_settings') ?></p>
            <p class="text-sm text-slate-700"><strong>Host:</strong> <span id="infoHost"></span></p>
            <p class="text-sm text-slate-700"><strong><?= t('modules.mensageria.smtp.port') ?>:</strong> <span id="infoPort"></span> | <strong><?= t('modules.mensageria.smtp.encryption') ?>:</strong> <span id="infoEncryption"></span></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.auth_email') ?></label>
            <input type="email" name="username" id="inputUsername" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp.keep_blank') ?>">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.password') ?></label>
            <input type="password" name="password" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp.keep_blank') ?>">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.smtp.password_change_hint') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.from_email') ?></label>
            <input type="email" name="from_email" id="inputFromEmail" class="form-input-focus w-full">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.from_name') ?></label>
            <input type="text" name="from_name" id="inputFromName" class="form-input-focus w-full">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.reply_to') ?></label>
            <input type="email" name="reply_to_email" id="inputReplyTo" class="form-input-focus w-full">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.daily_limit') ?></label>
            <input type="number" name="daily_limit" id="inputDailyLimit" class="form-input-focus w-full">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.common.branches_label') ?> *</label>
            <div id="filiaisContainer" class="border border-slate-300 rounded-md max-h-48 overflow-y-auto p-2 bg-slate-50">
                <div class="text-center text-slate-500 py-2"><?= t('common.labels.loading') ?></div>
            </div>
        </div>
        <button type="submit" class="btn-purple w-full py-2" id="btnSubmit">
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
        smtpUpdated: '<?= addslashes(t("modules.mensageria.messages.smtp_updated")) ?>',
        smtpUpdateError: '<?= addslashes(t("modules.mensageria.messages.smtp_update_error")) ?>',
        saveChanges: '<?= addslashes(t("common.buttons.save_changes")) ?>',
    };

    // Pegar ID da URL
    const urlParams = new URLSearchParams(window.location.search);
    const conexaoId = urlParams.get('id');

    if (!conexaoId) {
        mostrarToast(i18n.connectionIdMissing, 'error');
        return;
    }

    let conexao = null;
    let filiais = [];
    let filiaisSelecionadas = [];

    // Sincronizar campos visiveis com hidden
    document.getElementById('editSmtpHostVisible')?.addEventListener('input', function() {
        document.getElementById('editSmtpHostHidden').value = this.value;
    });
    document.getElementById('editSmtpPortVisible')?.addEventListener('change', function() {
        document.getElementById('editSmtpPortHidden').value = this.value;
    });
    document.getElementById('editSmtpEncryptionVisible')?.addEventListener('change', function() {
        document.getElementById('editSmtpEncryptionHidden').value = this.value;
    });

    // Carregar dados
    async function carregarDados() {
        try {
            const [conexaoResult, filiaisResult] = await Promise.all([
                API.get(`/api/smtp/${conexaoId}`),
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
            const isCustom = conexao.provider === 'smtp_custom';

            // Preencher campos
            document.getElementById('inputNome').value = conexao.nome || '';
            document.getElementById('inputUsername').value = conexao.username || '';
            document.getElementById('inputFromEmail').value = conexao.from_email || '';
            document.getElementById('inputFromName').value = conexao.from_name || '';
            document.getElementById('inputReplyTo').value = conexao.reply_to_email || '';
            if (conexao.daily_limit) {
                document.getElementById('inputDailyLimit').value = conexao.daily_limit;
            }

            // Campos hidden
            document.getElementById('editSmtpHostHidden').value = conexao.host || '';
            document.getElementById('editSmtpPortHidden').value = conexao.port || 587;
            document.getElementById('editSmtpEncryptionHidden').value = conexao.encryption || 'tls';

            // Se custom, mostrar campos editaveis
            if (isCustom) {
                document.getElementById('editSmtpCustomFields').classList.remove('hidden');
                document.getElementById('editSmtpProviderInfo').classList.add('hidden');
                document.getElementById('editSmtpHostVisible').value = conexao.host || '';
                document.getElementById('editSmtpPortVisible').value = conexao.port || 587;
                document.getElementById('editSmtpEncryptionVisible').value = conexao.encryption || 'tls';
            } else {
                document.getElementById('editSmtpCustomFields').classList.add('hidden');
                document.getElementById('editSmtpProviderInfo').classList.remove('hidden');
                document.getElementById('infoHost').textContent = conexao.host || '';
                document.getElementById('infoPort').textContent = conexao.port || 587;
                document.getElementById('infoEncryption').textContent = (conexao.encryption || 'tls').toUpperCase();
            }

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
            document.getElementById('formEditarConexaoSMTP').classList.remove('hidden');

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
    document.getElementById('formEditarConexaoSMTP')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const dados = Object.fromEntries(formData.entries());

        const filiaisCheckboxes = this.querySelectorAll('input[name="filiais_ids[]"]:checked');
        dados.filiais_ids = Array.from(filiaisCheckboxes).map(cb => parseInt(cb.value));

        if (dados.filiais_ids.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        const btnSubmit = document.getElementById('btnSubmit');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const result = await API.post(`/smtp/${conexaoId}/atualizar`, dados);

            if (result.success) {
                mostrarToast(i18n.smtpUpdated, 'success');
                window.parent.postMessage({ action: 'reloadMensageriaData' }, '*');
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
            } else {
                mostrarToast(result.message || i18n.smtpUpdateError, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.saveChanges}`;
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarToast(i18n.smtpUpdateError, 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.saveChanges}`;
        }
    });

    carregarDados();
})();
</script>
@endsection
