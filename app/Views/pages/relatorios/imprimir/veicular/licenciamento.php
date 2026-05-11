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
                <div class="totals-label"><?= t('modules.relatorios.veicular.licenciamento.total_encargos') ?></div>
                <div class="totals-value"><?= number_format($totals['total_encargos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.licenciamento.vencidos') ?></div>
                <div class="totals-value"><?= number_format($totals['vencidos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.licenciamento.prox_30') ?></div>
                <div class="totals-value"><?= number_format($totals['prox_30'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.licenciamento.em_dia') ?></div>
                <div class="totals-value"><?= number_format($totals['em_dia'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.licenciamento.valor_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_total']) ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.licenciamento.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.licenciamento.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.licenciamento.col_tipo') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.licenciamento.col_vencimento') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.licenciamento.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.licenciamento.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = match ($row['status']) {
                    'vencido' => 'badge-red',
                    'prox_30' => 'badge-yellow',
                    'em_dia' => 'badge-green',
                    default => 'badge-blue',
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['tipo'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['vencimento']) ? format_date($row['vencimento']) : '-' ?></td>
                <td class="right"><?= currency_format($row['valor']) ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status_label']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
