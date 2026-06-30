<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.turnaround.total_periodos') ?></div>
                <div class="totals-value"><?= number_format($totals['total_periodos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.turnaround.medio_horas') ?></div>
                <div class="totals-value" style="color:#15803d;"><?= number_format($totals['turnaround_medio_horas'], 1, ',', '.') ?>h</div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.turnaround.total_horas') ?></div>
                <div class="totals-value"><?= number_format($totals['turnaround_total_horas'], 1, ',', '.') ?>h</div>
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
                <th><?= t('modules.relatorios.operacional.turnaround.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.operacional.turnaround.col_locacao_anterior') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.turnaround.col_data_chegada') ?></th>
                <th><?= t('modules.relatorios.operacional.turnaround.col_proxima_locacao') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.turnaround.col_data_saida') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.turnaround.col_turnaround') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <?php
                $horas = (float) ($row['turnaround_horas'] ?? 0);
                $fmt = $horas < 24 ? number_format($horas, 1, ',', '.') . 'h' : floor($horas / 24) . 'd ' . round($horas % 24) . 'h';
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($row['locacao_anterior'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['data_chegada']) ? format_operational_datetime($row['data_chegada']) : '-' ?></td>
                <td><?= htmlspecialchars($row['proxima_locacao'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['data_saida_proxima']) ? format_operational_datetime($row['data_saida_proxima']) : '-' ?></td>
                <td class="center" style="font-weight:bold;"><?= htmlspecialchars($fmt) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
