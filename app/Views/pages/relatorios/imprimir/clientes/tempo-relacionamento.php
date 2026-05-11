<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.tempo_relacionamento.qtd_clientes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_clientes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.tempo_relacionamento.idade_media_meses') ?></div>
                <div class="totals-value"><?= number_format($totals['idade_media_meses'], 1, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.tempo_relacionamento.faturamento_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['faturamento_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.tempo_relacionamento.ltv_medio') ?></div>
                <div class="totals-value" style="color:#15803d;"><?= currency_format($totals['ltv_medio']) ?></div>
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
                <th><?= t('modules.relatorios.clientes.tempo_relacionamento.col_cliente') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_desde') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_meses') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_total_locacoes') ?></th>
                <th class="right"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_faturamento') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_ultima') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['desde']) ? format_date($row['desde']) : '-' ?></td>
                <td class="center"><?= number_format($row['meses'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['total_locacoes'] ?? 0, 0, ',', '.') ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['faturamento_lifetime'] ?? 0) ?></td>
                <td class="center"><?= !empty($row['ultima_locacao']) ? format_date($row['ultima_locacao']) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
