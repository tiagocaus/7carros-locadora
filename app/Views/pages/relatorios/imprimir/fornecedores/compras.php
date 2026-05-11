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
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.compras.qtd_fornecedores') ?></div><div class="totals-value"><?= number_format($totals['qtd_fornecedores'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.compras.qtd_compras_total') ?></div><div class="totals-value"><?= number_format($totals['qtd_compras_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.compras.valor_total_geral') ?></div><div class="totals-value"><?= currency_format($totals['valor_total_geral']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.compras.ticket_medio_geral') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio_geral']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.fornecedores.compras.col_fornecedor') ?></th>
                <th><?= t('modules.relatorios.fornecedores.compras.col_cnpj') ?></th>
                <th class="center"><?= t('modules.relatorios.fornecedores.compras.col_qtd') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.compras.col_valor_total') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.compras.col_ticket_medio') ?></th>
                <th class="center"><?= t('modules.relatorios.fornecedores.compras.col_ultima_compra') ?></th>
                <th class="center"><?= t('modules.relatorios.fornecedores.compras.col_investidor') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['fornecedor'] ?? '-') ?></strong></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '-') ?></td>
                <td class="center"><?= (int) $row['qtd_compras'] ?></td>
                <td class="right"><strong><?= currency_format($row['valor_total']) ?></strong></td>
                <td class="right"><?= currency_format($row['ticket_medio']) ?></td>
                <td class="center"><?= !empty($row['ultima_compra']) ? format_date($row['ultima_compra']) : '-' ?></td>
                <td class="center"><?= !empty($row['investidor']) ? '<span class="badge badge-blue">Sim</span>' : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
