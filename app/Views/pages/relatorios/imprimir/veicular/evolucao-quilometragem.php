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
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.evolucao_quilometragem.km_total') ?></div><div class="totals-value"><?= number_format((int) $totals['km_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.evolucao_quilometragem.vehicles_measured') ?></div><div class="totals-value"><?= number_format((int) $totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.evolucao_quilometragem.usages') ?></div><div class="totals-value"><?= number_format((int) $totals['qtd_utilizacoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.evolucao_quilometragem.periods') ?></div><div class="totals-value"><?= number_format((int) $totals['qtd_periodos'], 0, ',', '.') ?></div></td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.evolucao_quilometragem.peak_km') ?></div>
                <div class="totals-value"><?= number_format((int) $totals['pico_km'], 0, ',', '.') ?></div>
                <div class="totals-label"><?= htmlspecialchars((string) ($totals['pico_periodo'] ?? '')) ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_period') ?></th>
                <th><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_start') ?></th>
                <th><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_end') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_km') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_vehicles') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_usages') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['label'] ?? '')) ?></td>
                <td><?= format_date($row['data_inicio'] ?? '') ?></td>
                <td><?= format_date($row['data_fim'] ?? '') ?></td>
                <td class="right"><?= number_format((int) ($row['km_total'] ?? 0), 0, ',', '.') ?></td>
                <td class="center"><?= number_format((int) ($row['qtd_veiculos'] ?? 0), 0, ',', '.') ?></td>
                <td class="center"><?= number_format((int) ($row['qtd_utilizacoes'] ?? 0), 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
