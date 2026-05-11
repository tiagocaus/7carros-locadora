<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.por_veiculo.total_faturas') ?></div>
                <div class="totals-value"><?= number_format($totals['total_faturas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.por_veiculo.valor_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.por_veiculo.total_pago') ?></div>
                <div class="totals-value" style="color:#16a34a;"><?= currency_format($totals['total_pago']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.por_veiculo.total_pendente') ?></div>
                <div class="totals-value" style="color:#ca8a04;"><?= currency_format($totals['total_pendente']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.por_veiculo.total_vencido') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= currency_format($totals['total_vencido']) ?></div>
            </td>
        </tr>
    </table>

    <?php $lista = $details['lista'] ?? []; ?>

    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;">
            <?= t('modules.relatorios.common.no_data') ?>
        </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.faturas.por_veiculo.col_veiculo') ?></th>
                <th class="center"><?= t('modules.relatorios.faturas.por_veiculo.col_total_faturas') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.por_veiculo.col_valor_total') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.por_veiculo.col_pagas') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.por_veiculo.col_pendentes') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.por_veiculo.col_vencidas') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong>
                    <span style="color:#64748b; font-size:8pt;">
                        <?= htmlspecialchars($row['veiculo'] ?? '') ?>
                        <?= !empty($row['ano']) ? ' (' . htmlspecialchars((string) $row['ano']) . ')' : '' ?>
                    </span>
                </td>
                <td class="center"><?= number_format($row['total_faturas'] ?? 0, 0, ',', '.') ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['valor_total'] ?? 0) ?></td>
                <td class="right" style="color:#16a34a;"><?= currency_format($row['total_pago'] ?? 0) ?></td>
                <td class="right" style="color:#ca8a04;"><?= currency_format($row['total_pendente'] ?? 0) ?></td>
                <td class="right" style="color:#b91c1c;"><?= currency_format($row['total_vencido'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
