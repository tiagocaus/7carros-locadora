@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.test_whatsapp'))

@section('content')
<div class="p-4">
    <div id="testeConexaoInfo" class="mb-4 p-3 bg-slate-50 rounded-lg">
        <p class="text-sm text-slate-600"><?= t('modules.mensageria.whatsapp.instance_label') ?>: <strong id="conexaoNome"><?= t('common.labels.loading') ?></strong></p>
    </div>
    <div class="space-y-3">
        <button type="button" id="btnTestText" class="w-full btn-blue py-3 px-4 rounded-md text-sm flex items-center justify-center">
            <i class="fas fa-comment mr-2"></i><?= t('modules.mensageria.whatsapp.send_text') ?>
        </button>
        <button type="button" id="btnTestImage" class="w-full btn-purple py-3 px-4 rounded-md text-sm flex items-center justify-center">
            <i class="fas fa-image mr-2"></i><?= t('modules.mensageria.whatsapp.send_image') ?>
        </button>
        <button type="button" id="btnTestDocument" class="w-full btn-yellow py-3 px-4 rounded-md text-sm flex items-center justify-center">
            <i class="fas fa-file-pdf mr-2"></i><?= t('modules.mensageria.whatsapp.send_document') ?>
        </button>
    </div>
    <div id="testeResultado" class="mt-4 hidden">
        <div class="p-3 rounded-lg text-sm"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        connectionIdMissing: '<?= addslashes(t("modules.mensageria.common.connection_id_missing")) ?>',
        sending: '<?= addslashes(t("common.labels.sending")) ?>',
        testSent: '<?= addslashes(t("modules.mensageria.messages.test_sent")) ?>',
        testSuccess: '<?= addslashes(t("modules.mensageria.messages.test_success")) ?>',
        testError: '<?= addslashes(t("modules.mensageria.messages.test_error")) ?>',
    };

    const urlParams = new URLSearchParams(window.location.search);
    const conexaoId = urlParams.get('id');
    const conexaoNome = urlParams.get('nome') || 'WhatsApp';

    document.getElementById('conexaoNome').textContent = decodeURIComponent(conexaoNome);

    if (!conexaoId) {
        mostrarToast(i18n.connectionIdMissing, 'error');
        return;
    }

    function mostrarToast(mensagem, tipo = 'info') {
        if (typeof window.toast !== 'undefined') {
            window.toast[tipo](mensagem);
        } else if (window.parent !== window) {
            window.parent.postMessage({ action: 'openAlert', message: mensagem }, '*');
        }
    }

    function mostrarResultado(sucesso, mensagem) {
        const container = document.getElementById('testeResultado');
        const div = container.querySelector('div');

        container.classList.remove('hidden');
        div.className = 'p-3 rounded-lg text-sm ' + (sucesso ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800');
        div.innerHTML = `<i class="fas fa-${sucesso ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${mensagem}`;
    }

    async function enviarTeste(tipo) {
        const btn = document.getElementById('btnTest' + tipo.charAt(0).toUpperCase() + tipo.slice(1));
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.sending}`;

        try {
            const result = await API.post(`/whatsapp/test/${tipo}`, { id: conexaoId });

            if (result.success) {
                mostrarResultado(true, result.message || i18n.testSuccess);
                mostrarToast(i18n.testSent, 'success');
            } else {
                mostrarResultado(false, result.message || i18n.testError);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarResultado(false, error.message || i18n.testError);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    document.getElementById('btnTestText')?.addEventListener('click', function() {
        enviarTeste('text');
    });

    document.getElementById('btnTestImage')?.addEventListener('click', function() {
        enviarTeste('image');
    });

    document.getElementById('btnTestDocument')?.addEventListener('click', function() {
        enviarTeste('document');
    });
})();
</script>
@endsection
