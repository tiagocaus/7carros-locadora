<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.checklists_realizados.total_checklists') ?></div>
                <div class="totals-value"><?= number_format($totals['total_checklists'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.checklists_realizados.total_itens_ok') ?></div>
                <div class="totals-value" style="color:#15803d;"><?= number_format($totals['total_itens_ok'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.checklists_realizados.total_itens_problema') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['total_itens_problema'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.operacional.checklists_realizados.taxa_problema') ?></div>
                <div class="totals-value"><?= number_format($totals['taxa_problema'], 1, ',', '.') ?>%</div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $momentoLabels = [
            'S' => t('modules.relatorios.operacional.checklists_realizados.momento_saida'),
            'C' => t('modules.relatorios.operacional.checklists_realizados.momento_chegada'),
            'N' => t('modules.relatorios.operacional.checklists_realizados.momento_normal'),
        ];
    ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center"><?= t('modules.relatorios.operacional.checklists_realizados.col_data') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.checklists_realizados.col_momento') ?></th>
                <th><?= t('modules.relatorios.operacional.checklists_realizados.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.operacional.checklists_realizados.col_locacao') ?></th>
                <th><?= t('modules.relatorios.operacional.checklists_realizados.col_cliente') ?></th>
                <th><?= t('modules.relatorios.operacional.checklists_realizados.col_funcionario') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.checklists_realizados.col_ok') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.checklists_realizados.col_problemas') ?></th>
                <th class="center"><?= t('modules.relatorios.operacional.checklists_realizados.col_fotos') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td class="center"><?= !empty($row['data_checklist']) ? format_datetime($row['data_checklist']) : '-' ?></td>
                <td class="center"><?= htmlspecialchars($momentoLabels[$row['momento']] ?? $row['momento']) ?></td>
                <td><strong><?= htmlspecialchars($row['placa'] ?? '-') ?></strong> <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($row['veiculo_modelo'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($row['locacao_codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente_nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['funcionario_nome'] ?? '-') ?></td>
                <td class="center" style="color:#15803d;"><?= number_format($row['itens_ok'] ?? 0, 0, ',', '.') ?></td>
                <td class="center" <?= ($row['itens_problema'] ?? 0) > 0 ? 'style="color:#b91c1c; font-weight:bold;"' : '' ?>><?= number_format($row['itens_problema'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= number_format($row['qtd_fotos'] ?? 0, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
