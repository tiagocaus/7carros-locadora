@extends('layouts.iframe')

@section('title', t(isset($documento) ? 'modules.documentos.edit_title' : 'modules.documentos.new_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page">{{ isset($documento) ? t('modules.documentos.edit_title') : t('modules.documentos.new_title') }}</h2>
            <p class="text-xs text-slate-500"><?= t('modules.documentos.description') ?></p>
        </div>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Area Principal -->
    <div class="documento-editor-container">
        <div class="documento-editor-main">
            <!-- Titulo -->
            <div class="form-input-group mb-4">
                <label for="documentoTitulo" class="form-label-group"><?= t('modules.documentos.fields.title') ?> <span class="text-red-500">*</span></label>
                <input type="text" id="documentoTitulo" class="form-input-group-field" placeholder="<?= t('modules.documentos.placeholders.title_example') ?>" value="{{ $documento['titulo'] ?? '' }}">
            </div>

            <!-- Tipo e Status -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-input-group">
                    <label for="documentoTipo" class="form-label-group"><?= t('modules.documentos.fields.type') ?></label>
                    <select id="documentoTipo" class="form-input-group-field">
                        @foreach($tipos as $value => $label)
                            <option value="{{ $value }}" {{ (isset($documento) && $documento['tipo'] == $value) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-input-group">
                    <label for="documentoStatus" class="form-label-group"><?= t('modules.documentos.fields.status') ?></label>
                    <select id="documentoStatus" class="form-input-group-field">
                        <option value="1" {{ (!isset($documento) || $documento['status'] == 1) ? 'selected' : '' }}><?= t('modules.documentos.badges.status_active') ?></option>
                        <option value="0" {{ (isset($documento) && $documento['status'] == 0) ? 'selected' : '' }}><?= t('modules.documentos.badges.status_inactive') ?></option>
                    </select>
                </div>
            </div>

            <!-- Conteudo -->
            <div class="form-input-group mb-4">
                <label class="form-label-group"><?= t('modules.documentos.fields.content') ?></label>
                <textarea id="documentoConteudo" class="tinymce-editor">{{ $documento['texto'] ?? '' }}</textarea>
            </div>

            <!-- Botoes de Acao -->
            <div class="flex flex-wrap gap-2">
                <button type="button" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                </button>
                <button type="button" id="btnPreview" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-eye mr-2"></i><?= t('common.labels.preview') ?>
                </button>
            </div>
        </div>

        <!-- Painel de Variaveis -->
        <div class="documento-variables-panel">
            <h4 class="text-sm font-semibold text-slate-700 mb-3">
                <i class="fas fa-code mr-1"></i><?= t('modules.documentos.variables.title') ?>
            </h4>
            <p class="text-xs text-slate-500 mb-3"><?= t('modules.documentos.variables.description') ?></p>
            <div id="variablesContainer">
                <div class="flex items-center justify-center py-4">
                    <i class="fas fa-spinner fa-spin text-slate-400"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .documento-editor-container {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 1rem;
    }
    @media (max-width: 768px) {
        .documento-editor-container {
            grid-template-columns: 1fr;
        }
        .documento-variables-panel {
            order: -1;
        }
    }

    .documento-editor-main {
        min-width: 0;
    }

    .documento-variables-panel {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 1rem;
        max-height: 600px;
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
</style>
@endsection

@section('scripts')
<script src="/assets/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
<script>window.TINYMCE_LICENSE_KEY = '<?= getenv('TINYMCE_LICENSE_KEY') ?: 'gpl' ?>';</script>
<script src="<?= asset('js/tinymce-init.min.js') ?>"></script>
<script>
(function () {
    const i18n = {
        imported: '<?= addslashes(t('modules.documentos.messages.imported')) ?>',
        editorError: '<?= addslashes(t('modules.documentos.messages.editor_error')) ?>',
        loadVarsError: '<?= addslashes(t('modules.documentos.variables.load_error')) ?>',
        noVariables: '<?= addslashes(t('modules.documentos.variables.no_variables')) ?>',
        titleRequired: '<?= addslashes(t('modules.documentos.messages.title_required')) ?>',
        saving: '<?= addslashes(t('modules.documentos.messages.saving')) ?>',
        saved: '<?= addslashes(t('modules.documentos.messages.saved')) ?>',
        saveError: '<?= addslashes(t('modules.documentos.messages.save_error')) ?>',
        contentRequired: '<?= addslashes(t('modules.documentos.messages.content_required')) ?>',
        previewError: '<?= addslashes(t('modules.documentos.messages.preview_error')) ?>',
        btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
        btnPreview: '<?= addslashes(t('common.labels.preview')) ?>',
        btnClose: '<?= addslashes(t('common.buttons.close')) ?>',
        loading: '<?= addslashes(t('common.labels.loading')) ?>',
    };

    // Estado
    const documentoId = {{ $documento['id'] ?? 'null' }};
    const isNewDocument = !documentoId;
    let variablesData = {};

    // Elementos
    const tituloInput = document.getElementById('documentoTitulo');
    const tipoSelect = document.getElementById('documentoTipo');
    const statusSelect = document.getElementById('documentoStatus');
    const variablesContainer = document.getElementById('variablesContainer');

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: page
            }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== MODAL DE ESCOLHA (TELA COMPLETA NO PARENT) =====

    // Se for novo documento, abrir modal de escolha no parent
    if (isNewDocument && window.parent !== window) {
        window.parent.postMessage({ action: 'openDocumentoEscolhaModal' }, '*');
    }

    // Escutar respostas do parent
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'documentoEscolhaManual') {
            // Usuario escolheu criar manualmente - nada a fazer, apenas continuar
        } else if (event.data && event.data.action === 'documentoEscolhaVoltar') {
            // Usuario clicou em voltar - navegar para lista de documentos
            navegarPara('/pages/documentos');
        } else if (event.data && event.data.action === 'documentoUploadSuccess') {
            // Upload bem sucedido - inserir HTML no editor
            insertUploadedContent(event.data.html, event.data.filename);
        }
    });

    /**
     * Insere conteudo do upload no TinyMCE
     */
    function insertUploadedContent(html, filename) {
        // Aguardar TinyMCE estar pronto
        let attempts = 0;
        const maxAttempts = 50;

        const checkTinyMCE = () => {
            const editor = tinymce.get('documentoConteudo');
            if (editor && editor.initialized) {
                // Inserir conteudo
                editor.setContent(html);

                // Sugerir titulo baseado no nome do arquivo
                const titulo = filename.replace(/\.(pdf|docx)$/i, '').replace(/[_-]/g, ' ');
                if (tituloInput && !tituloInput.value) {
                    tituloInput.value = titulo;
                }

                showToast(i18n.imported, 'success');
            } else if (attempts < maxAttempts) {
                attempts++;
                setTimeout(checkTinyMCE, 100);
            } else {
                showToast(i18n.editorError, 'error');
            }
        };

        checkTinyMCE();
    }

    /**
     * Mostra toast de notificacao
     */
    function showToast(message, type = 'info') {
        if (window.parent !== window && typeof window.parent.postMessage === 'function') {
            window.parent.postMessage({
                action: 'showToast',
                message: message,
                type: type
            }, '*');
        } else if (typeof toast !== 'undefined') {
            toast[type] ? toast[type](message) : toast.show(message);
        } else {
            alert(message);
        }
    }

    // ===== VARIAVEIS =====

    async function carregarVariaveis() {
        try {
            const result = await API.get('/api/documentos/variables', { locale: 'pt_BR' });

            if (result.success && result.data) {
                variablesData = result.data;
                renderVariaveis(result.data);
                // Inicializar TinyMCE apos carregar variaveis
                await initTinyMCE('#documentoConteudo', result.data, null, { enableFontSize: true });
            } else {
                variablesContainer.innerHTML = '<p class="text-sm text-red-500">' + i18n.loadVarsError + '</p>';
            }
        } catch (error) {
            console.error('Erro ao buscar variaveis:', error);
            variablesContainer.innerHTML = '<p class="text-sm text-red-500">Erro ao carregar variaveis</p>';
        }
    }

    function renderVariaveis(data) {
        let html = '';

        for (const [entity, entityData] of Object.entries(data)) {
            const label = entityData.label || entity;
            const variables = entityData.variables || [];

            if (variables.length === 0) continue;

            html += `
                <div class="variable-group" data-entity="${entity}">
                    <div class="variable-group-header" onclick="toggleGroup('${entity}')">
                        <span><i class="fas fa-folder mr-2"></i>${label}</span>
                        <i class="fas fa-chevron-right text-xs"></i>
                    </div>
                    <div class="variable-group-items collapsed" id="group-${entity}">
            `;

            variables.forEach(v => {
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

        variablesContainer.innerHTML = html || '<p class="text-sm text-slate-500">' + i18n.noVariables + '</p>';
    }

    window.toggleGroup = function(entity) {
        const group = document.getElementById('group-' + entity);
        if (group) {
            group.classList.toggle('collapsed');
            const header = group.previousElementSibling;
            const icon = header?.querySelector('.fa-chevron-down, .fa-chevron-right');
            if (icon) {
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-right');
            }
        }
    };

    window.inserirVariavel = function(variable) {
        // Inserir variável com span não-editável
        const html = '<span class="mceNonEditable template-var" contenteditable="false">' + variable + '</span>&nbsp;';
        insertTinyMCEContent('documentoConteudo', html);
        const editor = tinymce.get('documentoConteudo');
        if (editor) editor.focus();
    };

    // ===== SALVAR =====

    async function salvarDocumento() {
        const titulo = tituloInput.value.trim();
        const tipo = tipoSelect.value;
        const status = statusSelect.value;
        const texto = getTinyMCEContent('documentoConteudo');

        // Validacao
        if (!titulo) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.titleRequired }, '*');
            tituloInput.focus();
            return;
        }

        const dados = {
            titulo: titulo,
            tipo: tipo,
            status: status,
            texto: texto
        };

        try {
            const btnSalvar = document.getElementById('btnSalvar');
            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

            let url, method;
            if (documentoId) {
                url = `/documentos/${documentoId}/atualizar`;
            } else {
                url = '/documentos/salvar';
            }

            const result = await API.post(url, dados);

            if (result.success) {
                // Notificar sucesso
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        message: result.message || i18n.saved,
                        type: 'success'
                    }, '*');
                }

                // Se era novo documento ou copia de modelo padrao, redirecionar para edicao
                if (result.data && result.data.id && (!documentoId || result.data.copied_from_global)) {
                    navegarPara('/pages/documentos/' + result.data.id + '/editar');
                }
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.saveError }, '*');
        } finally {
            const btnSalvar = document.getElementById('btnSalvar');
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.btnSave;
        }
    }

    // ===== PREVIEW =====

    async function previewDocumento() {
        const titulo = tituloInput.value.trim() || i18n.btnPreview;
        const conteudo = getTinyMCEContent('documentoConteudo');

        if (!conteudo) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.contentRequired }, '*');
            return;
        }

        try {
            const btnPreview = document.getElementById('btnPreview');
            btnPreview.disabled = true;
            btnPreview.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.loading;

            const result = await API.post('/api/documentos/preview', { titulo, conteudo });

            if (result.success && result.data) {
                // Abrir modal no parent
                if (window.parent !== window && typeof window.parent.abrirModalParent === 'function') {
                    window.parent.abrirModalParent({
                        title: 'Preview: ' + result.data.titulo,
                        large: true,
                        body: `
                            <div style="background: white; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; max-height: 500px; overflow-y: auto;">
                                ${result.data.conteudo}
                            </div>
                            <style>
                                .template-preview-var {
                                    background: #dbeafe;
                                    padding: 1px 4px;
                                    border-radius: 3px;
                                    color: #1e40af;
                                    font-weight: 500;
                                }
                                .template-preview-unknown {
                                    background: #fee2e2;
                                    padding: 1px 4px;
                                    border-radius: 3px;
                                    color: #991b1b;
                                }
                            </style>
                        `,
                        footer: '<button type="button" class="btn-secondary py-2 px-4 rounded-md text-sm" onclick="fecharModalParent()">' + i18n.btnClose + '</button>'
                    });
                } else {
                    // Fallback: abrir em nova janela
                    const win = window.open('', '_blank', 'width=800,height=600');
                    win.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Preview: ${result.data.titulo}</title>
                            <style>
                                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
                                .template-preview-var { background: #dbeafe; padding: 1px 4px; border-radius: 3px; color: #1e40af; font-weight: 500; }
                                .template-preview-unknown { background: #fee2e2; padding: 1px 4px; border-radius: 3px; color: #991b1b; }
                            </style>
                        </head>
                        <body>${result.data.conteudo}</body>
                        </html>
                    `);
                }
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.previewError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.previewError }, '*');
        } finally {
            const btnPreview = document.getElementById('btnPreview');
            btnPreview.disabled = false;
            btnPreview.innerHTML = '<i class="fas fa-eye mr-2"></i>' + i18n.btnPreview;
        }
    }

    // ===== EVENT LISTENERS =====

    document.getElementById('btnVoltar')?.addEventListener('click', function() {
        navegarPara('/pages/documentos');
    });

    document.getElementById('btnSalvar')?.addEventListener('click', salvarDocumento);
    document.getElementById('btnPreview')?.addEventListener('click', previewDocumento);

    // ===== INICIALIZACAO =====

    carregarVariaveis();
})();
</script>
@endsection
