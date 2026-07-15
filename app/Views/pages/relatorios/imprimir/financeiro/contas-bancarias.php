<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.contas_bancarias.total_entradas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_entradas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.contas_bancarias.total_saidas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_saidas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.contas_bancarias.saldo_geral') ?></div>
                <div class="totals-value"><?= currency_format($totals['saldo_geral']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.contas_bancarias.contas_ativas') ?></div>
                <div class="totals-value"><?= number_format($totals['total_contas'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.contas_bancarias.col_conta') ?></th>
                <th><?= t('modules.relatorios.financeiro.contas_bancarias.col_banco') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.contas_bancarias.col_entradas') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.contas_bancarias.col_saidas') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.contas_bancarias.col_saldo') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $saldo = $row['saldo'] ?? 0; ?>
            <tr>
                <td><?= htmlspecialchars($row['conta'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['banco'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['entradas'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['saidas'] ?? 0) ?></td>
                <td class="right" style="font-weight: bold; color: <?= $saldo >= 0 ? '#166534' : '#991b1b' ?>;">
                    <?= currency_format($saldo) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
