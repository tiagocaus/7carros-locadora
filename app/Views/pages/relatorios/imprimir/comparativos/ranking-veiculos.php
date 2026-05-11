<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <?php
        $criterio = $totals['criterio'] ?? 'receita';
        $isCurrency = $criterio === 'receita';
        $isPct = $criterio === 'taxa_ocupacao';
        $fmt = function ($v) use ($isCurrency, $isPct) {
            if ($isCurrency) return currency_format($v);
            if ($isPct) return number_format((float) $v, 2, ',', '.') . '%';
            return number_format((int) $v, 0, ',', '.');
        };
        $criterioLabel = match ($criterio) {
            'receita' => 'Receita Gerada',
            'qtd_locacoes' => 'Quantidade de Locações',
            'taxa_ocupacao' => 'Taxa de Ocupação',
            default => $criterio,
        };
    ?>

    <p style="font-size: 8pt; color: #666; margin-bottom: 8px;"><strong>Critério:</strong> <?= htmlspecialchars($criterioLabel) ?></p>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.ranking_veiculos.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.ranking_veiculos.valor_total') ?></div><div class="totals-value"><?= $fmt($totals['valor_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.ranking_veiculos.valor_maximo') ?></div><div class="totals-value"><?= $fmt($totals['valor_maximo']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.ranking_veiculos.valor_medio') ?></div><div class="totals-value"><?= $fmt($totals['valor_medio']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_pos') ?></th>
                <th><?= t('modules.relatorios.comparativos.ranking_veiculos.col_placa') ?></th>
                <th><?= t('modules.relatorios.comparativos.ranking_veiculos.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.comparativos.ranking_veiculos.col_grupo') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_valor') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $badgeClass = $row['ranking'] === 1 ? 'badge-yellow' : ($row['ranking'] <= 3 ? 'badge-blue' : ''); ?>
            <tr>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= $row['ranking'] ?>º</span></td>
                <td><strong><?= htmlspecialchars($row['placa']) ?></strong></td>
                <td><?= htmlspecialchars($row['veiculo']) ?></td>
                <td><?= htmlspecialchars($row['grupo'] ?? '-') ?></td>
                <td class="right"><strong><?= $fmt($row['valor']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
