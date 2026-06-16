@extends('layouts.iframe')

@section('title', t('modules.contratos.title_singular'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="title-page" id="pageTitle"><?= t('modules.contratos.edit_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formContrato" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id" value="<?= $contrato['id'] ?? '' ?>">
        <input type="hidden" id="contratoStatus" name="status" value="<?= $contrato['status'] ?? 'A' ?>">

        <!-- Cabecalho do Contrato -->
        <div class="form-section mb-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Filial (read-only) -->
                <div class="md:col-span-3 form-input-group">
                    <label for="id_matriz_filial_retirada" class="form-label-group">
                        <?= t('modules.contratos.fields.branch') ?> <i class="fas fa-lock text-slate-400 text-xs ml-1" title="<?= t('modules.contratos.fields.locked_field') ?>"></i>
                    </label>
                    <select id="id_matriz_filial_retirada" name="id_matriz_filial_retirada" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('common.labels.select_branch') ?>" disabled>
                        <option value=""><?= t('common.labels.select') ?></option>
                    </select>
                    <input type="hidden" name="id_matriz_filial_retirada" value="<?= $contrato['id_matriz_filial_retirada'] ?? '' ?>">
                </div>

                <!-- Data Inicio (read-only) -->
                <div class="md:col-span-2 form-input-group">
                    <label for="data_ini" class="form-label-group">
                        <?= t('modules.contratos.fields.start_date') ?> <i class="fas fa-lock text-slate-400 text-xs ml-1" title="<?= t('modules.contratos.fields.locked_field') ?>"></i>
                    </label>
                    <input type="datetime-local" id="data_ini" name="data_ini" class="form-input-group-field bg-slate-100" disabled>
                    <input type="hidden" name="data_ini" value="<?= $contrato['data_ini'] ?? '' ?>">
                </div>

                <!-- Data Fim (editavel) -->
                <div class="md:col-span-2 form-input-group">
                    <label for="data_fim" class="form-label-group">
                        <?= t('modules.contratos.fields.end_date') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="data_fim" name="data_fim" class="form-input-group-field" required>
                </div>

                <!-- Contagem -->
                <div class="md:col-span-2 form-input-group">
                    <label for="contagem" class="form-label-group"><?= t('modules.contratos.fields.billing_cycle') ?></label>
                    <select id="contagem" name="contagem" class="form-input-group-field">
                        <option value="dia"><?= t('modules.contratos.billing_cycles.day') ?></option>
                        <option value="semana"><?= t('modules.contratos.billing_cycles.week') ?></option>
                        <option value="mes"><?= t('modules.contratos.billing_cycles.month') ?></option>
                        <option value="ano"><?= t('modules.contratos.billing_cycles.year') ?></option>
                    </select>
                </div>

                <!-- Dias/Periodos (editavel) -->
                <div class="md:col-span-1 form-input-group">
                    <label for="dias" class="form-label-group" id="labelDias"><?= t('modules.contratos.period_labels.day') ?> <span class="text-red-500">*</span></label>
                    <input type="number" id="dias" name="dias" class="form-input-group-field" min="1" required>
                </div>

                <!-- Auto Renovacao (editavel) -->
                <div class="md:col-span-2 form-input-group">
                    <label for="auto_renovacao" class="form-label-group"><?= t('modules.contratos.fields.auto_renewal') ?></label>
                    <select id="auto_renovacao" name="auto_renovacao" class="form-input-group-field">
                        <option value="" selected><?= t('modules.contratos.auto_renewal_options.disabled') ?></option>
                        <option value="auto"><?= t('modules.contratos.auto_renewal_options.until_return') ?></option>
                        <option value="1">1x</option>
                        <option value="2">2x</option>
                        <option value="3">3x</option>
                        <option value="4">4x</option>
                        <option value="5">5x</option>
                        <option value="6">6x</option>
                        <option value="7">7x</option>
                        <option value="8">8x</option>
                        <option value="9">9x</option>
                        <option value="10">10x</option>
                        <option value="11">11x</option>
                        <option value="12">12x</option>
                    </select>
                </div>
            </div>

            <!-- Data Renovacao (aparece se auto_renovacao != vazio) -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-3" id="divDataRenovacao" style="display: none;">
                <div class="md:col-span-2 md:col-start-6 form-input-group">
                    <label for="data_renovacao" class="form-label-group"><?= t('modules.contratos.fields.renewal_date') ?> {!! aviso(t('modules.contratos.renewal_warning')) !!}</label>
                    <input type="date" id="data_renovacao" name="data_renovacao" class="form-input-group-field">
                </div>
            </div>
        </div>

        <!-- Navegacao de Abas -->
        <div class="mb-4 border-b border-slate-300 overflow-x-auto overflow-y-hidden">
            <nav class="flex -mb-px whitespace-nowrap" id="formTabsNav">
                <button type="button" data-form-tab-target="#tabCliente" class="form-tab-button active">
                    <i class="fas fa-user mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.client') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabVeiculos" class="form-tab-button">
                    <i class="fas fa-car mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.vehicles') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabCondutor" class="form-tab-button">
                    <i class="fas fa-id-card mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.driver') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabFiador" class="form-tab-button">
                    <i class="fas fa-user-shield mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.guarantor') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabAvalista" class="form-tab-button">
                    <i class="fas fa-user-check mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.endorser') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabTestemunhas" class="form-tab-button">
                    <i class="fas fa-users mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.witnesses') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabTaxas" class="form-tab-button">
                    <i class="fas fa-receipt mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.fees') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabFinanceiro" class="form-tab-button">
                    <i class="fas fa-dollar-sign mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.financial') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabObs" class="form-tab-button">
                    <i class="fas fa-sticky-note mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.notes') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabResumo" class="form-tab-button">
                    <i class="fas fa-file-invoice-dollar mr-1"></i><span class="hidden sm:inline"><?= t('modules.contratos.tabs.summary') ?></span>
                </button>
            </nav>
        </div>

        <!-- ================== ABA 1: CLIENTE ================== -->
        <div id="tabCliente" class="form-tab-content active">
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-user mr-2"></i><?= t('modules.contratos.sections.client_data') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-8 form-input-group">
                        <label for="id_cliente" class="form-label-group">
                            <?= t('modules.contratos.fields.client') ?> <span class="text-red-500">*</span>
                        </label>
                        <select id="id_cliente" name="id_cliente" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= t('common.labels.type_name_or_cpf') ?>" required>
                            <option value=""><?= t('common.labels.select') ?></option>
                            <?php if (!empty($contrato['id_cliente'])): ?>
                                <option value="<?= $contrato['id_cliente'] ?>" selected><?= htmlspecialchars($contrato['cliente_nome'] ?? '') ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 form-input-group flex items-end">
                        <button type="button" id="btnNovoCliente" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium w-full">
                            <i class="fas fa-plus mr-2"></i><?= t('modules.contratos.buttons.new_client') ?>
                        </button>
                    </div>
                </div>

                <!-- Dados do cliente selecionado -->
                <div id="dadosClienteSelecionado" class="mt-4 p-4 bg-slate-50 rounded-md hidden">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.contratos.fields.document') ?></span>
                            <p class="font-medium" id="clienteDocumento">-</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.contratos.fields.phone') ?></span>
                            <p class="font-medium" id="clienteTelefone">-</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.contratos.fields.email') ?></span>
                            <p class="font-medium" id="clienteEmail">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== ABA 2: VEICULOS ================== -->
        <div id="tabVeiculos" class="form-tab-content">
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-car mr-2"></i><?= t('modules.contratos.sections.contract_vehicles') ?></h3>
                    <button type="button" id="btnAdicionarVeiculo" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>

                <!-- Cabecalho da Lista -->
                <div id="cabecalhoVeiculos" class="hidden">
                    <div class="grid grid-cols-12 gap-2 px-4 py-2 bg-slate-300 rounded-t-md text-xs font-semibold text-slate-600 uppercase tracking-wide">
                        <div class="col-span-2"><?= t('modules.contratos.vehicles.header_plan') ?></div>
                        <div class="col-span-3"><?= t('modules.contratos.vehicles.header_group') ?></div>
                        <div class="col-span-5"><?= t('modules.contratos.vehicles.header_vehicle') ?></div>
                        <div class="col-span-2 text-center"><?= t('modules.contratos.vehicles.header_actions') ?></div>
                    </div>
                </div>

                <!-- Lista de Veiculos -->
                <div id="listaVeiculos">
                    <p class="text-slate-500 text-center py-4"><?= t('modules.contratos.vehicles.no_vehicles') ?></p>
                </div>
            </div>
        </div>

        <!-- ================== ABA 3: CONDUTOR ADICIONAL ================== -->
        <div id="tabCondutor" class="form-tab-content">
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-id-card mr-2"></i><?= t('modules.contratos.sections.additional_driver') ?></h3>
                    <button type="button" id="btnAdicionarCondutor" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.contratos.sections.additional_driver_hint') ?></p>

                <div id="listaCondutores">
                    <!-- Condutores serao adicionados aqui -->
                </div>
            </div>
        </div>

        <!-- ================== ABA 4: FIADOR ================== -->
        <div id="tabFiador" class="form-tab-content">
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-user-shield mr-2"></i><?= t('modules.contratos.sections.guarantors') ?></h3>
                    <button type="button" id="btnAdicionarFiador" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.contratos.sections.guarantors_hint') ?></p>

                <div id="listaFiadores">
                    <!-- Fiadores serao adicionados aqui -->
                </div>
            </div>
        </div>

        <!-- ================== ABA 5: AVALISTA ================== -->
        <div id="tabAvalista" class="form-tab-content">
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-user-check mr-2"></i><?= t('modules.contratos.sections.endorsers') ?></h3>
                    <button type="button" id="btnAdicionarAvalista" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.contratos.sections.endorsers_hint') ?></p>

                <div id="listaAvalistas">
                    <!-- Avalistas serao adicionados aqui -->
                </div>
            </div>
        </div>

        <!-- ================== ABA 6: TESTEMUNHAS ================== -->
        <div id="tabTestemunhas" class="form-tab-content">
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-users mr-2"></i><?= t('modules.contratos.sections.witnesses') ?></h3>
                    <button type="button" id="btnAdicionarTestemunha" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.contratos.sections.witnesses_hint') ?></p>

                <div id="listaTestemunhas">
                    <!-- Testemunhas serao adicionadas aqui -->
                </div>
            </div>
        </div>

        <!-- ================== ABA 7: TAXAS E SERVICOS ================== -->
        <div id="tabTaxas" class="form-tab-content">
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-receipt mr-2"></i><?= t('modules.contratos.sections.fees_services') ?></h3>

                <div class="bg-slate-50 p-4 rounded-md mb-3">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.fees.fee_service') ?></label>
                            <select id="taxa_select"
                                    class="chosen-select form-input-group-field"
                                    data-chosen-type="server-side"
                                    data-chosen-search-url="/api/taxas-e-servicos/buscar"
                                    data-chosen-placeholder="<?= t('common.labels.type_to_search') ?>"
                                    data-chosen-min-chars="2">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.fees.name') ?></label>
                            <input type="text" id="taxa_nome" class="form-input-group-field bg-slate-100" placeholder="<?= t('modules.contratos.fees.name') ?>" readonly>
                        </div>
                        <div class="md:col-span-1 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.fees.qty') ?></label>
                            <input type="number" id="taxa_qtd" class="form-input-group-field bg-slate-100" value="1" min="1" readonly>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.fees.unit_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                <input type="text" id="taxa_valor" class="form-input-group-field pl-8 input-moeda bg-slate-100" readonly>
                            </div>
                        </div>
                        <div class="md:col-span-2 form-input-group flex items-end">
                            <button type="button" id="btnAdicionarTaxa" class="btn-secondary py-2 px-3 rounded-md text-sm font-medium w-full">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="listaTaxas" class="space-y-2">
                    <!-- Taxas serao listadas aqui -->
                </div>
            </div>
        </div>

        <!-- ================== ABA 8: FINANCEIRO (modo edicao) ================== -->
        <div id="tabFinanceiro" class="form-tab-content">
            <!-- SECAO 1: RESUMO FINANCEIRO -->
            <div id="secaoResumoFinanceiro" class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-chart-pie mr-2"></i><?= t('modules.contratos.sections.financial_summary') ?></h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-slate-50 rounded-lg p-4 text-center">
                        <div class="text-xs text-slate-500 uppercase tracking-wide mb-1"><?= t('modules.contratos.financial.total_contract') ?></div>
                        <div id="resumoTotalContrato" class="text-xl font-bold text-slate-700">R$ 0,00</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-xs text-green-600 uppercase tracking-wide mb-1"><?= t('modules.contratos.financial.paid') ?></div>
                        <div id="resumoTotalPago" class="text-xl font-bold text-green-600">R$ 0,00</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-xs text-yellow-600 uppercase tracking-wide mb-1"><?= t('modules.contratos.financial.pending') ?></div>
                        <div id="resumoTotalPendente" class="text-xl font-bold text-yellow-600">R$ 0,00</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4 text-center">
                        <div class="text-xs text-red-600 uppercase tracking-wide mb-1"><?= t('modules.contratos.financial.overdue') ?></div>
                        <div id="resumoTotalAtrasado" class="text-xl font-bold text-red-600">R$ 0,00</div>
                    </div>
                </div>
                <!-- Aviso de diferenca -->
                <div id="avisoFinanceiroDiferenca" class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg hidden">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-amber-500"></i>
                        <span class="text-sm text-amber-700" id="spanDiferencaTexto">
                            <strong><?= t('common.labels.attention') ?>:</strong> <?= t('modules.contratos.financial.difference_warning', ['amount' => '<span id="valorDiferenca">R$ 0,00</span>']) ?>
                        </span>
                        <button type="button" id="btnResolverDiferenca" class="ml-auto btn-primary text-xs py-1 px-3">
                            <?= t('modules.contratos.financial.resolve') ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SECAO 2: CONFIGURACAO DE PAGAMENTO (colapsavel, maioria read-only) -->
            <div class="form-section mb-4">
                <div class="flex justify-between items-center cursor-pointer" id="toggleConfigPagamento">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-cog mr-2"></i><?= t('modules.contratos.sections.payment_config') ?></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fas fa-chevron-down" id="iconConfigPagamento"></i>
                    </button>
                </div>

                <div id="conteudoConfigPagamento" class="mt-4 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group">
                                <?= t('modules.contratos.fields.bank_account') ?> <i class="fas fa-lock text-slate-400 text-xs ml-1"></i>
                            </label>
                            <input type="text" id="configContaDisplay" class="form-input-group-field bg-slate-100 text-sm" readonly>
                            <input type="hidden" id="id_conta" name="id_conta" value="<?= $contrato['id_conta'] ?? '' ?>">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label for="id_forma_pagamento" class="form-label-group">
                                <?= t('modules.contratos.fields.payment_method') ?>
                            </label>
                            <select id="id_forma_pagamento" name="id_forma_pagamento" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/formas-pagamento/select" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                            <p class="text-xs text-slate-400 mt-1"><?= t('modules.contratos.messages.payment_method_renewal_hint') ?></p>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label for="id_comando_parcela" class="form-label-group">
                                <?= t('modules.contratos.fields.installment_command') ?>
                            </label>
                            <select id="id_comando_parcela" name="id_comando_parcela" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/comandos-parcelas/select" data-chosen-min-chars="2" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group">
                                <?= t('modules.contratos.fields.first_due_date') ?> <i class="fas fa-lock text-slate-400 text-xs ml-1"></i>
                            </label>
                            <input type="date" id="primeiro_vencimento" class="form-input-group-field bg-slate-100 text-sm" readonly>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group">
                                <?= t('modules.contratos.fields.discount') ?> <i class="fas fa-lock text-slate-400 text-xs ml-1"></i>
                            </label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="valor_desconto" class="form-input-group-field pl-10 bg-slate-100 text-sm" readonly>
                            </div>
                            <input type="hidden" name="valor_desconto" value="<?= $contrato['valor_desconto'] ?? '0' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECAO 3: LANCAMENTOS / PARCELAS -->
            <div id="secaoParcelasGeradas" class="form-section mb-4">
                <h3 class="form-section-title">
                    <i class="fas fa-list-ol mr-2"></i><?= t('modules.contratos.sections.installments') ?>
                    <span id="qtdParcelas" class="ml-2 bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full">0</span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 uppercase text-xs">
                                <th class="px-3 py-2 text-center w-12"><?= t('modules.contratos.financial.installment_header_num') ?></th>
                                <th class="px-3 py-2 text-left"><?= t('modules.contratos.financial.installment_header_account') ?></th>
                                <th class="px-3 py-2 text-left"><?= t('modules.contratos.financial.installment_header_method') ?></th>
                                <th class="px-3 py-2 text-center w-28"><?= t('modules.contratos.financial.installment_header_due_date') ?></th>
                                <th class="px-3 py-2 text-right w-28"><?= t('modules.contratos.financial.installment_header_value') ?></th>
                                <th class="px-3 py-2 text-center"><?= t('modules.contratos.financial.installment_header_status') ?></th>
                                <th class="px-3 py-2 text-center w-20"><?= t('modules.contratos.financial.installment_header_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tabelaParcelasBody">
                            <!-- Parcelas serao inseridas via JS -->
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 font-semibold">
                                <td colspan="4" class="px-3 py-2 text-right"><?= t('modules.contratos.financial.total_label') ?></td>
                                <td id="totalParcelas" class="px-3 py-2 text-right">R$ 0,00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Estado vazio (sem parcelas) -->
                <div id="semParcelas" class="text-center py-8 text-slate-400 hidden">
                    <i class="fas fa-receipt text-4xl mb-3"></i>
                    <p class="text-sm"><?= t('modules.contratos.financial.no_installments') ?></p>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="button" id="btnAdicionarParcelaAvulsa" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-plus mr-2"></i><?= t('modules.contratos.financial.add_single_installment') ?>
                    </button>
                    <button type="button" id="btnRegenerarPendentes" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center hidden">
                        <i class="fas fa-sync-alt mr-2"></i><?= t('modules.contratos.financial.regenerate_pending') ?>
                    </button>
                </div>
            </div>

            <!-- Campo oculto para armazenar parcelas (JSON) -->
            <input type="hidden" id="parcelasJson" name="parcelas_json" value="[]">

            <!-- SECAO: BLOQUEIO (Pre-autorizacao no Cartao) -->
            <div id="secaoBloqueio" class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-lock mr-2"></i><?= t('modules.contratos.block.title') ?></h3>

                <!-- Aviso se nao tem gateway compativel -->
                <div id="bloqueioSemGateway" class="hidden bg-amber-50 border border-amber-200 rounded-md p-3 mb-4">
                    <p class="text-amber-700 text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> <?= t('modules.contratos.block.no_gateway') ?></p>
                </div>

                <div id="bloqueioFormFields">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6 form-input-group">
                            <label for="bloqueio_id_cartao" class="form-label-group"><?= t('modules.contratos.block.card') ?></label>
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <select id="bloqueio_id_cartao" name="bloqueio_id_cartao" class="form-input-group-field chosen-select" data-chosen-type="normal" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                                        <option value=""><?= t('common.labels.select') ?></option>
                                    </select>
                                </div>
                                <button type="button" id="btnAdicionarCartaoBloqueio" class="btn-secondary py-1 px-3 text-xs whitespace-nowrap">
                                    <i class="fas fa-plus mr-1"></i><?= t('modules.contratos.block.add_card') ?>
                                </button>
                            </div>
                            <p id="bloqueioSemCartao" class="text-amber-600 text-xs mt-1 hidden"><i class="fas fa-info-circle mr-1"></i><?= t('modules.contratos.block.no_card') ?></p>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label for="bloqueio_valor" class="form-label-group"><?= t('modules.contratos.block.value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="bloqueio_valor" name="bloqueio_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group flex items-end">
                            <button type="button" id="btnCriarBloqueio" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center justify-center shadow hover:shadow-md transition-shadow w-full" disabled>
                                <i class="fas fa-shield-alt mr-1"></i><?= t('modules.contratos.block.create_hold') ?>
                            </button>
                        </div>
                    </div>

                    <!-- Status do bloqueio ativo -->
                    <div id="bloqueioStatusArea" class="hidden mt-4 p-4 rounded-lg border">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <span id="bloqueioStatusBadge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"></span>
                                <span class="text-sm text-slate-600">
                                    <i class="fas fa-credit-card mr-1"></i>
                                    <span id="bloqueioCartaoInfo"></span>
                                </span>
                                <span class="text-sm font-medium" id="bloqueioValorInfo"></span>
                            </div>
                            <span class="text-xs text-slate-500" id="bloqueioExpiraInfo"></span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" id="btnLiberarBloqueio" class="btn-secondary">
                                <i class="fas fa-unlock mr-1"></i><?= t('modules.contratos.block.release_hold') ?>
                            </button>
                            <button type="button" id="btnToggleCaptura" class="btn-red py-2 px-4 rounded-md text-sm font-medium">
                                <i class="fas fa-hand-holding-usd mr-1"></i><?= t('modules.contratos.block.capture_hold') ?>
                            </button>
                        </div>

                        <!-- Formulario de captura -->
                        <div id="bloqueioCapturarForm" class="hidden mt-4 p-4 rounded-lg border border-red-200 bg-red-50">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3"><i class="fas fa-hand-holding-usd mr-1"></i> <?= t('modules.contratos.block.capture_details') ?></h4>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <div class="md:col-span-3 form-input-group">
                                    <label for="capturaValor" class="form-label-group"><?= t('modules.contratos.block.capture_value') ?></label>
                                    <div class="relative">
                                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                        <input type="text" id="capturaValor" class="form-input-group-field pl-10 input-moeda">
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1"><?= t('modules.contratos.block.capture_max') ?>: <span id="capturaValorMax"></span></p>
                                </div>
                                <div class="md:col-span-4 form-input-group">
                                    <label for="capturaMotivo" class="form-label-group"><?= t('modules.contratos.block.capture_reason') ?></label>
                                    <select id="capturaMotivo" class="form-input-group-field">
                                        <option value="dano"><?= t('modules.contratos.block.reason_damage') ?></option>
                                        <option value="multa"><?= t('modules.contratos.block.reason_fine') ?></option>
                                        <option value="combustivel"><?= t('modules.contratos.block.reason_fuel') ?></option>
                                        <option value="diaria_extra"><?= t('modules.contratos.block.reason_extra_days') ?></option>
                                        <option value="outro"><?= t('modules.contratos.block.reason_other') ?></option>
                                    </select>
                                </div>
                                <div class="md:col-span-3 form-input-group">
                                    <label for="capturaContaBancaria" class="form-label-group"><?= t('modules.contratos.block.account') ?></label>
                                    <select id="capturaContaBancaria" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar" data-chosen-placeholder="<?= t('common.labels.select_account') ?>">
                                        <option value=""><?= t('common.labels.select') ?></option>
                                    </select>
                                </div>
                                <div class="md:col-span-2 form-input-group flex items-end gap-2">
                                    <button type="button" id="btnConfirmarCaptura" class="btn-red py-2 px-4 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow w-full">
                                        <i class="fas fa-check mr-1"></i><?= t('modules.contratos.block.confirm_capture') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== ABA 9: OBSERVACOES ================== -->
        <div id="tabObs" class="form-tab-content">
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-sticky-note mr-2"></i><?= t('modules.contratos.sections.observations') ?></h3>

                <div class="form-input-group">
                    <textarea id="obs" name="obs" class="form-input-group-field" rows="6" placeholder="<?= t('modules.contratos.sections.observations') ?>..."></textarea>
                </div>
            </div>
        </div>

        <!-- ================== ABA 10: RESUMO ================== -->
        <div id="tabResumo" class="form-tab-content">
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-file-invoice-dollar mr-2"></i><?= t('modules.contratos.sections.contract_summary') ?>
                </h3>

                <!-- Tabela estilo fatura -->
                <div class="bg-white border border-slate-200 rounded-md overflow-x-auto">
                    <table id="tabelaResumoFatura" class="w-full text-sm">
                        <colgroup>
                            <col>
                            <col style="width: 80px">
                            <col style="width: 110px">
                            <col style="width: 110px">
                            <col style="width: 160px">
                        </colgroup>
                        <tbody id="resumoFaturaBody">
                            <!-- Conteudo gerado via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Botoes de Acao -->
        <div class="flex justify-end space-x-3 mt-6 mb-4">
<button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>

<!-- Template para Card de Veiculo -->
<template id="templateVeiculoCard">
    <div class="veiculo-item grid grid-cols-12 gap-2 items-center py-3 px-4 bg-slate-50 border-b border-slate-200 hover:bg-slate-100 transition-colors" data-index="">
        <div class="col-span-2">
            <span class="text-sm font-medium veiculo-plano-label px-2 py-1 rounded inline-block">Diaria</span>
        </div>
        <div class="col-span-3">
            <span class="text-sm text-slate-600 veiculo-grupo-label">Grupo</span>
        </div>
        <div class="col-span-5">
            <span class="text-sm font-semibold text-slate-800 veiculo-info">ABC-1234 - Modelo</span>
        </div>
        <div class="col-span-2 flex items-center justify-center gap-2">
            <button type="button" class="btn-icon text-blue-600 hover:text-blue-800 btn-editar-veiculo" title="<?= t('common.buttons.edit') ?>">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-veiculo" title="<?= t('common.buttons.remove') ?>">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</template>

<!-- Template para Condutor Adicional (com CNH) -->
<template id="templateCondutorCard">
    <div class="pessoa-card bg-white border border-slate-200 rounded-md p-4 mb-3" data-index="" data-tipo="condutor">
        <div class="flex justify-between items-center mb-3">
            <span class="text-sm font-medium text-slate-600 pessoa-label"><?= t('modules.contratos.person.conductor_label', ['num' => '1']) ?></span>
            <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-pessoa">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-5 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.full_name') ?></label>
                <select class="form-input-group-field text-sm chosen-select pessoa-select-cliente"
                        data-chosen-type="server-side"
                        data-chosen-search-url="/api/clientes/buscar"
                        data-chosen-placeholder="<?= t('common.labels.type_name_or_cpf') ?>">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.cpf_cnpj') ?></label>
                <input type="text" class="form-input-group-field text-sm pessoa-cpf bg-slate-50" readonly>
            </div>
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.cnh') ?></label>
                <input type="text" class="form-input-group-field text-sm pessoa-cnh bg-slate-50" readonly>
            </div>
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.cnh_validity') ?></label>
                <input type="text" class="form-input-group-field text-sm pessoa-validade bg-slate-50" readonly>
                <small class="text-red-500 text-xs pessoa-cnh-alerta hidden"><?= t('modules.contratos.person.cnh_expired') ?></small>
            </div>
        </div>

        <input type="hidden" class="pessoa-id">
    </div>
</template>

<!-- Template para Fiador/Avalista/Testemunha (sem CNH) -->
<template id="templatePessoaSemCnhCard">
    <div class="pessoa-card bg-white border border-slate-200 rounded-md p-4 mb-3" data-index="" data-tipo="">
        <div class="flex justify-between items-center mb-3">
            <span class="text-sm font-medium text-slate-600 pessoa-label">1</span>
            <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-pessoa">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.full_name') ?></label>
                <select class="form-input-group-field text-sm chosen-select pessoa-select-cliente"
                        data-chosen-type="server-side"
                        data-chosen-search-url="/api/clientes/buscar"
                        data-chosen-placeholder="<?= t('common.labels.type_name_or_cpf') ?>">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="md:col-span-6 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.cpf_cnpj') ?></label>
                <input type="text" class="form-input-group-field text-sm pessoa-cpf bg-slate-50" readonly>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-2">
            <div class="md:col-span-12 form-input-group">
                <label class="form-label-group text-xs"><?= t('modules.contratos.person.address') ?></label>
                <input type="text" class="form-input-group-field text-sm pessoa-endereco bg-slate-50" readonly>
            </div>
        </div>

        <input type="hidden" class="pessoa-id">
    </div>
</template>

<!-- Template para Taxa/Servico -->
<template id="templateTaxaItem">
    <div class="taxa-item flex items-center justify-between bg-white border border-slate-200 rounded-md px-3 py-2" data-index="">
        <div class="flex-1 grid grid-cols-12 gap-2 items-center">
            <span class="col-span-5 text-sm font-medium taxa-nome"><?= t('modules.contratos.fees.name') ?></span>
            <span class="col-span-2 text-sm text-slate-500 taxa-qtd">1x</span>
            <span class="col-span-2 text-sm text-slate-500 taxa-valor-unit">R$ 0,00</span>
            <span class="col-span-2 text-sm font-semibold text-slate-800 taxa-valor-total">R$ 0,00</span>
        </div>
        <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-taxa ml-2">
            <i class="fas fa-times"></i>
        </button>
        <input type="hidden" class="taxa-id">
    </div>
</template>
@endsection

@section('scripts')
<?php
$jsText = static fn(string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$jsT = static fn(string $key, array $replace = []): string => $jsText(t($key, $replace));
?>
<script>
    window.userPermissions = <?php echo json_encode($permissions ?? []); ?>;
    window.contratoData = <?php echo json_encode($contrato ?? null); ?>;
    window.i18n_contratos = <?= json_encode([
        'editTitle' => t('modules.contratos.edit_title'),
        'newTitle' => t('modules.contratos.new_title'),
        'periodLabels' => [
            'dia' => t('modules.contratos.period_labels.day'),
            'semana' => t('modules.contratos.period_labels.week'),
            'mes' => t('modules.contratos.period_labels.month'),
            'ano' => t('modules.contratos.period_labels.year'),
        ],
        'qty' => t('modules.contratos.fees.qty'),
        'billingLabels' => [
            'dia' => t('modules.contratos.period_labels.day'),
            'semana' => t('modules.contratos.period_labels.week'),
            'mes' => t('modules.contratos.period_labels.month'),
            'ano' => t('modules.contratos.period_labels.year'),
        ],
        'editVehicle' => t('modules.contratos.vehicles.edit_vehicle'),
        'addVehicle' => t('modules.contratos.vehicles.add_vehicle'),
        'alreadyAdded' => t('modules.contratos.vehicles.already_added'),
        'noVehicles' => t('modules.contratos.vehicles.no_vehicles'),
        'selectBranchFirst' => t('modules.contratos.messages.select_branch_first'),
        'fillDatesFirst' => t('modules.contratos.messages.fill_dates_first'),
        'planKmFree' => t('modules.contratos.vehicles.plan_km_free'),
        'planKmControlled' => t('modules.contratos.vehicles.plan_km_controlled'),
        'planKmPaid' => t('modules.contratos.vehicles.plan_km_paid'),
        'planKmFreeLabel' => t('modules.contratos.vehicles.plan_km_free_label'),
        'planKmControlledLabel' => t('modules.contratos.vehicles.plan_km_controlled_label'),
        'planKmPaidLabel' => t('modules.contratos.vehicles.plan_km_paid_label'),
        'groupDefault' => t('modules.contratos.vehicles.header_group'),
        'conductorLabel' => t('modules.contratos.person.conductor_label', ['num' => ':num']),
        'guarantorLabel' => t('modules.contratos.person.guarantor_label', ['num' => ':num']),
        'endorserLabel' => t('modules.contratos.person.endorser_label', ['num' => ':num']),
        'witnessLabel' => t('modules.contratos.person.witness_label', ['num' => ':num']),
        'informFeeName' => t('modules.contratos.messages.inform_fee_name'),
        'byPeriod' => t('modules.contratos.fees.by_period'),
        'onTotalValue' => t('modules.contratos.fees.on_total_value'),
        'percentage' => t('modules.contratos.fees.percentage'),
        'noFees' => t('modules.contratos.fees.no_fees'),
        'fixed' => t('modules.contratos.fees.fixed'),
        'summaryVehicles' => t('modules.contratos.summary_section.vehicles'),
        'summaryFeesServices' => t('modules.contratos.summary_section.fees_services'),
        'summaryTotals' => t('modules.contratos.summary_section.totals'),
        'summaryRentalTotal' => t('modules.contratos.summary_section.rental_total'),
        'summaryDiscount' => t('modules.contratos.summary_section.discount_label'),
        'summaryTotalToPay' => t('modules.contratos.summary_section.total_to_pay'),
        'summaryVehicleInsurance' => t('modules.contratos.summary_section.vehicle_insurance'),
        'summaryThirdPartyInsurance' => t('modules.contratos.summary_section.third_party_insurance'),
        'headerVeic' => t('modules.contratos.vehicles.header_veic'),
        'headerValue' => t('modules.contratos.vehicles.header_value'),
        'headerTotal' => t('modules.contratos.vehicles.header_total'),
        'paid' => t('modules.contratos.financial.paid'),
        'pending' => t('modules.contratos.financial.pending'),
        'overdue' => t('modules.contratos.financial.overdue'),
        'saving' => t('modules.contratos.financial.saving'),
        'selectClient' => t('modules.contratos.messages.select_client'),
        'addAtLeastOneVehicle' => t('modules.contratos.messages.add_at_least_one_vehicle'),
        'feeNotAdded' => t('modules.contratos.messages.fee_not_added'),
        'endDateAfterStart' => t('modules.contratos.messages.end_date_after_start'),
        'generateInstallmentsFirst' => t('modules.contratos.messages.generate_installments_first'),
        'contractUpdated' => t('modules.contratos.messages.contract_updated'),
        'contractCreated' => t('modules.contratos.messages.contract_created'),
        'saveError' => t('modules.contratos.messages.save_error'),
        'loadDataError' => t('modules.contratos.messages.load_data_error'),
        'loadContractError' => t('modules.contratos.messages.load_contract_error'),
        'selectPaymentMethod' => t('modules.contratos.messages.select_payment_method'),
        'informFirstDueDate' => t('modules.contratos.messages.inform_first_due_date'),
        'generateInstallmentsError' => t('modules.contratos.messages.generate_installments_error'),
        'saveInstallmentsError' => t('modules.contratos.messages.save_installments_error'),
        'invalidValue' => t('modules.contratos.messages.invalid_value'),
        'clearAllConfirm' => t('modules.contratos.messages.clear_all_confirm'),
        'recalculateConfirm' => t('modules.contratos.messages.recalculate_confirm'),
        'resolveError' => t('modules.contratos.messages.resolve_error'),
        'addInstallmentError' => t('modules.contratos.messages.add_installment_error'),
        'installmentsGeneratedSave' => t('modules.contratos.messages.installments_generated_save'),
        'installmentChargesQueued' => t('modules.contratos.messages.installment_charges_queued'),
        'promptDueDate' => t('modules.contratos.messages.prompt_due_date'),
        'promptInstallmentValue' => t('modules.contratos.messages.prompt_installment_value'),
        'viewInFinancial' => t('modules.contratos.messages.view_in_financial'),
        'newClient' => t('modules.contratos.buttons.new_client'),
        'select' => t('common.labels.select'),
        'save' => t('common.buttons.save'),
        'remove' => t('common.buttons.remove'),
        'vehicleSavedUseDevolution' => t('modules.contratos.messages.vehicle_saved_use_devolution'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= asset('js/contratos.min.js') ?>"></script>
<script>
// ===== BLOQUEIO (Pre-autorizacao no Cartao) =====
(function() {
    const contratoData = window.contratoData || null;
    let cartoesCliente = [];

    async function carregarCartoesCliente(idCliente) {
        if (!idCliente) return;
        try {
            const result = await API.get(`/api/clientes/${idCliente}/cartoes`);
            if (result.success) {
                cartoesCliente = result.data || [];
                const options = cartoesCliente.map(c =>
                    `<option value="${c.id}">**** ${c.ultimos_digitos} ${c.bandeira}</option>`
                ).join('');
                const sel = document.getElementById('bloqueio_id_cartao');
                if (sel) {
                    sel.innerHTML = `<option value="">${<?= $jsT('common.labels.select') ?>}</option>` + options;
                    if (sel.chosenSelect) sel.chosenSelect.refresh();
                }
                const aviso = document.getElementById('bloqueioSemCartao');
                if (aviso) aviso.classList.toggle('hidden', cartoesCliente.length > 0);
            }
        } catch (e) {
            console.error('Erro ao carregar cartoes:', e);
        }
    }

    async function verificarGatewaysBloqueio(idCliente) {
        try {
            const result = await API.get(`/api/clientes/${idCliente}/gateways-cartao`);
            const temHold = result.success && result.data?.some(g => g.gateway_code === 'stripe' || g.gateway_code === 'square');
            document.getElementById('bloqueioSemGateway')?.classList.toggle('hidden', temHold);
            document.getElementById('bloqueioFormFields')?.classList.toggle('hidden', !temHold);
            if (result.success && result.data) {
                const stripe = result.data.find(g => g.gateway_code === 'stripe');
                if (stripe) window._stripePublishableKey = stripe.publishable_key;
            }
        } catch (e) {
            console.error('Erro ao verificar gateways:', e);
        }
    }

    function atualizarBtnCriarBloqueio() {
        const btn = document.getElementById('btnCriarBloqueio');
        if (!btn) return;
        const cartao = document.getElementById('bloqueio_id_cartao')?.value;
        const valor = parseCurrency(document.getElementById('bloqueio_valor')?.value || '');
        const registroId = document.getElementById('registroId')?.value;
        btn.disabled = !cartao || valor <= 0 || !registroId;
    }

    function atualizarStatusBloqueioUI(data) {
        const area = document.getElementById('bloqueioStatusArea');
        if (!area) return;
        area.classList.remove('hidden');

        const badge = document.getElementById('bloqueioStatusBadge');
        const statusMap = {
            authorized: { text: <?= $jsT('modules.contratos.block.authorized') ?>, cls: 'bg-green-100 text-green-800' },
            captured: { text: <?= $jsT('modules.contratos.block.captured') ?>, cls: 'bg-blue-100 text-blue-800' },
            released: { text: <?= $jsT('modules.contratos.block.released') ?>, cls: 'bg-slate-100 text-slate-600' },
            expired: { text: <?= $jsT('modules.contratos.block.expired') ?>, cls: 'bg-amber-100 text-amber-800' },
            failed: { text: <?= $jsT('modules.contratos.block.failed') ?>, cls: 'bg-red-100 text-red-800' },
        };
        const st = statusMap[data.status] || statusMap.authorized;
        if (badge) {
            badge.textContent = st.text;
            badge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${st.cls}`;
        }

        const borderMap = { authorized: 'border-green-200 bg-green-50', captured: 'border-blue-200 bg-blue-50', released: 'border-slate-200 bg-slate-50' };
        area.className = area.className.replace(/border-\S+\s*bg-\S+/g, '').trim();
        area.classList.add(...(borderMap[data.status] || 'border-slate-200 bg-slate-50').split(' '));

        document.getElementById('bloqueioCartaoInfo').textContent =
            `**** ${data.cartao_ultimos_digitos || '****'} ${data.cartao_bandeira || ''}`;
        document.getElementById('bloqueioValorInfo').textContent =
            data.valor ? fmtCurrency(parseFloat(data.valor)) : '';

        if (data.expires_at) {
            const dt = new Date(data.expires_at);
            document.getElementById('bloqueioExpiraInfo').textContent =
                `${<?= $jsT('modules.contratos.block.expires_at') ?>}: ${DateHelper.format(dt)}`;
        }

        const canAct = data.status === 'authorized';
        document.getElementById('btnLiberarBloqueio')?.classList.toggle('hidden', !canAct);
        document.getElementById('btnToggleCaptura')?.classList.toggle('hidden', !canAct);
    }

    // Event listeners
    document.getElementById('bloqueio_id_cartao')?.addEventListener('change', atualizarBtnCriarBloqueio);
    document.getElementById('bloqueio_valor')?.addEventListener('input', atualizarBtnCriarBloqueio);
    document.getElementById('bloqueio_valor')?.addEventListener('change', atualizarBtnCriarBloqueio);

    // Criar bloqueio
    document.getElementById('btnCriarBloqueio')?.addEventListener('click', async function() {
        const registroId = document.getElementById('registroId')?.value;
        if (!registroId) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.messages.save_before_hold') ?> }, '*');
            return;
        }
        const idCartao = document.getElementById('bloqueio_id_cartao')?.value;
        const valor = document.getElementById('bloqueio_valor')?.value;

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + <?= $jsT('common.labels.processing') ?>;

        try {
            const result = await API.post(`/api/contratos/${registroId}/bloqueio/criar`, {
                id_cartao: idCartao,
                valor: valor,
            });
            if (result.success) {
                if (result.data?.client_secret && result.data?.status === 'pending' && window._stripePublishableKey) {
                    const stripe3ds = Stripe(window._stripePublishableKey);
                    const { error } = await stripe3ds.handleCardAction(result.data.client_secret);
                    if (error) {
                        window.parent.postMessage({ action: 'openAlert', message: error.message }, '*');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-shield-alt mr-1"></i>' + <?= $jsT('modules.contratos.block.create_hold') ?>;
                        return;
                    }
                }
                window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.block.hold_created') ?>, type: 'success' }, '*');
                const cartaoSel = cartoesCliente.find(c => c.id == idCartao);
                atualizarStatusBloqueioUI({
                    status: 'authorized',
                    valor: parseCurrency(valor),
                    expires_at: result.data?.expires_at,
                    cartao_bandeira: cartaoSel?.bandeira || '',
                    cartao_ultimos_digitos: cartaoSel?.ultimos_digitos || '',
                });
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || <?= $jsT('modules.contratos.messages.hold_create_error') ?> }, '*');
            }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.messages.hold_create_error') ?> }, '*');
        }
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-shield-alt mr-1"></i>' + <?= $jsT('modules.contratos.block.create_hold') ?>;
    });

    // Liberar bloqueio
    document.getElementById('btnLiberarBloqueio')?.addEventListener('click', async function() {
        const registroId = document.getElementById('registroId')?.value;
        if (!registroId) return;
        this.disabled = true;
        try {
            const result = await API.post(`/api/contratos/${registroId}/bloqueio/liberar`);
            if (result.success) {
                window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.block.hold_released') ?>, type: 'success' }, '*');
                document.getElementById('bloqueioStatusArea')?.classList.add('hidden');
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || <?= $jsT('modules.contratos.messages.hold_release_error') ?> }, '*');
            }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.messages.hold_release_error') ?> }, '*');
        }
        this.disabled = false;
    });

    // Toggle captura
    document.getElementById('btnToggleCaptura')?.addEventListener('click', function() {
        const form = document.getElementById('bloqueioCapturarForm');
        if (!form) return;
        const isHidden = form.classList.contains('hidden');
        form.classList.toggle('hidden');
        if (isHidden) {
            const valorInfo = document.getElementById('bloqueioValorInfo')?.textContent || '';
            const valorMatch = valorInfo.replace(/[^\d,]/g, '');
            document.getElementById('capturaValor').value = valorMatch;
            document.getElementById('capturaValorMax').textContent = valorInfo;
        }
    });

    // Confirmar captura
    document.getElementById('btnConfirmarCaptura')?.addEventListener('click', async function() {
        const registroId = document.getElementById('registroId')?.value;
        if (!registroId) return;
        const valor = document.getElementById('capturaValor')?.value;
        const motivo = document.getElementById('capturaMotivo')?.value;
        const idConta = document.getElementById('capturaContaBancaria')?.value;

        if (!valor || parseCurrency(valor) <= 0) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.block.capture_value_required') ?> }, '*');
            return;
        }
        if (!idConta) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.block.capture_account_required') ?> }, '*');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + <?= $jsT('common.labels.processing') ?>;

        try {
            const result = await API.post(`/api/contratos/${registroId}/bloqueio/capturar`, {
                valor: valor,
                motivo: motivo,
                id_conta: idConta,
            });
            if (result.success) {
                window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.block.hold_captured') ?>, type: 'success' }, '*');
                const badge = document.getElementById('bloqueioStatusBadge');
                if (badge) {
                    badge.textContent = <?= $jsT('modules.contratos.block.captured') ?>;
                    badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
                }
                const valorCapturado = parseCurrency(valor);
                document.getElementById('bloqueioValorInfo').textContent = fmtCurrency(valorCapturado);
                const area = document.getElementById('bloqueioStatusArea');
                if (area) {
                    area.className = area.className.replace(/border-\S+\s*bg-\S+/g, '').trim();
                    area.classList.add('border-blue-200', 'bg-blue-50');
                }
                document.getElementById('btnLiberarBloqueio')?.classList.add('hidden');
                document.getElementById('btnToggleCaptura')?.classList.add('hidden');
                document.getElementById('bloqueioCapturarForm')?.classList.add('hidden');
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || <?= $jsT('modules.contratos.messages.hold_capture_error') ?> }, '*');
            }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.messages.hold_capture_error') ?> }, '*');
        }
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check mr-1"></i>' + <?= $jsT('modules.contratos.block.confirm_capture') ?>;
    });

    // Modal adicionar cartao
    let _gatewaysCache = null;
    async function abrirModalAdicionarCartao() {
        const idCliente = contratoData?.id_cliente || document.getElementById('id_cliente')?.value;
        if (!idCliente) {
            window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.contratos.messages.select_client_first') ?> }, '*');
            return;
        }
        if (!_gatewaysCache) {
            try {
                const result = await API.get(`/api/clientes/${idCliente}/gateways-cartao`);
                if (result.success) _gatewaysCache = result.data || [];
            } catch (e) {
                _gatewaysCache = [];
            }
        }
        window.parent.postMessage({
            action: 'openAddCartaoLocacaoModal',
            id_cliente: idCliente,
            gateways: _gatewaysCache,
        }, '*');
    }

    document.getElementById('btnAdicionarCartaoBloqueio')?.addEventListener('click', abrirModalAdicionarCartao);

    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'cartaoLocacaoSalvo') {
            const idCliente = contratoData?.id_cliente || event.data.id_cliente;
            if (idCliente) carregarCartoesCliente(idCliente);
        }
    });

    // Inicializar bloqueio ao carregar pagina
    if (contratoData?.id_cliente) {
        carregarCartoesCliente(contratoData.id_cliente);
        verificarGatewaysBloqueio(contratoData.id_cliente);

        // Se ja tem bloqueio ativo, mostrar status
        if (contratoData.bloqueio_status && contratoData.bloqueio_hold_valor > 0) {
            atualizarStatusBloqueioUI({
                status: contratoData.bloqueio_status,
                valor: contratoData.bloqueio_status === 'captured'
                    ? (contratoData.bloqueio_valor_capturado || contratoData.bloqueio_hold_valor)
                    : contratoData.bloqueio_hold_valor,
                expires_at: contratoData.bloqueio_expira_em,
                cartao_bandeira: contratoData.bloqueio_cartao_bandeira || '',
                cartao_ultimos_digitos: contratoData.bloqueio_cartao_ultimos_digitos || '',
            });
        }
    }

    // Quando cliente muda (no adicionar.php)
    document.getElementById('id_cliente')?.addEventListener('change', function() {
        const idCliente = this.value;
        if (idCliente) {
            carregarCartoesCliente(idCliente);
            verificarGatewaysBloqueio(idCliente);
        }
    });
})();
</script>
@endsection
