@extends('layouts.iframe')

@section('title', t('modules.nfse.cancel_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.nfse.cancel_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Resumo da NFS-e -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-file-invoice mr-2"></i><?= t('modules.nfse.sections.identification') ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.numero') ?></label>
                <span class="text-lg font-bold" id="infoNumero">-</span>
            </div>
            <div class="md:col-span-4 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.tomador_nome') ?></label>
                <span id="infoTomador">-</span>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.valor_servicos') ?></label>
                <span class="font-medium" id="infoValor">R$ 0,00</span>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.data_emissao') ?></label>
                <span id="infoData">-</span>
            </div>
        </div>
    </div>

    <!-- Aviso -->
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl mt-0.5"></i>
            <div>
                <div class="text-sm font-medium text-red-800 mb-1"><?= t('modules.nfse.cancel_title') ?></div>
                <div class="text-sm text-red-600"><?= t('modules.nfse.messages.cancel_confirm') ?></div>
            </div>
        </div>
    </div>

    <!-- Formulario -->
    <form id="formCancelar">
        @csrf
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-ban mr-2"></i><?= t('modules.nfse.fields.motivo_cancelamento') ?>
            </h3>
            <div class="grid grid-cols-1 gap-4">
                <div class="form-input-group">
                    <label class="form-label-group">
                        <?= t('modules.nfse.fields.motivo_cancelamento') ?> <span class="text-red-500">*</span>
                    </label>
                    <textarea name="motivo" id="inputMotivo" class="form-input-group-field" rows="3" minlength="15" required
                        placeholder="Informe o motivo do cancelamento (mínimo 15 caracteres)"></textarea>
                    <div class="text-xs text-slate-400 mt-1">
                        <span id="charCount">0</span>/15 caracteres mínimos
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <button type="button" class="btn-secondary py-2 px-4 rounded-md text-sm" id="btnCancelarForm">
                <?= t('common.buttons.back') ?>
            </button>
            <button type="submit" class="btn-red py-2 px-4 rounded-md text-sm font-medium" id="btnConfirmar" disabled>
                <i class="fas fa-ban mr-2"></i><?= t('modules.nfse.buttons.cancel_nfse') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const nfseId = extrairNfseIdDaUrl();

    if (!nfseId) {
        window.parent.postMessage({ action: 'openAlert', message: 'ID da NFS-e nao identificado na URL.' }, '*');
        voltarParaLista();
        return;
    }

    const inputMotivo = document.getElementById('inputMotivo');
    const charCount = document.getElementById('charCount');
    const btnConfirmar = document.getElementById('btnConfirmar');

    window.pageLoading.start();
    carregarNfse(nfseId);

    // Contador de caracteres
    inputMotivo.addEventListener('input', () => {
        const len = inputMotivo.value.trim().length;
        charCount.textContent = len;
        charCount.className = len >= 15 ? 'text-green-600' : 'text-red-500';
        btnConfirmar.disabled = len < 15;
    });

    document.getElementById('formCancelar').addEventListener('submit', cancelarNfse);
    document.getElementById('btnVoltar').addEventListener('click', voltarParaVisualizacao);
    document.getElementById('btnCancelarForm').addEventListener('click', voltarParaVisualizacao);

    function extrairNfseIdDaUrl() {
        const match = window.location.pathname.match(/\/pages\/nfse\/(\d+)\/cancelar\/?$/);
        return match ? match[1] : null;
    }

    function mensagemErroCarregamento(resultOrError) {
        const mensagem = resultOrError?.message || resultOrError?.mensagem || '';
        return mensagem || '<?= t('modules.nfse.messages.load_error') ?>';
    }

    function formatarMoeda(valor) {
        const numero = Number(valor) || 0;
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(numero);
    }

    async function carregarNfse(id) {
        try {
            const result = await API.get(`/api/nfse/${id}`);
            if (!result.success || !result.data) {
                window.parent.postMessage({ action: 'openAlert', message: mensagemErroCarregamento(result) }, '*');
                voltarParaLista();
                return;
            }

            const n = result.data;

            if (n.status !== 'autorizada') {
                window.parent.postMessage({ action: 'openAlert', message: 'Somente NFS-e autorizadas podem ser canceladas.' }, '*');
                voltarParaLista();
                return;
            }

            document.getElementById('infoNumero').textContent = n.numero || '-';
            document.getElementById('infoTomador').textContent = n.tomador_nome || '-';
            document.getElementById('infoValor').textContent = formatarMoeda(parseFloat(n.valor_servicos || 0));
            document.getElementById('infoData').textContent = n.created_at ? n.created_at.substring(0, 10).split('-').reverse().join('/') : '-';
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: mensagemErroCarregamento(e) }, '*');
        } finally {
            window.pageLoading.done();
        }
    }

    async function cancelarNfse(e) {
        e.preventDefault();

        const motivo = inputMotivo.value.trim();
        if (motivo.length < 15) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.cancel_motivo_min') ?>' }, '*');
            return;
        }

        const btn = btnConfirmar;
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Cancelando...';
        btn.disabled = true;

        try {
            const result = await API.post(`/nfse/${nfseId}/cancelar`, { motivo: motivo });

            if (result.success) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.cancel_success') ?>' }, '*');
                window.parent.postMessage({ action: 'navigate', page: `/pages/nfse/${nfseId}/visualizar` }, '*');
            } else {
                const msg = result.message || <?= js_t('modules.nfse.messages.cancel_error') ?>;
                window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
            }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.cancel_error') ?> }, '*');
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }

    function voltarParaVisualizacao() {
        window.parent.postMessage({ action: 'navigate', page: `/pages/nfse/${nfseId}/visualizar` }, '*');
    }

    function voltarParaLista() {
        window.parent.postMessage({ action: 'navigate', page: '/pages/nfse' }, '*');
    }
})();
</script>
@endsection
