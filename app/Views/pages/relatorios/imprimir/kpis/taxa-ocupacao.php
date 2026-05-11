<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.kpis.taxa_ocupacao.total_veiculos') ?></div>
                <div class="totals-value"><?= number_format($totals['total_veiculos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.kpis.taxa_ocupacao.dias_disponiveis') ?></div>
                <div class="totals-value"><?= number_format($totals['dias_disponiveis'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.kpis.taxa_ocupacao.dias_locados') ?></div>
                <div class="totals-value"><?= number_format($totals['dias_locados'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.kpis.taxa_ocupacao.dias_parados') ?></div>
                <div class="totals-value"><?= number_format($totals['dias_parados'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.kpis.taxa_ocupacao.taxa') ?></div>
                <div class="totals-value"><?= number_format($totals['taxa_ocupacao'], 1, ',', '.') ?>%</div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.kpis.taxa_ocupacao.col_placa') ?></th>
                <th><?= t('modules.relatorios.kpis.taxa_ocupacao.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.kpis.taxa_ocupacao.col_grupo') ?></th>
                <th class="center"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_dias_locados') ?></th>
                <th class="center"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_dias_parados') ?></th>
                <th class="center"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_taxa') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['taxa_ocupacao'] >= 70 ? 'badge-green' : ($row['taxa_ocupacao'] >= 50 ? 'badge-yellow' : 'badge-red');
            ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="center"><?= $row['dias_locados'] ?></td>
                <td class="center"><?= $row['dias_parados'] ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= number_format($row['taxa_ocupacao'], 1, ',', '.') ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
