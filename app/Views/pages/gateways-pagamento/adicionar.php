@extends('layouts.iframe')

@section('title', '<?= t("modules.gateways_pagamento.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.gateways_pagamento.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formPrincipal" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Secao: Dados Basicos -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-plug mr-2"></i><?= t('modules.gateways_pagamento.sections.gateway_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Gateway -->
                <div class="md:col-span-3 form-input-group">
                    <label for="gateway_code" class="form-label-group">
                        <?= t('modules.gateways_pagamento.fields.gateway') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="gateway_code" name="gateway_code" class="form-input-group-field" required>
                        <option value=""><?= t('modules.gateways_pagamento.dropdowns.select_gateway') ?></option>
                    </select>
                </div>

                <!-- Nome -->
                <div class="md:col-span-3 form-input-group">
                    <label for="nome" class="form-label-group">
                        <?= t('modules.gateways_pagamento.fields.name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" class="form-input-group-field" required maxlength="100" placeholder="<?= t('modules.gateways_pagamento.hints.name_placeholder') ?>">
                </div>

                <!-- Filiais -->
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.gateways_pagamento.fields.branches') ?> {!! aviso(t('modules.gateways_pagamento.hints.branches')) !!}
                    </label>
                    <div id="filiaisDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="filiaisDropdownTrigger">
                            <span class="filiais-dropdown-text" id="filiaisDropdownText"><?= t('modules.gateways_pagamento.dropdowns.all_branches') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="filiaisDropdownMenu">
                            <div class="filiais-loading">
                                <i class="fas fa-spinner fa-spin"></i> <?= t('common.labels.loading') ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="filiaisIdsJson" name="filiais_ids">
                </div>

                <!-- Moedas -->
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.gateways_pagamento.fields.currencies') ?> <span class="text-red-500">*</span> {!! aviso(t('modules.gateways_pagamento.hints.currencies')) !!}
                    </label>
                    <div id="currenciesDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="currenciesDropdownTrigger">
                            <span class="filiais-dropdown-text" id="currenciesDropdownText"><?= t('modules.gateways_pagamento.dropdowns.select_gateway_first') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="currenciesDropdownMenu">
                            <span class="text-slate-400 text-sm p-3"><?= t('modules.gateways_pagamento.dropdowns.select_gateway_first') ?></span>
                        </div>
                    </div>
                    <input type="hidden" id="currenciesJson" name="currencies">
                </div>

                <!-- Ambiente -->
                <div class="md:col-span-2 form-input-group">
                    <label for="ambiente" class="form-label-group">
                        <?= t('modules.gateways_pagamento.fields.environment') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="ambiente" name="ambiente" class="form-input-group-field" required>
                        <option value="sandbox"><?= t('modules.gateways_pagamento.environment.sandbox') ?></option>
                        <option value="production"><?= t('modules.gateways_pagamento.environment.production') ?></option>
                    </select>
                </div>

                <!-- Status -->
                <div class="md:col-span-1 form-input-group">
                    <label class="form-label-group"><?= t('modules.gateways_pagamento.fields.status') ?></label>
                    <div class="flex items-center mt-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="status" name="status" value="A" checked class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-700"><?= t('modules.gateways_pagamento.status_options.active') ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Ordem -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <div class="md:col-span-2 form-input-group">
                    <label for="ordem" class="form-label-group">
                        <?= t('modules.gateways_pagamento.fields.display_order') ?> {!! aviso(t('modules.gateways_pagamento.hints.display_order')) !!}
                    </label>
                    <input type="number" id="ordem" name="ordem" class="form-input-group-field" min="0" max="999" value="0">
                </div>
            </div>
        </div>

        <!-- Secao: Metodos de Pagamento -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-credit-card mr-2"></i><?= t('modules.gateways_pagamento.sections.payment_methods') ?></h3>
            <p class="text-sm text-slate-600 mb-4"><?= t('modules.gateways_pagamento.sections.payment_methods_desc') ?></p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="form-input-group">
                    <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-slate-50 transition-colors" id="label_pix">
                        <input type="checkbox" id="pix_enabled" name="pix_enabled" value="1" class="form-checkbox h-5 w-5 text-green-600 rounded border-slate-300 focus:ring-green-500">
                        <div class="ml-3">
                            <span class="block font-medium text-slate-700"><?= t('modules.gateways_pagamento.methods.pix') ?></span>
                            <span class="block text-xs text-slate-500"><?= t('modules.gateways_pagamento.methods.pix_desc') ?></span>
                        </div>
                    </label>
                </div>

                <div class="form-input-group">
                    <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-slate-50 transition-colors" id="label_boleto">
                        <input type="checkbox" id="boleto_enabled" name="boleto_enabled" value="1" class="form-checkbox h-5 w-5 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                        <div class="ml-3">
                            <span class="block font-medium text-slate-700"><?= t('modules.gateways_pagamento.methods.boleto') ?></span>
                            <span class="block text-xs text-slate-500"><?= t('modules.gateways_pagamento.methods.boleto_desc') ?></span>
                        </div>
                    </label>
                </div>

                <div class="form-input-group">
                    <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-slate-50 transition-colors" id="label_credit_card">
                        <input type="checkbox" id="credit_card_enabled" name="credit_card_enabled" value="1" class="form-checkbox h-5 w-5 text-purple-600 rounded border-slate-300 focus:ring-purple-500">
                        <div class="ml-3">
                            <span class="block font-medium text-slate-700"><?= t('modules.gateways_pagamento.methods.credit_card') ?></span>
                            <span class="block text-xs text-slate-500"><?= t('modules.gateways_pagamento.methods.credit_card_desc') ?></span>
                        </div>
                    </label>
                </div>

                <div class="form-input-group">
                    <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-slate-50 transition-colors" id="label_debit_card">
                        <input type="checkbox" id="debit_card_enabled" name="debit_card_enabled" value="1" class="form-checkbox h-5 w-5 text-amber-600 rounded border-slate-300 focus:ring-amber-500">
                        <div class="ml-3">
                            <span class="block font-medium text-slate-700"><?= t('modules.gateways_pagamento.methods.debit_card') ?></span>
                            <span class="block text-xs text-slate-500"><?= t('modules.gateways_pagamento.methods.debit_card_desc') ?></span>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Secao: Credenciais (dinamica) -->
        <div class="form-section mb-6" id="sectionCredenciais" style="display: none;">
            <h3 class="form-section-title"><i class="fas fa-key mr-2"></i><?= t('modules.gateways_pagamento.sections.credentials') ?></h3>
            <p class="text-sm text-slate-600 mb-4"><?= t('modules.gateways_pagamento.sections.credentials_desc') ?> <span id="docLink"></span></p>

            <div id="credenciaisContainer" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Campos dinamicos serao inseridos aqui -->
            </div>
        </div>

        <!-- Secao: Webhook -->
        <div class="form-section mb-6" id="sectionWebhook" style="display: none;">
            <h3 class="form-section-title"><i class="fas fa-satellite-dish mr-2"></i><?= t('modules.gateways_pagamento.sections.webhook') ?></h3>
            <p class="text-sm text-slate-600 mb-4"><?= t('modules.gateways_pagamento.sections.webhook_desc') ?></p>

            <div class="grid grid-cols-1 gap-4">
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.gateways_pagamento.fields.webhook_url') ?></label>
                    <div class="flex items-center space-x-2">
                        <input type="text" id="webhook_url" class="form-input-group-field bg-slate-50" readonly>
                        <button type="button" id="btnCopyWebhook" class="btn-secondary py-2 px-3 rounded-md text-sm" title="<?= t('modules.gateways_pagamento.actions.copy_url') ?>">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnTestar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center" style="display: none;">
                <i class="fas fa-plug mr-2"></i><?= t('modules.gateways_pagamento.actions.test_connection') ?>
            </button>
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
    (function() {
        const i18n = {
            loading: '<?= addslashes(t("common.labels.loading")) ?>',
            editTitle: '<?= addslashes(t("modules.gateways_pagamento.edit_title")) ?>',
            countryBR: '<?= addslashes(t("modules.gateways_pagamento.countries.BR")) ?>',
            countryPY: '<?= addslashes(t("modules.gateways_pagamento.countries.PY")) ?>',
            countryINTL: '<?= addslashes(t("modules.gateways_pagamento.countries.INTL")) ?>',
            allBranches: '<?= addslashes(t("modules.gateways_pagamento.dropdowns.all_branches")) ?>',
            noBranches: '<?= addslashes(t("modules.gateways_pagamento.dropdowns.no_branches")) ?>',
            noBranchesShort: '<?= addslashes(t("modules.gateways_pagamento.dropdowns.no_branches_short")) ?>',
            loadBranchesError: '<?= addslashes(t("modules.gateways_pagamento.messages.load_branches_error")) ?>',
            loadError: '<?= addslashes(t("modules.gateways_pagamento.dropdowns.load_error")) ?>',
            selectGatewayFirst: '<?= addslashes(t("modules.gateways_pagamento.dropdowns.select_gateway_first")) ?>',
            noCurrencies: '<?= addslashes(t("modules.gateways_pagamento.dropdowns.no_currencies")) ?>',
            viewDocs: '<?= addslashes(t("modules.gateways_pagamento.actions.view_docs")) ?>',
            notFound: '<?= addslashes(t("modules.gateways_pagamento.messages.not_found")) ?>',
            loadDataError: '<?= addslashes(t("modules.gateways_pagamento.messages.load_error")) ?>',
            gatewayRequired: '<?= addslashes(t("modules.gateways_pagamento.messages.gateway_required")) ?>',
            nameRequired: '<?= addslashes(t("modules.gateways_pagamento.messages.name_required")) ?>',
            currencyRequired: '<?= addslashes(t("modules.gateways_pagamento.messages.currency_required")) ?>',
            saving: '<?= addslashes(t("common.labels.saving")) ?>',
            saveSuccess: '<?= addslashes(t("modules.gateways_pagamento.messages.save_success")) ?>',
            saveError: '<?= addslashes(t("modules.gateways_pagamento.messages.save_error")) ?>',
            btnSave: '<?= addslashes(t("common.buttons.save")) ?>',
            testSuccess: '<?= addslashes(t("modules.gateways_pagamento.messages.test_success")) ?>',
            testFail: '<?= addslashes(t("modules.gateways_pagamento.messages.test_fail")) ?>',
            testError: '<?= addslashes(t("modules.gateways_pagamento.messages.test_error")) ?>',
            testConnection: '<?= addslashes(t("modules.gateways_pagamento.actions.test_connection")) ?>',
            testing: '<?= addslashes(t("modules.gateways_pagamento.actions.testing")) ?>',
            branchFallback: '<?= addslashes(t("modules.gateways_pagamento.messages.branch_fallback")) ?>',
        };

        let registroId = null;
        let gatewaysDisponiveis = [];
        let gatewayAtual = null;
        let credenciaisOriginais = {};
        let filiaisDisponiveis = [];
        let filiaisSelecionadas = [];
        let dropdownFiliaisAberto = false;
        let dropdownMoedasAberto = false;

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

        // Carregar gateways disponiveis
        async function carregarGatewaysDisponiveis() {
            try {
                const result = await API.get('/api/gateways-pagamento/disponiveis');

                if (result.success && result.data) {
                    gatewaysDisponiveis = result.data;
                    preencherSelectGateways();
                }
            } catch (error) {
                console.error('Erro ao carregar gateways:', error);
            }
        }

        // ===== FILIAIS MULTI-SELECT =====

        async function carregarFiliais() {
            const dropdownMenu = document.getElementById('filiaisDropdownMenu');
            const dropdownText = document.getElementById('filiaisDropdownText');

            try {
                const result = await API.get('/api/matrizes-filiais/buscar');

                if (result.success && result.data) {
                    filiaisDisponiveis = result.data;
                    renderizarFiliais();
                } else {
                    dropdownMenu.innerHTML = `<div class="filiais-dropdown-error">${i18n.loadBranchesError}</div>`;
                    dropdownText.textContent = i18n.loadError;
                }
            } catch (error) {
                console.error('Erro ao carregar filiais:', error);
                dropdownMenu.innerHTML = '<div class="filiais-dropdown-error">Erro ao carregar filiais</div>';
                dropdownText.textContent = 'Erro ao carregar';
            }
        }

        function renderizarFiliais() {
            const dropdownMenu = document.getElementById('filiaisDropdownMenu');

            if (filiaisDisponiveis.length === 0) {
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-empty">${i18n.noBranches}</div>`;
                document.getElementById('filiaisDropdownText').textContent = i18n.noBranchesShort;
                return;
            }

            let html = '';
            filiaisDisponiveis.forEach((filial) => {
                const filialId = parseInt(filial.id);
                const filialNome = filial.text || filial.nome || i18n.branchFallback.replace(':id', filial.id);
                const isChecked = filiaisSelecionadas.includes(filialId);

                html += `
                    <div class="filial-item ${isChecked ? 'selected' : ''}" data-id="${filialId}" data-nome="${escapeHtml(filialNome)}">
                        <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                            <input type="checkbox" class="filial-checkbox" value="${filialId}" ${isChecked ? 'checked' : ''}>
                            <span class="filial-nome">${escapeHtml(filialNome)}</span>
                        </label>
                    </div>
                `;
            });

            dropdownMenu.innerHTML = html;

            // Event listeners para checkboxes
            dropdownMenu.querySelectorAll('.filial-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleFilialCheckboxChange);
            });

            atualizarTextoDropdownFiliais();
        }

        function handleFilialCheckboxChange(e) {
            const checkbox = e.target;
            const filialItem = checkbox.closest('.filial-item');
            const filialId = parseInt(checkbox.value);

            if (checkbox.checked) {
                filialItem.classList.add('selected');
                if (!filiaisSelecionadas.includes(filialId)) {
                    filiaisSelecionadas.push(filialId);
                }
            } else {
                filialItem.classList.remove('selected');
                filiaisSelecionadas = filiaisSelecionadas.filter(id => id !== filialId);
            }

            atualizarTextoDropdownFiliais();
            atualizarHiddenInputFiliais();
        }

        function atualizarTextoDropdownFiliais() {
            const dropdownText = document.getElementById('filiaisDropdownText');
            const selecionados = Array.from(document.querySelectorAll('.filial-checkbox:checked'))
                .map(cb => cb.closest('.filial-item').dataset.nome);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.allBranches;
            } else if (selecionados.length <= 2) {
                dropdownText.textContent = selecionados.join(', ');
            } else {
                dropdownText.textContent = `${selecionados.slice(0, 2).join(', ')} +${selecionados.length - 2}`;
            }
        }

        function atualizarHiddenInputFiliais() {
            document.getElementById('filiaisIdsJson').value = JSON.stringify(filiaisSelecionadas);
        }

        function setFiliaisSelecionadas(filiais) {
            filiaisSelecionadas = (filiais || []).map(f => parseInt(f.id_matriz_filial || f.id || f));
            renderizarFiliais();
            atualizarHiddenInputFiliais();
        }

        function configurarDropdownFiliais() {
            const dropdown = document.getElementById('filiaisDropdown');
            const trigger = document.getElementById('filiaisDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownFiliaisAberto = !dropdownFiliaisAberto;
                dropdown.classList.toggle('open', dropdownFiliaisAberto);
            });
        }

        // Fechar dropdowns ao clicar fora
        document.addEventListener('click', function(e) {
            // Dropdown de filiais
            const dropdownFiliais = document.getElementById('filiaisDropdown');
            if (dropdownFiliais && !dropdownFiliais.contains(e.target)) {
                dropdownFiliaisAberto = false;
                dropdownFiliais.classList.remove('open');
            }

            // Dropdown de moedas
            const dropdownMoedas = document.getElementById('currenciesDropdown');
            if (dropdownMoedas && !dropdownMoedas.contains(e.target)) {
                dropdownMoedasAberto = false;
                dropdownMoedas.classList.remove('open');
            }
        });

        function preencherSelectGateways() {
            const select = document.getElementById('gateway_code');

            // Agrupar por pais
            const porPais = {
                'BR': [],
                'PY': [],
                'INTL': []
            };

            gatewaysDisponiveis.forEach(g => {
                const country = g.country || 'INTL';
                if (!porPais[country]) porPais[country] = [];
                porPais[country].push(g);
            });

            const paisNomes = {
                'BR': i18n.countryBR,
                'PY': i18n.countryPY,
                'INTL': i18n.countryINTL
            };

            ['BR', 'PY', 'INTL'].forEach(pais => {
                if (porPais[pais] && porPais[pais].length > 0) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = paisNomes[pais];

                    porPais[pais].forEach(g => {
                        const option = document.createElement('option');
                        option.value = g.code;
                        option.textContent = g.name;
                        option.dataset.methods = JSON.stringify(g.methods || []);
                        option.dataset.currencies = JSON.stringify(g.supported_currencies || ['BRL']);
                        option.dataset.schema = JSON.stringify(g.config_schema || {});
                        option.dataset.docUrl = g.documentation_url || '';
                        optgroup.appendChild(option);
                    });

                    select.appendChild(optgroup);
                }
            });
        }

        function atualizarFormularioPorGateway() {
            const select = document.getElementById('gateway_code');
            const option = select.options[select.selectedIndex];

            if (!option || !option.value) {
                document.getElementById('sectionCredenciais').style.display = 'none';
                document.getElementById('sectionWebhook').style.display = 'none';
                document.getElementById('btnTestar').style.display = 'none';
                atualizarMetodosSuportados([]);
                return;
            }

            const methods = JSON.parse(option.dataset.methods || '[]');
            const currencies = JSON.parse(option.dataset.currencies || '["BRL"]');
            const schema = JSON.parse(option.dataset.schema || '{}');
            const docUrl = option.dataset.docUrl || '';

            gatewayAtual = {
                code: option.value,
                methods: methods,
                currencies: currencies,
                schema: schema,
                docUrl: docUrl
            };

            // Atualizar metodos suportados
            atualizarMetodosSuportados(methods);

            // Atualizar moedas disponiveis
            atualizarMoedasDisponiveis(currencies);

            // Gerar campos de credenciais
            gerarCamposCredenciais(schema);

            // Mostrar link da documentacao
            if (docUrl) {
                document.getElementById('docLink').innerHTML = `<a href="${escapeHtml(docUrl)}" target="_blank" class="text-blue-600 hover:underline"><i class="fas fa-external-link-alt mr-1"></i>${i18n.viewDocs}</a>`;
            } else {
                document.getElementById('docLink').innerHTML = '';
            }

            // Mostrar secoes
            document.getElementById('sectionCredenciais').style.display = Object.keys(schema).length > 0 ? 'block' : 'none';
            document.getElementById('sectionWebhook').style.display = 'block';
            document.getElementById('btnTestar').style.display = registroId ? 'inline-flex' : 'none';

            // Atualizar webhook URL
            const baseUrl = window.location.origin;
            document.getElementById('webhook_url').value = `${baseUrl}/webhook/${option.value}`;

            // Sugerir nome se vazio
            if (!document.getElementById('nome').value) {
                document.getElementById('nome').value = option.textContent;
            }
        }

        function atualizarMetodosSuportados(methodsSuportados) {
            const metodos = ['pix', 'boleto', 'credit_card', 'debit_card'];

            metodos.forEach(metodo => {
                const checkbox = document.getElementById(`${metodo}_enabled`);
                const label = document.getElementById(`label_${metodo}`);

                if (methodsSuportados.includes(metodo)) {
                    checkbox.disabled = false;
                    label.classList.remove('opacity-50', 'cursor-not-allowed');
                    label.classList.add('cursor-pointer');
                } else {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                    label.classList.add('opacity-50', 'cursor-not-allowed');
                    label.classList.remove('cursor-pointer');
                }
            });
        }

        // Nomes das moedas
        const currencyNames = {
            'BRL': '<?= addslashes(t("modules.gateways_pagamento.currencies.BRL")) ?>',
            'USD': '<?= addslashes(t("modules.gateways_pagamento.currencies.USD")) ?>',
            'EUR': '<?= addslashes(t("modules.gateways_pagamento.currencies.EUR")) ?>',
            'GBP': '<?= addslashes(t("modules.gateways_pagamento.currencies.GBP")) ?>',
            'CAD': '<?= addslashes(t("modules.gateways_pagamento.currencies.CAD")) ?>',
            'AUD': '<?= addslashes(t("modules.gateways_pagamento.currencies.AUD")) ?>',
            'JPY': '<?= addslashes(t("modules.gateways_pagamento.currencies.JPY")) ?>',
            'MXN': '<?= addslashes(t("modules.gateways_pagamento.currencies.MXN")) ?>',
            'CHF': '<?= addslashes(t("modules.gateways_pagamento.currencies.CHF")) ?>',
            'PYG': '<?= addslashes(t("modules.gateways_pagamento.currencies.PYG")) ?>',
            'ARS': '<?= addslashes(t("modules.gateways_pagamento.currencies.ARS")) ?>',
            'CLP': '<?= addslashes(t("modules.gateways_pagamento.currencies.CLP")) ?>',
            'COP': '<?= addslashes(t("modules.gateways_pagamento.currencies.COP")) ?>',
            'PEN': '<?= addslashes(t("modules.gateways_pagamento.currencies.PEN")) ?>',
            'UYU': '<?= addslashes(t("modules.gateways_pagamento.currencies.UYU")) ?>'
        };

        let moedasSelecionadas = [];
        let moedasSuportadasAtual = [];

        function atualizarMoedasDisponiveis(moedasSuportadas, moedasAtuais = null) {
            const dropdownMenu = document.getElementById('currenciesDropdownMenu');
            const dropdownText = document.getElementById('currenciesDropdownText');

            if (!moedasSuportadas || moedasSuportadas.length === 0) {
                dropdownMenu.innerHTML = `<span class="text-slate-400 text-sm p-3">${i18n.selectGatewayFirst}</span>`;
                dropdownText.textContent = i18n.selectGatewayFirst;
                moedasSelecionadas = [];
                moedasSuportadasAtual = [];
                atualizarHiddenInputMoedas();
                return;
            }

            moedasSuportadasAtual = moedasSuportadas;

            // Se nao tem moedas atuais definidas, selecionar todas por padrao
            if (moedasAtuais === null) {
                moedasSelecionadas = [...moedasSuportadas];
            } else {
                // Filtrar apenas moedas que o gateway suporta
                moedasSelecionadas = moedasAtuais.filter(m => moedasSuportadas.includes(m));
                if (moedasSelecionadas.length === 0) {
                    moedasSelecionadas = [...moedasSuportadas];
                }
            }

            renderizarMoedas();
            atualizarHiddenInputMoedas();
        }

        function renderizarMoedas() {
            const dropdownMenu = document.getElementById('currenciesDropdownMenu');

            if (moedasSuportadasAtual.length === 0) {
                dropdownMenu.innerHTML = `<span class="text-slate-400 text-sm p-3">${i18n.selectGatewayFirst}</span>`;
                return;
            }

            let html = '';
            moedasSuportadasAtual.forEach(currency => {
                const name = currencyNames[currency] || currency;
                const isChecked = moedasSelecionadas.includes(currency);

                html += `
                    <div class="filial-item ${isChecked ? 'selected' : ''}" data-currency="${currency}" data-nome="${currency} - ${escapeHtml(name)}">
                        <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                            <input type="checkbox" class="filial-checkbox currency-checkbox" value="${currency}" ${isChecked ? 'checked' : ''}>
                            <span class="filial-nome"><strong>${currency}</strong> - ${escapeHtml(name)}</span>
                        </label>
                    </div>
                `;
            });

            dropdownMenu.innerHTML = html;

            // Event listeners para checkboxes
            dropdownMenu.querySelectorAll('.currency-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleMoedaCheckboxChange);
            });

            atualizarTextoDropdownMoedas();
        }

        function handleMoedaCheckboxChange(e) {
            const checkbox = e.target;
            const item = checkbox.closest('.filial-item');
            const currency = checkbox.value;

            if (checkbox.checked) {
                item.classList.add('selected');
                if (!moedasSelecionadas.includes(currency)) {
                    moedasSelecionadas.push(currency);
                }
            } else {
                item.classList.remove('selected');
                moedasSelecionadas = moedasSelecionadas.filter(c => c !== currency);
            }

            atualizarTextoDropdownMoedas();
            atualizarHiddenInputMoedas();
        }

        function atualizarTextoDropdownMoedas() {
            const dropdownText = document.getElementById('currenciesDropdownText');

            if (moedasSelecionadas.length === 0) {
                dropdownText.textContent = i18n.noCurrencies;
            } else if (moedasSelecionadas.length <= 3) {
                dropdownText.textContent = moedasSelecionadas.join(', ');
            } else {
                dropdownText.textContent = `${moedasSelecionadas.slice(0, 3).join(', ')} +${moedasSelecionadas.length - 3}`;
            }
        }

        function atualizarHiddenInputMoedas() {
            document.getElementById('currenciesJson').value = JSON.stringify(moedasSelecionadas);
        }

        function configurarDropdownMoedas() {
            const dropdown = document.getElementById('currenciesDropdown');
            const trigger = document.getElementById('currenciesDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMoedasAberto = !dropdownMoedasAberto;
                dropdown.classList.toggle('open', dropdownMoedasAberto);
            });
        }

        function gerarCamposCredenciais(schema) {
            const container = document.getElementById('credenciaisContainer');
            container.innerHTML = '';

            Object.entries(schema).forEach(([key, config]) => {
                const colSpan = config.type === 'textarea' ? 'md:col-span-12' : 'md:col-span-6';
                const required = config.required ? '<span class="text-red-500">*</span>' : '';
                const help = config.help ? `{!! aviso('${escapeHtml(config.help)}') !!}` : '';

                let inputHtml = '';
                if (config.type === 'textarea') {
                    inputHtml = `<textarea id="cred_${key}" name="credentials[${key}]" class="form-input-group-field" rows="3" placeholder="${escapeHtml(config.placeholder || '')}" ${config.required ? 'required' : ''}></textarea>`;
                } else if (config.type === 'select' && config.options) {
                    const options = config.options.map(opt => `<option value="${escapeHtml(opt)}">${escapeHtml(opt)}</option>`).join('');
                    inputHtml = `<select id="cred_${key}" name="credentials[${key}]" class="form-input-group-field" ${config.required ? 'required' : ''}>${options}</select>`;
                } else {
                    const inputType = config.type === 'password' ? 'password' : 'text';
                    inputHtml = `<input type="${inputType}" id="cred_${key}" name="credentials[${key}]" class="form-input-group-field" placeholder="${escapeHtml(config.placeholder || '')}" ${config.required ? 'required' : ''}>`;
                }

                const fieldHtml = `
                    <div class="${colSpan} form-input-group">
                        <label for="cred_${key}" class="form-label-group">
                            ${escapeHtml(config.label || key)} ${required}
                        </label>
                        ${inputHtml}
                        ${config.help ? `<p class="text-xs text-slate-500 mt-1">${escapeHtml(config.help)}</p>` : ''}
                    </div>
                `;

                container.insertAdjacentHTML('beforeend', fieldHtml);
            });

            // Preencher com credenciais originais se existirem
            if (credenciaisOriginais && Object.keys(credenciaisOriginais).length > 0) {
                Object.entries(credenciaisOriginais).forEach(([key, value]) => {
                    const input = document.getElementById(`cred_${key}`);
                    if (input) {
                        input.value = value;
                    }
                });
            }
        }

        // Carregar registro para edicao
        async function carregarRegistro(id) {
            try {
                const result = await API.get(`/api/gateways-pagamento/${id}`);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    mostrarAlerta(result.message || i18n.notFound);
                    navegarPara('/pages/gateways-pagamento');
                }
            } catch (error) {
                console.error('Erro ao carregar registro:', error);
                mostrarAlerta(i18n.loadDataError);
                navegarPara('/pages/gateways-pagamento');
            }
        }

        function preencherFormulario(dados) {
            document.getElementById('registroId').value = dados.id || '';
            document.getElementById('gateway_code').value = dados.gateway_code || '';
            document.getElementById('nome').value = dados.nome || '';
            document.getElementById('ambiente').value = dados.ambiente || 'sandbox';
            document.getElementById('status').checked = dados.status === 'A';
            document.getElementById('ordem').value = dados.ordem || 0;

            // Filiais
            if (dados.filiais && dados.filiais.length > 0) {
                setFiliaisSelecionadas(dados.filiais);
            }

            // Metodos
            document.getElementById('pix_enabled').checked = dados.pix_enabled == 1;
            document.getElementById('boleto_enabled').checked = dados.boleto_enabled == 1;
            document.getElementById('credit_card_enabled').checked = dados.credit_card_enabled == 1;
            document.getElementById('debit_card_enabled').checked = dados.debit_card_enabled == 1;

            // Salvar credenciais originais (mascaradas)
            credenciaisOriginais = dados.credentials || {};

            // Desabilitar troca de gateway na edicao
            document.getElementById('gateway_code').disabled = true;

            // Atualizar formulario com base no gateway
            atualizarFormularioPorGateway();

            // Atualizar moedas com valores salvos
            let moedasSalvas = dados.currencies || ['BRL'];
            if (typeof moedasSalvas === 'string') {
                try {
                    moedasSalvas = JSON.parse(moedasSalvas);
                } catch (e) {
                    moedasSalvas = ['BRL'];
                }
            }
            if (gatewayAtual && gatewayAtual.currencies) {
                atualizarMoedasDisponiveis(gatewayAtual.currencies, moedasSalvas);
            }

            document.getElementById('pageTitle').textContent = i18n.editTitle;
            document.getElementById('btnTestar').style.display = 'inline-flex';
        }

        // Salvar
        async function salvar(event) {
            event.preventDefault();

            const btnSalvar = document.getElementById('btnSalvar');
            const gatewayCode = document.getElementById('gateway_code').value;
            const nome = document.getElementById('nome').value.trim();

            if (!gatewayCode) {
                mostrarAlerta(i18n.gatewayRequired);
                document.getElementById('gateway_code').focus();
                return;
            }

            if (!nome) {
                mostrarAlerta(i18n.nameRequired);
                document.getElementById('nome').focus();
                return;
            }

            btnSalvar.disabled = true;
            btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

            try {
                // Coletar credenciais
                const credentials = {};
                document.querySelectorAll('[name^="credentials["]').forEach(input => {
                    const key = input.name.match(/credentials\[(.+)\]/)[1];
                    credentials[key] = input.value;
                });

                // Validar moedas selecionadas
                if (moedasSelecionadas.length === 0) {
                    mostrarAlerta(i18n.currencyRequired);
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.btnSave}`;
                    return;
                }

                const dados = {
                    gateway_code: gatewayCode,
                    nome: nome,
                    filiais_ids: filiaisSelecionadas,
                    currencies: moedasSelecionadas,
                    ambiente: document.getElementById('ambiente').value,
                    status: document.getElementById('status').checked ? 'A' : 'I',
                    ordem: parseInt(document.getElementById('ordem').value) || 0,
                    pix_enabled: document.getElementById('pix_enabled').checked ? 1 : 0,
                    boleto_enabled: document.getElementById('boleto_enabled').checked ? 1 : 0,
                    credit_card_enabled: document.getElementById('credit_card_enabled').checked ? 1 : 0,
                    debit_card_enabled: document.getElementById('debit_card_enabled').checked ? 1 : 0,
                    credentials: credentials
                };

                let url = '/gateways-pagamento/salvar';
                if (registroId) {
                    url = `/gateways-pagamento/${registroId}/atualizar`;
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    window.parent.postMessage({ action: 'showToast', message: result.message || i18n.saveSuccess }, '*');
                    navegarPara('/pages/gateways-pagamento');
                } else {
                    mostrarAlerta(result.message || i18n.saveError);
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(i18n.saveError);
            } finally {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.btnSave}`;
            }
        }

        // Testar conexao
        async function testarConexao() {
            if (!registroId) return;

            const btnTestar = document.getElementById('btnTestar');
            btnTestar.disabled = true;
            btnTestar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.testing}`;

            try {
                const result = await API.post(`/api/gateways-pagamento/${registroId}/testar`);

                if (result.success) {
                    mostrarAlerta(i18n.testSuccess);
                } else {
                    mostrarAlerta(result.message || i18n.testFail);
                }
            } catch (error) {
                console.error('Erro ao testar:', error);
                mostrarAlerta(i18n.testError);
            } finally {
                btnTestar.disabled = false;
                btnTestar.innerHTML = `<i class="fas fa-plug mr-2"></i>${i18n.testConnection}`;
            }
        }

        function mostrarAlerta(mensagem) {
            window.parent.postMessage({
                action: 'openAlert',
                message: mensagem
            }, '*');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Copiar webhook URL
        document.getElementById('btnCopyWebhook')?.addEventListener('click', function() {
            const input = document.getElementById('webhook_url');
            input.select();
            document.execCommand('copy');

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        });

        // Event listeners
        document.getElementById('formPrincipal')?.addEventListener('submit', salvar);

        document.getElementById('gateway_code')?.addEventListener('change', atualizarFormularioPorGateway);

        document.getElementById('btnVoltar')?.addEventListener('click', function() {
            navegarPara('/pages/gateways-pagamento');
        });

        document.getElementById('btnCancelar')?.addEventListener('click', function() {
            navegarPara('/pages/gateways-pagamento');
        });

        document.getElementById('btnTestar')?.addEventListener('click', testarConexao);

        // Inicializacao
        async function init() {
            configurarDropdownFiliais();
            configurarDropdownMoedas();

            await Promise.all([
                carregarGatewaysDisponiveis(),
                carregarFiliais()
            ]);

            const urlParams = new URLSearchParams(window.location.search);
            registroId = urlParams.get('id');
            const gatewayPreSelecionado = urlParams.get('gateway');

            if (registroId) {
                carregarRegistro(registroId);
            } else if (gatewayPreSelecionado) {
                const selectGateway = document.getElementById('gateway_code');
                const optionExists = Array.from(selectGateway.options).some(option => option.value === gatewayPreSelecionado);
                if (optionExists) {
                    selectGateway.value = gatewayPreSelecionado;
                    atualizarFormularioPorGateway();
                }
            }
        }

        init();
    })();
</script>
@endsection
