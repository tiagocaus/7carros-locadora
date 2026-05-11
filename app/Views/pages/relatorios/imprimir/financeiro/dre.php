<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.dre.receita_bruta') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_bruta']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.dre.lucro_bruto') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_bruto']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.dre.lucro_operacional') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_operacional']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.dre.lucro_liquido') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_liquido']) ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela DRE -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.dre.col_descricao') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.dre.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.dre.col_percentual') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $type = $row['type'] ?? 'item';
                $indent = (int)($row['indent'] ?? 0);
                $valor = $row['valor'] ?? 0;
                $isNegative = $valor < 0;
            ?>
            <?php if ($type === 'header'): ?>
            <tr>
                <td colspan="3" style="padding-left: <?= $indent * 20 ?>px; font-weight: bold; background: #e2e8f0; font-size: 9pt;">
                    <?= htmlspecialchars($row['descricao'] ?? '-') ?>
                </td>
            </tr>
            <?php elseif ($type === 'subtotal'): ?>
            <tr>
                <td style="padding-left: <?= $indent * 20 ?>px; font-weight: bold; border-top: 2px solid #334155;">
                    <?= htmlspecialchars($row['descricao'] ?? '-') ?>
                </td>
                <td class="right" style="font-weight: bold; border-top: 2px solid #334155; <?= $isNegative ? 'color: #991b1b;' : '' ?>">
                    <?= currency_format($valor) ?>
                </td>
                <td class="center" style="font-weight: bold; border-top: 2px solid #334155;">
                    <?= isset($row['percentual']) ? number_format($row['percentual'], 1, ',', '.') . '%' : '' ?>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td style="padding-left: <?= $indent * 20 ?>px;">
                    <?= htmlspecialchars($row['descricao'] ?? '-') ?>
                </td>
                <td class="right" style="<?= $isNegative ? 'color: #991b1b;' : '' ?>">
                    <?= currency_format($valor) ?>
                </td>
                <td class="center">
                    <?= isset($row['percentual']) ? number_format($row['percentual'], 1, ',', '.') . '%' : '' ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
