@extends('layouts.iframe')

@section('title', t('modules.promissorias.edit_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page">
            <?= t('modules.promissorias.edit_title') ?>
            <span id="codigoDisplay" class="font-mono"><?= htmlspecialchars($codigo ?? '') ?></span>
        </h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Secao 1: Dados Gerais -->
    <div class="form-section mb-6">
        <h3 class="form-section-title"><i class="fas fa-file-signature mr-2"></i><?= t('modules.promissorias.sections.general_data') ?></h3>

        <form id="formDadosGerais">
            @csrf
            <input type="hidden" id="codigoBase" value="<?= htmlspecialchars($codigo ?? '') ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Matriz/Filial -->
                <div class="form-input-group">
                    <label for="idMatrizFilial" class="form-label-group"><?= t('modules.promissorias.fields.branch') ?></label>
                    <select id="idMatrizFilial" name="id_matriz_filial" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Cliente -->
                <div class="form-input-group">
                    <label for="idCliente" class="form-label-group"><?= t('modules.promissorias.fields.client') ?></label>
                    <select id="idCliente" name="id_cliente" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Contrato -->
                <div class="form-input-group">
                    <label for="codigoContratoLocacao" class="form-label-group"><?= t('modules.promissorias.fields.contract') ?></label>
                    <select id="codigoContratoLocacao" name="codigo_contrato_locacao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contratos/buscar-select" data-chosen-placeholder="<?= t('modules.promissorias.fields.no_link') ?>">
                        <option value=""><?= t('modules.promissorias.fields.no_link') ?></option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <!-- Observacao -->
                <div class="form-input-group">
                    <label for="obs" class="form-label-group"><?= t('modules.promissorias.fields.observations') ?></label>
                    <textarea id="obs" name="obs" class="form-input-group-field" rows="2" placeholder="<?= t('modules.promissorias.fields.observations_placeholder') ?>"></textarea>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" id="btnSalvarDadosGerais" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-save mr-2"></i><?= t('modules.promissorias.buttons.save_general_data') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Secao 2: Parcelas -->
    <div class="form-section mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="form-section-title mb-0"><i class="fas fa-list-ol mr-2"></i><?= t('modules.promissorias.sections.installments') ?></h3>
            <button type="button" id="btnAddParcela" class="btn-green py-1.5 px-3 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i><?= t('modules.promissorias.buttons.add_installment') ?>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase w-16">#</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.value') ?></th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.due_date') ?></th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.status') ?></th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase"><?= t('modules.promissorias.table.payment_date') ?></th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase w-32"><?= t('common.labels.actions') ?></th>
                    </tr>
                </thead>
                <tbody id="parcelasTableBody" class="bg-white divide-y divide-slate-200">
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i><?= t('modules.promissorias.messages.loading_installments') ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Resumo -->
        <div class="mt-4 p-3 bg-slate-50 rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm text-slate-600"><?= t('modules.promissorias.messages.total_paid') ?>:</span>
                    <span id="totalPago" class="ml-2 font-medium text-green-600">R$ 0,00</span>
                </div>
                <div>
                    <span class="text-sm text-slate-600"><?= t('modules.promissorias.messages.total_pending') ?>:</span>
                    <span id="totalPendente" class="ml-2 font-medium text-red-600">R$ 0,00</span>
                </div>
                <div>
                    <span class="text-sm text-slate-600"><?= t('modules.promissorias.messages.total') ?>:</span>
                    <span id="totalGeral" class="ml-2 font-bold text-slate-800">R$ 0,00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Acoes Gerais -->
    <div class="flex justify-between items-center mt-6">
        <button type="button" id="btnExcluir" class="btn-red py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-trash mr-2"></i><?= t('modules.promissorias.buttons.delete_promissory') ?>
        </button>
        <div class="flex space-x-3">
            <button type="button" id="btnImprimir" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-print mr-2"></i><?= t('common.buttons.print') ?>
            </button>
            <button type="button" id="btnMarcarTodasPagas" class="btn-green py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-check-double mr-2"></i><?= t('modules.promissorias.buttons.mark_all_paid') ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Parcela -->
<div id="modalParcela" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <h4 id="modalParcelaTitulo" class="text-lg font-medium"><?= t('modules.promissorias.messages.add_installment_modal') ?></h4>
            <button type="button" id="btnFecharModal" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="formParcela" class="p-4">
            <input type="hidden" id="parcelaId" value="">

            <div class="form-input-group mb-4">
                <label for="parcelaValor" class="form-label-group"><?= t('modules.promissorias.fields.value') ?> <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                    <input type="text" id="parcelaValor" name="valor" class="form-input-group-field pl-10 input-moeda" required>
                </div>
            </div>

            <div class="form-input-group mb-4">
                <label for="parcelaVencimento" class="form-label-group"><?= t('modules.promissorias.fields.due_date') ?> <span class="text-red-500">*</span></label>
                <input type="date" id="parcelaVencimento" name="data_vencimento" class="form-input-group-field" required>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" id="btnCancelarModal" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.cancel') ?>
                </button>
                <button type="submit" id="btnSalvarParcela" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        saving: '<?= t("common.labels.saving") ?>',
        save: '<?= t("common.buttons.save") ?>',
        codeNotFound: '<?= t("modules.promissorias.messages.code_not_found") ?>',
        loadError: '<?= t("modules.promissorias.messages.load_promissory_error") ?>',
        noInstallments: '<?= t("modules.promissorias.messages.no_installments") ?>',
        statusPaid: '<?= t("modules.promissorias.status.paid") ?>',
        statusPending: '<?= t("modules.promissorias.status.pending") ?>',
        tooltipPrintInstallment: '<?= t("modules.promissorias.tooltips.print_installment") ?>',
        tooltipMarkPaid: '<?= t("modules.promissorias.tooltips.mark_paid") ?>',
        tooltipEdit: '<?= t("modules.promissorias.tooltips.edit") ?>',
        tooltipDelete: '<?= t("modules.promissorias.tooltips.delete") ?>',
        addInstallmentModal: '<?= t("modules.promissorias.messages.add_installment_modal") ?>',
        editInstallmentModal: '<?= t("modules.promissorias.messages.edit_installment_modal") ?>',
        installmentSaved: '<?= t("modules.promissorias.messages.installment_saved") ?>',
        installmentSaveError: '<?= t("modules.promissorias.messages.installment_save_error") ?>',
        markInstallmentPaidTitle: '<?= t("modules.promissorias.messages.mark_installment_paid_title") ?>',
        markInstallmentPaidConfirm: '<?= t("modules.promissorias.messages.mark_installment_paid_confirm") ?>',
        markInstallmentPaidBtn: '<?= t("modules.promissorias.messages.mark_installment_paid_btn") ?>',
        installmentMarkedPaid: '<?= t("modules.promissorias.messages.installment_marked_paid") ?>',
        installmentMarkError: '<?= t("modules.promissorias.messages.installment_mark_error") ?>',
        thisInstallment: '<?= t("modules.promissorias.messages.this_installment") ?>',
        confirmDeleteInstallment: '<?= t("modules.promissorias.messages.confirm_delete_installment") ?>',
        installmentDeleted: '<?= t("modules.promissorias.messages.installment_deleted") ?>',
        installmentDeleteError: '<?= t("modules.promissorias.messages.installment_delete_error") ?>',
        allAlreadyPaid: '<?= t("modules.promissorias.messages.all_already_paid") ?>',
        markAllTitle: '<?= t("modules.promissorias.messages.mark_all_title") ?>',
        markAllConfirm: '<?= t("modules.promissorias.messages.mark_all_confirm") ?>',
        markAllBtn: '<?= t("modules.promissorias.messages.mark_all_btn") ?>',
        allMarkedPaid: '<?= t("modules.promissorias.messages.all_marked_paid") ?>',
        markAllError: '<?= t("modules.promissorias.messages.mark_all_error") ?>',
        generalDataSaved: '<?= t("modules.promissorias.messages.general_data_saved") ?>',
        generalDataError: '<?= t("modules.promissorias.messages.general_data_error") ?>',
        saveGeneralData: '<?= t("modules.promissorias.buttons.save_general_data") ?>',
        confirmDeletePromissory: '<?= t("modules.promissorias.messages.confirm_delete_promissory") ?>',
        deletedSuccess: '<?= t("modules.promissorias.messages.deleted_success") ?>',
        deleteError: '<?= t("modules.promissorias.messages.delete_error") ?>',
        printInstallmentTitle: '<?= t("modules.promissorias.messages.print_installment_title") ?>',
        titleSingular: '<?= t("modules.promissorias.title_singular") ?>',
    };

    const codigoBase = document.getElementById('codigoBase')?.value || '';
    let parcelasData = [];

    // Estado para acao pendente (confirmacao via modal)
    let pendingAction = null;
    let pendingData = null;

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

    async function init() {
        if (!codigoBase) {
            toast.error(i18n.codeNotFound);
            voltar();
            return;
        }

        await carregarPromissoria();
    }

    async function carregarPromissoria() {
        try {
            const result = await API.get(`/api/promissorias/codigo/${codigoBase}`);

            if (!result.success) {
                toast.error(result.message || i18n.loadError);
                return;
            }

            const p = result.data;

            // Preencher dados gerais
            if (p.id_matriz_filial) {
                await setChosenValue('idMatrizFilial', p.id_matriz_filial, p.filial_nome || 'Filial');
            }
            if (p.id_cliente) {
                await setChosenValue('idCliente', p.id_cliente, p.cliente_nome || 'Cliente');
            }
            if (p.codigo_contrato_locacao) {
                await setChosenValue('codigoContratoLocacao', p.codigo_contrato_locacao, p.codigo_contrato_locacao);
            }

            document.getElementById('obs').value = p.obs || '';

            // Renderizar parcelas
            parcelasData = p.parcelas || [];
            renderParcelas();

        } catch (error) {
            console.error('Erro ao carregar promissoria:', error);
            toast.error(error.message || i18n.loadError);
        }
    }

    async function setChosenValue(selectId, value, text) {
        const select = document.getElementById(selectId);
        if (!select) return;

        let option = select.querySelector(`option[value="${value}"]`);
        if (!option) {
            option = document.createElement('option');
            option.value = value;
            option.textContent = text;
            select.appendChild(option);
        }
        select.value = value;

        if (typeof updateChosen === 'function') {
            updateChosen(select);
        }
    }

    // ===== PARCELAS =====

    function renderParcelas() {
        const tbody = document.getElementById('parcelasTableBody');

        if (!parcelasData || parcelasData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-slate-400">
                        ${i18n.noInstallments}
                    </td>
                </tr>
            `;
            atualizarTotais(0, 0);
            return;
        }

        let totalPago = 0;
        let totalPendente = 0;

        let rows = '';
        parcelasData.forEach((parcela, index) => {
            const pago = parcela.pago === 'S';
            const valor = parseFloat(parcela.valor_parcela) || 0;

            if (pago) {
                totalPago += valor;
            } else {
                totalPendente += valor;
            }

            const statusBadge = pago
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.statusPaid}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700"><i class="fas fa-clock mr-1"></i>${i18n.statusPending}</span>`;

            const dataPagamento = parcela.data_pagamento
                ? formatarData(parcela.data_pagamento)
                : '-';

            // Acoes: icone de impressora sempre visivel, demais acoes dependem do status
            let acoes = '';

            // Icone de impressao (sempre visivel)
            const btnImprimir = `
                <button type="button" onclick="imprimirParcela(${parcela.id})" class="text-slate-500 hover:text-slate-700 text-sm mr-2" title="${i18n.tooltipPrintInstallment}">
                    <i class="fas fa-print"></i>
                </button>
            `;

            if (pago) {
                // Parcela paga: impressora + icone de check verde
                acoes = `
                    ${btnImprimir}
                    <span class="text-green-600 text-sm"><i class="fas fa-check-circle"></i></span>
                `;
            } else {
                // Parcela pendente: impressora + acoes de edicao
                acoes = `
                    ${btnImprimir}
                    <button type="button" onclick="marcarParcelaPaga(${parcela.id})" class="text-slate-400 hover:text-slate-600 text-sm mr-2" title="${i18n.tooltipMarkPaid}">
                        <i class="fas fa-check-circle"></i>
                    </button>
                    <button type="button" onclick="editarParcela(${parcela.id})" class="text-blue-600 hover:text-blue-800 text-sm mr-2" title="${i18n.tooltipEdit}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" onclick="excluirParcela(${parcela.id})" class="text-red-600 hover:text-red-800 text-sm" title="${i18n.tooltipDelete}">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            }

            rows += `
                <tr class="hover:bg-slate-50" data-id="${parcela.id}">
                    <td class="px-3 py-2 text-sm text-slate-500">${parcela.numero_parcela || index + 1}</td>
                    <td class="px-3 py-2 text-sm text-right font-medium">${formatarMoeda(valor)}</td>
                    <td class="px-3 py-2 text-sm text-center">${formatarData(parcela.data_vencimento)}</td>
                    <td class="px-3 py-2 text-center">${statusBadge}</td>
                    <td class="px-3 py-2 text-sm text-center">${dataPagamento}</td>
                    <td class="px-3 py-2 text-center">${acoes}</td>
                </tr>
            `;
        });

        tbody.innerHTML = rows;
        atualizarTotais(totalPago, totalPendente);
    }

    function atualizarTotais(totalPago, totalPendente) {
        document.getElementById('totalPago').textContent = formatarMoeda(totalPago);
        document.getElementById('totalPendente').textContent = formatarMoeda(totalPendente);
        document.getElementById('totalGeral').textContent = formatarMoeda(totalPago + totalPendente);
    }

    // ===== MODAL =====

    function abrirModalAdicionar() {
        document.getElementById('modalParcelaTitulo').textContent = i18n.addInstallmentModal;
        document.getElementById('parcelaId').value = '';
        document.getElementById('parcelaValor').value = '';

        // Data padrao: ultimo vencimento + 30 dias ou hoje
        let dataVencimento = new Date();
        if (parcelasData.length > 0) {
            const ultimaParcela = parcelasData[parcelasData.length - 1];
            if (ultimaParcela.data_vencimento) {
                dataVencimento = new Date(ultimaParcela.data_vencimento + 'T12:00:00');
                dataVencimento.setDate(dataVencimento.getDate() + 30);
            }
        }
        document.getElementById('parcelaVencimento').value = dataVencimento.toISOString().split('T')[0];

        document.getElementById('modalParcela').classList.remove('hidden');
    }

    function fecharModal() {
        document.getElementById('modalParcela').classList.add('hidden');
    }

    window.editarParcela = function(id) {
        const parcela = parcelasData.find(p => p.id == id);
        if (!parcela) return;

        document.getElementById('modalParcelaTitulo').textContent = i18n.editInstallmentModal;
        document.getElementById('parcelaId').value = id;
        document.getElementById('parcelaValor').value = formatarMoedaInput(parcela.valor_parcela || 0);
        document.getElementById('parcelaVencimento').value = parcela.data_vencimento || '';

        document.getElementById('modalParcela').classList.remove('hidden');
    };

    async function salvarParcela(event) {
        event.preventDefault();

        const parcelaId = document.getElementById('parcelaId').value;
        const isEdicao = parcelaId && parcelaId !== '';

        const btnSalvar = document.getElementById('btnSalvarParcela');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            // Converter valor PT-BR ("2.806,05" -> 2806.05) usando Currency.parse
            const valorNum = Currency.parse(document.getElementById('parcelaValor').value || '0');

            const dados = {
                valor: valorNum,
                data_vencimento: document.getElementById('parcelaVencimento').value
            };

            let url, result;
            if (isEdicao) {
                url = `/promissorias/${codigoBase}/parcelas/${parcelaId}/atualizar`;
            } else {
                url = `/promissorias/${codigoBase}/parcelas/adicionar`;
            }

            result = await API.post(url, dados);

            if (result.success) {
                toast.success(result.message || i18n.installmentSaved);
                fecharModal();
                await carregarPromissoria();
            } else {
                toast.error(result.message || i18n.installmentSaveError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(error.message || i18n.installmentSaveError);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.save}`;
        }
    }

    // ===== ACOES PARCELAS =====

    window.marcarParcelaPaga = function(id) {
        if (window.parent !== window) {
            pendingAction = 'marcarParcelaPaga';
            pendingData = id;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.markInstallmentPaidTitle,
                message: i18n.markInstallmentPaidConfirm,
                confirmText: i18n.markInstallmentPaidBtn
            }, '*');
        } else {
            if (confirm(i18n.markInstallmentPaidConfirm)) {
                executarMarcarParcelaPaga(id);
            }
        }
    };

    async function executarMarcarParcelaPaga(id) {
        try {
            const result = await API.post(`/promissorias/${codigoBase}/parcelas/${id}/pagar`, {});

            if (result.success) {
                toast.success(result.message || i18n.installmentMarkedPaid);
                await carregarPromissoria();
            } else {
                toast.error(result.message || i18n.installmentMarkError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(error.message || i18n.installmentMarkError);
        }
    }

    window.excluirParcela = function(id) {
        if (window.parent !== window) {
            pendingAction = 'excluirParcela';
            pendingData = id;
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: id,
                recordName: i18n.thisInstallment,
                recordType: i18n.titleSingular,
                confirmType: 'text'
            }, '*');
        } else {
            if (confirm(i18n.confirmDeleteInstallment)) {
                executarExcluirParcela(id);
            }
        }
    };

    async function executarExcluirParcela(id) {
        try {
            const result = await API.post(`/promissorias/${codigoBase}/parcelas/${id}/excluir`, {});

            if (result.success) {
                toast.success(result.message || i18n.installmentDeleted);
                await carregarPromissoria();
            } else {
                toast.error(result.message || i18n.installmentDeleteError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(error.message || i18n.installmentDeleteError);
        }
    }

    // ===== ACOES GERAIS =====

    async function salvarDadosGerais(event) {
        event.preventDefault();

        const btnSalvar = document.getElementById('btnSalvarDadosGerais');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const formData = new FormData(document.getElementById('formDadosGerais'));
            const dados = Object.fromEntries(formData.entries());

            const result = await API.post(`/promissorias/${codigoBase}/atualizar`, dados);

            if (result.success) {
                toast.success(result.message || i18n.generalDataSaved);
            } else {
                toast.error(result.message || i18n.generalDataError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(error.message || i18n.generalDataError);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.saveGeneralData}`;
        }
    }

    function marcarTodasPagas() {
        const pendentes = parcelasData.filter(p => p.pago !== 'S');
        if (pendentes.length === 0) {
            toast.info(i18n.allAlreadyPaid);
            return;
        }

        if (window.parent !== window) {
            pendingAction = 'marcarTodasPagas';
            pendingData = null;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.markAllTitle,
                message: i18n.markAllConfirm.replace(':count', pendentes.length),
                confirmText: i18n.markAllBtn
            }, '*');
        } else {
            if (confirm(i18n.markAllConfirm.replace(':count', pendentes.length))) {
                executarMarcarTodasPagas();
            }
        }
    }

    async function executarMarcarTodasPagas() {
        try {
            const result = await API.post(`/promissorias/${codigoBase}/marcar-pago`, {});

            if (result.success) {
                toast.success(result.message || i18n.allMarkedPaid);
                await carregarPromissoria();
            } else {
                toast.error(result.message || i18n.markAllError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(error.message || i18n.markAllError);
        }
    }

    function excluirPromissoria() {
        if (window.parent !== window) {
            pendingAction = 'excluirPromissoria';
            pendingData = codigoBase;
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: codigoBase,
                recordName: i18n.titleSingular + ' ' + codigoBase,
                recordType: i18n.titleSingular,
                confirmType: 'text'
            }, '*');
        } else {
            if (confirm(i18n.confirmDeletePromissory)) {
                executarExcluirPromissoria();
            }
        }
    }

    async function executarExcluirPromissoria() {
        try {
            const result = await API.post(`/promissorias/${codigoBase}/excluir`, {});

            if (result.success) {
                toast.success(result.message || i18n.deletedSuccess);
                voltar();
            } else {
                toast.error(result.message || i18n.deleteError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(error.message || i18n.deleteError);
        }
    }

    function imprimir() {
        window.open(`/promissorias/${codigoBase}/imprimir`, '_blank');
    }

    window.imprimirParcela = function(id) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openPrintModal',
                url: `/promissorias/${codigoBase}/parcelas/${id}/imprimir`,
                title: i18n.printInstallmentTitle
            }, '*');
        } else {
            window.open(`/promissorias/${codigoBase}/parcelas/${id}/imprimir`, '_blank');
        }
    };

    // ===== EVENT LISTENERS =====

    document.addEventListener('DOMContentLoaded', function() {
        init();

        // Navegacao
        document.getElementById('btnVoltar')?.addEventListener('click', voltar);

        // Dados gerais
        document.getElementById('formDadosGerais')?.addEventListener('submit', salvarDadosGerais);

        // Parcelas
        document.getElementById('btnAddParcela')?.addEventListener('click', abrirModalAdicionar);
        document.getElementById('formParcela')?.addEventListener('submit', salvarParcela);
        document.getElementById('btnFecharModal')?.addEventListener('click', fecharModal);
        document.getElementById('btnCancelarModal')?.addEventListener('click', fecharModal);

        // Acoes gerais
        document.getElementById('btnMarcarTodasPagas')?.addEventListener('click', marcarTodasPagas);
        document.getElementById('btnExcluir')?.addEventListener('click', excluirPromissoria);
        document.getElementById('btnImprimir')?.addEventListener('click', imprimir);

        // Fechar modal ao clicar fora
        document.getElementById('modalParcela')?.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
    });

    // ===== LISTENER PARA MODAIS DO SISTEMA =====

    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Confirmacao generica (marcar como pago)
        if (event.data.action === 'genericConfirmed' && pendingAction) {
            if (pendingAction === 'marcarParcelaPaga') {
                executarMarcarParcelaPaga(pendingData);
            } else if (pendingAction === 'marcarTodasPagas') {
                executarMarcarTodasPagas();
            }
            pendingAction = null;
            pendingData = null;
        }

        // Confirmacao de exclusao
        if (event.data.action === 'confirmDelete' && pendingAction) {
            if (pendingAction === 'excluirParcela') {
                executarExcluirParcela(pendingData);
            } else if (pendingAction === 'excluirPromissoria') {
                executarExcluirPromissoria();
            }
            pendingAction = null;
            pendingData = null;
        }
    });

    // ===== HELPERS =====

    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    }

    function formatarMoedaInput(valor) {
        const num = parseFloat(valor) || 0;
        return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const [ano, mes, dia] = dataStr.split('-');
        return `${dia}/${mes}/${ano}`;
    }
})();
</script>
@endsection
