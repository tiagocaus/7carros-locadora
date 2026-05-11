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
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.promocoes.qtd_promocoes') ?></div><div class="totals-value"><?= number_format($totals['qtd_promocoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.promocoes.qtd_usos_total') ?></div><div class="totals-value"><?= number_format($totals['qtd_usos_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.promocoes.desconto_total') ?></div><div class="totals-value"><?= currency_format($totals['desconto_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.promocoes.receita_gerada') ?></div><div class="totals-value"><?= currency_format($totals['receita_gerada']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.promocoes.desconto_medio_geral') ?></div><div class="totals-value"><?= currency_format($totals['desconto_medio_geral']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comercial.promocoes.col_codigo') ?></th>
                <th><?= t('modules.relatorios.comercial.promocoes.col_nome') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.promocoes.col_usos') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.promocoes.col_desconto_total') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.promocoes.col_desconto_medio') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.promocoes.col_receita') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['codigo']) ?></strong></td>
                <td><?= htmlspecialchars($row['nome'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['qtd_usos'], 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['desconto_total']) ?></td>
                <td class="right"><?= currency_format($row['desconto_medio']) ?></td>
                <td class="right"><?= currency_format($row['receita']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
