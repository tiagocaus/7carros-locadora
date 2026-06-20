@extends('layouts.iframe')

@section('title', t('modules.veiculos.title_singular'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle">{{ t('modules.veiculos.new_title') }}</h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>{{ t('common.buttons.back') }}
        </button>
    </div>

    <!-- Formulario -->
    <form id="formVeiculo" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Navegacao de Abas -->
        <div class="mb-4 border-b border-slate-300">
            <nav class="flex -mb-px" id="formTabsNav">
                <button type="button" data-form-tab-target="#tabDadosVeiculo" class="form-tab-button active">
                    <i class="fas fa-car mr-2"></i>{{ t('modules.veiculos.tabs.vehicle_data') }}
                </button>
                <button type="button" data-form-tab-target="#tabPlanoManutencao" class="form-tab-button">
                    <i class="fas fa-tools mr-2"></i>{{ t('modules.veiculos.tabs.maintenance_plan') }} <span class="text-red-500">*</span>
                </button>
                <button type="button" data-form-tab-target="#tabManutencoes" class="form-tab-button" id="tabBtnManutencoes" style="display:none">
                    <i class="fas fa-wrench mr-2"></i>{{ t('modules.veiculos.tabs.maintenances') }}
                </button>
                <button type="button" data-form-tab-target="#tabFaturas" class="form-tab-button" id="tabBtnFaturas" style="display:none">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>{{ t('modules.veiculos.tabs.invoices') }}
                </button>
            </nav>
        </div>

        <!-- Aba 1: Dados do Veiculo -->
        <div id="tabDadosVeiculo" class="form-tab-content active">

        <!-- Secao 1: Dados Basicos -->
        <div class="form-section mb-6 relative">
            <h3 class="form-section-title"><i class="fas fa-car mr-2"></i>{{ t('modules.veiculos.sections.basic_data') }}</h3>

            <!-- Foto no canto superior direito -->
            <div class="absolute top-3 right-3 w-40 h-30 border-2 border-slate-300 rounded-md overflow-hidden bg-slate-100 cursor-pointer group z-10" id="fotoContainer">
                <img id="fotoImg"
                    src="<?= image('assets/img/veiculo_padrao.png') ?>"
                    alt="{{ t('modules.veiculos.fields.photo') }}"
                    class="w-full h-full object-cover">
                <input type="file" id="fotoInput" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                <input type="hidden" id="fotoBase64" name="foto_base64">
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex flex-col justify-end">
                    <div class="bg-black bg-opacity-40 text-white text-center py-2 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        {{ t('modules.veiculos.fields.change_photo') }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Filial -->
                <div class="md:col-span-3 form-input-group">
                    <label for="id_matriz_filial" class="form-label-group">
                        {{ t('modules.veiculos.fields.branch') }} <span class="text-red-500">*</span>
                    </label>
                    <select id="id_matriz_filial" name="id_matriz_filial" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="{{ t('modules.veiculos.placeholders.search_select') }}" required>
                        <option value="">{{ t('modules.veiculos.placeholders.select') }}</option>
                    </select>
                </div>

                <!-- Fornecedor -->
                <div class="md:col-span-3 form-input-group">
                    <label for="id_fornecedor" class="form-label-group">
                        {{ t('modules.veiculos.fields.supplier') }} <span class="text-red-500">*</span>
                    </label>
                    <select id="id_fornecedor" name="id_fornecedor" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/fornecedores/select" data-chosen-placeholder="{{ t('modules.veiculos.placeholders.search_select') }}" required>
                        <option value="">{{ t('modules.veiculos.placeholders.select') }}</option>
                    </select>
                </div>

                <!-- Grupo -->
                <div class="md:col-span-2 form-input-group">
                    <label for="id_grupo" class="form-label-group">{{ t('modules.veiculos.fields.group') }}</label>
                    <select id="id_grupo" name="id_grupo" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/grupos" data-chosen-placeholder="{{ t('modules.veiculos.placeholders.search_select') }}">
                        <option value="">{{ t('modules.veiculos.placeholders.select') }}</option>
                    </select>
                </div>

                <!-- Placa -->
                <div class="md:col-span-2 form-input-group">
                    <label for="placa" class="form-label-group">
                        {{ t('modules.veiculos.fields.plate') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="flex">
                        <input type="text" id="placa" name="placa" class="form-input-group-field rounded-r-none border-r-0" required maxlength="10" placeholder="{{ t('modules.veiculos.placeholders.plate') }}">
                        <button type="button" id="btnBuscarDadosOnline" class="flex items-center justify-center w-[31px] p-0 bg-[#87909d] hover:!bg-[#6b7480] active:!bg-[#5a626d] text-white border-0 rounded-none cursor-pointer transition-colors duration-200" title="{{ t('modules.veiculos.fields.search_online') }}">
                            <i class="fas fa-cloud-arrow-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <!-- Renavam -->
                <div class="md:col-span-3 form-input-group">
                    <label for="renavam" class="form-label-group">{{ t('modules.veiculos.fields.renavam') }}</label>
                    <input type="text" id="renavam" name="renavam" class="form-input-group-field" maxlength="25">
                </div>

                <!-- Chassi -->
                <div class="md:col-span-4 form-input-group">
                    <label for="chassi" class="form-label-group">{{ t('modules.veiculos.fields.chassis') }}</label>
                    <input type="text" id="chassi" name="chassi" class="form-input-group-field" maxlength="45">
                </div>

                <!-- Odometro -->
                <div class="md:col-span-2 form-input-group">
                    <label for="odometro" class="form-label-group">{{ t('modules.veiculos.fields.odometer') }}</label>
                    <input type="text" id="odometro" name="odometro" class="form-input-group-field input-km">
                </div>

                <!-- Disponibilidade -->
                <div class="md:col-span-3 form-input-group">
                    <label for="disponibilidade" class="form-label-group">{{ t('modules.veiculos.fields.availability') }}</label>
                    <select id="disponibilidade" name="disponibilidade" class="form-input-group-field">
                        <option value="D">{{ t('modules.veiculos.availability.available') }}</option>
                        <option value="L">{{ t('modules.veiculos.availability.rented') }}</option>
                        <option value="R">{{ t('modules.veiculos.availability.reserved') }}</option>
                        <option value="O">{{ t('modules.veiculos.availability.in_shop') }}</option>
                        <option value="V">{{ t('modules.veiculos.availability.sold') }}</option>
                        <option value="AV">{{ t('modules.veiculos.availability.for_sale') }}</option>
                        <option value="UI">{{ t('modules.veiculos.availability.internal_use') }}</option>
                        <option value="RO">{{ t('modules.veiculos.availability.stolen') }}</option>
                        <option value="E">{{ t('modules.veiculos.availability.excluded') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Secao 2: Caracteristicas -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-cogs mr-2"></i>{{ t('modules.veiculos.sections.characteristics') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Marca -->
                <div class="md:col-span-3 form-input-group">
                    <label for="marca" class="form-label-group">
                        {{ t('modules.veiculos.fields.brand') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="marca" name="marca" class="form-input-group-field" required maxlength="45">
                </div>

                <!-- Modelo -->
                <div class="md:col-span-3 form-input-group">
                    <label for="modelo" class="form-label-group">
                        {{ t('modules.veiculos.fields.model') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="modelo" name="modelo" class="form-input-group-field" required maxlength="45">
                </div>

                <!-- Ano -->
                <div class="md:col-span-2 form-input-group">
                    <label for="ano" class="form-label-group">
                        {{ t('modules.veiculos.fields.year') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="ano" name="ano" class="form-input-group-field" required maxlength="9" placeholder="{{ t('modules.veiculos.placeholders.year') }}">
                </div>

                <!-- Cor -->
                <div class="md:col-span-2 form-input-group">
                    <label for="cor" class="form-label-group">{{ t('modules.veiculos.fields.color') }}</label>
                    <input type="text" id="cor" name="cor" class="form-input-group-field" maxlength="45">
                </div>

                <!-- Transmissao -->
                <div class="md:col-span-2 form-input-group">
                    <label for="transmissao" class="form-label-group">{{ t('modules.veiculos.fields.transmission') }}</label>
                    <select id="transmissao" name="transmissao" class="form-input-group-field">
                        <option value="">{{ t('modules.veiculos.placeholders.select_option') }}</option>
                        <option value="A">{{ t('modules.veiculos.transmission.automatic') }}</option>
                        <option value="M">{{ t('modules.veiculos.transmission.manual') }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <!-- Motor -->
                <div class="md:col-span-2 form-input-group">
                    <label for="motor" class="form-label-group">{{ t('modules.veiculos.fields.engine') }}</label>
                    <input type="text" id="motor" name="motor" class="form-input-group-field" maxlength="5" placeholder="{{ t('modules.veiculos.placeholders.engine') }}">
                </div>

                <!-- Peso Max -->
                <div class="md:col-span-2 form-input-group">
                    <label for="peso_max" class="form-label-group">{{ t('modules.veiculos.fields.max_weight') }}</label>
                    <input type="text" id="peso_max" name="peso_max" class="form-input-group-field" maxlength="7">
                </div>

                <!-- Localizacao Atual -->
                <div class="md:col-span-4 form-input-group">
                    <label for="id_matriz_filial_localizacao" class="form-label-group">{{ t('modules.veiculos.fields.current_location') }}</label>
                    <select id="id_matriz_filial_localizacao" name="id_matriz_filial_localizacao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="{{ t('modules.veiculos.placeholders.search_select') }}">
                        <option value="">{{ t('modules.veiculos.placeholders.same_as_branch') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Secao 3: Combustivel -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-gas-pump mr-2"></i>{{ t('modules.veiculos.sections.fuel') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Tipo Combustivel -->
                <div class="md:col-span-3 form-input-group">
                    <label for="tipo_combustivel" class="form-label-group">{{ t('modules.veiculos.fields.fuel_type') }}</label>
                    <select id="tipo_combustivel" name="tipo_combustivel" class="form-input-group-field">
                        <option value="">{{ t('modules.veiculos.placeholders.select') }}</option>
                        <option value="GE">{{ t('modules.veiculos.fuel.gasoline_ethanol') }}</option>
                        <option value="G">{{ t('modules.veiculos.fuel.gasoline') }}</option>
                        <option value="E">{{ t('modules.veiculos.fuel.ethanol') }}</option>
                        <option value="D">{{ t('modules.veiculos.fuel.diesel') }}</option>
                        <option value="GAS">{{ t('modules.veiculos.fuel.gas') }}</option>
                        <option value="HE">{{ t('modules.veiculos.fuel.electric') }}</option>
                        <option value="HI">{{ t('modules.veiculos.fuel.hybrid') }}</option>
                    </select>
                </div>

                <!-- Tanque (litros) -->
                <div class="md:col-span-2 form-input-group">
                    <label for="tanque_litros" class="form-label-group">{{ t('modules.veiculos.fields.tank_liters') }}</label>
                    <input type="number" id="tanque_litros" name="tanque_litros" class="form-input-group-field" min="0" maxlength="5">
                </div>

                <!-- Fracao do Tanque -->
                <div class="md:col-span-2 form-input-group">
                    <label for="tanque_fracao" class="form-label-group">{{ t('modules.veiculos.fields.tank_fraction') }}</label>
                    <select id="tanque_fracao" name="tanque_fracao" class="form-input-group-field">
                        <option value="">{{ t('modules.veiculos.placeholders.select') }}</option>
                        <option value="8">{{ t('modules.veiculos.tank_fraction.full') }}</option>
                        <option value="7">7/8</option>
                        <option value="6">3/4</option>
                        <option value="5">5/8</option>
                        <option value="4">1/2</option>
                        <option value="3">3/8</option>
                        <option value="2">1/4</option>
                        <option value="1">1/8</option>
                        <option value="0">{{ t('modules.veiculos.tank_fraction.reserve') }}</option>
                    </select>
                </div>

                <!-- Valor por Fracao -->
                <div class="md:col-span-3 form-input-group">
                    <label for="valor_por_fracao" class="form-label-group">{{ t('modules.veiculos.fields.fraction_value') }}</label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_por_fracao" name="valor_por_fracao" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao 4: Compra/Venda -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-dollar-sign mr-2"></i>{{ t('modules.veiculos.sections.purchase_sale') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Data Compra -->
                <div class="md:col-span-2 form-input-group">
                    <label for="data_compra" class="form-label-group">{{ t('modules.veiculos.fields.purchase_date') }}</label>
                    <input type="date" id="data_compra" name="data_compra" class="form-input-group-field">
                </div>

                <!-- Valor Compra -->
                <div class="md:col-span-3 form-input-group">
                    <label for="valor_compra" class="form-label-group">{{ t('modules.veiculos.fields.purchase_value') }}</label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_compra" name="valor_compra" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>

                <!-- Disponivel para Venda -->
                <div class="md:col-span-2 form-input-group flex items-end pb-2">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="vender" name="vender" value="S" class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-700">{{ t('modules.veiculos.fields.for_sale') }} {!! aviso(t('modules.veiculos.messages.for_sale_tooltip')) !!}</span>
                    </label>
                </div>

                <!-- Data Venda -->
                <div class="md:col-span-2 form-input-group">
                    <label for="data_venda" class="form-label-group">{{ t('modules.veiculos.fields.sale_date') }}</label>
                    <input type="date" id="data_venda" name="data_venda" class="form-input-group-field">
                </div>

                <!-- Valor Venda -->
                <div class="md:col-span-3 form-input-group">
                    <label for="valor_venda" class="form-label-group">{{ t('modules.veiculos.fields.sale_value') }}</label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_venda" name="valor_venda" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao 5: Encargos do Veiculo -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-file-invoice-dollar mr-2"></i>{{ t('modules.veiculos.sections.vehicle_charges') }}</h3>

            <div id="encargos-container">
                <!-- Tabela de encargos -->
                <style>
                    #encargos-table .encargo-form-row input,
                    #encargos-table .encargo-form-row select {
                        padding-left: 6px;
                        padding-right: 6px;
                        border: 1px solid #CBD5E1;
                        border-radius: 0.25rem;
                        outline: none;
                    }
                    #encargos-table .encargo-form-row input:focus,
                    #encargos-table .encargo-form-row select:focus {
                        border-color: #0ea5e9;
                        box-shadow: 0 0 0 1px #0ea5e9;
                    }
                </style>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="encargos-table">
                        <thead>
                            <tr style="border-bottom: 1px solid #87909d;">
                                <th class="text-left py-2 px-2 font-medium text-slate-600">{{ t('modules.veiculos.fields.charge_name') }} *</th>
                                <th class="text-left py-2 px-2 font-medium text-slate-600">{{ t('modules.veiculos.fields.charge_description') }}</th>
                                <th class="text-left py-2 px-2 font-medium text-slate-600">{{ t('modules.veiculos.fields.charge_value') }}</th>
                                <th class="text-left py-2 px-2 font-medium text-slate-600">{{ t('modules.veiculos.fields.charge_due_date') }}</th>
                                <th class="text-left py-2 px-2 font-medium text-slate-600">{{ t('modules.veiculos.fields.charge_recurrence') }}</th>
                                <th class="text-left py-2 px-2 font-medium text-slate-600">{{ t('modules.veiculos.fields.charge_days_advance') }}</th>
                                <th class="text-center py-2 px-2 font-medium text-slate-600" style="width: 80px;">{{ t('common.labels.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="encargos-tbody">
                            <!-- Encargos carregados via JS -->
                        </tbody>
                    </table>
                </div>

                <p id="encargos-empty" class="text-sm text-slate-400 dark:text-slate-500 py-4 text-center" style="display: none;">{{ t('modules.veiculos.fields.no_charges') }}</p>

                <!-- Botao adicionar -->
                <div class="mt-3">
                    <button type="button" id="btn-add-encargo" class="btn btn-sm btn-outline-primary" onclick="adicionarLinhaEncargo()">
                        <i class="fas fa-plus mr-1"></i> {{ t('modules.veiculos.fields.add_charge') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Secao 6: Descricao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-sticky-note mr-2"></i>{{ t('modules.veiculos.sections.description') }}</h3>

            <div class="form-input-group">
                <textarea id="descricao" name="descricao" class="form-input-group-field" rows="3" maxlength="255" placeholder="{{ t('modules.veiculos.placeholders.description') }}"></textarea>
            </div>
        </div>

        <!-- Secao 7: Acessorios -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-puzzle-piece mr-2"></i>{{ t('modules.veiculos.sections.accessories') }}</h3>

            <div class="form-input-group">
                <label class="form-label-group">{{ t('modules.veiculos.fields.accessories') }}</label>
                <div id="acessoriosDropdown" class="filiais-dropdown">
                    <div class="filiais-dropdown-trigger" id="acessoriosDropdownTrigger">
                        <span class="filiais-dropdown-text" id="acessoriosDropdownText">{{ t('modules.veiculos.placeholders.select_accessories') }}</span>
                        <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                    </div>
                    <div class="filiais-dropdown-menu" id="acessoriosDropdownMenu">
                        <div class="filiais-loading">
                            <i class="fas fa-spinner fa-spin"></i> {{ t('modules.veiculos.messages.loading_accessories') }}
                        </div>
                    </div>
                </div>
                <input type="hidden" id="acessoriosIdsJson" name="acessorios_ids">
            </div>
        </div>

        </div><!-- Fim Aba 1: Dados do Veiculo -->

        <!-- Aba 2: Plano de Manutencao -->
        <div id="tabPlanoManutencao" class="form-tab-content">
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-clipboard-list mr-2"></i>{{ t('modules.veiculos.sections.select_plan') }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-6 form-input-group">
                        <label for="id_plano_manutencao" class="form-label-group">
                            {{ t('modules.veiculos.maintenance.plan') }} <span class="text-red-500">*</span>
                        </label>
                        <select id="id_plano_manutencao" name="id_plano_manutencao" class="form-input-group-field" required>
                            <option value="">{{ t('modules.veiculos.placeholders.select_plan') }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <button type="button" id="btnRecalcularPlano" class="btn-yellow py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-sync-alt mr-2"></i>{{ t('modules.veiculos.maintenance.recalculate') }}
                        </button>
                        <span class="text-xs text-slate-500 ml-2">{{ t('modules.veiculos.maintenance.recalculate_hint') }}</span>
                    </div>
                </div>
            </div>

            <!-- Motor -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-cog mr-2"></i>{{ t('modules.veiculos.maintenance.engine_section') }}</h3>
                <p class="text-sm text-slate-500 mb-4">{{ t('modules.veiculos.maintenance.engine_hint') }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.engine_oil') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_oleo]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.oil_filter') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_filtrooleo]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.timing_belt') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_correiadentada]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.alternator_belt') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_correiaalternador]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.ac_belt') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_correiaarcondicionado]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.water_pump_belt') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_correiabombadagua]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.air_filter') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_filtrodear]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.cabin_filter') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_filtrodecabine]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.fuel_filter') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_filtrodecombustivel]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.brake_fluid') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_fluidodofreio]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.clutch_fluid') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_fluidoembreagem]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.clutch_disc') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_discodeembreagem]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.gearbox_fluid') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_fluidocaixademarcha]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.cooling_flush') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_limpesaarrefecimento]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.spark_plugs') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_vejas]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.battery') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[motor_bateria]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rodagem -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-tire mr-2"></i>{{ t('modules.veiculos.maintenance.wheels_section') }}</h3>
                <p class="text-sm text-slate-500 mb-4">{{ t('modules.veiculos.maintenance.wheels_hint') }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.tires') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[rodagem_pneus]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.alignment') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[rodagem_alinhamento]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.brake_pads') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[rodagem_pastilhasdefreio]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.brake_discs') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[rodagem_discodefreios]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.tire_rotation') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[rodagem_rodiziodepneus]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acessorios -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-puzzle-piece mr-2"></i>{{ t('modules.veiculos.maintenance.accessories_section') }}</h3>
                <p class="text-sm text-slate-500 mb-4">{{ t('modules.veiculos.maintenance.accessories_hint') }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs">{{ t('modules.veiculos.maintenance.wiper_blades') }}</label>
                        <div class="relative">
                            <input type="text" name="plano[acessorio_paletasparabrisa]" class="form-input-group-field pr-12 input-km-plano" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- Fim Aba 2: Plano de Manutencao -->

        <!-- Aba 3: Manutencoes -->
        <div id="tabManutencoes" class="form-tab-content">
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-wrench mr-2"></i>{{ t('modules.veiculos.tabs.maintenances') }}</h3>

                <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                    <table class="w-full min-w-full divide-y divide-slate-200">
                        <thead class="table-header-custom">
                            <tr>
                                <th class="table-header w-36">{{ t('modules.veiculos.maintenances.table_os') }}</th>
                                <th class="table-header">{{ t('modules.veiculos.maintenances.table_workshop') }}</th>
                                <th class="table-header hidden sm:table-cell text-center">{{ t('modules.veiculos.maintenances.table_send_date') }}</th>
                                <th class="table-header hidden md:table-cell text-center">{{ t('modules.veiculos.maintenances.table_return_date') }}</th>
                                <th class="table-header hidden lg:table-cell text-right">{{ t('modules.veiculos.maintenances.table_total') }}</th>
                                <th class="table-header w-28 text-center">{{ t('modules.veiculos.maintenances.table_status') }}</th>
                                <th class="table-header px-2 w-16 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="manutencoesVeiculoBody" class="bg-white divide-y divide-slate-200">
                            <tr>
                                <td colspan="7" class="text-center py-8">
                                    <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- Fim Aba 3: Manutencoes -->

        <!-- Aba 4: Faturas -->
        <div id="tabFaturas" class="form-tab-content">
            <div class="form-section mb-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <h3 class="form-section-title mb-0"><i class="fas fa-file-invoice-dollar mr-2"></i>{{ t('modules.veiculos.tabs.invoices') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-blue py-2 px-3 rounded-md text-sm font-medium fatura-filter active" data-tipo="R">
                            <i class="fas fa-arrow-down mr-2"></i>{{ t('modules.veiculos.invoices.filter_receivable') }}
                        </button>
                        <button type="button" class="btn-blue py-2 px-3 rounded-md text-sm font-medium fatura-filter active" data-tipo="D">
                            <i class="fas fa-arrow-up mr-2"></i>{{ t('modules.veiculos.invoices.filter_payable') }}
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                    <table class="w-full min-w-full divide-y divide-slate-200">
                        <thead class="table-header-custom">
                            <tr>
                                <th class="table-header w-32">{{ t('modules.veiculos.invoices.table_type') }}</th>
                                <th class="table-header w-32 text-center">{{ t('modules.veiculos.invoices.table_due_date') }}</th>
                                <th class="table-header">{{ t('modules.veiculos.invoices.table_description') }}</th>
                                <th class="table-header hidden md:table-cell">{{ t('modules.veiculos.invoices.table_person') }}</th>
                                <th class="table-header hidden lg:table-cell">{{ t('modules.veiculos.invoices.table_payment_method') }}</th>
                                <th class="table-header hidden xl:table-cell">{{ t('modules.veiculos.invoices.table_origin') }}</th>
                                <th class="table-header w-32 text-right">{{ t('modules.veiculos.invoices.table_value') }}</th>
                                <th class="table-header w-28 text-center">{{ t('modules.veiculos.invoices.table_status') }}</th>
                                <th class="table-header px-2 w-16 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="faturasVeiculoBody" class="bg-white divide-y divide-slate-200">
                            <tr>
                                <td colspan="9" class="text-center py-8">
                                    <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- Fim Aba 4: Faturas -->

        <!-- Botoes de Acao -->
        <div id="formActions" class="flex justify-end space-x-3 mt-6 mb-4">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                {{ t('common.buttons.cancel') }}
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i>{{ t('common.buttons.save') }}
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    // Estado
    let editando = false;
    let registroId = null;
    let fotoBase64 = null;
    let removerFoto = false;
    const fotoPadrao = '<?= image("assets/img/veiculo_padrao.png") ?>';

    // Estado acessorios
    let acessoriosDisponiveis = [];
    let acessoriosSelecionados = [];
    let dropdownAcessoriosAberto = false;

    // Estado plano de manutencao
    let planosDisponiveis = [];
    let planoCarregado = null; // Cache do plano selecionado
    let planoIdPendente = null; // Plano aguardando confirmacao
    let dadosOnlinePendentes = null;
    let faturasVeiculo = [];
    let filtrosFaturasVeiculo = { R: true, D: true };

    // Traducoes JS
    const i18n = {
        editTitle: '<?= t('modules.veiculos.edit_title') ?>',
        selectPlanFirst: '<?= t('modules.veiculos.messages.select_plan_first') ?>',
        invalidImage: '<?= t('modules.veiculos.messages.invalid_image') ?>',
        imageTooLarge: '<?= t('modules.veiculos.messages.image_too_large') ?>',
        accessoriesLoadError: '<?= t('modules.veiculos.messages.accessories_load_error') ?>',
        accessoriesLoadErrorShort: '<?= t('modules.veiculos.messages.accessories_load_error_short') ?>',
        noAccessories: '<?= t('modules.veiculos.messages.no_accessories') ?>',
        noAccessoriesShort: '<?= t('modules.veiculos.messages.no_accessories_short') ?>',
        selectAccessories: '<?= t('modules.veiculos.placeholders.select_accessories') ?>',
        planLoadError: '<?= t('modules.veiculos.messages.plan_load_error') ?>',
        planFetchError: '<?= t('modules.veiculos.messages.plan_fetch_error') ?>',
        recalculateTitle: '<?= t('modules.veiculos.messages.recalculate_title') ?>',
        recalculateConfirm: '<?= t('modules.veiculos.messages.recalculate_confirm') ?>',
        recalculateBtn: '<?= t('modules.veiculos.messages.recalculate_btn') ?>',
        updated: '<?= t('modules.veiculos.messages.updated') ?>',
        created: '<?= t('modules.veiculos.messages.created') ?>',
        saveGenericError: '<?= t('modules.veiculos.messages.save_generic_error') ?>',
        saveError: '<?= t('modules.veiculos.messages.save_error') ?>',
        loadDataError: '<?= t('modules.veiculos.messages.load_data_error') ?>',
        onlinePlateRequired: '<?= t('modules.veiculos.messages.online_plate_required') ?>',
        onlineVehicleLoading: '<?= t('modules.veiculos.messages.online_vehicle_loading') ?>',
        onlineVehicleSuccess: '<?= t('modules.veiculos.messages.online_vehicle_success') ?>',
        onlineVehicleNoData: '<?= t('modules.veiculos.messages.online_vehicle_no_data') ?>',
        onlineVehicleError: '<?= t('modules.veiculos.messages.online_vehicle_error') ?>',
        onlineOverwriteTitle: '<?= t('modules.veiculos.messages.online_overwrite_title') ?>',
        onlineOverwriteConfirm: '<?= t('modules.veiculos.messages.online_overwrite_confirm') ?>',
        onlineOverwriteButton: '<?= t('modules.veiculos.messages.online_overwrite_button') ?>',
        saving: '<?= t('common.labels.saving') ?>',
        save: '<?= t('common.buttons.save') ?>',
        selectPlaceholder: '<?= t('modules.veiculos.placeholders.select') ?>',
        sameAsBranch: '<?= t('modules.veiculos.placeholders.same_as_branch') ?>',
        // Labels dinamicos combustivel/bateria
        tankLiters: '<?= t('modules.veiculos.fields.tank_liters') ?>',
        tankFraction: '<?= t('modules.veiculos.fields.tank_fraction') ?>',
        batteryKwh: '<?= t('modules.veiculos.fields.battery_kwh') ?>',
        batteryCharge: '<?= t('modules.veiculos.fields.battery_charge') ?>',
        fuelFull: '<?= t('modules.veiculos.tank_fraction.full') ?>',
        fuelReserve: '<?= t('modules.veiculos.tank_fraction.reserve') ?>',
        // Manutencoes
        mntNoRecords: '<?= t('modules.veiculos.maintenances.no_records') ?>',
        mntLoadError: '<?= t('modules.veiculos.maintenances.load_error') ?>',
        mntStatusCreated: '<?= t('modules.veiculos.maintenances.status_created') ?>',
        mntStatusOpen: '<?= t('modules.veiculos.maintenances.status_open') ?>',
        mntStatusClosed: '<?= t('modules.veiculos.maintenances.status_closed') ?>',
        mntActionPrint: '<?= t('modules.veiculos.maintenances.action_print') ?>',
        mntPrintTitle: '<?= t('modules.manutencao.print.title') ?>',
        invLoadError: '<?= t('modules.veiculos.invoices.load_error') ?>',
        invNoRecords: '<?= t('modules.veiculos.invoices.no_records') ?>',
        invNoFilteredRecords: '<?= t('modules.veiculos.invoices.no_filtered_records') ?>',
        invReceivable: '<?= t('modules.veiculos.invoices.receivable') ?>',
        invPayable: '<?= t('modules.veiculos.invoices.payable') ?>',
        invPaid: '<?= t('modules.veiculos.invoices.status_paid') ?>',
        invOverdue: '<?= t('modules.veiculos.invoices.status_overdue') ?>',
        invPending: '<?= t('modules.veiculos.invoices.status_pending') ?>',
        invOpen: '<?= t('modules.veiculos.invoices.action_open') ?>',
        invEntriesTitle: '<?= t('menu.financeiro_menu.entries') ?>',
    };

    // Elementos do formulario
    const form = document.getElementById('formVeiculo');
    const pageTitle = document.getElementById('pageTitle');
    const inputId = document.getElementById('registroId');

    // Elementos de foto
    const fotoContainer = document.getElementById('fotoContainer');
    const fotoInput = document.getElementById('fotoInput');
    const fotoImg = document.getElementById('fotoImg');
    const fotoBase64Input = document.getElementById('fotoBase64');

    // ===== LABELS DINAMICOS COMBUSTIVEL/BATERIA =====

    function atualizarLabelsCombustivel() {
        const tipo = document.getElementById('tipo_combustivel').value;
        const isEletrico = FuelLabels.isElectric(tipo);
        document.querySelector('label[for="tanque_litros"]').textContent = isEletrico ? i18n.batteryKwh : i18n.tankLiters;
        document.querySelector('label[for="tanque_fracao"]').textContent = isEletrico ? i18n.batteryCharge : i18n.tankFraction;
        FuelLabels.updateSelectOptions(document.getElementById('tanque_fracao'), tipo, i18n.fuelFull, i18n.fuelReserve);
    }

    document.getElementById('tipo_combustivel').addEventListener('change', atualizarLabelsCombustivel);

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
        navegarPara('/pages/veiculos');
    }

    // ===== INICIALIZACAO =====

    async function init() {
        // Carregar dados dos selects (Filiais, Fornecedores e Grupos agora sao chosen-select server-side)
        await Promise.all([
            carregarAcessorios(),
            carregarPlanosManutencao()
        ]);

        // Verificar se estamos editando
        const urlParams = new URLSearchParams(window.location.search);
        registroId = urlParams.get('id');

        // Verificar rota com ID
        const pathMatch = window.location.pathname.match(/\/veiculos\/(\d+)\/editar/);
        if (pathMatch) {
            registroId = pathMatch[1];
        }

        if (registroId) {
            editando = true;
            pageTitle.textContent = i18n.editTitle;
            await carregarDados(registroId);

            // Exibir aba de manutencoes e carregar dados
            document.getElementById('tabBtnManutencoes').style.display = '';
            document.getElementById('tabBtnFaturas').style.display = '';
            carregarManutencoesVeiculo(registroId);
            carregarFaturasVeiculo(registroId);
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

    function mostrarAlerta(message) {
        window.parent.postMessage({
            action: 'openAlert',
            message: message
        }, '*');
    }

    function normalizarPlaca(placa) {
        return String(placa || '').replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    }

    function setCampoSeAplicavel(id, valor, sobrescrever) {
        const el = document.getElementById(id);
        if (!el || valor === undefined || valor === null || valor === '') return false;
        if (!sobrescrever && el.value) return false;
        el.value = valor;
        el.dispatchEvent(new Event('change'));
        return true;
    }

    function separarMarcaModelo(descricao) {
        const texto = String(descricao || '').trim();
        if (!texto) return { marca: '', modelo: '' };

        const partes = texto.split('/').map(p => p.trim()).filter(Boolean);
        if (partes.length >= 2) {
            return {
                marca: partes.shift(),
                modelo: partes.join(' ')
            };
        }

        return { marca: '', modelo: texto };
    }

    function mapearCombustivelOnline(descricao) {
        const texto = String(descricao || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        if (!texto) return '';
        if (texto.includes('eletric')) return 'HE';
        if (texto.includes('hibrid')) return 'HI';
        if (texto.includes('diesel')) return 'D';
        if (texto.includes('gas natural') || texto.includes('gnv') || texto === 'gas') return 'GAS';
        if (texto.includes('alcool') || texto.includes('etanol')) {
            return texto.includes('gasolina') ? 'GE' : 'E';
        }
        if (texto.includes('gasolina')) return 'G';
        return '';
    }

    function mapearDadosVeiculoOnline(dados) {
        const veiculo = dados || {};
        const marcaModelo = separarMarcaModelo(veiculo.descricaoMarcaModelo || veiculo.marcaModelo || veiculo.modelo || '');
        const anoFabricacao = veiculo.anoFabricacao || '';
        const anoModelo = veiculo.anoModelo || '';
        const ano = anoFabricacao && anoModelo
            ? `${anoFabricacao}/${anoModelo}`
            : String(anoModelo || anoFabricacao || '');

        return {
            placa: normalizarPlaca(veiculo.placa || ''),
            renavam: veiculo.renavam || '',
            chassi: veiculo.chassi || '',
            marca: marcaModelo.marca,
            modelo: marcaModelo.modelo,
            ano: ano,
            cor: veiculo.descricaoCor || veiculo.cor || '',
            tipo_combustivel: mapearCombustivelOnline(veiculo.descricaoCombustivel || veiculo.combustivel || '')
        };
    }

    function camposOnlineComValor(campos) {
        return Object.entries(campos).filter(([, valor]) => valor !== undefined && valor !== null && valor !== '');
    }

    function possuiCampoPreenchidoParaOnline(campos) {
        return camposOnlineComValor(campos).some(([id]) => {
            if (id === 'placa') return false;
            const el = document.getElementById(id);
            return el && el.value;
        });
    }

    function aplicarDadosOnline(campos, sobrescrever) {
        let preenchidos = 0;
        camposOnlineComValor(campos).forEach(([id, valor]) => {
            if (setCampoSeAplicavel(id, valor, sobrescrever)) {
                preenchidos++;
            }
        });

        if (preenchidos > 0) {
            atualizarLabelsCombustivel();
            mostrarAlerta(i18n.onlineVehicleSuccess);
        } else {
            mostrarAlerta(i18n.onlineVehicleNoData);
        }
    }

    async function buscarDadosVeiculoOnline() {
        const placaInput = document.getElementById('placa');
        const placa = normalizarPlaca(placaInput?.value || '');
        if (!placa) {
            mostrarAlerta(i18n.onlinePlateRequired);
            return;
        }

        const button = document.getElementById('btnBuscarDadosOnline');
        const originalHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.get(`/api/multas-online/veiculo/${encodeURIComponent(placa)}`);
            if (!result.success) {
                mostrarAlerta(result.message || i18n.onlineVehicleError);
                return;
            }

            const dados = result.data?.veiculo || result.data || {};
            const campos = mapearDadosVeiculoOnline(dados);

            if (camposOnlineComValor(campos).length === 0) {
                mostrarAlerta(i18n.onlineVehicleNoData);
                return;
            }

            if (possuiCampoPreenchidoParaOnline(campos)) {
                dadosOnlinePendentes = campos;
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.onlineOverwriteTitle,
                    message: i18n.onlineOverwriteConfirm,
                    confirmText: i18n.onlineOverwriteButton
                }, '*');
                return;
            }

            aplicarDadosOnline(campos, false);
        } catch (error) {
            console.error('Erro ao consultar dados do veiculo na Consulta Online:', error);
            mostrarAlerta(error.message || i18n.onlineVehicleError);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    }

    // ===== ACESSORIOS =====

    async function carregarAcessorios() {
        const dropdownMenu = document.getElementById('acessoriosDropdownMenu');
        const dropdownText = document.getElementById('acessoriosDropdownText');

        try {
            const result = await API.get('/api/veiculos-acessorios');

            if (result.success && result.data) {
                acessoriosDisponiveis = result.data;
                renderizarAcessorios();
            } else {
                dropdownMenu.innerHTML = '<div class="filiais-dropdown-error">' + i18n.accessoriesLoadError + '</div>';
                dropdownText.textContent = i18n.accessoriesLoadErrorShort;
            }
        } catch (error) {
            console.error('Erro ao carregar acessorios:', error);
            dropdownMenu.innerHTML = '<div class="filiais-dropdown-error">' + i18n.accessoriesLoadError + '</div>';
            dropdownText.textContent = i18n.accessoriesLoadErrorShort;
        }
    }

    function renderizarAcessorios() {
        const dropdownMenu = document.getElementById('acessoriosDropdownMenu');
        const dropdownText = document.getElementById('acessoriosDropdownText');

        if (acessoriosDisponiveis.length === 0) {
            dropdownMenu.innerHTML = '<div class="filiais-dropdown-empty">' + i18n.noAccessories + '</div>';
            dropdownText.textContent = i18n.noAccessoriesShort;
            return;
        }

        let html = '';
        acessoriosDisponiveis.forEach((acessorio) => {
            const isChecked = acessoriosSelecionados.includes(parseInt(acessorio.id));

            html += `
                <div class="filial-item ${isChecked ? 'selected' : ''}" data-id="${acessorio.id}" data-nome="${acessorio.nome}">
                    <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                        <input type="checkbox" class="acessorio-checkbox" value="${acessorio.id}" ${isChecked ? 'checked' : ''}>
                        <span class="filial-nome">${acessorio.nome}</span>
                    </label>
                </div>
            `;
        });

        dropdownMenu.innerHTML = html;

        // Event listeners
        dropdownMenu.querySelectorAll('.acessorio-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleAcessorioCheckboxChange);
        });

        atualizarTextoDropdownAcessorios();
    }

    function handleAcessorioCheckboxChange(e) {
        const checkbox = e.target;
        const acessorioItem = checkbox.closest('.filial-item');
        const acessorioId = parseInt(checkbox.value);

        if (checkbox.checked) {
            acessorioItem.classList.add('selected');
            if (!acessoriosSelecionados.includes(acessorioId)) {
                acessoriosSelecionados.push(acessorioId);
            }
        } else {
            acessorioItem.classList.remove('selected');
            acessoriosSelecionados = acessoriosSelecionados.filter(id => id !== acessorioId);
        }

        atualizarTextoDropdownAcessorios();
        atualizarHiddenInputAcessorios();
    }

    function atualizarTextoDropdownAcessorios() {
        const dropdownText = document.getElementById('acessoriosDropdownText');
        const selecionados = Array.from(document.querySelectorAll('.acessorio-checkbox:checked'))
            .map(cb => cb.closest('.filial-item').dataset.nome);

        if (selecionados.length === 0) {
            dropdownText.textContent = i18n.selectAccessories;
        } else if (selecionados.length <= 3) {
            dropdownText.textContent = selecionados.join(', ');
        } else {
            dropdownText.textContent = `${selecionados.slice(0, 2).join(', ')} +${selecionados.length - 2}`;
        }
    }

    function atualizarHiddenInputAcessorios() {
        document.getElementById('acessoriosIdsJson').value = JSON.stringify(acessoriosSelecionados);
    }

    function setAcessoriosSelecionados(ids) {
        acessoriosSelecionados = ids.map(id => parseInt(id));
        renderizarAcessorios();
        atualizarHiddenInputAcessorios();
    }

    function configurarDropdownAcessorios() {
        const dropdown = document.getElementById('acessoriosDropdown');
        const trigger = document.getElementById('acessoriosDropdownTrigger');

        trigger?.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownAcessoriosAberto = !dropdownAcessoriosAberto;
            dropdown.classList.toggle('open', dropdownAcessoriosAberto);
        });

        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target)) {
                dropdownAcessoriosAberto = false;
                dropdown.classList.remove('open');
            }
        });
    }

    // ===== PLANO DE MANUTENCAO =====

    async function carregarPlanosManutencao() {
        try {
            const result = await API.get('/api/manutencoes-planos');
            if (result.success) {
                planosDisponiveis = result.data;
                const select = document.getElementById('id_plano_manutencao');
                result.data.forEach(p => {
                    select.add(new Option(p.nome, p.id));
                });
            }
        } catch (error) {
            console.error(i18n.planLoadError, error);
        }
    }

    // Busca o plano completo (com intervalos) via API
    async function buscarPlanoCompleto(planoId) {
        try {
            const result = await API.get(`/api/manutencoes-planos/${planoId}`);
            if (result.success) {
                return result.data;
            }
        } catch (error) {
            console.error(i18n.planFetchError, error);
        }
        return null;
    }

    // Calcula e preenche os campos de km do plano
    async function calcularPlanoManutencao(planoId, forcarRecalculo = false) {
        if (!planoId) return;

        const plano = await buscarPlanoCompleto(planoId);
        if (!plano || !plano.intervalos) return;

        planoCarregado = plano;

        const odometroInput = document.getElementById('odometro');
        const odometroAtual = Km.parse(odometroInput?.value || '0');

        // Preencher cada campo com odometro + intervalo
        for (const [item, intervaloStr] of Object.entries(plano.intervalos)) {
            const intervalo = parseKmPlano(intervaloStr);
            const proximaKm = odometroAtual + intervalo;

            const input = document.querySelector(`input[name="plano[${item}]"]`);
            if (input) {
                // Se forcarRecalculo ou valor atual for 0, preenche
                if (forcarRecalculo || parseKmPlano(input.value) === 0) {
                    input.value = formatKmPlano(proximaKm);
                }
            }
        }
    }

    // Recalcula todos os valores baseado no odometro atual
    async function recalcularPlanoManutencao() {
        const planoId = document.getElementById('id_plano_manutencao').value;
        if (!planoId) {
            mostrarAlerta(i18n.selectPlanFirst);
            return;
        }
        await calcularPlanoManutencao(planoId, true);
    }

    // Preenche os campos do plano com valores salvos (para edicao)
    function preencherPlanoManutencaoSalvo(planoArray) {
        if (!planoArray) return;

        // Se for string JSON, parse
        let dados = planoArray;
        if (typeof planoArray === 'string') {
            try {
                dados = JSON.parse(planoArray);
            } catch (e) {
                console.error('Erro ao parsear plano_manutencao_array:', e);
                return;
            }
        }

        // Preencher cada campo
        for (const [item, valor] of Object.entries(dados)) {
            const input = document.querySelector(`input[name="plano[${item}]"]`);
            if (input) {
                input.value = formatKmPlano(parseKmPlano(valor));
            }
        }
    }

    // Coleta os dados do plano para enviar ao backend
    function coletarDadosPlano() {
        const planoData = {};
        document.querySelectorAll('.input-km-plano').forEach(input => {
            const match = input.name.match(/plano\[(.+)\]/);
            if (match && match[1]) {
                planoData[match[1]] = parseKmPlano(input.value);
            }
        });
        return planoData;
    }

    // Formata km para exibicao (10000 -> "10.000")
    function formatKmPlano(valor) {
        if (!valor || valor === 0) return '0';
        return parseInt(valor).toLocaleString('pt-BR');
    }

    // Parse km da string ("10.000" -> 10000)
    function parseKmPlano(valor) {
        if (!valor) return 0;
        if (typeof valor === 'number') return valor;
        return parseInt(String(valor).replace(/\D/g, '')) || 0;
    }

    // Configura mascara de km nos campos do plano
    function configurarMascarasKmPlano() {
        document.querySelectorAll('.input-km-plano').forEach(input => {
            input.addEventListener('blur', function() {
                const valor = parseKmPlano(this.value);
                this.value = formatKmPlano(valor);
            });
        });
    }

    // ===== MANUTENCOES DO VEICULO =====

    async function carregarManutencoesVeiculo(idVeiculo) {
        const tbody = document.getElementById('manutencoesVeiculoBody');
        try {
            const result = await API.get(`/api/veiculos/${idVeiculo}/manutencoes`);

            if (result.success) {
                renderManutencoesVeiculo(result.data);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle text-2xl mb-2"></i><p>${i18n.mntLoadError}</p></td></tr>`;
            }
        } catch (error) {
            console.error('Erro ao carregar manutencoes:', error);
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle text-2xl mb-2"></i><p>${i18n.mntLoadError}</p></td></tr>`;
        }
    }

    function renderManutencoesVeiculo(manutencoes) {
        const tbody = document.getElementById('manutencoesVeiculoBody');

        if (!manutencoes || manutencoes.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-slate-500"><i class="fas fa-wrench text-4xl mb-3 opacity-30"></i><p>${i18n.mntNoRecords}</p></td></tr>`;
            return;
        }

        let html = '';
        manutencoes.forEach(m => {
            const oficina = m.oficina_nome || '-';

            let statusBadge = '';
            switch (m.status) {
                case 'C':
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">${i18n.mntStatusCreated}</span>`;
                    break;
                case 'A':
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${i18n.mntStatusOpen}</span>`;
                    break;
                case 'F':
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.mntStatusClosed}</span>`;
                    break;
            }

            html += `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="table-cell font-mono text-sm">${m.os}</td>
                    <td class="table-cell">${oficina}</td>
                    <td class="table-cell hidden sm:table-cell text-center">${m.data_enviado_formatted || '-'}</td>
                    <td class="table-cell hidden md:table-cell text-center">${m.data_retorno_formatted || '-'}</td>
                    <td class="table-cell hidden lg:table-cell text-right font-medium">${Currency.format(m.total_servicos, true)}</td>
                    <td class="table-cell text-center">${statusBadge}</td>
                    <td class="table-cell text-center">
                        <button onclick="imprimirManutencaoVeiculo(${m.id})" class="btn-icon text-blue-600 hover:text-blue-800" title="${i18n.mntActionPrint}"><i class="fas fa-print"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    window.imprimirManutencaoVeiculo = function(id) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openPrintModal',
                url: '/manutencoes/' + id + '/imprimir',
                title: i18n.mntPrintTitle
            }, '*');
        } else {
            window.open('/manutencoes/' + id + '/imprimir', '_blank');
        }
    };

    // ===== FATURAS DO VEICULO =====

    async function carregarFaturasVeiculo(idVeiculo) {
        const tbody = document.getElementById('faturasVeiculoBody');
        try {
            const result = await API.get(`/api/veiculos/${idVeiculo}/faturas`);

            if (result.success) {
                faturasVeiculo = result.data || [];
                renderFaturasVeiculo();
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle text-2xl mb-2"></i><p>${i18n.invLoadError}</p></td></tr>`;
            }
        } catch (error) {
            console.error('Erro ao carregar faturas:', error);
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle text-2xl mb-2"></i><p>${i18n.invLoadError}</p></td></tr>`;
        }
    }

    function renderFaturasVeiculo() {
        const tbody = document.getElementById('faturasVeiculoBody');
        if (!tbody) return;

        if (!faturasVeiculo || faturasVeiculo.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-slate-500"><i class="fas fa-file-invoice-dollar text-4xl mb-3 opacity-30"></i><p>${i18n.invNoRecords}</p></td></tr>`;
            return;
        }

        const faturasFiltradas = faturasVeiculo.filter(fatura => filtrosFaturasVeiculo[fatura.tipo]);
        if (faturasFiltradas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-slate-500"><i class="fas fa-filter text-4xl mb-3 opacity-30"></i><p>${i18n.invNoFilteredRecords}</p></td></tr>`;
            return;
        }

        let html = '';
        faturasFiltradas.forEach(fatura => {
            const tipoLabel = fatura.tipo === 'R' ? i18n.invReceivable : i18n.invPayable;
            const tipoClass = fatura.tipo === 'R'
                ? 'bg-green-100 text-green-700'
                : 'bg-amber-100 text-amber-700';
            const pessoa = fatura.tipo === 'R'
                ? (fatura.cliente_nome || '-')
                : (fatura.fornecedor_nome || '-');
            const codigo = fatura.codigo || fatura.documento || (fatura.sequencia ? `#${fatura.sequencia}` : `#${fatura.id}`);
            const descricao = fatura.descricao || '-';

            let statusLabel = fatura.status_label || i18n.invPending;
            let statusClass = 'bg-slate-100 text-slate-700';
            if (fatura.status === 'paid') {
                statusLabel = i18n.invPaid;
                statusClass = 'bg-green-100 text-green-700';
            } else if (fatura.status === 'overdue') {
                statusLabel = i18n.invOverdue;
                statusClass = 'bg-red-100 text-red-700';
            } else if (fatura.status === 'pending') {
                statusLabel = i18n.invPending;
                statusClass = 'bg-blue-100 text-blue-700';
            }

            html += `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="table-cell">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${tipoClass}">${tipoLabel}</span>
                    </td>
                    <td class="table-cell text-center">${fatura.data_venci_formatted || '-'}</td>
                    <td class="table-cell">
                        <div class="font-medium">${escapeHtml(codigo)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(descricao)}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell">${escapeHtml(pessoa)}</td>
                    <td class="table-cell hidden lg:table-cell">${escapeHtml(fatura.forma_pagamento_descricao || '-')}</td>
                    <td class="table-cell hidden xl:table-cell">${escapeHtml(fatura.origem || '-')}</td>
                    <td class="table-cell text-right font-medium">${fatura.valor_total_formatted || Currency.format(fatura.valor_total, true)}</td>
                    <td class="table-cell text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusClass}">${statusLabel}</span>
                    </td>
                    <td class="table-cell text-center">
                        <button type="button" onclick="abrirFaturaVeiculo(${fatura.id})" class="btn-icon text-blue-600 hover:text-blue-800" title="${i18n.invOpen}">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    window.abrirFaturaVeiculo = function(id) {
        if (!id) return;

        const page = `/pages/financeiro/adicionar?id=${encodeURIComponent(id)}`;
        if (window.parent !== window && typeof window.parent.openOrSwitchToTab === 'function') {
            window.parent.openOrSwitchToTab(page, i18n.invEntriesTitle, 'fas fa-file-invoice-dollar', `financeiro-${id}`);
            return;
        }

        navegarPara(page);
    };

    // ===== ABAS =====

    function configurarAbas() {
        const formTabButtons = document.querySelectorAll('#formTabsNav .form-tab-button');
        const formTabContents = document.querySelectorAll('.form-tab-content');

        formTabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remover active de todos
                formTabButtons.forEach(btn => btn.classList.remove('active'));
                formTabContents.forEach(content => content.classList.remove('active'));

                // Ativar aba clicada
                button.classList.add('active');
                const targetId = button.dataset.formTabTarget;
                document.querySelector(targetId)?.classList.add('active');

                const formActions = document.getElementById('formActions');
                if (formActions) {
                    const esconderAcoes = ['#tabManutencoes', '#tabFaturas'].includes(targetId);
                    formActions.style.display = esconderAcoes ? 'none' : '';
                }
            });
        });
    }

    // ===== CARREGAR DADOS =====

    async function carregarDados(id) {
        try {
            const result = await API.get(`/api/veiculos/${id}`);

            if (!result.success) {
                mostrarAlerta(result.message || i18n.loadDataError);
                voltar();
                return;
            }

            preencherFormulario(result.data);
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            mostrarAlerta(i18n.loadDataError);
            voltar();
        }
    }

    // Converter data formatada (d/m/Y) para formato de input date (Y-m-d)
    function converterDataParaInput(dataFormatada) {
        if (!dataFormatada) return '';

        // Se já está no formato Y-m-d, retornar como está
        if (/^\d{4}-\d{2}-\d{2}$/.test(dataFormatada)) {
            return dataFormatada;
        }

        // Converter de d/m/Y para Y-m-d
        const partes = dataFormatada.split('/');
        if (partes.length === 3) {
            const dia = partes[0].padStart(2, '0');
            const mes = partes[1].padStart(2, '0');
            const ano = partes[2];
            return `${ano}-${mes}-${dia}`;
        }

        return '';
    }

    function preencherFormulario(data) {
        inputId.value = data.id || '';

        // Dados basicos - campos chosen-select server-side
        if (data.id_matriz_filial && data.filial_nome) {
            const select = document.getElementById('id_matriz_filial');
            select.innerHTML = `<option value="">${i18n.selectPlaceholder}</option><option value="${data.id_matriz_filial}" selected>${escapeHtml(data.filial_nome)}</option>`;
            select.dispatchEvent(new Event('change'));
        }

        if (data.id_fornecedor && data.fornecedor_nome) {
            const select = document.getElementById('id_fornecedor');
            select.innerHTML = `<option value="">${i18n.selectPlaceholder}</option><option value="${data.id_fornecedor}" selected>${escapeHtml(data.fornecedor_nome)}</option>`;
            select.dispatchEvent(new Event('change'));
        }

        if (data.id_grupo && data.grupo_nome) {
            const select = document.getElementById('id_grupo');
            select.innerHTML = `<option value="">${i18n.selectPlaceholder}</option><option value="${data.id_grupo}" selected>${escapeHtml(data.grupo_nome)}</option>`;
            select.dispatchEvent(new Event('change'));
        }

        document.getElementById('placa').value = data.placa || '';
        document.getElementById('renavam').value = data.renavam || '';
        document.getElementById('chassi').value = data.chassi || '';
        document.getElementById('odometro').value = data.odometro ? Km.format(data.odometro) : '';
        document.getElementById('disponibilidade').value = data.disponibilidade || 'D';

        // Caracteristicas
        document.getElementById('marca').value = data.marca || '';
        document.getElementById('modelo').value = data.modelo || '';
        document.getElementById('ano').value = data.ano || '';
        document.getElementById('cor').value = data.cor || '';
        document.getElementById('transmissao').value = data.transmissao || '';
        document.getElementById('motor').value = data.motor || '';
        document.getElementById('peso_max').value = data.peso_max || '';

        // Localizacao - chosen-select server-side
        if (data.id_matriz_filial_localizacao && data.localizacao_nome) {
            const select = document.getElementById('id_matriz_filial_localizacao');
            select.innerHTML = `<option value="">${i18n.sameAsBranch}</option><option value="${data.id_matriz_filial_localizacao}" selected>${escapeHtml(data.localizacao_nome)}</option>`;
            select.dispatchEvent(new Event('change'));
        }

        // Combustivel
        document.getElementById('tipo_combustivel').value = data.tipo_combustivel || '';
        atualizarLabelsCombustivel();
        document.getElementById('tanque_litros').value = data.tanque_litros || '';
        document.getElementById('tanque_fracao').value = data.tanque_fracao || '';
        document.getElementById('valor_por_fracao').value = data.valor_por_fracao ? Currency.format(data.valor_por_fracao) : '';

        // Compra/Venda
        document.getElementById('data_compra').value = converterDataParaInput(data.data_compra_formatted || '');
        document.getElementById('valor_compra').value = data.valor_compra ? Currency.format(data.valor_compra) : '';
        document.getElementById('vender').checked = data.vender === 'S';
        document.getElementById('data_venda').value = converterDataParaInput(data.data_venda_formatted || '');
        document.getElementById('valor_venda').value = data.valor_venda ? Currency.format(data.valor_venda) : '';

        // Encargos do Veiculo
        carregarEncargos(data.id);

        // Descricao
        document.getElementById('descricao').value = data.descricao || '';

        // Acessorios vinculados
        if (data.acessorios_vinculados && data.acessorios_vinculados.length > 0) {
            setAcessoriosSelecionados(data.acessorios_vinculados);
        }

        // Plano de manutencao
        if (data.id_plano_manutencao) {
            document.getElementById('id_plano_manutencao').value = data.id_plano_manutencao;
        }

        // Valores salvos do plano (proxima km de cada item)
        if (data.plano_manutencao_array) {
            preencherPlanoManutencaoSalvo(data.plano_manutencao_array);
        }

        // Foto
        if (data.foto_url) {
            mostrarPreviewFoto(data.foto_url);
        }
    }

    // ===== FOTO =====

    function mostrarPreviewFoto(src) {
        if (fotoImg) fotoImg.src = src;
    }

    function resetarFoto() {
        if (fotoImg) fotoImg.src = fotoPadrao;
        fotoBase64 = null;
        if (fotoBase64Input) fotoBase64Input.value = '';
        if (fotoInput) fotoInput.value = '';
    }

    // ===== CONFIGURAR EVENTOS =====

    function configurarEventos() {
        // Botao voltar
        document.getElementById('btnVoltar')?.addEventListener('click', voltar);
        document.getElementById('btnCancelar')?.addEventListener('click', voltar);
        document.getElementById('btnBuscarDadosOnline')?.addEventListener('click', buscarDadosVeiculoOnline);

        // Eventos de foto
        fotoContainer?.addEventListener('click', () => fotoInput.click());

        fotoInput?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!tiposPermitidos.includes(file.type)) {
                mostrarAlerta(i18n.invalidImage);
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                mostrarAlerta(i18n.imageTooLarge);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                fotoBase64 = e.target.result;
                removerFoto = false;
                fotoImg.src = e.target.result;
                fotoBase64Input.value = e.target.result;
            };
            reader.readAsDataURL(file);
        });

        // Mascara de placa
        document.getElementById('placa')?.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value.length > 3) {
                // Placa Mercosul ou antiga
                if (value.length <= 7) {
                    value = value.slice(0, 3) + '-' + value.slice(3);
                }
            }
            e.target.value = value;
        });

        // Mascara de moeda (usa helper Currency do sistema)
        Currency.applyMaskToAll('input-moeda');

        // Atualizar simbolos de moeda dinamicamente
        function atualizarSimbolosMoeda() {
            const symbol = (typeof Currency !== 'undefined' && Currency.config)
                ? Currency.config.symbol
                : 'R$';
            document.querySelectorAll('.currency-symbol').forEach(span => {
                span.textContent = symbol;
            });
        }
        atualizarSimbolosMoeda();

        // Configurar dropdown de acessorios
        configurarDropdownAcessorios();

        // Configurar abas
        configurarAbas();

        document.querySelectorAll('.fatura-filter').forEach(button => {
            button.addEventListener('click', () => {
                const tipo = button.dataset.tipo;
                filtrosFaturasVeiculo[tipo] = !filtrosFaturasVeiculo[tipo];
                const ativo = filtrosFaturasVeiculo[tipo];
                button.classList.toggle('active', ativo);
                button.classList.toggle('btn-blue', ativo);
                button.classList.toggle('btn-secondary', !ativo);
                renderFaturasVeiculo();
            });
        });

        // Configurar mascaras de km do plano
        configurarMascarasKmPlano();

        // Evento ao selecionar plano de manutencao
        document.getElementById('id_plano_manutencao')?.addEventListener('change', async function() {
            const planoId = this.value;
            if (planoId && !editando) {
                // Novo veiculo: calcular automaticamente
                await calcularPlanoManutencao(planoId, true);
            } else if (planoId && editando) {
                // Edicao: perguntar se quer recalcular via modal
                planoIdPendente = planoId;
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.recalculateTitle,
                    message: i18n.recalculateConfirm,
                    confirmText: i18n.recalculateBtn
                }, '*');
            }
        });

        // Listener para resposta do modal de confirmacao
        window.addEventListener('message', async function(event) {
            if (event.data && event.data.action === 'genericConfirmed' && dadosOnlinePendentes) {
                aplicarDadosOnline(dadosOnlinePendentes, true);
                dadosOnlinePendentes = null;
                return;
            }

            if (event.data && event.data.action === 'genericModalClosed' && dadosOnlinePendentes) {
                aplicarDadosOnline(dadosOnlinePendentes, false);
                dadosOnlinePendentes = null;
                return;
            }

            if (event.data && event.data.action === 'genericConfirmed' && planoIdPendente) {
                await calcularPlanoManutencao(planoIdPendente, true);
                planoIdPendente = null;
            }
        });

        // Botao recalcular plano
        document.getElementById('btnRecalcularPlano')?.addEventListener('click', recalcularPlanoManutencao);

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

            // Ajuste checkbox
            dados.vender = document.getElementById('vender').checked ? 'S' : 'N';

            // Converter campos monetarios
            dados.valor_compra = Currency.parse(dados.valor_compra || '0');
            dados.valor_venda = Currency.parse(dados.valor_venda || '0');
            dados.valor_por_fracao = Currency.parse(dados.valor_por_fracao || '0');

            // Converter campo km
            dados.odometro = Km.parse(dados.odometro || '0');

            // Foto
            if (fotoBase64) {
                dados.foto_base64 = fotoBase64;
            }
            if (removerFoto) {
                dados.remover_foto = true;
            }

            // Acessorios
            dados.acessorios_ids = acessoriosSelecionados;

            // Plano de manutencao
            dados.plano_manutencao_array = coletarDadosPlano();

            let url;
            if (editando && registroId) {
                url = `/veiculos/${registroId}/atualizar`;
            } else {
                url = '/veiculos/salvar';
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
                window.parent.postMessage({
                    action: 'openAlert',
                    message: result.message || i18n.saveGenericError
                }, '*');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.saveError
            }, '*');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.save;
        }
    }

    // ===== ENCARGOS DO VEICULO =====

    const recorrenciaOptions = {
        'nenhuma': '{{ t("modules.veiculos.fields.recurrence_none") }}',
        'mensal': '{{ t("modules.veiculos.fields.recurrence_monthly") }}',
        'trimestral': '{{ t("modules.veiculos.fields.recurrence_quarterly") }}',
        'semestral': '{{ t("modules.veiculos.fields.recurrence_semiannual") }}',
        'anual': '{{ t("modules.veiculos.fields.recurrence_annual") }}'
    };

    const datePlaceholder = (typeof DateHelper !== 'undefined')
        ? DateHelper.config.date_format
            .replace('d', 'dd').replace('j', 'd')
            .replace('m', 'mm').replace('n', 'm')
            .replace('Y', 'yyyy').replace('y', 'yy')
        : 'dd/mm/aaaa';

    window.carregarEncargos = async function(idVeiculo) {
        const tbody = document.getElementById('encargos-tbody');
        const emptyMsg = document.getElementById('encargos-empty');
        tbody.innerHTML = '';

        try {
            const result = await API.get(`/api/veiculos/${idVeiculo}/encargos`);
            if (result.success && result.data.length > 0) {
                emptyMsg.style.display = 'none';
                result.data.forEach(enc => renderizarLinhaEncargo(enc));
            } else {
                emptyMsg.style.display = 'block';
            }
        } catch (e) {
            console.error('Erro ao carregar encargos:', e);
        }
    };

    function renderizarLinhaEncargo(enc) {
        const tbody = document.getElementById('encargos-tbody');
        const emptyMsg = document.getElementById('encargos-empty');
        emptyMsg.style.display = 'none';

        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #bcc7d8';
        tr.dataset.encargoId = enc.id;

        tr.innerHTML = `
            <td class="py-2 px-2 font-medium">${escapeHtml(enc.nome)}</td>
            <td class="py-2 px-2 text-slate-500">${escapeHtml(enc.descricao || '-')}</td>
            <td class="py-2 px-2">${enc.valor !== null && enc.valor !== '' ? Currency.format(enc.valor, true) : '-'}</td>
            <td class="py-2 px-2">${enc.vencimento_formatted || (enc.vencimento ? DateHelper.format(enc.vencimento) : '-')}</td>
            <td class="py-2 px-2">${recorrenciaOptions[enc.recorrencia] || enc.recorrencia}</td>
            <td class="py-2 px-2 text-center">${enc.dias_antecedencia}</td>
            <td class="py-2 px-2 text-center">
                <button type="button" class="text-blue-500 hover:text-blue-700 mr-1" onclick="editarEncargo(this)" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="text-red-500 hover:text-red-700" onclick="excluirEncargo(${enc.id}, this)" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    window.adicionarLinhaEncargo = function() {
        const tbody = document.getElementById('encargos-tbody');
        const emptyMsg = document.getElementById('encargos-empty');
        emptyMsg.style.display = 'none';

        const tr = document.createElement('tr');
        tr.className = 'encargo-form-row';
        tr.style.borderBottom = '1px solid #bcc7d8';

        let recorrenciaSelect = '<select class="form-input-group-field text-xs py-1" data-field="recorrencia">';
        for (const [val, label] of Object.entries(recorrenciaOptions)) {
            recorrenciaSelect += `<option value="${val}">${label}</option>`;
        }
        recorrenciaSelect += '</select>';

        tr.innerHTML = `
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1" data-field="nome" maxlength="100" placeholder="Ex: IPVA, Seguro..." required></td>
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1" data-field="descricao" maxlength="500" placeholder="Detalhes..."></td>
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1 input-moeda" data-field="valor" placeholder="0,00"></td>
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1" data-field="vencimento" placeholder="${datePlaceholder}"></td>
            <td class="py-1 px-2">${recorrenciaSelect}</td>
            <td class="py-1 px-2"><input type="number" class="form-input-group-field text-xs py-1 text-center" data-field="dias_antecedencia" value="30" min="0" max="365"></td>
            <td class="py-1 px-2 text-center whitespace-nowrap">
                <button type="button" class="text-green-500 hover:text-green-700 mr-1" onclick="salvarEncargo(this)" title="Salvar">
                    <i class="fas fa-check"></i>
                </button>
                <button type="button" class="text-slate-400 hover:text-slate-600" onclick="cancelarLinhaEncargo(this)" title="Cancelar">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);

        // Inicializar masks
        const valorInput = tr.querySelector('[data-field="valor"]');
        if (valorInput && typeof Currency !== 'undefined') {
            Currency.applyMask(valorInput);
        }
        const vencimentoInput = tr.querySelector('[data-field="vencimento"]');
        if (vencimentoInput && typeof DateHelper !== 'undefined') {
            DateHelper.applyMask(vencimentoInput);
        }

        tr.querySelector('[data-field="nome"]').focus();
    };

    window.salvarEncargo = async function(btn) {
        const tr = btn.closest('tr');
        const encargoId = tr.dataset.encargoId;
        const idVeiculo = registroId;

        if (!idVeiculo) {
            window.parent.postMessage({
                action: 'openAlert',
                message: '{{ t("modules.veiculos.fields.save_vehicle_first") }}'
            }, '*');
            return;
        }

        const nome = tr.querySelector('[data-field="nome"]').value.trim();
        if (!nome) {
            window.parent.postMessage({
                action: 'openAlert',
                message: '{{ t("modules.veiculos.fields.charge_name_required") }}'
            }, '*');
            return;
        }

        const vencimentoRaw = tr.querySelector('[data-field="vencimento"]').value.trim();
        const dados = {
            nome: nome,
            descricao: tr.querySelector('[data-field="descricao"]').value.trim(),
            valor: tr.querySelector('[data-field="valor"]').value,
            vencimento: vencimentoRaw || '',
            recorrencia: tr.querySelector('[data-field="recorrencia"]').value,
            dias_antecedencia: tr.querySelector('[data-field="dias_antecedencia"]').value || 30
        };

        try {
            let result;
            if (encargoId) {
                result = await API.post(`/veiculos/${idVeiculo}/encargos/${encargoId}/atualizar`, dados);
            } else {
                result = await API.post(`/veiculos/${idVeiculo}/encargos/salvar`, dados);
            }

            if (result.success) {
                window.parent.postMessage({
                    action: 'showToast',
                    type: 'success',
                    message: result.message
                }, '*');
                carregarEncargos(idVeiculo);
            } else {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: result.message || 'Erro ao salvar encargo'
                }, '*');
            }
        } catch (e) {
            console.error('Erro ao salvar encargo:', e);
            window.parent.postMessage({
                action: 'openAlert',
                message: 'Erro ao salvar encargo'
            }, '*');
        }
    };

    window.editarEncargo = function(btn) {
        const tr = btn.closest('tr');
        const encargoId = tr.dataset.encargoId;
        const cells = tr.querySelectorAll('td');

        const nome = cells[0].textContent.trim();
        const descricao = cells[1].textContent.trim() === '-' ? '' : cells[1].textContent.trim();
        const valorText = cells[2].textContent.trim();
        const vencimentoText = cells[3].textContent.trim();
        const diasAntecedencia = cells[5].textContent.trim();

        // Descobrir recorrencia atual pelo texto
        let recorrenciaAtual = 'nenhuma';
        const recText = cells[4].textContent.trim();
        for (const [val, label] of Object.entries(recorrenciaOptions)) {
            if (label === recText) { recorrenciaAtual = val; break; }
        }

        let recorrenciaSelect = '<select class="form-input-group-field text-xs py-1" data-field="recorrencia">';
        for (const [val, label] of Object.entries(recorrenciaOptions)) {
            recorrenciaSelect += `<option value="${val}" ${val === recorrenciaAtual ? 'selected' : ''}>${label}</option>`;
        }
        recorrenciaSelect += '</select>';

        tr.dataset.encargoId = encargoId;
        tr.className = 'encargo-form-row';
        tr.style.borderBottom = '1px solid #bcc7d8';

        tr.innerHTML = `
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1" data-field="nome" maxlength="100" value="${escapeHtml(nome)}"></td>
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1" data-field="descricao" maxlength="500" value="${escapeHtml(descricao)}"></td>
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1 input-moeda" data-field="valor" value="${valorText === '-' ? '' : valorText}"></td>
            <td class="py-1 px-2"><input type="text" class="form-input-group-field text-xs py-1" data-field="vencimento" value="${vencimentoText === '-' ? '' : vencimentoText}" placeholder="${datePlaceholder}"></td>
            <td class="py-1 px-2">${recorrenciaSelect}</td>
            <td class="py-1 px-2"><input type="number" class="form-input-group-field text-xs py-1 text-center" data-field="dias_antecedencia" value="${diasAntecedencia}" min="0" max="365"></td>
            <td class="py-1 px-2 text-center whitespace-nowrap">
                <button type="button" class="text-green-500 hover:text-green-700 mr-1" onclick="salvarEncargo(this)" title="Salvar">
                    <i class="fas fa-check"></i>
                </button>
                <button type="button" class="text-slate-400 hover:text-slate-600" onclick="cancelarEdicaoEncargo(this, ${encargoId})" title="Cancelar">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;

        const valorInput = tr.querySelector('[data-field="valor"]');
        if (valorInput && typeof Currency !== 'undefined') {
            Currency.applyMask(valorInput);
        }
        const vencimentoInput = tr.querySelector('[data-field="vencimento"]');
        if (vencimentoInput && typeof DateHelper !== 'undefined') {
            DateHelper.applyMask(vencimentoInput);
            // Converter valor ISO para formato local se necessário
            if (vencimentoInput.value && /^\d{4}-\d{2}-\d{2}$/.test(vencimentoInput.value)) {
                const formatted = DateHelper.format(vencimentoInput.value);
                if (formatted) vencimentoInput.value = formatted;
            }
        }
    };

    window.excluirEncargo = async function(encargoId, btn) {
        const idVeiculo = registroId;
        if (!idVeiculo) return;

        try {
            const result = await API.post(`/veiculos/${idVeiculo}/encargos/${encargoId}/excluir`);
            if (result.success) {
                window.parent.postMessage({
                    action: 'showToast',
                    type: 'success',
                    message: result.message
                }, '*');
                carregarEncargos(idVeiculo);
            } else {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: result.message || 'Erro ao excluir encargo'
                }, '*');
            }
        } catch (e) {
            console.error('Erro ao excluir encargo:', e);
        }
    };

    window.cancelarLinhaEncargo = function(btn) {
        const tr = btn.closest('tr');
        tr.remove();

        const tbody = document.getElementById('encargos-tbody');
        const emptyMsg = document.getElementById('encargos-empty');
        if (tbody.children.length === 0) {
            emptyMsg.style.display = 'block';
        }
    };

    window.cancelarEdicaoEncargo = function(btn, encargoId) {
        const idVeiculo = registroId;
        if (idVeiculo) {
            carregarEncargos(idVeiculo);
        }
    };

    // Inicializar
    init();
})();
</script>
@endsection
