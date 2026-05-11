<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.taxas_servicos.receita_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.taxas_servicos.total_cobradas') ?></div>
                <div class="totals-value"><?= number_format($totals['total_cobradas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.taxas_servicos.ticket_medio') ?></div>
                <div class="totals-value"><?= currency_format($totals['ticket_medio']) ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.taxas_servicos.col_nome') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.taxas_servicos.col_quantidade') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.taxas_servicos.col_valor_total') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.taxas_servicos.col_ticket_medio') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.taxas_servicos.col_percentual') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['nome'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['quantidade'] ?? 0, 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['valor_total'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['ticket_medio'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['percentual'] ?? 0, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
