<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.avarias_sinistros.total_avarias') ?></div>
                <div class="totals-value"><?= number_format($totals['total_avarias'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.avarias_sinistros.tipo_leve') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_leve'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.avarias_sinistros.tipo_media') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_media'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.avarias_sinistros.tipo_sinistro') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['qtd_sinistro'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $tipoLabels = [
            'leve' => t('modules.relatorios.operacional.avarias_sinistros.tipo_leve'),
            'media' => t('modules.relatorios.operacional.avarias_sinistros.tipo_media'),
            'sinistro' => t('modules.relatorios.operacional.avarias_sinistros.tipo_sinistro'),
        ];
    ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.operacional.avarias_sinistros.col_data') ?></th>
                <th><?= t('modules.relatorios.operacional.avarias_sinistros.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.operacional.avarias_sinistros.col_cliente') ?></th>
                <th><?= t('modules.relatorios.operacional.avarias_sinistros.col_locacao') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.avarias_sinistros.col_tipo') ?></th>
                <th><?= t('modules.relatorios.operacional.avarias_sinistros.col_descricao') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.avarias_sinistros.col_qtd_itens') ?></th>
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
                <td class="center"><?= number_format($row['qtd_itens'] ?? 0, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
