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
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.despesas.qtd_veiculos') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.despesas.manutencao_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['manutencao_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.despesas.multas_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['multas_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.despesas.encargos_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['encargos_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.despesas.outros_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['outros_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.despesas.total_geral') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_geral']) ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.despesas.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.despesas.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.despesas.col_grupo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.despesas.col_manutencao') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.despesas.col_multas') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.despesas.col_encargos') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.despesas.col_outros') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.despesas.col_total') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['manutencao']) ?></td>
                <td class="right"><?= currency_format($row['multas']) ?></td>
                <td class="right"><?= currency_format($row['encargos']) ?></td>
                <td class="right"><?= currency_format($row['outros']) ?></td>
                <td class="right"><strong><?= currency_format($row['total']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
