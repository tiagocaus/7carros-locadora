@extends('layouts.iframe')

@section('title', t('modules.programa_indicacao.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.programa_indicacao.title') ?></h2>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-users text-4xl text-blue-500"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-700 mb-2"><?= t('modules.programa_indicacao.your_code') ?></h3>
                <p class="text-sm text-slate-500 mb-6"><?= t('modules.programa_indicacao.share_code') ?></p>

                <div class="bg-slate-50 border-2 border-dashed border-slate-300 rounded-lg p-6 mb-6">
                    <div id="codigoContainer" class="flex items-center justify-center">
                        <span id="codigoLoading" class="text-slate-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i><?= t('common.labels.loading') ?>
                        </span>
                        <a id="codigoLink" href="#" target="_blank" class="text-lg font-semibold text-blue-600 hover:text-blue-800 hover:underline hidden break-all"></a>
                    </div>
                </div>

                <button id="btnCopiar" class="btn-blue px-6 py-2" disabled>
                    <i class="fas fa-copy mr-2"></i>
                    <span id="btnCopiarTexto"><?= t('modules.programa_indicacao.copy_link') ?></span>
                </button>
            </div>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h4 class="text-md font-semibold text-slate-700 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                <?= t('modules.programa_indicacao.how_it_works') ?>
            </h4>
            <ul class="space-y-3 text-sm text-slate-600">
                <li class="flex items-start">
                    <span class="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5">1</span>
                    <span><?= t('modules.programa_indicacao.step_1') ?></span>
                </li>
                <li class="flex items-start">
                    <span class="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5">2</span>
                    <span><?= t('modules.programa_indicacao.step_2') ?></span>
                </li>
                <li class="flex items-start">
                    <span class="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5">3</span>
                    <span><?= t('modules.programa_indicacao.step_3') ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        copyLink: <?= json_encode(t('modules.programa_indicacao.copy_link'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        copied: <?= json_encode(t('modules.programa_indicacao.copied'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadError: <?= json_encode(t('modules.programa_indicacao.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    window.pageLoading.start();

    const BASE_URL = 'https://www.7carros.com.br/i/';
    const codigoLoading = document.getElementById('codigoLoading');
    const codigoLink = document.getElementById('codigoLink');
    const btnCopiar = document.getElementById('btnCopiar');
    const btnCopiarTexto = document.getElementById('btnCopiarTexto');
    let linkCompleto = '';

    async function carregarCodigo() {
        try {
            const result = await API.get('/api/programa-indicacao/codigo');

            if (result.success) {
                linkCompleto = BASE_URL + result.data.codigo;
                codigoLink.textContent = linkCompleto;
                codigoLink.href = linkCompleto;
                codigoLoading.classList.add('hidden');
                codigoLink.classList.remove('hidden');
                btnCopiar.disabled = false;
            } else {
                codigoLoading.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>' + i18n.loadError;
            }
        } catch (error) {
            console.error('Erro:', error);
            codigoLoading.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>' + i18n.loadError;
        } finally {
            window.pageLoading.done();
        }
    }

    async function copiarLink() {
        if (!linkCompleto) return;

        try {
            await navigator.clipboard.writeText(linkCompleto);

            btnCopiarTexto.textContent = i18n.copied;
            btnCopiar.classList.remove('btn-blue');
            btnCopiar.classList.add('btn-green');

            setTimeout(() => {
                btnCopiarTexto.textContent = i18n.copyLink;
                btnCopiar.classList.remove('btn-green');
                btnCopiar.classList.add('btn-blue');
            }, 2000);
        } catch (error) {
            console.error('Erro ao copiar:', error);

            const input = document.createElement('input');
            input.value = linkCompleto;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);

            btnCopiarTexto.textContent = i18n.copied;
            setTimeout(() => {
                btnCopiarTexto.textContent = i18n.copyLink;
            }, 2000);
        }
    }

    btnCopiar.addEventListener('click', copiarLink);

    carregarCodigo();
})();
</script>
@endsection
