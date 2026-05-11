@extends('layouts.iframe')

@section('title', t('modules.promissorias_templates.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page">{{ t('modules.promissorias_templates.title') }}</h2>
            <p class="text-xs text-slate-500">{{ t('modules.promissorias_templates.subtitle') }}</p>
        </div>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>{{ t('modules.promissorias_templates.buttons.back') }}
        </button>
    </div>

    <!-- Tabs de Tipo de Template -->
    <div class="template-type-tabs mb-3" id="typeTabs">
        <!-- Populado via JavaScript -->
    </div>

    <!-- Tabs de Idioma -->
    <div class="template-locale-tabs mb-4" id="localeTabs">
        <button type="button" data-locale="pt_BR" class="template-tab active">{{ t('modules.promissorias_templates.locales.pt_BR') }}</button>
        <button type="button" data-locale="en_US" class="template-tab">{{ t('modules.promissorias_templates.locales.en_US') }}</button>
        <button type="button" data-locale="es_ES" class="template-tab">{{ t('modules.promissorias_templates.locales.es_ES') }}</button>
        <button type="button" data-locale="pt_PT" class="template-tab">{{ t('modules.promissorias_templates.locales.pt_PT') }}</button>
        <button type="button" data-locale="it_IT" class="template-tab">{{ t('modules.promissorias_templates.locales.it_IT') }}</button>
    </div>

    <!-- Status -->
    <div class="flex items-center gap-2 mb-4">
        <span id="customBadge" class="hidden badge-custom">
            <i class="fas fa-check-circle mr-1"></i>{{ t('modules.promissorias_templates.labels.customized') }}
        </span>
        <span id="defaultBadge" class="hidden badge-default">
            <i class="fas fa-database mr-1"></i>{{ t('modules.promissorias_templates.labels.using_default') }}
        </span>
    </div>

    <!-- Area Principal -->
    <div class="template-editor-container">
        <div class="template-editor-main">
            <!-- Editor -->
            <div class="form-input-group mb-4">
            <label class="form-label-group"><?= t('templates.fields.content') ?></label>
                <textarea id="templateContent" class="tinymce-editor" rows="10"></textarea>
            </div>

            <!-- Botoes de Acao -->
            <div class="flex flex-wrap gap-2">
                <button type="button" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-save mr-2"></i>{{ t('modules.promissorias_templates.buttons.save') }}
                </button>
                <button type="button" id="btnPreview" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-eye mr-2"></i>{{ t('modules.promissorias_templates.buttons.preview') }}
                </button>
                <button type="button" id="btnRestaurar" class="btn-outline-danger py-2 px-4 rounded-md text-sm font-medium hidden">
                    <i class="fas fa-undo mr-2"></i>{{ t('modules.promissorias_templates.buttons.restore_default') }}
                </button>
            </div>
        </div>

        <!-- Painel de Variaveis -->
        <div class="template-variables-panel">
            <h4 class="text-sm font-semibold text-slate-700 mb-3">
                <i class="fas fa-code mr-1"></i>{{ t('modules.promissorias_templates.labels.available_variables') }}
            </h4>
            <p class="text-xs text-slate-500 mb-3">{{ t('modules.promissorias_templates.labels.click_to_insert') }}</p>
            <div id="variablesContainer">
                <div class="flex items-center justify-center py-4">
                    <i class="fas fa-spinner fa-spin text-slate-400"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Preview -->
<div id="previewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" id="previewModalOverlay"></div>
        <div class="inline-block w-full max-w-2xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">{{ t('modules.promissorias_templates.labels.preview_title') }}</h3>
                <button type="button" id="closePreviewModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-4 py-4" id="previewContent">
                <!-- Preview sera inserido aqui -->
            </div>
        </div>
    </div>
</div>

<style>
    .template-type-tabs,
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
        color: #166534;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
    }
    .badge-custom:not(.hidden) {
        display: inline-flex;
        align-items: center;
    }
    .badge-default {
        background: #e5e7eb;
        color: #374151;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
    }
    .badge-default:not(.hidden) {
        display: inline-flex;
        align-items: center;
    }

    #templateContent {
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 0.875rem;
        line-height: 1.6;
    }

    .template-preview-var {
        background: #fef3c7;
        padding: 0 0.25rem;
        border-radius: 0.25rem;
    }
    .template-preview-unknown {
        background: #fee2e2;
    }
</style>
@endsection

@section('scripts')
<script src="/assets/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
<script src="<?= asset('js/tinymce-init.min.js') ?>"></script>
<script>
(function() {
    // Traducoes para JavaScript
    const i18n = {
        unsaved_changes_exit: '{{ t('modules.promissorias_templates.messages.unsaved_changes_exit') }}',
        unsaved_changes_continue: '{{ t('modules.promissorias_templates.messages.unsaved_changes_continue') }}',
        load_types_error: '{{ t('modules.promissorias_templates.messages.load_types_error') }}',
        load_template_error: '{{ t('modules.promissorias_templates.messages.load_template_error') }}',
        content_required: '{{ t('modules.promissorias_templates.messages.content_required') }}',
        saved_success: '{{ t('modules.promissorias_templates.messages.saved_success') }}',
        save_error: '{{ t('modules.promissorias_templates.messages.save_error') }}',
        restore_confirm: '{{ t('modules.promissorias_templates.messages.restore_confirm') }}',
        restored_success: '{{ t('modules.promissorias_templates.messages.restored_success') }}',
        restore_error: '{{ t('modules.promissorias_templates.messages.restore_error') }}',
        preview_empty: '{{ t('modules.promissorias_templates.messages.preview_empty') }}',
        preview_error: '{{ t('modules.promissorias_templates.messages.preview_error') }}',
        no_variables: '{{ t('modules.promissorias_templates.labels.no_variables') }}'
    };

    // Estado
    let templateTypes = [];
    let currentType = null;
    let currentLocale = 'pt_BR';
    let currentTemplate = null;
    let hasUnsavedChanges = false;
    let tinymceInitialized = false;

    // Elementos
    const typeTabsContainer = document.getElementById('typeTabs');
    const localeTabsContainer = document.getElementById('localeTabs');
    const templateContent = document.getElementById('templateContent');
    const variablesContainer = document.getElementById('variablesContainer');
    const customBadge = document.getElementById('customBadge');
    const defaultBadge = document.getElementById('defaultBadge');

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (hasUnsavedChanges && !confirm(i18n.unsaved_changes_exit)) {
            return;
        }
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== CARREGAMENTO =====

    async function carregarTipos() {
        try {
            const result = await API.get('/api/promissorias/templates/types');
            if (result.success && result.data) {
                templateTypes = result.data;
                renderTypeTabs();
                if (templateTypes.length > 0) {
                    selecionarTipo(templateTypes[0].slug);
                }
            }
        } catch (error) {
            console.error('Erro ao carregar tipos:', error);
            toast.error(i18n.load_types_error);
        }
    }

    async function carregarTemplate() {
        if (!currentType) return;

        try {
            const result = await API.get(`/api/promissorias/templates/${currentType}`, { locale: currentLocale });
            if (result.success && result.data) {
                currentTemplate = result.data;
                const content = result.data.content || '';
                const variables = result.data.available_variables || {};

                // Renderizar variaveis primeiro
                renderVariables(variables);
                atualizarBadges(result.data.is_custom);

                // Inicializar TinyMCE na primeira vez
                if (!tinymceInitialized && typeof initTinyMCE === 'function') {
                    await initTinyMCE('#templateContent', variables, () => {
                        hasUnsavedChanges = true;
                    });
                    tinymceInitialized = true;
                }

                // Setar conteudo no TinyMCE ou textarea
                if (typeof tinymce !== 'undefined' && tinymce.get('templateContent')) {
                    tinymce.get('templateContent').setContent(content);
                } else {
                    templateContent.value = content;
                }

                hasUnsavedChanges = false;
            }
        } catch (error) {
            console.error('Erro ao carregar template:', error);
            toast.error(i18n.load_template_error);
        }
    }

    // ===== RENDERIZACAO =====

    function renderTypeTabs() {
        let html = '';
        templateTypes.forEach(type => {
            const icon = type.category === 'promissoria' ? 'fa-file-contract' : 'fa-receipt';
            html += `
                <button type="button" data-type="${type.slug}" class="template-tab">
                    <i class="fas ${icon}"></i>${type.name}
                </button>
            `;
        });
        typeTabsContainer.innerHTML = html;

        // Event listeners
        typeTabsContainer.querySelectorAll('.template-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                if (hasUnsavedChanges && !confirm(i18n.unsaved_changes_continue)) {
                    return;
                }
                selecionarTipo(tab.dataset.type);
            });
        });
    }

    function renderVariables(variables) {
        if (!variables || Object.keys(variables).length === 0) {
            variablesContainer.innerHTML = '<p class="text-xs text-slate-500">' + i18n.no_variables + '</p>';
            return;
        }

        let html = '';
        for (const [entity, entityData] of Object.entries(variables)) {
            const entityLabel = entityData.label || entity;
            const entityVars = entityData.variables || [];

            html += `
                <div class="variable-group">
                    <div class="variable-group-header" data-entity="${entity}">
                        <span>${entityLabel}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                    <div class="variable-group-items">
            `;

            for (const varInfo of entityVars) {
                html += `
                    <div class="variable-item" data-variable="${varInfo.variable}" title="${varInfo.example || ''}">
                        <code>${varInfo.variable}</code>
                        <span class="variable-label">${varInfo.label}</span>
                    </div>
                `;
            }

            html += '</div></div>';
        }

        variablesContainer.innerHTML = html;

        // Event listeners para grupos
        variablesContainer.querySelectorAll('.variable-group-header').forEach(header => {
            header.addEventListener('click', () => {
                const items = header.nextElementSibling;
                const icon = header.querySelector('i');
                items.classList.toggle('collapsed');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-right');
            });
        });

        // Event listeners para variaveis
        variablesContainer.querySelectorAll('.variable-item').forEach(item => {
            item.addEventListener('click', () => {
                inserirVariavel(item.dataset.variable);
            });
        });
    }

    function atualizarBadges(isCustom) {
        const btnRestaurar = document.getElementById('btnRestaurar');

        if (isCustom) {
            customBadge.classList.remove('hidden');
            defaultBadge.classList.add('hidden');
            btnRestaurar.classList.remove('hidden');
        } else {
            customBadge.classList.add('hidden');
            defaultBadge.classList.remove('hidden');
            btnRestaurar.classList.add('hidden');
        }
    }

    // ===== ACOES =====

    function selecionarTipo(slug) {
        currentType = slug;

        // Atualizar tabs ativas
        typeTabsContainer.querySelectorAll('.template-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.type === slug);
        });

        carregarTemplate();
    }

    function selecionarLocale(locale) {
        currentLocale = locale;

        // Atualizar tabs ativas
        localeTabsContainer.querySelectorAll('.template-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.locale === locale);
        });

        carregarTemplate();
    }

    function inserirVariavel(variable) {
        // Inserir no TinyMCE se disponivel
        if (typeof tinymce !== 'undefined' && tinymce.get('templateContent')) {
            const editor = tinymce.get('templateContent');
            const varHtml = `<span class="mceNonEditable template-var" contenteditable="false">${variable}</span>&nbsp;`;
            editor.insertContent(varHtml);
            editor.focus();
        } else {
            // Fallback para textarea simples
            const textarea = templateContent;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;

            const insertText = `<strong>${variable}</strong>`;
            textarea.value = text.substring(0, start) + insertText + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + insertText.length;
        }
        hasUnsavedChanges = true;
    }

    async function salvarTemplate() {
        if (!currentType) return;

        // Obter conteudo do TinyMCE ou textarea
        let content;
        if (typeof tinymce !== 'undefined' && tinymce.get('templateContent')) {
            content = tinymce.get('templateContent').getContent().trim();
        } else {
            content = templateContent.value.trim();
        }

        if (!content) {
            toast.error(i18n.content_required);
            return;
        }

        try {
            const result = await API.post(`/api/promissorias/templates/${currentType}`, {
                locale: currentLocale,
                content: content
            });

            if (result.success) {
                toast.success(result.message || i18n.saved_success);
                hasUnsavedChanges = false;
                carregarTemplate(); // Recarregar para atualizar badges
            } else {
                toast.error(result.message || i18n.save_error);
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            toast.error(error.message || i18n.save_error);
        }
    }

    async function restaurarPadrao() {
        if (!currentType) return;

        if (!confirm(i18n.restore_confirm)) {
            return;
        }

        try {
            const result = await API.post(`/api/promissorias/templates/${currentType}/restore`, {
                locale: currentLocale
            });

            if (result.success) {
                toast.success(result.message || i18n.restored_success);
                hasUnsavedChanges = false;
                carregarTemplate();
            } else {
                toast.error(result.message || i18n.restore_error);
            }
        } catch (error) {
            console.error('Erro ao restaurar:', error);
            toast.error(error.message || i18n.restore_error);
        }
    }

    async function mostrarPreview() {
        // Obter conteudo do TinyMCE ou textarea
        let content;
        if (typeof tinymce !== 'undefined' && tinymce.get('templateContent')) {
            content = tinymce.get('templateContent').getContent();
        } else {
            content = templateContent.value;
        }

        if (!content) {
            toast.warning(i18n.preview_empty);
            return;
        }

        try {
            const result = await API.post('/api/promissorias/templates/preview', { content: content });
            if (result.success && result.data) {
                document.getElementById('previewContent').innerHTML = result.data.html;
                document.getElementById('previewModal').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Erro ao gerar preview:', error);
            toast.error(i18n.preview_error);
        }
    }

    function fecharPreview() {
        document.getElementById('previewModal').classList.add('hidden');
    }

    // ===== EVENT LISTENERS =====

    document.addEventListener('DOMContentLoaded', function() {
        carregarTipos();

        // Botao Voltar
        document.getElementById('btnVoltar')?.addEventListener('click', () => {
            navegarPara('/pages/promissorias');
        });

        // Tabs de idioma
        localeTabsContainer.querySelectorAll('.template-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                if (hasUnsavedChanges && !confirm(i18n.unsaved_changes_continue)) {
                    return;
                }
                selecionarLocale(tab.dataset.locale);
            });
        });

        // Botao Salvar
        document.getElementById('btnSalvar')?.addEventListener('click', salvarTemplate);

        // Botao Preview
        document.getElementById('btnPreview')?.addEventListener('click', mostrarPreview);

        // Botao Restaurar
        document.getElementById('btnRestaurar')?.addEventListener('click', restaurarPadrao);

        // Modal Preview
        document.getElementById('closePreviewModal')?.addEventListener('click', fecharPreview);
        document.getElementById('previewModalOverlay')?.addEventListener('click', fecharPreview);

        // Detectar alteracoes no textarea (fallback caso TinyMCE nao carregue)
        templateContent?.addEventListener('input', () => {
            hasUnsavedChanges = true;
        });

        // Prevenir saida com alteracoes nao salvas
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });
})();
</script>
@endsection
