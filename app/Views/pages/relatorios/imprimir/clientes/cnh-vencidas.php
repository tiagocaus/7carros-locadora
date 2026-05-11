<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.cnh_vencidas.total') ?></div>
                <div class="totals-value"><?= number_format($totals['total'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.cnh_vencidas.faixa_vencidas') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['vencidas'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.cnh_vencidas.faixa_30') ?></div>
                <div class="totals-value"><?= number_format($totals['vence_30'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.cnh_vencidas.faixa_60') ?></div>
                <div class="totals-value"><?= number_format($totals['vence_60'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.cnh_vencidas.faixa_90') ?></div>
                <div class="totals-value"><?= number_format($totals['vence_90'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $statusLabels = [
            'vencida' => t('modules.relatorios.clientes.cnh_vencidas.status_vencida'),
            '30' => t('modules.relatorios.clientes.cnh_vencidas.status_30'),
            '60' => t('modules.relatorios.clientes.cnh_vencidas.status_60'),
            '90' => t('modules.relatorios.clientes.cnh_vencidas.status_90'),
        ];
    ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.clientes.cnh_vencidas.col_cliente') ?></th>
                <th><?= t('modules.relatorios.clientes.cnh_vencidas.col_cpf') ?></th>
                <th><?= t('modules.relatorios.clientes.cnh_vencidas.col_cnh') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_validade') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_dias') ?></th>
                <th><?= t('modules.relatorios.clientes.cnh_vencidas.col_telefone') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_loc_ativa') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <?php
                $dias = (int) ($row['dias_para_vencer'] ?? 0);
                $labelDias = $dias < 0 ? abs($dias) . 'd ' . t('modules.relatorios.clientes.cnh_vencidas.atras') : $dias . 'd';
            ?>
            <tr>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['cnh_numero'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['cnh_validade']) ? format_date($row['cnh_validade']) : '-' ?></td>
                <td class="center" <?= $dias < 0 ? 'style="color:#b91c1c; font-weight:bold;"' : '' ?>><?= htmlspecialchars($labelDias) ?></td>
                <td><?= htmlspecialchars($row['telefone'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['tem_locacao_ativa']) ? t('modules.relatorios.clientes.cnh_vencidas.sim') : '-' ?></td>
                <td class="center"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
