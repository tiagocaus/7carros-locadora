<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.por_forma_pagamento.qtd_formas') ?></div><div class="totals-value"><?= number_format($totals['qtd_formas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.por_forma_pagamento.total_locacoes') ?></div><div class="totals-value"><?= number_format($totals['total_locacoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.por_forma_pagamento.valor_total') ?></div><div class="totals-value"><?= currency_format($totals['valor_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.por_forma_pagamento.ticket_medio') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio']) ?></div></td>
        </tr>
    </table>

    <?php $lista = $details['lista'] ?? []; ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.contratos.por_forma_pagamento.col_forma') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_pct_locacoes') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_valor_total') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_pct_valor') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_ticket_medio') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['forma_pagamento'] ?? '-') ?></strong></td>
                <td class="center"><?= number_format($r['qtd_locacoes'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= number_format($r['pct_locacoes'] ?? 0, 1, ',', '.') ?>%</td>
                <td class="right" style="font-weight:bold;"><?= currency_format($r['valor_total'] ?? 0) ?></td>
                <td class="center"><?= number_format($r['pct_valor'] ?? 0, 1, ',', '.') ?>%</td>
                <td class="right"><?= currency_format($r['ticket_medio'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
