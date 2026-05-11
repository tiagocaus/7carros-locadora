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
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.filiais.qtd_filiais') ?></div><div class="totals-value"><?= number_format($totals['qtd_filiais'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.filiais.total_veiculos') ?></div><div class="totals-value"><?= number_format($totals['total_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.filiais.total_locacoes') ?></div><div class="totals-value"><?= number_format($totals['total_locacoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.filiais.total_contratos') ?></div><div class="totals-value"><?= number_format($totals['total_contratos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.filiais.faturamento_total') ?></div><div class="totals-value"><?= currency_format($totals['faturamento_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.filiais.ticket_medio_geral') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio_geral']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.comparativos.filiais.col_ranking') ?></th>
                <th><?= t('modules.relatorios.comparativos.filiais.col_filial') ?></th>
                <th><?= t('modules.relatorios.comparativos.filiais.col_cidade') ?></th>
                <th class="center"><?= t('modules.relatorios.comparativos.filiais.col_veiculos') ?></th>
                <th class="center"><?= t('modules.relatorios.comparativos.filiais.col_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.comparativos.filiais.col_contratos') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.filiais.col_faturamento') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.filiais.col_ticket_medio') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['ranking'] === 1 ? 'badge-yellow' : ($row['ranking'] <= 3 ? 'badge-blue' : '');
            ?>
            <tr>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= $row['ranking'] ?>º</span></td>
                <td><strong><?= htmlspecialchars($row['filial']) ?></strong></td>
                <td><?= htmlspecialchars($row['cidade'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['veiculos'], 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['qtd_locacoes'], 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['qtd_contratos'], 0, ',', '.') ?></td>
                <td class="right"><strong><?= currency_format($row['faturamento']) ?></strong></td>
                <td class="right"><?= currency_format($row['ticket_medio']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
