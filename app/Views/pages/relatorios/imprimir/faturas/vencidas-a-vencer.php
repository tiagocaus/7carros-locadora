<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores gerais -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.vencidas_a_vencer.total_vencido') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= currency_format($totals['total_vencido']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.vencidas_a_vencer.qtd_vencidas') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_vencidas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.vencidas_a_vencer.total_a_vencer') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_a_vencer']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.vencidas_a_vencer.qtd_a_vencer') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_a_vencer'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <?php
        $visao = $details['visao'] ?? 'vencidas';
        $lista = $details['lista'] ?? [];
        $tituloVisao = $visao === 'vencidas'
            ? t('modules.relatorios.faturas.vencidas_a_vencer.lista_vencidas')
            : t('modules.relatorios.faturas.vencidas_a_vencer.lista_a_vencer');
        $colDiasLabel = $visao === 'vencidas'
            ? t('modules.relatorios.faturas.vencidas_a_vencer.col_dias_atraso')
            : t('modules.relatorios.faturas.vencidas_a_vencer.col_dias_a_vencer');
    ?>

    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; color: #334155;"><?= htmlspecialchars($tituloVisao) ?></div>

    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;">
            <?= t('modules.relatorios.common.no_data') ?>
        </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_fatura') ?></th>
                <th><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_cliente') ?></th>
                <th class="center"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_vencimento') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_valor_original') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_juros_multa') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_valor_total') ?></th>
                <th class="center"><?= htmlspecialchars($colDiasLabel) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($row['codigo'] ?? '-') ?>
                    <?php if (!empty($row['parcela_label']) && $row['parcela_label'] !== '-'): ?>
                        <span style="color:#94a3b8; font-size:8pt;">(<?= htmlspecialchars($row['parcela_label']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['data_venci']) ? format_date($row['data_venci']) : '-' ?></td>
                <td class="right"><?= currency_format($row['valor_subtotal'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['juros_multa'] ?? 0) ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['valor_total'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['dias'] ?? 0, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
