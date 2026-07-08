<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
    <style>
        .detail-list-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .detail-list-table th,
        .detail-list-table td { border: 1px solid #e5e7eb; padding: 4px; font-size: 8pt; }
        .detail-title { font-weight: bold; margin: 4px 0; color: #334155; }
        .detail-empty { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <?php
        $exibicao = $exibicao ?? 'simples';
        $isDetalhado = in_array($exibicao, ['detalhado', 'super_detalhado'], true);
        $isSuperDetalhado = $exibicao === 'super_detalhado';
        $colspan = $isDetalhado ? 10 : 7;
    ?>
    <?php include __DIR__ . '/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.qtd_veiculos') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.receita_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.despesa_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['despesa_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.lucro_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['lucro_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.lucro_veiculo.margem_geral') ?></div>
                <div class="totals-value"><?= number_format($totals['margem_geral'], 1, ',', '.') ?>%</div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.lucro_veiculo.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.lucro_veiculo.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.lucro_veiculo.col_grupo') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_receita') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_despesa') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_lucro') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_margem') ?></th>
                <?php if ($isDetalhado): ?>
                <th class="center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_ocupacao') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_manutencoes') ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['margem'] >= 30 ? 'badge-green' : ($row['margem'] >= 10 ? 'badge-yellow' : 'badge-red');
                $ocupacao = (float) ($row['ocupacao'] ?? 0);
                $ocupacaoBadgeClass = $ocupacao >= 70 ? 'badge-green' : ($ocupacao >= 50 ? 'badge-yellow' : 'badge-red');
            ?>
            <tr>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><?= currency_format($row['receita']) ?></td>
                <td class="right"><?= currency_format($row['despesa_total']) ?></td>
                <td class="right"><?= currency_format($row['lucro']) ?></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= number_format($row['margem'], 1, ',', '.') ?>%</span></td>
                <?php if ($isDetalhado): ?>
                <td class="center"><span class="badge <?= $ocupacaoBadgeClass ?>"><?= number_format($ocupacao, 1, ',', '.') ?>%</span></td>
                <td class="center"><?= number_format((int) ($row['locacoes'] ?? 0), 0, ',', '.') ?></td>
                <td class="center"><?= number_format((int) ($row['manutencoes_qtd'] ?? 0), 0, ',', '.') ?></td>
                <?php endif; ?>
            </tr>
            <?php if ($isSuperDetalhado): ?>
            <tr>
                <td colspan="<?= $colspan ?>">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 50%; vertical-align: top; border: 0; padding: 2px 6px 2px 0;">
                                <div class="detail-title"><?= t('modules.relatorios.veicular.lucro_veiculo.col_receitas_detalhe') ?></div>
                                <?php $items = $row['receitas_detalhe'] ?? []; ?>
                                <?php include __DIR__ . '/_lucro-veiculo-detalhe-lista.php'; ?>
                            </td>
                            <td style="width: 50%; vertical-align: top; border: 0; padding: 2px 0 2px 6px;">
                                <div class="detail-title"><?= t('modules.relatorios.veicular.lucro_veiculo.col_despesas_detalhe') ?></div>
                                <?php $items = $row['despesas_detalhe'] ?? []; ?>
                                <?php include __DIR__ . '/_lucro-veiculo-detalhe-lista.php'; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
