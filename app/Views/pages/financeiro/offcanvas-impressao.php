@extends('layouts.iframe')

@section('title', t('modules.financeiro.print.title'))

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
    $valorFmt = 'R$ ' . number_format((float) ($lancamento['valor_total'] ?? 0), 2, ',', '.');
    $venciFmt = !empty($lancamento['data_venci']) ? format_date($lancamento['data_venci']) : '-';
    $codigoLabel = $lancamento['codigo'] ?? ('#' . ($lancamento['id'] ?? ''));
    $tipoReceita = $tipoReceita ?? (($lancamento['tipo'] ?? '') === 'R');
    $contraparte = $contraparte ?? ($tipoReceita ? ($cliente ?? []) : ($fornecedor ?? []));
    $contraparteNome = $contraparte['nome_rsocial'] ?? ($tipoReceita ? ($lancamento['cliente_nome'] ?? '-') : ($lancamento['fornecedor_nome'] ?? '-'));
?>
<div class="p-4">
    <div class="mb-4">
        <p class="text-sm text-slate-500"><?= t('modules.financeiro.print.entry_label') ?></p>
        <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($codigoLabel) ?></p>
        <p class="text-sm text-slate-600"><?= htmlspecialchars($lancamento['descricao'] ?? '-') ?></p>
        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($contraparteNome) ?></p>
        <p class="text-sm text-slate-500"><?= t('modules.financeiro.print.value_label') ?>: <span class="font-medium text-slate-700"><?= $valorFmt ?></span></p>
        <p class="text-sm text-slate-500"><?= t('modules.financeiro.print.due_label') ?>: <span class="font-medium text-slate-700"><?= $venciFmt ?></span></p>
    </div>

    <input type="hidden" id="financeiroId" value="<?= (int) $lancamento['id'] ?>">
    <input type="hidden" id="financeiroCodigo" value="<?= htmlspecialchars($codigoLabel) ?>">

    <!-- Opcoes de impressao -->
    <div class="mb-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><?= t('modules.financeiro.print.print_type') ?></p>
        <div class="space-y-2" id="printOptions">
            <label class="print-option" data-tipo="fatura">
                <input type="radio" name="tipoImpressao" value="fatura" checked class="hidden">
                <div class="flex items-center p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <i class="fas fa-file-invoice-dollar text-blue-500 w-5 text-center mr-3"></i>
                    <span class="text-sm font-medium"><?= t('modules.financeiro.print.invoice') ?></span>
                </div>
            </label>
        </div>
    </div>

    <!-- Botao Gerar PDF -->
    <button type="button" id="btnGerarPdf" class="w-full btn-blue py-3 px-4 rounded-lg text-sm font-medium flex items-center justify-center mb-4">
        <i class="fas fa-file-pdf mr-2"></i><?= t('modules.financeiro.print.generate_pdf') ?>
    </button>

    <!-- Enviar por mensageria -->
    <?php if ($temEmail || $temWhatsapp || $temSms): ?>
    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3"><?= t('modules.financeiro.print.send_via') ?></p>
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
        <?= $tipoReceita ? t('modules.financeiro.print.no_channels_available') : t('modules.financeiro.print.expense_send_unavailable') ?>
    </div>
    <?php endif; ?>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = {
        entryLabel: '<?= t('modules.financeiro.print.entry_label') ?>',
        sending: '<?= t('modules.financeiro.print.sending') ?>',
        sendSuccess: '<?= addslashes(t('modules.financeiro.print.send_success')) ?>',
        sendError: <?= js_t('modules.financeiro.print.send_error') ?>,
        sendConnectionError: <?= js_t('modules.financeiro.print.send_connection_error') ?>,
    };

    document.getElementById('btnGerarPdf').addEventListener('click', function() {
        const id = document.getElementById('financeiroId').value;
        const codigo = document.getElementById('financeiroCodigo').value;

        window.parent.postMessage({
            action: 'openPrintModal',
            url: '/financeiro/' + id + '/imprimir/fatura',
            title: i18n.entryLabel + ' ' + codigo
        }, '*');

        window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
    });

    document.querySelectorAll('.btn-enviar').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const canal = this.dataset.canal;
            const id = document.getElementById('financeiroId').value;

            this.disabled = true;
            const originalHtml = this.innerHTML;
            this.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${i18n.sending}`;

            try {
                const data = await API.post('/financeiro/' + id + '/enviar', { canal: canal });
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
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    });

    if (typeof window.pageLoading !== 'undefined' && window.pageLoading.done) {
        window.pageLoading.done();
    }
});
</script>
@endsection
