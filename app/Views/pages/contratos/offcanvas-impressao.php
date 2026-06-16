@extends('layouts.iframe')

@section('title', t('modules.contratos.print.title'))

@section('content')
<style>
    .print-option {
        display: block;
    }
    .print-option input:checked + div {
        background-color: #eff6ff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
    }
</style>
<div class="p-4">
    <div class="mb-4">
        <p class="text-sm text-slate-500"><?= t('modules.contratos.print.contract_label') ?></p>
        <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($contrato['codigo']) ?></p>
        <p class="text-sm text-slate-600"><?= htmlspecialchars($contrato['cliente_nome'] ?? '-') ?></p>
    </div>

    <input type="hidden" id="contratoId" value="<?= (int) $contrato['id'] ?>">
    <input type="hidden" id="contratoCodigo" value="<?= htmlspecialchars($contrato['codigo']) ?>">

    <!-- Opcoes de Impressao -->
    <div class="mb-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><?= t('modules.contratos.print.print_type') ?></p>
        <div class="space-y-2" id="printOptions">
            <!-- Grupo Fatura -->
            <label class="print-option" data-tipo="fatura">
                <input type="radio" name="tipoImpressao" value="fatura" checked class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-invoice text-blue-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.invoice') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="fatura_documento">
                <input type="radio" name="tipoImpressao" value="fatura_documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-contract text-blue-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.invoice_document') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="documento">
                <input type="radio" name="tipoImpressao" value="documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-alt text-indigo-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.document') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="fatura_checklist">
                <input type="radio" name="tipoImpressao" value="fatura_checklist" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-tasks text-green-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.invoice_checklist') ?></span>
                    <?php if ($temChecklistDigital): ?>
                    <span class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"><?= t('modules.contratos.print.digital') ?></span>
                    <?php else: ?>
                    <span class="ml-auto text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= t('modules.contratos.print.printed') ?></span>
                    <?php endif; ?>
                </div>
            </label>

            <label class="print-option" data-tipo="fatura_checklist_documento">
                <input type="radio" name="tipoImpressao" value="fatura_checklist_documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-layer-group text-purple-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.invoice_checklist_document') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="documento_checklist">
                <input type="radio" name="tipoImpressao" value="documento_checklist" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-clipboard-list text-orange-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.document_checklist') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="checklist">
                <input type="radio" name="tipoImpressao" value="checklist" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-clipboard-check text-green-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.checklist') ?></span>
                    <?php if ($temChecklistDigital): ?>
                    <span class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"><?= t('modules.contratos.print.digital') ?></span>
                    <?php else: ?>
                    <span class="ml-auto text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= t('modules.contratos.print.printed') ?></span>
                    <?php endif; ?>
                </div>
            </label>

            <label class="print-option" data-tipo="recibo">
                <input type="radio" name="tipoImpressao" value="recibo" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-receipt text-amber-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.contratos.print.receipt') ?></span>
                </div>
            </label>
        </div>
    </div>

    <!-- Select de Documento (condicional) -->
    <div id="containerDocumento" class="mb-4 hidden">
        <div class="form-input-group">
            <label for="selectDocumento" class="form-label-group"><?= t('modules.contratos.print.select_document') ?></label>
            <select id="selectDocumento" class="form-input-group-field">
                <option value="" disabled selected><?= t('modules.contratos.print.select_document_placeholder') ?></option>
                <?php foreach ($documentos as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Select de Modelo Checklist Impresso (condicional) -->
    <div id="containerChecklistModelo" class="mb-4 hidden">
        <div class="form-input-group">
            <label for="selectChecklistModelo" class="form-label-group"><?= t('modules.contratos.print.select_checklist_model') ?></label>
            <select id="selectChecklistModelo" class="form-input-group-field">
                <option value="" disabled selected><?= t('modules.contratos.print.select_checklist_placeholder') ?></option>
                <?php foreach ($checklistModelos as $modelo): ?>
                <option value="<?= (int) $modelo['id'] ?>"><?= htmlspecialchars($modelo['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Botao Gerar PDF -->
    <button type="button" id="btnGerarPdf" class="w-full btn-blue py-3 px-4 rounded-lg text-sm font-medium flex items-center justify-center mb-4">
        <i class="fas fa-file-pdf mr-2"></i><?= t('modules.contratos.print.generate_pdf') ?>
    </button>

    <!-- Enviar por Mensageria -->
    <?php if ($temEmail || $temWhatsapp || $temSms): ?>
    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3"><?= t('modules.contratos.print.send_via') ?></p>
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
    <?php else: ?>
    <div class="border-t border-slate-200 pt-4 text-xs text-slate-500">
        <?= t('modules.contratos.print.no_channels_available') ?>
    </div>
    <?php endif; ?>
</div>
@endsection


@section('scripts')
<?php
$jsText = static fn(string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$jsT = static fn(string $key, array $replace = []): string => $jsText(t($key, $replace));
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = <?= json_encode([
        'selectDocBeforePdf' => t('modules.contratos.messages.select_doc_before_pdf'),
        'selectChecklistBeforePdf' => t('modules.contratos.messages.select_checklist_before_pdf'),
        'contractLabel' => t('modules.contratos.print.contract_label'),
        'selectDocBeforeSend' => t('modules.contratos.messages.select_doc_before_send'),
        'selectChecklistBeforeSend' => t('modules.contratos.messages.select_checklist_before_send'),
        'sending' => t('modules.contratos.messages.sending'),
        'sendSuccess' => t('modules.contratos.messages.send_success'),
        'sendError' => t('modules.contratos.messages.send_error'),
        'sendConnectionError' => t('modules.contratos.messages.send_connection_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const TIPOS_COM_DOCUMENTO = ['documento', 'fatura_documento', 'fatura_checklist_documento', 'documento_checklist'];
    const TIPOS_COM_CHECKLIST = ['checklist', 'fatura_checklist', 'fatura_checklist_documento', 'documento_checklist'];
    const containerDoc = document.getElementById('containerDocumento');
    const containerCkModelo = document.getElementById('containerChecklistModelo');
    const temChecklistDigital = <?= $temChecklistDigital ? 'true' : 'false' ?>;

    // Atualizar visibilidade dos selects condicionais
    document.querySelectorAll('input[name="tipoImpressao"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (TIPOS_COM_DOCUMENTO.includes(this.value)) {
                containerDoc.classList.remove('hidden');
            } else {
                containerDoc.classList.add('hidden');
            }
            if (TIPOS_COM_CHECKLIST.includes(this.value) && !temChecklistDigital) {
                containerCkModelo.classList.remove('hidden');
            } else {
                containerCkModelo.classList.add('hidden');
            }
        });
    });

    // Gerar PDF
    document.getElementById('btnGerarPdf').addEventListener('click', function() {
        const contratoId = document.getElementById('contratoId').value;
        const tipo = document.querySelector('input[name="tipoImpressao"]:checked').value;
        const idDocumento = document.getElementById('selectDocumento').value;
        const idChecklistModelo = document.getElementById('selectChecklistModelo').value;
        const codigo = document.getElementById('contratoCodigo').value;

        // Validar documento selecionado para tipos que exigem documento
        if (TIPOS_COM_DOCUMENTO.includes(tipo) && !idDocumento) {
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.selectDocBeforePdf
            }, '*');
            return;
        }

        // Validar modelo de checklist para tipos impresso
        if (TIPOS_COM_CHECKLIST.includes(tipo) && !temChecklistDigital && !idChecklistModelo) {
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.selectChecklistBeforePdf
            }, '*');
            return;
        }

        let url = '/contratos/' + contratoId + '/imprimir?tipo=' + tipo;
        if (idDocumento) {
            url += '&id_documento=' + idDocumento;
        }
        if (idChecklistModelo) {
            url += '&id_checklist_modelo=' + idChecklistModelo;
        }

        // Abrir PDF no modal fullscreen
        window.parent.postMessage({
            action: 'openPrintModal',
            url: url,
            title: i18n.contractLabel + ' ' + codigo
        }, '*');

        // Fechar offcanvas
        window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
    });

    // Enviar por mensageria
    document.querySelectorAll('.btn-enviar').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const canal = this.dataset.canal;
            const contratoId = document.getElementById('contratoId').value;
            const tipo = document.querySelector('input[name="tipoImpressao"]:checked').value;
            const idDocumento = document.getElementById('selectDocumento').value;
            const idChecklistModelo = document.getElementById('selectChecklistModelo').value;

            // Validar documento selecionado para tipos que exigem documento
            if (TIPOS_COM_DOCUMENTO.includes(tipo) && !idDocumento) {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: i18n.selectDocBeforeSend
                }, '*');
                return;
            }

            // Validar modelo de checklist para tipos impresso
            if (TIPOS_COM_CHECKLIST.includes(tipo) && !temChecklistDigital && !idChecklistModelo) {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: i18n.selectChecklistBeforeSend
                }, '*');
                return;
            }

            // Desabilitar botao
            this.disabled = true;
            const originalHtml = this.innerHTML;
            this.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${i18n.sending}`;

            try {
                const data = await API.post('/contratos/' + contratoId + '/enviar', {
                    tipo: tipo,
                    canal: canal,
                    id_documento: idDocumento ? parseInt(idDocumento) : 0,
                    id_checklist_modelo: idChecklistModelo ? parseInt(idChecklistModelo) : 0
                });
                window.parent.postMessage({
                    action: 'openAlert',
                    message: data.message || (data.success ? i18n.sendSuccess : i18n.sendError)
                }, '*');
            } catch (e) {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: i18n.sendConnectionError
                }, '*');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });

    // Sinalizar que a pagina carregou
    if (typeof window.pageLoading !== 'undefined' && window.pageLoading.done) {
        window.pageLoading.done();
    }
});
</script>
@endsection
