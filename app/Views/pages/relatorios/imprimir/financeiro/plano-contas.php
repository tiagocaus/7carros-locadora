<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.plano_contas.total_receitas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_receitas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.plano_contas.total_despesas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_despesas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.plano_contas.total_categorias') ?></div>
                <div class="totals-value"><?= number_format($totals['total_categorias'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.plano_contas.col_codigo') ?></th>
                <th><?= t('modules.relatorios.financeiro.plano_contas.col_descricao') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.plano_contas.col_tipo') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.plano_contas.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.plano_contas.col_percentual') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $tipoBadge = ($row['tipo'] ?? '') === 'R' ? 'badge-green' : 'badge-red'; ?>
            <tr>
                <td><?= htmlspecialchars($row['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '-') ?></td>
                <td class="center">
                    <span class="badge <?= $tipoBadge ?>">
                        <?= ($row['tipo'] ?? '') === 'R'
                            ? t('modules.relatorios.financeiro.tipo_receita')
                            : t('modules.relatorios.financeiro.tipo_despesa') ?>
                    </span>
                </td>
                <td class="right"><?= currency_format($row['valor'] ?? 0) ?></td>
                <td class="center"><?= number_format($row['percentual'] ?? 0, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
