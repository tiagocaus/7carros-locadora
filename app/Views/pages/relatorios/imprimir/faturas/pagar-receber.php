<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.pagar_receber.total_receber') ?></div>
                <div class="totals-value" style="color:#16a34a;"><?= currency_format($totals['total_receber']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.pagar_receber.total_pagar') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= currency_format($totals['total_pagar']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.pagar_receber.saldo') ?></div>
                <div class="totals-value" style="color:<?= $totals['saldo'] >= 0 ? '#16a34a' : '#b91c1c' ?>;"><?= currency_format($totals['saldo']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.pagar_receber.qtd_receber') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_receber'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.faturas.pagar_receber.qtd_pagar') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_pagar'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <?php
        $statusLabel = function ($s) {
            switch ($s) {
                case 'pago': return t('modules.relatorios.faturas.pagar_receber.status_pago');
                case 'vencida': return t('modules.relatorios.faturas.pagar_receber.status_vencida');
                default: return t('modules.relatorios.faturas.pagar_receber.status_pendente');
            }
        };
    ?>

    <!-- Contas a Receber -->
    <div style="font-size: 10pt; font-weight: bold; margin: 5px 0; color: #16a34a;">
        <?= t('modules.relatorios.faturas.pagar_receber.contas_receber') ?>
    </div>
    <?php if (empty($details['receber'])): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 8px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.faturas.pagar_receber.col_vencimento') ?></th>
                <th><?= t('modules.relatorios.faturas.pagar_receber.col_cliente') ?></th>
                <th><?= t('modules.relatorios.faturas.pagar_receber.col_descricao') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.pagar_receber.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.faturas.pagar_receber.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details['receber'] as $row): ?>
            <tr>
                <td class="center"><?= !empty($row['data_venci']) ? format_date($row['data_venci']) : '-' ?></td>
                <td><?= htmlspecialchars($row['pessoa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '') ?></td>
                <td class="right" style="color:#16a34a;"><?= currency_format($row['valor_total'] ?? 0) ?></td>
                <td class="center"><?= htmlspecialchars($statusLabel($row['status'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Contas a Pagar -->
    <div style="font-size: 10pt; font-weight: bold; margin: 12px 0 5px; color: #b91c1c;">
        <?= t('modules.relatorios.faturas.pagar_receber.contas_pagar') ?>
    </div>
    <?php if (empty($details['pagar'])): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 8px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.faturas.pagar_receber.col_vencimento') ?></th>
                <th><?= t('modules.relatorios.faturas.pagar_receber.col_fornecedor') ?></th>
                <th><?= t('modules.relatorios.faturas.pagar_receber.col_descricao') ?></th>
                <th class="right"><?= t('modules.relatorios.faturas.pagar_receber.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.faturas.pagar_receber.col_status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details['pagar'] as $row): ?>
            <tr>
                <td class="center"><?= !empty($row['data_venci']) ? format_date($row['data_venci']) : '-' ?></td>
                <td><?= htmlspecialchars($row['pessoa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '') ?></td>
                <td class="right" style="color:#b91c1c;"><?= currency_format($row['valor_total'] ?? 0) ?></td>
                <td class="center"><?= htmlspecialchars($statusLabel($row['status'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
