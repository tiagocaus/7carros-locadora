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
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tco.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tco.tco_total') ?></div><div class="totals-value"><?= currency_format($totals['tco_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tco.depreciacao_total') ?></div><div class="totals-value"><?= currency_format($totals['depreciacao_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tco.manutencao_total') ?></div><div class="totals-value"><?= currency_format($totals['manutencao_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tco.encargos_total') ?></div><div class="totals-value"><?= currency_format($totals['encargos_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tco.tco_medio') ?></div><div class="totals-value"><?= currency_format($totals['tco_medio_veiculo']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.tco.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.tco.col_veiculo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_depreciacao') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_manutencao') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_multas') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_encargos') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_tco_total') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_tco_mes') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.tco.col_tco_km') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['depreciacao']) ?></td>
                <td class="right"><?= currency_format($row['manutencao']) ?></td>
                <td class="right"><?= currency_format($row['multas']) ?></td>
                <td class="right"><?= currency_format($row['encargos']) ?></td>
                <td class="right"><strong><?= currency_format($row['tco_total']) ?></strong></td>
                <td class="right"><?= currency_format($row['tco_mes']) ?></td>
                <td class="right"><?= $row['tco_km'] > 0 ? currency_format($row['tco_km']) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
