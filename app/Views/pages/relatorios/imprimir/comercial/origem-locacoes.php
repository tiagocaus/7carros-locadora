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
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.origem_locacoes.qtd_canais') ?></div><div class="totals-value"><?= number_format($totals['qtd_canais'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.origem_locacoes.qtd_total') ?></div><div class="totals-value"><?= number_format($totals['qtd_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.origem_locacoes.faturamento_total') ?></div><div class="totals-value"><?= currency_format($totals['faturamento_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.origem_locacoes.ticket_medio_geral') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio_geral']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comercial.origem_locacoes.col_canal') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.origem_locacoes.col_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.origem_locacoes.col_contratos') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.origem_locacoes.col_total') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.origem_locacoes.col_faturamento') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.origem_locacoes.col_ticket_medio') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.origem_locacoes.col_pct') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['canal_label']) ?></strong></td>
                <td class="center"><?= number_format($row['qtd_locacoes'], 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['qtd_contratos'], 0, ',', '.') ?></td>
                <td class="center"><strong><?= number_format($row['qtd_total'], 0, ',', '.') ?></strong></td>
                <td class="right"><?= currency_format($row['faturamento']) ?></td>
                <td class="right"><?= currency_format($row['ticket_medio']) ?></td>
                <td class="center"><?= number_format($row['pct_participacao'], 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
