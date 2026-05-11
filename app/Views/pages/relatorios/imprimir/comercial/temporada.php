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
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.temporada.qtd_temporadas') ?></div><div class="totals-value"><?= number_format($totals['qtd_temporadas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.temporada.qtd_locacoes') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.temporada.faturamento') ?></div><div class="totals-value"><?= currency_format($totals['faturamento']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comercial.temporada.ticket_medio') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comercial.temporada.col_temporada') ?></th>
                <th><?= t('modules.relatorios.comercial.temporada.col_periodo') ?></th>
                <th class="center"><?= t('modules.relatorios.comercial.temporada.col_locacoes') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.temporada.col_faturamento') ?></th>
                <th class="right"><?= t('modules.relatorios.comercial.temporada.col_ticket_medio') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['temporada']) ?></strong></td>
                <td><?= htmlspecialchars($row['periodo']) ?></td>
                <td class="center"><?= number_format($row['qtd_locacoes'], 0, ',', '.') ?></td>
                <td class="right"><strong><?= currency_format($row['faturamento']) ?></strong></td>
                <td class="right"><?= currency_format($row['ticket_medio']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
