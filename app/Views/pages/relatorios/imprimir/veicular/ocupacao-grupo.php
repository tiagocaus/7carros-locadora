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
                <div class="totals-label"><?= t('modules.relatorios.veicular.ocupacao_grupo.qtd_grupos') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_grupos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.ocupacao_grupo.total_veiculos') ?></div>
                <div class="totals-value"><?= number_format($totals['total_veiculos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.ocupacao_grupo.dias_locados_total') ?></div>
                <div class="totals-value"><?= number_format($totals['dias_locados'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.ocupacao_grupo.taxa_geral') ?></div>
                <div class="totals-value"><?= number_format($totals['taxa_geral'], 1, ',', '.') ?>%</div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.ocupacao_grupo.receita_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.ocupacao_grupo.revpar_geral') ?></div>
                <div class="totals-value"><?= currency_format($totals['revpar_geral']) ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.ocupacao_grupo.col_grupo') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.ocupacao_grupo.col_veiculos') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.ocupacao_grupo.col_dias_disponiveis') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.ocupacao_grupo.col_dias_locados') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.ocupacao_grupo.col_taxa') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.ocupacao_grupo.col_receita') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.ocupacao_grupo.col_revpar') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['taxa_ocupacao'] >= 70 ? 'badge-green' : ($row['taxa_ocupacao'] >= 50 ? 'badge-yellow' : 'badge-red');
            ?>
            <tr>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['total_veiculos'], 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['dias_disponiveis'], 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['dias_locados'], 0, ',', '.') ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= number_format($row['taxa_ocupacao'], 1, ',', '.') ?>%</span></td>
                <td class="right"><?= currency_format($row['receita']) ?></td>
                <td class="right"><?= currency_format($row['revpar']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
