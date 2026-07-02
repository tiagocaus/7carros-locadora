<?php
$isReservaConfirmada = ($locacao['status'] ?? '') === 'R';
$printPageTitle = $isReservaConfirmada ? t('modules.locacoes.print.reservation_title') : t('modules.locacoes.print.title');
$invoiceLabel = $isReservaConfirmada ? t('modules.locacoes.print.voucher') : t('modules.locacoes.print.invoice');
$invoiceDocumentLabel = str_replace(t('modules.locacoes.print.invoice'), $invoiceLabel, t('modules.locacoes.print.invoice_document'));
$invoiceChecklistLabel = str_replace(t('modules.locacoes.print.invoice'), $invoiceLabel, t('modules.locacoes.print.invoice_checklist'));
$invoiceChecklistDocumentLabel = str_replace(t('modules.locacoes.print.invoice'), $invoiceLabel, t('modules.locacoes.print.invoice_checklist_document'));
$plainInvoiceTipo = $isReservaConfirmada ? 'voucher' : 'fatura';
$invoiceChecklistTipo = $isReservaConfirmada ? 'voucher_checklist' : 'fatura_checklist';
?>

@extends('layouts.iframe')

@section('title', $printPageTitle)

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
        <p class="text-sm text-slate-500"><?= $isReservaConfirmada ? t('modules.locacoes.print.reservation_label') : t('modules.locacoes.print.rental_label') ?></p>
        <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($locacao['codigo']) ?></p>
        <p class="text-sm text-slate-600"><?= htmlspecialchars($locacao['cliente_nome_completo'] ?? '-') ?></p>
    </div>

    <input type="hidden" id="locacaoId" value="<?= (int) $locacao['id'] ?>">
    <input type="hidden" id="locacaoCodigo" value="<?= htmlspecialchars($locacao['codigo']) ?>">

    <!-- Opcoes de Impressao -->
    <div class="mb-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><?= t('modules.locacoes.print.print_type') ?></p>
        <div class="space-y-2" id="printOptions">
            <label class="print-option" data-tipo="<?= $plainInvoiceTipo ?>">
                <input type="radio" name="tipoImpressao" value="<?= $plainInvoiceTipo ?>" checked class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-invoice text-blue-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= $invoiceLabel ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="fatura_documento">
                <input type="radio" name="tipoImpressao" value="fatura_documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-contract text-blue-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= $invoiceDocumentLabel ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="documento">
                <input type="radio" name="tipoImpressao" value="documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-alt text-indigo-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.locacoes.print.document') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="<?= $invoiceChecklistTipo ?>">
                <input type="radio" name="tipoImpressao" value="<?= $invoiceChecklistTipo ?>" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-tasks text-green-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= $invoiceChecklistLabel ?></span>
                    <?php if ($temChecklistDigital): ?>
                    <span class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"><?= t('modules.locacoes.print.digital') ?></span>
                    <?php else: ?>
                    <span class="ml-auto text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= t('modules.locacoes.print.printed') ?></span>
                    <?php endif; ?>
                </div>
            </label>

            <label class="print-option" data-tipo="fatura_checklist_documento">
                <input type="radio" name="tipoImpressao" value="fatura_checklist_documento" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-layer-group text-purple-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= $invoiceChecklistDocumentLabel ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="documento_checklist">
                <input type="radio" name="tipoImpressao" value="documento_checklist" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-clipboard-list text-orange-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.locacoes.print.document_checklist') ?></span>
                </div>
            </label>

            <label class="print-option" data-tipo="checklist">
                <input type="radio" name="tipoImpressao" value="checklist" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-clipboard-check text-green-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.locacoes.print.checklist') ?></span>
                    <?php if ($temChecklistDigital): ?>
                    <span class="ml-auto text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"><?= t('modules.locacoes.print.digital') ?></span>
                    <?php else: ?>
                    <span class="ml-auto text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= t('modules.locacoes.print.printed') ?></span>
                    <?php endif; ?>
                </div>
            </label>

            <label class="print-option" data-tipo="recibo">
                <input type="radio" name="tipoImpressao" value="recibo" class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-receipt text-amber-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.locacoes.print.receipt') ?></span>
                </div>
            </label>
        </div>
    </div>

    <!-- Select de Documento (condicional) -->
    <div id="containerDocumento" class="mb-4 hidden">
        <div class="form-input-group">
            <label for="selectDocumento" class="form-label-group"><?= t('modules.locacoes.print.select_document') ?></label>
            <select id="selectDocumento" class="form-input-group-field">
                <option value="" disabled selected><?= t('modules.locacoes.print.select_document_placeholder') ?></option>
                <?php foreach ($documentos as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Select de Checklist (condicional) -->
    <div id="containerChecklistModelo" class="mb-4 hidden">
        <div class="form-input-group">
            <label for="selectChecklistFonte" class="form-label-group"><?= t('modules.locacoes.print.select_checklist_model') ?></label>
            <select id="selectChecklistFonte" class="form-input-group-field">
                <option value="" disabled selected><?= t('modules.locacoes.print.select_checklist_placeholder') ?></option>
                <?php if (!empty($checklistModelos)): ?>
                <optgroup label="Modelos impressos">
                <?php foreach ($checklistModelos as $modelo): ?>
                <option value="modelo:<?= (int) $modelo['id'] ?>">
                    <?= htmlspecialchars($modelo['nome']) ?><?= ($modelo['chave'] ?? '') === '0' ? ' (Sistema)' : '' ?>
                </option>
                <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($checklistsDigitais)): ?>
                <optgroup label="Checklists digitais realizados">
                <?php foreach ($checklistsDigitais as $checklist): ?>
                <?php
                    $momento = ($checklist['momento'] ?? '') === 'C' ? 'Chegada' : 'Saida';
                    $veiculoLabel = trim(implode(' ', array_filter([
                        $checklist['placa'] ?? '',
                        $checklist['marca'] ?? '',
                        $checklist['veiculo_modelo'] ?? '',
                    ])));
                    $dataLabel = !empty($checklist['data_checklist'])
                        ? format_operational_datetime($checklist['data_checklist'])
                        : '';
                    $digitalLabel = trim(implode(' - ', array_filter([$momento, $veiculoLabel, $dataLabel])));
                ?>
                <option value="digital:<?= (int) $checklist['id'] ?>"><?= htmlspecialchars($digitalLabel) ?></option>
                <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <!-- Botao Gerar PDF -->
    <button type="button" id="btnGerarPdf" class="w-full btn-blue py-3 px-4 rounded-lg text-sm font-medium flex items-center justify-center mb-4">
        <i class="fas fa-file-pdf mr-2"></i><?= t('modules.locacoes.print.generate_pdf') ?>
    </button>

    <!-- Enviar por Mensageria -->
    <?php if ($temEmail || $temWhatsapp || $temSms): ?>
    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3"><?= t('modules.locacoes.print.send_via') ?></p>
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
        <?= t('modules.locacoes.print.no_channels_available') ?>
    </div>
    <?php endif; ?>
</div>
@endsection


@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = <?= json_encode([
        'selectDocBeforePdf' => t('modules.locacoes.messages.select_doc_before_pdf'),
        'selectChecklistBeforePdf' => t('modules.locacoes.messages.select_checklist_before_pdf'),
        'recordLabel' => $isReservaConfirmada ? t('modules.locacoes.print.reservation_label') : t('modules.locacoes.print.rental_label'),
        'selectDocBeforeSend' => t('modules.locacoes.messages.select_doc_before_send'),
        'selectChecklistBeforeSend' => t('modules.locacoes.messages.select_checklist_before_send'),
        'sending' => t('modules.locacoes.messages.sending'),
        'sendSuccess' => t('modules.locacoes.messages.send_success'),
        'sendError' => t('modules.locacoes.messages.send_error'),
        'sendConnectionError' => t('modules.locacoes.messages.send_connection_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const TIPOS_COM_DOCUMENTO = ['documento', 'fatura_documento', 'fatura_checklist_documento', 'documento_checklist'];
    const TIPOS_COM_CHECKLIST = ['checklist', 'fatura_checklist', 'fatura_checklist_documento', 'documento_checklist', 'voucher_checklist'];
    const containerDoc = document.getElementById('containerDocumento');
    const containerCkModelo = document.getElementById('containerChecklistModelo');
    const selectChecklistFonte = document.getElementById('selectChecklistFonte');

    function getChecklistSelecionado() {
        const value = selectChecklistFonte.value || '';
        const [tipo, id] = value.split(':');
        return {
            tipo: tipo || '',
            id: id ? parseInt(id, 10) : 0
        };
    }

    // Atualizar visibilidade dos selects condicionais
    document.querySelectorAll('input[name="tipoImpressao"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (TIPOS_COM_DOCUMENTO.includes(this.value)) {
                containerDoc.classList.remove('hidden');
            } else {
                containerDoc.classList.add('hidden');
            }
            if (TIPOS_COM_CHECKLIST.includes(this.value)) {
                containerCkModelo.classList.remove('hidden');
            } else {
                containerCkModelo.classList.add('hidden');
            }
        });
    });

    // Gerar PDF
    document.getElementById('btnGerarPdf').addEventListener('click', function() {
        const locacaoId = document.getElementById('locacaoId').value;
        const tipo = document.querySelector('input[name="tipoImpressao"]:checked').value;
        const idDocumento = document.getElementById('selectDocumento').value;
        const checklistSelecionado = getChecklistSelecionado();
        const codigo = document.getElementById('locacaoCodigo').value;

        // Validar documento selecionado para tipos que exigem documento
        if (TIPOS_COM_DOCUMENTO.includes(tipo) && !idDocumento) {
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.selectDocBeforePdf
            }, '*');
            return;
        }

        // Validar checklist selecionado para tipos com checklist
        if (TIPOS_COM_CHECKLIST.includes(tipo) && !checklistSelecionado.id) {
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.selectChecklistBeforePdf
            }, '*');
            return;
        }

        let url = '/locacoes/' + locacaoId + '/imprimir?tipo=' + tipo;
        if (idDocumento) {
            url += '&id_documento=' + idDocumento;
        }
        if (checklistSelecionado.id && checklistSelecionado.tipo === 'modelo') {
            url += '&id_checklist_modelo=' + checklistSelecionado.id;
        }
        if (checklistSelecionado.id && checklistSelecionado.tipo === 'digital') {
            url += '&id_checklist_digital=' + checklistSelecionado.id;
        }

        // Abrir PDF no modal fullscreen
        window.parent.postMessage({
            action: 'openPrintModal',
            url: url,
            title: i18n.recordLabel + ' ' + codigo
        }, '*');

        // Fechar offcanvas
        window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
    });

    // Enviar por mensageria
    document.querySelectorAll('.btn-enviar').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const canal = this.dataset.canal;
            const locacaoId = document.getElementById('locacaoId').value;
            const tipo = document.querySelector('input[name="tipoImpressao"]:checked').value;
            const idDocumento = document.getElementById('selectDocumento').value;
            const checklistSelecionado = getChecklistSelecionado();

            // Validar documento selecionado para tipos que exigem documento
            if (TIPOS_COM_DOCUMENTO.includes(tipo) && !idDocumento) {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: i18n.selectDocBeforeSend
                }, '*');
                return;
            }

            // Validar checklist selecionado para tipos com checklist
            if (TIPOS_COM_CHECKLIST.includes(tipo) && !checklistSelecionado.id) {
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
                const data = await API.post('/locacoes/' + locacaoId + '/enviar', {
                    tipo: tipo,
                    canal: canal,
                    id_documento: idDocumento ? parseInt(idDocumento) : 0,
                    id_checklist_modelo: checklistSelecionado.tipo === 'modelo' ? checklistSelecionado.id : 0,
                    id_checklist_digital: checklistSelecionado.tipo === 'digital' ? checklistSelecionado.id : 0
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
