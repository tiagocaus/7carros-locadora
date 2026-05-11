<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.por_periodo.qtd_locacoes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_locacoes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.por_periodo.total_dias') ?></div>
                <div class="totals-value"><?= number_format($totals['dias'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.por_periodo.receita') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.por_periodo.ticket_medio') ?></div>
                <div class="totals-value"><?= currency_format($totals['ticket_medio']) ?></div>
            </td>
        </tr>
    </table>

    <?php $lista = $details['lista'] ?? []; ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.contratos.por_periodo.col_periodo') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.por_periodo.col_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.por_periodo.col_dias') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.por_periodo.col_receita') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.por_periodo.col_ticket_medio') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.por_periodo.col_variacao') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['periodo_label'] ?? '-') ?></strong></td>
                <td class="center"><?= number_format($row['qtd_locacoes'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['dias'] ?? 0, 0, ',', '.') ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['receita'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['ticket_medio'] ?? 0) ?></td>
                <td class="right">
                    <?php if (isset($row['variacao_pct']) && $row['variacao_pct'] !== null): ?>
                        <?php
                            $v = (float) $row['variacao_pct'];
                            $cor = $v > 0 ? '#16a34a' : ($v < 0 ? '#b91c1c' : '#64748b');
                            $arrow = $v > 0 ? '▲' : ($v < 0 ? '▼' : '—');
                        ?>
                        <span style="color:<?= $cor ?>;"><?= $arrow ?> <?= number_format($v, 2, ',', '.') ?>%</span>
                    <?php else: ?>
                        <span style="color:#94a3b8;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
