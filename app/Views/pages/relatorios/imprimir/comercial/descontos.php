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
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.descontos.qtd_funcionarios') ?></div><div class="totals-value"><?= number_format($totals['qtd_funcionarios'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.descontos.qtd_locacoes_com_desconto') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes_com_desconto'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.descontos.desconto_total') ?></div><div class="totals-value"><?= currency_format($totals['desconto_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.descontos.receita_base_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_base_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.descontos.pct_desconto_geral') ?></div><div class="totals-value"><?= number_format($totals['pct_desconto_geral'], 1, ',', '.') ?>%</div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.descontos.desconto_medio_geral') ?></div><div class="totals-value"><?= currency_format($totals['desconto_medio_geral']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comercial.descontos.col_funcionario') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.descontos.col_qtd') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.descontos.col_desconto_total') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.descontos.col_desconto_medio') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.descontos.col_receita_base') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.descontos.col_pct') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['funcionario']) ?></strong></td>
                <td class="center"><?= (int) $row['qtd'] ?></td>
                <td class="right"><strong><?= currency_format($row['desconto_total']) ?></strong></td>
                <td class="right"><?= currency_format($row['desconto_medio']) ?></td>
                <td class="right"><?= currency_format($row['receita_base']) ?></td>
                <td class="center"><?= number_format($row['pct_desconto'], 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
