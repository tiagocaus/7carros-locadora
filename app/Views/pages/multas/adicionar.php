@extends('layouts.iframe')

@section('title', '<?= t("modules.multas.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.multas.new_title') ?></h2>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- STEP 1: Busca do Responsavel -->
    <div id="step1">
        <div class="form-section">
            <h3 class="form-section-title"><?= t('modules.multas.sections.search_responsible') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="form-input-group">
                    <label for="busca_data_hora" class="form-label-group"><?= t('modules.multas.fields.date_time') ?> <span class="text-red-500">*</span></label>
                    <input type="datetime-local" id="busca_data_hora" class="form-input-group-field" required>
                </div>

                <div class="form-input-group">
                    <label for="busca_placa" class="form-label-group"><?= t('modules.multas.fields.plate') ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="busca_placa" class="form-input-group-field uppercase" maxlength="7" placeholder="ABC1D23" required>
                </div>

                <div class="form-input-group flex items-end">
                    <button type="button" id="btnBuscar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center w-full justify-center">
                        <i class="fas fa-search mr-2"></i><?= t('modules.multas.buttons.search_responsible') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultado da busca -->
        <div id="resultadoBusca" class="mt-4 hidden">
        </div>
    </div>

    <!-- STEP 2: Formulario Completo -->
    <div id="step2" class="hidden">
        <form id="formMulta" method="POST">
            <!-- Hiddens -->
            <input type="hidden" id="id" name="id">
            <input type="hidden" id="tipo" name="tipo">
            <input type="hidden" id="id_contrato" name="id_contrato">
            <input type="hidden" id="id_locacao" name="id_locacao">
            <input type="hidden" id="id_cliente" name="id_cliente">
            <input type="hidden" id="id_veiculo" name="id_veiculo">
            <input type="hidden" id="id_matriz_filial" name="id_matriz_filial">
            <input type="hidden" id="foto_base64" name="foto_base64">

            <!-- Dados do Responsavel (readonly) -->
            <div class="form-section">
                <h3 class="form-section-title"><?= t('modules.multas.sections.responsible_data') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.multas.fields.branch') ?></label>
                        <input type="text" id="disp_filial" class="form-input-group-field bg-slate-50" readonly>
                    </div>

                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.multas.fields.type') ?></label>
                        <input type="text" id="disp_tipo" class="form-input-group-field bg-slate-50" readonly>
                    </div>

                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.multas.fields.code') ?></label>
                        <input type="text" id="disp_codigo" class="form-input-group-field bg-slate-50" readonly>
                    </div>

                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.multas.fields.client') ?></label>
                        <input type="text" id="disp_cliente" class="form-input-group-field bg-slate-50" readonly>
                    </div>

                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.multas.fields.vehicle') ?></label>
                        <input type="text" id="disp_veiculo" class="form-input-group-field bg-slate-50" readonly>
                    </div>
                </div>
            </div>

            <!-- Dados da Multa -->
            <div class="form-section">
                <h3 class="form-section-title"><?= t('modules.multas.sections.fine_data') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Linha 1: Data/hora, Vencimento, Valor, Status -->
                    <div class="form-input-group">
                        <label for="data_hora" class="form-label-group"><?= t('modules.multas.fields.date_time') ?> <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="data_hora" name="data_hora" class="form-input-group-field bg-slate-50" readonly>
                    </div>

                    <div class="form-input-group">
                        <label for="data_vencimento" class="form-label-group"><?= t('modules.multas.fields.due_date') ?> <span class="text-red-500">*</span></label>
                        <input type="date" id="data_vencimento" name="data_vencimento" class="form-input-group-field" required>
                    </div>

                    <div class="form-input-group">
                        <label for="valor" class="form-label-group"><?= t('modules.multas.fields.value') ?> <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span id="valorPrefix" class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valor" name="valor" class="form-input-group-field pl-10 input-moeda" placeholder="0,00" required>
                        </div>
                    </div>

                    <div class="form-input-group">
                        <label for="pagador" class="form-label-group"><?= t('modules.multas.fields.payer') ?></label>
                        <select id="pagador" name="pagador" class="form-input-group-field">
                            <option value="cliente" selected><?= t('modules.multas.fields.payer_client') ?></option>
                            <option value="empresa"><?= t('modules.multas.fields.payer_company') ?></option>
                        </select>
                    </div>

                    <!-- Status de pagamento (somente edicao) -->
                    <div id="statusPagoSection" class="form-input-group hidden">
                        <label class="form-label-group"><?= t('modules.multas.fields.status') ?></label>
                        <select id="statusPago" class="form-input-group-field">
                            <option value="N"><?= t('modules.multas.badges.status_pending') ?></option>
                            <option value="S"><?= t('modules.multas.badges.status_paid') ?></option>
                        </select>
                    </div>

                    <!-- Linha 2: N. Infracao, Orgao Autuador -->
                    <div class="form-input-group">
                        <label for="n_infracao" class="form-label-group"><?= t('modules.multas.fields.infraction_number') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="n_infracao" name="n_infracao" class="form-input-group-field" maxlength="10" required>
                    </div>

                    <div id="orgaoAutuadorGroup" class="form-input-group md:col-span-3">
                        <label for="orgao_autuador" class="form-label-group"><?= t('modules.multas.fields.issuing_body') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="orgao_autuador" name="orgao_autuador" class="form-input-group-field" maxlength="150" placeholder="Ex: DETRAN, PRF, DNIT..." required>
                    </div>

                    <!-- Linha 3: Local, Cidade, Estado -->
                    <div class="form-input-group md:col-span-2">
                        <label for="local" class="form-label-group"><?= t('modules.multas.fields.location') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="local" name="local" class="form-input-group-field" maxlength="150" required>
                    </div>

                    <div class="form-input-group">
                        <label for="cidade" class="form-label-group"><?= t('modules.multas.fields.city') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="cidade" name="cidade" class="form-input-group-field" maxlength="50" required>
                    </div>

                    <div class="form-input-group">
                        <label for="estado" class="form-label-group"><?= t('modules.multas.fields.state') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="estado" name="estado" class="form-input-group-field uppercase" maxlength="2" placeholder="ES" required>
                    </div>

                    <!-- Linha 4: Descricao -->
                    <div class="form-input-group md:col-span-4">
                        <label for="descri" class="form-label-group"><?= t('modules.multas.fields.description') ?></label>
                        <input type="text" id="descri" name="descri" class="form-input-group-field" maxlength="255" placeholder="Ex: Excesso de velocidade - 20% acima do limite">
                    </div>

                    <!-- Linha 5: Upload de foto -->
                    <div class="form-input-group md:col-span-4">
                        <label class="form-label-group"><?= t('modules.multas.fields.photo') ?></label>
                        <div id="fotoUploadArea" class="border-2 border-dashed border-slate-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-400 transition-colors">
                            <div id="fotoPreview" class="hidden mb-3">
                                <img id="fotoPreviewImg" src="" alt="Preview" class="max-h-40 mx-auto rounded shadow">
                                <div id="fotoPreviewPdf" class="hidden flex flex-col items-center justify-center text-red-600">
                                    <i class="fas fa-file-pdf text-4xl mb-2"></i>
                                    <span id="fotoPreviewPdfName" class="text-sm font-medium text-slate-700"></span>
                                </div>
                                <button type="button" id="btnRemoverFoto" class="mt-2 text-red-500 text-sm hover:text-red-700">
                                    <i class="fas fa-trash mr-1"></i><?= t('common.buttons.delete') ?>
                                </button>
                            </div>
                            <div id="fotoPlaceholder">
                                <i class="fas fa-cloud-upload-alt text-2xl text-slate-400 mb-2"></i>
                                <p class="text-sm text-slate-500"><?= t('modules.multas.fields.photo') ?></p>
                                <p class="text-xs text-slate-400"><?= t('modules.multas.messages.photo_allowed_types') ?></p>
                            </div>
                            <input type="file" id="fotoInput" accept="image/*,application/pdf,.pdf" class="hidden">
                        </div>
                    </div>
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
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const i18n = {
            editTitle: '<?= addslashes(t('modules.multas.edit_title')) ?>',
            loadError: '<?= addslashes(t('modules.multas.messages.load_error')) ?>',
            notFound: '<?= addslashes(t('modules.multas.messages.not_found')) ?>',
            vehicleNotFound: '<?= addslashes(t('modules.multas.messages.vehicle_not_found')) ?>',
            responsibleFound: '<?= addslashes(t('modules.multas.messages.responsible_found')) ?>',
            responsibleNotFound: '<?= addslashes(t('modules.multas.messages.responsible_not_found')) ?>',
            requiredFields: '<?= addslashes(t('modules.multas.messages.required_fields')) ?>',
            saving: '<?= addslashes(t('modules.multas.messages.saving')) ?>',
            searching: '<?= addslashes(t('modules.multas.messages.searching')) ?>',
            saveError: '<?= addslashes(t('modules.multas.messages.save_error')) ?>',
            created: '<?= addslashes(t('modules.multas.messages.created')) ?>',
            updated: '<?= addslashes(t('modules.multas.messages.updated')) ?>',
            invalidFileType: <?= js_t('modules.multas.messages.invalid_file_type') ?>,
            pdfSelected: '<?= addslashes(t('modules.multas.messages.pdf_selected')) ?>',
            btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
            btnSearch: '<?= addslashes(t('modules.multas.buttons.search_responsible')) ?>',
            btnContinue: '<?= addslashes(t('modules.multas.buttons.continue')) ?>',
            btnAddManualResponsible: '<?= addslashes(t('modules.multas.buttons.add_manual_responsible')) ?>',
            btnContinueManual: '<?= addslashes(t('modules.multas.buttons.continue_manual')) ?>',
            typeContract: '<?= addslashes(t('modules.multas.badges.type_contract')) ?>',
            typeRental: '<?= addslashes(t('modules.multas.badges.type_rental')) ?>',
            typeManual: '<?= addslashes(t('modules.multas.fields.manual_responsible')) ?>',
            branch: '<?= addslashes(t('modules.multas.fields.branch')) ?>',
            client: '<?= addslashes(t('modules.multas.fields.client')) ?>',
            vehicle: '<?= addslashes(t('modules.multas.fields.vehicle')) ?>',
            manualResponsibleHint: '<?= addslashes(t('modules.multas.messages.manual_responsible_hint')) ?>',
            selectManualResponsible: '<?= addslashes(t('modules.multas.messages.select_manual_responsible')) ?>',
            searchClientPlaceholder: '<?= addslashes(t('modules.multas.messages.search_client_placeholder')) ?>',
        };

        let registroId = null;
        let veiculoDaBusca = null;
        let responsavelDaBusca = null;
        let clienteManualDaBusca = null;
        let statusPagoOriginal = null;

        function navegarPara(page) {
            if (window.parent !== window) {
                window.parent.postMessage({ action: 'navigate', page: page }, '*');
            } else {
                window.location.href = page;
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function mostrarAlerta(mensagem, callbackAction = null) {
            if (window.parent !== window) {
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
            } else {
                if (callbackAction) callbackAction();
            }
        }

        // ===== STEP 1: BUSCAR RESPONSAVEL =====

        async function buscarResponsavel() {
            const dataHora = document.getElementById('busca_data_hora').value;
            const placa = document.getElementById('busca_placa').value.trim();

            if (!dataHora || !placa) {
                mostrarAlerta(i18n.requiredFields + '\n\n- ' + i18n.editTitle);
                return;
            }

            const btnBuscar = document.getElementById('btnBuscar');
            btnBuscar.disabled = true;
            btnBuscar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.searching;

            try {
                // Converter datetime-local para formato Y-m-d H:i:s
                const dataFormatada = dataHora.replace('T', ' ') + ':00';

                const result = await API.post('/api/multas/buscar-responsavel', {
                    placa: placa,
                    data_hora: dataFormatada
                });

                const resultDiv = document.getElementById('resultadoBusca');
                resultDiv.classList.remove('hidden');

                if (result.success) {
                    veiculoDaBusca = result.data.veiculo;
                    responsavelDaBusca = result.data.responsavel;

                    if (responsavelDaBusca) {
                        mostrarResultadoEncontrado(result.data);
                    } else {
                        mostrarResultadoNaoEncontrado(result.data.veiculo);
                    }
                } else {
                    resultDiv.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-times-circle text-red-500 mr-3 text-lg"></i>
                                <p class="text-red-700">${escapeHtml(result.message)}</p>
                            </div>
                        </div>`;
                }
            } catch (error) {
                console.error('Erro na busca:', error);
                document.getElementById('resultadoBusca').innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-times-circle text-red-500 mr-3 text-lg"></i>
                            <p class="text-red-700">${escapeHtml(error.message || i18n.loadError)}</p>
                        </div>
                    </div>`;
            } finally {
                btnBuscar.disabled = false;
                btnBuscar.innerHTML = '<i class="fas fa-search mr-2"></i>' + i18n.btnSearch;
            }
        }

        function mostrarResultadoEncontrado(data) {
            const resp = data.responsavel;
            const veiculo = data.veiculo;
            const tipoLabel = resp.tipo === 'C' ? i18n.typeContract : i18n.typeRental;
            const codigo = resp.contrato_codigo || resp.locacao_codigo || '';
            const tipoBadgeClass = resp.tipo === 'C' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700';

            document.getElementById('resultadoBusca').innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-lg mt-0.5"></i>
                        <div class="flex-1">
                            <p class="font-medium text-green-800 mb-2">${i18n.responsibleFound}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-green-700">
                                <div><strong>Tipo:</strong> <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${tipoBadgeClass}">${tipoLabel}</span> ${escapeHtml(codigo)}</div>
                                <div><strong>${i18n.client}:</strong> ${escapeHtml(resp.cliente_nome)} ${resp.cliente_cpf_cnpj ? '(' + escapeHtml(resp.cliente_cpf_cnpj) + ')' : ''}</div>
                                <div><strong>${i18n.vehicle}:</strong> ${escapeHtml(veiculo.placa)} - ${escapeHtml(veiculo.marca)} ${escapeHtml(veiculo.modelo)}</div>
                            </div>
                            <button type="button" id="btnContinuar" class="mt-3 btn-blue py-2 px-6 rounded-md text-sm font-medium">
                                <i class="fas fa-arrow-right mr-2"></i>${i18n.btnContinue}
                            </button>
                        </div>
                    </div>
                </div>`;

            document.getElementById('btnContinuar').addEventListener('click', function() {
                preencherFormularioComBusca();
            });
        }

        function mostrarResultadoNaoEncontrado(veiculo) {
            document.getElementById('resultadoBusca').innerHTML = `
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-amber-500 mr-3 text-lg mt-0.5"></i>
                        <div class="flex-1">
                            <p class="font-medium text-amber-800">${i18n.responsibleNotFound}</p>
                            <p class="text-sm text-amber-600 mt-1">${i18n.vehicle}: ${escapeHtml(veiculo.placa)} - ${escapeHtml(veiculo.marca)} ${escapeHtml(veiculo.modelo)}</p>
                            <button type="button" id="btnAdicionarResponsavelManual" class="mt-3 btn-blue py-2 px-6 rounded-md text-sm font-medium">
                                <i class="fas fa-user-plus mr-2"></i>${i18n.btnAddManualResponsible}
                            </button>
                            <div id="manualResponsavelArea" class="hidden mt-4 border-t border-amber-200 pt-4">
                                <p class="text-sm text-amber-700 mb-3">${i18n.manualResponsibleHint}</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="form-input-group md:col-span-2 bg-white">
                                        <label for="manual_cliente_id" class="form-label-group">${i18n.client} <span class="text-red-500">*</span></label>
                                        <select id="manual_cliente_id" class="form-input-group-field chosen-select"
                                                data-chosen-type="server-side"
                                                data-chosen-search-url="/api/clientes/buscar"
                                                data-chosen-placeholder="${escapeHtml(i18n.searchClientPlaceholder)}">
                                            <option value="">${escapeHtml(i18n.selectManualResponsible)}</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="button" id="btnContinuarManual" class="btn-blue py-2 px-6 rounded-md text-sm font-medium w-full" disabled>
                                            <i class="fas fa-arrow-right mr-2"></i>${i18n.btnContinueManual}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;

            document.getElementById('btnAdicionarResponsavelManual').addEventListener('click', mostrarSelecaoResponsavelManual);
        }

        function mostrarSelecaoResponsavelManual() {
            const area = document.getElementById('manualResponsavelArea');
            if (!area) return;

            area.classList.remove('hidden');

            if (window.initChosenSelects) {
                window.initChosenSelects();
            }

            const select = document.getElementById('manual_cliente_id');
            const btnContinuar = document.getElementById('btnContinuarManual');

            if (select && !select.dataset.manualBound) {
                select.dataset.manualBound = '1';
                select.addEventListener('change', function() {
                    clienteManualDaBusca = null;
                    btnContinuar.disabled = !this.value;
                });
            }

            if (btnContinuar && !btnContinuar.dataset.manualBound) {
                btnContinuar.dataset.manualBound = '1';
                btnContinuar.addEventListener('click', preencherFormularioManual);
            }
        }

        // ===== STEP 2: PREENCHER FORMULARIO =====

        function preencherFormularioComBusca() {
            const resp = responsavelDaBusca;
            const veiculo = veiculoDaBusca;
            const dataHora = document.getElementById('busca_data_hora').value;

            // Hiddens
            document.getElementById('tipo').value = resp.tipo;
            document.getElementById('id_contrato').value = resp.id_contrato || '';
            document.getElementById('id_locacao').value = resp.id_locacao || '';
            document.getElementById('id_cliente').value = resp.id_cliente;
            document.getElementById('id_veiculo').value = veiculo.id;
            document.getElementById('id_matriz_filial').value = resp.id_matriz_filial || '';

            // Display fields
            document.getElementById('disp_filial').value = resp.filial_nome || '';
            document.getElementById('disp_tipo').value = resp.tipo === 'C' ? i18n.typeContract : i18n.typeRental;
            document.getElementById('disp_codigo').value = resp.contrato_codigo || resp.locacao_codigo || '';
            document.getElementById('disp_cliente').value = resp.cliente_nome + (resp.cliente_cpf_cnpj ? ' (' + resp.cliente_cpf_cnpj + ')' : '');
            document.getElementById('disp_veiculo').value = veiculo.placa + ' - ' + veiculo.marca + ' ' + veiculo.modelo;

            // Data/hora
            document.getElementById('data_hora').value = dataHora;

            // Currency prefix
            if (typeof Currency !== 'undefined' && Currency.config) {
                document.getElementById('valorPrefix').textContent = Currency.config.symbol;
            }

            // Mostrar step 2, esconder step 1
            document.getElementById('step1').classList.add('hidden');
            document.getElementById('step2').classList.remove('hidden');
        }

        async function preencherFormularioManual() {
            const select = document.getElementById('manual_cliente_id');
            const clienteId = select ? parseInt(select.value || '0', 10) : 0;

            if (!clienteId || !veiculoDaBusca) {
                mostrarAlerta(i18n.requiredFields + '\n\n- ' + i18n.selectManualResponsible);
                return;
            }

            const btnContinuar = document.getElementById('btnContinuarManual');
            if (btnContinuar) {
                btnContinuar.disabled = true;
                btnContinuar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.searching;
            }

            try {
                const result = await API.get(`/api/clientes/${clienteId}`);
                if (!result.success || !result.data) {
                    mostrarAlerta(result.message || i18n.loadError);
                    return;
                }

                clienteManualDaBusca = result.data;
                const veiculo = veiculoDaBusca;
                const dataHora = document.getElementById('busca_data_hora').value;

                document.getElementById('tipo').value = '';
                document.getElementById('id_contrato').value = '';
                document.getElementById('id_locacao').value = '';
                document.getElementById('id_cliente').value = clienteManualDaBusca.id;
                document.getElementById('id_veiculo').value = veiculo.id;
                document.getElementById('id_matriz_filial').value = veiculo.id_matriz_filial || '';

                document.getElementById('disp_filial').value = veiculo.filial_nome || '';
                document.getElementById('disp_tipo').value = i18n.typeManual;
                document.getElementById('disp_codigo').value = '-';
                document.getElementById('disp_cliente').value = (clienteManualDaBusca.nome_rsocial || '') + (clienteManualDaBusca.cpf_cnpj ? ' (' + clienteManualDaBusca.cpf_cnpj + ')' : '');
                document.getElementById('disp_veiculo').value = veiculo.placa + ' - ' + veiculo.marca + ' ' + veiculo.modelo;

                document.getElementById('data_hora').value = dataHora;

                if (typeof Currency !== 'undefined' && Currency.config) {
                    document.getElementById('valorPrefix').textContent = Currency.config.symbol;
                }

                document.getElementById('step1').classList.add('hidden');
                document.getElementById('step2').classList.remove('hidden');
            } catch (error) {
                console.error('Erro ao carregar cliente manual:', error);
                mostrarAlerta(error.message || i18n.loadError);
            } finally {
                if (btnContinuar) {
                    btnContinuar.disabled = false;
                    btnContinuar.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>' + i18n.btnContinueManual;
                }
            }
        }

        // ===== EDICAO =====

        async function carregarDados(id) {
            try {
                const result = await API.get(`/api/multas/${id}`);

                if (result.success && result.data) {
                    preencherFormularioEdicao(result.data);
                } else {
                    mostrarAlerta(i18n.loadError + ': ' + (result.message || i18n.notFound), function() {
                        navegarPara('/pages/central-multas');
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                mostrarAlerta(i18n.loadError, function() {
                    navegarPara('/pages/central-multas');
                });
            }
        }

        function preencherFormularioEdicao(data) {
            document.getElementById('id').value = data.id;

            // Hiddens
            document.getElementById('tipo').value = data.tipo || '';
            document.getElementById('id_contrato').value = data.id_contrato || '';
            document.getElementById('id_locacao').value = data.id_locacao || '';
            document.getElementById('id_cliente').value = data.id_cliente || '';
            document.getElementById('id_veiculo').value = data.id_veiculo || '';
            document.getElementById('id_matriz_filial').value = data.id_matriz_filial || '';

            // Display readonly
            document.getElementById('disp_filial').value = data.filial_nome || '';
            document.getElementById('disp_tipo').value = data.tipo === 'C' ? i18n.typeContract : (data.tipo === 'L' ? i18n.typeRental : i18n.typeManual);
            document.getElementById('disp_codigo').value = data.contrato_codigo || data.locacao_codigo || '-';
            document.getElementById('disp_cliente').value = (data.cliente_nome || '') + (data.cliente_cpf_cnpj ? ' (' + data.cliente_cpf_cnpj + ')' : '');
            document.getElementById('disp_veiculo').value = (data.veiculo_placa || '') + ' - ' + (data.veiculo_marca || '') + ' ' + (data.veiculo_modelo || '');

            // Dados da multa
            if (data.data_hora) {
                // Converter para formato datetime-local
                const dt = data.data_hora.replace(' ', 'T').substring(0, 16);
                document.getElementById('data_hora').value = dt;
            }
            document.getElementById('data_vencimento').value = data.data_vencimento || '';
            document.getElementById('n_infracao').value = data.n_infracao || '';
            document.getElementById('orgao_autuador').value = data.orgao_autuador || '';
            document.getElementById('local').value = data.local || '';
            document.getElementById('cidade').value = data.cidade || '';
            document.getElementById('estado').value = data.estado || '';
            document.getElementById('descri').value = data.descri || '';
            document.getElementById('pagador').value = data.pagador || 'cliente';

            // Valor formatado
            Currency.setValue('#valor', data.valor || 0);

            // Currency prefix
            if (typeof Currency !== 'undefined' && Currency.config) {
                document.getElementById('valorPrefix').textContent = Currency.config.symbol;
            }

            // Foto existente
            if (data.foto_url) {
                renderFilePreview(data.foto_url, data.foto || '', isPdfFilename(data.foto || data.foto_url));
                document.getElementById('fotoPreview').classList.remove('hidden');
                document.getElementById('fotoPlaceholder').classList.add('hidden');
            }

            // Status de pagamento (somente edicao)
            statusPagoOriginal = data.pago || 'N';
            document.getElementById('statusPago').value = statusPagoOriginal;
            document.getElementById('statusPagoSection').classList.remove('hidden');
            document.getElementById('orgaoAutuadorGroup').classList.remove('md:col-span-3');
            document.getElementById('orgaoAutuadorGroup').classList.add('md:col-span-2');

            // Titulo de edicao
            document.getElementById('pageTitle').textContent = i18n.editTitle;

            // Ir direto para step 2
            document.getElementById('step1').classList.add('hidden');
            document.getElementById('step2').classList.remove('hidden');
        }

        // ===== UPLOAD DE FOTO =====

        function isPdfFilename(filename) {
            return String(filename || '').toLowerCase().split('?')[0].endsWith('.pdf');
        }

        function isImageFilename(filename) {
            return /\.(jpe?g|png|gif|webp|svg)$/i.test(String(filename || '').split('?')[0]);
        }

        function isPdfFile(file) {
            return file.type === 'application/pdf' || isPdfFilename(file.name);
        }

        function isAllowedFineFile(file) {
            return file.type.startsWith('image/') || isImageFilename(file.name) || isPdfFile(file);
        }

        function renderFilePreview(src, filename, isPdf) {
            const previewImg = document.getElementById('fotoPreviewImg');
            const previewPdf = document.getElementById('fotoPreviewPdf');
            const previewPdfName = document.getElementById('fotoPreviewPdfName');

            if (isPdf) {
                previewImg.src = '';
                previewImg.classList.add('hidden');
                previewPdfName.textContent = filename || i18n.pdfSelected;
                previewPdf.classList.remove('hidden');
            } else {
                previewPdf.classList.add('hidden');
                previewPdfName.textContent = '';
                previewImg.src = src;
                previewImg.classList.remove('hidden');
            }
        }

        function clearFilePreview() {
            const input = document.getElementById('fotoInput');
            const preview = document.getElementById('fotoPreview');
            const previewImg = document.getElementById('fotoPreviewImg');
            const placeholder = document.getElementById('fotoPlaceholder');

            document.getElementById('foto_base64').value = '';
            previewImg.src = '';
            previewImg.classList.remove('hidden');
            document.getElementById('fotoPreviewPdf').classList.add('hidden');
            document.getElementById('fotoPreviewPdfName').textContent = '';
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
            input.value = '';
        }

        function configurarUploadFoto() {
            const area = document.getElementById('fotoUploadArea');
            const input = document.getElementById('fotoInput');
            const preview = document.getElementById('fotoPreview');
            const previewImg = document.getElementById('fotoPreviewImg');
            const placeholder = document.getElementById('fotoPlaceholder');
            const btnRemover = document.getElementById('btnRemoverFoto');

            area.addEventListener('click', function(e) {
                if (e.target === btnRemover || btnRemover.contains(e.target)) return;
                input.click();
            });

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                if (!isAllowedFineFile(file)) {
                    clearFilePreview();
                    mostrarAlerta(i18n.invalidFileType);
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(ev) {
                    const base64 = ev.target.result;
                    document.getElementById('foto_base64').value = base64;
                    renderFilePreview(base64, file.name, isPdfFile(file));
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            });

            btnRemover.addEventListener('click', function(e) {
                e.stopPropagation();
                clearFilePreview();
            });

            // Drag and drop
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                area.classList.add('border-blue-400', 'bg-blue-50');
            });
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                area.classList.remove('border-blue-400', 'bg-blue-50');
            });
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                area.classList.remove('border-blue-400', 'bg-blue-50');
                const file = e.dataTransfer.files[0];
                if (file && isAllowedFineFile(file)) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    input.dispatchEvent(new Event('change'));
                } else if (file) {
                    mostrarAlerta(i18n.invalidFileType);
                }
            });
        }

        // ===== SALVAR =====

        async function salvar(e) {
            e.preventDefault();

            const form = document.getElementById('formMulta');
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());
            dados.estado = (dados.estado || '').trim().toUpperCase();
            document.getElementById('estado').value = dados.estado;

            // Validacao
            const erros = [];
            if (!dados.data_hora) erros.push('- Data e Hora');
            if (!dados.data_vencimento) erros.push('- Data de Vencimento');
            if (!dados.id_veiculo) erros.push('- Veiculo');
            if (!dados.id_cliente) erros.push('- Cliente');
            if (!dados.n_infracao?.trim()) erros.push('- N. Infracao');
            if (!dados.orgao_autuador?.trim()) erros.push('- Orgao Autuador');
            if (!dados.local?.trim()) erros.push('- Local');
            if (!dados.cidade?.trim()) erros.push('- Cidade');
            if (!dados.estado) erros.push('- Estado');
            if (dados.estado && !/^[A-Z]{2}$/.test(dados.estado)) {
                erros.push('- Estado deve ser uma UF com 2 letras');
            }

            const valorInput = document.getElementById('valor');
            if (!valorInput.value || valorInput.value.trim() === '' || valorInput.value === '0,00') {
                erros.push('- Valor');
            }

            if (erros.length > 0) {
                mostrarAlerta(i18n.requiredFields + '\n\n' + erros.join('\n'));
                return;
            }

            // Converter valor e data_hora para formato backend
            dados.valor = Currency.parse(dados.valor);
            dados.data_hora = dados.data_hora.replace('T', ' ');
            if (dados.data_hora.length === 16) dados.data_hora += ':00';

            try {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

                let url;
                if (registroId) {
                    url = `/multas/${registroId}/atualizar`;
                } else {
                    url = '/multas/salvar';
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    // Sync status pago se mudou (somente edicao)
                    if (registroId && statusPagoOriginal !== null) {
                        const novoStatus = document.getElementById('statusPago').value;
                        if (novoStatus !== statusPagoOriginal) {
                            const endpoint = novoStatus === 'S'
                                ? `/multas/${registroId}/marcar-pago`
                                : `/multas/${registroId}/marcar-nao-pago`;
                            await API.post(endpoint);
                        }
                    }

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'showToast',
                            type: 'success',
                            message: registroId ? i18n.updated : i18n.created
                        }, '*');
                    }
                    navegarPara('/pages/central-multas');
                } else {
                    mostrarAlerta(result.message || i18n.saveError);
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(error.message || i18n.saveError);
            } finally {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.btnSave;
            }
        }

        // ===== INICIALIZACAO =====

        async function init() {
            const urlParams = new URLSearchParams(window.location.search);
            registroId = urlParams.get('id');

            if (registroId) {
                await carregarDados(registroId);
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            configurarUploadFoto();

            document.getElementById('formMulta').addEventListener('submit', salvar);

            document.getElementById('btnBuscar').addEventListener('click', buscarResponsavel);

            document.getElementById('estado').addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
            });

            // Enter na placa tambem busca
            document.getElementById('busca_placa').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarResponsavel();
                }
            });

            document.getElementById('btnVoltar').addEventListener('click', function() {
                navegarPara('/pages/central-multas');
            });

            document.getElementById('btnCancelar').addEventListener('click', function() {
                navegarPara('/pages/central-multas');
            });

            init();
        });
    })();
</script>
@endsection
