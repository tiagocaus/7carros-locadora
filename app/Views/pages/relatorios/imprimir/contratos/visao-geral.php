<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.visao_geral.total_locacoes') ?></div>
                <div class="totals-value"><?= number_format($totals['total_locacoes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.visao_geral.valor_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['valor_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.visao_geral.media_dias') ?></div>
                <div class="totals-value"><?= number_format($totals['media_dias'], 1, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.contratos.visao_geral.ticket_medio') ?></div>
                <div class="totals-value"><?= currency_format($totals['ticket_medio']) ?></div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $statusLabels = [
            'A' => t('modules.relatorios.contratos.visao_geral.status_ativo'),
            'F' => t('modules.relatorios.contratos.visao_geral.status_finalizado'),
            'R' => t('modules.relatorios.contratos.visao_geral.status_reserva'),
            'P' => t('modules.relatorios.contratos.visao_geral.status_pendente'),
        ];
    ?>

    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.contratos.visao_geral.col_codigo') ?></th>
                <th><?= t('modules.relatorios.contratos.visao_geral.col_cliente') ?></th>
                <th><?= t('modules.relatorios.contratos.visao_geral.col_veiculo') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.visao_geral.col_data_saida') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.visao_geral.col_data_prevista') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.visao_geral.col_dias') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.visao_geral.col_valor_total') ?></th>
                <th><?= t('modules.relatorios.contratos.visao_geral.col_forma_pagamento') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.visao_geral.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['veiculo_placa'] ?? '-') ?></strong>
                    <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_descricao'] ?? '') ?></span>
                </td>
                <td class="center"><?= !empty($row['data_saida']) ? format_date($row['data_saida']) : '-' ?></td>
                <td class="center"><?= !empty($row['data_prevista']) ? format_date($row['data_prevista']) : '-' ?></td>
                <td class="center"><?= number_format($row['dias'] ?? 0, 0, ',', '.') ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($row['total_pagar'] ?? 0) ?></td>
                <td><?= htmlspecialchars($row['forma_pagamento'] ?? '-') ?></td>
                <td class="center"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
