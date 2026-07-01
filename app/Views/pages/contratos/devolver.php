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
    $filialRetiradaId = (int) ($contrato['id_matriz_filial_retirada'] ?? 0);
    $hojeInput = \App\Helpers\DateHelper::todayForDatabase();
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

        <!-- Taxas e servicos da devolucao -->
        <div class="form-section mb-6">
            <button type="button" id="toggleTaxasDevolucao" class="w-full flex items-center justify-between text-left">
                <span class="form-section-title mb-0">
                    <i class="fas fa-receipt mr-2 text-slate-500"></i>Taxas e servicos
                </span>
                <i class="fas fa-chevron-down text-slate-400" id="iconTaxasDevolucao"></i>
            </button>
            <div id="conteudoTaxasDevolucao" class="hidden mt-4 pl-0 md:pl-4">
                <div class="bg-slate-50 p-4 rounded-md mb-3">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group">Taxa/servico</label>
                            <select id="taxa_select"
                                    class="chosen-select form-input-group-field"
                                    data-chosen-type="server-side"
                                    data-chosen-search-url="/api/taxas-e-servicos/buscar"
                                    data-chosen-placeholder="<?= t('common.labels.type_to_search') ?>"
                                    data-chosen-min-chars="2">
                                <option value="">Selecione</option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group">Nome</label>
                            <input type="text" id="taxa_nome" class="form-input-group-field bg-slate-100" placeholder="Nome" readonly>
                        </div>
                        <div class="md:col-span-1 form-input-group">
                            <label class="form-label-group">Qtd</label>
                            <input type="number" id="taxa_qtd" class="form-input-group-field bg-slate-100" value="1" min="1" readonly>
                        </div>
                        <div class="md:col-span-2 form-input-group">
                            <label class="form-label-group">Valor unitario</label>
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
                <div id="listaTaxas" class="space-y-2"></div>
            </div>
        </div>

        <!-- Resumo geral -->
        <div id="resumoGeral" class="form-section bg-green-50 border border-green-200 <?= $singleMode ? '' : 'hidden' ?> my-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="form-section-title text-green-800 mb-0">
                    <i class="fas fa-receipt mr-2 text-green-600"></i><?= t('modules.contratos.return_page.summary_title') ?>
                </h3>
                <button type="button" id="btnGerarPagamento" class="btn-secondary py-2 px-3 rounded-md text-sm font-medium hidden">
                    <i class="fas fa-dollar-sign mr-2"></i>Gerar pagamento
                </button>
            </div>
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
        <input type="hidden" id="financeiroIdConta" value="<?= !empty($contrato['id_conta']) ? (int) $contrato['id_conta'] : '' ?>">
        <input type="hidden" id="financeiroContaTexto" value="<?= htmlspecialchars($contrato['conta_descricao'] ?? '') ?>">
        <input type="hidden" id="financeiroIdFormaPagamento" value="<?= !empty($contrato['id_forma_pagamento']) ? (int) $contrato['id_forma_pagamento'] : '' ?>">
        <input type="hidden" id="financeiroFormaPagamentoTexto" value="<?= htmlspecialchars($contrato['forma_pagamento_descricao'] ?? '') ?>">
        <input type="hidden" id="financeiroDataVenci" value="<?= htmlspecialchars($hojeInput) ?>">
        <input type="hidden" id="financeiroPago" value="N">
        <input type="hidden" id="financeiroDataPago" value="">
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
    const filialRetiradaId = <?= (int) $filialRetiradaId ?>;
    const hojeInput = <?= json_encode($hojeInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const taxasExtrasState = [];
    let taxasDisponiveis = [];
    let taxaSelecionadaAtual = null;
    let totalGeralAtual = 0;

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
        const agora = DateHelper.nowInput();

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
        carregarTaxasDisponiveis();
        renderizarTaxasDevolucao();

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

    // ==================== TAXAS EXTRAS ====================

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function calcularTotalTaxasExtras() {
        return taxasExtrasState.reduce((total, taxa) => {
            return total + ((parseInt(taxa.quantidade) || 1) * (parseFloat(taxa.valor_unitario) || 0));
        }, 0);
    }

    async function carregarTaxasDisponiveis() {
        try {
            const params = {};
            if (filialRetiradaId > 0) {
                params.id_filial = filialRetiradaId;
            }

            const result = await API.get('/api/taxas-e-servicos/buscar', params);
            if (result.success && Array.isArray(result.data)) {
                taxasDisponiveis = result.data;
            }
        } catch (error) {
            console.error('Erro ao buscar taxas:', error);
        }
    }

    async function obterTaxaSelecionada(idTaxa) {
        let taxa = taxasDisponiveis.find(t => String(t.id) === String(idTaxa));
        if (taxa) {
            return taxa;
        }

        try {
            const result = await API.get(`/api/taxas-e-servicos/${idTaxa}`);
            if (result.success && result.data) {
                taxa = {
                    id: result.data.id,
                    text: result.data.nome,
                    valor: result.data.valor,
                    base_calculo: result.data.base_calculo,
                    tipo_valor: result.data.tipo_valor,
                };
                taxasDisponiveis.push(taxa);
                return taxa;
            }
        } catch (error) {
            console.error('Erro ao buscar taxa selecionada:', error);
        }

        return null;
    }

    async function preencherTaxaSelecionada(idTaxa) {
        const simboloEl = document.querySelector('#taxa_valor')?.parentElement?.querySelector('.currency-symbol');
        const inputValor = document.getElementById('taxa_valor');

        if (!idTaxa) {
            taxaSelecionadaAtual = null;
            document.getElementById('taxa_nome').value = '';
            document.getElementById('taxa_qtd').value = '1';
            document.getElementById('taxa_valor').value = '';
            if (simboloEl) simboloEl.textContent = 'R$';
            inputValor?.classList.add('input-moeda');
            return;
        }

        const taxa = await obterTaxaSelecionada(idTaxa);
        if (!taxa) {
            taxaSelecionadaAtual = null;
            return;
        }

        taxaSelecionadaAtual = {
            id: taxa.id,
            nome: taxa.text || taxa.nome || '',
            valor: parseFloat(taxa.valor) || 0,
            base_calculo: taxa.base_calculo || 'FIX',
            tipo_valor: taxa.tipo_valor || 'MON',
        };

        document.getElementById('taxa_nome').value = taxaSelecionadaAtual.nome;
        document.getElementById('taxa_qtd').value = 1;

        if (taxaSelecionadaAtual.tipo_valor === 'POR') {
            if (simboloEl) simboloEl.textContent = '%';
            if (inputValor) {
                inputValor.value = taxaSelecionadaAtual.valor ? String(taxaSelecionadaAtual.valor).replace('.', ',') : '';
                inputValor.classList.remove('input-moeda');
            }
        } else {
            if (simboloEl) simboloEl.textContent = 'R$';
            if (inputValor) {
                inputValor.value = taxaSelecionadaAtual.valor ? Currency.format(taxaSelecionadaAtual.valor) : '';
                inputValor.classList.add('input-moeda');
            }
        }
    }

    function adicionarTaxaDevolucao() {
        const select = document.getElementById('taxa_select');
        const nome = document.getElementById('taxa_nome')?.value.trim() || '';
        const quantidade = Math.max(1, parseInt(document.getElementById('taxa_qtd')?.value || '1'));
        const valorUnitario = Currency.parse(document.getElementById('taxa_valor')?.value || '0');

        if (!nome || !select?.value) {
            window.parent.postMessage({ action: 'openAlert', message: 'Selecione uma taxa ou servico' }, '*');
            return;
        }

        taxasExtrasState.push({
            id_taxa: parseInt(select.value),
            nome,
            base_calculo: taxaSelecionadaAtual?.base_calculo || 'FIX',
            tipo_valor: taxaSelecionadaAtual?.tipo_valor || 'MON',
            quantidade,
            valor_unitario: valorUnitario,
        });

        taxaSelecionadaAtual = null;
        if (select.chosenSelect) {
            select.chosenSelect.clear();
        } else {
            select.value = '';
        }
        document.getElementById('taxa_nome').value = '';
        document.getElementById('taxa_qtd').value = '1';
        document.getElementById('taxa_valor').value = '';
        const simboloEl = document.querySelector('#taxa_valor')?.parentElement?.querySelector('.currency-symbol');
        if (simboloEl) simboloEl.textContent = 'R$';

        renderizarTaxasDevolucao();
        atualizarResumoGeral();
    }

    function renderizarTaxasDevolucao() {
        const lista = document.getElementById('listaTaxas');
        if (!lista) return;

        if (taxasExtrasState.length === 0) {
            lista.innerHTML = '<p class="text-sm text-slate-500">Nenhuma taxa adicional selecionada.</p>';
            return;
        }

        lista.innerHTML = taxasExtrasState.map((taxa, index) => {
            const total = (parseInt(taxa.quantidade) || 1) * (parseFloat(taxa.valor_unitario) || 0);
            return `
                <div class="flex items-center justify-between bg-white border border-slate-200 rounded-md px-4 py-3">
                    <div>
                        <p class="font-medium text-slate-800">${escapeHtml(taxa.nome)}</p>
                        <p class="text-sm text-slate-500">${taxa.quantidade} x ${Currency.format(taxa.valor_unitario, true)}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <strong class="text-slate-800">${Currency.format(total, true)}</strong>
                        <button type="button" class="text-red-600 hover:text-red-800 btn-remover-taxa-devolucao" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        lista.querySelectorAll('.btn-remover-taxa-devolucao').forEach(btn => {
            btn.addEventListener('click', function() {
                taxasExtrasState.splice(parseInt(this.dataset.index), 1);
                renderizarTaxasDevolucao();
                atualizarResumoGeral();
            });
        });
    }

    function atualizarFinanceiroDevolucao() {
        const btn = document.getElementById('btnGerarPagamento');
        if (!btn) return;

        btn.classList.toggle('hidden', totalGeralAtual <= 0);
    }

    function optionLabel(item) {
        return item.text || item.descricao || item.nome || item.nome_rsocial || item.titulo || item.label || '';
    }

    function financeiroValue(id) {
        return document.getElementById(id)?.value || '';
    }

    function setFinanceiroValue(id, value) {
        const input = document.getElementById(id);
        if (input) input.value = value || '';
    }

    function montarOptionsOffcanvas(itens, selectedValue, selectedText) {
        const map = new Map();
        if (selectedValue) {
            map.set(String(selectedValue), selectedText || 'Selecionado');
        }

        (itens || []).forEach(item => {
            if (!item || item.id === undefined || item.id === null) return;
            map.set(String(item.id), optionLabel(item));
        });

        let html = '<option value="">Selecione</option>';
        map.forEach((label, value) => {
            html += `<option value="${escapeHtml(value)}" ${String(value) === String(selectedValue) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        });
        return html;
    }

    async function carregarSelectOffcanvas(selector, url, selectedValue, selectedText) {
        const doc = window.parent?.document;
        const select = doc?.querySelector(selector);
        if (!select) return;

        select.innerHTML = montarOptionsOffcanvas([], selectedValue, selectedText);

        try {
            const result = await API.get(url);
            if (result.success && Array.isArray(result.data)) {
                select.innerHTML = montarOptionsOffcanvas(result.data, selectedValue, selectedText);
            }
        } catch (error) {
            console.error('Erro ao carregar dados do pagamento:', error);
        }
    }

    function configurarEventosOffcanvasPagamento() {
        const doc = window.parent?.document;
        if (!doc) return;

        const pagoSelect = doc.getElementById('offFinanceiroPago');
        const dataPagoGroup = doc.getElementById('offFinanceiroDataPagoGroup');
        if (!pagoSelect || !dataPagoGroup) return;

        const toggleDataPago = () => {
            dataPagoGroup.classList.toggle('hidden', pagoSelect.value !== 'S');
        };

        pagoSelect.addEventListener('change', toggleDataPago);
        toggleDataPago();
    }

    function abrirOffcanvasPagamento() {
        const idConta = financeiroValue('financeiroIdConta');
        const contaTexto = financeiroValue('financeiroContaTexto');
        const idFormaPagamento = financeiroValue('financeiroIdFormaPagamento');
        const formaPagamentoTexto = financeiroValue('financeiroFormaPagamentoTexto');
        const dataVenci = financeiroValue('financeiroDataVenci') || hojeInput;
        const pago = financeiroValue('financeiroPago') || 'N';
        const dataPago = financeiroValue('financeiroDataPago') || hojeInput;

        const content = `
            <div class="p-6">
                <div class="space-y-4">
                    <div class="form-input-group">
                        <label class="form-label-group">Conta bancaria <span class="text-red-500">*</span></label>
                        <select id="offFinanceiroIdConta" class="form-input-group-field">
                            ${montarOptionsOffcanvas([], idConta, contaTexto)}
                        </select>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group">Forma de pagamento <span class="text-red-500">*</span></label>
                        <select id="offFinanceiroIdFormaPagamento" class="form-input-group-field">
                            ${montarOptionsOffcanvas([], idFormaPagamento, formaPagamentoTexto)}
                        </select>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group">Vencimento <span class="text-red-500">*</span></label>
                        <input type="date" id="offFinanceiroDataVenci" class="form-input-group-field" value="${escapeHtml(dataVenci)}">
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group">Pago?</label>
                        <select id="offFinanceiroPago" class="form-input-group-field">
                            <option value="N" ${pago !== 'S' ? 'selected' : ''}>Nao</option>
                            <option value="S" ${pago === 'S' ? 'selected' : ''}>Sim</option>
                        </select>
                    </div>
                    <div class="form-input-group ${pago === 'S' ? '' : 'hidden'}" id="offFinanceiroDataPagoGroup">
                        <label class="form-label-group">Data de pagamento <span class="text-red-500">*</span></label>
                        <input type="date" id="offFinanceiroDataPago" class="form-input-group-field" value="${escapeHtml(dataPago)}">
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <p class="text-sm text-green-700">Total da cobranca</p>
                        <p class="text-2xl font-bold text-green-800 mt-1">${Currency.format(totalGeralAtual, true)}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" id="btnCancelarPagamentoDevolucao" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">Cancelar</button>
                    <button type="button" id="btnAplicarPagamentoDevolucao" class="btn-green py-2 px-4 rounded-md text-sm font-medium">Aplicar</button>
                </div>
            </div>
        `;

        window.parent.postMessage({
            action: 'openOffcanvasContent',
            title: 'Gerar pagamento',
            width: '420px',
            content
        }, '*');

        setTimeout(() => {
            carregarSelectOffcanvas('#offFinanceiroIdConta', '/api/contas-bancarias/buscar', idConta, contaTexto);
            carregarSelectOffcanvas('#offFinanceiroIdFormaPagamento', '/api/formas-pagamento/select', idFormaPagamento, formaPagamentoTexto);
            configurarEventosOffcanvasPagamento();
        }, 150);
    }

    function aplicarPagamentoOffcanvas(data) {
        if (!data.offFinanceiroIdConta) {
            window.parent.postMessage({ action: 'openAlert', message: 'Selecione a conta bancaria' }, '*');
            return;
        }

        if (!data.offFinanceiroIdFormaPagamento) {
            window.parent.postMessage({ action: 'openAlert', message: 'Selecione a forma de pagamento' }, '*');
            return;
        }

        if (!data.offFinanceiroDataVenci) {
            window.parent.postMessage({ action: 'openAlert', message: 'Informe o vencimento' }, '*');
            return;
        }

        if (data.offFinanceiroPago === 'S' && !data.offFinanceiroDataPago) {
            window.parent.postMessage({ action: 'openAlert', message: 'Informe a data de pagamento' }, '*');
            return;
        }

        const doc = window.parent?.document;
        const contaSelect = doc?.getElementById('offFinanceiroIdConta');
        const formaSelect = doc?.getElementById('offFinanceiroIdFormaPagamento');

        setFinanceiroValue('financeiroIdConta', data.offFinanceiroIdConta);
        setFinanceiroValue('financeiroContaTexto', contaSelect?.selectedOptions?.[0]?.textContent || '');
        setFinanceiroValue('financeiroIdFormaPagamento', data.offFinanceiroIdFormaPagamento);
        setFinanceiroValue('financeiroFormaPagamentoTexto', formaSelect?.selectedOptions?.[0]?.textContent || '');
        setFinanceiroValue('financeiroDataVenci', data.offFinanceiroDataVenci);
        setFinanceiroValue('financeiroPago', data.offFinanceiroPago === 'S' ? 'S' : 'N');
        setFinanceiroValue('financeiroDataPago', data.offFinanceiroPago === 'S' ? data.offFinanceiroDataPago : '');

        atualizarResumoGeral();
        window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
    }

    function handleOffcanvasPagamentoMessage(event) {
        if (!event.data || event.data.action !== 'offcanvasButtonClick') return;

        if (event.data.buttonId === 'btnCancelarPagamentoDevolucao') {
            window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
            return;
        }

        if (event.data.buttonId === 'btnAplicarPagamentoDevolucao') {
            aplicarPagamentoOffcanvas(event.data);
        }
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

        const totalTaxasExtras = calcularTotalTaxasExtras();
        const totalGeral = totalGeralKm + totalGeralCombustivel + totalTaxasExtras;
        totalGeralAtual = totalGeral;
        const resumoEl = document.getElementById('resumoGeral');
        const resumoContent = document.getElementById('resumoContent');

        if (selecionados > 0) {
            resumoEl.classList.remove('hidden');
            const corTotal = totalGeral > 0 ? 'text-red-600' : 'text-green-600';
            const htmlTaxas = totalTaxasExtras > 0 ? `
                <div class="flex justify-between text-sm py-1">
                    <span class="text-slate-700">Taxas e servicos</span>
                    <span class="text-slate-600"><strong>${Currency.format(totalTaxasExtras, true)}</strong></span>
                </div>
            ` : '';
            const pagamentoPago = totalGeral > 0 && financeiroValue('financeiroPago') === 'S';
            const htmlTotais = pagamentoPago ? `
                <div class="border-t border-green-300 pt-2 mt-2 space-y-1">
                    <div class="flex justify-between text-base font-bold text-green-700">
                        <span>VALOR PAGO:</span>
                        <span>${Currency.format(totalGeral, true)}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold text-green-800">
                        <span>TOTAL A PAGAR:</span>
                        <span>${Currency.format(0, true)}</span>
                    </div>
                </div>
            ` : `
                <div class="flex justify-between border-t border-green-300 pt-2 mt-2 text-base font-bold ${corTotal}">
                    <span>${i18n.summaryTotal}:</span>
                    <span>${Currency.format(totalGeral, true)}</span>
                </div>
            `;

            resumoContent.innerHTML = htmlLinhas + htmlTaxas + htmlTotais;
        } else {
            totalGeralAtual = 0;
            resumoEl.classList.add('hidden');
        }

        atualizarFinanceiroDevolucao();
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

        const gerarFinanceiro = totalGeralAtual > 0;
        const financeiroPayload = {
            gerar_financeiro: gerarFinanceiro ? 1 : 0,
            id_conta: document.getElementById('financeiroIdConta')?.value || '',
            id_forma_pagamento: document.getElementById('financeiroIdFormaPagamento')?.value || '',
            data_venci: document.getElementById('financeiroDataVenci')?.value || '',
            pago: document.getElementById('financeiroPago')?.value || 'N',
            data_pago: document.getElementById('financeiroDataPago')?.value || '',
        };

        if (gerarFinanceiro) {
            if (!financeiroPayload.id_conta) {
                window.parent.postMessage({ action: 'openAlert', message: 'Selecione a conta bancaria' }, '*');
                return;
            }

            if (!financeiroPayload.id_forma_pagamento) {
                window.parent.postMessage({ action: 'openAlert', message: 'Selecione a forma de pagamento' }, '*');
                return;
            }

            if (!financeiroPayload.data_venci) {
                window.parent.postMessage({ action: 'openAlert', message: 'Informe o vencimento' }, '*');
                return;
            }

            if (financeiroPayload.pago === 'S' && !financeiroPayload.data_pago) {
                window.parent.postMessage({ action: 'openAlert', message: 'Informe a data de pagamento' }, '*');
                return;
            }
        }

        const btnConfirmar = document.getElementById('btnConfirmar');
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.processing}`;

        try {
            const result = await API.post(`/contratos/${contratoId}/devolver`, {
                veiculos: veiculosPayload,
                taxas_extras: taxasExtrasState.map(taxa => ({
                    id_taxa: taxa.id_taxa,
                    quantidade: taxa.quantidade,
                    valor_unitario: taxa.valor_unitario,
                })),
                ...financeiroPayload,
            });

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

        document.getElementById('taxa_select')?.addEventListener('change', function() {
            preencherTaxaSelecionada(this.value);
        });

        document.getElementById('btnAdicionarTaxa')?.addEventListener('click', adicionarTaxaDevolucao);

        document.getElementById('toggleTaxasDevolucao')?.addEventListener('click', function() {
            const conteudo = document.getElementById('conteudoTaxasDevolucao');
            const icon = document.getElementById('iconTaxasDevolucao');
            if (!conteudo) return;

            const isHidden = conteudo.classList.contains('hidden');
            conteudo.classList.toggle('hidden', !isHidden);
            if (icon) {
                icon.classList.toggle('fa-chevron-down', !isHidden);
                icon.classList.toggle('fa-chevron-up', isHidden);
            }
        });

        // Botoes
        document.getElementById('btnVoltar').addEventListener('click', voltar);
        document.getElementById('btnCancelar').addEventListener('click', voltar);
        document.getElementById('btnConfirmar').addEventListener('click', confirmarDevolucao);
        document.getElementById('btnGerarPagamento')?.addEventListener('click', abrirOffcanvasPagamento);
        window.addEventListener('message', handleOffcanvasPagamentoMessage);
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(init, 0);
    });
})();
</script>
@endsection
