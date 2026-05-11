<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.multas_transito.total_multas') ?></div>
                <div class="totals-value"><?= number_format($totals['total_multas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.multas_transito.valor_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.multas_transito.qtd_pagas') ?></div>
                <div class="totals-value" style="color:#15803d;"><?= number_format($totals['qtd_pagas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.multas_transito.qtd_pendentes') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['qtd_pendentes'], 0, ',', '.') ?></div>
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
                <th class="center"><?= t('modules.relatorios.operacional.multas_transito.col_data') ?></th>
                <th><?= t('modules.relatorios.operacional.multas_transito.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.operacional.multas_transito.col_locacao') ?></th>
                <th><?= t('modules.relatorios.operacional.multas_transito.col_cliente') ?></th>
                <th><?= t('modules.relatorios.operacional.multas_transito.col_descricao') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.multas_transito.col_local') ?></th>
                <th class="right"><?= t('modules.relatorios.operacional.multas_transito.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.multas_transito.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td class="center"><?= !empty($row['data_hora']) ? format_date($row['data_hora']) : '-' ?></td>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($row['locacao_codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente_nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '-') ?></td>
                <td class="center"><?= htmlspecialchars(trim(($row['cidade'] ?? '') . '/' . ($row['estado'] ?? ''), '/')) ?: '-' ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['valor'] ?? 0) ?></td>
                <td class="center"><?= ($row['pago'] ?? 'N') === 'S' ? t('modules.relatorios.operacional.multas_transito.pago') : ($row['status_processamento'] ?? t('modules.relatorios.operacional.multas_transito.pendente')) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
