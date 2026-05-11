<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.receitas_adicionais.receita_locacao') ?></div><div class="totals-value"><?= currency_format($totals['receita_locacao']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.receitas_adicionais.receita_adicionais') ?></div><div class="totals-value"><?= currency_format($totals['receita_adicionais']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.receitas_adicionais.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.receitas_adicionais.percentual') ?></div><div class="totals-value"><?= number_format($totals['percentual_adicionais'], 1, ',', '.') ?>%</div></td>
        </tr>
    </table>
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead><tr><th><?= t('modules.relatorios.kpis.receitas_adicionais.col_nome') ?></th><th class="right"><?= t('modules.relatorios.kpis.receitas_adicionais.col_receita') ?></th><th class="center"><?= t('modules.relatorios.kpis.receitas_adicionais.col_percentual') ?></th></tr></thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr><td><?= htmlspecialchars($row['nome']) ?></td><td class="right"><?= currency_format($row['receita']) ?></td><td class="center"><?= number_format($row['percentual'], 1, ',', '.') ?>%</td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
