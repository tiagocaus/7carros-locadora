<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.adr.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.adr.dias_locados') ?></div><div class="totals-value"><?= number_format($totals['dias_locados'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.adr.adr') ?></div><div class="totals-value"><?= currency_format($totals['adr']) ?></div></td>
        </tr>
    </table>
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead><tr><th><?= t('modules.relatorios.kpis.adr.col_grupo') ?></th><th class="right"><?= t('modules.relatorios.kpis.adr.col_receita') ?></th><th class="center"><?= t('modules.relatorios.kpis.adr.col_dias') ?></th><th class="right"><?= t('modules.relatorios.kpis.adr.col_adr') ?></th></tr></thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr><td><?= htmlspecialchars($row['grupo']) ?></td><td class="right"><?= currency_format($row['receita']) ?></td><td class="center"><?= $row['dias_locados'] ?></td><td class="right"><?= currency_format($row['adr']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
