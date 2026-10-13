<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.faturas.receber_por_contrato.total_open') ?></div><div class="totals-value"><?= currency_format($totals['total_aberto'] ?? 0) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.faturas.receber_por_contrato.total_overdue') ?></div><div class="totals-value" style="color:#b91c1c;"><?= currency_format($totals['total_vencido'] ?? 0) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.faturas.receber_por_contrato.total_upcoming') ?></div><div class="totals-value" style="color:#15803d;"><?= currency_format($totals['total_a_vencer'] ?? 0) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.faturas.receber_por_contrato.total_contracts') ?></div><div class="totals-value"><?= number_format($totals['total_contratos'] ?? 0, 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.faturas.receber_por_contrato.total_clients') ?></div><div class="totals-value"><?= number_format($totals['total_clientes'] ?? 0, 0, ',', '.') ?></div></td>
        </tr>
    </table>

    <?php if (empty($details)): ?>
        <p style="font-size:9pt;color:#64748b;padding:10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
        <?php foreach ($details as $contrato): ?>
            <table class="data-table" style="margin-bottom:12px;page-break-inside:avoid;">
                <thead>
                    <tr>
                        <th colspan="2"><?= t('modules.relatorios.faturas.receber_por_contrato.col_contract') ?>: <?= htmlspecialchars($contrato['contrato'] ?? '-') ?></th>
                        <th colspan="2"><?= t('modules.relatorios.faturas.receber_por_contrato.col_client') ?>: <?= htmlspecialchars($contrato['cliente'] ?? '-') ?></th>
                        <th colspan="2"><?= t('modules.relatorios.faturas.receber_por_contrato.col_vehicle') ?>: <?= htmlspecialchars($contrato['veiculo_resumo'] ?: '-') ?></th>
                        <th colspan="2" class="right">
                            <?= ($contrato['contrato_status'] ?? '') === 'finalizado'
                                ? t('modules.relatorios.faturas.receber_por_contrato.contract_finalized_open')
                                : t('modules.relatorios.faturas.receber_por_contrato.contract_active') ?>
                        </th>
                    </tr>
                    <tr>
                        <th><?= t('modules.relatorios.faturas.receber_por_contrato.col_invoice') ?></th>
                        <th><?= t('modules.relatorios.faturas.receber_por_contrato.col_description') ?></th>
                        <th class="center"><?= t('modules.relatorios.faturas.receber_por_contrato.col_due_date') ?></th>
                        <th class="right"><?= t('modules.relatorios.faturas.receber_por_contrato.col_original_value') ?></th>
                        <th class="right"><?= t('modules.relatorios.faturas.receber_por_contrato.col_fees') ?></th>
                        <th class="right"><?= t('modules.relatorios.faturas.receber_por_contrato.col_total_value') ?></th>
                        <th class="center"><?= t('modules.relatorios.faturas.receber_por_contrato.col_status') ?></th>
                        <th class="center"><?= t('modules.relatorios.faturas.receber_por_contrato.col_deadline') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($contrato['parcelas'] ?? []) as $parcela): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($parcela['fatura'] ?? '-') ?>
                            <?php if (($parcela['parcela_label'] ?? '-') !== '-'): ?>
                                <span style="color:#94a3b8;font-size:8pt;">(<?= htmlspecialchars($parcela['parcela_label']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($parcela['descricao'] ?: '-') ?></td>
                        <td class="center"><?= format_date($parcela['data_venci'] ?? null) ?></td>
                        <td class="right"><?= currency_format($parcela['valor_subtotal'] ?? 0) ?></td>
                        <td class="right"><?= currency_format($parcela['juros_multa'] ?? 0) ?></td>
                        <td class="right" style="font-weight:bold;"><?= currency_format($parcela['valor_total'] ?? 0) ?></td>
                        <td class="center"><?= ($parcela['situacao'] ?? '') === 'vencida'
                            ? t('modules.relatorios.faturas.receber_por_contrato.overdue')
                            : t('modules.relatorios.faturas.receber_por_contrato.upcoming') ?></td>
                        <td class="center"><?= number_format($parcela['dias'] ?? 0, 0, ',', '.') ?>d</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4"></td>
                        <td class="right" style="font-weight:bold;color:#b91c1c;"><?= currency_format($contrato['total_vencido'] ?? 0) ?></td>
                        <td class="right" style="font-weight:bold;"><?= currency_format($contrato['total_aberto'] ?? 0) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
