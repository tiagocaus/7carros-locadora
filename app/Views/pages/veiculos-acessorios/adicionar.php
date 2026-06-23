@extends('layouts.iframe')

@section('title', t('modules.veiculos_acessorios.new_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.veiculos_acessorios.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formAcessorio" method="POST">
        @csrf
        <input type="hidden" id="acessorioId" name="id">

        <!-- Secao: Dados do Acessorio -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-car-side mr-2"></i><?= t('modules.veiculos_acessorios.sections.accessory_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-input-group">
                    <label for="acessorioNome" class="form-label-group">
                        <?= t('modules.veiculos_acessorios.fields.name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="acessorioNome" name="nome" class="form-input-group-field" required maxlength="100" placeholder="<?= t('modules.veiculos_acessorios.placeholders.name') ?>">
                </div>
            </div>
        </div>

        <!-- Botoes de Acao -->
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center">
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
        newTitle: '<?= addslashes(t("modules.veiculos_acessorios.new_title")) ?>',
        editTitle: '<?= addslashes(t("modules.veiculos_acessorios.edit_title")) ?>',
        saving: '<?= addslashes(t("common.labels.saving")) ?>',
        save: '<?= addslashes(t("common.buttons.save")) ?>',
        notFound: '<?= addslashes(t("modules.veiculos_acessorios.messages.not_found")) ?>',
        loadDataError: <?= js_t("modules.veiculos_acessorios.messages.load_data_error") ?>,
        saveError: <?= js_t("modules.veiculos_acessorios.messages.save_error") ?>,
        nameRequired: <?= js_t("modules.veiculos_acessorios.messages.name_required") ?>,
    };

    // Elementos
    const form = document.getElementById('formAcessorio');
    const acessorioIdInput = document.getElementById('acessorioId');
    const acessorioNomeInput = document.getElementById('acessorioNome');
    const pageTitle = document.getElementById('pageTitle');
    const btnSalvar = document.getElementById('btnSalvar');

    // Estado
    let isEditMode = false;
    let acessorioId = null;

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
        navegarPara('/pages/veiculos-acessorios');
    }

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarAcessorio(id) {
        try {
            const result = await API.get(`/api/veiculos-acessorios/${id}`);

            if (result.success && result.data) {
                preencherFormulario(result.data);
            } else {
                toast.error(i18n.notFound);
                voltarParaLista();
            }
        } catch (error) {
            console.error('Erro ao carregar acessorio:', error);
            toast.error(i18n.loadDataError);
            voltarParaLista();
        }
    }

    function preencherFormulario(acessorio) {
        acessorioIdInput.value = acessorio.id;
        acessorioNomeInput.value = acessorio.nome || '';

        pageTitle.textContent = i18n.editTitle;
        isEditMode = true;

        // Recapturar estado inicial para auditoria (após preencher dados)
        if (window.FormAudit) {
            FormAudit.recapture(form);
        }
    }

    // ===== SALVAMENTO =====

    async function salvarAcessorio(dados) {
        try {
            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

            const url = isEditMode
                ? `/veiculos-acessorios/${acessorioId}/atualizar`
                : '/veiculos-acessorios/salvar';

            const result = await API.post(url, dados);

            if (result.success) {
                voltarParaLista();
            } else {
                toast.error(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            toast.error(i18n.saveError);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.save;
        }
    }

    // ===== EVENT LISTENERS =====

    // Botao voltar
    document.getElementById('btnVoltar')?.addEventListener('click', voltarParaLista);
    document.getElementById('btnCancelar')?.addEventListener('click', voltarParaLista);

    // Submit do formulario
    form?.addEventListener('submit', function (e) {
        e.preventDefault();

        const nome = acessorioNomeInput.value.trim();

        if (!nome) {
            toast.error(i18n.nameRequired);
            acessorioNomeInput.focus();
            return;
        }

        const dados = {
            nome: nome,
            ...FormAudit.getAuditData(form)  // Adiciona _audit_data ou _audit_changes
        };

        salvarAcessorio(dados);
    });

    // ===== INICIALIZACAO =====

    // Verificar se eh edicao (parametro id na URL)
    const urlParams = new URLSearchParams(window.location.search);
    const idParam = urlParams.get('id');

    if (idParam) {
        acessorioId = parseInt(idParam);
        isEditMode = true;
        carregarAcessorio(acessorioId);
    }
})();
</script>
@endsection
