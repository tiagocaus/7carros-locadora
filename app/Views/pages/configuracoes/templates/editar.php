@extends('layouts.iframe')

@section('title', '<?= t("modules.configuracoes.edit_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page" id="templateTitle"><?= t('modules.configuracoes.messages.loading_page') ?></h2>
            <p class="text-xs text-slate-500" id="templateDescription"></p>
        </div>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Tabs de Canal -->
    <div class="template-channel-tabs mb-3" id="channelTabs">
        <!-- Populado via JavaScript -->
    </div>

    <!-- Tabs de Idioma -->
    <div class="template-locale-tabs mb-4" id="localeTabs">
        <button type="button" data-locale="pt_BR" class="template-tab active">Português (BR)</button>
        <button type="button" data-locale="en_US" class="template-tab">English</button>
        <button type="button" data-locale="es_ES" class="template-tab">Español</button>
        <button type="button" data-locale="pt_PT" class="template-tab">Português (PT)</button>
        <button type="button" data-locale="it_IT" class="template-tab">Italiano</button>
    </div>

    <!-- Status -->
    <div class="flex items-center gap-2 mb-4">
        <span id="customBadge" class="hidden badge-custom">
            <i class="fas fa-check-circle mr-1"></i><?= t('modules.configuracoes.labels.customized') ?>
        </span>
        <span id="defaultBadge" class="hidden badge-default">
            <i class="fas fa-database mr-1"></i><?= t('modules.configuracoes.labels.using_default') ?>
        </span>
    </div>

    <!-- Área Principal -->
    <div class="template-editor-container">
        <div class="template-editor-main">
            <!-- Assunto (só para email) -->
            <div id="subjectContainer" class="form-input-group mb-4">
                <label for="templateSubject" class="form-label-group"><?= t('modules.configuracoes.labels.email_subject') ?></label>
                <input type="text" id="templateSubject" class="form-input-group-field" placeholder="<?= t('modules.configuracoes.placeholders.email_subject') ?>">
            </div>

            <!-- Conteúdo -->
            <div class="form-input-group mb-4">
                <label class="form-label-group"><?= t('modules.configuracoes.labels.content') ?></label>
                <!-- Editor Rico (Email) -->
                <div id="richEditorContainer">
                    <textarea id="templateContent" class="tinymce-editor"></textarea>
                </div>
                <!-- Textarea Simples (WhatsApp/SMS) -->
                <div id="simpleEditorContainer" class="hidden">
                    <textarea id="templateContentSimple" class="form-input-group-field" rows="10" placeholder="<?= t('modules.configuracoes.placeholders.message_content') ?>"></textarea>
                    <p class="text-xs text-slate-500 mt-1">
                        <span id="charCount">0</span> <?= t('modules.configuracoes.labels.characters') ?>
                        <span id="smsWarning" class="hidden text-amber-600 ml-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i><?= t('modules.configuracoes.warnings.sms_split') ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex flex-wrap gap-2">
                <button type="button" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                </button>
                <button type="button" id="btnPreview" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-eye mr-2"></i><?= t('modules.configuracoes.buttons.preview') ?>
                </button>
                <button type="button" id="btnRestaurar" class="btn-outline-danger py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-undo mr-2"></i><?= t('modules.configuracoes.buttons.restore_default') ?>
                </button>
            </div>
        </div>

        <!-- Painel de Variáveis -->
        <div class="template-variables-panel">
            <h4 class="text-sm font-semibold text-slate-700 mb-3">
                <i class="fas fa-code mr-1"></i><?= t('modules.configuracoes.labels.available_variables') ?>
            </h4>
            <p class="text-xs text-slate-500 mb-3"><?= t('modules.configuracoes.labels.click_to_insert') ?></p>
            <div id="variablesContainer">
                <div class="flex items-center justify-center py-4">
                    <i class="fas fa-spinner fa-spin text-slate-400"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .template-channel-tabs,
    .template-locale-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .template-tab {
        padding: 0.5rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background: white;
        font-size: 0.875rem;
        color: #64748b;
        cursor: pointer;
        transition: all 0.15s;
    }
    .template-tab:hover {
        background: #f1f5f9;
    }
    .template-tab.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }
    .template-tab i {
        margin-right: 0.375rem;
    }

    .template-editor-container {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 1rem;
    }
    @media (max-width: 768px) {
        .template-editor-container {
            grid-template-columns: 1fr;
        }
        .template-variables-panel {
            order: -1;
        }
    }

    .template-editor-main {
        min-width: 0;
    }

    .template-variables-panel {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 1rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .variable-group {
        margin-bottom: 0.5rem;
    }
    .variable-group-header {
        font-weight: 600;
        font-size: 0.75rem;
        padding: 0.5rem;
        cursor: pointer;
        background: white;
        border-radius: 0.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #475569;
    }
    .variable-group-header:hover {
        background: #f1f5f9;
    }
    .variable-group-items {
        padding: 0.25rem 0.5rem;
    }
    .variable-group-items.collapsed {
        display: none;
    }
    .variable-item {
        padding: 0.375rem 0.5rem;
        cursor: pointer;
        border-radius: 0.25rem;
        font-size: 0.75rem;
    }
    .variable-item:hover {
        background: #dbeafe;
    }
    .variable-item code {
        color: #2563eb;
        font-size: 0.6875rem;
        display: block;
    }
    .variable-item .variable-label {
        color: #64748b;
        font-size: 0.625rem;
    }

    .badge-custom {
        background: #dcfce7;
        color: #15803d;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .badge-default {
        background: #f1f5f9;
        color: #64748b;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .btn-outline-danger {
        border: 1px solid #ef4444;
        color: #ef4444;
        background: white;
    }
    .btn-outline-danger:hover {
        background: #fef2f2;
    }

    /* TinyMCE container */
    .tox-tinymce {
        border-radius: 0.375rem !important;
    }

    /* Toast */
    .toast-container {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 99999;
    }
    .toast {
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease;
    }
    .toast.success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .toast.error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>
@endsection

@section('scripts')
<script src="/assets/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
<script>window.TINYMCE_LICENSE_KEY = '<?= getenv('TINYMCE_LICENSE_KEY') ?: 'gpl' ?>';</script>
<script src="<?= asset('js/template-variables.min.js') ?>"></script>
<script src="<?= asset('js/tinymce-init.min.js') ?>"></script>
<script>
// Função de Toast
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    const i18n = {
        editTitlePrefix: '<?= addslashes(t('modules.configuracoes.edit_title_prefix')) ?>',
        attention: '<?= addslashes(t('modules.configuracoes.modals.attention')) ?>',
        unsavedChanges: '<?= addslashes(t('modules.configuracoes.modals.unsaved_changes')) ?>',
        cancel: '<?= addslashes(t('common.buttons.cancel')) ?>',
        continue: '<?= addslashes(t('modules.configuracoes.modals.continue')) ?>',
        restoreTitle: '<?= addslashes(t('modules.configuracoes.modals.restore_title')) ?>',
        restoreConfirm: '<?= addslashes(t('modules.configuracoes.modals.restore_confirm')) ?>',
        restoreWarning: '<?= addslashes(t('modules.configuracoes.modals.restore_warning')) ?>',
        restoreBtn: '<?= addslashes(t('modules.configuracoes.modals.restore_btn')) ?>',
        previewTitle: '<?= addslashes(t('modules.configuracoes.modals.preview_title')) ?>',
        close: '<?= addslashes(t('modules.configuracoes.modals.close')) ?>',
        subjectLabel: '<?= addslashes(t('modules.configuracoes.labels.subject')) ?>',
        noSubject: '<?= addslashes(t('modules.configuracoes.labels.no_subject')) ?>',
        contentLabel: '<?= addslashes(t('modules.configuracoes.labels.content_label')) ?>',
        noVariables: '<?= addslashes(t('modules.configuracoes.messages.no_variables')) ?>',
        saving: '<?= addslashes(t('modules.configuracoes.messages.saving')) ?>',
        saveSuccess: '<?= addslashes(t('modules.configuracoes.messages.save_success')) ?>',
        saveError: '<?= addslashes(t('modules.configuracoes.messages.save_error')) ?>',
        previewError: '<?= addslashes(t('modules.configuracoes.messages.preview_error')) ?>',
        restoring: '<?= addslashes(t('modules.configuracoes.messages.restoring')) ?>',
        restoreSuccess: '<?= addslashes(t('modules.configuracoes.messages.restore_success')) ?>',
        restoreError: '<?= addslashes(t('modules.configuracoes.messages.restore_error')) ?>',
    };

    // Configuração inicial
    const slug = '<?= $slug ?? '' ?>';
    const typeData = <?= json_encode($type ?? []) ?>;

    // Estado
    let currentChannel = 'email';
    let currentLocale = 'pt_BR';
    let currentTemplate = null;
    let variables = {};
    let isCustom = false;
    let isDirty = false;
    let editorInitialized = false;

    // Elementos
    const channelTabs = document.getElementById('channelTabs');
    const localeTabs = document.getElementById('localeTabs');
    const subjectContainer = document.getElementById('subjectContainer');
    const richEditorContainer = document.getElementById('richEditorContainer');
    const simpleEditorContainer = document.getElementById('simpleEditorContainer');
    const templateSubject = document.getElementById('templateSubject');
    const templateContentSimple = document.getElementById('templateContentSimple');
    const customBadge = document.getElementById('customBadge');
    const defaultBadge = document.getElementById('defaultBadge');
    const charCount = document.getElementById('charCount');
    const smsWarning = document.getElementById('smsWarning');

    // Funções para modal na janela pai (quando em iframe)
    function abrirModalParent(config) {
        const parent = window.parent || window;
        const doc = parent.document;

        // Remover modal anterior se existir
        const existente = doc.getElementById('iframe-modal');
        if (existente) existente.remove();

        // Criar overlay + modal
        const overlay = doc.createElement('div');
        overlay.id = 'iframe-modal';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;display:flex;align-items:center;justify-content:center;';

        overlay.innerHTML = `
            <div style="background:white;border-radius:0.5rem;max-width:${config.large ? '48rem' : '28rem'};width:90%;margin:1rem;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
                <div style="padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:1rem;font-weight:600;color:#1e293b;margin:0;">${config.title}</h3>
                    <button type="button" id="modal-close-btn" style="color:#94a3b8;padding:0.25rem;cursor:pointer;border:none;background:none;font-size:1rem;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div style="padding:1.5rem;">
                    ${config.body}
                </div>
                <div style="padding:1rem 1.5rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:0.5rem;">
                    ${config.footer}
                </div>
            </div>
        `;

        doc.body.appendChild(overlay);

        // Eventos de fechar
        doc.getElementById('modal-close-btn').onclick = () => fecharModalParent();
        overlay.onclick = (e) => { if (e.target === overlay) fecharModalParent(); };

        return overlay;
    }

    function fecharModalParent() {
        const parent = window.parent || window;
        const modal = parent.document.getElementById('iframe-modal');
        if (modal) modal.remove();
    }

    function confirmarModalParent(mensagem) {
        return new Promise((resolve) => {
            abrirModalParent({
                title: i18n.attention,
                body: `<p style="color:#475569;margin:0;">${mensagem}</p>`,
                footer: `
                    <button type="button" id="modal-cancelar-btn" style="background:#f1f5f9;color:#334155;padding:0.5rem 1rem;border-radius:0.375rem;font-size:0.875rem;border:none;cursor:pointer;">${i18n.cancel}</button>
                    <button type="button" id="modal-confirmar-btn" style="background:#2563eb;color:white;padding:0.5rem 1rem;border-radius:0.375rem;font-size:0.875rem;border:none;cursor:pointer;">${i18n.continue}</button>
                `
            });
            const parentDoc = (window.parent || window).document;
            parentDoc.getElementById('modal-cancelar-btn').onclick = () => { fecharModalParent(); resolve(false); };
            parentDoc.getElementById('modal-confirmar-btn').onclick = () => { fecharModalParent(); resolve(true); };
        });
    }

    // Inicialização
    inicializar();

    // Eventos
    document.getElementById('btnVoltar').addEventListener('click', voltar);
    document.getElementById('btnSalvar').addEventListener('click', salvar);
    document.getElementById('btnPreview').addEventListener('click', preview);
    document.getElementById('btnRestaurar').addEventListener('click', () => {
        abrirModalParent({
            title: i18n.restoreTitle,
            large: false,
            body: `
                <p style="color:#475569;margin:0 0 0.5rem 0;">${i18n.restoreConfirm}</p>
                <p style="font-size:0.875rem;color:#d97706;margin:0;">
                    <i class="fas fa-exclamation-triangle" style="margin-right:0.25rem;"></i>
                    ${i18n.restoreWarning}
                </p>
            `,
            footer: `
                <button type="button" id="modal-cancelar-btn" style="background:#f1f5f9;color:#334155;padding:0.5rem 1rem;border-radius:0.375rem;font-size:0.875rem;border:none;cursor:pointer;">${i18n.cancel}</button>
                <button type="button" id="modal-confirmar-btn" style="background:#ef4444;color:white;padding:0.5rem 1rem;border-radius:0.375rem;font-size:0.875rem;border:none;cursor:pointer;">${i18n.restoreBtn}</button>
            `
        });

        const parentDoc = (window.parent || window).document;
        parentDoc.getElementById('modal-cancelar-btn').onclick = fecharModalParent;
        parentDoc.getElementById('modal-confirmar-btn').onclick = restaurar;
    });

    // Contador de caracteres para SMS/WhatsApp
    templateContentSimple.addEventListener('input', function() {
        const len = this.value.length;
        charCount.textContent = len;
        smsWarning.classList.toggle('hidden', currentChannel !== 'sms' || len <= 160);
        isDirty = true;
    });

    // Marcar como dirty quando mudar subject
    templateSubject.addEventListener('input', () => isDirty = true);

    async function inicializar() {
        document.getElementById('templateTitle').textContent = i18n.editTitlePrefix + ' ' + (typeData.name || '');
        document.getElementById('templateDescription').textContent = typeData.description || '';

        // Criar tabs de canal
        criarChannelTabs();

        // Carregar variáveis
        await carregarVariaveis();

        // Inicializar editor se for email (ANTES de carregar o template)
        if (currentChannel === 'email') {
            await initTinyMCE('#templateContent', variables, () => isDirty = true);
            editorInitialized = true;
        }

        // Carregar template inicial (DEPOIS do editor estar pronto)
        await carregarTemplate();
    }

    function criarChannelTabs() {
        const channels = typeData.channels || ['email'];
        const icons = {
            'email': '<i class="fas fa-envelope"></i>',
            'whatsapp': '<i class="fab fa-whatsapp"></i>',
            'sms': '<i class="fas fa-sms"></i>'
        };
        const labels = {
            'email': 'Email',
            'whatsapp': 'WhatsApp',
            'sms': 'SMS'
        };

        let html = '';
        channels.forEach((ch, idx) => {
            html += `<button type="button" data-channel="${ch}" class="template-tab ${idx === 0 ? 'active' : ''}">
                ${icons[ch] || ''} ${labels[ch] || ch}
            </button>`;
        });
        channelTabs.innerHTML = html;

        // Se houver apenas um canal, selecionar ele
        if (channels.length > 0) {
            currentChannel = channels[0];
        }

        // Eventos de troca de canal
        channelTabs.querySelectorAll('.template-tab').forEach(tab => {
            tab.addEventListener('click', async function() {
                if (isDirty) {
                    const continuar = await confirmarModalParent(i18n.unsavedChanges);
                    if (!continuar) return;
                }
                channelTabs.querySelectorAll('.template-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentChannel = this.dataset.channel;
                await trocarEditor();
                await carregarTemplate();
            });
        });
    }

    // Eventos de troca de locale
    localeTabs.querySelectorAll('.template-tab').forEach(tab => {
        tab.addEventListener('click', async function() {
            if (isDirty) {
                const continuar = await confirmarModalParent('Você tem alterações não salvas. Deseja continuar?');
                if (!continuar) return;
            }
            localeTabs.querySelectorAll('.template-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentLocale = this.dataset.locale;
            await carregarTemplate();
        });
    });

    async function trocarEditor() {
        if (currentChannel === 'email') {
            subjectContainer.classList.remove('hidden');
            richEditorContainer.classList.remove('hidden');
            simpleEditorContainer.classList.add('hidden');

            if (!editorInitialized) {
                await initTinyMCE('#templateContent', variables, () => isDirty = true);
                editorInitialized = true;
            }
        } else {
            subjectContainer.classList.add('hidden');
            richEditorContainer.classList.add('hidden');
            simpleEditorContainer.classList.remove('hidden');

            // Destruir TinyMCE se estiver ativo
            if (tinymce.get('templateContent')) {
                tinymce.get('templateContent').remove();
                editorInitialized = false;
            }
        }
    }

    async function carregarVariaveis() {
        try {
            const result = await API.get(`/api/templates/variables/${slug}?locale=${currentLocale}`);
            if (result.success) {
                variables = result.data;
                renderizarVariaveis();
            }
        } catch (error) {
            console.error('Erro ao carregar variáveis:', error);
        }
    }

    function renderizarVariaveis() {
        const container = document.getElementById('variablesContainer');
        let html = '';

        for (const [entity, data] of Object.entries(variables)) {
            const entityVars = data.variables || [];
            html += `
                <div class="variable-group">
                    <div class="variable-group-header" onclick="toggleVariableGroup('${entity}')">
                        <span>${data.label || entity}</span>
                        <i class="fas fa-chevron-down text-xs" id="icon-${entity}"></i>
                    </div>
                    <div class="variable-group-items" id="vars-${entity}">
            `;

            entityVars.forEach(v => {
                html += `
                    <div class="variable-item" onclick="inserirVariavel('${v.variable}')" title="${v.example || ''}">
                        <code>${v.variable}</code>
                        <span class="variable-label">${v.label}</span>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        container.innerHTML = html || '<p class="text-xs text-slate-500">' + i18n.noVariables + '</p>';
    }

    // Funções globais
    window.toggleVariableGroup = function(entity) {
        const items = document.getElementById(`vars-${entity}`);
        const icon = document.getElementById(`icon-${entity}`);
        if (items) {
            items.classList.toggle('collapsed');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-right');
        }
    };

    window.inserirVariavel = function(variable) {
        if (currentChannel === 'email' && tinymce.activeEditor) {
            tinymce.activeEditor.insertContent(variable);
        } else {
            const textarea = templateContentSimple;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + variable.length, start + variable.length);

            // Atualizar contador
            charCount.textContent = textarea.value.length;
        }
        isDirty = true;
    };

    async function carregarTemplate() {
        try {
            const result = await API.get(`/api/templates/${slug}?channel=${currentChannel}&locale=${currentLocale}`);

            if (result.success && result.data) {
                currentTemplate = result.data;
                isCustom = result.data.is_custom;

                // Preencher campos
                templateSubject.value = result.data.subject || '';

                if (currentChannel === 'email') {
                    if (tinymce.get('templateContent')) {
                        tinymce.get('templateContent').setContent(result.data.content || '');
                    }
                } else {
                    templateContentSimple.value = result.data.content || '';
                    charCount.textContent = (result.data.content || '').length;
                }

                // Atualizar badges
                customBadge.classList.toggle('hidden', !isCustom);
                defaultBadge.classList.toggle('hidden', isCustom);

                // Mostrar botão "Restaurar Padrão" apenas se houver customização
                document.getElementById('btnRestaurar').classList.toggle('hidden', !isCustom);

                isDirty = false;
            }
        } catch (error) {
            console.error('Erro ao carregar template:', error);
        }
    }

    async function salvar() {
        const btn = document.getElementById('btnSalvar');
        const originalText = btn.innerHTML;

        try {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

            let content = '';
            if (currentChannel === 'email' && tinymce.get('templateContent')) {
                content = tinymce.get('templateContent').getContent();
            } else {
                content = templateContentSimple.value;
            }

            const data = {
                channel: currentChannel,
                locale: currentLocale,
                subject: templateSubject.value,
                content: content,
                content_plain: currentChannel !== 'email' ? content : ''
            };

            const result = await API.post(`/api/templates/${slug}`, data);

            if (result.success) {
                isDirty = false;
                isCustom = true;
                customBadge.classList.remove('hidden');
                defaultBadge.classList.add('hidden');
                document.getElementById('btnRestaurar').classList.remove('hidden');
                showToast(i18n.saveSuccess, 'success');
            } else {
                showToast(result.message || i18n.saveError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.saveError, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function preview() {
        try {
            const result = await API.get(`/api/templates/${slug}/preview?channel=${currentChannel}&locale=${currentLocale}`);

            if (result.success && result.data) {
                const subjectHtml = currentChannel === 'email'
                    ? `<div style="margin-bottom:1rem;">
                         <label style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;">${i18n.subjectLabel}</label>
                         <p style="color:#334155;margin:0.25rem 0 0 0;">${result.data.subject || i18n.noSubject}</p>
                       </div>`
                    : '';

                abrirModalParent({
                    title: i18n.previewTitle,
                    large: true,
                    body: `
                        ${subjectHtml}
                        <div>
                            <label style="font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;">${i18n.contentLabel}</label>
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:0.375rem;padding:1rem;max-height:400px;overflow-y:auto;margin-top:0.5rem;text-align:left;">
                                ${result.data.content || ''}
                            </div>
                        </div>
                    `,
                    footer: `<button type="button" id="modal-fechar-btn" style="background:#f1f5f9;color:#334155;padding:0.5rem 1rem;border-radius:0.375rem;font-size:0.875rem;border:none;cursor:pointer;">${i18n.close}</button>`
                });

                (window.parent || window).document.getElementById('modal-fechar-btn').onclick = fecharModalParent;
            } else {
                showToast(result.message || i18n.previewError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.previewError, 'error');
        }
    }

    async function restaurar() {
        const parentDoc = (window.parent || window).document;
        const btn = parentDoc.getElementById('modal-confirmar-btn');
        const originalText = btn.innerHTML;

        try {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:0.5rem;"></i>' + i18n.restoring;

            // Tenta restaurar (ignorar erro da API pois o affected_rows pode retornar incorreto)
            await API.post(`/api/templates/${slug}/restore`, {
                channel: currentChannel,
                locale: currentLocale
            });

            fecharModalParent();
            await carregarTemplate();

            // Verifica se a restauração funcionou
            if (!isCustom) {
                showToast(i18n.restoreSuccess, 'success');
            } else {
                showToast(i18n.restoreError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.restoreError, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function voltar() {
        if (isDirty) {
            const continuar = await confirmarModalParent(i18n.unsavedChanges);
            if (!continuar) return;
        }
        window.location.href = '/pages/configuracoes/templates';
    }
});
</script>
@endsection
