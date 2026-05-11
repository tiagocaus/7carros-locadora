@extends('layouts.iframe')

@section('title', t('modules.nfse.emit_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.nfse.emit_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formEmitir">
        @csrf
        <input type="hidden" name="id_financeiro" id="inputIdFinanceiro">

        <!-- Dados do Prestador (readonly) -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-building mr-2"></i><?= t('modules.nfse.sections.prestador') ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-8 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.prestador') ?></label>
                    <input type="text" id="prestadorNome" class="form-input-group-field bg-slate-50" readonly>
                </div>
                <div class="md:col-span-4 form-input-group">
                    <label class="form-label-group">CNPJ</label>
                    <input type="text" id="prestadorCnpj" class="form-input-group-field bg-slate-50" readonly>
                </div>
            </div>
        </div>

        <!-- Dados do Tomador -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-user mr-2"></i><?= t('modules.nfse.sections.tomador') ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-6 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.tomador_nome') ?></label>
                    <input type="text" name="tomador_nome" id="tomadorNome" class="form-input-group-field bg-slate-50" readonly>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.tomador_cpf_cnpj') ?></label>
                    <input type="text" name="tomador_cpf_cnpj" id="tomadorCpfCnpj" class="form-input-group-field bg-slate-50" readonly>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.tomador_email') ?></label>
                    <input type="email" name="tomador_email" id="tomadorEmail" class="form-input-group-field">
                </div>
            </div>
        </div>

        <!-- Servico -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-concierge-bell mr-2"></i><?= t('modules.nfse.sections.servico') ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-12 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.descricao_servico') ?></label>
                    <textarea name="descricao_servico" id="inputDescricaoServico" class="form-input-group-field" rows="2"></textarea>
                </div>
            </div>
        </div>

        <!-- Valores -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-calculator mr-2"></i><?= t('modules.nfse.sections.valores') ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.valor_servicos') ?></label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="valorServicos" class="form-input-group-field input-moeda pl-12 bg-slate-50" readonly>
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.valor_deducoes') ?></label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" name="valor_deducoes" id="valorDeducoes" class="form-input-group-field input-moeda pl-12" value="0,00">
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.base_calculo') ?></label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="baseCalculo" class="form-input-group-field pl-12 bg-slate-50" readonly>
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="flex items-center cursor-pointer mt-6">
                        <input type="checkbox" name="iss_retido" value="S" id="inputIssRetido" class="form-checkbox h-4 w-4 text-blue-600">
                        <span class="ml-2 text-sm text-slate-700"><?= t('modules.nfse.fields.iss_retido') ?></span>
                    </label>
                </div>
            </div>

            <!-- Resumo tributos -->
            <div class="mt-4 p-3 bg-slate-50 rounded-lg">
                <div class="grid grid-cols-3 gap-4 text-sm text-center">
                    <div>
                        <span class="text-slate-500">ISS</span>
                        <div class="font-medium" id="resumoISS">R$ 0,00</div>
                    </div>
                    <div>
                        <span class="text-slate-500">IBS (0,10%)</span>
                        <div class="font-medium" id="resumoIBS">R$ 0,00</div>
                    </div>
                    <div>
                        <span class="text-slate-500">CBS (0,90%)</span>
                        <div class="font-medium" id="resumoCBS">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info ambiente -->
        <div id="avisoHomologacao" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= t('modules.nfse.messages.homologacao_aviso') ?>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" class="btn-secondary py-2 px-4 rounded-md text-sm" id="btnCancelarForm">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" id="btnEmitir">
                <i class="fas fa-paper-plane mr-2"></i><?= t('modules.nfse.buttons.emit') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    let financeiroData = null;
    let configData = null;
    let aliquotaISS = 0;

    // Pegar id_financeiro da URL
    const urlParams = new URLSearchParams(window.location.search);
    const idFinanceiro = urlParams.get('id_financeiro');

    if (!idFinanceiro) {
        window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.financeiro_required') ?>' }, '*');
        voltarParaLista();
        return;
    }

    document.getElementById('inputIdFinanceiro').value = idFinanceiro;

    // Init
    window.pageLoading.start();
    carregarDadosFinanceiro(idFinanceiro);
    setupEventListeners();

    function setupEventListeners() {
        document.getElementById('formEmitir').addEventListener('submit', emitirNfse);
        document.getElementById('btnVoltar').addEventListener('click', voltarParaLista);
        document.getElementById('btnCancelarForm').addEventListener('click', voltarParaLista);
        document.getElementById('valorDeducoes').addEventListener('change', calcularTributos);
    }

    async function carregarDadosFinanceiro(id) {
        try {
            // Carregar financeiro
            const result = await API.get(`/api/financeiro/${id}`);
            if (!result.success || !result.data) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
                voltarParaLista();
                return;
            }
            financeiroData = result.data;

            // Carregar config da filial
            const filialId = financeiroData.id_matriz_filial;
            const configResult = await API.get('/api/nfse/configuracoes', { filial: filialId });
            configData = configResult.success ? configResult.data : null;

            preencherDados();
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
        } finally {
            window.pageLoading.done();
        }
    }

    function preencherDados() {
        if (!financeiroData) return;

        // Prestador
        document.getElementById('prestadorNome').value = financeiroData.filial_nome || '';
        document.getElementById('prestadorCnpj').value = financeiroData.filial_cnpj || '';

        // Tomador
        document.getElementById('tomadorNome').value = financeiroData.cliente_nome || '';
        document.getElementById('tomadorCpfCnpj').value = financeiroData.cliente_cpf_cnpj || '';
        document.getElementById('tomadorEmail').value = financeiroData.cliente_email || '';

        // Valor
        const valorTotal = parseFloat(financeiroData.valor_total || 0);
        document.getElementById('valorServicos').value = formatarMoeda(valorTotal).replace('R$ ', '');

        // Descricao do servico (da config)
        if (configData && configData.descricao_servico) {
            document.getElementById('inputDescricaoServico').value = configData.descricao_servico;
        }

        // Aliquota ISS da config
        aliquotaISS = configData ? parseFloat(configData.aliquota_iss || 0) : 0;

        // Aviso homologacao
        if (configData && parseInt(configData.ambiente) !== 1) {
            document.getElementById('avisoHomologacao').classList.remove('hidden');
        }

        // Config cert
        if (!configData || !configData.certificado_arquivo) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.cert_required') ?>' }, '*');
        }

        calcularTributos();
    }

    function calcularTributos() {
        const valorServicos = parseFloat(financeiroData?.valor_total || 0);
        const valorDeducoes = parseFromCurrency(document.getElementById('valorDeducoes').value) || 0;
        const baseCalculo = valorServicos - valorDeducoes;

        document.getElementById('baseCalculo').value = formatarMoeda(Math.max(0, baseCalculo)).replace('R$ ', '');

        const tribISSQN = configData ? parseInt(configData.trib_issqn || 4) : 4;
        const valorISS = tribISSQN === 1 ? baseCalculo * (aliquotaISS / 100) : 0;
        const valorIBS = valorServicos * (0.10 / 100);
        const valorCBS = valorServicos * (0.90 / 100);

        document.getElementById('resumoISS').textContent = formatarMoeda(valorISS);
        document.getElementById('resumoIBS').textContent = formatarMoeda(valorIBS);
        document.getElementById('resumoCBS').textContent = formatarMoeda(valorCBS);
    }

    async function emitirNfse(e) {
        e.preventDefault();

        const btn = document.getElementById('btnEmitir');
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Emitindo...';
        btn.disabled = true;

        try {
            const dados = {
                id_financeiro: idFinanceiro,
                valor_deducoes: parseFromCurrency(document.getElementById('valorDeducoes').value) || 0,
                descricao_servico: document.getElementById('inputDescricaoServico').value,
                iss_retido: document.getElementById('inputIssRetido').checked ? 'S' : 'N',
                tomador_cpf_cnpj: document.getElementById('tomadorCpfCnpj').value,
                tomador_nome: document.getElementById('tomadorNome').value,
                tomador_email: document.getElementById('tomadorEmail').value,
            };

            const result = await API.post('/nfse/emitir', dados);

            if (result.success) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.emit_success') ?>' }, '*');
                // Navegar para visualizar
                if (result.data && result.data.id) {
                    window.parent.postMessage({ action: 'navigate', page: `/pages/nfse/${result.data.id}/visualizar` }, '*');
                } else {
                    voltarParaLista();
                }
            } else {
                const msg = result.message || '<?= t('modules.nfse.messages.emit_error') ?>';
                window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
            }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.emit_error') ?>' }, '*');
        } finally {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }

    function voltarParaLista() {
        window.parent.postMessage({ action: 'navigate', page: '/pages/nfse' }, '*');
    }
})();
</script>
@endsection
