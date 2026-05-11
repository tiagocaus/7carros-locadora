<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.ocorrencias.qtd_ocorrencias') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_ocorrencias'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.ocorrencias.qtd_atrasos') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_atrasos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.ocorrencias.qtd_inadimplencia') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['qtd_inadimplencia'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.ocorrencias.valor_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_total']) ?></div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $tipoLabels = [
            'devolucao_atrasada' => t('modules.relatorios.clientes.ocorrencias.tipo_atraso'),
            'inadimplencia' => t('modules.relatorios.clientes.ocorrencias.tipo_inadimplencia'),
        ];
        $statusLabels = [
            'finalizada' => t('modules.relatorios.clientes.ocorrencias.status_finalizada'),
            'pendente' => t('modules.relatorios.clientes.ocorrencias.status_pendente'),
        ];
    ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.clientes.ocorrencias.col_data') ?></th>
                <th><?= t('modules.relatorios.clientes.ocorrencias.col_tipo') ?></th>
                <th><?= t('modules.relatorios.clientes.ocorrencias.col_cliente') ?></th>
                <th><?= t('modules.relatorios.clientes.ocorrencias.col_locacao') ?></th>
                <th><?= t('modules.relatorios.clientes.ocorrencias.col_descricao') ?></th>
                <th class="right"><?= t('modules.relatorios.clientes.ocorrencias.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.ocorrencias.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td class="center"><?= !empty($row['data']) ? format_date($row['data']) : '-' ?></td>
                <td><?= htmlspecialchars($tipoLabels[$row['tipo']] ?? $row['tipo']) ?></td>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['locacao'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '-') ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['valor'] ?? 0) ?></td>
                <td class="center"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
