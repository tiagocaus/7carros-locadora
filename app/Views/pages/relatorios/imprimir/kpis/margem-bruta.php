<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.margem_bruta.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.margem_bruta.custos_variaveis') ?></div><div class="totals-value"><?= currency_format($totals['custos_variaveis']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.margem_bruta.margem_bruta') ?></div><div class="totals-value"><?= currency_format($totals['margem_bruta']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.margem_bruta.dias_locados') ?></div><div class="totals-value"><?= number_format($totals['dias_locados'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.margem_bruta.margem_por_dia') ?></div><div class="totals-value"><?= currency_format($totals['margem_por_dia']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.margem_bruta.percentual_margem') ?></div><div class="totals-value"><?= number_format($totals['percentual_margem'], 1, ',', '.') ?>%</div></td>
        </tr>
    </table>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
