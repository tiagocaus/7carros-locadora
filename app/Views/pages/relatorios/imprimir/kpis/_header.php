<?php
/**
 * Partial: Cabeçalho PDF para relatórios KPI
 *
 * Variáveis esperadas:
 * - $titulo (string) - Título do relatório
 * - $descricao (string) - Descrição do relatório
 * - $dataInicio (string) - Data início (Y-m-d)
 * - $dataFim (string) - Data fim (Y-m-d)
 * - $empresa (array) - Dados da empresa ['nome', 'logo']
 */
?>
<table style="width: 100%; margin-bottom: 15px; border-bottom: 2px solid #333;">
    <tr>
        <?php if (!empty($empresa['logo'])): ?>
        <td style="width: 80px; vertical-align: middle; padding-bottom: 8px;">
            <img src="<?= $empresa['logo'] ?>" style="max-width: 70px; max-height: 50px;" alt="">
        </td>
        <?php endif; ?>
        <td style="vertical-align: middle; padding-bottom: 8px;">
            <div style="font-size: 14pt; font-weight: bold; color: #333;"><?= htmlspecialchars($empresa['nome'] ?? '') ?></div>
            <div style="font-size: 12pt; font-weight: bold; color: #555; margin-top: 2px;"><?= htmlspecialchars($titulo) ?></div>
            <div style="font-size: 8pt; color: #888; margin-top: 2px;"><?= htmlspecialchars($descricao ?? '') ?></div>
        </td>
        <td style="text-align: right; vertical-align: middle; padding-bottom: 8px;">
            <div style="font-size: 8pt; color: #888;"><?= t('modules.relatorios.common.period') ?></div>
            <div style="font-size: 9pt; font-weight: bold;"><?= format_date($dataInicio) ?> - <?= format_date($dataFim) ?></div>
        </td>
    </tr>
</table>
