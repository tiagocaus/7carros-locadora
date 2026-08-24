<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.sinistros.report.total') ?></div>
                <div class="totals-value"><?= number_format($totals['total_sinistros'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.sinistros.report.open') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_abertos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.sinistros.report.completed') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_concluidos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.sinistros.report.charged_value') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_cobrado'] ?? 0) ?></div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $tipoLabels = [
            'colisao' => t('modules.sinistros.types.collision'), 'furto_roubo' => t('modules.sinistros.types.theft'),
            'incendio' => t('modules.sinistros.types.fire'), 'alagamento' => t('modules.sinistros.types.flood'),
            'danos_terceiros' => t('modules.sinistros.types.third_party'), 'perda_total' => t('modules.sinistros.types.total_loss'),
            'outros' => t('modules.sinistros.types.other'),
        ];
    ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.sinistros.fields.date') ?></th>
                <th><?= t('modules.sinistros.fields.vehicle') ?></th>
                <th><?= t('modules.sinistros.report.client') ?></th>
                <th><?= t('modules.sinistros.report.link') ?></th>
                <th class="center"><?= t('modules.sinistros.fields.type') ?></th>
                <th><?= t('modules.sinistros.fields.description') ?></th>
                <th class="right"><?= t('modules.sinistros.fields.estimated_value') ?></th>
                <th class="right"><?= t('modules.sinistros.fields.charge') ?></th>
                <th class="center"><?= t('modules.sinistros.fields.status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td class="center"><?= !empty($row['data']) ? format_date($row['data']) : '-' ?></td>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($row['cliente_nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['locacao_codigo'] ?? '-') ?></td>
                <td class="center"><?= htmlspecialchars($tipoLabels[$row['tipo']] ?? $row['tipo']) ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['valor_estimado'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['valor_cobrado'] ?? 0) ?></td>
                <td class="center"><?= ($row['status'] ?? 'A') === 'C' ? t('modules.sinistros.status.completed') : t('modules.sinistros.status.open') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
