<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.projecao_receitas.receita_confirmada') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_confirmada']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.projecao_receitas.receita_projetada') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_projetada']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.projecao_receitas.total_esperado') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_esperado']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.projecao_receitas.contratos_ativos') ?></div>
                <div class="totals-value"><?= number_format($totals['contratos_ativos'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.projecao_receitas.col_mes') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.projecao_receitas.col_confirmada') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.projecao_receitas.col_projetada') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.projecao_receitas.col_total') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['mes'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['confirmada'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['projetada'] ?? 0) ?></td>
                <td class="right" style="font-weight: bold;"><?= currency_format($row['total'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
