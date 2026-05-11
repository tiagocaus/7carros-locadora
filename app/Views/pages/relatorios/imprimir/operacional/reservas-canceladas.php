<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.reservas_canceladas.total') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['total_canceladas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.reservas_canceladas.valor_perdido') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_perdido']) ?></div>
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
                <th><?= t('modules.relatorios.operacional.reservas_canceladas.col_codigo') ?></th>
                <th><?= t('modules.relatorios.operacional.reservas_canceladas.col_cliente') ?></th>
                <th><?= t('modules.relatorios.operacional.reservas_canceladas.col_veiculo') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.reservas_canceladas.col_data_reserva') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.reservas_canceladas.col_prevista_saida') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.reservas_canceladas.col_antecedencia') ?></th>
                <th class="right"><?= t('modules.relatorios.operacional.reservas_canceladas.col_valor_perdido') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente_nome'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td class="center"><?= !empty($row['data_reserva']) ? format_date($row['data_reserva']) : '-' ?></td>
                <td class="center"><?= !empty($row['data_prevista_saida']) ? format_date($row['data_prevista_saida']) : '-' ?></td>
                <td class="center"><?= number_format($row['antecedencia'] ?? 0, 0, ',', '.') ?>d</td>
                <td class="right"><?= currency_format($row['valor_perdido'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
