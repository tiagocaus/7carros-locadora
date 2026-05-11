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
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.quilometragem_media.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.quilometragem_media.km_total') ?></div><div class="totals-value"><?= number_format($totals['km_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.quilometragem_media.qtd_locacoes') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.quilometragem_media.media_km_veiculo') ?></div><div class="totals-value"><?= number_format($totals['media_km_veiculo'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.quilometragem_media.media_km_locacao') ?></div><div class="totals-value"><?= number_format($totals['media_km_locacao'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.quilometragem_media.media_km_dia') ?></div><div class="totals-value"><?= number_format($totals['media_km_dia'], 1, ',', '.') ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.quilometragem_media.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.quilometragem_media.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.quilometragem_media.col_grupo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.quilometragem_media.col_km_total') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.quilometragem_media.col_locacoes') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.quilometragem_media.col_km_dia') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.quilometragem_media.col_km_locacao') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><?= number_format((int) $row['km_total'], 0, ',', '.') ?></td>
                <td class="center"><?= (int) $row['qtd_locacoes'] ?></td>
                <td class="right"><?= number_format((float) $row['km_dia'], 1, ',', '.') ?></td>
                <td class="right"><?= number_format((int) $row['km_locacao'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
