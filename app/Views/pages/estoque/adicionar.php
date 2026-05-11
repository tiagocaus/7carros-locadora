@extends('layouts.iframe')

@section('title', '<?= t("modules.estoque.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.estoque.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formEstoque" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Secao 1: Dados do Produto -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-box mr-2"></i><?= t('modules.estoque.sections.product_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Codigo -->
                <div class="md:col-span-2 form-input-group">
                    <label for="produto_codigo" class="form-label-group">
                        <?= t('modules.estoque.fields.code') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="produto_codigo" name="produto_codigo" class="form-input-group-field" required maxlength="30">
                </div>

                <!-- Nome -->
                <div class="md:col-span-4 form-input-group">
                    <label for="produto_nome" class="form-label-group">
                        <?= t('modules.estoque.fields.name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="produto_nome" name="produto_nome" class="form-input-group-field" required maxlength="255">
                </div>

                <!-- Marca -->
                <div class="md:col-span-3 form-input-group">
                    <label for="produto_marca" class="form-label-group">
                        <?= t('modules.estoque.fields.brand') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="produto_marca" name="produto_marca" class="form-input-group-field" required maxlength="255">
                </div>

                <!-- Modelo -->
                <div class="md:col-span-3 form-input-group">
                    <label for="produto_modelo" class="form-label-group">
                        <?= t('modules.estoque.fields.model') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="produto_modelo" name="produto_modelo" class="form-input-group-field" required maxlength="255">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <!-- Unidade -->
                <div class="md:col-span-2 form-input-group">
                    <label for="produto_unidade" class="form-label-group">
                        <?= t('modules.estoque.fields.unit') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="produto_unidade" name="produto_unidade" class="form-input-group-field" required>
                        <option value=""><?= t('modules.estoque.placeholders.select') ?></option>
                        <option value="UN"><?= t('modules.estoque.unit_options.UN') ?></option>
                        <option value="PC"><?= t('modules.estoque.unit_options.PC') ?></option>
                        <option value="CX"><?= t('modules.estoque.unit_options.CX') ?></option>
                        <option value="KG"><?= t('modules.estoque.unit_options.KG') ?></option>
                        <option value="L"><?= t('modules.estoque.unit_options.L') ?></option>
                        <option value="M"><?= t('modules.estoque.unit_options.M') ?></option>
                        <option value="M2"><?= t('modules.estoque.unit_options.M2') ?></option>
                        <option value="M3"><?= t('modules.estoque.unit_options.M3') ?></option>
                        <option value="JG"><?= t('modules.estoque.unit_options.JG') ?></option>
                        <option value="KIT"><?= t('modules.estoque.unit_options.KIT') ?></option>
                        <option value="PAR"><?= t('modules.estoque.unit_options.PAR') ?></option>
                    </select>
                </div>

                <!-- Local -->
                <div class="md:col-span-4 form-input-group">
                    <label for="produto_local" class="form-label-group"><?= t('modules.estoque.fields.storage_location') ?></label>
                    <input type="text" id="produto_local" name="produto_local" class="form-input-group-field" maxlength="100" placeholder="<?= t('modules.estoque.placeholders.storage_location') ?>">
                </div>

                <!-- Filial -->
                <div class="md:col-span-3 form-input-group">
                    <label for="id_matriz_filial" class="form-label-group">
                        <?= t('modules.estoque.fields.branch') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="id_matriz_filial" name="id_matriz_filial" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('modules.estoque.placeholders.search_branch') ?>" required>
                        <option value=""><?= t('modules.estoque.placeholders.select') ?></option>
                    </select>
                </div>

                <!-- Fornecedor -->
                <div class="md:col-span-2 form-input-group">
                    <label for="id_fornecedor" class="form-label-group"><?= t('modules.estoque.fields.supplier') ?></label>
                    <select id="id_fornecedor" name="id_fornecedor" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/fornecedores/select" data-chosen-placeholder="<?= t('modules.estoque.placeholders.search_supplier') ?>">
                        <option value=""><?= t('modules.estoque.placeholders.none') ?></option>
                    </select>
                </div>

                <!-- Status -->
                <div class="md:col-span-1 form-input-group" id="statusGroup" style="display: none;">
                    <label for="status" class="form-label-group"><?= t('modules.estoque.status.label') ?></label>
                    <select id="status" name="status" class="form-input-group-field">
                        <option value="A"><?= t('modules.estoque.status.active') ?></option>
                        <option value="I"><?= t('modules.estoque.status.inactive') ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Secao 2: Estoque -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-cubes mr-2"></i><?= t('modules.estoque.sections.stock') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Estoque Atual -->
                <div class="md:col-span-3 form-input-group">
                    <label for="produto_estoque_atual" class="form-label-group">
                        <?= t('modules.estoque.fields.current_stock') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="produto_estoque_atual" name="produto_estoque_atual" class="form-input-group-field" required value="0">
                </div>

                <!-- Estoque Minimo -->
                <div class="md:col-span-3 form-input-group">
                    <label for="produto_estoque_minimo" class="form-label-group"><?= t('modules.estoque.fields.minimum_stock') ?> {!! aviso(t('modules.estoque.tooltips.minimum_stock')) !!}</label>
                    <input type="number" id="produto_estoque_minimo" name="produto_estoque_minimo" class="form-input-group-field" min="0" value="0">
                </div>

                <!-- Baixa Automatica -->
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.estoque.fields.auto_deduct') ?> {!! aviso(t('modules.estoque.tooltips.auto_deduct')) !!}
                    </label>
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="baixa_automatica" class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-700"><?= t('modules.estoque.fields.auto_deduct_enable') ?></span>
                        </label>
                    </div>
                </div>

                <!-- Permitir Estoque Negativo -->
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.estoque.fields.allow_negative_stock') ?> {!! aviso(t('modules.estoque.tooltips.allow_negative_stock')) !!}
                    </label>
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="permitir_estoque_negativo" class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-700"><?= t('modules.estoque.fields.allow_negative_stock_enable') ?></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao 3: Valores -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-dollar-sign mr-2"></i><?= t('modules.estoque.sections.values') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Valor Compra -->
                <div class="md:col-span-3 form-input-group">
                    <label for="valor_compra" class="form-label-group">
                        <?= t('modules.estoque.fields.purchase_value') ?> <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_compra" name="valor_compra" class="form-input-group-field pl-10 input-moeda" required>
                    </div>
                </div>

                <!-- Valor Venda -->
                <div class="md:col-span-3 form-input-group">
                    <label for="valor_venda" class="form-label-group"><?= t('modules.estoque.fields.sale_value') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_venda" name="valor_venda" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>
            </div>
        </div>

        <!-- Botoes de Acao -->
        <div class="flex justify-end space-x-3 mt-6 mb-4">
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
        editTitle: '<?= addslashes(t('modules.estoque.edit_title')) ?>',
        loadDataError: '<?= addslashes(t('modules.estoque.messages.load_data_error')) ?>',
        loadProductError: '<?= addslashes(t('modules.estoque.messages.load_product_error')) ?>',
        saving: '<?= addslashes(t('modules.estoque.messages.saving')) ?>',
        saveError: '<?= addslashes(t('modules.estoque.messages.save_error')) ?>',
        saveProductError: '<?= addslashes(t('modules.estoque.messages.save_product_error')) ?>',
        created: '<?= addslashes(t('modules.estoque.messages.created')) ?>',
        updated: '<?= addslashes(t('modules.estoque.messages.updated')) ?>',
        btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
    };

    // Estado
    let editando = false;
    let registroId = null;

    // Elementos do formulario
    const form = document.getElementById('formEstoque');
    const pageTitle = document.getElementById('pageTitle');
    const inputId = document.getElementById('registroId');

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

    function voltar() {
        navegarPara('/pages/estoque');
    }

    // ===== INICIALIZACAO =====

    async function init() {
        // Selects de Filial e Fornecedor agora sao chosen-select server-side

        // Verificar se estamos editando
        const urlParams = new URLSearchParams(window.location.search);
        registroId = urlParams.get('id');

        if (registroId) {
            editando = true;
            pageTitle.textContent = i18n.editTitle;
            document.getElementById('statusGroup').style.display = '';
            await carregarDados(registroId);
        }

        configurarEventos();
    }

    // ===== UTILIDADES =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== CARREGAR DADOS =====

    async function carregarDados(id) {
        try {
            const result = await API.get(`/api/estoque/${id}`);

            if (!result.success) {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.loadDataError }, '*');
                voltar();
                return;
            }

            preencherFormulario(result.data);
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadProductError }, '*');
            voltar();
        }
    }

    function preencherFormulario(data) {
        inputId.value = data.id || '';

        // Dados do produto
        document.getElementById('produto_codigo').value = data.produto_codigo || '';
        document.getElementById('produto_nome').value = data.produto_nome || '';
        document.getElementById('produto_marca').value = data.produto_marca || '';
        document.getElementById('produto_modelo').value = data.produto_modelo || '';
        document.getElementById('produto_unidade').value = data.produto_unidade || '';
        document.getElementById('produto_local').value = data.produto_local || '';

        // Selects - chosen-select server-side
        if (data.id_matriz_filial && data.filial_nome) {
            const selectFilial = document.getElementById('id_matriz_filial');
            selectFilial.innerHTML = `<option value="">Selecione...</option><option value="${data.id_matriz_filial}" selected>${escapeHtml(data.filial_nome)}</option>`;
            selectFilial.dispatchEvent(new Event('change'));
        }

        if (data.id_fornecedor && data.fornecedor_nome) {
            const selectFornecedor = document.getElementById('id_fornecedor');
            selectFornecedor.innerHTML = `<option value="">Nenhum</option><option value="${data.id_fornecedor}" selected>${escapeHtml(data.fornecedor_nome)}</option>`;
            selectFornecedor.dispatchEvent(new Event('change'));
        }

        // Estoque
        document.getElementById('produto_estoque_atual').value = data.produto_estoque_atual || 0;
        document.getElementById('produto_estoque_minimo').value = data.produto_estoque_minimo || 0;

        // Status
        document.getElementById('status').value = data.status || 'A';

        // Baixa automatica
        document.getElementById('baixa_automatica').checked = data.baixa_automatica === 'S';

        // Permitir estoque negativo
        document.getElementById('permitir_estoque_negativo').checked = data.permitir_estoque_negativo === 'S';

        // Valores (usando Currency helper)
        Currency.setValue('#valor_compra', data.valor_compra || 0);
        Currency.setValue('#valor_venda', data.valor_venda || 0);
    }

    // ===== CONFIGURAR EVENTOS =====

    function configurarEventos() {
        // Botao voltar
        document.getElementById('btnVoltar')?.addEventListener('click', voltar);
        document.getElementById('btnCancelar')?.addEventListener('click', voltar);

        // Mascara moeda aplicada automaticamente pela classe input-moeda

        // Submissao do formulario
        form.addEventListener('submit', salvar);
    }

    // ===== SALVAR =====

    async function salvar(e) {
        e.preventDefault();

        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

        try {
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());

            // Converter inteiros
            dados.produto_estoque_atual = parseInt(dados.produto_estoque_atual) || 0;
            dados.produto_estoque_minimo = parseInt(dados.produto_estoque_minimo) || 0;

            // Baixa automatica (checkbox nao vai no FormData se desmarcado)
            dados.baixa_automatica = document.getElementById('baixa_automatica').checked ? 'S' : 'N';

            // Permitir estoque negativo
            dados.permitir_estoque_negativo = document.getElementById('permitir_estoque_negativo').checked ? 'S' : 'N';

            let url;
            if (editando && registroId) {
                url = `/estoque/${registroId}/atualizar`;
            } else {
                url = '/estoque/salvar';
            }

            const result = await API.post(url, dados);

            if (result.success) {
                // Notificar parent e voltar
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        type: 'success',
                        message: editando ? i18n.updated : i18n.created
                    }, '*');
                }
                voltar();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.saveProductError }, '*');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.btnSave;
        }
    }

    // Inicializar
    init();
})();
</script>
@endsection
