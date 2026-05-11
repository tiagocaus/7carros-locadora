<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <?php
        $periodos = $totals['_periodos'] ?? [];
        $granLabel = match ($totals['granularidade'] ?? 'mes') {
            'dia' => 'Diária',
            'semana' => 'Semanal',
            'mes' => 'Mensal',
            default => '',
        };
    ?>

    <p style="font-size: 8pt; color: #666; margin-bottom: 8px;"><strong>Granularidade:</strong> <?= htmlspecialchars($granLabel) ?></p>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.tendencias.qtd_periodos') ?></div><div class="totals-value"><?= number_format($totals['qtd_periodos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.tendencias.receita_total') ?></div><div class="totals-value"><?= currency_format($totals['receita_total']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.tendencias.qtd_locacoes_total') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes_total'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.tendencias.ticket_medio_geral') ?></div><div class="totals-value"><?= currency_format($totals['ticket_medio_geral']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.comparativos.tendencias.variacao_receita_pct') ?></div><div class="totals-value"><?= number_format($totals['variacao_receita_pct'], 1, ',', '.') ?>%</div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.comparativos.tendencias.col_indicador') ?></th>
                <th class="center"><?= t('modules.relatorios.comparativos.tendencias.col_tendencia') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.tendencias.col_variacao') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.tendencias.col_inicio') ?></th>
                <th class="right"><?= t('modules.relatorios.comparativos.tendencias.col_fim') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $badgeClass = $row['tendencia'] === 'up' ? 'badge-green' : ($row['tendencia'] === 'down' ? 'badge-red' : 'badge-blue');
                $arrow = $row['tendencia'] === 'up' ? '↑ Crescimento' : ($row['tendencia'] === 'down' ? '↓ Queda' : '→ Estável');
                $serie = $row['serie'] ?? [];
                $inicio = !empty($serie) ? $serie[0] : 0;
                $fim = !empty($serie) ? end($serie) : 0;
                $fmt = fn($v) => $row['is_currency'] ? currency_format($v) : number_format((float) $v, 0, ',', '.');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['indicador']) ?></strong></td>
                <td class="center"><span class="badge <?= $badgeClass ?>"><?= $arrow ?></span></td>
                <td class="right"><strong><?= number_format($row['variacao_pct'], 1, ',', '.') ?>%</strong></td>
                <td class="right"><?= $fmt($inicio) ?></td>
                <td class="right"><?= $fmt($fim) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($periodos) && !empty($details[0]['serie'] ?? [])): ?>
    <h3 style="font-size: 9pt; margin-top: 12px; margin-bottom: 4px; color: #555;">Série temporal de Receita por período</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Período</th>
                <th class="right">Receita</th>
                <th class="right">Locações</th>
                <th class="right">Ticket Médio</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($periodos as $i => $p): ?>
            <tr>
                <td><?= htmlspecialchars($p) ?></td>
                <td class="right"><?= currency_format($details[0]['serie'][$i] ?? 0) ?></td>
                <td class="right"><?= number_format($details[1]['serie'][$i] ?? 0, 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($details[2]['serie'][$i] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
