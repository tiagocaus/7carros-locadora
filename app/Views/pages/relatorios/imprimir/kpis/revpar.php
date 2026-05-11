<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.revpar.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.revpar.total_veiculos') ?></div><div class="totals-value"><?= $totals['total_veiculos'] ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.revpar.dias_disponiveis') ?></div><div class="totals-value"><?= number_format($totals['dias_disponiveis'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.revpar.revpar') ?></div><div class="totals-value"><?= currency_format($totals['revpar']) ?></div></td>
        </tr>
    </table>
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead><tr><th><?= t('modules.relatorios.kpis.revpar.col_grupo') ?></th><th class="center"><?= t('modules.relatorios.kpis.revpar.col_veiculos') ?></th><th class="right"><?= t('modules.relatorios.kpis.revpar.col_receita') ?></th><th class="center"><?= t('modules.relatorios.kpis.revpar.col_dias') ?></th><th class="right"><?= t('modules.relatorios.kpis.revpar.col_revpar') ?></th></tr></thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr><td><?= htmlspecialchars($row['grupo']) ?></td><td class="center"><?= $row['total_veiculos'] ?></td><td class="right"><?= currency_format($row['receita']) ?></td><td class="center"><?= $row['dias_disponiveis'] ?></td><td class="right"><?= currency_format($row['revpar']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
