<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.qtd_investidores') ?></div><div class="totals-value"><?= number_format($totals['qtd_investidores'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.valor_investido') ?></div><div class="totals-value"><?= currency_format($totals['valor_investido']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.receita_gerada') ?></div><div class="totals-value"><?= currency_format($totals['receita_gerada']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.comissao_devida') ?></div><div class="totals-value"><?= currency_format($totals['comissao_devida']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.comissao_paga') ?></div><div class="totals-value"><?= currency_format($totals['comissao_paga']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.fornecedores.investidor.col_investidor') ?></th>
                <th><?= t('modules.relatorios.fornecedores.investidor.col_cnpj') ?></th>
                <th class="center"><?= t('modules.relatorios.fornecedores.investidor.col_veiculos') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_valor_investido') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_receita_gerada') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_devida') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_paga') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_saldo') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $badgeClass = $row['saldo'] > 0 ? 'badge-yellow' : 'badge-green'; ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['investidor']) ?></strong></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '-') ?></td>
                <td class="center"><?= (int) $row['qtd_veiculos'] ?></td>
                <td class="right"><?= currency_format($row['valor_investido']) ?></td>
                <td class="right"><?= currency_format($row['receita_gerada']) ?></td>
                <td class="right"><?= currency_format($row['comissao_devida']) ?></td>
                <td class="right"><?= currency_format($row['comissao_paga']) ?></td>
                <td class="right"><span class="badge <?= $badgeClass ?>"><?= currency_format($row['saldo']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
