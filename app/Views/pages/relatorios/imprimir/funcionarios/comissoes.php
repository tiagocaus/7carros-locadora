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
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.comissoes.qtd_funcionarios') ?></div><div class="totals-value"><?= number_format($totals['qtd_funcionarios'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.comissoes.valor_base_total') ?></div><div class="totals-value"><?= currency_format($totals['valor_base_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.comissoes.valor_comissao_total') ?></div><div class="totals-value"><?= currency_format($totals['valor_comissao_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.comissoes.bonus_total') ?></div><div class="totals-value"><?= currency_format($totals['bonus_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.comissoes.pendente_total') ?></div><div class="totals-value"><?= currency_format($totals['pendente_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.comissoes.pago_total') ?></div><div class="totals-value"><?= currency_format($totals['pago_total']) ?></div></td>
        </tr>
    </table>

    <?php if (empty($details)): ?>
    <p style="font-size: 9pt; color: #b45309; padding: 10px; background: #fef3c7; border-radius: 4px;">
        <strong>Sem dados.</strong> Não há comissões cadastradas para o período selecionado. Cadastre comissões em "Comissões de Funcionários" antes de gerar este relatório.
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.funcionarios.comissoes.col_funcionario') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.comissoes.col_qtd') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.comissoes.col_valor_base') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.comissoes.col_pct_comissao') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.comissoes.col_valor_comissao') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.comissoes.col_bonus') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.comissoes.col_valor_total') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.comissoes.col_pendente') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.comissoes.col_pago') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['funcionario']) ?></strong></td>
                <td class="center"><?= (int) $row['qtd'] ?></td>
                <td class="right"><?= currency_format($row['valor_base']) ?></td>
                <td class="center"><?= number_format($row['pct_comissao'], 1, ',', '.') ?>%</td>
                <td class="right"><?= currency_format($row['valor_comissao']) ?></td>
                <td class="right"><?= currency_format($row['bonus']) ?></td>
                <td class="right"><strong><?= currency_format($row['valor_total']) ?></strong></td>
                <td class="right"><?= currency_format($row['pendente']) ?></td>
                <td class="right"><?= currency_format($row['pago']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
