<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.total') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['total_devolucoes_atrasadas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.media_dias') ?></div>
                <div class="totals-value"><?= number_format($totals['media_dias_atraso'], 1, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.total_dias') ?></div>
                <div class="totals-value"><?= number_format($totals['total_dias_atraso'], 0, ',', '.') ?></div>
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
                <th><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_codigo') ?></th>
                <th><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_cliente') ?></th>
                <th><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_veiculo') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_prevista') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_chegada') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_dias_atraso') ?></th>
                <th class="right"><?= t('modules.relatorios.operacional.devolucoes_atrasadas.col_valor') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente_nome'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td class="center"><?= !empty($row['data_prevista']) ? format_date($row['data_prevista']) : '-' ?></td>
                <td class="center"><?= !empty($row['data_chegada']) ? format_date($row['data_chegada']) : '-' ?></td>
                <td class="center" style="font-weight:bold; color:#b91c1c;"><?= number_format($row['dias_atraso'] ?? 0, 0, ',', '.') ?>d</td>
                <td class="right"><?= currency_format($row['total_pagar'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
