@extends('layouts.iframe')

@section('title', '<?= t("modules.oficinas.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.oficinas.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formOficina" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Secao: Dados da Oficina -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-wrench mr-2"></i><?= t('modules.oficinas.sections.workshop_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Empresa -->
                <div class="md:col-span-6 form-input-group">
                    <label for="empresa" class="form-label-group">
                        <?= t('modules.oficinas.fields.company_name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="empresa" name="empresa" class="form-input-group-field" required maxlength="255">
                </div>

                <!-- Email -->
                <div class="md:col-span-3 form-input-group">
                    <label for="email" class="form-label-group"><?= t('modules.oficinas.fields.email') ?></label>
                    <input type="email" id="email" name="email" class="form-input-group-field" maxlength="255">
                </div>

                <!-- Telefone -->
                <div class="md:col-span-3 form-input-group">
                    <label for="telefone" class="form-label-group"><?= t('modules.oficinas.fields.phone') ?></label>
                    <input type="text" id="telefone" name="telefone" class="form-input-group-field" maxlength="100">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <!-- Contrato -->
                <div class="md:col-span-3 form-input-group">
                    <label for="contrato" class="form-label-group"><?= t('modules.oficinas.fields.contract_number') ?></label>
                    <input type="text" id="contrato" name="contrato" class="form-input-group-field" maxlength="45">
                </div>

                <!-- Observacoes -->
                <div class="md:col-span-9 form-input-group">
                    <label for="obs" class="form-label-group"><?= t('modules.oficinas.fields.observations') ?></label>
                    <input type="text" id="obs" name="obs" class="form-input-group-field" maxlength="255">
                </div>
            </div>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end gap-3 mt-6">
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
<script>
(function () {
    const i18n = {
        editTitle: '<?= addslashes(t('modules.oficinas.edit_title')) ?>',
        notFound: '<?= addslashes(t('modules.oficinas.messages.not_found')) ?>',
        loadDataError: '<?= addslashes(t('modules.oficinas.messages.load_data_error')) ?>',
        companyRequired: '<?= addslashes(t('modules.oficinas.messages.company_required')) ?>',
        saving: '<?= addslashes(t('modules.oficinas.messages.saving')) ?>',
        saveError: '<?= addslashes(t('modules.oficinas.messages.save_error')) ?>',
        created: '<?= addslashes(t('modules.oficinas.messages.created')) ?>',
        updated: '<?= addslashes(t('modules.oficinas.messages.updated')) ?>',
    };

    // Estado
    let registroId = null;
    let isSubmitting = false;

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

    function voltarParaLista() {
        navegarPara('/pages/oficinas');
    }

    // ===== INICIALIZACAO =====

    async function init() {
        // Verificar se e edicao
        const params = new URLSearchParams(window.location.search);
        registroId = params.get('id');

        if (registroId) {
            document.getElementById('pageTitle').textContent = i18n.editTitle;
            document.getElementById('registroId').value = registroId;
            await carregarDados(registroId);
        }

        // Event listeners
        document.getElementById('btnVoltar')?.addEventListener('click', voltarParaLista);
        document.getElementById('btnCancelar')?.addEventListener('click', voltarParaLista);
        document.getElementById('formOficina')?.addEventListener('submit', salvarRegistro);
    }

    // ===== CARREGAR DADOS =====

    async function carregarDados(id) {
        try {
            const result = await API.get(`/api/oficinas/${id}`);

            if (result.success && result.data) {
                preencherFormulario(result.data);
            } else {
                window.parent.postMessage({ action: 'openAlert', message: i18n.notFound }, '*');
                voltarParaLista();
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadDataError }, '*');
            voltarParaLista();
        }
    }

    function preencherFormulario(dados) {
        document.getElementById('empresa').value = dados.empresa || '';
        document.getElementById('email').value = dados.email || '';
        document.getElementById('telefone').value = dados.telefone || '';
        document.getElementById('contrato').value = dados.contrato || '';
        document.getElementById('obs').value = dados.obs || '';
    }

    // ===== SALVAR =====

    async function salvarRegistro(event) {
        event.preventDefault();

        if (isSubmitting) return;

        // Validacao
        const empresa = document.getElementById('empresa').value.trim();
        if (!empresa) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.companyRequired }, '*');
            document.getElementById('empresa').focus();
            return;
        }

        isSubmitting = true;
        const btnSalvar = document.getElementById('btnSalvar');
        const textoOriginal = btnSalvar.innerHTML;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;
        btnSalvar.disabled = true;

        try {
            const formData = {
                empresa: empresa,
                email: document.getElementById('email').value.trim(),
                telefone: document.getElementById('telefone').value.trim(),
                contrato: document.getElementById('contrato').value.trim(),
                obs: document.getElementById('obs').value.trim()
            };

            let result;
            if (registroId) {
                result = await API.post(`/oficinas/${registroId}/atualizar`, formData);
            } else {
                result = await API.post('/oficinas/salvar', formData);
            }

            if (result.success) {
                // Mostrar mensagem de sucesso
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        message: registroId ? i18n.updated : i18n.created,
                        type: 'success'
                    }, '*');
                }
                voltarParaLista();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.saveError }, '*');
        } finally {
            isSubmitting = false;
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    }

    // Inicializar
    init();
})();
</script>
@endsection
