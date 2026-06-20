@extends('layouts.iframe')

@section('title', t('modules.contratos.substitution.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page" id="pageTitle"><?= t('modules.contratos.substitution.title') ?></h2>
            <p class="text-sm text-slate-500 mt-1">
                <?= t('modules.contratos.substitution.contract_label') ?> <strong><?= htmlspecialchars($contrato['codigo'] ?? '') ?></strong>
                · <?= t('modules.contratos.substitution.client_label') ?> <strong><?= htmlspecialchars($contrato['cliente_nome'] ?? '') ?></strong>
                · <?= t('modules.contratos.substitution.period_label') ?> <strong id="contagemLabel"><?= ucfirst(htmlspecialchars($contrato['contagem'] ?? 'dia')) ?></strong>
            </p>
        </div>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formSubstituicao" method="POST">
        @csrf

        <!-- Seletor de veiculo (visivel apenas quando ha multiplos) -->
        <?php if (count($veiculosAtivos) > 1): ?>
        <div class="form-section mb-6">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-6 form-input-group">
                    <label for="seletorVeiculo" class="form-label-group">
                        <?= t('modules.contratos.substitution.select_vehicle_to_replace') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="seletorVeiculo" class="form-input-group-field">
                        <?php foreach ($veiculosAtivos as $v): ?>
                        <option value="<?= $v['id'] ?>"
                            data-json="<?= htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE)) ?>">
                            <?= htmlspecialchars($v['veiculo_placa'] . ' - ' . $v['veiculo_marca'] . ' ' . $v['veiculo_modelo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grid 2 colunas -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <!-- ===== COLUNA ESQUERDA: Veiculo a ser devolvido ===== -->
            <div class="form-section" style="margin-bottom: 0;">
                <h3 class="form-section-title"><i class="fas fa-arrow-down mr-2 text-red-500 text-lg"></i><?= t('modules.contratos.substitution.vehicle_to_return') ?></h3>

                <!-- Linha 1: Plano, Placa/Marca/Modelo, Grupo -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.plan') ?></label>
                        <input type="text" id="atualPlano" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                    <div class="md:col-span-5 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.plate_brand_model') ?></label>
                        <input type="text" id="atualModelo" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.group') ?></label>
                        <input type="text" id="atualGrupo" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                </div>

                <!-- Linha 2: Odometro Inicial, Odometro Atual, Odometro Rodado -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.odometer_initial') ?></label>
                        <input type="text" id="odometroInicial" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.odometer_current') ?> <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" id="odometroAtual" class="form-input-group-field input-km" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-sm">km</span>
                        </div>
                    </div>
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.odometer_driven') ?></label>
                        <input type="text" id="odometroRodado" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                </div>

                <!-- Linha 3: Tanque de Saida, Tanque de Chegada -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-6 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.fuel_out') ?></label>
                        <input type="text" id="tanqueSaida" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <label class="form-label-group"><?= t('modules.contratos.substitution.fuel_arrival') ?></label>
                        <select id="tanqueChegada" class="form-input-group-field">
                            <option value="">-</option>
                            <option value="8"><?= t('modules.contratos.fuel_levels.full') ?></option>
                            <option value="7">7/8</option>
                            <option value="6">3/4</option>
                            <option value="5">5/8</option>
                            <option value="4">1/2</option>
                            <option value="3">3/8</option>
                            <option value="2">1/4</option>
                            <option value="1">1/8</option>
                            <option value="0"><?= t('modules.contratos.fuel_levels.reserve') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Linha 4: Acao para este veiculo -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-12 form-input-group">
                        <label for="acaoVeiculo" class="form-label-group"><?= t('modules.contratos.substitution.vehicle_action') ?></label>
                        <select id="acaoVeiculo" class="form-input-group-field">
                            <option value="disponivel"><?= t('modules.contratos.substitution.action_available') ?></option>
                            <option value="criar_os"><?= t('modules.contratos.substitution.action_create_os') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Linha 5: Motivo da Substituicao -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-12 form-input-group">
                        <label for="motivoSaida" class="form-label-group"><?= t('modules.contratos.substitution.substitution_reason') ?></label>
                        <textarea id="motivoSaida" class="form-input-group-field" rows="2" placeholder="<?= t('modules.contratos.substitution.reason_placeholder') ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- ===== COLUNA DIREITA: Sendo substituido por ===== -->
            <div class="form-section" style="margin-bottom: 0;">
                <h3 class="form-section-title"><i class="fas fa-arrow-up mr-2 text-green-500 text-lg"></i><?= t('modules.contratos.substitution.being_replaced_by') ?></h3>

                <!-- Plano e Grupo -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoPlano" class="form-label-group"><?= t('modules.contratos.substitution.plan') ?> <span class="text-red-500">*</span></label>
                        <select id="novoPlano" class="form-input-group-field chosen-select">
                            <option value=""><?= t('common.labels.select') ?>...</option>
                            <option value="KP"><?= t('modules.contratos.vehicles.plan_km_paid') ?></option>
                            <option value="KMC"><?= t('modules.contratos.vehicles.plan_km_controlled') ?></option>
                            <option value="KL"><?= t('modules.contratos.vehicles.plan_km_free') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoGrupo" class="form-label-group"><?= t('modules.contratos.substitution.group') ?> <span class="text-red-500">*</span></label>
                        <select id="novoGrupo" class="form-input-group-field chosen-select">
                            <option value=""><?= t('common.labels.select') ?>...</option>
                        </select>
                    </div>
                </div>

                <!-- Veiculo -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-12 form-input-group">
                        <label for="novoVeiculo" class="form-label-group"><?= t('modules.contratos.substitution.vehicle') ?> <span class="text-red-500">*</span></label>
                        <select id="novoVeiculo" class="form-input-group-field chosen-select">
                            <option value=""><?= t('modules.contratos.messages.select_group_first') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Seguros (checkboxes) -->
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="form-input-group">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="seguroCarro" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                            <span class="ml-2 text-sm"><?= t('modules.contratos.vehicles.vehicle_insurance') ?></span>
                        </label>
                    </div>
                    <div class="form-input-group">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="seguroTerceiros" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                            <span class="ml-2 text-sm"><?= t('modules.contratos.vehicles.third_party_insurance') ?></span>
                        </label>
                    </div>
                </div>

                <!-- Odometro e Tanque -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoOdometro" class="form-label-group"><?= t('modules.contratos.vehicles.odometer_out') ?></label>
                        <input type="text" id="novoOdometro" class="form-input-group-field input-km" placeholder="0">
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoTanque" class="form-label-group"><?= t('modules.contratos.vehicles.fuel_out') ?></label>
                        <select id="novoTanque" class="form-input-group-field">
                            <option value="">-</option>
                            <option value="8"><?= t('modules.contratos.fuel_levels.full') ?></option>
                            <option value="7">7/8</option>
                            <option value="6">3/4</option>
                            <option value="5">5/8</option>
                            <option value="4">1/2</option>
                            <option value="3">3/8</option>
                            <option value="2">1/4</option>
                            <option value="1">1/8</option>
                            <option value="0"><?= t('modules.contratos.fuel_levels.reserve') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Secao de Valores -->
                <div class="border-t border-slate-300 pt-4 mt-4">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">
                        <?= t('modules.contratos.vehicles.values_per', ['period' => '<span class="novo-contagem">' . htmlspecialchars($contrato['contagem'] ?? 'dia') . '</span>']) ?>
                    </h4>

                    <!-- Campos Plano Km Pago -->
                    <div id="camposKmPago" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-3">
                            <div class="md:col-span-6 form-input-group">
                                <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_paid') ?></label>
                                <div class="relative">
                                    <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                    <input type="text" id="valorDiaria" class="form-input-group-field pl-7 text-sm input-moeda">
                                </div>
                            </div>
                            <div class="md:col-span-6 form-input-group">
                                <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_per_km') ?></label>
                                <div class="relative">
                                    <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                    <input type="text" id="valorKmDiaria" class="form-input-group-field pl-7 text-sm input-moeda">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campos Plano Km Controlado -->
                    <div id="camposKmControlado" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-3">
                            <div class="md:col-span-4 form-input-group">
                                <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_controlled') ?></label>
                                <div class="relative">
                                    <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                    <input type="text" id="valorKmControlado" class="form-input-group-field pl-7 text-sm input-moeda">
                                </div>
                            </div>
                            <div class="md:col-span-4 form-input-group">
                                <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.km_franchise') ?></label>
                                <input type="text" id="kmFranquia" class="form-input-group-field text-sm input-km">
                            </div>
                            <div class="md:col-span-4 form-input-group">
                                <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_excess') ?></label>
                                <div class="relative">
                                    <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                    <input type="text" id="valorKmExcedente" class="form-input-group-field pl-7 text-sm input-moeda">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campos Plano Km Livre -->
                    <div id="camposKmLivre" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-3">
                            <div class="md:col-span-6 form-input-group">
                                <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_free') ?></label>
                                <div class="relative">
                                    <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                    <input type="text" id="valorKmLivre" class="form-input-group-field pl-7 text-sm input-moeda">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Valores dos Seguros -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6 form-input-group">
                            <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_vehicle_insurance') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                <input type="text" id="valorSeguroCarro" class="form-input-group-field pl-7 text-sm input-moeda">
                            </div>
                        </div>
                        <div class="md:col-span-6 form-input-group">
                            <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_third_party_insurance') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                                <input type="text" id="valorSeguroTerceiros" class="form-input-group-field pl-7 text-sm input-moeda">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checkbox Manter Valores -->
                <div class="mt-4 pt-3 border-t border-slate-300">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="manterValores" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                        <span class="ml-2 text-sm font-medium text-slate-700"><?= t('modules.contratos.substitution.keep_values') ?></span>
                    </label>
                    <p class="text-xs text-slate-500 mt-1 ml-6"><?= t('modules.contratos.substitution.keep_values_hint') ?></p>
                </div>
            </div>
        </div>

        <!-- ===== SECAO: CALCULO DA SUBSTITUICAO ===== -->
        <div id="secaoCalculo" class="form-section hidden mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-calculator mr-2 text-amber-500 text-lg"></i><?= t('modules.contratos.substitution.calculation_title') ?>
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Box Quilometragem -->
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">
                        <i class="fas fa-road mr-1 text-slate-500"></i>
                        <?= t('modules.contratos.substitution.calc_km_title') ?>
                    </h4>
                    <div id="calcKmContent"></div>
                </div>

                <!-- Box Combustivel -->
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">
                        <i class="fas fa-gas-pump mr-1 text-slate-500"></i>
                        <span id="calcFuelTitleLabel"><?= t('modules.contratos.substitution.calc_fuel_title') ?></span>
                    </h4>
                    <div id="calcFuelContent"></div>
                </div>

                <!-- Box Resumo -->
                <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                    <h4 class="text-sm font-semibold text-amber-800 mb-3">
                        <i class="fas fa-receipt mr-1"></i>
                        <?= t('modules.contratos.substitution.calc_summary_title') ?>
                    </h4>
                    <div id="calcSummaryContent"></div>
                </div>
            </div>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end space-x-3 mt-6 mb-4">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="button" id="btnConfirmar" class="btn-green py-2 px-6 rounded-md text-sm font-medium">
                <i class="fas fa-check mr-2"></i><?= t('modules.contratos.substitution.confirm_substitution') ?>
            </button>
        </div>

        <!-- Hidden fields -->
        <input type="hidden" id="contratoId" value="<?= (int) $contrato['id'] ?>">
        <input type="hidden" id="filialId" value="<?= (int) ($contrato['id_matriz_filial_retirada'] ?? 0) ?>">
        <input type="hidden" id="contagem" value="<?= htmlspecialchars($contrato['contagem'] ?? 'dia') ?>">
    </form>
</div>
@endsection

@section('scripts')
<?php
$jsText = static fn(string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$jsT = static fn(string $key, array $replace = []): string => $jsText(t($key, $replace));
?>
<script>
(function() {
    const i18n = <?= json_encode([
        'fuelFull' => t('modules.contratos.fuel_levels.full'),
        'fuelReserve' => t('modules.contratos.fuel_levels.reserve'),
        'planKP' => t('modules.contratos.vehicles.plan_km_paid'),
        'planKMC' => t('modules.contratos.vehicles.plan_km_controlled'),
        'planKL' => t('modules.contratos.vehicles.plan_km_free'),
        'select' => t('common.labels.select') . '...',
        'selectGroupFirst' => t('modules.contratos.messages.select_group_first'),
        'selectVehicleHint' => t('modules.contratos.messages.select_vehicle_hint'),
        'noVehicleSelected' => t('modules.contratos.messages.no_vehicle_selected'),
        'selectPlan' => t('modules.contratos.messages.select_plan_required'),
        'selectGroupNew' => t('modules.contratos.messages.select_group_new'),
        'selectNewVehicle' => t('modules.contratos.messages.select_new_vehicle'),
        'informOdometer' => t('modules.contratos.messages.inform_current_odometer'),
        'processing' => t('common.labels.processing'),
        'substitutionSuccess' => t('modules.contratos.messages.substitution_success'),
        'substitutionError' => t('modules.contratos.messages.substitution_error'),
        'confirmSubstitution' => t('modules.contratos.substitution.confirm_substitution'),
        'fuelOut' => t('modules.contratos.substitution.fuel_out'),
        'fuelArrival' => t('modules.contratos.substitution.fuel_arrival'),
        'chargeOut' => t('modules.contratos.substitution.charge_out'),
        'chargeArrival' => t('modules.contratos.substitution.charge_arrival'),
        'calcPlanKL' => t('modules.contratos.substitution.calc_plan_kl'),
        'calcPlanKMC' => t('modules.contratos.substitution.calc_plan_kmc'),
        'calcPlanKP' => t('modules.contratos.substitution.calc_plan_kp'),
        'calcKmDriven' => t('modules.contratos.substitution.calc_km_driven'),
        'calcInformative' => t('modules.contratos.substitution.calc_informative'),
        'calcFranchise' => t('modules.contratos.substitution.calc_franchise'),
        'calcKmExcess' => t('modules.contratos.substitution.calc_km_excess'),
        'calcValuePerKm' => t('modules.contratos.substitution.calc_value_per_km'),
        'calcTotalKm' => t('modules.contratos.substitution.calc_total_km'),
        'calcFuelOutLabel' => t('modules.contratos.substitution.calc_fuel_out'),
        'calcFuelReturn' => t('modules.contratos.substitution.calc_fuel_return'),
        'calcFuelDiff' => t('modules.contratos.substitution.calc_fuel_diff'),
        'calcValuePerFraction' => t('modules.contratos.substitution.calc_value_per_fraction'),
        'calcTotalFuel' => t('modules.contratos.substitution.calc_total_fuel'),
        'calcNoFuelCharge' => t('modules.contratos.substitution.calc_no_fuel_charge'),
        'calcKmLabel' => t('modules.contratos.substitution.calc_km_label'),
        'calcFuelLabel' => t('modules.contratos.substitution.calc_fuel_label'),
        'calcFraction' => t('modules.contratos.substitution.calc_fraction'),
        'calcFractions' => t('modules.contratos.substitution.calc_fractions'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // Dados do servidor
    const veiculosAtivos = <?= json_encode($veiculosAtivos, JSON_UNESCAPED_UNICODE) ?>;
    const contratoId = document.getElementById('contratoId').value;
    const filialId = document.getElementById('filialId').value;
    const contagem = document.getElementById('contagem').value;

    // Multiplicadores (valores do grupo sao por dia)
    const multiplicadores = { 'dia': 1, 'semana': 7, 'mes': 30, 'ano': 365 };
    const multiplicador = multiplicadores[contagem] || 1;

    // Labels de combustivel (dinamico por tipo de veiculo)
    let combustivelLabels = FuelLabels.getLevelLabels('', i18n.fuelFull, i18n.fuelReserve);

    // Labels de plano
    const planoLabels = { 'KP': i18n.planKP, 'KMC': i18n.planKMC, 'KL': i18n.planKL };

    // Estado
    let veiculoAtualData = null;
    let valoresNovoGrupo = null;
    let veiculosDisponiveis = [];
    let gruposDisponiveis = [];
    let valoresGrupoCache = {};
    let odometroInicialRaw = 0;
    let valorPorFracao = 0;

    // Elementos - Coluna esquerda
    const seletorVeiculo = document.getElementById('seletorVeiculo');
    const inputOdometroAtual = document.getElementById('odometroAtual');
    const inputOdometroRodado = document.getElementById('odometroRodado');

    // Elementos - Coluna direita
    const selectPlano = document.getElementById('novoPlano');
    const selectGrupo = document.getElementById('novoGrupo');
    const selectVeiculo = document.getElementById('novoVeiculo');
    const checkSeguroCarro = document.getElementById('seguroCarro');
    const checkSeguroTerceiros = document.getElementById('seguroTerceiros');
    const checkManterValores = document.getElementById('manterValores');
    const inputNovoOdometro = document.getElementById('novoOdometro');
    const selectNovoTanque = document.getElementById('novoTanque');

    // Campos de valores condicionais
    const camposKmPago = document.getElementById('camposKmPago');
    const camposKmControlado = document.getElementById('camposKmControlado');
    const camposKmLivre = document.getElementById('camposKmLivre');

    // Atualizar labels de contagem
    document.querySelectorAll('.novo-contagem').forEach(el => {
        el.textContent = contagem;
    });

    // ==================== INICIALIZACAO ====================

    async function init() {
        // Definir veiculo atual
        if (veiculosAtivos.length === 1) {
            exibirVeiculoAtual(veiculosAtivos[0]);
        } else if (seletorVeiculo) {
            exibirVeiculoAtual(veiculosAtivos[0]);
            seletorVeiculo.addEventListener('change', function() {
                const option = this.selectedOptions[0];
                if (option && option.dataset.json) {
                    exibirVeiculoAtual(JSON.parse(option.dataset.json));
                }
            });
        }

        // Pre-selecionar plano do veiculo atual
        if (veiculoAtualData) {
            selectPlano.value = veiculoAtualData.plano || 'KL';
            atualizarCamposPorPlano(selectPlano.value);
        }

        // Carregar grupos
        await carregarGrupos();

        // Aplicar mascaras de moeda
        Currency.applyMaskToAll('input-moeda');

        // Eventos
        configurarEventos();
    }

    // ==================== COLUNA ESQUERDA: VEICULO A SER DEVOLVIDO ====================

    function exibirVeiculoAtual(veiculo) {
        veiculoAtualData = veiculo;

        // Linha 1: Plano, Placa/Marca/Modelo, Grupo
        document.getElementById('atualPlano').value = planoLabels[veiculo.plano] || veiculo.plano || '-';
        const placa = veiculo.veiculo_placa || '';
        const marcaModelo = ((veiculo.veiculo_marca || '') + ' ' + (veiculo.veiculo_modelo || '')).trim();
        document.getElementById('atualModelo').value = placa && marcaModelo ? placa + ' - ' + marcaModelo : (placa || marcaModelo || '-');
        document.getElementById('atualGrupo').value = veiculo.grupo_nome || '-';

        // Linha 2: Odometros
        odometroInicialRaw = parseInt(veiculo.odometro_saida) || 0;
        document.getElementById('odometroInicial').value = Km.format(odometroInicialRaw) + ' km';
        inputOdometroAtual.value = '';
        inputOdometroRodado.value = '-';

        // Linha 3: Tanque de Saida (readonly = combustivel de entrada no contrato)
        const tipoAtual = veiculo.veiculo_tipo_combustivel || '';
        combustivelLabels = FuelLabels.getLevelLabels(tipoAtual, i18n.fuelFull, i18n.fuelReserve);
        const tanqueSaidaVal = veiculo.combustivel_saida;
        document.getElementById('tanqueSaida').value = combustivelLabels[tanqueSaidaVal] || '-';

        // Atualizar labels do tanque de chegada
        const labelSaida = document.querySelector('label[for="tanqueSaida"]') || document.getElementById('tanqueSaida').closest('.form-input-group').querySelector('.form-label-group');
        const labelChegada = document.querySelector('label[for="tanqueChegada"]') || document.getElementById('tanqueChegada').closest('.form-input-group').querySelector('.form-label-group');
        if (labelSaida) labelSaida.textContent = FuelLabels.isElectric(tipoAtual) ? i18n.chargeOut : i18n.fuelOut;
        if (labelChegada) labelChegada.textContent = FuelLabels.isElectric(tipoAtual) ? i18n.chargeArrival : i18n.fuelArrival;
        FuelLabels.updateSelectOptions(document.getElementById('tanqueChegada'), tipoAtual, i18n.fuelFull, i18n.fuelReserve);

        // Pre-selecionar plano
        selectPlano.value = veiculo.plano || 'KL';
        atualizarCamposPorPlano(selectPlano.value);

        // Capturar valor por fracao do veiculo atual
        valorPorFracao = parseFloat(veiculo.veiculo_valor_por_fracao) || 0;

        // Se checkbox manter valores estiver marcado, atualizar campos
        if (checkManterValores.checked) {
            preencherValoresDoContratoAtual();
        }

        // Recalcular diferencas
        calcularDiferencas();
    }

    function calcularOdometroRodado() {
        const odAtual = Km.parse(inputOdometroAtual.value || '0');
        if (odAtual > 0 && odometroInicialRaw > 0) {
            const rodado = odAtual - odometroInicialRaw;
            inputOdometroRodado.value = Km.format(Math.max(rodado, 0)) + ' km';
        } else {
            inputOdometroRodado.value = '-';
        }
    }

    // ==================== COLUNA DIREITA: SENDO SUBSTITUIDO POR ====================

    function atualizarCamposPorPlano(plano) {
        camposKmPago.classList.add('hidden');
        camposKmControlado.classList.add('hidden');
        camposKmLivre.classList.add('hidden');

        if (plano === 'KP') {
            camposKmPago.classList.remove('hidden');
        } else if (plano === 'KMC') {
            camposKmControlado.classList.remove('hidden');
        } else if (plano === 'KL') {
            camposKmLivre.classList.remove('hidden');
        }
    }

    async function carregarGrupos() {
        if (!filialId) return;
        try {
            const result = await API.get('/api/grupos', { id_filial: filialId });
            if (result.success) {
                gruposDisponiveis = result.data;
                selectGrupo.innerHTML = `<option value="">${i18n.select}</option>`;
                result.data.forEach(g => {
                    const disp = g.qtd_disponiveis !== undefined ? ` (${g.qtd_disponiveis} disp.)` : '';
                    selectGrupo.add(new Option(g.nome + disp, g.id));
                });
                if (selectGrupo.chosenSelect) selectGrupo.chosenSelect.refresh();
            }
        } catch (error) {
            console.error('Erro ao carregar grupos:', error);
        }
    }

    async function carregarVeiculosDoGrupo(grupoId) {
        if (!grupoId || !filialId) return;
        try {
            const result = await API.get('/api/veiculos/por-grupo', {
                id_grupo: grupoId,
                id_filial: filialId
            });
            if (result.success) {
                veiculosDisponiveis = result.data;
                selectVeiculo.innerHTML = `<option value="">${i18n.selectVehicleHint}</option>`;
                result.data.forEach(v => {
                    selectVeiculo.add(new Option(`${v.placa} - ${v.marca} ${v.modelo}`, v.id));
                });
                if (selectVeiculo.chosenSelect) selectVeiculo.chosenSelect.refresh();
            }
        } catch (error) {
            console.error('Erro ao carregar veiculos:', error);
        }
    }

    async function carregarValoresGrupo(grupoId) {
        if (!grupoId) return;

        // Cache por grupo+filial — multi-moeda
        const filialId = document.getElementById('filialId')?.value || null;
        const cacheKey = `${grupoId}:${filialId || 0}`;

        if (valoresGrupoCache[cacheKey]) {
            valoresNovoGrupo = valoresGrupoCache[cacheKey];
            if (!checkManterValores.checked) {
                preencherValoresDoGrupo(valoresNovoGrupo);
            }
            return;
        }

        try {
            // Prioriza endpoint multi-moeda quando ha filial (Fase 2b/2c)
            if (filialId && parseInt(filialId) > 0) {
                const res = await API.get(`/api/grupos/${grupoId}/precos-filial/${filialId}`);
                if (res.success && res.data?.valores) {
                    valoresGrupoCache[cacheKey] = res.data.valores;
                    valoresNovoGrupo = res.data.valores;
                    if (!checkManterValores.checked) {
                        preencherValoresDoGrupo(res.data.valores);
                    }
                    return;
                }
            }
            // Fallback: valores globais do grupo
            const result = await API.get(`/api/grupos/${grupoId}`);
            if (result.success) {
                valoresGrupoCache[cacheKey] = result.data;
                valoresNovoGrupo = result.data;
                if (!checkManterValores.checked) {
                    preencherValoresDoGrupo(result.data);
                }
            }
        } catch (error) {
            console.error('Erro ao carregar valores do grupo:', error);
        }
    }

    function preencherValoresDoGrupo(valores) {
        // Aplicar multiplicador (valores do grupo sao por dia)
        document.getElementById('valorDiaria').value = valores.valor_plano_km_pago ? Currency.format(valores.valor_plano_km_pago * multiplicador) : '';
        document.getElementById('valorKmControlado').value = valores.valor_plano_km_controlado ? Currency.format(valores.valor_plano_km_controlado * multiplicador) : '';
        document.getElementById('valorKmLivre').value = valores.valor_plano_km_livre ? Currency.format(valores.valor_plano_km_livre * multiplicador) : '';
        document.getElementById('valorSeguroCarro').value = valores.valor_seguro_carro ? Currency.format(valores.valor_seguro_carro * multiplicador) : '';
        document.getElementById('valorSeguroTerceiros').value = valores.valor_seguro_terceiros ? Currency.format(valores.valor_seguro_terceiros * multiplicador) : '';
        document.getElementById('kmFranquia').value = valores.km_franquia ? Km.format(valores.km_franquia * multiplicador) : '';
        // Valores por km - NAO multiplicar
        document.getElementById('valorKmDiaria').value = valores.valor_km_excedente ? Currency.format(valores.valor_km_excedente) : '';
        document.getElementById('valorKmExcedente').value = valores.valor_km_excedente ? Currency.format(valores.valor_km_excedente) : '';

        // Marcar/desmarcar checkboxes de seguro conforme grupo
        checkSeguroCarro.checked = parseFloat(valores.valor_seguro_carro || 0) > 0;
        checkSeguroTerceiros.checked = parseFloat(valores.valor_seguro_terceiros || 0) > 0;
    }

    function preencherValoresDoContratoAtual() {
        if (!veiculoAtualData) return;

        const v = veiculoAtualData;
        document.getElementById('valorDiaria').value = v.valor_plano_km_pago ? Currency.format(v.valor_plano_km_pago) : '';
        document.getElementById('valorKmControlado').value = v.valor_plano_km_controlado ? Currency.format(v.valor_plano_km_controlado) : '';
        document.getElementById('valorKmLivre').value = v.valor_plano_km_livre ? Currency.format(v.valor_plano_km_livre) : '';
        document.getElementById('valorSeguroCarro').value = v.valor_seguro_carro ? Currency.format(v.valor_seguro_carro) : '';
        document.getElementById('valorSeguroTerceiros').value = v.valor_seguro_terceiros ? Currency.format(v.valor_seguro_terceiros) : '';
        document.getElementById('kmFranquia').value = v.km_franquia ? Km.format(v.km_franquia) : '';
        document.getElementById('valorKmDiaria').value = v.valor_km_excedente ? Currency.format(v.valor_km_excedente) : '';
        document.getElementById('valorKmExcedente').value = v.valor_km_excedente ? Currency.format(v.valor_km_excedente) : '';

        // Checkboxes de seguro
        checkSeguroCarro.checked = v.seguro_carro == 1 || v.seguro_carro === true;
        checkSeguroTerceiros.checked = v.seguro_terceiros == 1 || v.seguro_terceiros === true;
    }

    function carregarDadosVeiculo(veiculoId) {
        const veiculoData = veiculosDisponiveis.find(v => v.id == veiculoId);
        if (veiculoData) {
            inputNovoOdometro.value = veiculoData.odometro || '';
            // Atualizar labels para o novo veiculo
            const tipoNovo = veiculoData.tipo_combustivel || '';
            const labelNovoTanque = document.querySelector('label[for="novoTanque"]');
            if (labelNovoTanque) labelNovoTanque.textContent = FuelLabels.isElectric(tipoNovo) ? i18n.chargeOut : i18n.fuelOut;
            FuelLabels.updateSelectOptions(selectNovoTanque, tipoNovo, i18n.fuelFull, i18n.fuelReserve);
            selectNovoTanque.value = veiculoData.tanque_fracao || '';
        }
    }

    function toggleCamposValores(disabled) {
        const campos = [
            'valorDiaria', 'valorKmDiaria', 'valorKmControlado',
            'kmFranquia', 'valorKmExcedente', 'valorKmLivre',
            'valorSeguroCarro', 'valorSeguroTerceiros'
        ];
        campos.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.readOnly = disabled;
                if (disabled) {
                    el.classList.add('bg-slate-50');
                } else {
                    el.classList.remove('bg-slate-50');
                }
            }
        });
    }

    // ==================== EVENTOS ====================

    function configurarEventos() {
        // Odometro Atual mudou -> calcular rodado + diferencas
        inputOdometroAtual.addEventListener('input', function() { calcularOdometroRodado(); calcularDiferencas(); });
        inputOdometroAtual.addEventListener('change', function() { calcularOdometroRodado(); calcularDiferencas(); });

        // Tanque de chegada mudou -> recalcular diferencas
        document.getElementById('tanqueChegada').addEventListener('change', calcularDiferencas);

        // Plano mudou -> mostrar/ocultar campos
        selectPlano.addEventListener('change', function() {
            atualizarCamposPorPlano(this.value);
        });

        // Grupo mudou -> carregar veiculos + valores
        selectGrupo.addEventListener('change', async function() {
            if (this.value) {
                await Promise.all([
                    carregarVeiculosDoGrupo(this.value),
                    carregarValoresGrupo(this.value)
                ]);
            } else {
                selectVeiculo.innerHTML = `<option value="">${i18n.selectGroupFirst}</option>`;
                if (selectVeiculo.chosenSelect) selectVeiculo.chosenSelect.refresh();
                valoresNovoGrupo = null;
            }
        });

        // Veiculo selecionado -> preencher odometro e tanque
        selectVeiculo.addEventListener('change', function() {
            if (this.value) {
                carregarDadosVeiculo(this.value);
            }
        });

        // Checkbox manter valores
        checkManterValores.addEventListener('change', function() {
            if (this.checked) {
                preencherValoresDoContratoAtual();
                toggleCamposValores(true);
            } else {
                if (valoresNovoGrupo) {
                    preencherValoresDoGrupo(valoresNovoGrupo);
                }
                toggleCamposValores(false);
            }
        });

        // Botoes
        document.getElementById('btnVoltar').addEventListener('click', voltar);
        document.getElementById('btnCancelar').addEventListener('click', voltar);
        document.getElementById('btnConfirmar').addEventListener('click', confirmarSubstituicao);
    }

    function voltar() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: '/pages/contratos' }, '*');
        } else {
            window.location.href = '/pages/contratos';
        }
    }

    // ==================== CALCULO DE DIFERENCAS ====================

    function calcularTotalKm() {
        if (!veiculoAtualData) return 0;
        const odometroAtual = Km.parse(inputOdometroAtual.value || '0');
        const kmRodados = Math.max(0, odometroAtual - odometroInicialRaw);
        const plano = veiculoAtualData.plano;
        const kmFranquiaVal = parseInt(veiculoAtualData.km_franquia) || 0;
        const valorKmExcVal = parseFloat(veiculoAtualData.valor_km_excedente) || 0;

        if (plano === 'KL') return 0;
        if (plano === 'KMC') return Math.max(0, kmRodados - kmFranquiaVal) * valorKmExcVal;
        if (plano === 'KP') return kmRodados * valorKmExcVal;
        return 0;
    }

    function calcularTotalCombustivel() {
        if (!veiculoAtualData) return 0;
        const tanqueChegada = parseInt(document.getElementById('tanqueChegada').value);
        if (isNaN(tanqueChegada)) return 0;
        const combustivelEntrada = parseInt(veiculoAtualData.combustivel_saida) || 0;
        const diferencaFracoes = Math.max(0, combustivelEntrada - tanqueChegada);
        return diferencaFracoes * valorPorFracao;
    }

    function calcularDiferencas() {
        if (!veiculoAtualData) return;

        const odometroAtual = Km.parse(inputOdometroAtual.value || '0');
        const tanqueChegadaEl = document.getElementById('tanqueChegada');
        const tanqueChegada = parseInt(tanqueChegadaEl.value);

        // Precisa dos dois campos preenchidos para calcular
        if (odometroAtual <= 0 || isNaN(tanqueChegada)) {
            document.getElementById('secaoCalculo').classList.add('hidden');
            return;
        }

        document.getElementById('secaoCalculo').classList.remove('hidden');

        const kmRodados = Math.max(0, odometroAtual - odometroInicialRaw);
        const planoAtual = veiculoAtualData.plano;
        const kmFranquiaVal = parseInt(veiculoAtualData.km_franquia) || 0;
        const valorKmExcVal = parseFloat(veiculoAtualData.valor_km_excedente) || 0;
        const combustivelEntrada = parseInt(veiculoAtualData.combustivel_saida) || 0;

        // --- CALCULO KM ---
        let totalKm = 0;
        let htmlKm = '';

        if (planoAtual === 'KL') {
            htmlKm = `
                <p class="text-sm text-slate-600">${i18n.calcPlanKL}</p>
                <p class="text-sm text-slate-500 mt-2">${i18n.calcKmDriven}: <strong>${Km.format(kmRodados)} km</strong> <span class="text-xs text-slate-400">(${i18n.calcInformative})</span></p>
            `;
            totalKm = 0;
        } else if (planoAtual === 'KMC') {
            const kmExcedente = Math.max(0, kmRodados - kmFranquiaVal);
            totalKm = kmExcedente * valorKmExcVal;
            htmlKm = `
                <p class="text-sm text-slate-600 mb-2">${i18n.calcPlanKMC}</p>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFranchise}:</span><strong>${Km.format(kmFranquiaVal)} km</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcKmDriven}:</span><strong>${Km.format(kmRodados)} km</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcKmExcess}:</span><strong>${Km.format(kmExcedente)} km</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcValuePerKm}:</span><strong>${Currency.format(valorKmExcVal, true)}</strong></div>
                    <div class="flex justify-between border-t border-slate-300 pt-1 mt-1 font-semibold"><span>${i18n.calcTotalKm}:</span><strong>${Currency.format(totalKm, true)}</strong></div>
                </div>
            `;
        } else if (planoAtual === 'KP') {
            totalKm = kmRodados * valorKmExcVal;
            htmlKm = `
                <p class="text-sm text-slate-600 mb-2">${i18n.calcPlanKP}</p>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcKmDriven}:</span><strong>${Km.format(kmRodados)} km</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcValuePerKm}:</span><strong>${Currency.format(valorKmExcVal, true)}</strong></div>
                    <div class="flex justify-between border-t border-slate-300 pt-1 mt-1 font-semibold"><span>${i18n.calcTotalKm}:</span><strong>${Currency.format(totalKm, true)}</strong></div>
                </div>
            `;
        }

        // --- CALCULO COMBUSTIVEL ---
        let totalCombustivel = 0;
        const diferencaFracoes = Math.max(0, combustivelEntrada - tanqueChegada);
        let htmlFuel = '';

        if (diferencaFracoes > 0 && valorPorFracao > 0) {
            totalCombustivel = diferencaFracoes * valorPorFracao;
            htmlFuel = `
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFuelOutLabel}:</span><strong>${combustivelLabels[combustivelEntrada] || combustivelEntrada + '/8'}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFuelReturn}:</span><strong>${combustivelLabels[tanqueChegada] || tanqueChegada + '/8'}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFuelDiff}:</span><strong>${diferencaFracoes} ${diferencaFracoes === 1 ? i18n.calcFraction : i18n.calcFractions}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcValuePerFraction}:</span><strong>${Currency.format(valorPorFracao, true)}</strong></div>
                    <div class="flex justify-between border-t border-slate-300 pt-1 mt-1 font-semibold"><span>${i18n.calcTotalFuel}:</span><strong>${Currency.format(totalCombustivel, true)}</strong></div>
                </div>
            `;
        } else {
            htmlFuel = `<p class="text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i>${i18n.calcNoFuelCharge}</p>`;
        }

        // --- RESUMO ---
        const totalGeral = totalKm + totalCombustivel;
        const corTotal = totalGeral > 0 ? 'text-red-600' : 'text-green-600';
        const htmlSummary = `
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-600">${i18n.calcKmLabel}:</span>
                    <strong>${Currency.format(totalKm, true)}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">${i18n.calcFuelLabel}:</span>
                    <strong>${Currency.format(totalCombustivel, true)}</strong>
                </div>
                <div class="flex justify-between border-t border-amber-300 pt-2 mt-2 text-base font-bold ${corTotal}">
                    <span>TOTAL:</span>
                    <span>${Currency.format(totalGeral, true)}</span>
                </div>
            </div>
        `;

        document.getElementById('calcKmContent').innerHTML = htmlKm;
        document.getElementById('calcFuelContent').innerHTML = htmlFuel;
        document.getElementById('calcSummaryContent').innerHTML = htmlSummary;
    }

    // ==================== SUBMISSAO ====================

    async function confirmarSubstituicao() {
        // Validacoes
        if (!veiculoAtualData) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.noVehicleSelected }, '*');
            return;
        }

        const veiculoNovoId = selectVeiculo.value;
        const grupoNovoId = selectGrupo.value;
        const planoNovo = selectPlano.value;
        const odometroAtualVal = inputOdometroAtual.value;

        if (!planoNovo) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectPlan }, '*');
            return;
        }
        if (!grupoNovoId) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectGroupNew }, '*');
            return;
        }
        if (!veiculoNovoId) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectNewVehicle }, '*');
            return;
        }
        if (!odometroAtualVal) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.informOdometer }, '*');
            return;
        }

        const manterValores = checkManterValores.checked;

        const btnConfirmar = document.getElementById('btnConfirmar');
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.processing}`;

        try {
            const valorKmPago = Currency.parse(document.getElementById('valorDiaria').value || '0');
            const valorKmControlado = Currency.parse(document.getElementById('valorKmControlado').value || '0');
            const valorKmLivre = Currency.parse(document.getElementById('valorKmLivre').value || '0');

            const payload = {
                id_contrato_veiculo_antigo: veiculoAtualData.id,
                // Dados devolucao (veiculo entra na empresa)
                odometro_entrada: Km.parse(odometroAtualVal),
                combustivel_entrada: document.getElementById('tanqueChegada').value || null,
                motivo_saida: document.getElementById('motivoSaida').value || null,
                acao_veiculo: document.getElementById('acaoVeiculo').value || 'disponivel',
                // Dados novo veiculo
                id_veiculo_novo: parseInt(veiculoNovoId),
                id_grupo_novo: parseInt(grupoNovoId),
                plano_novo: planoNovo,
                odometro_saida_novo: Km.parse(inputNovoOdometro.value || '0'),
                combustivel_saida_novo: selectNovoTanque.value || null,
                // Seguros
                seguro_carro: checkSeguroCarro.checked ? 1 : 0,
                seguro_terceiros: checkSeguroTerceiros.checked ? 1 : 0,
                // Valores
                valor_plano_km_pago: planoNovo === 'KP' ? valorKmPago : 0,
                valor_plano_km_controlado: planoNovo === 'KMC' ? valorKmControlado : 0,
                valor_plano_km_livre: planoNovo === 'KL' ? valorKmLivre : 0,
                valor_km_excedente: Currency.parse(document.getElementById('valorKmExcedente').value || document.getElementById('valorKmDiaria').value || '0'),
                km_franquia: Km.parse(document.getElementById('kmFranquia').value || '0'),
                valor_seguro_carro: Currency.parse(document.getElementById('valorSeguroCarro').value || '0'),
                valor_seguro_terceiros: Currency.parse(document.getElementById('valorSeguroTerceiros').value || '0'),
                // Flag
                manter_valores: manterValores
            };

            const result = await API.post(`/contratos/${contratoId}/substituir`, payload);

            if (result.success) {
                window.parent.postMessage({
                    action: 'openAlert',
                    type: 'success',
                    message: i18n.substitutionSuccess
                }, '*');
                setTimeout(() => voltar(), 500);
            } else {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: result.message || i18n.substitutionError
                }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.substitutionError
            }, '*');
        } finally {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = `<i class="fas fa-check mr-2"></i>${i18n.confirmSubstitution}`;
        }
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(init, 0);
    });
})();
</script>
@endsection
