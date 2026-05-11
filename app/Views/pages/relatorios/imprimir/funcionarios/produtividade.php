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
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.produtividade.qtd_funcionarios') ?></div><div class="totals-value"><?= number_format($totals['qtd_funcionarios'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.produtividade.qtd_locacoes') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.produtividade.faturamento_total') ?></div><div class="totals-value"><?= currency_format($totals['faturamento_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.produtividade.qtd_checklists') ?></div><div class="totals-value"><?= number_format($totals['qtd_checklists'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.produtividade.media_loc') ?></div><div class="totals-value"><?= number_format($totals['media_locacoes_funcionario'], 1, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.produtividade.media_fat') ?></div><div class="totals-value"><?= currency_format($totals['media_faturamento_funcionario']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.funcionarios.produtividade.col_funcionario') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.produtividade.col_dias_trabalhados') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.produtividade.col_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.produtividade.col_locacoes_dia') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.produtividade.col_faturamento') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.produtividade.col_faturamento_dia') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.produtividade.col_checklists') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['funcionario']) ?></strong></td>
                <td class="center"><?= (int) $row['dias_trabalhados'] ?></td>
                <td class="center"><?= (int) $row['locacoes'] ?></td>
                <td class="center"><?= number_format((float) $row['locacoes_dia'], 1, ',', '.') ?></td>
                <td class="right"><strong><?= currency_format($row['faturamento']) ?></strong></td>
                <td class="right"><?= currency_format($row['faturamento_dia']) ?></td>
                <td class="center"><?= (int) $row['checklists'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
