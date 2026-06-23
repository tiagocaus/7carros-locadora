@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.test_smtp'))

@section('content')
<div class="p-4">
    <div id="testeConexaoInfo" class="mb-4 p-3 bg-slate-50 rounded-lg">
        <p class="text-sm text-slate-600"><?= t('modules.mensageria.common.connection') ?>: <strong id="conexaoNome"><?= t('common.labels.loading') ?></strong></p>
    </div>
    <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.mensageria.smtp.test_email_label') ?> *</label>
        <input type="email" id="smtpTestEmail" class="form-input-focus w-full" placeholder="<?= t('modules.mensageria.smtp_placeholders.auth_email') ?>">
        <p class="text-xs text-slate-500 mt-1"><?= t('modules.mensageria.smtp.test_email_hint') ?></p>
    </div>
    <div class="space-y-3">
        <button type="button" id="btnTestSMTP" class="w-full btn-purple py-3 px-4 rounded-md text-sm flex items-center justify-center">
            <i class="fas fa-paper-plane mr-2"></i><?= t('modules.mensageria.smtp.send_test') ?>
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
        provideEmail: <?= js_t("modules.mensageria.messages.provide_email") ?>,
        provideValidEmail: <?= js_t("modules.mensageria.messages.provide_valid_email") ?>,
        sending: '<?= addslashes(t("common.labels.sending")) ?>',
        emailTestSuccess: '<?= addslashes(t("modules.mensageria.messages.email_test_success")) ?>',
        emailSent: '<?= addslashes(t("modules.mensageria.messages.email_sent")) ?>',
        emailTestError: <?= js_t("modules.mensageria.messages.email_test_error") ?>,
        emailTestSendError: <?= js_t("modules.mensageria.messages.email_test_send_error") ?>,
        sendTest: '<?= addslashes(t("modules.mensageria.smtp.send_test")) ?>',
    };

    const urlParams = new URLSearchParams(window.location.search);
    const conexaoId = urlParams.get('id');
    const conexaoNome = urlParams.get('nome') || 'SMTP';

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

    document.getElementById('btnTestSMTP')?.addEventListener('click', async function() {
        const email = document.getElementById('smtpTestEmail').value.trim();

        if (!email) {
            mostrarToast(i18n.provideEmail, 'error');
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            mostrarToast(i18n.provideValidEmail, 'error');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.sending}`;

        try {
            const result = await API.post('/smtp/test', {
                smtp_id: conexaoId,
                email: email
            });

            if (result.success) {
                mostrarResultado(true, i18n.emailTestSuccess);
                mostrarToast(i18n.emailSent, 'success');
            } else {
                mostrarResultado(false, result.message || i18n.emailTestError);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarResultado(false, error.message || i18n.emailTestSendError);
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-paper-plane mr-2"></i>${i18n.sendTest}`;
        }
    });
})();
</script>
@endsection
