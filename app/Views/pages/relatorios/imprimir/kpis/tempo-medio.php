<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.tempo_medio.total_operacoes') ?></div><div class="totals-value"><?= $totals['total_operacoes'] ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.tempo_medio.total_dias') ?></div><div class="totals-value"><?= number_format($totals['total_dias'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.tempo_medio.media_dias') ?></div><div class="totals-value"><?= number_format($totals['media_dias'], 1, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.tempo_medio.minimo') ?></div><div class="totals-value"><?= $totals['minimo'] ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.kpis.tempo_medio.maximo') ?></div><div class="totals-value"><?= $totals['maximo'] ?></div></td>
        </tr>
    </table>
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead><tr><th><?= t('modules.relatorios.kpis.tempo_medio.col_faixa') ?></th><th class="center"><?= t('modules.relatorios.kpis.tempo_medio.col_quantidade') ?></th></tr></thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr><td><?= htmlspecialchars($row['faixa']) ?></td><td class="center"><?= $row['quantidade'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
