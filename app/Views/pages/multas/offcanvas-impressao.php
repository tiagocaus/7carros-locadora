@extends('layouts.iframe')

@section('title', t('modules.multas.print.title'))

@section('content')
<style>
    .print-option { display: block; }
    .print-option input:checked + div {
        background-color: #eff6ff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
    }
</style>
<?php
    $isPaga = ($multa['pago'] ?? 'N') === 'S';
    $temAit = !empty($multa['numero_ait']);
?>
<div class="p-4">
    <div class="mb-4">
        <p class="text-sm text-slate-500"><?= t('modules.multas.print.fine_label') ?></p>
        <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($multa['n_infracao'] ?? '-') ?></p>
        <p class="text-sm text-slate-600"><?= htmlspecialchars(($multa['veiculo_placa'] ?? '-') . ' - ' . ($multa['cliente_nome'] ?? '-')) ?></p>
    </div>

    <input type="hidden" id="multaId" value="<?= (int) $multa['id'] ?>">
    <input type="hidden" id="multaNumero" value="<?= htmlspecialchars($multa['n_infracao'] ?? '') ?>">

    <!-- Opcoes de Impressao -->
    <div class="mb-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><?= t('modules.multas.print.print_type') ?></p>
        <div class="space-y-2" id="printOptions">

            <label class="print-option" data-tipo="notificacao">
                <input type="radio" name="tipoImpressao" value="notificacao" checked class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-bell text-blue-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.multas.print.notification') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="documento">
                <input type="radio" name="tipoImpressao" value="documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-alt text-indigo-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.multas.print.document') ?></span>
                </div>
            </label>

            <?php if ($isPaga): ?>
            <label class="print-option" data-tipo="comprovante">
                <input type="radio" name="tipoImpressao" value="comprovante" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-receipt text-green-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.multas.print.receipt') ?></span>
                </div>
            </label>
            <?php endif; ?>

            <?php if ($temAit): ?>
            <label class="print-option" data-tipo="termo_indicacao">
                <input type="radio" name="tipoImpressao" value="termo_indicacao" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-user-shield text-orange-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.multas.print.indication_term') ?></span>
                </div>
            </label>
            <?php endif; ?>
        </div>
    </div>

    <!-- Select de Documento (condicional) -->
    <div id="containerDocumento" class="mb-4 hidden">
        <div class="form-input-group">
            <label for="selectDocumento" class="form-label-group"><?= t('modules.multas.print.select_document') ?></label>
            <select id="selectDocumento" class="form-input-group-field">
                <option value="" disabled selected><?= t('modules.multas.print.select_document_placeholder') ?></option>
                <?php foreach ($documentos as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($documentos)): ?>
            <p class="text-xs text-amber-600 mt-1"><i class="fas fa-info-circle mr-1"></i><?= t('modules.multas.print.no_documents') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botao Gerar PDF -->
    <button type="button" id="btnGerarPdf" class="w-full btn-blue py-3 px-4 rounded-lg text-sm font-medium flex items-center justify-center mb-4">
        <i class="fas fa-file-pdf mr-2"></i><?= t('modules.multas.print.generate_pdf') ?>
    </button>

    <!-- Enviar por Mensageria -->
    <?php if ($temEmail || $temWhatsapp || $temSms): ?>
    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3"><?= t('modules.multas.print.send_via') ?></p>
        <div class="flex gap-2">
            <?php if ($temEmail): ?>
            <button type="button" class="btn-enviar flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition-colors" data-canal="email">
                <i class="fas fa-envelope"></i>Email
            </button>
            <?php endif; ?>
            <?php if ($temWhatsapp): ?>
            <button type="button" class="btn-enviar flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-green-50 hover:border-green-300 hover:text-green-700 transition-colors" data-canal="whatsapp">
                <i class="fab fa-whatsapp"></i>WhatsApp
            </button>
            <?php endif; ?>
            <?php if ($temSms): ?>
            <button type="button" class="btn-enviar flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-700 transition-colors" data-canal="sms">
                <i class="fas fa-sms"></i>SMS
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = {
        selectDocBeforePdf: '<?= addslashes(t('modules.multas.messages.select_doc_before_pdf')) ?>',
        selectDocBeforeSend: '<?= addslashes(t('modules.multas.messages.select_doc_before_send')) ?>',
        fineLabel: '<?= t('modules.multas.print.fine_label') ?>',
        sending: '<?= t('modules.multas.messages.sending') ?>',
        sendSuccess: '<?= t('modules.multas.messages.send_success') ?>',
        sendError: '<?= addslashes(t('modules.multas.messages.send_error')) ?>',
        sendConnectionError: '<?= addslashes(t('modules.multas.messages.send_connection_error')) ?>',
    };

    const TIPOS_COM_DOCUMENTO = ['documento'];
    const containerDoc = document.getElementById('containerDocumento');

    document.querySelectorAll('input[name="tipoImpressao"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (TIPOS_COM_DOCUMENTO.includes(this.value)) {
                containerDoc.classList.remove('hidden');
            } else {
                containerDoc.classList.add('hidden');
            }
        });
    });

    document.getElementById('btnGerarPdf').addEventListener('click', function() {
        const multaId = document.getElementById('multaId').value;
        const tipo = document.querySelector('input[name="tipoImpressao"]:checked').value;
        const idDocumento = document.getElementById('selectDocumento')?.value || '';
        const numero = document.getElementById('multaNumero').value;

        if (TIPOS_COM_DOCUMENTO.includes(tipo) && !idDocumento) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectDocBeforePdf }, '*');
            return;
        }

        let url = '/multas/' + multaId + '/imprimir?tipo=' + tipo;
        if (idDocumento) {
            url += '&id_documento=' + idDocumento;
        }

        window.parent.postMessage({
            action: 'openPrintModal',
            url: url,
            title: i18n.fineLabel + ' ' + numero
        }, '*');

        window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
    });

    document.querySelectorAll('.btn-enviar').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const canal = this.dataset.canal;
            const multaId = document.getElementById('multaId').value;
            const tipo = document.querySelector('input[name="tipoImpressao"]:checked').value;
            const idDocumento = document.getElementById('selectDocumento')?.value || '';

            if (TIPOS_COM_DOCUMENTO.includes(tipo) && !idDocumento) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.selectDocBeforeSend }, '*');
                return;
            }

            this.disabled = true;
            const originalHtml = this.innerHTML;
            this.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${i18n.sending}`;

            try {
                const data = await API.post('/multas/' + multaId + '/enviar', {
                    tipo: tipo,
                    canal: canal,
                    id_documento: idDocumento ? parseInt(idDocumento) : 0
                });
                window.parent.postMessage({
                    action: 'openAlert',
                    message: data.message || (data.success ? i18n.sendSuccess : i18n.sendError)
                }, '*');
            } catch (e) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.sendConnectionError }, '*');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });

    if (typeof window.pageLoading !== 'undefined' && window.pageLoading.done) {
        window.pageLoading.done();
    }
});
</script>
@endsection
