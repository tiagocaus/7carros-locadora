<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.resultado_caixa.receita_liquida') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_liquida'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.resultado_caixa.lucro_bruto') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_bruto'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.resultado_caixa.lucro_operacional') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_operacional'] ?? 0) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.resultado_caixa.lucro_liquido') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_liquido'] ?? 0) ?></div>
            </td>
        </tr>
    </table>

    <?php if (($totals['sem_data_quantidade'] ?? 0) > 0): ?>
        <?php
            $warning = strtr(t('modules.relatorios.financeiro.resultado_caixa.warning_sem_data'), [
                '{quantidade}' => (string) $totals['sem_data_quantidade'],
                '{receitas}' => currency_format($totals['sem_data_receitas'] ?? 0),
                '{despesas}' => currency_format($totals['sem_data_despesas'] ?? 0),
            ]);
        ?>
        <div style="border: 1px solid #d97706; background: #fffbeb; color: #78350f; padding: 8px; margin-bottom: 12px; font-size: 8pt;">
            <?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.resultado_caixa.col_descricao') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.resultado_caixa.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.resultado_caixa.col_percentual') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
                <?php
                    $type = $row['type'] ?? 'value';
                    $indent = (int) ($row['indent'] ?? 0);
                    $valor = (float) ($row['valor'] ?? 0);
                    $style = $type === 'header'
                        ? 'font-weight: bold; background: #e2e8f0;'
                        : ($type === 'subtotal' ? 'font-weight: bold; border-top: 2px solid #334155;' : '');
                ?>
                <tr>
                    <td style="padding-left: <?= $indent * 20 ?>px; <?= $style ?>"><?= htmlspecialchars($row['label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="right" style="<?= $style ?> <?= $valor < 0 ? 'color: #991b1b;' : '' ?>"><?= currency_format($valor) ?></td>
                    <td class="center" style="<?= $style ?>"><?= isset($row['percentual']) ? number_format((float) $row['percentual'], 1, ',', '.') . '%' : '' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
