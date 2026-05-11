@extends('layouts.iframe')

@section('title', '<?= t("modules.formas_pagamento.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.formas_pagamento.new_title') ?></h2>
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
            <h3 class="form-section-title"><i class="fas fa-credit-card mr-2"></i><?= t('modules.formas_pagamento.sections.payment_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Filiais -->
                <div class="md:col-span-12 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.branches') ?> <span class="text-red-500">*</span> {!! aviso(t('modules.formas_pagamento.fields.branches_hint')) !!}
                    </label>
                    <div id="filiaisDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="filiaisDropdownTrigger">
                            <span class="filiais-dropdown-text" id="filiaisDropdownText"><?= t('modules.formas_pagamento.dropdowns.select_branches') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="filiaisDropdownMenu">
                            <div class="filiais-loading">
                                <i class="fas fa-spinner fa-spin"></i> <?= t('modules.formas_pagamento.dropdowns.loading_branches') ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="filiaisIdsJson" name="filiais_ids">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <!-- Nome -->
                <div class="md:col-span-3 form-input-group">
                    <label for="nome" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" class="form-input-group-field" maxlength="100" required>
                </div>

                <!-- Onde Exibir -->
                <div class="md:col-span-2 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.where_to_show') ?> {!! aviso(t('modules.formas_pagamento.fields.where_to_show_hint')) !!}
                    </label>
                    <div id="ondeExibirDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="ondeExibirDropdownTrigger">
                            <span class="filiais-dropdown-text" id="ondeExibirDropdownText"><?= t('modules.formas_pagamento.where_options.system') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="ondeExibirDropdownMenu">
                            <div class="filial-item" data-id="1" data-nome="<?= t('modules.formas_pagamento.where_options.site') ?>">
                                <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="onde-exibir-checkbox" value="1">
                                    <span class="filial-nome"><?= t('modules.formas_pagamento.where_options.site') ?></span>
                                </label>
                            </div>
                            <div class="filial-item selected" data-id="2" data-nome="<?= t('modules.formas_pagamento.where_options.system') ?>">
                                <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="onde-exibir-checkbox" value="2" checked>
                                    <span class="filial-nome"><?= t('modules.formas_pagamento.where_options.system') ?></span>
                                </label>
                            </div>
                            <div class="filial-item" data-id="3" data-nome="<?= t('modules.formas_pagamento.where_options.app') ?>">
                                <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="onde-exibir-checkbox" value="3">
                                    <span class="filial-nome"><?= t('modules.formas_pagamento.where_options.app') ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lancar Pago -->
                <div class="md:col-span-2 form-input-group">
                    <label class="form-label-group"><?= t('modules.formas_pagamento.fields.post_as_paid') ?></label>
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="lancar_pago" name="lancar_pago" value="S" class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-700"><?= t('modules.formas_pagamento.badges.yes') ?></span>
                        </label>
                    </div>
                </div>

                <!-- Status -->
                <div class="md:col-span-1 form-input-group">
                    <label class="form-label-group"><?= t('modules.formas_pagamento.table.status') ?></label>
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="status" name="status" value="A" checked class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-700"><?= t('modules.formas_pagamento.badges.active') ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Gateways de Pagamento -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <div class="md:col-span-12 form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.payment_gateways') ?> {!! aviso(t('modules.formas_pagamento.fields.payment_gateways_hint')) !!}
                    </label>
                    <div id="gatewaysDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="gatewaysDropdownTrigger">
                            <span class="filiais-dropdown-text" id="gatewaysDropdownText"><?= t('modules.formas_pagamento.dropdowns.no_gateway_selected') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="gatewaysDropdownMenu">
                            <div class="filiais-loading">
                                <i class="fas fa-spinner fa-spin"></i> <?= t('modules.formas_pagamento.dropdowns.loading_gateways') ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="gatewaysIdsJson" name="gateways_ids">
                </div>
            </div>
        </div>

        <!-- Secao: Multa e Juros -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-percentage mr-2"></i><?= t('modules.formas_pagamento.sections.penalty_interest') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Multa -->
                <div class="md:col-span-3 form-input-group">
                    <label for="multa" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.penalty_percent') ?> {!! aviso(t('modules.formas_pagamento.fields.penalty_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <input type="text" id="multa" name="multa" class="form-input-group-field input-percent" placeholder="0,00">
                        <span class="input-addon">%</span>
                    </div>
                </div>

                <!-- Juros por dia -->
                <div class="md:col-span-3 form-input-group">
                    <label for="juros_por_dia" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.interest_per_day') ?> {!! aviso(t('modules.formas_pagamento.fields.interest_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <input type="text" id="juros_por_dia" name="juros_por_dia" class="form-input-group-field input-percent" data-decimals="3" placeholder="0,000">
                        <span class="input-addon">%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao: Taxas da Cobranca -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-money-bill-wave mr-2"></i><?= t('modules.formas_pagamento.sections.billing_fees') ?></h3>
            <p class="text-sm text-slate-600 mb-4"><?= t('modules.formas_pagamento.sections.billing_fees_desc') ?></p>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Taxa Fixa -->
                <div class="md:col-span-4 form-input-group">
                    <label for="taxa_fixa" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.fixed_fee_total') ?> {!! aviso(t('modules.formas_pagamento.fields.fixed_fee_total_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="taxa_fixa" name="taxa_fixa" class="form-input-group-field input-moeda pl-12" placeholder="0,00">
                    </div>
                </div>

                <!-- Taxa Fixa por Parcela -->
                <div class="md:col-span-4 form-input-group">
                    <label for="taxa_fixa_parcela" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.fixed_fee_installment') ?> {!! aviso(t('modules.formas_pagamento.fields.fixed_fee_installment_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="taxa_fixa_parcela" name="taxa_fixa_parcela" class="form-input-group-field input-moeda pl-12" placeholder="0,00">
                    </div>
                </div>

                <!-- Taxa Percentual por Parcela -->
                <div class="md:col-span-4 form-input-group">
                    <label for="taxa_percentual_parcela" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.percent_fee_installment') ?> {!! aviso(t('modules.formas_pagamento.fields.percent_fee_installment_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <input type="text" id="taxa_percentual_parcela" name="taxa_percentual_parcela" class="form-input-group-field input-percent" placeholder="0,00">
                        <span class="input-addon">%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao: Desconto por Antecipacao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-gift mr-2"></i><?= t('modules.formas_pagamento.sections.early_discount') ?></h3>
            <p class="text-sm text-slate-600 mb-4"><?= t('modules.formas_pagamento.sections.early_discount_desc') ?></p>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Dias antes do vencimento -->
                <div class="md:col-span-4 form-input-group">
                    <label for="desconto_antecipacao_dias" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.days_before_due') ?> {!! aviso(t('modules.formas_pagamento.fields.days_before_due_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <input type="number" id="desconto_antecipacao_dias" name="desconto_antecipacao_dias" class="form-input-group-field" min="0" max="365" placeholder="0">
                        <span class="input-addon"><?= t('common.labels.days') ?></span>
                    </div>
                </div>

                <!-- Percentual de desconto -->
                <div class="md:col-span-4 form-input-group">
                    <label for="desconto_antecipacao_percentual" class="form-label-group">
                        <?= t('modules.formas_pagamento.fields.discount_percent') ?> {!! aviso(t('modules.formas_pagamento.fields.discount_percent_hint')) !!}
                    </label>
                    <div class="input-group-with-addon">
                        <input type="text" id="desconto_antecipacao_percentual" name="desconto_antecipacao_percentual" class="form-input-group-field input-percent" placeholder="0,00">
                        <span class="input-addon">%</span>
                    </div>
                </div>
            </div>

            <!-- Exemplo -->
            <div id="exemploDesconto" class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200 hidden">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong><?= t('modules.formas_pagamento.discount_example.label') ?></strong> <span id="exemploDescontoTexto"></span>
                </p>
            </div>
        </div>

        <!-- Botoes -->
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

<style>
    .input-group-with-addon {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-group-with-addon .input-addon {
        position: absolute;
        right: 12px;
        color: #64748b;
        font-size: 0.875rem;
        pointer-events: none;
    }

    .input-group-with-addon .input-addon-left {
        position: absolute;
        left: 12px;
        color: #64748b;
        font-size: 0.875rem;
        pointer-events: none;
    }

    .input-group-with-addon input {
        padding-right: 40px;
    }

    .input-group-with-addon input.pl-12 {
        padding-left: 40px;
    }
</style>
@endsection

@section('scripts')
<script>
    (function() {
        const i18n = {
            newTitle: '<?= addslashes(t('modules.formas_pagamento.new_title')) ?>',
            editTitle: '<?= addslashes(t('modules.formas_pagamento.edit_title')) ?>',
            notFound: '<?= addslashes(t('modules.formas_pagamento.messages.not_found')) ?>',
            loadError: '<?= addslashes(t('modules.formas_pagamento.messages.load_error')) ?>',
            nameRequired: '<?= addslashes(t('modules.formas_pagamento.messages.name_required')) ?>',
            branchesRequired: '<?= addslashes(t('modules.formas_pagamento.messages.branches_required')) ?>',
            saveSuccess: '<?= addslashes(t('modules.formas_pagamento.messages.save_success')) ?>',
            saveError: '<?= addslashes(t('modules.formas_pagamento.messages.save_error')) ?>',
            saving: '<?= addslashes(t('modules.formas_pagamento.messages.saving')) ?>',
            selectBranches: '<?= addslashes(t('modules.formas_pagamento.dropdowns.select_branches')) ?>',
            loadingBranches: '<?= addslashes(t('modules.formas_pagamento.dropdowns.loading_branches')) ?>',
            errorLoadingBranches: '<?= addslashes(t('modules.formas_pagamento.dropdowns.error_loading_branches')) ?>',
            errorLoading: '<?= addslashes(t('modules.formas_pagamento.dropdowns.error_loading')) ?>',
            noBranches: '<?= addslashes(t('modules.formas_pagamento.dropdowns.no_branches')) ?>',
            noBranchesShort: '<?= addslashes(t('modules.formas_pagamento.dropdowns.no_branches_short')) ?>',
            noGatewaySelected: '<?= addslashes(t('modules.formas_pagamento.dropdowns.no_gateway_selected')) ?>',
            loadingGateways: '<?= addslashes(t('modules.formas_pagamento.dropdowns.loading_gateways')) ?>',
            errorLoadingGateways: '<?= addslashes(t('modules.formas_pagamento.dropdowns.error_loading_gateways')) ?>',
            noGateways: '<?= addslashes(t('modules.formas_pagamento.dropdowns.no_gateways')) ?>',
            noGatewaysAvailable: '<?= addslashes(t('modules.formas_pagamento.dropdowns.no_gateways_available')) ?>',
            noActiveGateways: '<?= addslashes(t('modules.formas_pagamento.dropdowns.no_active_gateways')) ?>',
            select: '<?= addslashes(t('modules.formas_pagamento.dropdowns.select')) ?>',
            discountExample: '<?= addslashes(t('modules.formas_pagamento.discount_example.text')) ?>',
            whereSite: '<?= addslashes(t('modules.formas_pagamento.where_options.site')) ?>',
            whereSystem: '<?= addslashes(t('modules.formas_pagamento.where_options.system')) ?>',
            whereApp: '<?= addslashes(t('modules.formas_pagamento.where_options.app')) ?>',
            whereAll: '<?= addslashes(t('modules.formas_pagamento.where_options.all')) ?>',
            save: '<?= addslashes(t('common.buttons.save')) ?>',
        };

        let registroId = null;
        let ondeExibirSelecionados = ['2'];
        let dropdownOndeExibirAberto = false;

        // Estado filiais
        let filiaisDisponiveis = [];
        let filiaisSelecionadas = [];
        let dropdownFiliaisAberto = false;

        // Estado gateways
        let gatewaysDisponiveis = [];
        let gatewaysSelecionados = [];
        let dropdownGatewaysAberto = false;

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

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===== FILIAIS =====

        async function carregarFiliais() {
            const dropdownMenu = document.getElementById('filiaisDropdownMenu');
            const dropdownText = document.getElementById('filiaisDropdownText');

            try {
                const result = await API.get('/api/matrizes-filiais/buscar');

                if (result.success && result.data) {
                    filiaisDisponiveis = result.data;
                    renderizarFiliais();
                } else {
                    dropdownMenu.innerHTML = `<div class="filiais-dropdown-error">${i18n.errorLoadingBranches}</div>`;
                    dropdownText.textContent = i18n.errorLoading;
                }
            } catch (error) {
                console.error('Erro ao carregar filiais:', error);
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-error">${i18n.errorLoadingBranches}</div>`;
                dropdownText.textContent = i18n.errorLoading;
            }
        }

        function renderizarFiliais() {
            const dropdownMenu = document.getElementById('filiaisDropdownMenu');
            const dropdownText = document.getElementById('filiaisDropdownText');

            if (filiaisDisponiveis.length === 0) {
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-empty">${i18n.noBranches}</div>`;
                dropdownText.textContent = i18n.noBranchesShort;
                return;
            }

            let html = '';
            filiaisDisponiveis.forEach((filial) => {
                const isChecked = filiaisSelecionadas.includes(parseInt(filial.id));

                html += `
                    <div class="filial-item ${isChecked ? 'selected' : ''}" data-id="${filial.id}" data-nome="${escapeHtml(filial.nome)}">
                        <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                            <input type="checkbox" class="filial-checkbox" value="${filial.id}" ${isChecked ? 'checked' : ''}>
                            <span class="filial-nome">${escapeHtml(filial.nome)}</span>
                        </label>
                    </div>
                `;
            });

            dropdownMenu.innerHTML = html;

            // Event listeners
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
                dropdownText.textContent = i18n.selectBranches;
            } else if (selecionados.length <= 3) {
                dropdownText.textContent = selecionados.join(', ');
            } else {
                dropdownText.textContent = `${selecionados.slice(0, 2).join(', ')} +${selecionados.length - 2}`;
            }
        }

        function atualizarHiddenInputFiliais() {
            document.getElementById('filiaisIdsJson').value = JSON.stringify(filiaisSelecionadas);
        }

        function setFiliaisSelecionadas(filiais) {
            filiaisSelecionadas = filiais.map(f => parseInt(f.id));
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

        // ===== GATEWAYS =====

        async function carregarGateways() {
            const dropdownMenu = document.getElementById('gatewaysDropdownMenu');
            const dropdownText = document.getElementById('gatewaysDropdownText');

            try {
                const result = await API.get('/api/gateways-pagamento?perPage=100');

                if (result.success && result.data) {
                    // Filtrar apenas gateways ativos
                    gatewaysDisponiveis = result.data.filter(g => g.status === 'A');
                    renderizarGateways();
                } else {
                    dropdownMenu.innerHTML = `<div class="filiais-dropdown-empty">${i18n.noGateways}</div>`;
                    dropdownText.textContent = i18n.noGatewaysAvailable;
                }
            } catch (error) {
                console.error('Erro ao carregar gateways:', error);
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-error">${i18n.errorLoadingGateways}</div>`;
                dropdownText.textContent = i18n.errorLoading;
            }
        }

        function renderizarGateways() {
            const dropdownMenu = document.getElementById('gatewaysDropdownMenu');
            const dropdownText = document.getElementById('gatewaysDropdownText');

            if (gatewaysDisponiveis.length === 0) {
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-empty">${i18n.noActiveGateways}</div>`;
                dropdownText.textContent = i18n.noGatewaysAvailable;
                return;
            }

            let html = '';
            gatewaysDisponiveis.forEach((gateway) => {
                const isChecked = gatewaysSelecionados.includes(parseInt(gateway.id));

                html += `
                    <div class="filial-item ${isChecked ? 'selected' : ''}" data-id="${gateway.id}" data-nome="${escapeHtml(gateway.nome)}">
                        <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                            <input type="checkbox" class="gateway-checkbox" value="${gateway.id}" ${isChecked ? 'checked' : ''}>
                            <span class="filial-nome">${escapeHtml(gateway.nome)}</span>
                        </label>
                    </div>
                `;
            });

            dropdownMenu.innerHTML = html;

            // Event listeners
            dropdownMenu.querySelectorAll('.gateway-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleGatewayCheckboxChange);
            });

            atualizarTextoDropdownGateways();
        }

        function handleGatewayCheckboxChange(e) {
            const checkbox = e.target;
            const gatewayItem = checkbox.closest('.filial-item');
            const gatewayId = parseInt(checkbox.value);

            if (checkbox.checked) {
                gatewayItem.classList.add('selected');
                if (!gatewaysSelecionados.includes(gatewayId)) {
                    gatewaysSelecionados.push(gatewayId);
                }
            } else {
                gatewayItem.classList.remove('selected');
                gatewaysSelecionados = gatewaysSelecionados.filter(id => id !== gatewayId);
            }

            atualizarTextoDropdownGateways();
            atualizarHiddenInputGateways();
        }

        function atualizarTextoDropdownGateways() {
            const dropdownText = document.getElementById('gatewaysDropdownText');
            const selecionados = Array.from(document.querySelectorAll('.gateway-checkbox:checked'))
                .map(cb => cb.closest('.filial-item').dataset.nome);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.noGatewaySelected;
            } else if (selecionados.length <= 3) {
                dropdownText.textContent = selecionados.join(', ');
            } else {
                dropdownText.textContent = `${selecionados.slice(0, 2).join(', ')} +${selecionados.length - 2}`;
            }
        }

        function atualizarHiddenInputGateways() {
            document.getElementById('gatewaysIdsJson').value = JSON.stringify(gatewaysSelecionados);
        }

        function setGatewaysSelecionados(gateways) {
            gatewaysSelecionados = gateways.map(g => parseInt(g.id));
            renderizarGateways();
            atualizarHiddenInputGateways();
        }

        function configurarDropdownGateways() {
            const dropdown = document.getElementById('gatewaysDropdown');
            const trigger = document.getElementById('gatewaysDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownGatewaysAberto = !dropdownGatewaysAberto;
                dropdown.classList.toggle('open', dropdownGatewaysAberto);
            });
        }

        // Carregar registro para edicao
        async function carregarRegistro(id) {
            try {
                const result = await API.get(`/api/formas-pagamento/${id}`);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    mostrarAlerta(result.message || i18n.notFound);
                    navegarPara('/pages/formas-pagamento');
                }
            } catch (error) {
                console.error('Erro ao carregar registro:', error);
                mostrarAlerta(i18n.loadError);
                navegarPara('/pages/formas-pagamento');
            }
        }

        function preencherFormulario(dados) {
            document.getElementById('registroId').value = dados.id || '';
            document.getElementById('nome').value = dados.nome || '';
            document.getElementById('status').checked = dados.status === 'A';
            document.getElementById('lancar_pago').checked = dados.lancar_pago === 'S';

            // Onde Exibir - dropdown
            const valores = (dados.onde_exibir || '2').split(',');
            setOndeExibirSelecionados(valores);

            // Filiais
            if (dados.filiais && dados.filiais.length > 0) {
                setFiliaisSelecionadas(dados.filiais);
            }

            // Gateways
            if (dados.gateways && dados.gateways.length > 0) {
                setGatewaysSelecionados(dados.gateways);
            }

            // Multa e Juros (porcentagem)
            Percent.setValue(document.getElementById('multa'), dados.multa || 0);
            Percent.setValue(document.getElementById('juros_por_dia'), dados.juros_por_dia || 0, 3);

            // Taxas (moeda e porcentagem)
            Currency.setValue(document.getElementById('taxa_fixa'), dados.taxa_fixa || 0);
            Currency.setValue(document.getElementById('taxa_fixa_parcela'), dados.taxa_fixa_parcela || 0);
            Percent.setValue(document.getElementById('taxa_percentual_parcela'), dados.taxa_percentual_parcela || 0);

            // Desconto antecipacao
            document.getElementById('desconto_antecipacao_dias').value = dados.desconto_antecipacao_dias || 0;
            Percent.setValue(document.getElementById('desconto_antecipacao_percentual'), dados.desconto_antecipacao_percentual || 0);

            document.getElementById('pageTitle').textContent = i18n.editTitle;
            atualizarExemploDesconto();
        }

        // Atualizar exemplo de desconto
        function atualizarExemploDesconto() {
            const dias = parseInt(document.getElementById('desconto_antecipacao_dias').value) || 0;
            const percentual = Percent.getValue(document.getElementById('desconto_antecipacao_percentual'));

            const exemploDiv = document.getElementById('exemploDesconto');
            const exemploTexto = document.getElementById('exemploDescontoTexto');

            if (dias > 0 && percentual > 0) {
                const valorExemplo = 100;
                const desconto = valorExemplo * (percentual / 100);
                const valorFinal = valorExemplo - desconto;

                exemploTexto.textContent = i18n.discountExample
                    .replace(':days', dias)
                    .replace(':amount', valorExemplo.toFixed(2).replace('.', ','))
                    .replace(':percent', percentual.toFixed(2).replace('.', ','))
                    .replace(':discount', desconto.toFixed(2).replace('.', ','))
                    .replace(':final', valorFinal.toFixed(2).replace('.', ','));
                exemploDiv.classList.remove('hidden');
            } else {
                exemploDiv.classList.add('hidden');
            }
        }

        // Salvar
        async function salvar(event) {
            event.preventDefault();

            const btnSalvar = document.getElementById('btnSalvar');

            // Validar nome obrigatorio
            if (!document.getElementById('nome').value.trim()) {
                mostrarAlerta(i18n.nameRequired);
                document.getElementById('nome').focus();
                return;
            }

            // Validar filiais obrigatorias
            if (filiaisSelecionadas.length === 0) {
                mostrarAlerta(i18n.branchesRequired);
                return;
            }

            btnSalvar.disabled = true;
            btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

            try {
                const dados = {
                    nome: document.getElementById('nome').value.trim(),
                    onde_exibir: ondeExibirSelecionados.join(',') || '2',
                    status: document.getElementById('status').checked ? 'A' : 'I',
                    lancar_pago: document.getElementById('lancar_pago').checked ? 'S' : 'N',
                    multa: Percent.getValue(document.getElementById('multa')),
                    juros_por_dia: Percent.getValue(document.getElementById('juros_por_dia')),
                    taxa_fixa: Currency.getValue(document.getElementById('taxa_fixa')),
                    taxa_fixa_parcela: Currency.getValue(document.getElementById('taxa_fixa_parcela')),
                    taxa_percentual_parcela: Percent.getValue(document.getElementById('taxa_percentual_parcela')),
                    desconto_antecipacao_dias: parseInt(document.getElementById('desconto_antecipacao_dias').value) || 0,
                    desconto_antecipacao_percentual: Percent.getValue(document.getElementById('desconto_antecipacao_percentual')),
                    filiais_ids: JSON.stringify(filiaisSelecionadas),
                    gateways_ids: JSON.stringify(gatewaysSelecionados)
                };

                let url = '/formas-pagamento/salvar';
                if (registroId) {
                    url = `/formas-pagamento/${registroId}/atualizar`;
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    mostrarAlerta(result.message || i18n.saveSuccess, () => {
                        navegarPara('/pages/formas-pagamento');
                    });
                } else {
                    mostrarAlerta(result.message || i18n.saveError);
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(i18n.saveError);
            } finally {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.save}`;
            }
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
                alert(mensagem);
                if (callbackAction) callbackAction();
            }
        }

        // ===== ONDE EXIBIR =====

        function configurarDropdownOndeExibir() {
            const dropdown = document.getElementById('ondeExibirDropdown');
            const trigger = document.getElementById('ondeExibirDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownOndeExibirAberto = !dropdownOndeExibirAberto;
                dropdown.classList.toggle('open', dropdownOndeExibirAberto);
            });

            // Event listeners para checkboxes
            document.querySelectorAll('.onde-exibir-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleOndeExibirCheckboxChange);
            });

            atualizarTextoDropdownOndeExibir();
        }

        function handleOndeExibirCheckboxChange(e) {
            const checkbox = e.target;
            const item = checkbox.closest('.filial-item');
            const valor = checkbox.value;

            if (checkbox.checked) {
                item.classList.add('selected');
                if (!ondeExibirSelecionados.includes(valor)) {
                    ondeExibirSelecionados.push(valor);
                }
            } else {
                item.classList.remove('selected');
                ondeExibirSelecionados = ondeExibirSelecionados.filter(v => v !== valor);
            }

            atualizarTextoDropdownOndeExibir();
        }

        function atualizarTextoDropdownOndeExibir() {
            const dropdownText = document.getElementById('ondeExibirDropdownText');
            const nomes = {
                '1': i18n.whereSite,
                '2': i18n.whereSystem,
                '3': i18n.whereApp
            };

            const selecionados = ondeExibirSelecionados.map(v => nomes[v] || v);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.select;
            } else if (selecionados.length === 3) {
                dropdownText.textContent = i18n.whereAll;
            } else {
                dropdownText.textContent = selecionados.join(', ');
            }
        }

        function setOndeExibirSelecionados(valores) {
            ondeExibirSelecionados = valores;
            document.querySelectorAll('.onde-exibir-checkbox').forEach(checkbox => {
                const isChecked = valores.includes(checkbox.value);
                checkbox.checked = isChecked;
                checkbox.closest('.filial-item').classList.toggle('selected', isChecked);
            });
            atualizarTextoDropdownOndeExibir();
        }

        // Fechar dropdowns ao clicar fora
        document.addEventListener('click', function(e) {
            const dropdownOndeExibir = document.getElementById('ondeExibirDropdown');
            const dropdownFiliais = document.getElementById('filiaisDropdown');
            const dropdownGateways = document.getElementById('gatewaysDropdown');

            if (dropdownOndeExibir && !dropdownOndeExibir.contains(e.target)) {
                dropdownOndeExibirAberto = false;
                dropdownOndeExibir.classList.remove('open');
            }

            if (dropdownFiliais && !dropdownFiliais.contains(e.target)) {
                dropdownFiliaisAberto = false;
                dropdownFiliais.classList.remove('open');
            }

            if (dropdownGateways && !dropdownGateways.contains(e.target)) {
                dropdownGatewaysAberto = false;
                dropdownGateways.classList.remove('open');
            }
        });

        // Event listeners
        document.getElementById('formPrincipal')?.addEventListener('submit', salvar);

        document.getElementById('btnVoltar')?.addEventListener('click', function() {
            navegarPara('/pages/formas-pagamento');
        });

        document.getElementById('btnCancelar')?.addEventListener('click', function() {
            navegarPara('/pages/formas-pagamento');
        });

        // Atualizar exemplo quando mudar campos de desconto
        document.getElementById('desconto_antecipacao_dias')?.addEventListener('input', atualizarExemploDesconto);
        document.getElementById('desconto_antecipacao_percentual')?.addEventListener('input', atualizarExemploDesconto);

        // Inicializacao
        configurarDropdownOndeExibir();
        configurarDropdownFiliais();
        configurarDropdownGateways();

        // Carregar filiais e gateways, depois verificar se estamos editando
        Promise.all([carregarFiliais(), carregarGateways()]).then(() => {
            const urlParams = new URLSearchParams(window.location.search);
            registroId = urlParams.get('id');

            if (registroId) {
                carregarRegistro(registroId);
            }
        });
    })();
</script>
@endsection
