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
                <div class="totals-label"><?= t('modules.relatorios.veicular.disponibilidade.total_frota') ?></div>
                <div class="totals-value"><?= number_format($totals['total_frota'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.disponibilidade.disponiveis') ?></div>
                <div class="totals-value"><?= number_format($totals['disponiveis'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.disponibilidade.locados') ?></div>
                <div class="totals-value"><?= number_format($totals['locados'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.disponibilidade.reservados') ?></div>
                <div class="totals-value"><?= number_format($totals['reservados'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.disponibilidade.oficina') ?></div>
                <div class="totals-value"><?= number_format($totals['oficina'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.disponibilidade.taxa_ocupacao_atual') ?></div>
                <div class="totals-value"><?= number_format($totals['taxa_ocupacao_atual'], 1, ',', '.') ?>%</div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.disponibilidade.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.disponibilidade.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.disponibilidade.col_grupo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.disponibilidade.col_odometro') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.disponibilidade.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = match ($row['status']) {
                    'D' => 'badge-green',
                    'L' => 'badge-blue',
                    'R' => 'badge-yellow',
                    'O', 'E' => 'badge-red',
                    default => '',
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><?= number_format((int) $row['odometro'], 0, ',', '.') ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status_label']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
