<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.taxa_conversao.total_geral') ?></div><div class="totals-value"><?= number_format($totals['total_geral'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.taxa_conversao.reservas') ?></div><div class="totals-value"><?= number_format($totals['reservas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.taxa_conversao.convertidas') ?></div><div class="totals-value"><?= number_format($totals['convertidas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.taxa_conversao.canceladas') ?></div><div class="totals-value"><?= number_format($totals['canceladas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.taxa_conversao.taxa') ?></div><div class="totals-value"><?= number_format($totals['taxa_conversao'], 1, ',', '.') ?>%</div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.taxa_conversao.cancelamento') ?></div><div class="totals-value"><?= number_format($totals['taxa_cancelamento'], 1, ',', '.') ?>%</div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comercial.taxa_conversao.col_status') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.taxa_conversao.col_qtd') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.taxa_conversao.col_faturamento') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.taxa_conversao.col_pct') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = match ($row['status']) {
                    'F' => 'badge-green',
                    'A' => 'badge-blue',
                    'R' => 'badge-yellow',
                    'C' => 'badge-red',
                    default => '',
                };
            ?>
            <tr>
                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status_label']) ?></span></td>
                <td class="center"><?= number_format($row['qtd'], 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['faturamento']) ?></td>
                <td class="center"><?= number_format($row['pct'], 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
