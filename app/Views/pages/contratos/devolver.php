@extends('layouts.iframe')

@section('title', t('modules.contratos.return_page.title'))

@section('content')
<?php
    $singleMode = count($veiculosAtivos) === 1;
    $planoLabels = [
        'KP' => t('modules.contratos.vehicles.plan_km_paid'),
        'KMC' => t('modules.contratos.vehicles.plan_km_controlled'),
        'KL' => t('modules.contratos.vehicles.plan_km_free'),
    ];
?>
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page"><?= t('modules.contratos.return_page.title') ?></h2>
            <p class="text-sm text-slate-500 mt-1">
                <?= t('modules.contratos.return_page.contract_label') ?> <strong><?= htmlspecialchars($contrato['codigo'] ?? '') ?></strong>
                · <?= t('modules.contratos.return_page.client_label') ?> <strong><?= htmlspecialchars($contrato['cliente_nome'] ?? '') ?></strong>
                · <?= t('modules.contratos.return_page.period_label') ?> <strong><?= ucfirst(htmlspecialchars($contrato['contagem'] ?? 'dia')) ?></strong>
            </p>
        </div>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formDevolucao" method="POST">
        @csrf

        <?php if (!$singleMode): ?>
        <!-- Barra superior: contagem + selecionar todos -->
        <div class="flex justify-between items-center mb-4">
            <span class="text-sm text-slate-600">
                <?= str_replace(':count', count($veiculosAtivos), t('modules.contratos.return_page.vehicles_count')) ?>
            </span>
            <button type="button" id="btnSelecionarTodos" class="btn-secondary py-1 px-3 text-sm rounded-md">
                <i class="fas fa-check-double mr-1"></i><?= t('modules.contratos.return_page.select_all') ?>
            </button>
        </div>
        <?php endif; ?>

        <!-- Cards de veiculos -->
        <div id="veiculosContainer" class="space-y-4 mb-6">
            <?php foreach ($veiculosAtivos as $index => $v): ?>
            <?php
                $vJson = htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                $placa = htmlspecialchars($v['veiculo_placa'] ?? '');
                $marcaModelo = trim(($v['veiculo_marca'] ?? '') . ' ' . ($v['veiculo_modelo'] ?? ''));
                $grupoNome = htmlspecialchars($v['grupo_nome'] ?? '-');
                $planoLabel = $planoLabels[$v['plano'] ?? ''] ?? ($v['plano'] ?? '-');
                $odometroSaida = (int) ($v['odometro_saida'] ?? 0);
                $combustivelSaida = (int) ($v['combustivel_saida'] ?? 0);
            ?>
            <div class="veiculo-card form-section" data-index="<?= $index ?>" data-json="<?= $vJson ?>" style="margin-bottom: 0;">
                <!-- Header do card -->
                <div class="flex items-center justify-between cursor-pointer card-header" data-index="<?= $index ?>">
                    <div class="flex items-center flex-1 min-w-0">
                        <?php if (!$singleMode): ?>
                        <input type="checkbox" class="veiculo-checkbox form-checkbox h-5 w-5 text-green-600 rounded flex-shrink-0 cursor-pointer"
                               data-index="<?= $index ?>">
                        <?php endif; ?>
                        <div class="<?= $singleMode ? '' : 'ml-3' ?> min-w-0">
                            <span class="font-semibold text-slate-800">
                                <?= $placa ?> · <?= htmlspecialchars($marcaModelo) ?>
                            </span>
                            <span class="text-sm text-slate-500 ml-2">
                                (<?= $grupoNome ?> · <?= $planoLabel ?>)
                            </span>
                        </div>
                    </div>
                    <?php if (!$singleMode): ?>
                    <i class="fas fa-chevron-down text-slate-400 card-chevron transition-transform flex-shrink-0 ml-2" data-index="<?= $index ?>"></i>
                    <?php endif; ?>
                </div>

                <!-- Corpo do card (colapsavel) -->
                <div class="card-body mt-4 <?= $singleMode ? '' : 'hidden' ?>" id="cardBody_<?= $index ?>">

                    <!-- Dados readonly compactos -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-x-6 gap-y-1 text-sm">
                        <div>
                            <span class="text-slate-500"><?= t('modules.contratos.return_page.odometer_initial') ?></span>
                            <p class="font-medium text-slate-800"><?= number_format($odometroSaida, 0, '', '.') ?> km</p>
                        </div>
                        <div>
                            <span class="text-slate-500"><?= t('modules.contratos.return_page.km_franchise') ?></span>
                            <p class="font-medium text-slate-800"><?= ($v['plano'] === 'KMC') ? number_format((int)($v['km_franquia'] ?? 0), 0, '', '.') . ' km' : '-' ?></p>
                        </div>
                        <div>
                            <span class="text-slate-500"><?= t('modules.contratos.return_page.value_per_km') ?></span>
                            <p class="font-medium text-slate-800"><?= ($v['plano'] !== 'KL' && !empty($v['valor_km_excedente'])) ? 'R$ ' . number_format((float)($v['valor_km_excedente'] ?? 0), 2, ',', '.') : '-' ?></p>
                        </div>
                        <div>
                            <span class="text-slate-500 fuel-out-label" data-index="<?= $index ?>"><?= t('modules.contratos.return_page.fuel_out') ?></span>
                            <p class="font-medium text-slate-800 tanque-saida-display" data-index="<?= $index ?>">-</p>
                        </div>
                        <div>
                            <span class="text-slate-500"><?= t('modules.contratos.return_page.value_per_fraction') ?></span>
                            <p class="font-medium text-slate-800"><?= !empty($v['veiculo_valor_por_fracao']) ? 'R$ ' . number_format((float)($v['veiculo_valor_por_fracao'] ?? 0), 2, ',', '.') : '-' ?></p>
                        </div>
                    </div>

                    <!-- Separador -->
                    <div class="border-t border-slate-200 my-4"></div>

                    <!-- Campos editaveis: Data/Hora, Odometro Atual, Km Rodados, Tanque Chegada -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.return_page.return_datetime') ?> <span class="text-red-500">*</span></label>
                            <input type="datetime-local" class="form-input-group-field data-devolucao" data-index="<?= $index ?>">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.return_page.odometer_current') ?> <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" class="form-input-group-field input-km odometro-atual" data-index="<?= $index ?>" placeholder="0">
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-sm">km</span>
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.return_page.odometer_driven') ?></label>
                            <input type="text" class="form-input-group-field bg-slate-50 odometro-rodado" data-index="<?= $index ?>" readonly value="-">
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group fuel-arrival-label" data-index="<?= $index ?>"><?= t('modules.contratos.return_page.fuel_arrival') ?></label>
                            <select class="form-input-group-field tanque-chegada" data-index="<?= $index ?>">
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

                    <!-- Acao do veiculo + Observacao -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.return_page.vehicle_action') ?></label>
                            <select class="form-input-group-field acao-veiculo" data-index="<?= $index ?>">
                                <option value="disponivel"><?= t('modules.contratos.return_page.action_available') ?></option>
                                <option value="criar_os"><?= t('modules.contratos.return_page.action_create_os') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-8 form-input-group">
                            <label class="form-label-group"><?= t('modules.contratos.return_page.observation') ?></label>
                            <input type="text" class="form-input-group-field observacao-veiculo" data-index="<?= $index ?>" placeholder="<?= t('modules.contratos.return_page.obs_placeholder') ?>">
                        </div>
                    </div>

                    <!-- Calculos inline (aparece quando odometro + combustivel preenchidos) -->
                    <div class="calculo-section hidden mt-4" id="calculo_<?= $index ?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Box KM -->
                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                                <h4 class="text-sm font-semibold text-slate-700 mb-3">
                                    <i class="fas fa-road mr-1 text-slate-500"></i>
                                    <?= t('modules.contratos.return_page.calc_km_title') ?>
                                </h4>
                                <div class="calc-km-content" data-index="<?= $index ?>"></div>
                            </div>
                            <!-- Box Combustivel -->
                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                                <h4 class="text-sm font-semibold text-slate-700 mb-3">
                                    <i class="fas fa-gas-pump calc-fuel-icon mr-1 text-slate-500"></i>
                                    <span class="calc-fuel-title-label" data-index="<?= $index ?>"><?= t('modules.contratos.return_page.calc_fuel_title') ?></span>
                                </h4>
                                <div class="calc-fuel-content" data-index="<?= $index ?>"></div>
                            </div>
                            <!-- Box Subtotal -->
                            <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                                <h4 class="text-sm font-semibold text-amber-800 mb-3">
                                    <i class="fas fa-receipt mr-1"></i>
                                    <?= t('modules.contratos.return_page.calc_subtotal_title') ?>
                                </h4>
                                <div class="calc-subtotal-content" data-index="<?= $index ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Resumo geral -->
        <div id="resumoGeral" class="form-section bg-green-50 border border-green-200 <?= $singleMode ? '' : 'hidden' ?> mb-6" style="margin-bottom: 0;">
            <h3 class="form-section-title text-green-800">
                <i class="fas fa-receipt mr-2 text-green-600"></i><?= t('modules.contratos.return_page.summary_title') ?>
            </h3>
            <div id="resumoContent"></div>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end space-x-3 mt-6 mb-4">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="button" id="btnConfirmar" class="btn-green py-2 px-6 rounded-md text-sm font-medium">
                <i class="fas fa-check mr-2"></i><span id="btnConfirmarTexto"><?= t('modules.contratos.return_page.confirm_return') ?></span>
            </button>
        </div>

        <!-- Hidden fields -->
        <input type="hidden" id="contratoId" value="<?= (int) $contrato['id'] ?>">
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
        'fuelOut' => t('modules.contratos.return_page.fuel_out'),
        'fuelArrival' => t('modules.contratos.return_page.fuel_arrival'),
        'chargeOut' => t('modules.contratos.return_page.charge_out'),
        'chargeArrival' => t('modules.contratos.return_page.charge_arrival'),
        'selectAll' => t('modules.contratos.return_page.select_all'),
        'deselectAll' => t('modules.contratos.return_page.deselect_all'),
        'confirmReturn' => t('modules.contratos.return_page.confirm_return'),
        'confirmReturns' => t('modules.contratos.return_page.confirm_returns'),
        'selectAtLeastOne' => t('modules.contratos.return_page.select_at_least_one'),
        'informOdometer' => t('modules.contratos.return_page.inform_odometer'),
        'informReturnDate' => t('modules.contratos.return_page.inform_return_datetime'),
        'processing' => t('modules.contratos.return_page.processing'),
        'returnSuccess' => t('modules.contratos.return_page.return_success'),
        'returnError' => t('modules.contratos.return_page.return_error'),
        'calcPlanKL' => t('modules.contratos.return_page.calc_plan_kl'),
        'calcPlanKMC' => t('modules.contratos.return_page.calc_plan_kmc'),
        'calcPlanKP' => t('modules.contratos.return_page.calc_plan_kp'),
        'calcKmDriven' => t('modules.contratos.return_page.calc_km_driven'),
        'calcInformative' => t('modules.contratos.return_page.calc_informative'),
        'calcFranchise' => t('modules.contratos.return_page.calc_franchise'),
        'calcKmExcess' => t('modules.contratos.return_page.calc_km_excess'),
        'calcValuePerKm' => t('modules.contratos.return_page.calc_value_per_km'),
        'calcTotalKm' => t('modules.contratos.return_page.calc_total_km'),
        'calcFuelOutLabel' => t('modules.contratos.return_page.calc_fuel_out'),
        'calcFuelReturn' => t('modules.contratos.return_page.calc_fuel_return'),
        'calcFuelDiff' => t('modules.contratos.return_page.calc_fuel_diff'),
        'calcValuePerFraction' => t('modules.contratos.return_page.calc_value_per_fraction'),
        'calcTotalFuel' => t('modules.contratos.return_page.calc_total_fuel'),
        'calcNoFuelCharge' => t('modules.contratos.return_page.calc_no_fuel_charge'),
        'calcKmLabel' => t('modules.contratos.return_page.calc_km_title'),
        'calcFuelLabel' => t('modules.contratos.return_page.calc_fuel_title'),
        'calcChargeLabel' => t('modules.contratos.return_page.calc_charge_title'),
        'calcFraction' => t('modules.contratos.return_page.calc_fraction'),
        'calcFractions' => t('modules.contratos.return_page.calc_fractions'),
        'summaryTotal' => t('modules.contratos.return_page.summary_total'),
        'summaryCharge' => t('modules.contratos.return_page.summary_charge_client'),
        'summaryNoCharge' => t('modules.contratos.return_page.summary_no_charge'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // Dados do servidor
    const veiculosAtivos = <?= json_encode($veiculosAtivos, JSON_UNESCAPED_UNICODE) ?>;
    const contratoId = document.getElementById('contratoId').value;
    const singleMode = veiculosAtivos.length === 1;

    // Estado por veiculo
    const veiculoState = {};
    veiculosAtivos.forEach((v, i) => {
        veiculoState[i] = {
            selecionado: singleMode,
            data: v,
            odometroInicialRaw: parseInt(v.odometro_saida) || 0,
            valorPorFracao: parseFloat(v.veiculo_valor_por_fracao) || 0,
            combustivelLabels: FuelLabels.getLevelLabels(v.veiculo_tipo_combustivel || '', i18n.fuelFull, i18n.fuelReserve),
            totalKm: 0,
            totalCombustivel: 0,
        };
    });

    // ==================== INICIALIZACAO ====================

    function init() {
        const agora = formatarDatetimeLocal(new Date());

        // Atualizar FuelLabels e labels dinamicos para cada veiculo
        veiculosAtivos.forEach((v, i) => {
            const tipo = v.veiculo_tipo_combustivel || '';
            const card = document.querySelector(`.veiculo-card[data-index="${i}"]`);

            const dataDevolucao = card.querySelector('.data-devolucao');
            if (dataDevolucao && !dataDevolucao.value) {
                dataDevolucao.value = agora;
            }

            // Tanque de saida (display texto)
            const tanqueSaidaDisplay = card.querySelector('.tanque-saida-display');
            const labels = veiculoState[i].combustivelLabels;
            tanqueSaidaDisplay.textContent = labels[v.combustivel_saida] || '-';

            // Labels dinamicos (eletrico vs combustao)
            const labelOut = card.querySelector('.fuel-out-label');
            const labelArrival = card.querySelector('.fuel-arrival-label');
            if (labelOut) labelOut.textContent = FuelLabels.isElectric(tipo) ? i18n.chargeOut : i18n.fuelOut;
            if (labelArrival) labelArrival.textContent = FuelLabels.isElectric(tipo) ? i18n.chargeArrival : i18n.fuelArrival;

            // Titulo e icone do box calculo combustivel
            const calcFuelTitle = card.querySelector('.calc-fuel-title-label');
            if (calcFuelTitle) calcFuelTitle.textContent = FuelLabels.isElectric(tipo) ? i18n.calcChargeLabel : i18n.calcFuelLabel;
            const calcFuelIcon = card.querySelector('.calc-fuel-icon');
            if (calcFuelIcon && FuelLabels.isElectric(tipo)) {
                calcFuelIcon.classList.remove('fa-gas-pump');
                calcFuelIcon.classList.add('fa-bolt');
            }

            // Opcoes do select de tanque
            FuelLabels.updateSelectOptions(card.querySelector('.tanque-chegada'), tipo, i18n.fuelFull, i18n.fuelReserve);
        });

        configurarEventos();

        if (singleMode) {
            atualizarResumoGeral();
            atualizarBotaoConfirmar();
        }
    }

    // ==================== EXPAND/COLLAPSE ====================

    function toggleCard(index, forceOpen) {
        const body = document.getElementById(`cardBody_${index}`);
        const chevron = document.querySelector(`.card-chevron[data-index="${index}"]`);
        if (!body) return;

        const isHidden = body.classList.contains('hidden');
        const shouldOpen = forceOpen !== undefined ? forceOpen : isHidden;

        if (shouldOpen) {
            body.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180');
        } else {
            body.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
        }
    }

    // ==================== CALCULOS POR VEICULO ====================

    function calcularDiferencasVeiculo(index) {
        const state = veiculoState[index];
        const v = state.data;
        const card = document.querySelector(`.veiculo-card[data-index="${index}"]`);

        const odometroAtualInput = card.querySelector('.odometro-atual');
        const tanqueChegadaSelect = card.querySelector('.tanque-chegada');
        const rodadoField = card.querySelector('.odometro-rodado');

        const odometroAtual = Km.parse(odometroAtualInput.value || '0');
        const tanqueChegada = parseInt(tanqueChegadaSelect.value);

        // Calcular odometro rodado
        const kmRodados = Math.max(0, odometroAtual - state.odometroInicialRaw);
        rodadoField.value = odometroAtual > 0 ? Km.format(kmRodados) + ' km' : '-';

        // Precisa dos dois campos para calcular
        if (odometroAtual <= 0 || isNaN(tanqueChegada)) {
            card.querySelector('.calculo-section').classList.add('hidden');
            state.totalKm = 0;
            state.totalCombustivel = 0;
            atualizarResumoGeral();
            return;
        }

        card.querySelector('.calculo-section').classList.remove('hidden');

        const plano = v.plano;
        const kmFranquiaVal = parseInt(v.km_franquia) || 0;
        const valorKmExcVal = parseFloat(v.valor_km_excedente) || 0;
        const combustivelSaida = parseInt(v.combustivel_saida) || 0;

        // --- CALCULO KM ---
        let totalKm = 0;
        let htmlKm = '';

        if (plano === 'KL') {
            htmlKm = `
                <p class="text-sm text-slate-600">${i18n.calcPlanKL}</p>
                <p class="text-sm text-slate-500 mt-2">${i18n.calcKmDriven}: <strong>${Km.format(kmRodados)} km</strong> <span class="text-xs text-slate-400">(${i18n.calcInformative})</span></p>
            `;
            totalKm = 0;
        } else if (plano === 'KMC') {
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
        } else if (plano === 'KP') {
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
        const diferencaFracoes = Math.max(0, combustivelSaida - tanqueChegada);
        let htmlFuel = '';

        if (diferencaFracoes > 0 && state.valorPorFracao > 0) {
            totalCombustivel = diferencaFracoes * state.valorPorFracao;
            htmlFuel = `
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFuelOutLabel}:</span><strong>${state.combustivelLabels[combustivelSaida] || combustivelSaida + '/8'}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFuelReturn}:</span><strong>${state.combustivelLabels[tanqueChegada] || tanqueChegada + '/8'}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcFuelDiff}:</span><strong>${diferencaFracoes} ${diferencaFracoes === 1 ? i18n.calcFraction : i18n.calcFractions}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">${i18n.calcValuePerFraction}:</span><strong>${Currency.format(state.valorPorFracao, true)}</strong></div>
                    <div class="flex justify-between border-t border-slate-300 pt-1 mt-1 font-semibold"><span>${i18n.calcTotalFuel}:</span><strong>${Currency.format(totalCombustivel, true)}</strong></div>
                </div>
            `;
        } else {
            htmlFuel = `<p class="text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i>${i18n.calcNoFuelCharge}</p>`;
        }

        // --- SUBTOTAL ---
        const subtotal = totalKm + totalCombustivel;
        const corSubtotal = subtotal > 0 ? 'text-red-600' : 'text-green-600';
        const tipo = v.veiculo_tipo_combustivel || '';
        const fuelLabel = FuelLabels.isElectric(tipo) ? i18n.calcChargeLabel : i18n.calcFuelLabel;
        const htmlSubtotal = `
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-600">${i18n.calcKmLabel}:</span>
                    <strong>${Currency.format(totalKm, true)}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">${fuelLabel}:</span>
                    <strong>${Currency.format(totalCombustivel, true)}</strong>
                </div>
                <div class="flex justify-between border-t border-amber-300 pt-2 mt-2 text-base font-bold ${corSubtotal}">
                    <span>${i18n.summaryTotal}:</span>
                    <span>${Currency.format(subtotal, true)}</span>
                </div>
            </div>
        `;

        state.totalKm = totalKm;
        state.totalCombustivel = totalCombustivel;

        // Atualizar DOM
        card.querySelector('.calc-km-content').innerHTML = htmlKm;
        card.querySelector('.calc-fuel-content').innerHTML = htmlFuel;
        card.querySelector('.calc-subtotal-content').innerHTML = htmlSubtotal;

        atualizarResumoGeral();
    }

    // ==================== RESUMO GERAL ====================

    function atualizarResumoGeral() {
        let totalGeralKm = 0;
        let totalGeralCombustivel = 0;
        let selecionados = 0;
        let htmlLinhas = '';

        Object.keys(veiculoState).forEach(i => {
            const state = veiculoState[i];
            if (!state.selecionado && !singleMode) return;
            selecionados++;

            const subtotal = state.totalKm + state.totalCombustivel;
            totalGeralKm += state.totalKm;
            totalGeralCombustivel += state.totalCombustivel;

            const tipoV = state.data.veiculo_tipo_combustivel || '';
            const fuelLabelResumo = FuelLabels.isElectric(tipoV) ? i18n.calcChargeLabel : i18n.calcFuelLabel;
            htmlLinhas += `
                <div class="flex justify-between text-sm py-1">
                    <span class="text-slate-700">${state.data.veiculo_placa} - ${(state.data.veiculo_marca || '')} ${(state.data.veiculo_modelo || '')}</span>
                    <span class="text-slate-600">
                        ${i18n.calcKmLabel}: ${Currency.format(state.totalKm, true)}
                        · ${fuelLabelResumo}: ${Currency.format(state.totalCombustivel, true)}
                        · <strong>${Currency.format(subtotal, true)}</strong>
                    </span>
                </div>
            `;
        });

        const totalGeral = totalGeralKm + totalGeralCombustivel;
        const resumoEl = document.getElementById('resumoGeral');
        const resumoContent = document.getElementById('resumoContent');

        if (selecionados > 0) {
            resumoEl.classList.remove('hidden');
            const corTotal = totalGeral > 0 ? 'text-red-600' : 'text-green-600';
            const labelExtra = totalGeral > 0 ? i18n.summaryCharge : i18n.summaryNoCharge;
            resumoContent.innerHTML = htmlLinhas + `
                <div class="flex justify-between border-t border-green-300 pt-2 mt-2 text-base font-bold ${corTotal}">
                    <span>${i18n.summaryTotal}:</span>
                    <span>${Currency.format(totalGeral, true)} <span class="text-xs font-normal">(${labelExtra})</span></span>
                </div>
            `;
        } else {
            resumoEl.classList.add('hidden');
        }
    }

    // ==================== BOTAO CONFIRMAR ====================

    function atualizarBotaoConfirmar() {
        const count = singleMode ? 1 : Object.values(veiculoState).filter(s => s.selecionado).length;
        const texto = document.getElementById('btnConfirmarTexto');
        if (count > 1) {
            texto.textContent = i18n.confirmReturns.replace(':count', count);
        } else {
            texto.textContent = i18n.confirmReturn;
        }
    }

    // ==================== SUBMISSAO ====================

    function formatarDatetimeLocal(date) {
        const pad = value => String(value).padStart(2, '0');
        return [
            date.getFullYear(),
            pad(date.getMonth() + 1),
            pad(date.getDate())
        ].join('-') + 'T' + [
            pad(date.getHours()),
            pad(date.getMinutes())
        ].join(':');
    }

    async function confirmarDevolucao() {
        const veiculosPayload = [];

        Object.keys(veiculoState).forEach(i => {
            const state = veiculoState[i];
            if (!state.selecionado && !singleMode) return;

            const card = document.querySelector(`.veiculo-card[data-index="${i}"]`);
            const odometro = card.querySelector('.odometro-atual').value;
            const dataDevolucao = card.querySelector('.data-devolucao')?.value || '';

            veiculosPayload.push({
                id_contrato_veiculo: state.data.id,
                data_entrada: dataDevolucao,
                odometro_entrada: Km.parse(odometro || '0'),
                combustivel_entrada: card.querySelector('.tanque-chegada').value || null,
                acao_veiculo: card.querySelector('.acao-veiculo').value || 'disponivel',
                observacao: card.querySelector('.observacao-veiculo').value || null,
            });
        });

        if (veiculosPayload.length === 0) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectAtLeastOne }, '*');
            return;
        }

        // Validar: todos tem data/hora de devolucao
        for (const vp of veiculosPayload) {
            if (!vp.data_entrada) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.informReturnDate }, '*');
                return;
            }
        }

        // Validar: todos tem odometro
        for (const vp of veiculosPayload) {
            if (!vp.odometro_entrada || vp.odometro_entrada <= 0) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.informOdometer }, '*');
                return;
            }
        }

        const btnConfirmar = document.getElementById('btnConfirmar');
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.processing}`;

        try {
            const result = await API.post(`/contratos/${contratoId}/devolver`, { veiculos: veiculosPayload });

            if (result.success) {
                window.parent.postMessage({
                    action: 'openAlert',
                    type: 'success',
                    message: result.message || i18n.returnSuccess
                }, '*');
                setTimeout(() => voltar(), 500);
            } else {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: result.message || i18n.returnError
                }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.returnError
            }, '*');
        } finally {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = `<i class="fas fa-check mr-2"></i><span id="btnConfirmarTexto">${i18n.confirmReturn}</span>`;
            atualizarBotaoConfirmar();
        }
    }

    // ==================== NAVEGACAO ====================

    function voltar() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: '/pages/contratos' }, '*');
        } else {
            window.location.href = '/pages/contratos';
        }
    }

    // ==================== EVENTOS ====================

    function configurarEventos() {
        // Checkboxes (multi-veiculo)
        document.querySelectorAll('.veiculo-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const index = parseInt(this.dataset.index);
                veiculoState[index].selecionado = this.checked;
                toggleCard(index, this.checked);
                atualizarResumoGeral();
                atualizarBotaoConfirmar();
            });
        });

        // Click no header do card (apenas expand/collapse, sem afetar checkbox)
        if (!singleMode) {
            document.querySelectorAll('.card-header').forEach(header => {
                header.addEventListener('click', function(e) {
                    if (e.target.closest('input[type="checkbox"]')) return;
                    const index = parseInt(this.dataset.index);
                    toggleCard(index);
                });
            });
        }

        // Selecionar todos
        const btnSelecionarTodos = document.getElementById('btnSelecionarTodos');
        if (btnSelecionarTodos) {
            let todosChecked = false;
            btnSelecionarTodos.addEventListener('click', function() {
                todosChecked = !todosChecked;
                document.querySelectorAll('.veiculo-checkbox').forEach(cb => {
                    cb.checked = todosChecked;
                    cb.dispatchEvent(new Event('change'));
                });
                this.innerHTML = todosChecked
                    ? `<i class="fas fa-times mr-1"></i>${i18n.deselectAll}`
                    : `<i class="fas fa-check-double mr-1"></i>${i18n.selectAll}`;
            });
        }

        // Odometro e tanque de cada card
        document.querySelectorAll('.odometro-atual').forEach(input => {
            input.addEventListener('input', function() {
                calcularDiferencasVeiculo(parseInt(this.dataset.index));
            });
        });

        document.querySelectorAll('.tanque-chegada').forEach(select => {
            select.addEventListener('change', function() {
                calcularDiferencasVeiculo(parseInt(this.dataset.index));
            });
        });

        // Botoes
        document.getElementById('btnVoltar').addEventListener('click', voltar);
        document.getElementById('btnCancelar').addEventListener('click', voltar);
        document.getElementById('btnConfirmar').addEventListener('click', confirmarDevolucao);
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(init, 0);
    });
})();
</script>
@endsection
