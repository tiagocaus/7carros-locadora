<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.ticket_medio.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.ticket_medio.total_locacoes') ?></div><div class="totals-value"><?= $totals['total_locacoes'] ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.ticket_medio.total_contratos') ?></div><div class="totals-value"><?= $totals['total_contratos'] ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.ticket_medio.total_operacoes') ?></div><div class="totals-value"><?= $totals['total_operacoes'] ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.ticket_medio.ticket_medio') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio']) ?></div></td>
        </tr>
    </table>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
