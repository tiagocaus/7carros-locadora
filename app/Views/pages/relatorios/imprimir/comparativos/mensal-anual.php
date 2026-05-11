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
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.mensal_anual.faturamento_atual') ?></div><div class="totals-value"><?= currency_format($totals['faturamento_atual']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.mensal_anual.faturamento_anterior') ?></div><div class="totals-value"><?= currency_format($totals['faturamento_anterior']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.mensal_anual.variacao_faturamento_pct') ?></div><div class="totals-value"><?= number_format($totals['variacao_faturamento_pct'], 1, ',', '.') ?>%</div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.mensal_anual.qtd_locacoes_atual') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes_atual'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.mensal_anual.qtd_locacoes_anterior') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes_anterior'], 0, ',', '.') ?></div></td>
        </tr>
    </table>

    <p style="font-size: 8pt; color: #666; margin-bottom: 10px;">
        <strong>Atual:</strong> <?= htmlspecialchars($totals['periodo_atual']) ?>
        &nbsp;|&nbsp;
        <strong>Anterior:</strong> <?= htmlspecialchars($totals['periodo_anterior']) ?>
    </p>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comparativos.mensal_anual.col_indicador') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.mensal_anual.col_anterior') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.mensal_anual.col_atual') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.mensal_anual.col_variacao_abs') ?></th>
                <th class="center"><?= t('modules.relatorios.comparativos.mensal_anual.col_variacao_pct') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['tendencia'] === 'up' ? 'badge-green' : ($row['tendencia'] === 'down' ? 'badge-red' : 'badge-blue');
                $arrow = $row['tendencia'] === 'up' ? '▲' : ($row['tendencia'] === 'down' ? '▼' : '–');
                $fmt = fn($v) => $row['is_currency'] ? currency_format($v) : number_format($v, 0, ',', '.');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['indicador']) ?></strong></td>
                <td class="right"><?= $fmt($row['anterior']) ?></td>
                <td class="right"><?= $fmt($row['atual']) ?></td>
                <td class="right"><?= $fmt($row['variacao_abs']) ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= $arrow ?> <?= number_format($row['variacao_pct'], 1, ',', '.') ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
