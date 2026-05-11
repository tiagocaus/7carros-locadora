<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead><tr>
            <th><?= t('modules.relatorios.kpis.roi_veiculo.col_placa') ?></th>
            <th><?= t('modules.relatorios.kpis.roi_veiculo.col_veiculo') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.roi_veiculo.col_valor_compra') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.roi_veiculo.col_receita') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.roi_veiculo.col_custos') ?></th>
            <th class="right"><?= t('modules.relatorios.kpis.roi_veiculo.col_lucro') ?></th>
            <th class="center"><?= t('modules.relatorios.kpis.roi_veiculo.col_roi') ?></th>
        </tr></thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $badgeClass = $row['roi'] >= 0 ? 'badge-green' : 'badge-red'; ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['valor_compra']) ?></td>
                <td class="right"><?= currency_format($row['receita_total']) ?></td>
                <td class="right"><?= currency_format($row['custos']) ?></td>
                <td class="right"><?= currency_format($row['lucro_liquido']) ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= number_format($row['roi'], 1, ',', '.') ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
