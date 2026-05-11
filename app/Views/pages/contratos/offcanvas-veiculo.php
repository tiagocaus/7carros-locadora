@extends('layouts.iframe')

@section('title', t('modules.contratos.vehicles.vehicle_contract'))

@section('content')
<div class="p-4">
    <form id="formVeiculo">
        <!-- Plano -->
        <div class="form-input-group mb-4">
            <label for="plano" class="form-label-group"><?= t('modules.contratos.substitution.plan') ?> <span class="text-red-500">*</span></label>
            <select id="plano" class="form-input-group-field chosen-select">
                <option value=""><?= t('common.labels.select') ?>...</option>
                <option value="KP"><?= t('modules.contratos.vehicles.plan_km_paid') ?></option>
                <option value="KMC"><?= t('modules.contratos.vehicles.plan_km_controlled') ?></option>
                <option value="KL"><?= t('modules.contratos.vehicles.plan_km_free') ?></option>
            </select>
        </div>

        <!-- Grupo -->
        <div class="form-input-group mb-4">
            <label for="grupo" class="form-label-group"><?= t('modules.contratos.substitution.group') ?> <span class="text-red-500">*</span></label>
            <select id="grupo" class="form-input-group-field chosen-select">
                <option value=""><?= t('common.labels.select') ?>...</option>
            </select>
        </div>

        <!-- Veiculo -->
        <div class="form-input-group mb-4">
            <label for="veiculo" class="form-label-group"><?= t('modules.contratos.substitution.vehicle') ?> <span class="text-red-500">*</span></label>
            <select id="veiculo" class="form-input-group-field chosen-select">
                <option value=""><?= t('modules.contratos.messages.select_group_first') ?></option>
            </select>
        </div>

        <!-- Seguros -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-input-group">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="seguro_carro" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                    <span class="ml-2 text-sm"><?= t('modules.contratos.vehicles.vehicle_insurance') ?></span>
                </label>
            </div>
            <div class="form-input-group">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="seguro_terceiros" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                    <span class="ml-2 text-sm"><?= t('modules.contratos.vehicles.third_party_insurance') ?></span>
                </label>
            </div>
        </div>

        <!-- Odometro e Tanque -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-input-group">
                <label for="odometro" class="form-label-group"><?= t('modules.contratos.vehicles.odometer_out') ?></label>
                <input type="text" id="odometro" class="form-input-group-field input-km" placeholder="0">
            </div>
            <div class="form-input-group">
                <label for="tanque" class="form-label-group"><?= t('modules.contratos.vehicles.fuel_out') ?></label>
                <select id="tanque" class="form-input-group-field">
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
        <div class="border-t border-slate-200 pt-4 mt-4">
            <h4 class="text-sm font-semibold text-slate-700 mb-3" id="valores_titulo">
                <?= t('modules.contratos.vehicles.values_per', ['period' => '<span id="contagem_label">dia</span>']) ?>
            </h4>

            <!-- Campos Plano Km Pago -->
            <div id="campos_km_pago" class="hidden">
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_paid') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                            <input type="text" id="valor_diaria" class="form-input-group-field pl-7 text-sm input-moeda">
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_per_km') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                            <input type="text" id="valor_km_diaria" class="form-input-group-field pl-7 text-sm input-moeda">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campos Plano Km Controlado -->
            <div id="campos_km_controlado" class="hidden">
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_controlled') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                            <input type="text" id="valor_km_controlado" class="form-input-group-field pl-7 text-sm input-moeda">
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.km_franchise') ?></label>
                        <input type="text" id="km_franquia" class="form-input-group-field text-sm input-km">
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_excess') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                            <input type="text" id="valor_km_excedente" class="form-input-group-field pl-7 text-sm input-moeda">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campos Plano Km Livre -->
            <div id="campos_km_livre" class="hidden">
                <div class="form-input-group mb-3">
                    <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_km_free') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                        <input type="text" id="valor_km_livre" class="form-input-group-field pl-7 text-sm input-moeda">
                    </div>
                </div>
            </div>

            <!-- Valores dos Seguros (sempre visiveis) -->
            <div class="grid grid-cols-2 gap-4">
                <div class="form-input-group">
                    <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_vehicle_insurance') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                        <input type="text" id="valor_seguro_carro" class="form-input-group-field pl-7 text-sm input-moeda">
                    </div>
                </div>
                <div class="form-input-group">
                    <label class="form-label-group text-xs"><?= t('modules.contratos.vehicles.value_third_party_insurance') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                        <input type="text" id="valor_seguro_terceiros" class="form-input-group-field pl-7 text-sm input-moeda">
                    </div>
                </div>
            </div>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-200">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="button" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                <i class="fas fa-check mr-2"></i><?= t('common.buttons.confirm') ?>
            </button>
        </div>
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
        'select' => t('common.labels.select') . '...',
        'selectGroupFirst' => t('modules.contratos.messages.select_group_first'),
        'selectVehicleHint' => t('modules.contratos.messages.select_vehicle_hint'),
        'loadGroupsError' => t('modules.contratos.messages.load_groups_error'),
        'loadVehiclesError' => t('modules.contratos.messages.load_vehicles_error'),
        'loadValuesError' => t('modules.contratos.messages.load_values_error'),
        'selectPlan' => t('modules.contratos.messages.select_plan'),
        'selectGroup' => t('modules.contratos.messages.select_group'),
        'selectVehicle' => t('modules.contratos.messages.select_vehicle'),
        'vehicleNotFound' => t('modules.contratos.vehicles.not_found'),
        'fuelOut' => t('modules.contratos.vehicles.fuel_out'),
        'chargeOut' => t('modules.contratos.vehicles.charge_out'),
        'fuelFull' => t('modules.contratos.fuel_levels.full'),
        'fuelReserve' => t('modules.contratos.fuel_levels.reserve'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // Parametros da URL
    const params = new URLSearchParams(window.location.search);
    const modo = params.get('modo') || 'adicionar';
    const index = params.get('index');
    const filialId = params.get('filial_id');
    const contagem = params.get('contagem') || 'dia';
    let dadosIniciais = null;

    // Multiplicadores por tipo de contagem (valores do grupo sao por dia)
    const multiplicadores = {
        'dia': 1,
        'semana': 7,
        'mes': 30,
        'ano': 365
    };
    const multiplicador = multiplicadores[contagem] || 1;

    try {
        const dadosParam = params.get('dados');
        if (dadosParam) {
            dadosIniciais = JSON.parse(decodeURIComponent(dadosParam));
        }
    } catch (e) {
        console.error('Erro ao parsear dados iniciais:', e);
    }

    // Cache de dados
    let gruposDisponiveis = [];
    let veiculosDisponiveis = [];
    let valoresGrupoCache = {};

    // Elementos
    const selectPlano = document.getElementById('plano');
    const selectGrupo = document.getElementById('grupo');
    const selectVeiculo = document.getElementById('veiculo');
    const checkSeguroCarro = document.getElementById('seguro_carro');
    const checkSeguroTerceiros = document.getElementById('seguro_terceiros');
    const inputOdometro = document.getElementById('odometro');
    const selectTanque = document.getElementById('tanque');

    // Campos de valores
    const camposKmPago = document.getElementById('campos_km_pago');
    const camposKmControlado = document.getElementById('campos_km_controlado');
    const camposKmLivre = document.getElementById('campos_km_livre');

    // Inicializar
    async function init() {
        // Atualizar label de contagem
        const contagemLabel = document.getElementById('contagem_label');
        contagemLabel.textContent = contagem;

        // Carregar grupos
        await carregarGrupos();

        // Se for edicao, preencher dados
        if (modo === 'editar' && dadosIniciais) {
            preencherDados(dadosIniciais);
        }

        // Aplicar mascaras
        Currency.applyMaskToAll('input-moeda');

        // Configurar eventos
        configurarEventos();

        // Sincronizar CSRF token com iframe do contrato
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const iframes = window.parent.document.querySelectorAll('iframe');
            iframes.forEach(iframe => {
                if (iframe.src && iframe.src.includes('contratos')) {
                    iframe.contentWindow.postMessage({ action: 'csrfTokenUpdate', csrfToken }, '*');
                }
            });
        } catch (e) {}
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
                // Atualizar Chosen Select
                if (selectGrupo.chosenSelect) {
                    selectGrupo.chosenSelect.refresh();
                }
            }
        } catch (error) {
            console.error('Erro ao carregar grupos:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.loadGroupsError
            }, '*');
        }
    }

    async function carregarVeiculosDoGrupo(grupoId, veiculoIdEditar = null) {
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
                    // Nao filtrar se estamos editando (o veiculo atual deve aparecer)
                    selectVeiculo.add(new Option(`${v.placa} - ${v.modelo}`, v.id));
                });
                // Atualizar Chosen Select
                if (selectVeiculo.chosenSelect) {
                    selectVeiculo.chosenSelect.refresh();
                }

                // Carregar valores do grupo
                await carregarValoresGrupo(grupoId);
            }
        } catch (error) {
            console.error('Erro ao carregar veiculos:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.loadVehiclesError
            }, '*');
        }
    }

    async function carregarValoresGrupo(grupoId) {
        // Cache por grupo+filial — multi-moeda
        const cacheKey = `${grupoId}:${filialId || 0}`;

        if (valoresGrupoCache[cacheKey]) {
            preencherValores(valoresGrupoCache[cacheKey]);
            return;
        }

        try {
            // Prioriza endpoint multi-moeda quando ha filial
            if (filialId && parseInt(filialId) > 0) {
                const res = await API.get(`/api/grupos/${grupoId}/precos-filial/${filialId}`);
                if (res.success && res.data?.valores) {
                    valoresGrupoCache[cacheKey] = res.data.valores;
                    preencherValores(res.data.valores);
                    return;
                }
            }
            // Fallback: valores globais do grupo
            const result = await API.get(`/api/grupos/${grupoId}`);
            if (result.success) {
                valoresGrupoCache[cacheKey] = result.data;
                preencherValores(result.data);
            }
        } catch (error) {
            console.error('Erro ao carregar valores do grupo:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.loadValuesError
            }, '*');
        }
    }

    function preencherValores(valores) {
        // Aplicar multiplicador nos valores por periodo (valores do grupo sao por dia)
        document.getElementById('valor_diaria').value = valores.valor_plano_km_pago ? Currency.format(valores.valor_plano_km_pago * multiplicador) : '';
        document.getElementById('valor_km_controlado').value = valores.valor_plano_km_controlado ? Currency.format(valores.valor_plano_km_controlado * multiplicador) : '';
        document.getElementById('valor_km_livre').value = valores.valor_plano_km_livre ? Currency.format(valores.valor_plano_km_livre * multiplicador) : '';
        document.getElementById('valor_seguro_carro').value = valores.valor_seguro_carro ? Currency.format(valores.valor_seguro_carro * multiplicador) : '';
        document.getElementById('valor_seguro_terceiros').value = valores.valor_seguro_terceiros ? Currency.format(valores.valor_seguro_terceiros * multiplicador) : '';
        document.getElementById('km_franquia').value = valores.km_franquia ? (valores.km_franquia * multiplicador) : '';
        // Valores por km - NAO multiplicar
        document.getElementById('valor_km_diaria').value = valores.valor_km_excedente ? Currency.format(valores.valor_km_excedente) : '';
        document.getElementById('valor_km_excedente').value = valores.valor_km_excedente ? Currency.format(valores.valor_km_excedente) : '';
    }

    function atualizarCamposPorPlano(plano) {
        // Esconder todos
        camposKmPago.classList.add('hidden');
        camposKmControlado.classList.add('hidden');
        camposKmLivre.classList.add('hidden');

        // Mostrar o correto
        if (plano === 'KP') {
            camposKmPago.classList.remove('hidden');
        } else if (plano === 'KMC') {
            camposKmControlado.classList.remove('hidden');
        } else if (plano === 'KL') {
            camposKmLivre.classList.remove('hidden');
        }
    }

    function preencherDados(dados) {
        // Plano
        selectPlano.value = dados.plano || '';
        if (selectPlano.chosenSelect) selectPlano.chosenSelect.refresh();
        atualizarCamposPorPlano(dados.plano);

        // Grupo
        selectGrupo.value = dados.id_grupo || '';
        if (selectGrupo.chosenSelect) selectGrupo.chosenSelect.refresh();

        // Carregar veiculos do grupo e depois selecionar o veiculo
        if (dados.id_grupo) {
            carregarVeiculosDoGrupo(dados.id_grupo, dados.id_veiculo).then(() => {
                // Se o veiculo atual nao esta na lista (ex: disponibilidade != 'D'), adicionar
                if (dados.id_veiculo && !veiculosDisponiveis.find(v => v.id == dados.id_veiculo)) {
                    veiculosDisponiveis.push({
                        id: dados.id_veiculo,
                        placa: dados.placa,
                        marca: '',
                        modelo: dados.modelo,
                        tipo_combustivel: dados.tipo_combustivel || ''
                    });
                    selectVeiculo.add(new Option(`${dados.placa} - ${dados.modelo}`, dados.id_veiculo));
                }
                selectVeiculo.value = dados.id_veiculo || '';
                if (selectVeiculo.chosenSelect) selectVeiculo.chosenSelect.refresh();

                // Veiculo salvo: bloquear troca de veiculo
                if (dados._salvo) {
                    selectVeiculo.disabled = true;
                    if (selectVeiculo.chosenSelect) {
                        selectVeiculo.chosenSelect.wrapper.style.pointerEvents = 'none';
                        selectVeiculo.chosenSelect.wrapper.style.opacity = '0.6';
                    }
                }
            });
        }

        // Veiculo salvo: bloquear troca de grupo
        if (dados._salvo) {
            selectGrupo.disabled = true;
            if (selectGrupo.chosenSelect) {
                selectGrupo.chosenSelect.wrapper.style.pointerEvents = 'none';
                selectGrupo.chosenSelect.wrapper.style.opacity = '0.6';
            }
        }

        // Seguros
        checkSeguroCarro.checked = dados.seguro_carro || false;
        checkSeguroTerceiros.checked = dados.seguro_terceiros || false;

        // Odometro e tanque
        inputOdometro.value = dados.odometro_saida || '';
        atualizarLabelsTanque(dados.tipo_combustivel || '');
        selectTanque.value = dados.combustivel_saida || '';

        // Valores
        document.getElementById('valor_diaria').value = dados.valor_plano_km_pago ? Currency.format(dados.valor_plano_km_pago) : '';
        document.getElementById('valor_km_diaria').value = dados.valor_km_excedente ? Currency.format(dados.valor_km_excedente) : '';
        document.getElementById('valor_km_controlado').value = dados.valor_plano_km_controlado ? Currency.format(dados.valor_plano_km_controlado) : '';
        document.getElementById('km_franquia').value = dados.km_franquia || '';
        document.getElementById('valor_km_excedente').value = dados.valor_km_excedente ? Currency.format(dados.valor_km_excedente) : '';
        document.getElementById('valor_km_livre').value = dados.valor_plano_km_livre ? Currency.format(dados.valor_plano_km_livre) : '';
        document.getElementById('valor_seguro_carro').value = dados.valor_seguro_carro ? Currency.format(dados.valor_seguro_carro) : '';
        document.getElementById('valor_seguro_terceiros').value = dados.valor_seguro_terceiros ? Currency.format(dados.valor_seguro_terceiros) : '';
    }

    function atualizarLabelsTanque(tipoCombustivel) {
        const labelTanque = document.querySelector('label[for="tanque"]');
        if (labelTanque) {
            labelTanque.textContent = FuelLabels.isElectric(tipoCombustivel) ? i18n.chargeOut : i18n.fuelOut;
        }
        FuelLabels.updateSelectOptions(selectTanque, tipoCombustivel, i18n.fuelFull, i18n.fuelReserve);
    }

    function carregarDadosVeiculo(veiculoId) {
        const veiculoData = veiculosDisponiveis.find(v => v.id == veiculoId);
        if (veiculoData) {
            inputOdometro.value = veiculoData.odometro || '';
            atualizarLabelsTanque(veiculoData.tipo_combustivel || '');
            selectTanque.value = veiculoData.tanque_fracao || '';
        }
    }

    function configurarEventos() {
        // Plano muda
        selectPlano.addEventListener('change', function() {
            atualizarCamposPorPlano(selectPlano.value);
        });

        // Grupo muda
        selectGrupo.addEventListener('change', function() {
            if (selectGrupo.value) {
                carregarVeiculosDoGrupo(selectGrupo.value);
            } else {
                selectVeiculo.innerHTML = `<option value="">${i18n.selectGroupFirst}</option>`;
                if (selectVeiculo.chosenSelect) {
                    selectVeiculo.chosenSelect.refresh();
                }
            }
        });

        // Veiculo selecionado
        selectVeiculo.addEventListener('change', function() {
            if (selectVeiculo.value) {
                carregarDadosVeiculo(selectVeiculo.value);
            }
        });

        // Cancelar
        document.getElementById('btnCancelar').addEventListener('click', fecharOffcanvas);

        // Salvar
        document.getElementById('btnSalvar').addEventListener('click', salvarVeiculo);
    }

    function fecharOffcanvas() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
        }
    }

    function salvarVeiculo() {
        const plano = selectPlano.value;
        const grupoId = selectGrupo.value;
        const veiculoId = selectVeiculo.value;

        // Validacoes
        if (!plano) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectPlan }, '*');
            return;
        }
        if (!grupoId) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectGroup }, '*');
            return;
        }
        if (!veiculoId) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectVehicle }, '*');
            return;
        }

        // Buscar dados do veiculo
        const veiculoData = veiculosDisponiveis.find(v => v.id == veiculoId);
        if (!veiculoData) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.vehicleNotFound }, '*');
            return;
        }

        // Buscar nome do grupo
        const grupoData = gruposDisponiveis.find(g => g.id == grupoId);

        const dados = {
            id_veiculo: veiculoId,
            id_grupo: grupoId,
            grupo_nome: grupoData?.nome || '',
            placa: veiculoData.placa,
            modelo: [veiculoData.marca, veiculoData.modelo].filter(Boolean).join(' '),
            tipo_combustivel: veiculoData.tipo_combustivel || '',
            plano: plano,
            valor_plano_km_pago: Currency.parse(document.getElementById('valor_diaria').value || '0'),
            valor_plano_km_controlado: Currency.parse(document.getElementById('valor_km_controlado').value || '0'),
            valor_plano_km_livre: Currency.parse(document.getElementById('valor_km_livre').value || '0'),
            valor_km_excedente: Currency.parse(document.getElementById('valor_km_excedente').value || document.getElementById('valor_km_diaria').value || '0'),
            km_franquia: parseInt(document.getElementById('km_franquia').value || '0'),
            odometro_saida: parseInt(inputOdometro.value || '0'),
            combustivel_saida: selectTanque.value || null,
            seguro_carro: checkSeguroCarro.checked,
            valor_seguro_carro: Currency.parse(document.getElementById('valor_seguro_carro').value || '0'),
            seguro_terceiros: checkSeguroTerceiros.checked,
            valor_seguro_terceiros: Currency.parse(document.getElementById('valor_seguro_terceiros').value || '0')
        };

        // Encontrar o iframe do contrato e enviar mensagem
        try {
            const iframes = window.parent.document.querySelectorAll('iframe');
            iframes.forEach(iframe => {
                if (iframe.src && iframe.src.includes('contratos')) {
                    iframe.contentWindow.postMessage({
                        action: 'veiculoSalvo',
                        modo: modo,
                        index: index ? parseInt(index) : null,
                        dados: dados
                    }, '*');
                }
            });
        } catch (e) {
            console.error('Erro ao enviar mensagem para iframe:', e);
        }

        // Fechar offcanvas
        fecharOffcanvas();
    }

    // Inicializar quando DOM estiver pronto (apos Chosen Select)
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(init, 0);
    });
})();
</script>
@endsection
