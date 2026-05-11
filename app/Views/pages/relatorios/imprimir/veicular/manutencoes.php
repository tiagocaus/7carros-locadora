<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.manutencoes.total_manutencoes') ?></div>
                <div class="totals-value"><?= number_format($totals['total_manutencoes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.manutencoes.custo_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['custo_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.manutencoes.custo_medio') ?></div>
                <div class="totals-value"><?= currency_format($totals['custo_medio']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.manutencoes.dias_parados_total') ?></div>
                <div class="totals-value"><?= number_format($totals['dias_parados_total'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.manutencoes.custo_por_km') ?></div>
                <div class="totals-value"><?= currency_format($totals['custo_por_km']) ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.manutencoes.col_os') ?></th>
                <th><?= t('modules.relatorios.veicular.manutencoes.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.manutencoes.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.manutencoes.col_oficina') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.manutencoes.col_data_entrada') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.manutencoes.col_data_saida') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.manutencoes.col_dias_parado') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.manutencoes.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.manutencoes.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = match ($row['status']) {
                    'F' => 'badge-green',
                    'A' => 'badge-blue',
                    'C' => 'badge-yellow',
                    default => 'badge-red',
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($row['os'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['oficina'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['data_entrada']) ? format_date($row['data_entrada']) : '-' ?></td>
                <td class="center"><?= !empty($row['data_saida']) ? format_date($row['data_saida']) : '-' ?></td>
                <td class="center"><?= (int) $row['dias_parado'] ?></td>
                <td class="right"><?= currency_format($row['valor']) ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status_label'] ?? '-') ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
