@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.test_sms'))

@section('content')
<div class="p-4">
    <div id="testeConexaoInfo" class="mb-4 p-3 bg-slate-50 rounded-lg">
        <p class="text-sm text-slate-600"><?= t('modules.mensageria.sms.sender_id_short') ?>: <strong id="conexaoNome"><?= t('common.labels.loading') ?></strong></p>
    </div>
    <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.sms.test_phone_label') ?> *</label>
        <input type="text" id="smsTestPhone" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.sms.test_phone_placeholder') ?>">
        <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.sms.test_phone_hint') ?></p>
    </div>
    <div class="space-y-3">
        <button type="button" id="btnTestSMS" class="w-full btn-blue py-3 px-4 rounded-md text-sm flex items-center justify-center">
            <i class="fas fa-paper-plane mr-2"></i><?= t('modules.mensageria.sms.send_test') ?>
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
        providePhone: '<?= addslashes(t("modules.mensageria.messages.provide_phone")) ?>',
        provideValidPhone: '<?= addslashes(t("modules.mensageria.messages.provide_valid_phone")) ?>',
        sending: '<?= addslashes(t("common.labels.sending")) ?>',
        smsTestSuccess: '<?= addslashes(t("modules.mensageria.messages.sms_test_success")) ?>',
        smsSent: '<?= addslashes(t("modules.mensageria.messages.sms_sent")) ?>',
        smsTestError: <?= js_t("modules.mensageria.messages.sms_test_error") ?>,
        smsTestSendError: <?= js_t("modules.mensageria.messages.sms_test_send_error") ?>,
        sendTest: '<?= addslashes(t("modules.mensageria.sms.send_test")) ?>',
    };

    const urlParams = new URLSearchParams(window.location.search);
    const conexaoId = urlParams.get('id');
    const conexaoNome = urlParams.get('nome') || 'SMS';

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

    function formatarTelefone(telefone) {
        return telefone.replace(/\D/g, '');
    }

    document.getElementById('btnTestSMS')?.addEventListener('click', async function() {
        const telefone = document.getElementById('smsTestPhone').value.trim();

        if (!telefone) {
            mostrarToast(i18n.providePhone, 'error');
            return;
        }

        const telefoneFormatado = formatarTelefone(telefone);
        if (telefoneFormatado.length < 10) {
            mostrarToast(i18n.provideValidPhone, 'error');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.sending}`;

        try {
            const result = await API.post('/sms/test', {
                sms_id: conexaoId,
                phone: telefoneFormatado
            });

            if (result.success) {
                mostrarResultado(true, i18n.smsTestSuccess);
                mostrarToast(i18n.smsSent, 'success');
            } else {
                mostrarResultado(false, result.message || i18n.smsTestError);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarResultado(false, error.message || i18n.smsTestSendError);
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-paper-plane mr-2"></i>${i18n.sendTest}`;
        }
    });
})();
</script>
@endsection
