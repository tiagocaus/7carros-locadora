@extends('layouts.iframe')

@section('title', '<?= t("modules.checklist_modelos.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.checklist_modelos.new_title') ?></h2>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formChecklistModelo" method="POST">
        <input type="hidden" id="id" name="id">

        <div class="form-section">
            <h3 class="form-section-title"><?= t('modules.checklist_modelos.sections.model_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Nome -->
                <div class="form-input-group md:col-span-1">
                    <label for="nome" class="form-label-group"><?= t('modules.checklist_modelos.fields.name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="nome" name="nome" class="form-input-group-field" required maxlength="50" placeholder="<?= t('modules.checklist_modelos.placeholders.name_example') ?>">
                </div>

                <!-- Tipo -->
                <div class="form-input-group">
                    <label for="tipo" class="form-label-group"><?= t('modules.checklist_modelos.fields.type') ?></label>
                    <select id="tipo" name="tipo" class="form-input-group-field">
                        <option value="0"><?= t('modules.checklist_modelos.type_options.digital') ?></option>
                        <option value="1"><?= t('modules.checklist_modelos.type_options.printed') ?></option>
                    </select>
                </div>

                <!-- Status -->
                <div class="form-input-group">
                    <label for="status" class="form-label-group"><?= t('modules.checklist_modelos.fields.status') ?></label>
                    <select id="status" name="status" class="form-input-group-field">
                        <option value="A"><?= t('modules.checklist_modelos.status_options.active') ?></option>
                        <option value="I"><?= t('modules.checklist_modelos.status_options.inactive') ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <!-- Secao Questoes -->
            <div class="form-section">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><?= t('modules.checklist_modelos.sections.questions') ?></h3>
                    <button type="button" id="btnAddItemQuestoes" class="btn-secondary py-1.5 px-3 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-plus mr-2"></i><?= t('modules.checklist_modelos.sections.questions') ?>
                    </button>
                </div>

                <div id="nestableContainerQuestoes" class="nestable-container">
                    <ol class="nestable-list"></ol>
                </div>
                <textarea id="jsonTextareaQuestoes" name="questoes" class="hidden"></textarea>
            </div>

            <!-- Secao Vistoria -->
            <div class="form-section" id="secaoVistoria">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><?= t('modules.checklist_modelos.sections.inspection') ?></h3>
                    <button type="button" id="btnAddItemVistoria" class="btn-secondary py-1.5 px-3 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-plus mr-2"></i><?= t('modules.checklist_modelos.sections.inspection') ?>
                    </button>
                </div>

                <div id="nestableContainerVistoria" class="nestable-container">
                    <ol class="nestable-list"></ol>
                </div>
                <textarea id="jsonTextareaVistoria" name="vistoria" class="hidden"></textarea>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script src="<?= asset('js/nestable-list.min.js') ?>"></script>
<script>
    (function() {
        const i18n = {
            editTitle: '<?= addslashes(t('modules.checklist_modelos.edit_title')) ?>',
            loadError: '<?= addslashes(t('modules.checklist_modelos.messages.load_error')) ?>',
            notFound: '<?= addslashes(t('modules.checklist_modelos.messages.not_found')) ?>',
            saveError: '<?= addslashes(t('modules.checklist_modelos.messages.save_error')) ?>',
            saving: '<?= addslashes(t('modules.checklist_modelos.messages.saving')) ?>',
            saved: '<?= addslashes(t('modules.checklist_modelos.messages.saved')) ?>',
            requiredFields: '<?= addslashes(t('modules.checklist_modelos.messages.required_fields')) ?>',
            requiredName: '<?= addslashes(t('modules.checklist_modelos.messages.required_name')) ?>',
            btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
            nestableQuestion: '<?= addslashes(t('modules.checklist_modelos.nestable.question')) ?>',
            nestableInspection: '<?= addslashes(t('modules.checklist_modelos.nestable.inspection')) ?>',
            nestableAddQuestion: '<?= addslashes(t('modules.checklist_modelos.nestable.add_question')) ?>',
            nestableEditQuestion: '<?= addslashes(t('modules.checklist_modelos.nestable.edit_question')) ?>',
            nestableAddInspection: '<?= addslashes(t('modules.checklist_modelos.nestable.add_inspection')) ?>',
            nestableEditInspection: '<?= addslashes(t('modules.checklist_modelos.nestable.edit_inspection')) ?>',
            nestableItem: '<?= addslashes(t('modules.checklist_modelos.nestable.item')) ?>',
            fieldName: '<?= addslashes(t('modules.checklist_modelos.fields.item_name')) ?>',
        };

        let registroId = null;
        let nestableQuestoes = null;
        let nestableVistoria = null;

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

        // ===== CLASSE CUSTOMIZADA PARA NESTABLE SEM HIERARQUIA =====
        class NestableListFlat extends NestableList {
            constructor(containerId, textareaId, instanceKey, addTitle, editTitle, options = {}) {
                // Forcar maxDepth = 0 para impedir hierarquia
                options.maxDepth = 0;
                super(containerId, textareaId, options);
                this.instanceKey = instanceKey; // 'Questao' ou 'Vistoria'
                this.addTitle = addTitle;
                this.editTitle = editTitle;
            }

            // Override para usar modal global via postMessage
            openEditModal(itemId, currentName) {
                this.currentEditItem = itemId;
                window.parent.postMessage({
                    action: 'openInputModal',
                    title: this.editTitle,
                    label: i18n.fieldName,
                    value: currentName,
                    callbackAction: 'editItem' + this.instanceKey
                }, '*');
            }

            // Override para usar modal global de exclusao via postMessage
            openDeleteModal(recordId, recordName) {
                this.currentDeleteItem = recordId;
                window.parent.postMessage({
                    action: 'openDeleteModal',
                    recordId: recordId,
                    recordName: recordName,
                    recordType: i18n.nestableItem,
                    confirmType: 'none',
                    customAction: 'deleteItem' + this.instanceKey
                }, '*');
            }

            // Metodo para abrir modal de adicionar (novo item)
            openAddModal() {
                window.parent.postMessage({
                    action: 'openInputModal',
                    title: this.addTitle,
                    label: i18n.fieldName,
                    value: '',
                    callbackAction: 'addItem' + this.instanceKey
                }, '*');
            }
        }

        // ===== INICIALIZACAO DOS NESTABLE =====
        function initNestableLists() {
            // Questoes
            nestableQuestoes = new NestableListFlat(
                'nestableContainerQuestoes',
                'jsonTextareaQuestoes',
                'Questao',
                i18n.nestableAddQuestion,
                i18n.nestableEditQuestion,
                { maxDepth: 0 }
            );

            // Vistoria
            nestableVistoria = new NestableListFlat(
                'nestableContainerVistoria',
                'jsonTextareaVistoria',
                'Vistoria',
                i18n.nestableAddInspection,
                i18n.nestableEditInspection,
                { maxDepth: 0 }
            );

            // Botoes de adicionar (abre modal para digitar nome)
            document.getElementById('btnAddItemQuestoes')?.addEventListener('click', () => {
                nestableQuestoes.openAddModal();
            });

            document.getElementById('btnAddItemVistoria')?.addEventListener('click', () => {
                nestableVistoria.openAddModal();
            });
        }

        // ===== TOGGLE SECAO VISTORIA =====
        function toggleVistoriaSection() {
            const tipo = document.getElementById('tipo').value;
            const secaoVistoria = document.getElementById('secaoVistoria');
            if (tipo === '1') {
                secaoVistoria.style.display = 'none';
            } else {
                secaoVistoria.style.display = '';
            }
        }

        // Listener para respostas do parent (modais globais)
        window.addEventListener('message', function(event) {
            if (!event.data || !event.data.action) return;

            // Adicionar novos itens
            if (event.data.action === 'addItemQuestao') {
                if (event.data.value) {
                    nestableQuestoes.addItem(event.data.value);
                }
            } else if (event.data.action === 'addItemVistoria') {
                if (event.data.value) {
                    nestableVistoria.addItem(event.data.value);
                }
            // Editar itens existentes
            } else if (event.data.action === 'editItemQuestao') {
                // Atualizar nome do item de questao
                if (nestableQuestoes && nestableQuestoes.currentEditItem) {
                    nestableQuestoes.updateItemName(nestableQuestoes.currentEditItem, event.data.value);
                    nestableQuestoes.currentEditItem = null;
                }
            } else if (event.data.action === 'editItemVistoria') {
                // Atualizar nome do item de vistoria
                if (nestableVistoria && nestableVistoria.currentEditItem) {
                    nestableVistoria.updateItemName(nestableVistoria.currentEditItem, event.data.value);
                    nestableVistoria.currentEditItem = null;
                }
            } else if (event.data.action === 'confirmDelete') {
                // Processar exclusao baseado em customAction
                if (event.data.customAction === 'deleteItemQuestao') {
                    if (nestableQuestoes) {
                        nestableQuestoes.deleteItem(event.data.recordId);
                        nestableQuestoes.currentDeleteItem = null;
                    }
                } else if (event.data.customAction === 'deleteItemVistoria') {
                    if (nestableVistoria) {
                        nestableVistoria.deleteItem(event.data.recordId);
                        nestableVistoria.currentDeleteItem = null;
                    }
                }
            }
        });

        // ===== CARREGAR DADOS =====
        async function carregarDados(id) {
            try {
                const result = await API.get(`/api/checklist-modelos/${id}`);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    mostrarAlerta(i18n.loadError + ': ' + (result.message || i18n.notFound), function() {
                        navegarPara('/pages/checklist-modelos');
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                mostrarAlerta(i18n.loadError, function() {
                    navegarPara('/pages/checklist-modelos');
                });
            }
        }

        function preencherFormulario(data) {
            document.getElementById('id').value = data.id;
            document.getElementById('nome').value = data.nome || '';
            document.getElementById('tipo').value = data.tipo || '0';
            document.getElementById('status').value = data.status || 'A';

            // Carregar questoes no NestableList
            if (data.questoes) {
                try {
                    const questoesJson = typeof data.questoes === 'string' ? data.questoes : JSON.stringify(data.questoes);
                    document.getElementById('jsonTextareaQuestoes').value = questoesJson;
                    nestableQuestoes.loadFromTextarea();
                } catch (e) {
                    console.error('Erro ao carregar questoes:', e);
                }
            }

            // Carregar vistoria no NestableList
            if (data.vistoria) {
                try {
                    const vistoriaJson = typeof data.vistoria === 'string' ? data.vistoria : JSON.stringify(data.vistoria);
                    document.getElementById('jsonTextareaVistoria').value = vistoriaJson;
                    nestableVistoria.loadFromTextarea();
                } catch (e) {
                    console.error('Erro ao carregar vistoria:', e);
                }
            }

            // Atualizar titulo
            document.getElementById('pageTitle').textContent = i18n.editTitle;

            // Atualizar visibilidade da secao Vistoria
            toggleVistoriaSection();
        }

        // ===== MODAL DE ALERTA =====
        function mostrarAlerta(mensagem, callbackAction = null) {
            window.parent.postMessage({
                action: 'openAlert',
                message: mensagem,
                callbackAction: callbackAction ? 'callback' : null
            }, '*');

            if (callbackAction) {
                const handler = function(event) {
                    if (event.data && event.data.action === 'alertModalClosed') {
                        window.removeEventListener('message', handler);
                        callbackAction();
                    }
                };
                window.addEventListener('message', handler);
            }
        }

        // ===== SALVAR =====
        async function salvar(e) {
            e.preventDefault();

            const form = document.getElementById('formChecklistModelo');
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());

            // Validar campos obrigatorios
            const erros = [];

            if (!dados.nome || dados.nome.trim() === '') {
                erros.push(i18n.requiredName);
            }

            if (erros.length > 0) {
                mostrarAlerta(i18n.requiredFields + '\n\n' + erros.join('\n'));
                return;
            }

            // Atualizar textareas com dados atuais dos NestableList
            nestableQuestoes.updateTextarea();
            nestableVistoria.updateTextarea();

            // Pegar os JSONs atualizados
            dados.questoes = document.getElementById('jsonTextareaQuestoes').value || '[]';

            // Se tipo = Impresso, nao salvar vistoria
            if (dados.tipo === '1') {
                dados.vistoria = '[]';
            } else {
                dados.vistoria = document.getElementById('jsonTextareaVistoria').value || '[]';
            }

            try {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

                let url;
                if (registroId) {
                    url = `/checklist-modelos/${registroId}/atualizar`;
                } else {
                    url = '/checklist-modelos/salvar';
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    window.parent.postMessage({ action: 'showToast', message: result.message || i18n.saved }, '*');
                    navegarPara('/pages/checklist-modelos');
                } else {
                    mostrarAlerta(result.message || i18n.saveError);
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(error.message || i18n.saveError);
            } finally {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.btnSave}`;
            }
        }

        // ===== INICIALIZACAO =====
        async function init() {
            initNestableLists();

            // Verificar se estamos editando
            const urlParams = new URLSearchParams(window.location.search);
            registroId = urlParams.get('id');

            if (registroId) {
                await carregarDados(registroId);
            }

            // Atualizar visibilidade da secao Vistoria (para novo registro)
            toggleVistoriaSection();
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('formChecklistModelo').addEventListener('submit', salvar);

            document.getElementById('btnVoltar').addEventListener('click', function() {
                navegarPara('/pages/checklist-modelos');
            });

            document.getElementById('btnCancelar').addEventListener('click', function() {
                navegarPara('/pages/checklist-modelos');
            });

            // Toggle secao Vistoria quando mudar o tipo
            document.getElementById('tipo').addEventListener('change', toggleVistoriaSection);

            init();
        });
    })();
</script>
@endsection
