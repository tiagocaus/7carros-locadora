<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.extensoes.qtd_extensoes') ?></div><div class="totals-value"><?= number_format($totals['qtd_extensoes'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.extensoes.pct_extensoes') ?></div><div class="totals-value"><?= number_format($totals['pct_extensoes'], 1, ',', '.') ?>%</div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.extensoes.media_dias') ?></div><div class="totals-value"><?= number_format($totals['media_dias'], 1, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.extensoes.receita_extensoes') ?></div><div class="totals-value"><?= currency_format($totals['receita_extensoes']) ?></div></td>
        </tr>
    </table>

    <?php $lista = $details['lista'] ?? []; ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.contratos.extensoes.col_codigo') ?></th>
                <th><?= t('modules.relatorios.contratos.extensoes.col_cliente') ?></th>
                <th><?= t('modules.relatorios.contratos.extensoes.col_veiculo') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.extensoes.col_data_saida') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.extensoes.col_data_prevista') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.extensoes.col_data_chegada') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.extensoes.col_dias_originais') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.extensoes.col_dias_extensao') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.extensoes.col_valor_total') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['codigo'] ?? '-') ?></strong></td>
                <td><?= htmlspecialchars($r['cliente'] ?? '-') ?></td>
                <td>
                    <strong><?= htmlspecialchars($r['veiculo_placa'] ?? '-') ?></strong>
                    <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($r['veiculo_descricao'] ?? '') ?></span>
                </td>
                <td class="center"><?= !empty($r['data_saida']) ? format_date($r['data_saida']) : '-' ?></td>
                <td class="center"><?= !empty($r['data_prevista']) ? format_date($r['data_prevista']) : '-' ?></td>
                <td class="center"><?= !empty($r['data_chegada']) ? format_date($r['data_chegada']) : '-' ?></td>
                <td class="center"><?= number_format($r['dias_originais'] ?? 0, 0, ',', '.') ?></td>
                <td class="center" style="color:#ea580c; font-weight:bold;">+<?= number_format($r['dias_extensao'] ?? 0, 0, ',', '.') ?></td>
                <td class="right" style="font-weight:bold;"><?= currency_format($r['total_pagar'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
