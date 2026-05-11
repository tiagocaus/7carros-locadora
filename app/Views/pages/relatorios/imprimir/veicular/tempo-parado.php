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
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tempo_parado.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tempo_parado.dias_periodo') ?></div><div class="totals-value"><?= number_format($totals['dias_periodo'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tempo_parado.dias_locados_total') ?></div><div class="totals-value"><?= number_format($totals['dias_locados_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tempo_parado.dias_parados_total') ?></div><div class="totals-value"><?= number_format($totals['dias_parados_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tempo_parado.pct_ociosidade') ?></div><div class="totals-value"><?= number_format($totals['pct_ociosidade_geral'], 1, ',', '.') ?>%</div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.tempo_parado.media_parado') ?></div><div class="totals-value"><?= number_format($totals['media_dias_parado'], 1, ',', '.') ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.tempo_parado.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.tempo_parado.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.tempo_parado.col_grupo') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.tempo_parado.col_dias_periodo') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.tempo_parado.col_dias_locados') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.tempo_parado.col_dias_parados') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.tempo_parado.col_pct') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['pct_ociosidade'] >= 70 ? 'badge-red' : ($row['pct_ociosidade'] >= 40 ? 'badge-yellow' : 'badge-green');
            ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="center"><?= (int) $row['dias_periodo'] ?></td>
                <td class="center"><?= (int) $row['dias_locados'] ?></td>
                <td class="center"><?= (int) $row['dias_parados'] ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= number_format($row['pct_ociosidade'], 1, ',', '.') ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
