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
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.depreciacao.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.depreciacao.valor_aquisicao_total') ?></div><div class="totals-value"><?= currency_format($totals['valor_aquisicao_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.depreciacao.dep_acumulada_total') ?></div><div class="totals-value"><?= currency_format($totals['depreciacao_acumulada_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.depreciacao.dep_periodo_total') ?></div><div class="totals-value"><?= currency_format($totals['depreciacao_periodo_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.depreciacao.valor_contabil_total') ?></div><div class="totals-value"><?= currency_format($totals['valor_contabil_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.veicular.depreciacao.pct_geral') ?></div><div class="totals-value"><?= number_format($totals['pct_depreciado_geral'], 1, ',', '.') ?>%</div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.depreciacao.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.depreciacao.col_veiculo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.depreciacao.col_valor_compra') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.depreciacao.col_data_compra') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.depreciacao.col_idade') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.depreciacao.col_dep_acumulada') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.depreciacao.col_valor_contabil') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.depreciacao.col_pct') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['valor_compra']) ?></td>
                <td class="center"><?= !empty($row['data_compra']) ? format_date($row['data_compra']) : '-' ?></td>
                <td class="center"><?= number_format($row['idade_anos'], 1, ',', '.') ?> anos</td>
                <td class="right"><?= currency_format($row['depreciacao_acumulada']) ?></td>
                <td class="right"><strong><?= currency_format($row['valor_contabil']) ?></strong></td>
                <td class="center"><?= number_format($row['pct_depreciado'], 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
