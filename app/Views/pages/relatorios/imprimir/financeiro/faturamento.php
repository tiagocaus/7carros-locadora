<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.faturamento.faturamento_bruto') ?></div>
                <div class="totals-value"><?= currency_format($totals['faturamento_bruto']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.faturamento.descontos') ?></div>
                <div class="totals-value"><?= currency_format($totals['descontos']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.faturamento.faturamento_liquido') ?></div>
                <div class="totals-value"><?= currency_format($totals['faturamento_liquido']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.faturamento.lancamentos') ?></div>
                <div class="totals-value"><?= number_format($totals['lancamentos'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela: Por Origem -->
    <?php if (!empty($details['por_origem'])): ?>
    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; color: #334155;"><?= t('modules.relatorios.financeiro.faturamento.por_origem') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.faturamento.col_nome') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.faturamento.col_qtd') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.faturamento.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.faturamento.col_percentual') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details['por_origem'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['nome'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['qtd'] ?? 0, 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['valor'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['percentual'] ?? 0, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Tabela: Por Forma de Pagamento -->
    <?php if (!empty($details['por_forma_pagamento'])): ?>
    <div style="font-size: 10pt; font-weight: bold; margin: 10px 0 5px 0; color: #334155;"><?= t('modules.relatorios.financeiro.faturamento.por_forma_pagamento') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.faturamento.col_nome') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.faturamento.col_qtd') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.faturamento.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.faturamento.col_percentual') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details['por_forma_pagamento'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['nome'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['qtd'] ?? 0, 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['valor'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['percentual'] ?? 0, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
