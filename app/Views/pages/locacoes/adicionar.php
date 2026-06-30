@extends('layouts.iframe')

@section('title', t('modules.locacoes.title_singular'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="title-page" id="pageTitle"><?= t('modules.locacoes.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Banner de reserva pendente (status=P) -->
    <div id="pendingApprovalBanner" class="hidden mb-4 rounded-md border border-amber-300 bg-amber-50 p-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 text-amber-800">
            <i class="fas fa-exclamation-triangle text-amber-600 text-xl"></i>
            <div>
                <div class="font-semibold"><?= t('modules.locacoes.form.pending_approval_title') ?></div>
                <div class="text-sm"><?= t('modules.locacoes.form.pending_approval_hint') ?></div>
            </div>
        </div>
        <button type="button" id="btnAprovarReserva" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center whitespace-nowrap">
            <i class="fas fa-check-circle mr-2"></i><?= t('modules.locacoes.buttons.approve') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formLocacao" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Cabecalho do formulario (sempre visivel) -->
        <div class="form-section mb-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-1 form-input-group">
                    <label for="locacaoStatus" class="form-label-group">
                        <?= t('modules.locacoes.fields.status') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="locacaoStatus" name="status" class="form-input-group-field">
                        <option value="R"><?= t('modules.locacoes.status.reservation') ?></option>
                        <option value="A" selected><?= t('modules.locacoes.status.open') ?></option>
                        <option value="F"><?= t('modules.locacoes.status.closed') ?></option>
                    </select>
                </div>
                <div class="md:col-span-2 form-input-group">
                    <label for="id_matriz_filial_retirada" class="form-label-group">
                        <?= t('modules.locacoes.fields.branch_pickup') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="id_matriz_filial_retirada" name="id_matriz_filial_retirada" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('common.labels.select_branch') ?>" required>
                        <option value=""><?= t('common.labels.select') ?></option>
                    </select>
                </div>
                <div class="md:col-span-2 form-input-group">
                    <label for="id_matriz_filial_devolucao" class="form-label-group">
                        <?= t('modules.locacoes.fields.branch_return') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="id_matriz_filial_devolucao" name="id_matriz_filial_devolucao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('common.labels.select_branch') ?>" required>
                        <option value=""><?= t('common.labels.select') ?></option>
                    </select>
                </div>
                <div class="md:col-span-2 form-input-group">
                    <label for="data_saida" class="form-label-group">
                        <?= t('modules.locacoes.fields.checkout_date') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="data_saida" name="data_saida" class="form-input-group-field" required>
                </div>
                <div class="md:col-span-2 form-input-group">
                    <label for="data_prevista" class="form-label-group">
                        <?= t('modules.locacoes.fields.expected_date') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="data_prevista" name="data_prevista" class="form-input-group-field" required>
                </div>
                <div class="md:col-span-1 form-input-group">
                    <label for="dias" class="form-label-group"><?= t('modules.locacoes.fields.days') ?></label>
                    <input type="number" id="dias" name="dias" class="form-input-group-field bg-slate-100" readonly>
                </div>
                <div class="md:col-span-2 form-input-group">
                    <label for="id_funcionario" class="form-label-group">
                        <?= t('modules.locacoes.fields.employee') ?>
                    </label>
                    <select id="id_funcionario" name="id_funcionario" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/funcionarios/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                        <option value=""><?= t('common.labels.select') ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Navegacao de Abas -->
        <div class="mb-4 border-b border-slate-300 overflow-x-auto overflow-y-hidden">
            <nav class="flex -mb-px whitespace-nowrap" id="formTabsNav">
                <button type="button" data-form-tab-target="#tabCliente" class="form-tab-button active">
                    <i class="fas fa-user mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.client') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabVeiculo" class="form-tab-button">
                    <i class="fas fa-car mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.vehicle') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabIntervenientes" class="form-tab-button">
                    <i class="fas fa-users mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.stakeholders') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabTaxas" class="form-tab-button">
                    <i class="fas fa-receipt mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.fees') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabFinanceiro" class="form-tab-button">
                    <i class="fas fa-dollar-sign mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.financial') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabObs" class="form-tab-button">
                    <i class="fas fa-sticky-note mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.notes') ?></span>
                </button>
                <button type="button" data-form-tab-target="#tabResumo" class="form-tab-button">
                    <i class="fas fa-file-invoice-dollar mr-1"></i><span class="hidden sm:inline"><?= t('modules.locacoes.tabs.summary') ?></span>
                </button>
            </nav>
        </div>

        <!-- ================== ABA 1: CLIENTE ================== -->
        <div id="tabCliente" class="form-tab-content active">
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-user mr-2"></i><?= t('modules.locacoes.sections.client_data') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-8 form-input-group">
                        <label for="id_cliente" class="form-label-group">
                            <?= t('modules.locacoes.fields.client') ?> <span class="text-red-500">*</span>
                        </label>
                        <select id="id_cliente" name="id_cliente" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= t('common.labels.type_name_or_cpf') ?>" required>
                            <option value=""><?= t('common.labels.select') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-4 form-input-group flex items-end">
                        <button type="button" id="btnNovoCliente" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium w-full">
                            <i class="fas fa-plus mr-2"></i><?= t('modules.locacoes.buttons.new_client') ?>
                        </button>
                    </div>
                </div>

                <!-- Dados do cliente selecionado -->
                <div id="dadosClienteSelecionado" class="mt-4 p-4 bg-slate-50 rounded-md hidden">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.locacoes.person.cpf_cnpj') ?></span>
                            <p class="font-medium" id="clienteDocumento">-</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.locacoes.person.cnh') ?></span>
                            <p class="font-medium" id="clienteCnh">-</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.locacoes.person.cnh_validity') ?></span>
                            <p class="font-medium" id="clienteCnhValidade">-</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.locacoes.form.phone') ?></span>
                            <p class="font-medium" id="clienteTelefone">-</p>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500"><?= t('modules.locacoes.form.email') ?></span>
                            <p class="font-medium" id="clienteEmail">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== ABA 2: VEICULO ================== -->
        <div id="tabVeiculo" class="form-tab-content">
            <!-- Plano, Grupo e Veiculo -->
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-car mr-2"></i><?= t('modules.locacoes.sections.vehicle_data') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3 form-input-group">
                        <label for="plano" class="form-label-group">
                            <?= t('modules.locacoes.fields.plan') ?>
                        </label>
                        <select id="plano" name="plano" class="form-input-group-field">
                            <option value="KL"><?= t('modules.locacoes.plans.km_free') ?></option>
                            <option value="KMC"><?= t('modules.locacoes.plans.km_controlled') ?></option>
                            <option value="DI"><?= t('modules.locacoes.plans.km_paid') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="id_grupo" class="form-label-group">
                            <?= t('modules.locacoes.fields.group') ?>
                        </label>
                        <select id="id_grupo" name="id_grupo" class="form-input-group-field chosen-select" data-chosen-type="normal" data-chosen-placeholder="<?= t('modules.locacoes.messages.select_group') ?>">
                            <option value=""><?= t('common.labels.select') ?></option>
                        </select>
                    </div>
                    <div id="campoVeiculoWrapper" class="md:col-span-6 form-input-group">
                        <label for="id_veiculo" class="form-label-group">
                            <?= t('modules.locacoes.fields.vehicle') ?> <span class="text-red-500" id="asterisco_id_veiculo">*</span> <?= aviso(t('modules.locacoes.messages.vehicle_locked_use_substitution')) ?>
                        </label>
                        <select id="id_veiculo" name="id_veiculo" class="form-input-group-field chosen-select" data-chosen-type="normal" data-chosen-placeholder="<?= t('modules.locacoes.messages.select_group_first') ?>">
                            <option value=""><?= t('modules.locacoes.messages.select_group_first') ?></option>
                        </select>
                        <input type="hidden" id="id_veiculo_locked" name="id_veiculo" value="" disabled>
                    </div>
                </div>

                <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-3 mt-4">
                    <i class="fas fa-dollar-sign mr-1"></i><?= t('modules.locacoes.sections.values') ?>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-2 form-input-group">
                        <label for="diaria_valor" class="form-label-group"><?= t('modules.locacoes.plans.daily_rate') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="diaria_valor" name="diaria_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                            <input type="hidden" id="diaria_valor_origem" name="diaria_valor_origem" value="<?= isset($locacao) ? 'manual' : 'auto' ?>">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group plano-km-field hidden">
                        <label for="km_valor" class="form-label-group"><?= t('modules.locacoes.plans.value_per_km') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="km_valor" name="km_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group plano-kmc-field hidden">
                        <label for="km_controlado_franquia" class="form-label-group"><?= t('modules.locacoes.plans.km_franchise') ?></label>
                        <input type="number" id="km_controlado_franquia" name="km_controlado_franquia" class="form-input-group-field" value="0">
                    </div>
                    <div class="hidden">
                        <label for="km_controlado_valor" class="form-label-group"><?= t('modules.locacoes.plans.value_km_excess') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="km_controlado_valor" name="km_controlado_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="minuto_tolerancia" class="form-label-group"><?= t('modules.locacoes.fields.tolerance_minutes') ?></label>
                        <div class="relative">
                            <input type="number" id="minuto_tolerancia" name="minuto_tolerancia" class="form-input-group-field pr-12" min="0" value="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-xs"><?= t('modules.locacoes.form.minutes_suffix') ?></span>
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="valor_tolerancia" class="form-label-group"><?= t('modules.locacoes.fields.tolerance_value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valor_tolerancia" name="valor_tolerancia" class="form-input-group-field pl-10 pr-20 input-moeda" value="0,00">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-xs"><?= t('modules.locacoes.form.per_minute') ?></span>
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="valor_km_retorno" class="form-label-group"><?= t('modules.locacoes.fields.return_km_value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valor_km_retorno" name="valor_km_retorno" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="valor_condutor_adicional" class="form-label-group"><?= t('modules.locacoes.fields.additional_driver_value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valor_condutor_adicional" name="valor_condutor_adicional" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seguro -->
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-shield-alt mr-2"></i><?= t('modules.locacoes.sections.insurance') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-2 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.insurance.vehicle') ?></label>
                        <label class="inline-flex items-center mt-1">
                            <input type="checkbox" id="seguro_carro" name="seguro_carro" value="S" class="form-checkbox">
                            <span class="ml-2 text-sm"><?= t('common.buttons.activate') ?></span>
                        </label>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="seguro_carro_valor" class="form-label-group"><?= t('modules.locacoes.insurance.value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="seguro_carro_valor" name="seguro_carro_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="cobertura_carro_valor" class="form-label-group"><?= t('modules.locacoes.insurance.coverage') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="cobertura_carro_valor" name="cobertura_carro_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.insurance.third_party') ?></label>
                        <label class="inline-flex items-center mt-1">
                            <input type="checkbox" id="seguro_terceiros" name="seguro_terceiros" value="S" class="form-checkbox">
                            <span class="ml-2 text-sm"><?= t('common.buttons.activate') ?></span>
                        </label>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="seguro_terceiros_valor" class="form-label-group"><?= t('modules.locacoes.insurance.value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="seguro_terceiros_valor" name="seguro_terceiros_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="cobertura_terceiros_valor" class="form-label-group"><?= t('modules.locacoes.insurance.coverage') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="cobertura_terceiros_valor" name="cobertura_terceiros_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Odometro / Combustivel -->
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-tachometer-alt mr-2"></i><?= t('modules.locacoes.sections.odometer_fuel') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3 form-input-group">
                        <label for="odometro_ini" class="form-label-group"><?= t('modules.locacoes.odometer_fuel.odometer') ?> (km)</label>
                        <input type="text" id="odometro_ini" name="odometro_ini" class="form-input-group-field input-km" placeholder="0">
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="combustivel_ini" id="labelCombustivelIni" class="form-label-group"><?= t('modules.locacoes.odometer_fuel.fuel_out') ?></label>
                        <select id="combustivel_ini" name="combustivel_ini" class="form-input-group-field">
                            <option value="">-</option>
                            <option value="8"><?= t('modules.locacoes.fuel_levels.full') ?></option>
                            <option value="7">7/8</option>
                            <option value="6">3/4</option>
                            <option value="5">5/8</option>
                            <option value="4">1/2</option>
                            <option value="3">3/8</option>
                            <option value="2">1/4</option>
                            <option value="1">1/8</option>
                            <option value="0"><?= t('modules.locacoes.fuel_levels.reserve') ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Devolucao (visivel apenas quando status muda para Fechado) -->
            <div id="secaoDevolucao" class="form-section mb-4 hidden">
                <h3 class="form-section-title"><i class="fas fa-undo-alt mr-2"></i><?= t('modules.locacoes.form.return_vehicle') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3 form-input-group">
                        <label for="odometro_fim" class="form-label-group"><?= t('modules.locacoes.form.return_odometer_km') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="odometro_fim" name="odometro_fim" class="form-input-group-field input-km" placeholder="0">
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="combustivel_fim" id="labelCombustivelFim" class="form-label-group"><?= t('modules.locacoes.form.return_fuel') ?> <span class="text-red-500 hidden" id="asterisco_combustivel_fim">*</span></label>
                        <select id="combustivel_fim" name="combustivel_fim" class="form-input-group-field">
                            <option value="">-</option>
                            <option value="8"><?= t('modules.locacoes.fuel_levels.full') ?></option>
                            <option value="7">7/8</option>
                            <option value="6">3/4</option>
                            <option value="5">5/8</option>
                            <option value="4">1/2</option>
                            <option value="3">3/8</option>
                            <option value="2">1/4</option>
                            <option value="1">1/8</option>
                            <option value="0"><?= t('modules.locacoes.fuel_levels.reserve') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="km_rodados" class="form-label-group"><?= t('modules.locacoes.form.km_driven') ?></label>
                        <input type="text" id="km_rodados" class="form-input-group-field bg-slate-100" readonly>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-2">
                    <div class="md:col-span-3 form-input-group">
                        <label for="km_excedente" class="form-label-group"><?= t('modules.locacoes.return_page.km_excess') ?></label>
                        <input type="text" id="km_excedente" class="form-input-group-field bg-slate-100" readonly value="0">
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="combustivel_usado" class="form-label-group"><?= t('modules.locacoes.form.fuel_used_levels') ?></label>
                        <input type="text" id="combustivel_usado" class="form-input-group-field bg-slate-100" readonly value="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== ABA 3: INTERVENIENTES ================== -->
        <div id="tabIntervenientes" class="form-tab-content">
            <!-- Condutor Adicional -->
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-id-card mr-2"></i><?= t('modules.locacoes.sections.additional_driver') ?></h3>
                    <button type="button" id="btnAdicionarCondutor" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.locacoes.sections.additional_driver_hint') ?></p>
                <div id="listaCondutores"></div>
            </div>

            <!-- Fiadores -->
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-user-shield mr-2"></i><?= t('modules.locacoes.sections.guarantors') ?></h3>
                    <button type="button" id="btnAdicionarFiador" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.locacoes.sections.guarantors_hint') ?></p>
                <div id="listaFiadores"></div>
            </div>

            <!-- Avalistas -->
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-user-check mr-2"></i><?= t('modules.locacoes.sections.endorsers') ?></h3>
                    <button type="button" id="btnAdicionarAvalista" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.locacoes.sections.endorsers_hint') ?></p>
                <div id="listaAvalistas"></div>
            </div>

            <!-- Testemunhas -->
            <div class="form-section mb-4">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-users mr-2"></i><?= t('modules.locacoes.sections.witnesses') ?></h3>
                    <button type="button" id="btnAdicionarTestemunha" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('common.buttons.add') ?>
                    </button>
                </div>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.locacoes.sections.witnesses_hint') ?></p>
                <div id="listaTestemunhas"></div>
            </div>
        </div>

        <!-- ================== ABA 4: TAXAS E SERVICOS ================== -->
        <div id="tabTaxas" class="form-tab-content">
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-receipt mr-2"></i><?= t('modules.locacoes.sections.fees_services') ?></h3>

                <div class="bg-slate-50 p-4 rounded-md mb-3">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.fees.fee_service') ?></label>
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
                            <label class="form-label-group"><?= t('modules.locacoes.fees.name') ?></label>
                            <input type="text" id="taxa_nome" class="form-input-group-field bg-slate-100" placeholder="<?= t('modules.locacoes.fees.name') ?>" readonly>
                        </div>
                        <div class="md:col-span-1 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.fees.qty') ?></label>
                            <input type="number" id="taxa_qtd" class="form-input-group-field bg-slate-100" value="1" min="1" readonly>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.fees.unit_value') ?></label>
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
                    <p class="text-slate-500 text-center py-4"><?= t('modules.locacoes.fees.no_fees') ?></p>
                </div>
            </div>
        </div>

        <!-- ================== ABA 5: FINANCEIRO ================== -->
        <div id="tabFinanceiro" class="form-tab-content">
            <div class="form-section mb-4">
                <div class="flex justify-between items-center <?= isset($locacao) ? 'cursor-pointer' : '' ?>" id="toggleConfigPagamentoLocacao">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-cog mr-2"></i><?= t('modules.locacoes.sections.payment_config') ?></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors <?= isset($locacao) ? '' : 'hidden' ?>">
                        <i class="fas fa-chevron-down" id="iconConfigPagamentoLocacao"></i>
                    </button>
                </div>

                <div id="conteudoConfigPagamentoLocacao" class="mt-4 <?= isset($locacao) ? 'hidden' : '' ?>">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4 form-input-group">
                        <label for="id_conta" class="form-label-group"><?= t('modules.locacoes.fields.bank_account') ?> <span class="text-red-500">*</span></label>
                        <select id="id_conta" name="id_conta" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar" data-chosen-placeholder="<?= t('common.labels.select_account') ?>" required>
                            <option value=""><?= t('common.labels.select') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="id_forma_pagamento" class="form-label-group"><?= t('modules.locacoes.fields.payment_method') ?> <span class="text-red-500">*</span></label>
                        <select id="id_forma_pagamento" name="id_forma_pagamento" class="form-input-group-field chosen-select" data-chosen-type="normal" data-chosen-placeholder="<?= t('common.labels.select') ?>" required>
                            <option value=""><?= t('common.labels.loading') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="valor_desconto" class="form-label-group"><?= t('modules.locacoes.fields.discount') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valor_desconto" name="valor_desconto" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="promocao_codigo" class="form-label-group"><?= t('modules.locacoes.fields.promo_code') ?></label>
                        <input type="text" id="promocao_codigo" name="promocao_codigo" class="form-input-group-field">
                    </div>
                </div>
                </div>
            </div>

            <!-- Bloqueio (Pre-autorizacao no Cartao) -->
            <div id="secaoBloqueio" class="form-section mb-4">
                <div class="flex justify-between items-center <?= isset($locacao) ? 'cursor-pointer' : '' ?>" id="toggleBloqueioLocacao">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-lock mr-2"></i><?= t('modules.locacoes.sections.block') ?></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors <?= isset($locacao) ? '' : 'hidden' ?>">
                        <i class="fas fa-chevron-down" id="iconBloqueioLocacao"></i>
                    </button>
                </div>

                <div id="conteudoBloqueioLocacao" class="mt-4 <?= isset($locacao) ? 'hidden' : '' ?>">
                <!-- Aviso se nao tem gateway compativel -->
                <div id="bloqueioSemGateway" class="hidden bg-amber-50 border border-amber-200 rounded-md p-3 mb-4">
                    <p class="text-amber-700 text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> <?= t('modules.locacoes.block.no_gateway') ?></p>
                </div>

                <div id="bloqueioFormFields">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6 form-input-group">
                            <label for="bloqueio_id_cartao" class="form-label-group"><?= t('modules.locacoes.block.card') ?></label>
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <select id="bloqueio_id_cartao" name="bloqueio_id_cartao" class="form-input-group-field chosen-select" data-chosen-type="normal" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                                        <option value=""><?= t('common.labels.select') ?></option>
                                    </select>
                                </div>
                                <button type="button" id="btnAdicionarCartaoBloqueio" class="btn-secondary py-1 px-3 text-xs whitespace-nowrap">
                                    <i class="fas fa-plus mr-1"></i><?= t('modules.locacoes.block.add_card') ?>
                                </button>
                            </div>
                            <p id="bloqueioSemCartao" class="text-amber-600 text-xs mt-1 hidden"><i class="fas fa-info-circle mr-1"></i><?= t('modules.locacoes.block.no_card') ?></p>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label for="bloqueio_valor" class="form-label-group"><?= t('modules.locacoes.block.value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="bloqueio_valor" name="bloqueio_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group flex items-end">
                            <button type="button" id="btnCriarBloqueio" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center justify-center shadow hover:shadow-md transition-shadow w-full" disabled>
                                <i class="fas fa-shield-alt mr-1"></i><?= t('modules.locacoes.block.create_hold') ?>
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
                                <i class="fas fa-unlock mr-1"></i><?= t('modules.locacoes.block.release_hold') ?>
                            </button>
                            <button type="button" id="btnToggleCaptura" class="btn-red py-2 px-4 rounded-md text-sm font-medium">
                                <i class="fas fa-hand-holding-usd mr-1"></i><?= t('modules.locacoes.block.capture_hold') ?>
                            </button>
                        </div>

                        <!-- Formulario de captura (visivel ao clicar em Capturar) -->
                        <div id="bloqueioCapturarForm" class="hidden mt-4 p-4 rounded-lg border border-red-200 bg-red-50">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3"><i class="fas fa-hand-holding-usd mr-1"></i> <?= t('modules.locacoes.block.capture_details') ?></h4>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <div class="md:col-span-3 form-input-group">
                                    <label for="capturaValor" class="form-label-group"><?= t('modules.locacoes.block.capture_value') ?></label>
                                    <div class="relative">
                                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                        <input type="text" id="capturaValor" class="form-input-group-field pl-10 input-moeda">
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1"><?= t('modules.locacoes.block.capture_max') ?>: <span id="capturaValorMax"></span></p>
                                </div>
                                <div class="md:col-span-4 form-input-group">
                                    <label for="capturaMotivo" class="form-label-group"><?= t('modules.locacoes.block.capture_reason') ?></label>
                                    <select id="capturaMotivo" class="form-input-group-field">
                                        <option value="dano"><?= t('modules.locacoes.block.reason_damage') ?></option>
                                        <option value="multa"><?= t('modules.locacoes.block.reason_fine') ?></option>
                                        <option value="combustivel"><?= t('modules.locacoes.block.reason_fuel') ?></option>
                                        <option value="diaria_extra"><?= t('modules.locacoes.block.reason_extra_days') ?></option>
                                        <option value="outro"><?= t('modules.locacoes.block.reason_other') ?></option>
                                    </select>
                                </div>
                                <div class="md:col-span-3 form-input-group">
                                    <label for="capturaContaBancaria" class="form-label-group"><?= t('modules.locacoes.deposit.account') ?></label>
                                    <select id="capturaContaBancaria" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar" data-chosen-placeholder="<?= t('common.labels.select_account') ?>">
                                        <option value=""><?= t('common.labels.select') ?></option>
                                    </select>
                                </div>
                                <div class="md:col-span-2 form-input-group flex items-end gap-2">
                                    <button type="button" id="btnConfirmarCaptura" class="btn-red py-2 px-4 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow w-full">
                                        <i class="fas fa-check mr-1"></i><?= t('modules.locacoes.block.confirm_capture') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Caucao (Deposito de Garantia) -->
            <div class="form-section mb-4">
                <div class="flex justify-between items-center <?= isset($locacao) ? 'cursor-pointer' : '' ?>" id="toggleCaucaoLocacao">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-shield-alt mr-2"></i><?= t('modules.locacoes.sections.deposit') ?></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors <?= isset($locacao) ? '' : 'hidden' ?>">
                        <i class="fas fa-chevron-down" id="iconCaucaoLocacao"></i>
                    </button>
                </div>

                <div id="conteudoCaucaoLocacao" class="mt-4 <?= isset($locacao) ? 'hidden' : '' ?>">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3 form-input-group">
                        <label for="id_conta_caucao" class="form-label-group"><?= t('modules.locacoes.deposit.account') ?> <span id="asterisco_conta_caucao" class="text-red-500 hidden">*</span></label>
                        <select id="id_conta_caucao" name="id_conta_caucao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar" data-chosen-placeholder="<?= t('common.labels.select_account') ?>">
                            <option value=""><?= t('common.labels.select') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-3 form-input-group">
                        <label for="id_forma_pagamento_caucao" class="form-label-group"><?= t('modules.locacoes.deposit.payment_method') ?> <span id="asterisco_forma_pagamento_caucao" class="text-red-500 hidden">*</span></label>
                        <select id="id_forma_pagamento_caucao" name="id_forma_pagamento_caucao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/formas-pagamento/select" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                            <option value=""><?= t('common.labels.select') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="caucao_valor" class="form-label-group"><?= t('modules.locacoes.deposit.value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="caucao_valor" name="caucao_valor" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="caucao_prazo_devolucao" class="form-label-group"><?= t('modules.locacoes.deposit.return_days') ?> <span id="asterisco_caucao_prazo" class="text-red-500 hidden">*</span></label>
                        <div class="relative">
                            <input type="number" id="caucao_prazo_devolucao" name="caucao_prazo_devolucao" class="form-input-group-field pr-12" min="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-xs"><?= t('modules.locacoes.form.days_suffix') ?></span>
                        </div>
                    </div>
                    <div class="md:col-span-2 form-input-group">
                        <label for="caucao_lancar_financeiro" class="form-label-group"><?= t('modules.locacoes.deposit.launch_financial') ?></label>
                        <select id="caucao_lancar_financeiro" name="caucao_lancar_financeiro" class="form-input-group-field">
                            <option value="0"><?= t('common.labels.no') ?></option>
                            <option value="1"><?= t('common.labels.yes') ?></option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-3">
                    <div class="md:col-span-12 form-input-group">
                        <label for="caucao_observacoes" class="form-label-group"><?= t('modules.locacoes.deposit.notes') ?></label>
                        <input type="text" id="caucao_observacoes" name="caucao_observacoes" class="form-input-group-field">
                    </div>
                </div>
                </div>
            </div>

            <!-- Pagamentos -->
            <div id="secaoParcelas" class="form-section mb-4 hidden">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-300">
                    <h3 class="form-section-title mb-0 pb-0 border-b-0"><i class="fas fa-list-ol mr-2"></i><?= t('modules.locacoes.installments.title') ?></h3>
                    <div id="parcelasAcoesHeader" class="flex space-x-2">
                        <button type="button" id="btnGerarParcelas" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-magic mr-1"></i><?= t('modules.locacoes.installments.generate') ?>
                        </button>
                        <button type="button" id="btnAdicionarParcela" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-plus mr-1"></i><?= t('modules.locacoes.installments.add') ?>
                        </button>
                        <button type="button" id="btnAdicionarAvaria" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-car-crash mr-1"></i><?= t('modules.locacoes.installments.add_damage') ?>
                        </button>
                    </div>
                </div>

                <div id="parcelasEstadoNovo" class="hidden rounded-md border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">
                    <i class="fas fa-info-circle mr-2 text-slate-400"></i><?= t('modules.locacoes.installments.save_before_add_payment') ?>
                </div>

                <!-- Formulario gerar parcelas (toggle) -->
                <div id="formGerarParcelas" class="bg-blue-50 p-4 rounded-md mb-4 hidden">
                    <h4 class="text-sm font-semibold text-blue-700 mb-3"><?= t('modules.locacoes.installments.generate_auto') ?></h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.quantity') ?></label>
                            <input type="number" id="gerar_quantidade" class="form-input-group-field" min="1" max="60" value="1">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.first_due_date') ?></label>
                            <input type="date" id="gerar_data_vencimento" class="form-input-group-field">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.bank_account') ?></label>
                            <select id="gerar_id_conta" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.payment_method_short') ?></label>
                            <select id="gerar_id_forma_pagamento" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group flex items-end space-x-2">
                            <button type="button" id="btnConfirmarGerarParcelas" class="btn-blue py-2 px-3 rounded-md text-sm font-medium flex-1">
                                <i class="fas fa-check mr-1"></i><?= t('modules.locacoes.installments.generate_confirm') ?>
                            </button>
                            <button type="button" id="btnCancelarGerarParcelas" class="btn-secondary py-2 px-3 rounded-md text-sm font-medium">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Formulario adicionar parcela avulsa (toggle) -->
                <div id="formAdicionarParcela" class="bg-green-50 p-4 rounded-md mb-4 hidden">
                    <h4 class="text-sm font-semibold text-green-700 mb-3"><?= t('modules.locacoes.installments.add_single') ?></h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="parcela_valor" class="form-input-group-field pl-10 input-moeda">
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.due_date') ?></label>
                            <input type="date" id="parcela_data_venci" class="form-input-group-field">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.bank_account') ?></label>
                            <select id="parcela_id_conta" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.payment_method_short') ?></label>
                            <select id="parcela_id_forma_pagamento" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group flex items-end space-x-2">
                            <button type="button" id="btnConfirmarAdicionarParcela" class="btn-blue py-2 px-3 rounded-md text-sm font-medium flex-1">
                                <i class="fas fa-check mr-1"></i><?= t('common.buttons.save') ?>
                            </button>
                            <button type="button" id="btnCancelarAdicionarParcela" class="btn-secondary py-2 px-3 rounded-md text-sm font-medium">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-2">
                        <div class="md:col-span-6 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.description') ?></label>
                            <input type="text" id="parcela_descricao" class="form-input-group-field" placeholder="<?= t('modules.locacoes.installments.optional_description') ?>">
                        </div>
                    </div>
                </div>

                <div id="formAdicionarAvaria" class="bg-orange-50 p-4 rounded-md mb-4 hidden">
                    <h4 class="text-sm font-semibold text-orange-700 mb-3"><?= t('modules.locacoes.installments.add_damage') ?></h4>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="avaria_valor" class="form-input-group-field pl-10 input-moeda">
                            </div>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.due_date') ?></label>
                            <input type="date" id="avaria_data_venci" class="form-input-group-field">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.bank_account') ?></label>
                            <select id="avaria_id_conta" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.payment_method_short') ?></label>
                            <select id="avaria_id_forma_pagamento" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.description') ?></label>
                            <input type="text" id="avaria_descricao" class="form-input-group-field" placeholder="<?= t('modules.locacoes.installments.damage_description_placeholder') ?>">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <span class="text-xs text-orange-700"><?= t('modules.locacoes.installments.damage_chart_account_hint') ?></span>
                        <div class="flex gap-2">
                            <button type="button" id="btnConfirmarAdicionarAvaria" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                                <i class="fas fa-check mr-1"></i><?= t('common.buttons.save') ?>
                            </button>
                            <button type="button" id="btnCancelarAdicionarAvaria" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                                <i class="fas fa-times mr-1"></i><?= t('common.buttons.cancel') ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Formulario marcar parcela como paga (toggle) -->
                <div id="formMarcarPago" class="bg-emerald-50 p-4 rounded-md mb-4 hidden">
                    <h4 class="text-sm font-semibold text-emerald-700 mb-3">
                        <i class="fas fa-check-circle mr-1"></i><?= t('modules.locacoes.installments.register_payment') ?> — <span id="pagar_descricao_resumo"></span>
                    </h4>
                    <input type="hidden" id="pagar_id_parcela">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.payment_date') ?></label>
                            <input type="date" id="pagar_data_pago" class="form-input-group-field">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.payment_method_short') ?></label>
                            <select id="pagar_id_forma_pagamento" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.locacoes.installments.bank_account') ?></label>
                            <select id="pagar_id_conta" class="form-input-group-field">
                                <option value=""><?= t('common.labels.select') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-2 form-input-group flex items-end space-x-2">
                            <button type="button" id="btnConfirmarMarcarPago" class="btn-blue py-2 px-3 rounded-md text-sm font-medium flex-1">
                                <i class="fas fa-check mr-1"></i><?= t('modules.locacoes.installments.confirm') ?>
                            </button>
                            <button type="button" id="btnCancelarMarcarPago" class="btn-secondary py-2 px-3 rounded-md text-sm font-medium">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabela de parcelas -->
                <div id="parcelasTabelaWrapper" class="overflow-x-auto">
                    <table class="w-full text-sm" id="tabelaParcelas">
                        <thead class="bg-slate-100">
                            <tr class="text-xs text-slate-500 uppercase">
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left"><?= t('modules.locacoes.installments.description') ?></th>
                                <th class="px-3 py-2 text-center"><?= t('modules.locacoes.installments.due_date') ?></th>
                                <th class="px-3 py-2 text-right"><?= t('modules.locacoes.installments.value') ?></th>
                                <th class="px-3 py-2 text-center"><?= t('modules.locacoes.fields.status') ?></th>
                                <th class="px-3 py-2 text-center w-28"><?= t('modules.locacoes.installments.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="parcelasBody">
                            <tr><td colspan="6" class="px-3 py-4 text-center text-slate-400"><?= t('modules.locacoes.installments.no_installments') ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Resumo financeiro -->
                <div class="mt-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" id="resumoFinanceiroParcelas">
                    <div class="bg-slate-50 rounded-md p-3 text-center">
                        <span class="text-xs text-slate-500 block"><?= t('modules.locacoes.installments.total_launched') ?></span>
                        <span class="text-lg font-semibold" id="rfTotalLancado">R$ 0,00</span>
                    </div>
                    <div class="bg-orange-50 rounded-md p-3 text-center">
                        <span class="text-xs text-orange-600 block"><?= t('modules.locacoes.installments.total_damages') ?></span>
                        <span class="text-lg font-semibold text-orange-600" id="rfTotalAvarias">R$ 0,00</span>
                    </div>
                    <div class="bg-green-50 rounded-md p-3 text-center">
                        <span class="text-xs text-green-600 block"><?= t('modules.locacoes.installments.total_paid') ?></span>
                        <span class="text-lg font-semibold text-green-600" id="rfTotalPago">R$ 0,00</span>
                    </div>
                    <div class="bg-yellow-50 rounded-md p-3 text-center">
                        <span class="text-xs text-yellow-600 block"><?= t('modules.locacoes.installments.total_pending') ?></span>
                        <span class="text-lg font-semibold text-yellow-600" id="rfTotalPendente">R$ 0,00</span>
                    </div>
                    <div class="bg-rose-50 rounded-md p-3 text-center">
                        <span class="text-xs text-rose-600 block"><?= t('modules.locacoes.installments.total_refunded') ?></span>
                        <span class="text-lg font-semibold text-rose-600" id="rfTotalReembolsado">R$ 0,00</span>
                    </div>
                    <div class="bg-blue-50 rounded-md p-3 text-center">
                        <span class="text-xs text-blue-600 block"><?= t('modules.locacoes.installments.difference') ?></span>
                        <span class="text-lg font-semibold text-blue-600" id="rfDiferenca">R$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== ABA 6: OBSERVACOES ================== -->
        <div id="tabObs" class="form-tab-content">
            <div class="form-section mb-4">
                <h3 class="form-section-title"><i class="fas fa-sticky-note mr-2"></i><?= t('modules.locacoes.sections.observations') ?></h3>

                <div class="form-input-group">
                    <textarea id="obs" name="obs" class="form-input-group-field" rows="6" placeholder="<?= t('modules.locacoes.sections.observations') ?>..."></textarea>
                </div>
            </div>
        </div>

        <!-- ================== ABA 7: RESUMO ================== -->
        <div id="tabResumo" class="form-tab-content">
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-file-invoice-dollar mr-2"></i><?= t('modules.locacoes.sections.rental_summary') ?>
                </h3>

                <div class="bg-white rounded-md overflow-x-auto">
                    <table id="tabelaResumo" class="w-full text-sm">
                        <colgroup>
                            <col>
                            <col style="width: 80px">
                            <col style="width: 110px">
                            <col style="width: 110px">
                            <col style="width: 160px">
                        </colgroup>
                        <tbody id="resumoBody">
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

<!-- Template para Condutor Adicional (com CNH) -->
<template id="templateCondutorCard">
    <div class="pessoa-card bg-white border border-slate-200 rounded-md p-4 mb-3" data-index="" data-tipo="condutor">
        <div class="flex justify-between items-center mb-3">
            <span class="text-sm font-medium text-slate-600 pessoa-label"><?= t('modules.locacoes.person.conductor_label', ['num' => '1']) ?></span>
            <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-pessoa">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-5 form-input-group">
                <label class="form-label-group"><?= t('modules.locacoes.person.full_name') ?></label>
                <select class="form-input-group-field text-sm chosen-select pessoa-select-cliente"
                        data-chosen-type="server-side"
                        data-chosen-search-url="/api/clientes/buscar"
                        data-chosen-placeholder="<?= t('common.labels.type_name_or_cpf') ?>">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.locacoes.person.cpf_cnpj') ?></label>
                <input type="text" class="form-input-group-field pessoa-cc bg-slate-50" readonly>
            </div>
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group"><?= t('modules.locacoes.person.cnh') ?></label>
                <input type="text" class="form-input-group-field pessoa-cnh bg-slate-50" readonly>
            </div>
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group"><?= t('modules.locacoes.person.cnh_validity') ?></label>
                <input type="text" class="form-input-group-field pessoa-cnh-validade bg-slate-50" readonly>
                <small class="text-red-500 text-xs pessoa-cnh-alerta hidden"><?= t('modules.contratos.person.cnh_expired') ?></small>
            </div>
        </div>
        <input type="hidden" class="pessoa-id">
    </div>
</template>

<!-- Template para Pessoa sem CNH (Fiador, Avalista, Testemunha) -->
<template id="templatePessoaCard">
    <div class="pessoa-card bg-white border border-slate-200 rounded-md p-4 mb-3" data-index="" data-tipo="">
        <div class="flex justify-between items-center mb-3">
            <span class="text-sm font-medium text-slate-600 pessoa-label"><?= t('modules.locacoes.person.witness_label', ['num' => '1']) ?></span>
            <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-pessoa">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6 form-input-group">
                <label class="form-label-group"><?= t('modules.locacoes.person.full_name') ?></label>
                <select class="form-input-group-field text-sm chosen-select pessoa-select-cliente"
                        data-chosen-type="server-side"
                        data-chosen-search-url="/api/clientes/buscar"
                        data-chosen-placeholder="<?= t('common.labels.type_name_or_cpf') ?>">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="md:col-span-6 form-input-group">
                <label class="form-label-group"><?= t('modules.locacoes.person.cpf_cnpj') ?></label>
                <input type="text" class="form-input-group-field pessoa-cc bg-slate-50" readonly>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-2">
            <div class="md:col-span-12 form-input-group">
                <label class="form-label-group"><?= t('modules.contratos.person.address') ?></label>
                <input type="text" class="form-input-group-field pessoa-endereco bg-slate-50" readonly>
            </div>
        </div>
        <input type="hidden" class="pessoa-id">
    </div>
</template>

<!-- Template para item de taxa -->
<template id="templateTaxaItem">
    <div class="taxa-item flex items-center justify-between bg-slate-50 rounded-md px-4 py-3 border border-slate-200" data-index="" data-id="">
        <div class="flex-1 grid grid-cols-12 gap-2 items-center">
            <div class="col-span-4 text-sm font-medium taxa-nome"><?= t('modules.locacoes.fees.name') ?></div>
            <div class="col-span-1 text-sm text-center taxa-qtd">1x</div>
            <div class="col-span-3 text-sm text-right text-slate-500 taxa-valor-unit">R$ 0,00</div>
            <div class="col-span-2 text-sm text-right font-semibold taxa-valor-total">R$ 0,00</div>
            <div class="col-span-2 text-center">
                <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-taxa">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>
@endsection

@section('scripts')
<?php
$jsText = static fn(string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$jsT = static fn(string $key, array $replace = []): string => $jsText(t($key, $replace));
?>
<script>
    (function() {
        const i18n = <?= json_encode([
            'saveError' => t('modules.locacoes.messages.save_error'),
            'created' => t('modules.locacoes.messages.rental_created'),
            'updated' => t('modules.locacoes.messages.rental_updated'),
            'selectClient' => t('modules.locacoes.messages.select_client'),
            'selectVehicle' => t('modules.locacoes.messages.select_vehicle'),
            'fillDates' => t('modules.locacoes.messages.fill_dates_first'),
            'loadError' => t('modules.locacoes.messages.load_data_error'),
            'fuelOut' => t('modules.locacoes.odometer_fuel.fuel_out'),
            'chargeOut' => t('modules.locacoes.odometer_fuel.charge_out'),
            'fuelFull' => t('modules.locacoes.fuel_levels.full'),
            'fuelReserve' => t('modules.locacoes.fuel_levels.reserve'),
            'zipLabel' => t('modules.locacoes.pdf.zip_label'),
            'select' => t('common.labels.select'),
            'saving' => t('common.labels.saving'),
            'processing' => t('common.labels.processing'),
            'returnVehicle' => t('modules.locacoes.form.return_vehicle'),
            'arrivalDate' => t('modules.locacoes.form.arrival_date'),
            'returnOdometerKm' => t('modules.locacoes.form.return_odometer_km'),
            'returnOdometerLessThanCheckout' => t('modules.locacoes.form.return_odometer_less_than_checkout'),
            'returnFuel' => t('modules.locacoes.form.return_fuel'),
            'financialSummaryUnavailable' => t('modules.locacoes.form.financial_summary_unavailable'),
            'registerFinancialInstallments' => t('modules.locacoes.form.register_financial_installments'),
            'installmentsTotalMismatch' => t('modules.locacoes.form.installments_total_mismatch'),
            'returnRefundTitle' => t('modules.locacoes.form.return_refund_title'),
            'returnRefundMessage' => t('modules.locacoes.form.return_refund_message'),
            'returnRefundConfirm' => t('modules.locacoes.form.return_refund_confirm'),
            'saveBeforeHold' => t('modules.locacoes.form.save_before_hold'),
            'holdCreateError' => t('modules.locacoes.form.hold_create_error'),
            'holdReleaseError' => t('modules.locacoes.form.hold_release_error'),
            'holdCaptureError' => t('modules.locacoes.form.hold_capture_error'),
            'genericError' => t('modules.locacoes.form.generic_error'),
            'selectClientFirst' => t('modules.locacoes.form.select_client_first'),
            'allFieldsRequired' => t('modules.locacoes.form.all_fields_required'),
            'allRequiredFields' => t('modules.locacoes.form.all_required_fields'),
            'paymentDateRequired' => t('modules.locacoes.form.payment_date_required'),
            'paymentMethodRequired' => t('modules.locacoes.form.payment_method_required'),
            'bankAccountRequired' => t('modules.locacoes.form.bank_account_required'),
            'paymentTypes' => [
                'dinheiro' => t('modules.locacoes.payment_types.cash'),
                'pix' => t('modules.locacoes.payment_types.pix'),
                'cartao' => t('modules.locacoes.payment_types.card'),
                'cheque' => t('modules.locacoes.payment_types.check'),
            ],
            'installments' => [
                'noInstallments' => t('modules.locacoes.installments.no_installments'),
                'paid' => t('modules.locacoes.installments.paid'),
                'pending' => t('modules.locacoes.installments.pending'),
                'installmentLabel' => t('modules.locacoes.installments.installment_label'),
                'reversePayment' => t('modules.locacoes.installments.reverse_payment'),
                'markPaid' => t('modules.locacoes.installments.mark_paid'),
                'remove' => t('modules.locacoes.installments.remove'),
                'removeTitle' => t('modules.locacoes.installments.remove_title'),
                'removeMessage' => t('modules.locacoes.installments.remove_message'),
                'reverseTitle' => t('modules.locacoes.installments.reverse_title'),
                'reverseMessage' => t('modules.locacoes.installments.reverse_message'),
                'reverseConfirm' => t('modules.locacoes.installments.reverse_confirm'),
                'processError' => t('modules.locacoes.installments.process_error'),
                'markPaidError' => t('modules.locacoes.installments.mark_paid_error'),
                'generated' => t('modules.locacoes.installments.generated'),
                'generateError' => t('modules.locacoes.installments.generate_error'),
                'added' => t('modules.locacoes.installments.added'),
                'addError' => t('modules.locacoes.installments.add_error'),
            ],
            'summary' => [
                'qty' => t('modules.locacoes.summary.qty'),
                'period' => t('modules.locacoes.summary.period_abbr'),
                'total' => t('modules.locacoes.summary.total'),
                'fixed' => t('modules.locacoes.summary.fixed'),
                'perDay' => t('modules.locacoes.summary.per_day'),
                'onBase' => t('modules.locacoes.summary.on_base'),
                'returnSection' => t('modules.locacoes.summary.return_section'),
                'fuel' => t('modules.locacoes.summary.fuel'),
                'totals' => t('modules.locacoes.summary.totals'),
                'amountPaid' => t('modules.locacoes.summary.amount_paid'),
                'amountRefunded' => t('modules.locacoes.summary.amount_refunded'),
                'balanceDue' => t('modules.locacoes.summary.balance_due'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        const isEditing = <?= isset($locacao) ? 'true' : 'false' ?>;
        const locacaoData = <?= isset($locacao) ? json_encode($locacao) : 'null' ?>;
        const vehicleChangeLocked = isEditing && locacaoData && ['A', 'F'].includes(locacaoData.status);

        // ===== NAVEGACAO =====

        function navegarPara(page) {
            if (window.parent !== window) {
                window.parent.postMessage({ action: 'navigate', page: page }, '*');
            } else {
                window.location.href = page;
            }
        }

        document.getElementById('btnVoltar')?.addEventListener('click', function() {
            navegarPara('/pages/locacoes');
        });

        // ===== DATETIME HELPERS =====

        function formatDateTimeLocal(d) {
            const pad = n => n.toString().padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        // ===== CALCULO DE DIAS =====

        function calcularDias() {
            const saida = document.getElementById('data_saida')?.value;
            const prevista = document.getElementById('data_prevista')?.value;
            if (saida && prevista) {
                const status = document.getElementById('locacaoStatus')?.value || 'R';
                const toleranciaMinutos = status === 'F'
                    ? (parseInt(document.getElementById('minuto_tolerancia')?.value, 10) || 0)
                    : 0;
                const diffMs = Math.max(0, DateHelper.diffDateTime(saida, prevista));
                const minutosCobradosMs = Math.max(0, diffMs - (toleranciaMinutos * 60 * 1000));
                const diff = Math.ceil(minutosCobradosMs / (1000 * 60 * 60 * 24));
                document.getElementById('dias').value = Math.max(1, diff);
            }
        }

        function aplicarChegadaAtual() {
            const campoChegada = document.getElementById('data_prevista');
            if (!campoChegada) return;

            campoChegada.value = DateHelper.nowInput();
            calcularDias();
            renderTaxas();
            atualizarResumo();
            carregarResumoFinanceiro();
        }

        // ===== NOVO CLIENTE =====

        document.getElementById('btnNovoCliente')?.addEventListener('click', function() {
            window.parent.openOrSwitchToTab('/pages/clientes/adicionar', <?= $jsT('modules.locacoes.buttons.new_client') ?>, 'fas fa-user-plus');
        });

        // ===== PLANO (mostrar/esconder campos) =====

        // Mapeamento plano (codigo do select) -> coluna de diaria no grupo.
        // Select usa "DI" historicamente; "KP" eh o codigo no banco apos migration 00231.
        const MAPA_DIARIA_POR_PLANO = {
            'KL':  'valor_plano_km_livre',
            'KMC': 'valor_plano_km_controlado',
            'DI':  'valor_plano_km_pago',
            'KP':  'valor_plano_km_pago',
        };
        const MAPA_PRECO_DIAS_POR_PLANO = {
            'KL': 'km_livre',
            'KML': 'km_livre',
            'KMC': 'km_controlado',
            'DI': 'diaria',
            'KP': 'diaria',
            'DIA': 'diaria',
        };

        let diariaValorManual = isEditing;

        function definirOrigemDiaria(origem) {
            const origemEl = document.getElementById('diaria_valor_origem');
            if (origemEl) origemEl.value = origem;
        }

        function obterValorProgressivoDiaria(plano, dias) {
            const tipoPreco = MAPA_PRECO_DIAS_POR_PLANO[plano] || 'diaria';
            const faixas = precosDiasGrupoAtuais?.[tipoPreco] || [];
            let faixaAplicada = null;

            faixas.forEach(faixa => {
                const inicio = parseInt(faixa.dia_inicio, 10) || 0;
                const fim = faixa.dia_fim === null || faixa.dia_fim === undefined || faixa.dia_fim === ''
                    ? Infinity
                    : (parseInt(faixa.dia_fim, 10) || 0);

                if (dias >= inicio && dias <= fim && (!faixaAplicada || inicio > faixaAplicada.inicio)) {
                    faixaAplicada = { inicio, valor: parseFloat(faixa.valor || 0) };
                }
            });

            return faixaAplicada ? faixaAplicada.valor : null;
        }

        function aplicarValorDiaria(forcar = false) {
            const el = document.getElementById('diaria_valor');
            if (!el || !valoresGrupoAtuais) return;
            if (diariaValorManual && !forcar) return;

            const plano = document.getElementById('plano')?.value || 'KL';
            const campo = MAPA_DIARIA_POR_PLANO[plano] || 'valor_plano_km_livre';
            const dias = parseInt(document.getElementById('dias')?.value, 10) || 1;
            const valorProgressivo = obterValorProgressivoDiaria(plano, dias);
            const valor = valorProgressivo !== null
                ? valorProgressivo
                : parseFloat(valoresGrupoAtuais[campo] || 0);

            el.value = valor.toFixed(2).replace('.', ',');
            diariaValorManual = false;
            definirOrigemDiaria('auto');
            sincronizarValorKmControlado();
            atualizarResumo();
        }

        function sincronizarValorKmControlado() {
            const plano = document.getElementById('plano')?.value || 'KL';
            const diaria = document.getElementById('diaria_valor');
            const kmControlado = document.getElementById('km_controlado_valor');
            if (plano === 'KMC' && diaria && kmControlado) {
                kmControlado.value = diaria.value || '0,00';
            }
        }

        function atualizarCamposPlano() {
            const plano = document.getElementById('plano')?.value || 'KL';
            const kmFields = document.querySelectorAll('.plano-km-field');
            const kmcFields = document.querySelectorAll('.plano-kmc-field');

            kmFields.forEach(el => el.classList.toggle('hidden', plano === 'KL'));
            kmcFields.forEach(el => el.classList.toggle('hidden', plano !== 'KMC'));

            diariaValorManual = false;
            aplicarValorDiaria(true);
        }

        document.getElementById('plano')?.addEventListener('change', atualizarCamposPlano);
        document.getElementById('diaria_valor')?.addEventListener('input', () => {
            diariaValorManual = true;
            definirOrigemDiaria('manual');
            sincronizarValorKmControlado();
        });

        // ===== AUTO-FILL VEICULO (odometro + combustivel) =====

        let veiculosDisponiveis = [];

        function atualizarLabelsTanque(tipoCombustivel) {
            const label = document.getElementById('labelCombustivelIni');
            if (label) {
                label.textContent = FuelLabels.isElectric(tipoCombustivel) ? i18n.chargeOut : i18n.fuelOut;
            }
            const select = document.getElementById('combustivel_ini');
            FuelLabels.updateSelectOptions(select, tipoCombustivel, i18n.fuelFull, i18n.fuelReserve);
        }

        function carregarDadosVeiculo(veiculoId) {
            const v = veiculosDisponiveis.find(v => v.id == veiculoId);
            if (v) {
                document.getElementById('odometro_ini').value = Km.format(v.odometro || 0);
                atualizarLabelsTanque(v.tipo_combustivel || '');
                document.getElementById('combustivel_ini').value = v.tanque_fracao || '';
            }
        }

        document.getElementById('id_veiculo')?.addEventListener('change', function() {
            if (this.value) {
                carregarDadosVeiculo(this.value);
            }
        });

        // ===== AUTO-FILL VALORES DO GRUPO + CASCATA VEICULOS =====

        // Cache por grupo+filial — multi-moeda, cada filial tem sua tabela
        let valoresGrupoCache = {};
        let valoresGrupoAtuais = null;
        let precosDiasGrupoAtuais = {};

        async function carregarValoresGrupo(grupoId) {
            const filialId = document.getElementById('id_matriz_filial_retirada')?.value;
            const cacheKey = `${grupoId}:${filialId || 0}`;

            if (valoresGrupoCache[cacheKey]) {
                preencherValoresGrupo(valoresGrupoCache[cacheKey]);
                return;
            }

            try {
                // Prioriza endpoint multi-moeda se filial está selecionada
                if (filialId) {
                    const res = await API.get(`/api/grupos/${grupoId}/precos-filial/${filialId}`);
                    if (res.success && res.data?.valores) {
                        valoresGrupoCache[cacheKey] = res.data;
                        preencherValoresGrupo(res.data);
                        return;
                    }
                }
                // Fallback: valores globais do grupo (compat com transição)
                const result = await API.get(`/api/grupos/${grupoId}`);
                if (result.success && result.data) {
                    valoresGrupoCache[cacheKey] = result.data;
                    preencherValoresGrupo(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar valores do grupo:', error);
            }
        }

        function preencherValoresGrupo(payload) {
            const valores = payload?.valores || payload || {};
            valoresGrupoAtuais = valores;
            precosDiasGrupoAtuais = payload?.precos_dias || {};

            const setCurrency = (id, val) => {
                const el = document.getElementById(id);
                if (el && val) el.value = parseFloat(val).toFixed(2).replace('.', ',');
            };
            const setNumber = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val || 0;
            };

            // Valor da diaria depende do plano selecionado (aplicado no final).
            setCurrency('km_valor', valores.valor_km_excedente);
            setCurrency('km_controlado_valor', valores.valor_plano_km_controlado);
            setNumber('km_controlado_franquia', valores.km_franquia);

            // Seguros
            setCurrency('seguro_carro_valor', valores.valor_seguro_carro);
            setCurrency('cobertura_carro_valor', valores.cobertura_carro);
            setCurrency('seguro_terceiros_valor', valores.valor_seguro_terceiros);
            setCurrency('cobertura_terceiros_valor', valores.cobertura_terceiros);

            // Tolerancia e Extras
            setNumber('minuto_tolerancia', valores.minutos_tolerancia);
            setCurrency('valor_tolerancia', valores.valor_tolerancia);
            setCurrency('valor_km_retorno', valores.valor_km_retorno);
            setCurrency('valor_condutor_adicional', valores.valor_condutor_adicional);

            aplicarValorDiaria(true);
        }

        function garantirVeiculoAtualNoSelect(selectVeiculo) {
            if (!selectVeiculo || !isEditing || !locacaoData?.id_veiculo) {
                return;
            }

            if (['R', 'P'].includes(locacaoData?.status || '')) {
                return;
            }

            const idVeiculoAtual = String(locacaoData.id_veiculo);
            const jaExiste = Array.from(selectVeiculo.options).some(opt => opt.value === idVeiculoAtual);

            if (!jaExiste) {
                const textoVeiculo = locacaoData.veiculo_info || locacaoData.veiculo_placa || idVeiculoAtual;
                selectVeiculo.add(new Option(textoVeiculo, idVeiculoAtual));
            }
        }

        function aplicarBloqueioChosenVeiculo(bloqueado) {
            const selectVeiculo = document.getElementById('id_veiculo');
            if (!selectVeiculo?.chosenSelect?.wrapper) {
                return;
            }

            const chosen = selectVeiculo.chosenSelect;
            if (bloqueado && typeof chosen.close === 'function') {
                chosen.close();
            }

            chosen.wrapper.style.pointerEvents = bloqueado ? 'none' : '';
            chosen.wrapper.style.opacity = bloqueado ? '0.6' : '';
            chosen.display?.setAttribute('aria-disabled', bloqueado ? 'true' : 'false');
        }

        async function carregarVeiculosPorGrupo(grupoId) {
            const filialId = document.getElementById('id_matriz_filial_retirada')?.value;
            const selectVeiculo = document.getElementById('id_veiculo');
            const valorAtual = selectVeiculo?.value || (isEditing && locacaoData?.id_veiculo ? String(locacaoData.id_veiculo) : '');

            if (!selectVeiculo) {
                return;
            }

            if (vehicleChangeLocked) {
                garantirVeiculoAtualNoSelect(selectVeiculo);
                if (valorAtual && Array.from(selectVeiculo.options).some(opt => opt.value === String(valorAtual))) {
                    selectVeiculo.value = String(valorAtual);
                }
                if (selectVeiculo.chosenSelect) {
                    selectVeiculo.chosenSelect.refresh();
                    aplicarBloqueioChosenVeiculo(true);
                }
                return;
            }

            if (!grupoId || !filialId) {
                selectVeiculo.innerHTML = '<option value=""><?= t('modules.locacoes.messages.select_group_first') ?></option>';
                if (selectVeiculo.chosenSelect) selectVeiculo.chosenSelect.refresh();
                return;
            }

            try {
                const result = await API.get('/api/veiculos/por-grupo', {
                    id_grupo: grupoId,
                    id_filial: filialId,
                    contexto: isStatusReserva() ? 'reserva' : ''
                });

                if (result.success) {
                    veiculosDisponiveis = result.data;
                    selectVeiculo.innerHTML = '<option value=""><?= t('modules.locacoes.messages.select_vehicle') ?></option>';
                    result.data.forEach(v => {
                        selectVeiculo.add(new Option(`${v.placa} - ${v.modelo}`, v.id));
                    });
                    garantirVeiculoAtualNoSelect(selectVeiculo);
                    if (valorAtual && Array.from(selectVeiculo.options).some(opt => opt.value === String(valorAtual))) {
                        selectVeiculo.value = String(valorAtual);
                    }
                    if (selectVeiculo.chosenSelect) selectVeiculo.chosenSelect.refresh();
                }
            } catch (error) {
                console.error('Erro ao carregar veiculos:', error);
            }
        }

        function isStatusReserva() {
            const status = document.getElementById('locacaoStatus')?.value || 'R';
            return ['R', 'P'].includes(status);
        }

        async function carregarGrupos() {
            const filialId = document.getElementById('id_matriz_filial_retirada')?.value;
            const selectGrupo = document.getElementById('id_grupo');
            if (!filialId) return;

            try {
                const params = { id_filial: filialId };
                if (isStatusReserva()) {
                    const dataSaida = document.getElementById('data_saida')?.value || '';
                    const dataPrevista = document.getElementById('data_prevista')?.value || '';
                    if (dataSaida && dataPrevista) {
                        params.contexto = 'reserva';
                        params.data_saida = dataSaida;
                        params.data_prevista = dataPrevista;
                    }
                }

                const valorAtual = selectGrupo.value;
                const result = await API.get('/api/grupos', params);
                if (result.success) {
                    selectGrupo.innerHTML = '<option value=""><?= t('common.labels.select') ?></option>';
                    result.data.forEach(g => {
                        const disp = g.qtd_disponiveis !== undefined ? ` (${g.qtd_disponiveis})` : '';
                        selectGrupo.add(new Option(g.nome + disp, g.id));
                    });
                    if (valorAtual && Array.from(selectGrupo.options).some(opt => opt.value === valorAtual)) {
                        selectGrupo.value = valorAtual;
                    }
                    if (selectGrupo.chosenSelect) selectGrupo.chosenSelect.refresh();
                }
            } catch (e) {
                console.error('Erro ao carregar grupos:', e);
            }
        }

        document.getElementById('id_grupo')?.addEventListener('change', async function() {
            const grupoId = this.value;
            if (!grupoId) {
                const selectVeiculo = document.getElementById('id_veiculo');
                selectVeiculo.innerHTML = '<option value=""><?= t('modules.locacoes.messages.select_group_first') ?></option>';
                if (selectVeiculo.chosenSelect) selectVeiculo.chosenSelect.refresh();
                return;
            }

            await carregarValoresGrupo(grupoId);
            await carregarVeiculosPorGrupo(grupoId);
        });

        // Recarregar grupos e veiculos quando filial de retirada muda
        document.getElementById('id_matriz_filial_retirada')?.addEventListener('change', function() {
            // Invalida cache de valores — cada filial tem sua moeda/tabela
            valoresGrupoCache = {};
            valoresGrupoAtuais = null;
            carregarGrupos();
            const grupoId = document.getElementById('id_grupo')?.value;
            if (grupoId) {
                carregarValoresGrupo(grupoId);
                carregarVeiculosPorGrupo(grupoId);
            }
            verificarKmRetorno();
        });

        // Calcular km retorno quando filial de devolucao muda
        document.getElementById('id_matriz_filial_devolucao')?.addEventListener('change', function() {
            verificarKmRetorno();
        });

        // ===== KM RETORNO (TAXA AUTOMATICA) =====

        let _kmRetornoTaxaIndex = -1; // indice da taxa automatica no array taxas

        async function verificarKmRetorno() {
            const retiradaId = document.getElementById('id_matriz_filial_retirada')?.value;
            const devolucaoId = document.getElementById('id_matriz_filial_devolucao')?.value;

            // Remover taxa existente de km retorno
            removerTaxaKmRetorno();

            // Se filiais iguais, vazias, ou sem valor_km_retorno, nao criar taxa
            if (!retiradaId || !devolucaoId || retiradaId === devolucaoId) {
                atualizarResumo();
                return;
            }

            const valorKmRetorno = parseFloat(
                String(document.getElementById('valor_km_retorno')?.value || '0')
                    .replace(/\./g, '').replace(',', '.')
            ) || 0;

            if (valorKmRetorno <= 0) {
                atualizarResumo();
                return;
            }

            try {
                const result = await API.get(`/api/matrizes-filiais/distancia?origem=${retiradaId}&destino=${devolucaoId}`);
                if (result.success && result.data && result.data.distancia_km > 0) {
                    const distKm = result.data.distancia_km;
                    const valorTotal = distKm * valorKmRetorno;

                    taxas.push({
                        id_taxa: null,
                        nome: `Km Retorno (${distKm} km)`,
                        quantidade: 1,
                        valor_unitario: valorTotal.toFixed(2).replace('.', ','),
                        _auto_km_retorno: true
                    });

                    renderTaxas();
                    atualizarResumo();
                }
            } catch (e) {
                console.error('Erro ao calcular distância km retorno:', e);
            }
        }

        function removerTaxaKmRetorno() {
            const totalAntes = taxas.length;
            for (let i = taxas.length - 1; i >= 0; i--) {
                const taxa = taxas[i];
                if (taxa._auto_km_retorno === true || String(taxa.nome || '').startsWith('Km Retorno')) {
                    taxas.splice(i, 1);
                }
            }
            if (taxas.length !== totalAntes) {
                renderTaxas();
            }
        }

        // ===== INTERVENIENTES =====

        // Helpers para formatacao e endereco
        function formatarCpfCnpj(valor) {
            if (!valor) return '';
            const v = String(valor).replace(/\D/g, '');
            if (v.length === 11) return v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            if (v.length === 14) return v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            return v;
        }

        function limparDocumento(valor) {
            return valor ? String(valor).replace(/\D/g, '') : '';
        }

        function formatarDataBr(data) {
            if (!data) return '';
            return DateHelper.format(data);
        }

        function montarEnderecoCompleto(cliente) {
            if (!cliente) return '';
            const partes = [];
            if (cliente.rua) partes.push(cliente.rua + (cliente.numero ? ', ' + cliente.numero : ''));
            if (cliente.complemento) partes.push(cliente.complemento);
            if (cliente.bairro) partes.push(cliente.bairro);
            if (cliente.cidade) partes.push(cliente.cidade);
            if (cliente.estado) partes.push(cliente.estado);
            if (cliente.pais) partes.push(cliente.pais);
            if (cliente.cep) partes.push(i18n.zipLabel + ' ' + cliente.cep);
            return partes.join(' - ');
        }

        async function preencherDadosClienteEmCard(card, clienteId) {
            if (!clienteId) {
                card.querySelector('.pessoa-id').value = '';
                card.querySelector('.pessoa-cc').value = '';
                const cnhEl = card.querySelector('.pessoa-cnh');
                if (cnhEl) cnhEl.value = '';
                const cnhValEl = card.querySelector('.pessoa-cnh-validade');
                if (cnhValEl) cnhValEl.value = '';
                const enderecoEl = card.querySelector('.pessoa-endereco');
                if (enderecoEl) enderecoEl.value = '';
                card.querySelector('.pessoa-cnh-alerta')?.classList.add('hidden');
                return;
            }

            try {
                const result = await API.get('/api/clientes/' + clienteId);
                if (result.success) {
                    const cliente = result.data;
                    card.querySelector('.pessoa-id').value = cliente.id;
                    card.querySelector('.pessoa-cc').value = formatarCpfCnpj(cliente.cpf_cnpj);

                    const cnhNumero = cliente.cnh_numero || cliente.cnh || '';
                    const cnhEl = card.querySelector('.pessoa-cnh');
                    if (cnhEl) cnhEl.value = cnhNumero;

                    const cnhValEl = card.querySelector('.pessoa-cnh-validade');
                    if (cnhValEl) cnhValEl.value = formatarDataBr(cliente.cnh_validade);

                    const enderecoEl = card.querySelector('.pessoa-endereco');
                    if (enderecoEl) enderecoEl.value = montarEnderecoCompleto(cliente);

                    const alerta = card.querySelector('.pessoa-cnh-alerta');
                    if (alerta) {
                        if (cliente.cnh_validade && DateHelper.diffDays(DateHelper.todayISO(), cliente.cnh_validade) < 0) {
                            alerta.classList.remove('hidden');
                        } else {
                            alerta.classList.add('hidden');
                        }
                    }
                }
            } catch (e) {
                console.error('Erro ao buscar cliente:', e);
            }
        }

        function adicionarPessoa(tipo, containerId, templateId, labelKey, dados = null) {
            const container = document.getElementById(containerId);
            const template = document.getElementById(templateId);
            const clone = template.content.cloneNode(true);
            const card = clone.querySelector('.pessoa-card');
            const count = container.querySelectorAll('.pessoa-card').length + 1;

            card.dataset.index = count;
            card.dataset.tipo = tipo;
            card.querySelector('.pessoa-label').textContent = labelKey.replace(':num', count);

            card.querySelector('.btn-remover-pessoa').addEventListener('click', function() {
                card.remove();
                if (tipo === 'condutor') atualizarResumo();
            });

            // Preencher dados existentes (edicao)
            if (dados) {
                const select = card.querySelector('.pessoa-select-cliente');

                if (dados.id) {
                    // Formato novo: cliente vinculado por id
                    card.querySelector('.pessoa-id').value = dados.id;
                    const opt = new Option(dados.nome || '', dados.id, true, true);
                    select.appendChild(opt);
                    card.querySelector('.pessoa-cc').value = formatarCpfCnpj(dados.cc);
                    const cnhEl = card.querySelector('.pessoa-cnh');
                    if (cnhEl) cnhEl.value = dados.cn || dados.cnh || '';
                    const cnhValEl = card.querySelector('.pessoa-cnh-validade');
                    if (cnhValEl) cnhValEl.value = formatarDataBr(dados.va || dados.cnh_validade);
                    const enderecoEl = card.querySelector('.pessoa-endereco');
                    if (enderecoEl) enderecoEl.value = dados.endereco || '';

                    const alerta = card.querySelector('.pessoa-cnh-alerta');
                    const va = dados.va || dados.cnh_validade;
                    if (alerta && va && DateHelper.diffDays(DateHelper.todayISO(), va) < 0) {
                        alerta.classList.remove('hidden');
                    }
                } else if (dados.nome) {
                    // Formato legado (sem id): exibir nome no select e dados nos campos readonly
                    const opt = new Option(dados.nome, '', true, true);
                    select.appendChild(opt);
                    card.querySelector('.pessoa-cc').value = formatarCpfCnpj(dados.cc);
                    const cnhEl = card.querySelector('.pessoa-cnh');
                    if (cnhEl) cnhEl.value = dados.cnh || '';
                    const cnhValEl = card.querySelector('.pessoa-cnh-validade');
                    if (cnhValEl) cnhValEl.value = formatarDataBr(dados.cnh_validade);
                }
            }

            container.appendChild(clone);

            // Inicializar chosen-select neste novo card
            if (window.initChosenSelects) {
                window.initChosenSelects();
            }

            // Listener: quando seleciona um cliente, busca dados e preenche
            const selectInserido = container.querySelector('.pessoa-card:last-child .pessoa-select-cliente');
            selectInserido?.addEventListener('change', async function() {
                await preencherDadosClienteEmCard(selectInserido.closest('.pessoa-card'), this.value);
                if (tipo === 'condutor') atualizarResumo();
            });

            if (tipo === 'condutor') atualizarResumo();
        }

        document.getElementById('btnAdicionarCondutor')?.addEventListener('click', () => {
            adicionarPessoa('condutor', 'listaCondutores', 'templateCondutorCard', <?= $jsT('modules.locacoes.person.conductor_label', ['num' => ':num']) ?>);
        });

        document.getElementById('btnAdicionarFiador')?.addEventListener('click', () => {
            adicionarPessoa('fiador', 'listaFiadores', 'templatePessoaCard', <?= $jsT('modules.locacoes.person.guarantor_label', ['num' => ':num']) ?>);
        });

        document.getElementById('btnAdicionarAvalista')?.addEventListener('click', () => {
            adicionarPessoa('avalista', 'listaAvalistas', 'templatePessoaCard', <?= $jsT('modules.locacoes.person.endorser_label', ['num' => ':num']) ?>);
        });

        document.getElementById('btnAdicionarTestemunha')?.addEventListener('click', () => {
            adicionarPessoa('testemunha', 'listaTestemunhas', 'templatePessoaCard', <?= $jsT('modules.locacoes.person.witness_label', ['num' => ':num']) ?>);
        });

        // ===== TAXAS =====

        const taxas = [];
        let taxasDisponiveis = [];
        let taxaSelecionadaAtual = null;
        let totalPagoFinanceiro = 0;
        let totalReembolsadoFinanceiro = 0;
        let totalAvariasFinanceiro = 0;

        // Carregar cache de todas as taxas disponiveis
        async function carregarTaxasDisponiveis() {
            try {
                const result = await API.get('/api/taxas-e-servicos/buscar');
                if (result.success && result.data) {
                    taxasDisponiveis = result.data;
                }
            } catch (error) {
                console.error('Erro ao carregar taxas:', error);
            }
        }

        // Carregar taxas automaticas (aplicar=S, onde_usar=SIS) para novas locacoes
        async function carregarTaxasAutomaticas() {
            try {
                const result = await API.get('/api/taxas-e-servicos/auto-aplicar');
                if (result.success && result.data && result.data.length > 0) {
                    result.data.forEach(t => {
                        taxas.push({
                            id_taxa: t.id,
                            nome: t.text,
                            base_calculo: t.base_calculo || 'FIX',
                            tipo_valor: t.tipo_valor || 'MON',
                            quantidade: 1,
                            valor_unitario: t.valor ? Currency.format(t.valor) : '0,00',
                            _auto: true
                        });
                    });
                    renderTaxas();
                    atualizarResumo();
                }
            } catch (error) {
                console.error('Erro ao carregar taxas automaticas:', error);
            }
        }

        // Preencher campos ao selecionar taxa no chosen-select
        document.getElementById('taxa_select')?.addEventListener('change', function() {
            const taxa = taxasDisponiveis.find(t => t.id == this.value);
            const simboloEl = document.querySelector('#taxa_valor')?.parentElement?.querySelector('.currency-symbol');
            const inputValor = document.getElementById('taxa_valor');

            if (taxa) {
                taxaSelecionadaAtual = {
                    id: taxa.id,
                    nome: taxa.text || '',
                    valor: taxa.valor,
                    base_calculo: taxa.base_calculo || 'FIX',
                    tipo_valor: taxa.tipo_valor || 'MON'
                };

                document.getElementById('taxa_nome').value = taxaSelecionadaAtual.nome;
                document.getElementById('taxa_qtd').value = 1;

                if (taxaSelecionadaAtual.tipo_valor === 'POR') {
                    if (simboloEl) simboloEl.textContent = '%';
                    inputValor.value = taxa.valor ? taxa.valor.toString().replace('.', ',') : '';
                } else {
                    if (simboloEl) simboloEl.textContent = 'R$';
                    inputValor.value = taxa.valor ? Currency.format(taxa.valor) : '';
                }
            } else {
                taxaSelecionadaAtual = null;
                document.getElementById('taxa_nome').value = '';
                document.getElementById('taxa_valor').value = '';
                if (simboloEl) simboloEl.textContent = 'R$';
            }
        });

        document.getElementById('btnAdicionarTaxa')?.addEventListener('click', function() {
            const select = document.getElementById('taxa_select');
            const nome = document.getElementById('taxa_nome').value;
            const qtd = parseInt(document.getElementById('taxa_qtd').value) || 1;
            const valor = document.getElementById('taxa_valor').value;

            if (!nome) return;

            taxas.push({
                id_taxa: select.value,
                nome: nome,
                base_calculo: taxaSelecionadaAtual?.base_calculo || 'FIX',
                tipo_valor: taxaSelecionadaAtual?.tipo_valor || 'MON',
                quantidade: qtd,
                valor_unitario: valor
            });

            renderTaxas();
            atualizarResumo();

            // Limpar campos
            taxaSelecionadaAtual = null;
            if (select.chosenSelect) {
                select.chosenSelect.clear();
            }
            document.getElementById('taxa_nome').value = '';
            document.getElementById('taxa_qtd').value = '1';
            document.getElementById('taxa_valor').value = '';
            const simboloEl = document.querySelector('#taxa_valor')?.parentElement?.querySelector('.currency-symbol');
            if (simboloEl) simboloEl.textContent = 'R$';
        });

        // Aliases para Currency global
        const parseCurrency = (val) => Currency.parse(val);
        const fmtCurrency = (val) => Currency.format(val, true);

        // Calcula valor base da locacao (diaria + seguros) x dias para taxas percentuais
        function calcularValorTotalLocacao() {
            const dias = parseInt(document.getElementById('dias')?.value) || 1;
            let valorDiario = parseCurrency(document.getElementById('diaria_valor')?.value);

            if (document.getElementById('seguro_carro')?.checked) {
                valorDiario += parseCurrency(document.getElementById('seguro_carro_valor')?.value);
            }
            if (document.getElementById('seguro_terceiros')?.checked) {
                valorDiario += parseCurrency(document.getElementById('seguro_terceiros_valor')?.value);
            }

            return valorDiario * dias;
        }

        function calcularValorCombustivelDevolucao() {
            const deficit = calcularDeficitCombustivelDevolucao();
            return deficit.fracoes * deficit.valorPorFracao;
        }

        function calcularDeficitCombustivelDevolucao() {
            const status = document.getElementById('locacaoStatus')?.value || 'R';
            if (status !== 'F') return { fracoes: 0, valorPorFracao: 0 };

            const combIni = parseInt(document.getElementById('combustivel_ini')?.value);
            const combFim = parseInt(document.getElementById('combustivel_fim')?.value);
            const valorPorFracao = parseFloat(locacaoData?.veiculo_valor_por_fracao) || 0;

            if (isNaN(combIni) || isNaN(combFim)) return { fracoes: 0, valorPorFracao };

            return {
                fracoes: Math.max(0, combIni - combFim),
                valorPorFracao
            };
        }

        function calcularKmExcedenteDevolucao() {
            const status = document.getElementById('locacaoStatus')?.value || 'R';
            const plano = document.getElementById('plano')?.value || 'KL';
            if (status !== 'F' || plano !== 'KMC') {
                return { kmRodados: 0, kmPermitido: 0, kmExcedente: 0, valorUnitario: 0, valorTotal: 0 };
            }

            const odometroIni = parseInt(String(document.getElementById('odometro_ini')?.value || '0').replace(/\D/g, ''), 10) || 0;
            const odometroFim = parseInt(String(document.getElementById('odometro_fim')?.value || '0').replace(/\D/g, ''), 10) || 0;
            const kmRodados = odometroFim > odometroIni ? odometroFim - odometroIni : 0;
            const franquia = parseInt(document.getElementById('km_controlado_franquia')?.value, 10) || 0;
            const dias = parseInt(document.getElementById('dias')?.value, 10) || 1;
            const kmPermitido = franquia * dias;
            const kmExcedente = Math.max(0, kmRodados - kmPermitido);
            const valorUnitario = parseCurrency(document.getElementById('km_valor')?.value);

            return {
                kmRodados,
                kmPermitido,
                kmExcedente,
                valorUnitario,
                valorTotal: kmExcedente * valorUnitario
            };
        }

        function calcularTotalPagarFormulario() {
            const dias = parseInt(document.getElementById('dias')?.value) || 1;
            const desconto = parseCurrency(document.getElementById('valor_desconto')?.value);
            const valorTotalLocacao = calcularValorTotalLocacao();
            const totalTaxas = taxas.reduce((total, taxa) => {
                return total + calcularValorTotalTaxa(taxa, dias, valorTotalLocacao);
            }, 0);
            const qtdCondutores = document.getElementById('listaCondutores')?.querySelectorAll('.pessoa-card').length || 0;
            const valorCondutorUnit = parseCurrency(document.getElementById('valor_condutor_adicional')?.value);
            const valorCombustivel = calcularValorCombustivelDevolucao();
            const valorKmExcedente = calcularKmExcedenteDevolucao().valorTotal;

            return Math.max(0, valorTotalLocacao + totalTaxas + (qtdCondutores * valorCondutorUnit) + valorKmExcedente + valorCombustivel - desconto);
        }

        // Calcula valor total de uma taxa conforme base_calculo e tipo_valor
        function calcularValorTotalTaxa(taxa, dias, valorTotalLocacao) {
            const valor = parseCurrency(taxa.valor_unitario);
            const baseCalculo = taxa.base_calculo || 'FIX';
            const tipoValor = taxa.tipo_valor || 'MON';
            const quantidade = parseInt(taxa.quantidade) || 1;

            let valorBase;
            if (tipoValor === 'POR') {
                if (baseCalculo === 'VLT') {
                    valorBase = valorTotalLocacao * (valor / 100);
                } else {
                    const valorDiario = dias > 0 ? valorTotalLocacao / dias : 0;
                    valorBase = valorDiario * (valor / 100);
                }
            } else {
                valorBase = valor;
            }

            if (baseCalculo === 'PER') {
                return valorBase * quantidade * dias;
            } else {
                return valorBase * quantidade;
            }
        }

        function renderTaxas() {
            const container = document.getElementById('listaTaxas');
            if (taxas.length === 0) {
                container.innerHTML = `<p class="text-slate-500 text-center py-4"><?= t('modules.locacoes.fees.no_fees') ?></p>`;
                return;
            }

            const dias = parseInt(document.getElementById('dias')?.value) || 1;
            const valorTotalLocacao = calcularValorTotalLocacao();

            container.innerHTML = '';
            taxas.forEach((taxa, idx) => {
                const template = document.getElementById('templateTaxaItem');
                const clone = template.content.cloneNode(true);
                const item = clone.querySelector('.taxa-item');

                const valorTotal = calcularValorTotalTaxa(taxa, dias, valorTotalLocacao);

                item.dataset.index = idx;
                item.querySelector('.taxa-nome').textContent = taxa.nome;
                item.querySelector('.taxa-qtd').textContent = `${taxa.quantidade}x`;

                // Valor unitario formatado conforme tipo
                let valorUnitFormatado;
                if (taxa.tipo_valor === 'POR') {
                    valorUnitFormatado = `${parseCurrency(taxa.valor_unitario)}%`;
                } else if (taxa.base_calculo === 'PER') {
                    valorUnitFormatado = `${fmtCurrency(parseCurrency(taxa.valor_unitario))}/dia`;
                } else {
                    valorUnitFormatado = fmtCurrency(parseCurrency(taxa.valor_unitario));
                }
                item.querySelector('.taxa-valor-unit').textContent = valorUnitFormatado;
                item.querySelector('.taxa-valor-total').textContent = fmtCurrency(valorTotal);

                if (taxa._auto_km_retorno) {
                    // Taxa automatica km retorno: badge + esconder botao remover
                    const nomeEl = item.querySelector('.taxa-nome');
                    nomeEl.innerHTML += ' <span class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded ml-1">auto</span>';
                    item.querySelector('.btn-remover-taxa').style.display = 'none';
                } else {
                    if (taxa._auto) {
                        // Taxa auto-aplicada do cadastro: badge mas removivel
                        const nomeEl = item.querySelector('.taxa-nome');
                        nomeEl.innerHTML += ' <span class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded ml-1">auto</span>';
                    }
                    item.querySelector('.btn-remover-taxa').addEventListener('click', function() {
                        taxas.splice(idx, 1);
                        renderTaxas();
                        atualizarResumo();
                    });
                }

                container.appendChild(clone);
            });
        }

        // ===== RESUMO =====

        function atualizarResumo() {
            const tbody = document.getElementById('resumoBody');
            if (!tbody) return;

            const dias = parseInt(document.getElementById('dias')?.value) || 1;
            const desconto = parseCurrency(document.getElementById('valor_desconto')?.value);

            let html = '';
            let totalLocacao = 0;

            // ====== SECAO: VEICULO ======
            html += `<tr class="bg-slate-100"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs"><?= t('modules.locacoes.tabs.vehicle') ?></td></tr>`;
            html += `<tr class="text-xs text-slate-500 uppercase border-b border-slate-200">
                <td class="px-4 py-1"></td>
                <td class="px-4 py-1 text-center">${i18n.summary.qty}</td>
                <td class="px-4 py-1 text-center"><?= t('modules.locacoes.summary_section.days') ?></td>
                <td class="px-4 py-1 text-right"><?= t('modules.locacoes.insurance.value') ?></td>
                <td class="px-4 py-1 text-right">${i18n.summary.total}</td>
            </tr>`;

            // Diaria
            const diariaValor = parseCurrency(document.getElementById('diaria_valor')?.value);
            if (diariaValor > 0) {
                const sub = diariaValor * dias;
                totalLocacao += sub;
                html += `<tr class="border-b border-slate-100">
                    <td class="px-4 py-2"><?= t('modules.locacoes.plans.daily_rate') ?></td>
                    <td class="px-4 py-2 text-center">1</td>
                    <td class="px-4 py-2 text-center">${dias}</td>
                    <td class="px-4 py-2 text-right">${fmtCurrency(diariaValor)}</td>
                    <td class="px-4 py-2 text-right font-medium">${fmtCurrency(sub)}</td>
                </tr>`;
            }

            // Seguro veiculo
            if (document.getElementById('seguro_carro')?.checked) {
                const val = parseCurrency(document.getElementById('seguro_carro_valor')?.value);
                if (val > 0) {
                    const sub = val * dias;
                    totalLocacao += sub;
                    html += `<tr class="border-b border-slate-100">
                        <td class="px-4 py-2"><?= t('modules.locacoes.insurance.vehicle') ?></td>
                        <td class="px-4 py-2 text-center">1</td>
                        <td class="px-4 py-2 text-center">${dias}</td>
                        <td class="px-4 py-2 text-right">${fmtCurrency(val)}</td>
                        <td class="px-4 py-2 text-right font-medium">${fmtCurrency(sub)}</td>
                    </tr>`;
                }
            }

            // Seguro terceiros
            if (document.getElementById('seguro_terceiros')?.checked) {
                const val = parseCurrency(document.getElementById('seguro_terceiros_valor')?.value);
                if (val > 0) {
                    const sub = val * dias;
                    totalLocacao += sub;
                    html += `<tr class="border-b border-slate-100">
                        <td class="px-4 py-2"><?= t('modules.locacoes.insurance.third_party') ?></td>
                        <td class="px-4 py-2 text-center">1</td>
                        <td class="px-4 py-2 text-center">${dias}</td>
                        <td class="px-4 py-2 text-right">${fmtCurrency(val)}</td>
                        <td class="px-4 py-2 text-right font-medium">${fmtCurrency(sub)}</td>
                    </tr>`;
                }
            }

            // Condutor Adicional
            const qtdCondutores = document.getElementById('listaCondutores')?.querySelectorAll('.pessoa-card').length || 0;
            const valorCondutorUnit = parseCurrency(document.getElementById('valor_condutor_adicional')?.value);
            if (qtdCondutores > 0 && valorCondutorUnit > 0) {
                const subCondutores = qtdCondutores * valorCondutorUnit;
                totalLocacao += subCondutores;
                html += `<tr class="border-b border-slate-100">
                    <td class="px-4 py-2"><?= t('modules.locacoes.fields.additional_driver_value') ?></td>
                    <td class="px-4 py-2 text-center">${qtdCondutores}</td>
                    <td class="px-4 py-2 text-center">-</td>
                    <td class="px-4 py-2 text-right">${fmtCurrency(valorCondutorUnit)}</td>
                    <td class="px-4 py-2 text-right font-medium">${fmtCurrency(subCondutores)}</td>
                </tr>`;
            }

            // ====== SECAO: TAXAS E SERVICOS ======
            html += `<tr class="bg-slate-100"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs"><?= t('modules.locacoes.sections.fees_services') ?></td></tr>`;
            html += `<tr class="text-xs text-slate-500 uppercase border-b border-slate-200">
                <td class="px-4 py-1"></td>
                <td class="px-4 py-1 text-center">${i18n.summary.qty}</td>
                <td class="px-4 py-1 text-center">${i18n.summary.period}</td>
                <td class="px-4 py-1 text-right"><?= t('modules.locacoes.insurance.value') ?></td>
                <td class="px-4 py-1 text-right">${i18n.summary.total}</td>
            </tr>`;

            if (taxas.length === 0) {
                html += `<tr><td colspan="5" class="px-4 py-3 text-slate-400 italic text-center"><?= t('modules.locacoes.fees.no_fees') ?></td></tr>`;
            } else {
                const valorTotalLoc = calcularValorTotalLocacao();
                const valorDiario = dias > 0 ? valorTotalLoc / dias : 0;

                taxas.forEach(taxa => {
                    const valorTotal = calcularValorTotalTaxa(taxa, dias, valorTotalLoc);
                    totalLocacao += valorTotal;

                    const baseCalculo = taxa.base_calculo || 'FIX';
                    const tipoValor = taxa.tipo_valor || 'MON';
                    const valorUnit = parseCurrency(taxa.valor_unitario);
                    const quantidade = parseInt(taxa.quantidade) || 1;

                    // Coluna Periodo
                const colPeriodo = baseCalculo === 'PER' ? dias : i18n.summary.fixed;

                    // Coluna Valor e linha de explicacao
                    let colValor;
                    let linhaExplicacao = '';

                    if (tipoValor === 'POR') {
                        let valorBase, textoBase;
                        if (baseCalculo === 'VLT' || baseCalculo === 'FIX') {
                            valorBase = valorTotalLoc * (valorUnit / 100);
                            textoBase = fmtCurrency(valorTotalLoc);
                        } else {
                            valorBase = valorDiario * (valorUnit / 100);
                            textoBase = `${fmtCurrency(valorDiario)}/${i18n.summary.perDay}`;
                        }
                        colValor = fmtCurrency(valorBase);
                        linhaExplicacao = `<tr class="text-xs text-slate-400">
                            <td colspan="5" class="px-4 pb-2 pt-0">
                                <span class="ml-2">\u21b3 ${valorUnit}% ${i18n.summary.onBase} ${textoBase}</span>
                            </td>
                        </tr>`;
                    } else if (baseCalculo === 'PER') {
                        colValor = `${fmtCurrency(valorUnit)}/dia`;
                    } else {
                        colValor = fmtCurrency(valorUnit);
                    }

                    html += `<tr class="border-b border-slate-100">
                        <td class="px-4 py-2">${taxa.nome}</td>
                        <td class="px-4 py-2 text-center">${quantidade}</td>
                        <td class="px-4 py-2 text-center">${colPeriodo}</td>
                        <td class="px-4 py-2 text-right">${colValor}</td>
                        <td class="px-4 py-2 text-right font-medium">${fmtCurrency(valorTotal)}</td>
                    </tr>`;

                    if (linhaExplicacao) {
                        html += linhaExplicacao;
                    }
                });
            }

            const kmExcedenteDevolucao = calcularKmExcedenteDevolucao();
            const deficitCombustivel = calcularDeficitCombustivelDevolucao();
            const valorCombustivelDevolucao = deficitCombustivel.fracoes * deficitCombustivel.valorPorFracao;
            const mostrarSecaoDevolucao = kmExcedenteDevolucao.kmExcedente > 0 || deficitCombustivel.fracoes > 0;

            if (mostrarSecaoDevolucao) {
                html += `<tr class="bg-slate-100"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs">${i18n.summary.returnSection}</td></tr>`;
            }

            if (kmExcedenteDevolucao.kmExcedente > 0) {
                html += `<tr class="border-b border-slate-100">
                    <td class="px-4 py-2"><?= t('modules.locacoes.return_page.km_excess') ?></td>
                    <td class="px-4 py-2 text-center">${Km.format(kmExcedenteDevolucao.kmExcedente)}</td>
                    <td class="px-4 py-2 text-center">-</td>
                    <td class="px-4 py-2 text-right">${fmtCurrency(kmExcedenteDevolucao.valorUnitario)}</td>
                    <td class="px-4 py-2 text-right font-medium">${fmtCurrency(kmExcedenteDevolucao.valorTotal)}</td>
                </tr>`;
                totalLocacao += kmExcedenteDevolucao.valorTotal;
            }

            if (deficitCombustivel.fracoes > 0) {
                html += `<tr class="border-b border-slate-100">
                    <td class="px-4 py-2">${i18n.summary.fuel}</td>
                    <td class="px-4 py-2 text-center">${deficitCombustivel.fracoes}</td>
                    <td class="px-4 py-2 text-center">-</td>
                    <td class="px-4 py-2 text-right">${fmtCurrency(deficitCombustivel.valorPorFracao)}</td>
                    <td class="px-4 py-2 text-right font-medium">${fmtCurrency(valorCombustivelDevolucao)}</td>
                </tr>`;
                totalLocacao += valorCombustivelDevolucao;
            }

            // ====== SECAO: TOTAIS ======
            html += `<tr class="bg-slate-200"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs">${i18n.summary.totals}</td></tr>`;
            html += `<tr class="border-b border-slate-200">
                <td colspan="4" class="px-4 py-2 text-right"><?= t('modules.locacoes.summary_section.rental_total') ?></td>
                <td class="px-4 py-2 text-right font-medium">${fmtCurrency(totalLocacao)}</td>
            </tr>`;
            html += `<tr class="border-b border-slate-200">
                <td colspan="4" class="px-4 py-2 text-right text-red-600"><?= t('modules.locacoes.summary_section.discount_label') ?></td>
                <td class="px-4 py-2 text-right font-medium text-red-600">${fmtCurrency(desconto)}</td>
            </tr>`;
            if (totalAvariasFinanceiro > 0) {
                html += `<tr class="border-b border-slate-200">
                    <td colspan="4" class="px-4 py-2 text-right text-orange-600"><?= t('modules.locacoes.installments.total_damages') ?></td>
                    <td class="px-4 py-2 text-right font-medium text-orange-600">${fmtCurrency(totalAvariasFinanceiro)}</td>
                </tr>`;
            }

            const totalCobrado = totalLocacao - desconto + totalAvariasFinanceiro;
            html += `<tr class="border-b border-slate-200">
                <td colspan="4" class="px-4 py-2 text-right"><?= t('modules.locacoes.summary_section.total_to_pay') ?></td>
                <td class="px-4 py-2 text-right font-medium">${fmtCurrency(totalCobrado)}</td>
            </tr>`;
            html += `<tr class="border-b border-slate-200">
                <td colspan="4" class="px-4 py-2 text-right text-green-600">${i18n.summary.amountPaid}</td>
                <td class="px-4 py-2 text-right font-medium text-green-600">${fmtCurrency(totalPagoFinanceiro)}</td>
            </tr>`;
            if (totalReembolsadoFinanceiro > 0) {
                html += `<tr class="border-b border-slate-200">
                    <td colspan="4" class="px-4 py-2 text-right text-rose-600">${i18n.summary.amountRefunded}</td>
                    <td class="px-4 py-2 text-right font-medium text-rose-600">${fmtCurrency(totalReembolsadoFinanceiro)}</td>
                </tr>`;
            }
            const saldoPagar = Math.max(0, totalCobrado - totalPagoFinanceiro - totalReembolsadoFinanceiro);
            html += `<tr class="bg-orange-50">
                <td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700">${i18n.summary.balanceDue}</td>
                <td class="px-4 py-3 text-right font-bold text-xl text-orange-600">${fmtCurrency(saldoPagar)}</td>
            </tr>`;

            // ====== SECAO: GARANTIAS (Bloqueio + Caucao) ======
            // Bloqueio: ler do status area (valor real do hold) ou do campo input (novo bloqueio)
            const bloqueioStatusArea = document.getElementById('bloqueioStatusArea');
            const bloqueioStatusVisivel = bloqueioStatusArea && !bloqueioStatusArea.classList.contains('hidden');
            const bloqueioValorInfo = document.getElementById('bloqueioValorInfo')?.textContent || '';
            const bloqueioValor = bloqueioStatusVisivel ? parseCurrency(bloqueioValorInfo.replace(/[^\d,]/g, '')) : parseCurrency(document.getElementById('bloqueio_valor')?.value || '0');
            const bloqueioStatus = document.getElementById('bloqueioStatusBadge')?.textContent || '';
            const bloqueioStatusClass = document.getElementById('bloqueioStatusBadge')?.className || '';

            const caucaoValor = parseCurrency(document.getElementById('caucao_valor')?.value || '0');
            const caucaoFormaPagamentoEl = document.getElementById('id_forma_pagamento_caucao');
            const caucaoFormaPagamento = caucaoFormaPagamentoEl?.options[caucaoFormaPagamentoEl.selectedIndex]?.text || '';
            const caucaoPrazo = document.getElementById('caucao_prazo_devolucao')?.value || '';

            if (bloqueioValor > 0 || caucaoValor > 0) {
                html += `<tr class="bg-slate-200"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs"><?= t('modules.locacoes.summary_section.guarantees') ?></td></tr>`;

                if (bloqueioValor > 0) {
                    const cartaoInfo = document.getElementById('bloqueioCartaoInfo')?.textContent || '';
                    const isCaptured = bloqueioStatusClass.includes('bg-blue');
                    const badgeCls = isCaptured ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                    const badgeHtml = bloqueioStatus
                        ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeCls} ml-2">${bloqueioStatus}</span>`
                        : '';
                    html += `<tr class="border-b border-slate-200">
                        <td colspan="4" class="px-4 py-2">
                            <i class="fas fa-lock text-slate-400 mr-1"></i> <?= t('modules.locacoes.sections.block') ?>
                            ${badgeHtml}
                            ${cartaoInfo ? '<span class="text-xs text-slate-400 ml-2"><i class="fas fa-credit-card mr-1"></i>' + cartaoInfo + '</span>' : ''}
                        </td>
                        <td class="px-4 py-2 text-right font-medium">${fmtCurrency(bloqueioValor)}</td>
                    </tr>`;
                }

                if (caucaoValor > 0) {
                    const prazoLabel = caucaoPrazo !== '' ? caucaoPrazo + ' ' + <?= $jsT('modules.locacoes.summary_section.days') ?> : '';
                    html += `<tr class="border-b border-slate-200">
                        <td colspan="4" class="px-4 py-2">
                            <i class="fas fa-shield-alt text-slate-400 mr-1"></i> <?= t('modules.locacoes.sections.deposit') ?>
                            <span class="text-xs text-slate-400 ml-2">${caucaoFormaPagamento}${prazoLabel ? ' - <?= t('modules.locacoes.deposit.return_days') ?>: ' + prazoLabel : ''}</span>
                        </td>
                        <td class="px-4 py-2 text-right font-medium">${fmtCurrency(caucaoValor)}</td>
                    </tr>`;
                }
            }

            tbody.innerHTML = html;
        }

        async function validarFinanceiroFechamento() {
            if (!isEditing || !locacaoData) return [];

            try {
                const result = await API.get(`/api/locacoes/${locacaoData.id}/resumo-financeiro`);
                if (!result.success || !result.data) {
                    return [i18n.financialSummaryUnavailable];
                }

                const resumo = result.data;
                const totalParcelas = parseInt(resumo.total_parcelas) || 0;
                const totalLancado = parseFloat(resumo.total_lancado) || 0;
                const totalAvarias = parseFloat(resumo.total_avarias) || 0;
                const totalPagar = calcularTotalPagarFormulario() + totalAvarias;
                const diferenca = Math.round((totalPagar - totalLancado) * 100) / 100;
                const pendencias = [];

                if (totalParcelas <= 0) {
                    pendencias.push(i18n.registerFinancialInstallments);
                } else if (diferenca > 0.009) {
                    pendencias.push(i18n.installmentsTotalMismatch
                        .replace(':launched', fmtCurrency(totalLancado))
                        .replace(':expected', fmtCurrency(totalPagar)));
                }

                return pendencias;
            } catch (e) {
                console.error('Erro ao validar financeiro:', e);
                return [i18n.financialSummaryUnavailable];
            }
        }

        // ===== SUBMIT =====

        let devolucaoCreditoPendente = null;

        async function enviarLocacao(dados) {
            const btnSalvar = document.getElementById('btnSalvar');
            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

            try {
                const url = isEditing ? `/locacoes/${locacaoData.id}/atualizar` : '/locacoes/salvar';
                const result = await API.post(url, dados);

                if (result.success) {
                    const deveAtualizarPagamentosCaucao = isEditing
                        && locacaoData?.id
                        && document.getElementById('caucao_valor')
                        && document.getElementById('caucao_lancar_financeiro');

                    if (deveAtualizarPagamentosCaucao) {
                        await carregarParcelas();
                    }

                    window.parent.postMessage({
                        action: 'openAlert',
                        message: isEditing ? i18n.updated : i18n.created
                    }, '*');

                    if (dados.status === 'F') {
                        navegarPara('/pages/locacoes');
                        return;
                    }

                    if (!isEditing && result.data?.id) {
                        navegarPara('/pages/locacoes/editar/' + result.data.id);
                    }
                } else if (result.code === 'return_refund_required' && result.data?.valor_credito_devolucao) {
                    devolucaoCreditoPendente = { ...dados, gerar_credito_devolucao: '1' };
                    window.parent.postMessage({
                        action: 'openGenericConfirmModal',
                        title: i18n.returnRefundTitle,
                        message: i18n.returnRefundMessage
                            .replace(':amount', fmtCurrency(parseFloat(result.data.valor_credito_devolucao) || 0)),
                        confirmText: i18n.returnRefundConfirm
                    }, '*');
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
                }
            } catch (error) {
                console.error('Erro:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.saveError }, '*');
            } finally {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + <?= $jsT('common.buttons.save') ?>;
            }
        }

        document.getElementById('formLocacao')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            sincronizarValorKmControlado();
            const form = new FormData(this);
            const dados = Object.fromEntries(form.entries());
            dados.km_controlado_franquia = String(parseInt(dados.km_controlado_franquia || '0', 10) || 0);

            // datetime-local já envia formato ISO (YYYY-MM-DDTHH:MM)

            // Intervenientes
            dados.condutor_adicional = coletarPessoas('listaCondutores');
            dados.array_fiadores = coletarPessoas('listaFiadores');
            dados.array_avalistas = coletarPessoas('listaAvalistas');
            dados.array_testemunhas = coletarPessoas('listaTestemunhas');

            // Taxas
            dados.taxas = JSON.stringify(taxas);

            // Checkboxes
            dados.seguro_carro = document.getElementById('seguro_carro')?.checked ? 'S' : 'N';
            dados.seguro_terceiros = document.getElementById('seguro_terceiros')?.checked ? 'S' : 'N';

            // Dados de devolucao (quando status muda para F)
            if (dados.status === 'F' && isEditing && locacaoData?.status === 'A') {
                calcularDias();
                renderTaxas();
                atualizarResumo();
                carregarResumoFinanceiro();
                dados.data_chegada = document.getElementById('data_prevista')?.value || '';
                dados.dias = document.getElementById('dias')?.value || dados.dias || '1';
                delete dados.data_prevista;
                dados.odometro_fim = document.getElementById('odometro_fim')?.value || '';
                dados.combustivel_fim = document.getElementById('combustivel_fim')?.value || '';
                dados.combustivel_valor = calcularValorCombustivelDevolucao().toFixed(2);

                const camposPendentes = [];
                if (!dados.data_chegada) camposPendentes.push(i18n.arrivalDate);
                if (!dados.odometro_fim) camposPendentes.push(i18n.returnOdometerKm);
                if (dados.combustivel_fim === '') camposPendentes.push(i18n.returnFuel);

                const odometroSaida = Km.parse(document.getElementById('odometro_ini')?.value || '0');
                const odometroDevolucao = Km.parse(dados.odometro_fim || '0');
                if (dados.odometro_fim && odometroDevolucao < odometroSaida) {
                    camposPendentes.push(i18n.returnOdometerLessThanCheckout);
                }

                if (camposPendentes.length > 0) {
                    window.parent.postMessage({
                        action: 'openValidationModal',
                        errors: [{
                            tabName: i18n.returnVehicle,
                            tabId: null,
                            fields: camposPendentes
                        }]
                    }, '*');
                    document.getElementById('btnSalvar').disabled = false;
                    document.getElementById('btnSalvar').innerHTML = '<i class="fas fa-save mr-2"></i>' + <?= $jsT('common.buttons.save') ?>;
                    return;
                }

                const pendenciasFinanceiro = await validarFinanceiroFechamento();
                if (pendenciasFinanceiro.length > 0) {
                    window.parent.postMessage({
                        action: 'openValidationModal',
                        errors: [{
                            tabName: <?= $jsT('modules.locacoes.installments.title') ?>,
                            tabId: null,
                            fields: pendenciasFinanceiro
                        }]
                    }, '*');
                    document.getElementById('btnSalvar').disabled = false;
                    document.getElementById('btnSalvar').innerHTML = '<i class="fas fa-save mr-2"></i>' + <?= $jsT('common.buttons.save') ?>;
                    return;
                }
            }

            await enviarLocacao(dados);
        });

        function coletarPessoas(containerId) {
            const container = document.getElementById(containerId);
            const cards = container.querySelectorAll('.pessoa-card');
            const pessoas = [];

            cards.forEach(card => {
                const id = card.querySelector('.pessoa-id')?.value || '';
                const select = card.querySelector('.pessoa-select-cliente');
                const selectedOption = select?.options[select.selectedIndex];
                const nome = selectedOption?.text?.trim() || '';
                const cc = limparDocumento(card.querySelector('.pessoa-cc')?.value || '');
                const cnh = card.querySelector('.pessoa-cnh')?.value || '';
                const cnhValidade = card.querySelector('.pessoa-cnh-validade')?.value || '';
                const endereco = card.querySelector('.pessoa-endereco')?.value || '';

                if (nome && nome !== <?= $jsT('common.labels.select') ?>) {
                    const pessoa = { nome, cc };
                    if (id) pessoa.id = id;
                    if (cnh) pessoa.cnh = cnh;
                    if (cnhValidade) pessoa.cnh_validade = cnhValidade;
                    if (endereco) pessoa.endereco = endereco;
                    pessoas.push(pessoa);
                }
            });

            return pessoas;
        }

        // ===== CARREGAR FORMAS DE PAGAMENTO =====

        async function carregarFormasPagamento(selectedId = null) {
            try {
                const result = await API.get('/api/formas-pagamento');
                if (result.success) {
                    const select = document.getElementById('id_forma_pagamento');
                    select.innerHTML = '<option value=""><?= t('common.labels.select') ?></option>';
                    result.data.forEach(fp => {
                        const opt = document.createElement('option');
                        opt.value = fp.id;
                        opt.textContent = fp.nome;
                        if (selectedId && String(fp.id) === String(selectedId)) opt.selected = true;
                        select.appendChild(opt);
                    });
                    if (select.chosenSelect) {
                        select.chosenSelect.refresh();
                    }
                }
            } catch (e) {
                console.error('Erro ao carregar formas de pagamento:', e);
            }
        }

        // ===== CARREGAR DADOS DO CLIENTE =====

        document.getElementById('id_cliente')?.addEventListener('change', async function() {
            const clienteId = this.value;
            const dadosDiv = document.getElementById('dadosClienteSelecionado');

            if (!clienteId) {
                dadosDiv.classList.add('hidden');
                return;
            }

            try {
                const result = await API.get(`/api/clientes/${clienteId}`);
                if (result.success && result.data) {
                    const c = result.data;
                    document.getElementById('clienteDocumento').textContent = c.cpf_cnpj || '-';
                    document.getElementById('clienteCnh').textContent = c.cnh_numero || '-';

                    // CNH validade — vermelho se vencida
                    const cnhValidadeEl = document.getElementById('clienteCnhValidade');
                    if (c.cnh_validade) {
                        cnhValidadeEl.textContent = DateHelper.format(c.cnh_validade);
                        if (DateHelper.diffDays(DateHelper.todayISO(), c.cnh_validade) < 0) {
                            cnhValidadeEl.classList.add('text-red-600', 'font-bold');
                        } else {
                            cnhValidadeEl.classList.remove('text-red-600', 'font-bold');
                        }
                    } else {
                        cnhValidadeEl.textContent = '-';
                        cnhValidadeEl.classList.remove('text-red-600', 'font-bold');
                    }

                    document.getElementById('clienteTelefone').textContent = c.tel_cel || '-';
                    document.getElementById('clienteEmail').textContent = c.email || '-';
                    dadosDiv.classList.remove('hidden');
                }
            } catch (e) {
                console.error('Erro ao carregar cliente:', e);
            }
        });

        // ===== EDITAR: PREENCHER DADOS =====

        if (isEditing && locacaoData) {
            document.getElementById('pageTitle').textContent = <?= $jsT('modules.locacoes.edit_title') ?>;
            document.getElementById('registroId').value = locacaoData.id;
            document.getElementById('locacaoStatus').value = locacaoData.status;

            // Banner "reserva pendente" + botao Aprovar (confirma via modal global do parent)
            if (locacaoData.status === 'P') {
                const banner = document.getElementById('pendingApprovalBanner');
                if (banner) banner.classList.remove('hidden');
                document.getElementById('btnAprovarReserva')?.addEventListener('click', () => {
                    const codigo = locacaoData.codigo || '';
                    const msg = <?= $jsT('modules.locacoes.messages.approve_confirm') ?>.replace(':code', codigo);
                    window._pendingApproveLocacaoId = locacaoData.id;
                    window.parent.postMessage({
                        action: 'openGenericConfirmModal',
                        title: <?= $jsT('modules.locacoes.buttons.approve') ?>,
                        message: msg,
                        confirmText: <?= $jsT('modules.locacoes.buttons.approve') ?>
                    }, '*');
                });
            }

            // Listener global p/ confirmacao da aprovacao
            window.addEventListener('message', async (event) => {
                if (!event.data || event.data.action !== 'genericConfirmed') return;
                if (!window._pendingApproveLocacaoId) return;
                const id = window._pendingApproveLocacaoId;
                window._pendingApproveLocacaoId = null;
                try {
                    const resp = await API.post('/api/locacoes/' + id + '/confirmar-reserva', {});
                    if (resp && resp.success) {
                        if (window.toast) toast.success(<?= $jsT('modules.locacoes.messages.approve_ok') ?>);
                        locacaoData.status = 'R';
                        document.getElementById('locacaoStatus').value = 'R';
                        const banner = document.getElementById('pendingApprovalBanner');
                        if (banner) banner.classList.add('hidden');
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: (resp && resp.message) || <?= $jsT('modules.locacoes.messages.approve_error') ?> }, '*');
                    }
                } catch (err) {
                    console.error(err);
                    window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.locacoes.messages.approve_error') ?> + ': ' + err.message }, '*');
                }
            });

            // Chosen selects server-side
            const setChosen = (selectId, id, text) => {
                if (!id || !text) return;
                const sel = document.getElementById(selectId);
                if (!sel) return;
                sel.innerHTML = `<option value=""><?= t('common.labels.select') ?></option><option value="${id}" selected>${text}</option>`;
                sel.dispatchEvent(new Event('change'));
            };

            setChosen('id_cliente', locacaoData.id_cliente, locacaoData.cliente_nome_completo || locacaoData.cliente_nome);
            setChosen('id_matriz_filial_retirada', locacaoData.id_matriz_filial_retirada, locacaoData.filial_retirada_nome);
            setChosen('id_matriz_filial_devolucao', locacaoData.id_matriz_filial_devolucao, locacaoData.filial_devolucao_nome);
            setChosen('id_conta', locacaoData.id_conta, locacaoData.conta_descricao);
            // id_conta_bloqueio legado - nao usado mais na UI
            setChosen('id_funcionario', locacaoData.id_funcionario, locacaoData.funcionario_nome);

            // Grupo e Veiculo: fluxo async para cascata
            if (locacaoData.id_grupo && locacaoData.grupo_nome) {
                // Carregar grupos com disponibilidade primeiro
                carregarGrupos().then(() => {
                    const selGrupo = document.getElementById('id_grupo');
                    // Se o grupo atual nao esta na lista, adicionar
                    const opts = Array.from(selGrupo.options).map(o => o.value);
                    if (!opts.includes(String(locacaoData.id_grupo))) {
                        selGrupo.add(new Option(locacaoData.grupo_nome, locacaoData.id_grupo));
                    }
                    selGrupo.value = locacaoData.id_grupo;
                    if (selGrupo.chosenSelect) selGrupo.chosenSelect.refresh();

                    // Carregar veiculos do grupo e selecionar o atual
                    carregarVeiculosPorGrupo(locacaoData.id_grupo).then(() => {
                        if (locacaoData.id_veiculo) {
                            const selVeiculo = document.getElementById('id_veiculo');
                            const vopts = Array.from(selVeiculo.options).map(o => o.value);
                            if (!vopts.includes(String(locacaoData.id_veiculo))) {
                                selVeiculo.add(new Option(locacaoData.veiculo_info, locacaoData.id_veiculo));
                            }
                            selVeiculo.value = locacaoData.id_veiculo;
                            if (selVeiculo.chosenSelect) {
                                selVeiculo.chosenSelect.refresh();
                                aplicarBloqueioChosenVeiculo(vehicleChangeLocked);
                            }
                            // Atualizar labels de combustivel com tipo do veiculo
                            atualizarLabelsTanque(locacaoData.veiculo_tipo_combustivel || '');
                        }
                    });
                });
            }

            // Datas
            if (locacaoData.data_saida) {
                document.getElementById('data_saida').value = DateHelper.toOperationalDateTimeInput(locacaoData.data_saida);
            }
            const dataPrincipal = locacaoData.status === 'F'
                ? (locacaoData.data_chegada || locacaoData.data_prevista)
                : locacaoData.data_prevista;
            if (dataPrincipal) {
                document.getElementById('data_prevista').value = DateHelper.toOperationalDateTimeInput(dataPrincipal);
            }

            // Campos simples
            if (locacaoData.dias) document.getElementById('dias').value = locacaoData.dias;
            if (locacaoData.odometro_ini) document.getElementById('odometro_ini').value = Km.format(locacaoData.odometro_ini);
            if (locacaoData.combustivel_ini !== null && locacaoData.combustivel_ini !== undefined && locacaoData.combustivel_ini !== '') {
                document.getElementById('combustivel_ini').value = locacaoData.combustivel_ini;
            }
            if (locacaoData.obs) document.getElementById('obs').value = locacaoData.obs;

            // Plano
            if (locacaoData.plano) {
                document.getElementById('plano').value = locacaoData.plano;
                atualizarCamposPlano();
            }

            // Valores monetarios
            const camposMoeda = {
                'diaria_valor': locacaoData.diaria_valor || locacaoData.km_livre_valor,
                'km_valor': locacaoData.km_valor,
                'km_controlado_valor': locacaoData.km_controlado_valor,
                'seguro_carro_valor': locacaoData.seguro_carro_valor,
                'cobertura_carro_valor': locacaoData.cobertura_carro_valor,
                'seguro_terceiros_valor': locacaoData.seguro_terceiros_valor,
                'cobertura_terceiros_valor': locacaoData.cobertura_terceiros_valor,
                'valor_tolerancia': locacaoData.valor_tolerancia,
                'valor_km_retorno': locacaoData.valor_km_retorno,
                'valor_condutor_adicional': locacaoData.valor_condutor_adicional,
                'valor_desconto': locacaoData.valor_desconto,
                'caucao_valor': locacaoData.caucao_valor,
            };

            Object.entries(camposMoeda).forEach(([campo, valor]) => {
                const el = document.getElementById(campo);
                if (el && valor) {
                    el.value = parseFloat(valor).toFixed(2).replace('.', ',');
                }
            });
            diariaValorManual = true;
            definirOrigemDiaria('manual');

            // Campos numericos (nao monetarios)
            if (locacaoData.minuto_tolerancia) document.getElementById('minuto_tolerancia').value = locacaoData.minuto_tolerancia;
            if (locacaoData.km_controlado_franquia !== null && locacaoData.km_controlado_franquia !== undefined) {
                document.getElementById('km_controlado_franquia').value = parseInt(locacaoData.km_controlado_franquia, 10) || 0;
            }

            // Seguros
            if (locacaoData.seguro_carro === 'S') document.getElementById('seguro_carro').checked = true;
            if (locacaoData.seguro_terceiros === 'S') document.getElementById('seguro_terceiros').checked = true;

            // Caucao
            if (locacaoData.caucao_prazo_devolucao !== null && locacaoData.caucao_prazo_devolucao !== undefined) {
                document.getElementById('caucao_prazo_devolucao').value = locacaoData.caucao_prazo_devolucao;
            }
            setChosen('id_conta_caucao', locacaoData.id_conta_caucao, locacaoData.conta_caucao_descricao);
            setChosen('id_forma_pagamento_caucao', locacaoData.id_forma_pagamento_caucao, locacaoData.forma_pagamento_caucao_descricao);
            if (locacaoData.caucao_lancar_financeiro !== null && locacaoData.caucao_lancar_financeiro !== undefined) {
                document.getElementById('caucao_lancar_financeiro').value = String(locacaoData.caucao_lancar_financeiro) === '1' ? '1' : '0';
            }
            if (locacaoData.caucao_observacoes) {
                document.getElementById('caucao_observacoes').value = locacaoData.caucao_observacoes;
            }

            // Bloqueio (hold) - carregar status se existir (authorized, captured, released, etc)
            if (locacaoData.bloqueio_status) {
                atualizarStatusBloqueioUI({
                    status: locacaoData.bloqueio_status,
                    valor: locacaoData.bloqueio_status === 'captured'
                        ? (locacaoData.bloqueio_valor_capturado || locacaoData.bloqueio_hold_valor)
                        : locacaoData.bloqueio_hold_valor,
                    external_id: locacaoData.bloqueio_external_id,
                    expires_at: locacaoData.bloqueio_expira_em,
                    cartao_bandeira: locacaoData.bloqueio_cartao_bandeira,
                    cartao_ultimos_digitos: locacaoData.bloqueio_cartao_ultimos_digitos,
                });
            }

            // Carregar cartoes do cliente se tem id_cliente
            if (locacaoData.id_cliente) {
                carregarCartoesCliente(locacaoData.id_cliente);
            }

            if (locacaoData.promocao_codigo) document.getElementById('promocao_codigo').value = locacaoData.promocao_codigo;

            // Dados de devolucao (quando status = F)
            if (locacaoData.status === 'F') {
                if (locacaoData.odometro_fim) {
                    document.getElementById('odometro_fim').value = Km.format(locacaoData.odometro_fim);
                }
                if (locacaoData.combustivel_fim !== null && locacaoData.combustivel_fim !== undefined && locacaoData.combustivel_fim !== '') {
                    document.getElementById('combustivel_fim').value = locacaoData.combustivel_fim;
                }
                calcularDevolucao();
            }

            // Intervenientes
            const parseJSON = (val) => {
                if (!val) return [];
                try { return typeof val === 'string' ? JSON.parse(val) : (Array.isArray(val) ? val : []); }
                catch (e) { return []; }
            };

            parseJSON(locacaoData.condutor_adicional).forEach(p => {
                adicionarPessoa('condutor', 'listaCondutores', 'templateCondutorCard', <?= $jsT('modules.locacoes.person.conductor_label', ['num' => ':num']) ?>, p);
            });
            parseJSON(locacaoData.array_fiadores).forEach(p => {
                adicionarPessoa('fiador', 'listaFiadores', 'templatePessoaCard', <?= $jsT('modules.locacoes.person.guarantor_label', ['num' => ':num']) ?>, p);
            });
            parseJSON(locacaoData.array_avalistas).forEach(p => {
                adicionarPessoa('avalista', 'listaAvalistas', 'templatePessoaCard', <?= $jsT('modules.locacoes.person.endorser_label', ['num' => ':num']) ?>, p);
            });
            parseJSON(locacaoData.array_testemunhas).forEach(p => {
                adicionarPessoa('testemunha', 'listaTestemunhas', 'templatePessoaCard', <?= $jsT('modules.locacoes.person.witness_label', ['num' => ':num']) ?>, p);
            });

            // Taxas - carregar da API
            (async () => {
                try {
                    const result = await API.get(`/api/locacoes/${locacaoData.id}/taxas`);
                    if (result.success && result.data) {
                        result.data.forEach(t => {
                            const isAutoKm = t.nome && t.nome.startsWith('Km Retorno');
                            taxas.push({
                                id_taxa: t.id_taxa,
                                nome: t.nome,
                                base_calculo: t.base_calculo || 'FIX',
                                tipo_valor: t.tipo_valor || 'MON',
                                quantidade: t.quantidade,
                                valor_unitario: t.valor_unitario,
                                ...(isAutoKm ? { _auto_km_retorno: true } : {})
                            });
                        });
                        renderTaxas();
                        await verificarKmRetorno();
                    }
                } catch (e) {
                    console.error('Erro ao carregar taxas:', e);
                }
                atualizarResumo();
            })();
        }

        // ===== LISTENERS PARA RESUMO E TAXAS =====

        function atualizarResumoETaxas() {
            renderTaxas();
            atualizarResumo();
            carregarResumoFinanceiro();
        }

        ['diaria_valor', 'valor_desconto', 'valor_condutor_adicional', 'km_valor', 'km_controlado_franquia'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => {
                calcularDevolucao();
                atualizarResumo();
                carregarResumoFinanceiro();
            });
        });
        // Campos que afetam calculo VLT das taxas: re-renderizar taxas tambem
        ['seguro_carro_valor', 'seguro_terceiros_valor'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', atualizarResumoETaxas);
        });
        document.getElementById('valor_km_retorno')?.addEventListener('change', verificarKmRetorno);
        document.getElementById('seguro_carro')?.addEventListener('change', atualizarResumoETaxas);
        document.getElementById('seguro_terceiros')?.addEventListener('change', atualizarResumoETaxas);
        document.getElementById('dias')?.addEventListener('change', () => {
            aplicarValorDiaria();
            calcularDevolucao();
            atualizarResumoETaxas();
        });
        document.getElementById('data_saida')?.addEventListener('change', () => {
            calcularDias();
            aplicarValorDiaria();
            atualizarResumoETaxas();
            if (isStatusReserva()) carregarGrupos();
        });
        document.getElementById('data_prevista')?.addEventListener('change', () => {
            calcularDias();
            aplicarValorDiaria();
            atualizarResumoETaxas();
            if (isStatusReserva()) carregarGrupos();
        });
        document.getElementById('minuto_tolerancia')?.addEventListener('change', () => {
            calcularDias();
            aplicarValorDiaria();
            atualizarResumoETaxas();
        });

        // ===== ABAS =====

        function configurarAbas() {
            const btns = document.querySelectorAll('#formTabsNav .form-tab-button');
            const contents = document.querySelectorAll('.form-tab-content');
            btns.forEach(btn => {
                btn.addEventListener('click', () => {
                    btns.forEach(b => b.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));
                    btn.classList.add('active');
                    document.querySelector(btn.dataset.formTabTarget)?.classList.add('active');
                    if (btn.dataset.formTabTarget === '#tabResumo') atualizarResumo();
                });
            });
        }

        // ===== VALIDACAO CONDICIONAL CAUCAO =====

        function atualizarCaucaoRequired() {
            const valorStr = document.getElementById('caucao_valor')?.value || '';
            const valor = parseCurrency(valorStr);
            const isRequired = valor > 0;

            [
                { id: 'id_conta_caucao', asterisco: 'asterisco_conta_caucao' },
                { id: 'id_forma_pagamento_caucao', asterisco: 'asterisco_forma_pagamento_caucao' },
                { id: 'caucao_prazo_devolucao', asterisco: 'asterisco_caucao_prazo' },
            ].forEach(({ id, asterisco }) => {
                const el = document.getElementById(id);
                const ast = document.getElementById(asterisco);
                if (el) isRequired ? el.setAttribute('required', '') : el.removeAttribute('required');
                if (ast) ast.classList.toggle('hidden', !isRequired);
            });
        }

        document.getElementById('caucao_valor')?.addEventListener('change', atualizarCaucaoRequired);
        document.getElementById('caucao_valor')?.addEventListener('input', atualizarCaucaoRequired);

        // ===== CARTOES DO CLIENTE =====

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

                    ['bloqueio_id_cartao'].forEach(selId => {
                        const sel = document.getElementById(selId);
                        if (sel) {
                            sel.innerHTML = '<option value=""><?= t('common.labels.select') ?></option>' + options;
                            if (sel.chosenSelect) sel.chosenSelect.refresh();
                        }
                    });

                    // Aviso se nao tem cartao
                    const aviso = document.getElementById('bloqueioSemCartao');
                    if (aviso) aviso.classList.toggle('hidden', cartoesCliente.length > 0);
                }
            } catch (e) {
                console.error('Erro ao carregar cartoes:', e);
            }
        }

        // Recarregar cartoes quando cliente muda
        document.getElementById('id_cliente')?.addEventListener('change', function() {
            const idCliente = this.value;
            if (idCliente) {
                carregarCartoesCliente(idCliente);
                verificarGatewaysBloqueio(idCliente);
            }
        });

        // Habilitar/desabilitar botao de criar bloqueio
        function atualizarBtnCriarBloqueio() {
            const btn = document.getElementById('btnCriarBloqueio');
            if (!btn) return;
            const cartao = document.getElementById('bloqueio_id_cartao')?.value;
            const valor = parseCurrency(document.getElementById('bloqueio_valor')?.value || '');
            const registroId = document.getElementById('registroId')?.value;
            btn.disabled = !cartao || valor <= 0 || !registroId;
        }

        document.getElementById('bloqueio_id_cartao')?.addEventListener('change', atualizarBtnCriarBloqueio);
        document.getElementById('bloqueio_valor')?.addEventListener('input', atualizarBtnCriarBloqueio);
        document.getElementById('bloqueio_valor')?.addEventListener('change', atualizarBtnCriarBloqueio);

        // ===== VERIFICAR GATEWAYS QUE SUPORTAM HOLD =====

        async function verificarGatewaysBloqueio(idCliente) {
            try {
                const result = await API.get(`/api/clientes/${idCliente}/gateways-cartao`);
                const temHold = result.success && result.data?.some(g => g.gateway_code === 'stripe' || g.gateway_code === 'square');
                document.getElementById('bloqueioSemGateway')?.classList.toggle('hidden', temHold);
                document.getElementById('bloqueioFormFields')?.classList.toggle('hidden', !temHold);

                // Guardar publishable_key do Stripe para o modal
                if (result.success && result.data) {
                    const stripe = result.data.find(g => g.gateway_code === 'stripe');
                    if (stripe) {
                        window._stripePublishableKey = stripe.publishable_key;
                    }
                }
            } catch (e) {
                console.error('Erro ao verificar gateways:', e);
            }
        }

        // ===== BLOQUEIO: CRIAR / LIBERAR / CAPTURAR =====

        document.getElementById('btnCriarBloqueio')?.addEventListener('click', async function() {
            const registroId = document.getElementById('registroId')?.value;
            if (!registroId) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.saveBeforeHold }, '*');
                return;
            }

            const idCartao = document.getElementById('bloqueio_id_cartao')?.value;
            const valor = document.getElementById('bloqueio_valor')?.value;

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + i18n.processing;

            try {
                const result = await API.post(`/api/locacoes/${registroId}/bloqueio/criar`, {
                    id_cartao: idCartao,
                    valor: valor,
                });

                if (result.success) {
                    // Se precisa de 3D Secure
                    if (result.data?.client_secret && result.data?.status === 'pending' && window._stripePublishableKey) {
                        const stripe3ds = Stripe(window._stripePublishableKey);
                        const { error } = await stripe3ds.handleCardAction(result.data.client_secret);
                        if (error) {
                            window.parent.postMessage({ action: 'openAlert', message: error.message }, '*');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-shield-alt mr-1"></i>' + <?= $jsT('modules.locacoes.block.create_hold') ?>;
                            return;
                        }
                    }

                    window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.locacoes.block.hold_created') ?>, type: 'success' }, '*');

                    // Buscar cartao selecionado para mostrar info
                    const cartaoSel = cartoesCliente.find(c => c.id == idCartao);
                    atualizarStatusBloqueioUI({
                        status: 'authorized',
                        valor: parseCurrency(valor),
                        expires_at: result.data?.expires_at,
                        cartao_bandeira: cartaoSel?.bandeira || '',
                        cartao_ultimos_digitos: cartaoSel?.ultimos_digitos || '',
                    });
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.holdCreateError }, '*');
                }
            } catch (e) {
                console.error('Erro:', e);
                window.parent.postMessage({ action: 'openAlert', message: i18n.holdCreateError }, '*');
            }

            this.disabled = false;
            this.innerHTML = '<i class="fas fa-shield-alt mr-1"></i>' + <?= $jsT('modules.locacoes.block.create_hold') ?>;
        });

        document.getElementById('btnLiberarBloqueio')?.addEventListener('click', async function() {
            const registroId = document.getElementById('registroId')?.value;
            if (!registroId) return;

            this.disabled = true;
            try {
                const result = await API.post(`/api/locacoes/${registroId}/bloqueio/liberar`);
                if (result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.locacoes.block.hold_released') ?>, type: 'success' }, '*');
                    document.getElementById('bloqueioStatusArea')?.classList.add('hidden');
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.genericError }, '*');
                }
            } catch (e) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.holdReleaseError }, '*');
            }
            this.disabled = false;
        });

        // Toggle formulario de captura
        document.getElementById('btnToggleCaptura')?.addEventListener('click', function() {
            const form = document.getElementById('bloqueioCapturarForm');
            if (!form) return;
            const isHidden = form.classList.contains('hidden');
            form.classList.toggle('hidden');

            if (isHidden) {
                // Preencher valor maximo com valor do bloqueio
                const valorInfo = document.getElementById('bloqueioValorInfo')?.textContent || '';
                const valorMatch = valorInfo.replace(/[^\d,]/g, '');
                document.getElementById('capturaValor').value = valorMatch;
                document.getElementById('capturaValorMax').textContent = valorInfo;
            }
        });

        // Confirmar captura com valor e motivo
        document.getElementById('btnConfirmarCaptura')?.addEventListener('click', async function() {
            const registroId = document.getElementById('registroId')?.value;
            if (!registroId) return;

            const valor = document.getElementById('capturaValor')?.value;
            const motivo = document.getElementById('capturaMotivo')?.value;
            const idConta = document.getElementById('capturaContaBancaria')?.value;

            if (!valor || parseCurrency(valor) <= 0) {
                window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.locacoes.block.capture_value_required') ?> }, '*');
                return;
            }
            if (!idConta) {
                window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.locacoes.block.capture_account_required') ?> }, '*');
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + <?= $jsT('common.labels.processing') ?>;

            try {
                const result = await API.post(`/api/locacoes/${registroId}/bloqueio/capturar`, {
                    valor: valor,
                    motivo: motivo,
                    id_conta: idConta,
                });

                if (result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= $jsT('modules.locacoes.block.hold_captured') ?>, type: 'success' }, '*');

                    // Atualizar status visual
                    const badge = document.getElementById('bloqueioStatusBadge');
                    if (badge) {
                        badge.textContent = <?= $jsT('modules.locacoes.block.captured') ?>;
                        badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
                    }

                    // Atualizar valor exibido com valor capturado
                    const valorCapturado = parseCurrency(valor);
                    document.getElementById('bloqueioValorInfo').textContent = fmtCurrency(valorCapturado);

                    // Atualizar cor da area de status
                    const area = document.getElementById('bloqueioStatusArea');
                    if (area) {
                        area.className = area.className.replace(/border-\S+\s*bg-\S+/g, '').trim();
                        area.classList.add('border-blue-200', 'bg-blue-50');
                    }

                    // Guardar dados do bloqueio para o resumo
                    window._bloqueioResumo = {
                        status: <?= $jsT('modules.locacoes.block.captured') ?>,
                        valor: valorCapturado,
                        cartao: document.getElementById('bloqueioCartaoInfo')?.textContent || '',
                        statusClass: 'bg-blue-100 text-blue-800',
                    };

                    document.getElementById('btnLiberarBloqueio')?.classList.add('hidden');
                    document.getElementById('btnToggleCaptura')?.classList.add('hidden');
                    document.getElementById('bloqueioCapturarForm')?.classList.add('hidden');

                    // Atualizar resumo
                    atualizarResumo();
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.genericError }, '*');
                }
            } catch (e) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.holdCaptureError }, '*');
            }

            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check mr-1"></i>' + <?= $jsT('modules.locacoes.block.confirm_capture') ?>;
        });

        function atualizarStatusBloqueioUI(data) {
            const area = document.getElementById('bloqueioStatusArea');
            if (!area) return;

            area.classList.remove('hidden');

            // Cor do status
            const badge = document.getElementById('bloqueioStatusBadge');
            const statusMap = {
                authorized: { text: <?= $jsT('modules.locacoes.block.authorized') ?>, cls: 'bg-green-100 text-green-800' },
                captured: { text: <?= $jsT('modules.locacoes.block.captured') ?>, cls: 'bg-blue-100 text-blue-800' },
                released: { text: <?= $jsT('modules.locacoes.block.released') ?>, cls: 'bg-slate-100 text-slate-600' },
                expired: { text: <?= $jsT('modules.locacoes.block.expired') ?>, cls: 'bg-amber-100 text-amber-800' },
                failed: { text: <?= $jsT('modules.locacoes.block.failed') ?>, cls: 'bg-red-100 text-red-800' },
            };

            const st = statusMap[data.status] || statusMap.authorized;
            if (badge) {
                badge.textContent = st.text;
                badge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${st.cls}`;
            }

            // Cor do border da area
            const borderMap = { authorized: 'border-green-200 bg-green-50', captured: 'border-blue-200 bg-blue-50', released: 'border-slate-200 bg-slate-50' };
            area.className = area.className.replace(/border-\S+\s*bg-\S+/g, '').trim();
            area.classList.add(...(borderMap[data.status] || 'border-slate-200 bg-slate-50').split(' '));

            // Info do cartao e valor
            document.getElementById('bloqueioCartaoInfo').textContent =
                `**** ${data.cartao_ultimos_digitos || '****'} ${data.cartao_bandeira || ''}`;
            document.getElementById('bloqueioValorInfo').textContent =
                data.valor ? `R$ ${parseFloat(data.valor).toFixed(2).replace('.', ',')}` : '';

            // Data de expiracao
            if (data.expires_at) {
                document.getElementById('bloqueioExpiraInfo').textContent =
                    `<?= t('modules.locacoes.block.expires_at') ?>: ${DateHelper.formatDateTime(data.expires_at)}`;
            }

            // Mostrar/ocultar botoes de acao
            const canAct = data.status === 'authorized';
            document.getElementById('btnLiberarBloqueio')?.classList.toggle('hidden', !canAct);
            document.getElementById('btnToggleCaptura')?.classList.toggle('hidden', !canAct);
        }

        // ===== MODAL ADICIONAR CARTAO =====

        // ===== MODAL ADICIONAR CARTAO (via postMessage para app.php) =====

        let _gatewaysCache = null;

        async function abrirModalAdicionarCartao() {
            const idCliente = document.getElementById('id_cliente')?.value;
            if (!idCliente) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.selectClientFirst }, '*');
                return;
            }

            // Buscar gateways se nao tiver cache
            if (!_gatewaysCache) {
                try {
                    const result = await API.get(`/api/clientes/${idCliente}/gateways-cartao`);
                    if (result.success) _gatewaysCache = result.data || [];
                } catch (e) {
                    _gatewaysCache = [];
                }
            }

            // Abrir modal no parent (fullscreen)
            window.parent.postMessage({
                action: 'openAddCartaoLocacaoModal',
                id_cliente: idCliente,
                gateways: _gatewaysCache,
            }, '*');
        }

        document.getElementById('btnAdicionarCartaoBloqueio')?.addEventListener('click', abrirModalAdicionarCartao);
        document.getElementById('btnAdicionarCartaoCaucao')?.addEventListener('click', abrirModalAdicionarCartao);

        // Escutar resposta do parent quando cartao for salvo
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'cartaoLocacaoSalvo') {
                carregarCartoesCliente(event.data.id_cliente);
            }
        });

        // ===== STATUS DINAMICO =====

        function atualizarStatusOpcoes() {
            const select = document.getElementById('locacaoStatus');
            if (!select) return;

            const statusAtual = isEditing && locacaoData ? locacaoData.status : null;

            // Remover todas as opcoes
            select.innerHTML = '';

            if (!isEditing) {
                // Novo: pode ser Reserva ou Aberto
                select.add(new Option(<?= $jsT('modules.locacoes.status.reservation') ?>, 'R'));
                select.add(new Option(<?= $jsT('modules.locacoes.status.open') ?>, 'A'));
                select.value = 'A';
            } else if (statusAtual === 'R') {
                select.add(new Option(<?= $jsT('modules.locacoes.status.reservation') ?>, 'R'));
                select.add(new Option(<?= $jsT('modules.locacoes.status.open') ?>, 'A'));
                select.value = 'R';
            } else if (statusAtual === 'A') {
                select.add(new Option(<?= $jsT('modules.locacoes.status.open') ?>, 'A'));
                select.add(new Option(<?= $jsT('modules.locacoes.status.closed') ?>, 'F'));
                select.value = 'A';
            } else if (statusAtual === 'F') {
                select.add(new Option(<?= $jsT('modules.locacoes.status.closed') ?>, 'F'));
                select.value = 'F';
                select.disabled = true;
            }
        }

        function atualizarVisibilidadePorStatus() {
            const status = document.getElementById('locacaoStatus')?.value || 'R';
            const secaoDevolucao = document.getElementById('secaoDevolucao');
            const secaoParcelas = document.getElementById('secaoParcelas');

            // Devolucao: visivel quando status = F
            if (secaoDevolucao) {
                secaoDevolucao.classList.toggle('hidden', status !== 'F');
            }

            const devolucaoObrigatoria = status === 'F';
            ['odometro_fim', 'combustivel_fim'].forEach(id => {
                const campo = document.getElementById(id);
                if (campo) {
                    devolucaoObrigatoria
                        ? campo.setAttribute('required', '')
                        : campo.removeAttribute('required');
                }
            });
            document.getElementById('asterisco_combustivel_fim')?.classList.toggle('hidden', !devolucaoObrigatoria);

            const veiculoObrigatorio = !['R', 'P'].includes(status);
            const campoVeiculo = document.getElementById('id_veiculo');
            const campoVeiculoLocked = document.getElementById('id_veiculo_locked');
            if (campoVeiculo) {
                veiculoObrigatorio
                    ? campoVeiculo.setAttribute('required', '')
                    : campoVeiculo.removeAttribute('required');

                if (vehicleChangeLocked) {
                    campoVeiculo.disabled = true;
                    campoVeiculo.removeAttribute('name');
                    if (campoVeiculoLocked) {
                        campoVeiculoLocked.disabled = false;
                        campoVeiculoLocked.value = locacaoData?.id_veiculo || campoVeiculo.value || '';
                    }
                } else {
                    campoVeiculo.disabled = false;
                    campoVeiculo.setAttribute('name', 'id_veiculo');
                    if (campoVeiculoLocked) {
                        campoVeiculoLocked.disabled = true;
                        campoVeiculoLocked.value = '';
                    }
                }

                if (campoVeiculo.chosenSelect) {
                    campoVeiculo.chosenSelect.refresh();
                    aplicarBloqueioChosenVeiculo(vehicleChangeLocked);
                }
            }
            document.getElementById('campoVeiculoWrapper')?.classList.remove('hidden');
            document.getElementById('asterisco_id_veiculo')?.classList.toggle('hidden', !veiculoObrigatorio);

            const grupoId = document.getElementById('id_grupo')?.value;
            if (grupoId && !vehicleChangeLocked) carregarVeiculosPorGrupo(grupoId);

            // Pagamentos: visivel sempre, mas sem acoes antes de salvar.
            if (secaoParcelas) {
                secaoParcelas.classList.remove('hidden');
            }
            document.getElementById('parcelasEstadoNovo')?.classList.toggle('hidden', isEditing);
            document.getElementById('parcelasAcoesHeader')?.classList.toggle('hidden', !isEditing);
            document.getElementById('parcelasTabelaWrapper')?.classList.toggle('hidden', !isEditing);
            document.getElementById('resumoFinanceiroParcelas')?.classList.toggle('hidden', !isEditing);
            if (!isEditing) {
                esconderFormularioBaixaLocacao();
                ['formGerarParcelas', 'formAdicionarParcela', 'formAdicionarAvaria'].forEach(id => {
                    document.getElementById(id)?.classList.add('hidden');
                });
            }

            // Label do campo data_prevista: muda para "Data Chegada" quando F
            const labelPrevista = document.querySelector('label[for="data_prevista"]');
            const inputDataPrincipal = document.getElementById('data_prevista');
            if (labelPrevista) {
                labelPrevista.innerHTML = status === 'F'
                    ? <?= $jsText(t('modules.locacoes.fields.arrival_date') . ' <span class="text-red-500">*</span>') ?>
                    : <?= $jsText(t('modules.locacoes.fields.expected_date') . ' <span class="text-red-500">*</span>') ?>;
            }
            if (inputDataPrincipal) {
                inputDataPrincipal.setAttribute('name', status === 'F' ? 'data_chegada' : 'data_prevista');
            }
        }

        document.getElementById('locacaoStatus')?.addEventListener('change', function() {
            atualizarVisibilidadePorStatus();
            carregarGrupos();

            // Se mudou para F, o campo de previsao passa a representar data_chegada.
            if (this.value === 'F') {
                aplicarChegadaAtual();
                return;
            }
            atualizarResumo();
            carregarResumoFinanceiro();
        });

        // ===== CALCULOS DE DEVOLUCAO =====

        function calcularDevolucao() {
            const odometroIni = parseInt(String(document.getElementById('odometro_ini')?.value || '0').replace(/\D/g, '')) || 0;
            const odometroFim = parseInt(String(document.getElementById('odometro_fim')?.value || '0').replace(/\D/g, '')) || 0;

            // Km rodados
            const kmRodados = odometroFim > odometroIni ? odometroFim - odometroIni : 0;
            document.getElementById('km_rodados').value = Km.format(kmRodados);

            const kmExcedente = calcularKmExcedenteDevolucao().kmExcedente;
            document.getElementById('km_excedente').value = Km.format(kmExcedente);

            // Combustivel usado
            const combIni = parseInt(document.getElementById('combustivel_ini')?.value) || 0;
            const combFim = parseInt(document.getElementById('combustivel_fim')?.value) || 0;
            const combUsado = combIni - combFim;
            document.getElementById('combustivel_usado').value = combUsado > 0 ? combUsado : 0;
        }

        document.getElementById('odometro_fim')?.addEventListener('input', () => {
            calcularDevolucao();
            atualizarResumo();
            carregarResumoFinanceiro();
        });
        document.getElementById('combustivel_fim')?.addEventListener('change', () => {
            calcularDevolucao();
            atualizarResumo();
            carregarResumoFinanceiro();
        });

        // ===== PARCELAS =====

        let parcelasData = [];
        const origemFormularioBaixaLocacao = {};

        function registrarOrigemFormularioBaixaLocacao() {
            if (origemFormularioBaixaLocacao.parent) return;

            const form = document.getElementById('formMarcarPago');
            if (!form || !form.parentNode) return;

            origemFormularioBaixaLocacao.parent = form.parentNode;
            origemFormularioBaixaLocacao.nextSibling = form.nextSibling;
        }

        function removerLinhaFormularioBaixaLocacao() {
            document.getElementById('linhaFormularioBaixaLocacao')?.remove();
        }

        function esconderFormularioBaixaLocacao() {
            registrarOrigemFormularioBaixaLocacao();

            const form = document.getElementById('formMarcarPago');
            if (form && origemFormularioBaixaLocacao.parent) {
                form.classList.add('hidden');
                origemFormularioBaixaLocacao.parent.insertBefore(form, origemFormularioBaixaLocacao.nextSibling || null);
            }

            removerLinhaFormularioBaixaLocacao();
        }

        function inserirFormularioBaixaAbaixoLinhaLocacao(linhaReferencia) {
            const form = document.getElementById('formMarcarPago');
            if (!form || !linhaReferencia) return;

            esconderFormularioBaixaLocacao();

            const linhaFormulario = document.createElement('tr');
            linhaFormulario.id = 'linhaFormularioBaixaLocacao';
            linhaFormulario.className = 'bg-slate-50 border-b border-slate-100';

            const celula = document.createElement('td');
            celula.colSpan = linhaReferencia.children.length || 6;
            celula.className = 'px-3 py-3';

            linhaFormulario.appendChild(celula);
            linhaReferencia.insertAdjacentElement('afterend', linhaFormulario);
            celula.appendChild(form);
            form.classList.remove('hidden');
        }

        async function carregarParcelas() {
            if (!isEditing || !locacaoData) return;

            try {
                const result = await API.get(`/api/locacoes/${locacaoData.id}/parcelas`);
                if (result.success) {
                    parcelasData = result.data || [];
                    renderParcelas();
                }
            } catch (e) {
                console.error('Erro ao carregar parcelas:', e);
            }

            carregarResumoFinanceiro();
        }

        async function carregarResumoFinanceiro() {
            if (!isEditing || !locacaoData) return;

            try {
                const result = await API.get(`/api/locacoes/${locacaoData.id}/resumo-financeiro`);
                if (result.success && result.data) {
                    const r = result.data;
                    const totalSimulado = calcularTotalPagarFormulario();
                    const totalLancado = parseFloat(r.total_lancado) || 0;
                    const totalAvarias = parseFloat(r.total_avarias) || 0;
                    const totalPago = parseFloat(r.total_pago) || 0;
                    const totalReembolsado = parseFloat(r.total_credito_devolucao) || 0;
                    const totalEsperado = totalSimulado + totalAvarias;
                    const diferencaSimulada = Math.max(0, Math.round((totalEsperado - totalLancado) * 100) / 100);
                    totalAvariasFinanceiro = totalAvarias;
                    totalPagoFinanceiro = totalPago;
                    totalReembolsadoFinanceiro = totalReembolsado;
                    document.getElementById('rfTotalLancado').textContent = fmtCurrency(parseFloat(r.total_lancado) || 0);
                    document.getElementById('rfTotalAvarias').textContent = fmtCurrency(totalAvarias);
                    document.getElementById('rfTotalPago').textContent = fmtCurrency(parseFloat(r.total_pago) || 0);
                    document.getElementById('rfTotalPendente').textContent = fmtCurrency(parseFloat(r.total_pendente) || 0);
                    document.getElementById('rfTotalReembolsado').textContent = fmtCurrency(totalReembolsado);
                    document.getElementById('rfDiferenca').textContent = fmtCurrency(diferencaSimulada);
                    atualizarResumo();
                }
            } catch (e) {
                console.error('Erro ao carregar resumo financeiro:', e);
            }
        }

        function renderParcelas() {
            const tbody = document.getElementById('parcelasBody');
            if (!tbody) return;
            esconderFormularioBaixaLocacao();

            if (parcelasData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-3 py-4 text-center text-slate-400">${i18n.installments.noInstallments}</td></tr>`;
                return;
            }

            tbody.innerHTML = parcelasData.map((p, idx) => {
                const pago = p.pago === 'S';
                const statusBadge = pago
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.installments.paid}</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">${i18n.installments.pending}</span>`;

                let acoes = '';
                if (pago) {
                    acoes = `<button type="button" class="btn-icon text-amber-600 hover:text-amber-800 btn-estornar-parcela" data-id="${p.id}" title="${i18n.installments.reversePayment}"><i class="fas fa-undo"></i></button>`;
                } else {
                    const fallbackDescricao = i18n.installments.installmentLabel.replace(':num', p.parcela || (idx + 1));
                    acoes = `<button type="button" class="btn-icon text-emerald-600 hover:text-emerald-800 btn-marcar-pago" data-id="${p.id}" data-valor="${p.valor_total}" data-descricao="${(p.descricao || fallbackDescricao).replace(/"/g, '&quot;')}" data-id-conta="${p.id_conta || ''}" data-id-forma="${p.id_forma_pagamento || ''}" title="${i18n.installments.markPaid}"><i class="fas fa-check-circle"></i></button>`
                        + ` <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-parcela" data-id="${p.id}" title="${i18n.installments.remove}"><i class="fas fa-trash"></i></button>`;
                }

                const vencimento = p.data_venci ? DateHelper.format(p.data_venci) : '-';
                const valor = fmtCurrency(parseFloat(p.valor_total) || 0);
                const descricao = p.descricao || i18n.installments.installmentLabel.replace(':num', p.parcela || (idx + 1));

                return `<tr class="border-b border-slate-100">
                    <td class="px-3 py-2 text-slate-500">${p.parcela || (idx + 1)}</td>
                    <td class="px-3 py-2">${descricao}</td>
                    <td class="px-3 py-2 text-center">${vencimento}</td>
                    <td class="px-3 py-2 text-right font-medium">${valor}</td>
                    <td class="px-3 py-2 text-center">${statusBadge}</td>
                    <td class="px-3 py-2 text-center">${acoes}</td>
                </tr>`;
            }).join('');

            // Bind remover (usa modal global de confirmacao)
            tbody.querySelectorAll('.btn-remover-parcela').forEach(btn => {
                btn.addEventListener('click', function() {
                    parcelaAcaoPendente = { id: this.dataset.id, tipo: 'remover' };
                    window.parent.postMessage({
                        action: 'openGenericConfirmModal',
                        title: i18n.installments.removeTitle,
                        message: i18n.installments.removeMessage,
                        confirmText: i18n.installments.remove
                    }, '*');
                });
            });

            // Bind marcar pago: abre form logo abaixo da linha acionada
            tbody.querySelectorAll('.btn-marcar-pago').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idP = this.dataset.id;
                    const descricao = this.dataset.descricao;
                    const idConta = this.dataset.idConta;
                    const idForma = this.dataset.idForma;

                    document.getElementById('pagar_id_parcela').value = idP;
                    document.getElementById('pagar_descricao_resumo').textContent = descricao;
                    document.getElementById('pagar_data_pago').value = DateHelper.todayInput();

                    const selConta = document.getElementById('pagar_id_conta');
                    if (selConta) selConta.value = idConta || '';
                    const selForma = document.getElementById('pagar_id_forma_pagamento');
                    if (selForma) selForma.value = idForma || '';

                    inserirFormularioBaixaAbaixoLinhaLocacao(this.closest('tr'));
                    document.getElementById('formGerarParcelas')?.classList.add('hidden');
                    document.getElementById('formAdicionarParcela')?.classList.add('hidden');
                });
            });

            // Bind estornar (usa modal global de confirmacao)
            tbody.querySelectorAll('.btn-estornar-parcela').forEach(btn => {
                btn.addEventListener('click', function() {
                    parcelaAcaoPendente = { id: this.dataset.id, tipo: 'estornar' };
                    window.parent.postMessage({
                        action: 'openGenericConfirmModal',
                        title: i18n.installments.reverseTitle,
                        message: i18n.installments.reverseMessage,
                        confirmText: i18n.installments.reverseConfirm
                    }, '*');
                });
            });
        }

        // Estado da acao de parcela pendente de confirmacao via modal global
        let parcelaAcaoPendente = null;

        // Listener para resposta do modal de confirmacao generico
        window.addEventListener('message', async function(event) {
            if (!event.data || event.data.action !== 'genericConfirmed') return;
            if (devolucaoCreditoPendente) {
                const dados = devolucaoCreditoPendente;
                devolucaoCreditoPendente = null;
                await enviarLocacao(dados);
                return;
            }

            if (!parcelaAcaoPendente) return;

            const { id, tipo } = parcelaAcaoPendente;
            parcelaAcaoPendente = null;

            const url = tipo === 'estornar'
                ? `/api/locacoes/${locacaoData.id}/parcelas/${id}/estornar`
                : `/api/locacoes/${locacaoData.id}/parcelas/${id}/excluir`;

            try {
                const result = await API.post(url);
                if (result.success) {
                    carregarParcelas();
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.processError }, '*');
                }
            } catch (e) {
                console.error('Erro na acao da parcela:', e);
            }
        });

        window.addEventListener('message', function(event) {
            if (!event.data || event.data.action !== 'genericModalClosed') return;
            devolucaoCreditoPendente = null;
        });

        // Carregar contas e formas de pagamento nos selects de parcelas
        async function carregarSelectsParcelas() {
            try {
                // Contas bancarias
                const contasResult = await API.get('/api/contas-bancarias/buscar');
                if (contasResult.success && contasResult.data) {
                    ['gerar_id_conta', 'parcela_id_conta', 'avaria_id_conta', 'pagar_id_conta'].forEach(selectId => {
                        const sel = document.getElementById(selectId);
                        if (!sel) return;
                        sel.innerHTML = `<option value="">${i18n.select}</option>`;
                        contasResult.data.forEach(c => {
                            sel.add(new Option(c.text || c.nome, c.id));
                        });
                    });
                }

                // Formas de pagamento
                const fpResult = await API.get('/api/formas-pagamento');
                if (fpResult.success && fpResult.data) {
                    ['gerar_id_forma_pagamento', 'parcela_id_forma_pagamento', 'avaria_id_forma_pagamento', 'pagar_id_forma_pagamento'].forEach(selectId => {
                        const sel = document.getElementById(selectId);
                        if (!sel) return;
                        sel.innerHTML = `<option value="">${i18n.select}</option>`;
                        fpResult.data.forEach(fp => {
                            sel.add(new Option(fp.nome, fp.id));
                        });
                    });
                }
            } catch (e) {
                console.error('Erro ao carregar selects parcelas:', e);
            }
        }

        // Toggle formularios de parcelas
        document.getElementById('btnGerarParcelas')?.addEventListener('click', () => {
            document.getElementById('formGerarParcelas')?.classList.toggle('hidden');
            document.getElementById('formAdicionarParcela')?.classList.add('hidden');
            document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
            esconderFormularioBaixaLocacao();
        });

        // Marcar pago: cancelar e confirmar
        document.getElementById('btnCancelarMarcarPago')?.addEventListener('click', () => {
            esconderFormularioBaixaLocacao();
        });

        document.getElementById('btnConfirmarMarcarPago')?.addEventListener('click', async () => {
            const idP = document.getElementById('pagar_id_parcela').value;
            const dataPago = document.getElementById('pagar_data_pago').value;
            const idForma = document.getElementById('pagar_id_forma_pagamento').value;
            const idConta = document.getElementById('pagar_id_conta').value;

            if (!idP) return;
            if (!dataPago) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.paymentDateRequired }, '*');
                return;
            }
            if (!idForma) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.paymentMethodRequired }, '*');
                return;
            }
            if (!idConta) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.bankAccountRequired }, '*');
                return;
            }

            try {
                const result = await API.post(`/api/locacoes/${locacaoData.id}/parcelas/${idP}/marcar-pago`, {
                    data_pago: dataPago,
                    id_forma_pagamento: idForma,
                    id_conta: idConta,
                });
                if (result.success) {
                    esconderFormularioBaixaLocacao();
                    carregarParcelas();
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.markPaidError }, '*');
                }
            } catch (e) {
                console.error('Erro ao marcar como paga:', e);
            }
        });

        document.getElementById('btnAdicionarParcela')?.addEventListener('click', () => {
            document.getElementById('formAdicionarParcela')?.classList.toggle('hidden');
            document.getElementById('formGerarParcelas')?.classList.add('hidden');
            document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
            esconderFormularioBaixaLocacao();
        });

        document.getElementById('btnAdicionarAvaria')?.addEventListener('click', () => {
            document.getElementById('formAdicionarAvaria')?.classList.toggle('hidden');
            document.getElementById('formAdicionarParcela')?.classList.add('hidden');
            document.getElementById('formGerarParcelas')?.classList.add('hidden');
            esconderFormularioBaixaLocacao();
        });

        document.getElementById('btnCancelarGerarParcelas')?.addEventListener('click', () => {
            document.getElementById('formGerarParcelas')?.classList.add('hidden');
        });

        document.getElementById('btnCancelarAdicionarParcela')?.addEventListener('click', () => {
            document.getElementById('formAdicionarParcela')?.classList.add('hidden');
        });

        document.getElementById('btnCancelarAdicionarAvaria')?.addEventListener('click', () => {
            document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
        });

        // Confirmar gerar parcelas
        document.getElementById('btnConfirmarGerarParcelas')?.addEventListener('click', async () => {
            if (!isEditing || !locacaoData) return;

            const quantidade = document.getElementById('gerar_quantidade')?.value;
            const dataVenci = document.getElementById('gerar_data_vencimento')?.value;
            const idConta = document.getElementById('gerar_id_conta')?.value;
            const idFormaPgto = document.getElementById('gerar_id_forma_pagamento')?.value;

            if (!quantidade || !dataVenci || !idConta || !idFormaPgto) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.allFieldsRequired }, '*');
                return;
            }

            try {
                const result = await API.post(`/api/locacoes/${locacaoData.id}/gerar-parcelas`, {
                    quantidade: parseInt(quantidade),
                    data_primeiro_vencimento: dataVenci,
                    id_conta: idConta,
                    id_forma_pagamento: idFormaPgto,
                    status: document.getElementById('locacaoStatus')?.value || locacaoData.status || 'R',
                    dias: document.getElementById('dias')?.value || locacaoData.dias || 1,
                    plano: document.getElementById('plano')?.value || locacaoData.plano || 'KL',
                    valor_desconto: document.getElementById('valor_desconto')?.value || '0,00',
                    seguro_carro: document.getElementById('seguro_carro')?.checked ? 'S' : 'N',
                    seguro_carro_valor: document.getElementById('seguro_carro_valor')?.value || '0,00',
                    seguro_terceiros: document.getElementById('seguro_terceiros')?.checked ? 'S' : 'N',
                    seguro_terceiros_valor: document.getElementById('seguro_terceiros_valor')?.value || '0,00',
                    combustivel_fim: document.getElementById('combustivel_fim')?.value || '',
                    condutor_adicional: coletarPessoas('listaCondutores'),
                    taxas: JSON.stringify(taxas)
                });

                if (result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.generated }, '*');
                    document.getElementById('formGerarParcelas')?.classList.add('hidden');
                    carregarParcelas();
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.generateError }, '*');
                }
            } catch (e) {
                console.error('Erro ao gerar parcelas:', e);
                window.parent.postMessage({ action: 'openAlert', message: i18n.installments.generateError }, '*');
            }
        });

        // Confirmar adicionar parcela avulsa
        document.getElementById('btnConfirmarAdicionarParcela')?.addEventListener('click', async () => {
            if (!isEditing || !locacaoData) return;

            const valor = document.getElementById('parcela_valor')?.value;
            const dataVenci = document.getElementById('parcela_data_venci')?.value;
            const idConta = document.getElementById('parcela_id_conta')?.value;
            const idFormaPgto = document.getElementById('parcela_id_forma_pagamento')?.value;
            const descricao = document.getElementById('parcela_descricao')?.value || '';

            if (!valor || !dataVenci || !idConta || !idFormaPgto) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.allRequiredFields }, '*');
                return;
            }

            try {
                const result = await API.post(`/api/locacoes/${locacaoData.id}/parcelas`, {
                    valor: valor,
                    data_venci: dataVenci,
                    id_conta: idConta,
                    id_forma_pagamento: idFormaPgto,
                    descricao: descricao
                });

                if (result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: i18n.installments.added }, '*');
                    document.getElementById('formAdicionarParcela')?.classList.add('hidden');
                    // Limpar campos
                    document.getElementById('parcela_valor').value = '';
                    document.getElementById('parcela_data_venci').value = '';
                    document.getElementById('parcela_descricao').value = '';
                    carregarParcelas();
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.addError }, '*');
                }
            } catch (e) {
                console.error('Erro ao adicionar parcela:', e);
                window.parent.postMessage({ action: 'openAlert', message: i18n.installments.addError }, '*');
            }
        });

        document.getElementById('btnConfirmarAdicionarAvaria')?.addEventListener('click', async () => {
            if (!isEditing || !locacaoData) return;

            const valor = document.getElementById('avaria_valor')?.value;
            const dataVenci = document.getElementById('avaria_data_venci')?.value;
            const idConta = document.getElementById('avaria_id_conta')?.value;
            const idFormaPgto = document.getElementById('avaria_id_forma_pagamento')?.value;
            const descricao = document.getElementById('avaria_descricao')?.value || '';

            if (!valor || !dataVenci || !idConta || !idFormaPgto) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.allRequiredFields }, '*');
                return;
            }

            try {
                const result = await API.post(`/api/locacoes/${locacaoData.id}/parcelas`, {
                    tipo_lancamento: 'avaria',
                    valor: valor,
                    data_venci: dataVenci,
                    id_conta: idConta,
                    id_forma_pagamento: idFormaPgto,
                    descricao: descricao
                });

                if (result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.added }, '*');
                    document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
                    ['avaria_valor', 'avaria_data_venci', 'avaria_descricao'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                    carregarParcelas();
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installments.addError }, '*');
                }
            } catch (e) {
                console.error('Erro ao adicionar avaria:', e);
                window.parent.postMessage({ action: 'openAlert', message: i18n.installments.addError }, '*');
            }
        });

        function configurarToggleSecaoLocacao(toggleId, conteudoId, iconId) {
            const toggle = document.getElementById(toggleId);
            const conteudo = document.getElementById(conteudoId);
            const icon = document.getElementById(iconId);

            if (!toggle || !conteudo) return;

            toggle.addEventListener('click', function () {
                const isHidden = conteudo.classList.contains('hidden');
                conteudo.classList.toggle('hidden');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down', !isHidden);
                    icon.classList.toggle('fa-chevron-up', isHidden);
                }
            });
        }

        function configurarTogglesFinanceiroLocacao() {
            if (!isEditing) return;

            configurarToggleSecaoLocacao('toggleConfigPagamentoLocacao', 'conteudoConfigPagamentoLocacao', 'iconConfigPagamentoLocacao');
            configurarToggleSecaoLocacao('toggleBloqueioLocacao', 'conteudoBloqueioLocacao', 'iconBloqueioLocacao');
            configurarToggleSecaoLocacao('toggleCaucaoLocacao', 'conteudoCaucaoLocacao', 'iconCaucaoLocacao');
        }

        // ===== INIT =====

        configurarAbas();
        configurarTogglesFinanceiroLocacao();
        carregarFormasPagamento(isEditing && locacaoData ? locacaoData.id_forma_pagamento : null);
        carregarTaxasDisponiveis();
        if (!isEditing) carregarTaxasAutomaticas();
        atualizarCamposPlano();
        atualizarCaucaoRequired();
        atualizarStatusOpcoes();
        atualizarVisibilidadePorStatus();
        if (isEditing) {
            atualizarResumo();
            carregarParcelas();
            carregarSelectsParcelas();
        }
    })();
</script>
@endsection
