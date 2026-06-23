@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.new_smtp'))

@section('content')
<div class="p-4">
    <div id="loadingContainer" class="flex items-center justify-center py-8">
        <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
    </div>

    <form id="formNovaConexaoSMTP" class="hidden">
        <input type="hidden" name="host" id="smtpHostHidden" value="">
        <input type="hidden" name="port" id="smtpPortHidden" value="587">
        <input type="hidden" name="encryption" id="smtpEncryptionHidden" value="tls">

        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.provider') ?> *</label>
            <select name="provider" id="smtpProvider" class="form-input-focus w-full">
                <option value=""><?= t('common.labels.loading') ?></option>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.connection_name') ?> *</label>
            <input type="text" name="nome" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.name') ?>" maxlength="100">
        </div>
        <div id="smtpCustomFields" class="hidden">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.server') ?> *</label>
                <input type="text" id="smtpHostVisible" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.server') ?>">
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.port') ?></label>
                    <select id="smtpPortVisible" class="form-input-focus w-full">
                        <option value="587">587 (TLS)</option>
                        <option value="465">465 (SSL)</option>
                        <option value="25">25</option>
                        <option value="2525">2525</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.encryption') ?></label>
                    <select id="smtpEncryptionVisible" class="form-input-focus w-full">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none"><?= t('modules.mensageria.smtp.encryption_none') ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.auth_email') ?> *</label>
            <input type="email" name="username" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.auth_email') ?>">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.password') ?> *</label>
            <input type="password" name="password" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.password') ?>">
            <p class="text-xs text-slate-500 mt-1" id="smtpPasswordHelp"><?= t('modules.mensageria.smtp.password_hint_gmail') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.from_email') ?> *</label>
            <input type="email" name="from_email" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.from_email') ?>">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.from_name') ?> *</label>
            <input type="text" name="from_name" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.from_name') ?>">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.reply_to') ?></label>
            <input type="email" name="reply_to_email" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.reply_to') ?>">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.daily_limit') ?></label>
            <input type="number" name="daily_limit" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.daily_limit') ?>">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.smtp.daily_limit_hint') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.common.branches_label') ?> *</label>
            <p class="text-xs text-slate-500 mb-2"><?= t('modules.mensageria.common.branches_desc') ?></p>
            <div id="filiaisContainer" class="border border-slate-300 rounded-md max-h-48 overflow-y-auto p-2 bg-slate-50">
                <div class="text-center text-slate-500 py-2"><?= t('common.labels.loading') ?></div>
            </div>
        </div>
        <button type="submit" class="btn-purple w-full py-2" id="btnSubmit">
            <i class="fas fa-envelope mr-2"></i><?= t('modules.mensageria.smtp.create_validate') ?>
        </button>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loading: '<?= addslashes(t("common.labels.loading")) ?>',
        loadBranchesError: '<?= addslashes(t("modules.mensageria.common.load_branches_error")) ?>',
        loadError: '<?= addslashes(t("modules.mensageria.common.load_error")) ?>',
        noBranches: '<?= addslashes(t("modules.mensageria.common.no_branches")) ?>',
        alreadyLinked: '<?= addslashes(t("modules.mensageria.common.already_linked")) ?>',
        fillRequired: '<?= addslashes(t("modules.mensageria.common.fill_required")) ?>',
        selectBranch: <?= js_t("modules.mensageria.common.select_branch") ?>,
        creating: '<?= addslashes(t("common.labels.creating")) ?>',
        smtpCreated: '<?= addslashes(t("modules.mensageria.messages.smtp_created")) ?>',
        smtpCreateError: '<?= addslashes(t("modules.mensageria.messages.smtp_create_error")) ?>',
        createValidate: '<?= addslashes(t("modules.mensageria.smtp.create_validate")) ?>',
        passwordHintCustom: '<?= addslashes(t("modules.mensageria.smtp.password_hint_custom")) ?>',
        passwordHintDefault: '<?= addslashes(t("modules.mensageria.smtp.password_hint_default")) ?>',
    };

    let providers = [];
    let filiais = [];
    let filiaisOcupadas = [];

    // Handler para mudanca de provedor
    function handleSmtpProviderChange(sel) {
        const opt = sel.options[sel.selectedIndex];
        const isCustom = opt.dataset.custom === 'true' || opt.dataset.custom === '1';
        const container = document.getElementById('smtpCustomFields');
        const hostHidden = document.getElementById('smtpHostHidden');
        const portHidden = document.getElementById('smtpPortHidden');
        const encHidden = document.getElementById('smtpEncryptionHidden');
        const hostVisible = document.getElementById('smtpHostVisible');
        const portVisible = document.getElementById('smtpPortVisible');
        const encVisible = document.getElementById('smtpEncryptionVisible');
        const helpText = document.getElementById('smtpPasswordHelp');

        if (isCustom) {
            if (container) container.classList.remove('hidden');
            if (hostHidden && hostVisible) hostHidden.value = hostVisible.value || '';
            if (portHidden && portVisible) portHidden.value = portVisible.value || '587';
            if (encHidden && encVisible) encHidden.value = encVisible.value || 'tls';
            if (helpText) helpText.textContent = i18n.passwordHintCustom;
        } else {
            if (container) container.classList.add('hidden');
            if (hostHidden) hostHidden.value = opt.dataset.host || '';
            if (portHidden) portHidden.value = opt.dataset.port || '587';
            if (encHidden) encHidden.value = opt.dataset.encryption || 'tls';
            if (helpText) helpText.textContent = i18n.passwordHintDefault;
        }
    }

    // Sincronizar campos visiveis com hidden
    document.getElementById('smtpHostVisible')?.addEventListener('input', function() {
        document.getElementById('smtpHostHidden').value = this.value;
    });
    document.getElementById('smtpPortVisible')?.addEventListener('change', function() {
        document.getElementById('smtpPortHidden').value = this.value;
    });
    document.getElementById('smtpEncryptionVisible')?.addEventListener('change', function() {
        document.getElementById('smtpEncryptionHidden').value = this.value;
    });

    // Carregar dados iniciais
    async function carregarDados() {
        try {
            const [filiaisResult, ocupadasResult, providersResult] = await Promise.all([
                API.get('/api/matrizes-filiais/buscar', { q: '' }),
                API.get('/api/smtp/filiais-ocupadas'),
                API.get('/api/smtp/providers')
            ]);

            if (!filiaisResult.success) {
                mostrarToast(i18n.loadBranchesError, 'error');
                return;
            }

            filiais = filiaisResult.data;
            filiaisOcupadas = ocupadasResult.success ? ocupadasResult.data : [];
            providers = providersResult.success ? providersResult.data.providers : [];

            // Popular providers
            const providerSelect = document.getElementById('smtpProvider');
            providerSelect.innerHTML = providers.map(p =>
                `<option value="${p.value}" data-host="${p.host}" data-port="${p.port}" data-encryption="${p.encryption}" data-custom="${p.is_custom}">${escapeHtml(p.label)}</option>`
            ).join('');

            // Configurar evento de mudanca
            providerSelect.addEventListener('change', function() {
                handleSmtpProviderChange(this);
            });

            // Setar valores iniciais do primeiro provedor
            if (providers.length > 0) {
                const defaultProvider = providers[0];
                document.getElementById('smtpHostHidden').value = defaultProvider.host || '';
                document.getElementById('smtpPortHidden').value = defaultProvider.port || '587';
                document.getElementById('smtpEncryptionHidden').value = defaultProvider.encryption || 'tls';
                if (defaultProvider.is_custom) {
                    document.getElementById('smtpCustomFields').classList.remove('hidden');
                }
            }

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
            document.getElementById('formNovaConexaoSMTP').classList.remove('hidden');

        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            mostrarToast(i18n.loadError, 'error');
        }
    }

    // Funcao auxiliar para escapar HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Mostrar toast
    function mostrarToast(mensagem, tipo = 'info') {
        if (typeof window.toast !== 'undefined') {
            window.toast[tipo](mensagem);
        } else if (window.parent !== window) {
            window.parent.postMessage({ action: 'openAlert', message: mensagem }, '*');
        }
    }

    // Submit do formulario
    document.getElementById('formNovaConexaoSMTP')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const dados = Object.fromEntries(formData.entries());

        // Pegar filiais selecionadas
        const filiaisCheckboxes = this.querySelectorAll('input[name="filiais_ids[]"]:checked');
        dados.filiais_ids = Array.from(filiaisCheckboxes).map(cb => parseInt(cb.value));

        // Validar campos obrigatorios
        if (!dados.nome || !dados.username || !dados.password || !dados.from_email || !dados.from_name) {
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
            const result = await API.post('/smtp/salvar', dados);

            if (result.success) {
                mostrarToast(i18n.smtpCreated, 'success');
                // Notificar parent para recarregar dados e fechar offcanvas
                window.parent.postMessage({ action: 'reloadMensageriaData' }, '*');
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
            } else {
                mostrarToast(result.message || i18n.smtpCreateError, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = `<i class="fas fa-envelope mr-2"></i>${i18n.createValidate}`;
            }
        } catch (error) {
            console.error('Erro ao criar conexao:', error);
            mostrarToast(i18n.smtpCreateError, 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="fas fa-envelope mr-2"></i>${i18n.createValidate}`;
        }
    });

    // Carregar dados ao iniciar
    carregarDados();
})();
</script>
@endsection
