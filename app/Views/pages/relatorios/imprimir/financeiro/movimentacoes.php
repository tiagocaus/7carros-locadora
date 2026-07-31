<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.movimentacoes.total_receitas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_receitas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.movimentacoes.total_despesas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_despesas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.movimentacoes.saldo') ?></div>
                <div class="totals-value"><?= currency_format($totals['saldo']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.movimentacoes.quantidade') ?></div>
                <div class="totals-value"><?= number_format($totals['quantidade'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.movimentacoes.col_data') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.movimentacoes.col_tipo') ?></th>
                <th><?= t('modules.relatorios.financeiro.movimentacoes.col_categoria') ?></th>
                <th><?= t('modules.relatorios.financeiro.movimentacoes.col_descricao') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.movimentacoes.col_valor') ?></th>
                <th class="center"><?= t('modules.relatorios.financeiro.movimentacoes.col_status') ?></th>
                <th><?= t('modules.relatorios.financeiro.movimentacoes.col_conta') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $tipoBadge = ($row['tipo'] ?? '') === 'R' ? 'badge-green' : 'badge-red';
                $tipoLabel = ($row['tipo'] ?? '') === 'R'
                    ? t('modules.relatorios.financeiro.movimentacoes.tipo_receita')
                    : t('modules.relatorios.financeiro.movimentacoes.tipo_despesa');
                $statusBadge = ($row['status'] ?? '') === 'pago' ? 'badge-green' : 'badge-yellow';
                $statusLabel = ($row['status'] ?? '') === 'pago'
                    ? t('modules.relatorios.financeiro.movimentacoes.status_pago')
                    : t('modules.relatorios.financeiro.movimentacoes.status_pendente');
            ?>
            <tr>
                <td><?= format_date($row['data'] ?? '') ?></td>
                <td class="center"><span class="badge <?= $tipoBadge ?>"><?= $tipoLabel ?></span></td>
                <td><?= htmlspecialchars($row['categoria'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['descricao'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['valor'] ?? 0) ?></td>
                <td class="center"><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                <td><?= htmlspecialchars($row['conta'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
