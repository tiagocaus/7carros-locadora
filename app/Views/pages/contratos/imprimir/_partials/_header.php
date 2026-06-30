<?php
/**
 * Partial: Header padrao para todas as paginas de impressao
 *
 * Variaveis esperadas do controller:
 *   $empresa, $contrato, $logoPath, $qrPath
 *
 * Flag de controle (definida antes do include):
 *   $_docTitulo (string) - titulo do documento: "FATURA", "CHECKLIST DE VEICULO", "DOCUMENTO", etc.
 */
$_docTitulo = $_docTitulo ?? t('modules.contratos.pdf.invoice_title');
$_showQrCode = $_showQrCode ?? true;
?>

<table class="header-table" cellpadding="0" cellspacing="0" style="width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px;">
    <tr>
        <td style="width: 20%; vertical-align: middle; padding: 0;">
            <?php if (!empty($logoPath)): ?>
            <img src="<?= $logoPath ?>" class="logo-img" alt="Logo" style="max-height: 70px; max-width: 120px; margin-bottom: 5px;">
            <?php endif; ?>
        </td>
        <td style="width: 45%; vertical-align: top; padding: 0;">
            <div class="empresa-nome" style="font-size: 14pt; font-weight: bold; margin-bottom: 3px;"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? t('modules.contratos.pdf.company_fallback')) ?></div>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= t('modules.contratos.pdf.cpf_cnpj_label') ?> <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            <?php
                $endereco = trim(($empresa['rua'] ?? '') . ', ' . ($empresa['num'] ?? '') . ' - ' . ($empresa['bairro'] ?? ''), ' -,');
                $cidadeUf = trim(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? ''), '/');
            ?>
            <?php if ($endereco): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= htmlspecialchars($endereco) ?></div>
            <?php endif; ?>
            <?php if ($cidadeUf): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= htmlspecialchars($cidadeUf) ?><?= !empty($empresa['cep']) ? ' - ' . t('modules.contratos.pdf.zip_label') . ' ' . htmlspecialchars($empresa['cep']) : '' ?></div>
            <?php endif; ?>
            <?php if (!empty($empresa['celular'])): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= t('modules.contratos.pdf.phone_label') ?> <?= htmlspecialchars($empresa['celular']) ?></div>
            <?php elseif (!empty($empresa['fixo'])): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= t('modules.contratos.pdf.phone_label') ?> <?= htmlspecialchars($empresa['fixo']) ?></div>
            <?php endif; ?>
        </td>
        <td style="width: 20%; vertical-align: top; padding: 0;">
            <div class="doc-titulo" style="font-size: 13pt; font-weight: bold; text-align: right; margin-bottom: 5px;"><?= htmlspecialchars($_docTitulo) ?></div>
            <div class="doc-detalhe" style="font-size: 9pt; text-align: right; color: #555;"><strong><?= t('modules.contratos.pdf.contract_label') ?></strong> <?= htmlspecialchars($contrato['codigo']) ?>-<?= htmlspecialchars($contrato['sequencia'] ?? '') ?></div>
            <div class="doc-detalhe" style="font-size: 9pt; text-align: right; color: #555;"><strong><?= t('modules.contratos.pdf.date_label') ?></strong> <?= !empty($contrato['created_at']) ? format_date($contrato['created_at']) : format_date(today()) ?></div>
            <div class="doc-detalhe" style="font-size: 9pt; text-align: right; color: #555;"><strong><?= t('modules.contratos.pdf.status_label') ?></strong> <?= $contrato['status'] === 'A' ? t('modules.contratos.status.active') : t('modules.contratos.status.finalized') ?></div>
        </td>
        <td style="width: 15%; text-align: right; vertical-align: top; padding: 0;">
            <?php if (!empty($qrPath) && $_showQrCode): ?>
            <img src="<?= $qrPath ?>" class="qr-img" alt="QR Code" style="width: 80px; height: 80px;">
            <?php endif; ?>
        </td>
    </tr>
</table>
