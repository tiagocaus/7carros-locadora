@extends('layouts.iframe')

@section('title', t('modules.promissorias.new_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.promissorias.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formPromissoria" method="POST">
        @csrf

        <!-- Secao 1: Dados Basicos -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-file-signature mr-2"></i><?= t('modules.promissorias.sections.basic_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Matriz/Filial -->
                <div class="form-input-group">
                    <label for="idMatrizFilial" class="form-label-group"><?= t('modules.promissorias.fields.branch') ?> <span class="text-red-500">*</span></label>
                    <select id="idMatrizFilial" name="id_matriz_filial" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>..." required>
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Cliente -->
                <div class="form-input-group">
                    <label for="idCliente" class="form-label-group"><?= t('modules.promissorias.fields.client') ?> <span class="text-red-500">*</span></label>
                    <select id="idCliente" name="id_cliente" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>..." required>
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <!-- Codigo Contrato -->
                <div class="form-input-group">
                    <label for="codigoContratoLocacao" class="form-label-group"><?= t('modules.promissorias.fields.contract_optional') ?></label>
                    <select id="codigoContratoLocacao" name="codigo_contrato_locacao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contratos/buscar-select" data-chosen-placeholder="<?= t('modules.promissorias.fields.no_link') ?>">
                        <option value=""><?= t('modules.promissorias.fields.no_link') ?></option>
                    </select>
                </div>

                <!-- Valor Total -->
                <div class="form-input-group">
                    <label for="valor" class="form-label-group"><?= t('modules.promissorias.fields.total_value') ?> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor" name="valor" class="form-input-group-field pl-10 input-moeda" value="0,00" required>
                    </div>
                </div>

                <!-- Primeiro Vencimento -->
                <div class="form-input-group">
                    <label for="dataPrimeiroVencimento" class="form-label-group"><?= t('modules.promissorias.fields.first_due_date') ?> <span class="text-red-500">*</span></label>
                    <input type="date" id="dataPrimeiroVencimento" name="primeiro_vencimento" class="form-input-group-field" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <!-- Numero de Parcelas -->
                <div class="form-input-group">
                    <label for="numParcelas" class="form-label-group"><?= t('modules.promissorias.fields.installments') ?> <span class="text-red-500">*</span></label>
                    <input type="number" id="numParcelas" name="parcelas" class="form-input-group-field" min="1" max="120" value="1" required>
                </div>

                <!-- Intervalo em Dias -->
                <div class="form-input-group">
                    <label for="intervaloDias" class="form-label-group"><?= t('modules.promissorias.fields.interval_days') ?></label>
                    <input type="number" id="intervaloDias" name="intervalo" class="form-input-group-field" min="1" max="365" value="30">
                </div>
            </div>

            <!-- Observacoes -->
            <div class="grid grid-cols-1 gap-4 mt-4">
                <div class="form-input-group">
                    <label for="obs" class="form-label-group"><?= t('modules.promissorias.fields.observations') ?></label>
                    <textarea id="obs" name="obs" class="form-input-group-field" rows="2" placeholder="<?= t('modules.promissorias.fields.observations_placeholder') ?>"></textarea>
                </div>
            </div>
        </div>

        <!-- Secao 2: Previa das Parcelas -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-list-ol mr-2"></i><?= t('modules.promissorias.sections.installments_preview') ?></h3>

            <div class="overflow-x-auto">
                <table class="w-full min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.installment') ?></th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.value') ?></th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.due_date') ?></th>
                        </tr>
                    </thead>
                    <tbody id="previaTableBody" class="bg-white divide-y divide-slate-200">
                        <tr>
                            <td colspan="3" class="px-3 py-4 text-center text-slate-400">
                                <?= t('modules.promissorias.messages.fill_fields_preview') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 p-3 bg-slate-50 rounded-lg flex justify-between items-center">
                <span class="text-sm text-slate-600"><?= t('modules.promissorias.fields.installment_value') ?>:</span>
                <span id="valorParcelaDisplay" class="font-medium text-slate-800">R$ 0,00</span>
            </div>
        </div>

        <!-- Botoes -->
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
        saving: '<?= t("common.labels.saving") ?>',
        save: '<?= t("common.buttons.save") ?>',
        createdSuccess: '<?= t("modules.promissorias.messages.created_success") ?>',
        saveError: '<?= t("modules.promissorias.messages.save_error") ?>',
        fillFieldsPreview: '<?= t("modules.promissorias.messages.fill_fields_preview") ?>',
        installmentOf: '<?= t("modules.promissorias.fields.installment_of") ?>',
    };

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
        navegarPara('/pages/promissorias');
    }

    // ===== INICIALIZACAO =====

    function init() {
        // Configurar data padrao (hoje)
        const hoje = DateHelper.todayInput();
        document.getElementById('dataPrimeiroVencimento').value = hoje;

        // Calcular previa inicial
        atualizarPrevia();
    }

    // ===== PREVIA DE PARCELAS =====

    function atualizarPrevia() {
        const valorInput = document.getElementById('valor');
        const numParcelas = parseInt(document.getElementById('numParcelas').value) || 1;
        const intervaloDias = parseInt(document.getElementById('intervaloDias').value) || 30;
        const dataVencimento = document.getElementById('dataPrimeiroVencimento').value;

        // Converter valor (formato PT-BR: "2.806,05" -> 2806.05)
        const valorTotal = Currency.parse(valorInput.value || '0');

        const valorParcela = valorTotal / numParcelas;
        document.getElementById('valorParcelaDisplay').textContent = formatarMoeda(valorParcela);

        const tbody = document.getElementById('previaTableBody');

        if (valorTotal <= 0 || !dataVencimento) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="px-3 py-4 text-center text-slate-400">
                        ${i18n.fillFieldsPreview}
                    </td>
                </tr>
            `;
            return;
        }

        let rows = '';
        let dataAtual = dataVencimento;

        for (let i = 1; i <= numParcelas; i++) {
            const dataFormatada = DateHelper.format(dataAtual);

            rows += `
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 text-sm text-slate-700">${i18n.installmentOf.replace(':current', i).replace(':total', numParcelas)}</td>
                    <td class="px-3 py-2 text-sm text-right font-medium">${formatarMoeda(valorParcela)}</td>
                    <td class="px-3 py-2 text-sm text-center">${dataFormatada}</td>
                </tr>
            `;

            // Adicionar intervalo para proxima parcela
            dataAtual = DateHelper.addDays(dataAtual, intervaloDias);
        }

        tbody.innerHTML = rows;
    }

    // ===== SALVAR =====

    async function salvarPromissoria(event) {
        event.preventDefault();

        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const formData = new FormData(document.getElementById('formPromissoria'));
            const dados = Object.fromEntries(formData.entries());

            // Valor enviado em formato BR ("100,00") — Promissoria::toDecimal()
            // no backend converte. NAO normalizar aqui ou o controller interpreta
            // o ponto decimal como ponto de milhar e multiplica por 100.

            const result = await API.post('/promissorias/salvar', dados);

            if (result.success) {
                toast.success(result.message || i18n.createdSuccess);
                // Redirecionar para edicao ou listagem
                if (result.codigo) {
                    navegarPara(`/pages/promissorias/editar/${result.codigo}`);
                } else {
                    navegarPara('/pages/promissorias');
                }
            } else {
                toast.error(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            toast.error(error.message || i18n.saveError);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.save}`;
        }
    }

    // ===== EVENT LISTENERS =====

    document.addEventListener('DOMContentLoaded', function() {
        init();

        // Botoes de navegacao
        document.getElementById('btnVoltar')?.addEventListener('click', voltar);
        document.getElementById('btnCancelar')?.addEventListener('click', voltar);

        // Formulario
        document.getElementById('formPromissoria')?.addEventListener('submit', salvarPromissoria);

        // Calculo de previa
        document.getElementById('valor')?.addEventListener('input', atualizarPrevia);
        document.getElementById('numParcelas')?.addEventListener('input', atualizarPrevia);
        document.getElementById('intervaloDias')?.addEventListener('input', atualizarPrevia);
        document.getElementById('dataPrimeiroVencimento')?.addEventListener('change', atualizarPrevia);
    });

    // ===== HELPERS =====

    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    }
})();
</script>
@endsection
