<?php
/**
 * Partial: Footer padrao com assinaturas para todas as paginas de impressao
 *
 * Variaveis esperadas do controller:
 *   $assinatura, $assinaturaPath, $empresaAssinaturaPath, $contrato, $empresa
 */
?>

<table class="assinatura-table" cellpadding="0" cellspacing="0" style="width: 100%; margin-top: 0;">
    <tr>
        <td class="assinatura-cell" style="width: 45%; text-align: center; vertical-align: bottom; padding: 0 10px;">
            <?php if (!empty($assinaturaPath)): ?>
            <img src="<?= $assinaturaPath ?>" alt="<?= t('modules.contratos.pdf.signature_alt') ?>" class="assinatura-img" style="max-height: 50px; margin-bottom: 5px; background: #fff;">
            <?php endif; ?>
            <hr class="assinatura-linha" style="border: 0; border-top: 1px solid #333; margin: <?= !empty($assinaturaPath) ? '0' : '70px' ?> 0 0 0; padding: 0; width: 100%; height: 0;">
            <div class="assinatura-nome" style="text-align: center; font-size: 8pt; padding-top: 5px;"><?= htmlspecialchars($contrato['cliente_nome'] ?? t('modules.contratos.pdf.client_fallback')) ?></div>
        </td>
        <td class="spacer" style="width: 10%;"></td>
        <td class="assinatura-cell" style="width: 45%; text-align: center; vertical-align: bottom; padding: 0 10px;">
            <?php if (!empty($empresaAssinaturaPath)): ?>
            <img src="<?= $empresaAssinaturaPath ?>" alt="<?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? t('modules.contratos.pdf.company_fallback')) ?>" class="assinatura-img" style="max-height: 50px; margin-bottom: 5px; background: #fff;">
            <?php endif; ?>
            <hr class="assinatura-linha" style="border: 0; border-top: 1px solid #333; margin: <?= !empty($empresaAssinaturaPath) ? '0' : '70px' ?> 0 0 0; padding: 0; width: 100%; height: 0;">
            <div class="assinatura-nome" style="text-align: center; font-size: 8pt; padding-top: 5px;"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? t('modules.contratos.pdf.company_fallback')) ?></div>
        </td>
    </tr>
</table>
<div class="page-number" style="text-align: center; font-size: 8pt; color: #999; margin-top: 8px;"><?= t('modules.contratos.pdf.page_label', ['page' => '{PAGENO}', 'total' => '{nbpg}']) ?></div>
