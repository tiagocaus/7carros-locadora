<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.inativos.qtd_inativos') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['qtd_inativos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.inativos.qtd_nunca_locaram') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_nunca_locaram'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.inativos.media_dias_inativo') ?></div>
                <div class="totals-value"><?= number_format($totals['media_dias_inativo'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.inativos.faturamento_perdido') ?></div>
                <div class="totals-value"><?= currency_format($totals['faturamento_perdido']) ?></div>
            </td>
        </tr>
    </table>

    <?php $lista = $details['lista'] ?? []; ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.clientes.inativos.col_cliente') ?></th>
                <th><?= t('modules.relatorios.clientes.inativos.col_cpf_cnpj') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.inativos.col_ultima_locacao') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.inativos.col_dias_inativo') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.inativos.col_total_locacoes') ?></th>
                <th class="right"><?= t('modules.relatorios.clientes.inativos.col_faturamento') ?></th>
                <th><?= t('modules.relatorios.clientes.inativos.col_telefone') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '') ?></td>
                <td class="center"><?= !empty($row['nunca_locou']) ? t('modules.relatorios.clientes.inativos.nunca_locou') : (!empty($row['ultima_locacao']) ? format_date($row['ultima_locacao']) : '-') ?></td>
                <td class="center"><?= !empty($row['nunca_locou']) ? '-' : (($row['dias_inativo'] ?? 0) . 'd') ?></td>
                <td class="center"><?= number_format($row['total_locacoes'] ?? 0, 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['faturamento'] ?? 0) ?></td>
                <td><?= htmlspecialchars($row['telefone'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
