@extends('layouts.iframe')

@section('title', t('modules.mensageria.offcanvas.connect_whatsapp'))

@section('content')
<div class="p-4">
    <div class="text-center">
        <div id="qrcodeContainer" class="mb-4">
            <div class="flex items-center justify-center h-64">
                <i class="fas fa-spinner fa-spin fa-3x text-slate-400"></i>
            </div>
        </div>
        <p class="text-sm text-slate-600 mb-4"><?= t('modules.mensageria.messages.qr_scan') ?></p>
        <div id="statusConexao" class="text-sm font-medium">
            <span class="text-amber-600"><i class="fas fa-clock mr-1"></i><?= t('modules.mensageria.messages.qr_generating') ?></span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        connectionIdMissing: '<?= addslashes(t("modules.mensageria.common.connection_id_missing")) ?>',
        qrConnected: '<?= addslashes(t("modules.mensageria.messages.qr_connected")) ?>',
        qrWaiting: '<?= addslashes(t("modules.mensageria.messages.qr_waiting")) ?>',
        qrError: '<?= addslashes(t("modules.mensageria.messages.qr_error")) ?>',
        qrConnectError: '<?= addslashes(t("modules.mensageria.messages.qr_connect_error")) ?>',
    };

    const urlParams = new URLSearchParams(window.location.search);
    const conexaoId = urlParams.get('id');

    if (!conexaoId) {
        mostrarToast(i18n.connectionIdMissing, 'error');
        return;
    }

    let pollingInterval = null;

    function mostrarToast(mensagem, tipo = 'info') {
        if (typeof window.toast !== 'undefined') {
            window.toast[tipo](mensagem);
        } else if (window.parent !== window) {
            window.parent.postMessage({ action: 'openAlert', message: mensagem }, '*');
        }
    }

    function atualizarQRCode(base64, erro = null) {
        const container = document.getElementById('qrcodeContainer');

        if (erro) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-64 text-red-500">
                    <i class="fas fa-exclamation-triangle fa-3x mb-4"></i>
                    <p>${escapeHtml(erro)}</p>
                </div>
            `;
        } else {
            let src = base64;
            if (!base64.startsWith('data:')) {
                src = 'data:image/png;base64,' + base64;
            }
            container.innerHTML = `<img src="${src}" alt="QR Code" class="mx-auto max-w-full h-64">`;
        }
    }

    function atualizarStatus(status, mensagem) {
        const statusEl = document.getElementById('statusConexao');

        if (status === 'connected') {
            statusEl.innerHTML = `<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>${i18n.qrConnected}</span>`;
        } else if (status === 'connecting') {
            statusEl.innerHTML = `<span class="text-amber-600"><i class="fas fa-clock mr-1"></i>${i18n.qrWaiting}</span>`;
        } else {
            statusEl.innerHTML = `<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>${escapeHtml(mensagem || 'Erro')}</span>`;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function pararPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    function iniciarPolling() {
        pararPolling();
        pollingInterval = setInterval(async () => {
            try {
                const result = await API.get(`/api/whatsapp/${conexaoId}/status`);
                if (result.success) {
                    if (result.data.status === 'connected') {
                        atualizarStatus('connected');
                        pararPolling();
                        setTimeout(() => {
                            window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                            window.parent.postMessage({ action: 'reloadMensageriaData' }, '*');
                        }, 1500);
                    } else if (result.data.status === 'connecting') {
                        atualizarStatus('connecting');
                    }
                }
            } catch (error) {
                console.error('Erro no polling:', error);
            }
        }, 3000);
    }

    async function conectar() {
        try {
            const result = await API.post(`/whatsapp/${conexaoId}/connect`);

            if (result.success && result.data.qrcode) {
                atualizarQRCode(result.data.qrcode);
                iniciarPolling();
            } else {
                atualizarQRCode(null, result.message || i18n.qrError);
            }
        } catch (error) {
            atualizarQRCode(null, error.message || i18n.qrConnectError);
        }
    }

    // Limpar polling ao fechar
    window.addEventListener('beforeunload', pararPolling);

    // Iniciar conexao
    setTimeout(conectar, 300);
})();
</script>
@endsection
