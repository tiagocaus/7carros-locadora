<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.receita_veiculo.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
        </tr>
    </table>
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead><tr>
            <th><?= t('modules.relatorios.kpis.receita_veiculo.col_placa') ?></th>
            <th><?= t('modules.relatorios.kpis.receita_veiculo.col_veiculo') ?></th>
            <th><?= t('modules.relatorios.kpis.receita_veiculo.col_grupo') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.receita_veiculo.col_receita_loc') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.receita_veiculo.col_receita_taxas') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.receita_veiculo.col_receita_total') ?></th>
            <th class="center"><?= t('modules.relatorios.kpis.receita_veiculo.col_dias') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.receita_veiculo.col_receita_dia') ?></th>
            <th class="center"><?= t('modules.relatorios.kpis.receita_veiculo.col_percentual') ?></th>
        </tr></thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['receita_locacao']) ?></td>
                <td class="right"><?= currency_format($row['receita_taxas']) ?></td>
                <td class="right"><?= currency_format($row['receita_total']) ?></td>
                <td class="center"><?= $row['dias_locados'] ?></td>
                <td class="right"><?= currency_format($row['receita_dia']) ?></td>
                <td class="center"><?= number_format($row['percentual_faturamento'], 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
