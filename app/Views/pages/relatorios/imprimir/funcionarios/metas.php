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
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.metas.qtd_funcionarios') ?></div><div class="totals-value"><?= number_format($totals['qtd_funcionarios'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.metas.meta_receita_total') ?></div><div class="totals-value"><?= currency_format($totals['meta_receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.metas.realizado_receita_total') ?></div><div class="totals-value"><?= currency_format($totals['realizado_receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.metas.pct_atingimento_receita') ?></div><div class="totals-value"><?= number_format($totals['pct_atingimento_receita'], 1, ',', '.') ?>%</div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.metas.meta_locacoes_total') ?></div><div class="totals-value"><?= number_format($totals['meta_locacoes_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.funcionarios.metas.realizado_locacoes_total') ?></div><div class="totals-value"><?= number_format($totals['realizado_locacoes_total'], 0, ',', '.') ?></div></td>
        </tr>
    </table>

    <?php if (empty($details)): ?>
    <p style="font-size: 9pt; color: #b45309; padding: 10px; background: #fef3c7; border-radius: 4px;">
        <strong>Sem metas cadastradas.</strong> Não há metas cadastradas para o período selecionado. Cadastre metas em "Metas de Funcionários" antes de gerar este relatório.
    </p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.funcionarios.metas.col_funcionario') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.metas.col_meta_receita') ?></th>
                <th class="right"><?= t('modules.relatorios.funcionarios.metas.col_realizado_receita') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.metas.col_pct_receita') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.metas.col_meta_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.metas.col_realizado_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.funcionarios.metas.col_pct_locacoes') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $bRec = $row['pct_atingimento_receita'] >= 100 ? 'badge-green' : ($row['pct_atingimento_receita'] >= 70 ? 'badge-yellow' : 'badge-red');
                $bLoc = $row['pct_atingimento_locacoes'] >= 100 ? 'badge-green' : ($row['pct_atingimento_locacoes'] >= 70 ? 'badge-yellow' : 'badge-red');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['funcionario']) ?></strong></td>
                <td class="right"><?= currency_format($row['meta_receita']) ?></td>
                <td class="right"><strong><?= currency_format($row['realizado_receita']) ?></strong></td>
                <td class="center"><span class="badge <?= $bRec ?>"><?= number_format($row['pct_atingimento_receita'], 1, ',', '.') ?>%</span></td>
                <td class="center"><?= number_format($row['meta_locacoes'], 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['realizado_locacoes'], 0, ',', '.') ?></td>
                <td class="center"><span class="badge <?= $bLoc ?>"><?= number_format($row['pct_atingimento_locacoes'], 1, ',', '.') ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
