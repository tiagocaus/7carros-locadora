<?php
/**
 * Partial: Rodapé PDF para relatórios Fornecedores
 */
?>
<table style="width: 100%; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 8px;">
    <tr>
        <td style="font-size: 7pt; color: #999;">
            <?= t('modules.relatorios.common.generated_at') ?>: <?= format_datetime(date('Y-m-d H:i:s')) ?>
            &nbsp;|&nbsp;
            <?= t('modules.relatorios.common.generated_by') ?>: <?= htmlspecialchars($usuario ?? '') ?>
        </td>
    </tr>
</table>
