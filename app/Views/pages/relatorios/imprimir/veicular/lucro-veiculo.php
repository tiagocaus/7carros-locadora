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
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.qtd_veiculos') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.receita_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.despesa_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['despesa_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.lucro_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.margem_geral') ?></div>
                <div class="totals-value"><?= number_format($totals['margem_geral'], 1, ',', '.') ?>%</div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.lucro_veiculo.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.lucro_veiculo.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.lucro_veiculo.col_grupo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_receita') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_despesa') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_lucro') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_margem') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['margem'] >= 30 ? 'badge-green' : ($row['margem'] >= 10 ? 'badge-yellow' : 'badge-red');
            ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['receita']) ?></td>
                <td class="right"><?= currency_format($row['despesa_total']) ?></td>
                <td class="right"><?= currency_format($row['lucro']) ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= number_format($row['margem'], 1, ',', '.') ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
