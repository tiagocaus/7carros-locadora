<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.combustivel.total_locacoes') ?></div>
                <div class="totals-value"><?= number_format($totals['total_locacoes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.combustivel.qtd_com_diferenca') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['qtd_com_diferenca'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.combustivel.taxa_diferenca') ?></div>
                <div class="totals-value"><?= number_format($totals['taxa_diferenca'], 1, ',', '.') ?>%</div>
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
                <th><?= t('modules.relatorios.operacional.combustivel.col_codigo') ?></th>
                <th><?= t('modules.relatorios.operacional.combustivel.col_cliente') ?></th>
                <th><?= t('modules.relatorios.operacional.combustivel.col_veiculo') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.combustivel.col_nivel_saida') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.combustivel.col_nivel_chegada') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.combustivel.col_diferenca') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.combustivel.col_data') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <?php
                $dif = $row['diferenca'] ?? null;
                $difTxt = $dif === null ? '-' : (($dif > 0 ? '+' : '') . $dif);
                $cls = !empty($row['tem_diferenca']) ? 'style="color:#b91c1c; font-weight:bold;"' : 'style="color:#64748b;"';
            ?>
            <tr>
                <td><?= htmlspecialchars($row['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente_nome'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td class="center"><?= htmlspecialchars($row['nivel_saida'] ?? '-') ?></td>
                <td class="center"><?= htmlspecialchars($row['nivel_chegada'] ?? '-') ?></td>
                <td class="center" <?= $cls ?>><?= htmlspecialchars((string) $difTxt) ?></td>
                <td class="center"><?= !empty($row['data_chegada']) ? format_date($row['data_chegada']) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
