<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.total_a_receber') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_a_receber']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.total_vencido') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_vencido']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.taxa_inadimplencia') ?></div>
                <div class="totals-value"><?= number_format($totals['taxa_inadimplencia'], 1, ',', '.') ?>%</div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.clientes_inadimplentes') ?></div>
                <div class="totals-value"><?= number_format($totals['clientes_inadimplentes'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <!-- Aging (Faixas de Atraso) -->
    <?php if (!empty($details['aging'])): ?>
    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; color: #334155;"><?= t('modules.relatorios.financeiro.inadimplencia.aging_title') ?></div>
    <table class="totals-table" style="margin-bottom: 15px;">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.aging_1_15') ?></div>
                <div class="totals-value" style="font-size: 10pt;"><?= currency_format($details['aging']['faixa_1_15'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.aging_16_30') ?></div>
                <div class="totals-value" style="font-size: 10pt;"><?= currency_format($details['aging']['faixa_16_30'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.aging_31_60') ?></div>
                <div class="totals-value" style="font-size: 10pt;"><?= currency_format($details['aging']['faixa_31_60'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.aging_61_90') ?></div>
                <div class="totals-value" style="font-size: 10pt;"><?= currency_format($details['aging']['faixa_61_90'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.inadimplencia.aging_90_plus') ?></div>
                <div class="totals-value" style="font-size: 10pt;"><?= currency_format($details['aging']['faixa_90_plus'] ?? 0) ?></div>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Top Devedores -->
    <?php if (!empty($details['devedores'])): ?>
    <div style="font-size: 10pt; font-weight: bold; margin-bottom: 5px; color: #334155;"><?= t('modules.relatorios.financeiro.inadimplencia.top_devedores') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.inadimplencia.col_cliente') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.inadimplencia.col_valor_vencido') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.inadimplencia.col_faturas') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.inadimplencia.col_maior_atraso') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details['devedores'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['valor_vencido'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['faturas'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['maior_atraso'] ?? 0, 0, ',', '.') ?> <?= t('modules.relatorios.financeiro.inadimplencia.dias') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
