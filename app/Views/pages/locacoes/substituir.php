@extends('layouts.iframe')

@section('title', t('modules.locacoes.substitution.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page"><?= t('modules.locacoes.substitution.title') ?></h2>
            <p class="text-sm text-slate-500 mt-1">
                <?= t('modules.locacoes.substitution.rental_label') ?> <strong><?= htmlspecialchars($locacao['codigo'] ?? '') ?></strong>
                · <?= t('modules.locacoes.substitution.client_label') ?> <strong><?= htmlspecialchars($locacao['cliente_nome_completo'] ?? $locacao['cliente_nome'] ?? '') ?></strong>
            </p>
        </div>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formSubstituicao" method="POST">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="form-section" style="margin-bottom:0;">
                <h3 class="form-section-title">
                    <i class="fas fa-arrow-down mr-2 text-red-500 text-lg"></i><?= t('modules.locacoes.substitution.vehicle_to_return') ?>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-12 form-input-group">
                        <label for="dataSubstituicao" class="form-label-group">Data da Substituição <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="dataSubstituicao" class="form-input-group-field">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.fields.plan') ?></label>
                        <input type="text" id="atualPlano" class="form-input-group-field bg-slate-50" readonly>
                    </div>
                    <div class="md:col-span-8 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.fields.vehicle') ?></label>
                        <input type="text" id="atualVeiculo" class="form-input-group-field bg-slate-50" readonly>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.substitution.odometer_initial') ?></label>
                        <input type="text" id="odometroInicial" class="form-input-group-field bg-slate-50" readonly>
                    </div>
                    <div class="md:col-span-4 form-input-group">
                        <label for="odometroEntrada" class="form-label-group">
                            <?= t('modules.locacoes.substitution.odometer_return') ?> <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="odometroEntrada" class="form-input-group-field" placeholder="0">
                    </div>
                    <div class="md:col-span-4 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.substitution.odometer_driven') ?></label>
                        <input type="text" id="odometroRodado" class="form-input-group-field bg-slate-50" readonly value="-">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-6 form-input-group">
                        <label class="form-label-group"><?= t('modules.locacoes.substitution.fuel_out') ?></label>
                        <input type="text" id="combustivelSaida" class="form-input-group-field bg-slate-50" readonly>
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <label for="combustivelEntrada" class="form-label-group"><?= t('modules.locacoes.substitution.fuel_return') ?></label>
                        <select id="combustivelEntrada" class="form-input-group-field">
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

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-12 form-input-group">
                        <label for="motivoSaida" class="form-label-group"><?= t('modules.locacoes.substitution.reason') ?></label>
                        <textarea id="motivoSaida" class="form-input-group-field" rows="3" placeholder="<?= t('modules.locacoes.substitution.reason_placeholder') ?>"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section" style="margin-bottom:0;">
                <h3 class="form-section-title">
                    <i class="fas fa-arrow-up mr-2 text-green-500 text-lg"></i><?= t('modules.locacoes.substitution.new_vehicle') ?>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoPlano" class="form-label-group"><?= t('modules.locacoes.fields.plan') ?> <span class="text-red-500">*</span></label>
                        <select id="novoPlano" class="form-input-group-field">
                            <option value="KL"><?= t('modules.locacoes.plans.km_free') ?></option>
                            <option value="KMC"><?= t('modules.locacoes.plans.km_controlled') ?></option>
                            <option value="KP"><?= t('modules.locacoes.plans.km_paid') ?></option>
                        </select>
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoGrupo" class="form-label-group"><?= t('modules.locacoes.fields.group') ?> <span class="text-red-500">*</span></label>
                        <select id="novoGrupo" class="form-input-group-field">
                            <option value=""><?= t('common.labels.select') ?></option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-12 form-input-group">
                        <label for="novoVeiculo" class="form-label-group"><?= t('modules.locacoes.fields.vehicle') ?> <span class="text-red-500">*</span></label>
                        <select id="novoVeiculo" class="form-input-group-field">
                            <option value=""><?= t('modules.locacoes.messages.select_group_first') ?></option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoOdometro" class="form-label-group"><?= t('modules.locacoes.substitution.odometer_out_new') ?></label>
                        <input type="text" id="novoOdometro" class="form-input-group-field" placeholder="0">
                    </div>
                    <div class="md:col-span-6 form-input-group">
                        <label for="novoCombustivel" class="form-label-group"><?= t('modules.locacoes.substitution.fuel_out_new') ?></label>
                        <select id="novoCombustivel" class="form-input-group-field">
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

                <div class="border-t border-slate-300 pt-4 mt-4">
                    <label class="flex items-center cursor-pointer mb-3">
                        <input type="checkbox" id="manterValores" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                        <span class="ml-2 text-sm font-medium text-slate-700"><?= t('modules.locacoes.substitution.keep_values') ?></span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group text-xs"><?= t('modules.locacoes.plans.daily_rate') ?></label>
                            <input type="text" id="diariaValor" class="form-input-group-field text-sm">
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group text-xs"><?= t('modules.locacoes.plans.km_franchise') ?></label>
                            <input type="text" id="kmFranquia" class="form-input-group-field text-sm">
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group text-xs"><?= t('modules.locacoes.plans.value_km_excess') ?></label>
                            <input type="text" id="valorKmExcedente" class="form-input-group-field text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-3">
                        <div class="md:col-span-6 form-input-group">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" id="seguroCarro" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm"><?= t('modules.locacoes.insurance.vehicle') ?></span>
                            </label>
                            <input type="text" id="valorSeguroCarro" class="form-input-group-field text-sm mt-2" placeholder="0,00">
                        </div>
                        <div class="md:col-span-6 form-input-group">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" id="seguroTerceiros" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm"><?= t('modules.locacoes.insurance.third_party') ?></span>
                            </label>
                            <input type="text" id="valorSeguroTerceiros" class="form-input-group-field text-sm mt-2" placeholder="0,00">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6 mb-4">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="button" id="btnConfirmar" class="btn-green py-2 px-6 rounded-md text-sm font-medium">
                <i class="fas fa-check mr-2"></i><?= t('modules.locacoes.substitution.confirm') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = <?= json_encode([
        'select' => t('common.labels.select'),
        'selectGroupFirst' => t('modules.locacoes.messages.select_group_first'),
        'selectVehicle' => t('modules.locacoes.messages.select_vehicle_hint'),
        'required' => t('modules.locacoes.substitution.required_fields'),
        'odometerInvalid' => t('modules.locacoes.api.return_odometer_invalid'),
        'processing' => t('common.labels.processing'),
        'confirm' => t('modules.locacoes.substitution.confirm'),
        'success' => t('modules.locacoes.api.substitution_success'),
        'error' => t('modules.locacoes.substitution.error'),
        'fuelFull' => t('modules.locacoes.fuel_levels.full'),
        'fuelReserve' => t('modules.locacoes.fuel_levels.reserve'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    const locacao = <?= json_encode($locacao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const veiculoAtual = <?= json_encode($veiculoAtivo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const locacaoId = parseInt(locacao.id, 10);
    const filialId = parseInt(locacao.id_matriz_filial_retirada || 0, 10);
    const planoLabels = {
        KL: <?= json_encode(t('modules.locacoes.plans.km_free')) ?>,
        KMC: <?= json_encode(t('modules.locacoes.plans.km_controlled')) ?>,
        KP: <?= json_encode(t('modules.locacoes.plans.km_paid')) ?>,
        DI: <?= json_encode(t('modules.locacoes.plans.km_paid')) ?>,
    };
    const fuelLabels = {
        8: i18n.fuelFull,
        7: '7/8',
        6: '3/4',
        5: '5/8',
        4: '1/2',
        3: '3/8',
        2: '1/4',
        1: '1/8',
        0: i18n.fuelReserve,
    };
    let veiculosDisponiveis = [];
    let valoresGrupo = null;

    const $ = id => document.getElementById(id);

    function parseNumber(value) {
        const raw = String(value || '').trim();
        if (!raw) return 0;
        if (raw.includes(',')) {
            return parseFloat(raw.replace(/\./g, '').replace(',', '.')) || 0;
        }
        return parseFloat(raw.replace(/[^\d.-]/g, '')) || 0;
    }

    function parseIntBr(value) {
        return parseInt(String(value || '').replace(/\D/g, ''), 10) || 0;
    }

    function formatCurrency(value) {
        return (parseFloat(value) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatKm(value) {
        return (parseInt(value, 10) || 0).toLocaleString('pt-BR');
    }

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

    function alertMessage(message) {
        window.parent.postMessage({ action: 'openAlert', message }, '*');
    }

    function voltar() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: '/pages/locacoes' }, '*');
        } else {
            window.location.href = '/pages/locacoes';
        }
    }

    function preencherAtual() {
        if ($('dataSubstituicao') && !$('dataSubstituicao').value) {
            $('dataSubstituicao').value = formatarDatetimeLocal(new Date());
        }

        const descricao = [
            veiculoAtual.veiculo_placa || '',
            [veiculoAtual.veiculo_marca || '', veiculoAtual.veiculo_modelo || ''].join(' ').trim()
        ].filter(Boolean).join(' - ');

        $('atualPlano').value = planoLabels[veiculoAtual.plano] || veiculoAtual.plano || '-';
        $('atualVeiculo').value = descricao || '-';
        $('odometroInicial').value = formatKm(veiculoAtual.odometro_saida || 0) + ' km';
        $('combustivelSaida').value = fuelLabels[veiculoAtual.combustivel_saida] || '-';
        $('novoPlano').value = veiculoAtual.plano === 'DI' ? 'KP' : (veiculoAtual.plano || 'KL');
        preencherValoresAtuais();
    }

    function preencherValoresAtuais() {
        const plano = $('novoPlano').value;
        $('diariaValor').value = formatCurrency(
            plano === 'KMC'
                ? veiculoAtual.valor_plano_km_controlado
                : (plano === 'KL' ? veiculoAtual.valor_plano_km_livre : veiculoAtual.valor_plano_km_pago)
        );
        $('kmFranquia').value = formatKm(veiculoAtual.km_franquia || 0);
        $('valorKmExcedente').value = formatCurrency(veiculoAtual.valor_km_excedente || 0);
        $('seguroCarro').checked = String(veiculoAtual.seguro_carro || '0') === '1';
        $('seguroTerceiros').checked = String(veiculoAtual.seguro_terceiros || '0') === '1';
        $('valorSeguroCarro').value = formatCurrency(veiculoAtual.valor_seguro_carro || 0);
        $('valorSeguroTerceiros').value = formatCurrency(veiculoAtual.valor_seguro_terceiros || 0);
    }

    function preencherValoresGrupo() {
        if (!valoresGrupo || $('manterValores').checked) return;

        const plano = $('novoPlano').value;
        $('diariaValor').value = formatCurrency(
            plano === 'KMC'
                ? valoresGrupo.valor_plano_km_controlado
                : (plano === 'KL' ? valoresGrupo.valor_plano_km_livre : valoresGrupo.valor_plano_km_pago)
        );
        $('kmFranquia').value = formatKm(valoresGrupo.km_franquia || 0);
        $('valorKmExcedente').value = formatCurrency(valoresGrupo.valor_km_excedente || 0);
        $('seguroCarro').checked = parseFloat(valoresGrupo.valor_seguro_carro || 0) > 0;
        $('seguroTerceiros').checked = parseFloat(valoresGrupo.valor_seguro_terceiros || 0) > 0;
        $('valorSeguroCarro').value = formatCurrency(valoresGrupo.valor_seguro_carro || 0);
        $('valorSeguroTerceiros').value = formatCurrency(valoresGrupo.valor_seguro_terceiros || 0);
    }

    function atualizarOdometroRodado() {
        const inicial = parseIntBr(veiculoAtual.odometro_saida || 0);
        const entrada = parseIntBr($('odometroEntrada').value);
        $('odometroRodado').value = entrada > 0 ? formatKm(Math.max(0, entrada - inicial)) + ' km' : '-';
    }

    async function carregarGrupos() {
        if (!filialId) return;
        const result = await API.get('/api/grupos', { id_filial: filialId });
        if (!result.success) return;

        $('novoGrupo').innerHTML = `<option value="">${i18n.select}</option>`;
        (result.data || []).forEach(grupo => {
            const disp = grupo.qtd_disponiveis !== undefined ? ` (${grupo.qtd_disponiveis} disp.)` : '';
            $('novoGrupo').add(new Option((grupo.nome || '-') + disp, grupo.id));
        });
    }

    async function carregarVeiculos(grupoId) {
        $('novoVeiculo').innerHTML = `<option value="">${i18n.selectGroupFirst}</option>`;
        if (!grupoId || !filialId) return;

        const result = await API.get('/api/veiculos/por-grupo', { id_grupo: grupoId, id_filial: filialId });
        if (!result.success) return;

        veiculosDisponiveis = result.data || [];
        $('novoVeiculo').innerHTML = `<option value="">${i18n.selectVehicle}</option>`;
        veiculosDisponiveis.forEach(veiculo => {
            $('novoVeiculo').add(new Option(`${veiculo.placa || ''} - ${veiculo.marca || ''} ${veiculo.modelo || ''}`.trim(), veiculo.id));
        });
    }

    async function carregarValoresGrupo(grupoId) {
        valoresGrupo = null;
        if (!grupoId || !filialId) return;

        const res = await API.get(`/api/grupos/${grupoId}/precos-filial/${filialId}`);
        if (res.success && res.data && res.data.valores) {
            valoresGrupo = res.data.valores;
            preencherValoresGrupo();
            return;
        }

        const fallback = await API.get(`/api/grupos/${grupoId}`);
        if (fallback.success) {
            valoresGrupo = fallback.data;
            preencherValoresGrupo();
        }
    }

    function preencherNovoVeiculo() {
        const veiculo = veiculosDisponiveis.find(v => String(v.id) === String($('novoVeiculo').value));
        if (!veiculo) return;

        $('novoOdometro').value = veiculo.odometro ? formatKm(veiculo.odometro) : '';
        $('novoCombustivel').value = veiculo.tanque_fracao || '';
    }

    async function confirmar() {
        const odometroEntrada = parseIntBr($('odometroEntrada').value);
        const odometroSaida = parseIntBr(veiculoAtual.odometro_saida || 0);
        const novoGrupo = $('novoGrupo').value;
        const novoVeiculo = $('novoVeiculo').value;
        const novoPlano = $('novoPlano').value;
        const dataSubstituicao = $('dataSubstituicao').value || '';

        if (!dataSubstituicao || !odometroEntrada || !novoGrupo || !novoVeiculo || !novoPlano) {
            alertMessage(i18n.required);
            return;
        }

        if (odometroEntrada < odometroSaida) {
            alertMessage(i18n.odometerInvalid);
            return;
        }

        const plano = novoPlano;
        const payload = {
            id_locacao_veiculo_antigo: veiculoAtual.id,
            data_entrada: dataSubstituicao,
            data_saida_novo: dataSubstituicao,
            odometro_entrada: odometroEntrada,
            combustivel_entrada: $('combustivelEntrada').value || null,
            motivo_saida: $('motivoSaida').value || null,
            id_veiculo_novo: parseInt(novoVeiculo, 10),
            id_grupo_novo: parseInt(novoGrupo, 10),
            plano_novo: plano,
            odometro_saida_novo: parseIntBr($('novoOdometro').value),
            combustivel_saida_novo: $('novoCombustivel').value || null,
            manter_valores: $('manterValores').checked ? 1 : 0,
            seguro_carro: $('seguroCarro').checked ? 1 : 0,
            seguro_terceiros: $('seguroTerceiros').checked ? 1 : 0,
            valor_plano_km_pago: plano === 'KP' ? parseNumber($('diariaValor').value) : 0,
            valor_plano_km_controlado: plano === 'KMC' ? parseNumber($('diariaValor').value) : 0,
            valor_plano_km_livre: plano === 'KL' ? parseNumber($('diariaValor').value) : 0,
            km_franquia: parseIntBr($('kmFranquia').value),
            valor_km_excedente: parseNumber($('valorKmExcedente').value),
            valor_seguro_carro: parseNumber($('valorSeguroCarro').value),
            valor_seguro_terceiros: parseNumber($('valorSeguroTerceiros').value),
        };

        const btn = $('btnConfirmar');
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.processing}`;

        try {
            const result = await API.post(`/locacoes/${locacaoId}/substituir`, payload);
            if (result.success) {
                alertMessage(result.message || i18n.success);
                setTimeout(voltar, 400);
                return;
            }
            alertMessage(result.message || i18n.error);
        } catch (error) {
            alertMessage(error.message || i18n.error);
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-check mr-2"></i>${i18n.confirm}`;
        }
    }

    $('btnVoltar').addEventListener('click', voltar);
    $('btnCancelar').addEventListener('click', voltar);
    $('btnConfirmar').addEventListener('click', confirmar);
    $('odometroEntrada').addEventListener('input', atualizarOdometroRodado);
    $('novoPlano').addEventListener('change', function() {
        $('manterValores').checked ? preencherValoresAtuais() : preencherValoresGrupo();
    });
    $('novoGrupo').addEventListener('change', async function() {
        await Promise.all([carregarVeiculos(this.value), carregarValoresGrupo(this.value)]);
    });
    $('novoVeiculo').addEventListener('change', preencherNovoVeiculo);
    $('manterValores').addEventListener('change', function() {
        this.checked ? preencherValoresAtuais() : preencherValoresGrupo();
    });

    preencherAtual();
    carregarGrupos();
})();
</script>
@endsection
