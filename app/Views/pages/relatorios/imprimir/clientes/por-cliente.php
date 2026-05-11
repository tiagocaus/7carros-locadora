<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.por_cliente.qtd_clientes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_clientes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.por_cliente.total_locacoes') ?></div>
                <div class="totals-value"><?= number_format($totals['total_locacoes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.por_cliente.faturamento_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['faturamento_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.por_cliente.ticket_medio') ?></div>
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
                <th><?= t('modules.relatorios.clientes.por_cliente.col_cliente') ?></th>
                <th><?= t('modules.relatorios.clientes.por_cliente.col_cpf_cnpj') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.por_cliente.col_total_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.por_cliente.col_primeira') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.por_cliente.col_ultima') ?></th>
                <th class="right"><?= t('modules.relatorios.clientes.por_cliente.col_faturamento') ?></th>
                <th class="right"><?= t('modules.relatorios.clientes.por_cliente.col_ticket_medio') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.por_cliente.col_dias_medio') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '') ?></td>
                <td class="center"><?= number_format($row['total_locacoes'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= !empty($row['primeira_locacao']) ? format_date($row['primeira_locacao']) : '-' ?></td>
                <td class="center"><?= !empty($row['ultima_locacao']) ? format_date($row['ultima_locacao']) : '-' ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['faturamento_total'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['ticket_medio'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['dias_medio'] ?? 0, 1, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
