<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.saldo_inicial') ?></div>
                <div class="totals-value"><?= currency_format($totals['saldo_inicial']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.total_entradas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_entradas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.total_saidas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_saidas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.saldo_final') ?></div>
                <div class="totals-value"><?= currency_format($totals['saldo_final']) ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_data') ?></th>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_historico') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.livro_caixa.col_entrada') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.livro_caixa.col_saida') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.livro_caixa.col_saldo') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= format_date($row['data'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['historico'] ?? '-') ?></td>
                <td class="right" style="color: #166534;"><?= ($row['entrada'] ?? 0) > 0 ? currency_format($row['entrada']) : '-' ?></td>
                <td class="right" style="color: #991b1b;"><?= ($row['saida'] ?? 0) > 0 ? currency_format($row['saida']) : '-' ?></td>
                <td class="right" style="font-weight: bold;"><?= currency_format($row['saldo'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
