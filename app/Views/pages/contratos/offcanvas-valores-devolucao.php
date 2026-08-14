@extends('layouts.iframe')

@section('title', t('modules.contratos.return_page.adjust_values_title'))

@section('content')
<div class="p-4">
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-md p-3 text-sm mb-5">
        <i class="fas fa-info-circle mr-1"></i><?= t('modules.contratos.return_page.adjust_values_notice') ?>
    </div>

    <div class="mb-5">
        <h4 id="veiculoTitulo" class="font-semibold text-slate-800"></h4>
        <p class="text-sm text-slate-500 mt-1">
            <?= t('modules.contratos.return_page.plan') ?>: <strong id="planoLabel">-</strong>
        </p>
    </div>

    <form id="formValoresDevolucao">
        <div class="form-input-group mb-4">
            <label for="valor_plano" class="form-label-group"><?= t('modules.contratos.return_page.plan_value') ?></label>
            <div class="relative">
                <span class="currency-symbol absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">R$</span>
                <input type="text" id="valor_plano" class="form-input-group-field pl-10 input-moeda" required>
            </div>
        </div>

        <div id="camposKmControlado" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div class="form-input-group">
                <label for="km_franquia" class="form-label-group"><?= t('modules.contratos.return_page.km_franchise') ?></label>
                <div class="relative">
                    <input type="text" id="km_franquia" class="form-input-group-field pr-10 input-km">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">km</span>
                </div>
            </div>
            <div class="form-input-group">
                <label for="valor_km_excedente" class="form-label-group"><?= t('modules.contratos.return_page.value_per_km') ?></label>
                <div class="relative">
                    <span class="currency-symbol absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">R$</span>
                    <input type="text" id="valor_km_excedente" class="form-input-group-field pl-10 input-moeda">
                </div>
            </div>
        </div>

        <div id="camposKmPago" class="hidden form-input-group mb-4">
            <label for="valor_km_pago" class="form-label-group"><?= t('modules.contratos.return_page.paid_km_value') ?></label>
            <div class="relative">
                <span class="currency-symbol absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">R$</span>
                <input type="text" id="valor_km_pago" class="form-input-group-field pl-10 input-moeda">
            </div>
        </div>

        <div class="border-t border-slate-200 pt-4 mt-5 space-y-4">
            <div class="rounded-md border border-slate-200 p-3">
                <label class="flex items-center cursor-pointer mb-3">
                    <input type="checkbox" id="seguro_carro" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                    <span class="ml-2 text-sm font-medium text-slate-700"><?= t('modules.contratos.vehicles.vehicle_insurance') ?></span>
                </label>
                <div class="form-input-group">
                    <label for="valor_seguro_carro" class="form-label-group"><?= t('modules.contratos.vehicles.value_vehicle_insurance') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_seguro_carro" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>
            </div>

            <div class="rounded-md border border-slate-200 p-3">
                <label class="flex items-center cursor-pointer mb-3">
                    <input type="checkbox" id="seguro_terceiros" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                    <span class="ml-2 text-sm font-medium text-slate-700"><?= t('modules.contratos.vehicles.third_party_insurance') ?></span>
                </label>
                <div class="form-input-group">
                    <label for="valor_seguro_terceiros" class="form-label-group"><?= t('modules.contratos.vehicles.value_third_party_insurance') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor_seguro_terceiros" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-200">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                <i class="fas fa-check mr-2"></i><?= t('modules.contratos.return_page.apply_values') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const planLabels = <?= json_encode([
        'KP' => t('modules.contratos.vehicles.plan_km_paid'),
        'KMC' => t('modules.contratos.vehicles.plan_km_controlled'),
        'KL' => t('modules.contratos.vehicles.plan_km_free'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const invalidValuesMessage = <?= json_encode(t('modules.contratos.return_page.invalid_adjusted_values'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    let dados = null;

    try {
        dados = JSON.parse(new URLSearchParams(window.location.search).get('dados') || 'null');
    } catch (error) {
        console.error('Erro ao carregar valores da devolucao:', error);
    }

    function fechar() {
        window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
    }

    function enviarParaTelaDevolucao(valores) {
        try {
            window.parent.document.querySelectorAll('iframe').forEach(iframe => {
                if (iframe.src && iframe.src.includes('/pages/contratos/devolver/')) {
                    iframe.contentWindow.postMessage({
                        action: 'valoresDevolucaoAplicados',
                        index: dados.index,
                        valores
                    }, '*');
                }
            });
        } catch (error) {
            console.error('Erro ao aplicar valores na tela de devolucao:', error);
        }
    }

    function numeroValido(valor) {
        return Number.isFinite(valor) && valor >= 0;
    }

    function init() {
        if (!dados || !dados.valores || !['KP', 'KMC', 'KL'].includes(dados.plano)) {
            window.parent.postMessage({ action: 'openAlert', message: invalidValuesMessage }, '*');
            fechar();
            return;
        }

        document.getElementById('veiculoTitulo').textContent = [dados.placa, dados.modelo].filter(Boolean).join(' · ');
        document.getElementById('planoLabel').textContent = planLabels[dados.plano] || dados.plano;
        Currency.setValue('#valor_plano', Number(dados.valores.valor_plano || 0));
        Currency.setValue('#valor_km_excedente', Number(dados.valores.valor_km_excedente || 0));
        Currency.setValue('#valor_km_pago', Number(dados.valores.valor_km_excedente || 0));
        document.getElementById('km_franquia').value = Km.format(Number.parseInt(dados.valores.km_franquia, 10) || 0);
        document.getElementById('seguro_carro').checked = Boolean(dados.valores.seguro_carro);
        Currency.setValue('#valor_seguro_carro', Number(dados.valores.valor_seguro_carro || 0));
        document.getElementById('seguro_terceiros').checked = Boolean(dados.valores.seguro_terceiros);
        Currency.setValue('#valor_seguro_terceiros', Number(dados.valores.valor_seguro_terceiros || 0));

        document.getElementById('camposKmControlado').classList.toggle('hidden', dados.plano !== 'KMC');
        document.getElementById('camposKmPago').classList.toggle('hidden', dados.plano !== 'KP');
        Currency.applyMaskToAll('input-moeda');
        Km.applyMaskToAll('input-km');
    }

    document.getElementById('btnCancelar').addEventListener('click', fechar);
    document.getElementById('formValoresDevolucao').addEventListener('submit', function(event) {
        event.preventDefault();

        const valores = {
            valor_plano: Currency.getValue('#valor_plano'),
            km_franquia: Km.parse(document.getElementById('km_franquia').value || '0'),
            valor_km_excedente: dados.plano === 'KP'
                ? Currency.getValue('#valor_km_pago')
                : Currency.getValue('#valor_km_excedente'),
            seguro_carro: document.getElementById('seguro_carro').checked,
            valor_seguro_carro: Currency.getValue('#valor_seguro_carro'),
            seguro_terceiros: document.getElementById('seguro_terceiros').checked,
            valor_seguro_terceiros: Currency.getValue('#valor_seguro_terceiros'),
        };

        const numericos = [valores.valor_plano, valores.valor_seguro_carro, valores.valor_seguro_terceiros];
        if (dados.plano !== 'KL') numericos.push(valores.valor_km_excedente);
        if (dados.plano === 'KMC') numericos.push(valores.km_franquia);
        if (!numericos.every(numeroValido)) {
            window.parent.postMessage({ action: 'openAlert', message: invalidValuesMessage }, '*');
            return;
        }

        enviarParaTelaDevolucao(valores);
        fechar();
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(init, 0);
    });
})();
</script>
@endsection
