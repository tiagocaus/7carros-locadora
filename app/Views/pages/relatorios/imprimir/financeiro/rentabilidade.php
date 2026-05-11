<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.rentabilidade.receita_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.rentabilidade.custos_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['custos_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.rentabilidade.lucro_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.rentabilidade.margem_media') ?></div>
                <div class="totals-value"><?= number_format($totals['margem_media'], 1, ',', '.') ?>%</div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.rentabilidade.col_dimensao') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.rentabilidade.col_receita') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.rentabilidade.col_custos') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.rentabilidade.col_lucro') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.rentabilidade.col_margem') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.rentabilidade.col_participacao') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $margem = $row['margem'] ?? 0;
                $margemBadge = $margem >= 30 ? 'badge-green' : ($margem >= 15 ? 'badge-yellow' : 'badge-red');
            ?>
            <tr>
                <td><?= htmlspecialchars($row['dimensao'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['receita'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['custos'] ?? 0) ?></td>
                <td class="right"><?= currency_format($row['lucro'] ?? 0) ?></td>
                <td class="center"><span class="badge <?= $margemBadge ?>"><?= number_format($margem, 1, ',', '.') ?>%</span></td>
                <td class="center"><?= number_format($row['participacao'] ?? 0, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
