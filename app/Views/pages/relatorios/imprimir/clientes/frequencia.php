<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.frequencia.qtd_clientes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_clientes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.frequencia.classe_frequente') ?></div>
                <div class="totals-value" style="color:#15803d;"><?= number_format($totals['frequente'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.frequencia.classe_regular') ?></div>
                <div class="totals-value"><?= number_format($totals['regular'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.frequencia.classe_esporadico') ?></div>
                <div class="totals-value"><?= number_format($totals['esporadico'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.frequencia.classe_infrequente') ?></div>
                <div class="totals-value" style="color:#b91c1c;"><?= number_format($totals['infrequente'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <?php
        $lista = $details['lista'] ?? [];
        $classeLabels = [
            'frequente' => t('modules.relatorios.clientes.frequencia.classe_frequente'),
            'regular' => t('modules.relatorios.clientes.frequencia.classe_regular'),
            'esporadico' => t('modules.relatorios.clientes.frequencia.classe_esporadico'),
            'infrequente' => t('modules.relatorios.clientes.frequencia.classe_infrequente'),
            'unica' => t('modules.relatorios.clientes.frequencia.classe_unica'),
        ];
    ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.clientes.frequencia.col_cliente') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.frequencia.col_total_locacoes') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.frequencia.col_primeira') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.frequencia.col_ultima') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.frequencia.col_intervalo') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.frequencia.col_classificacao') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td class="center"><?= number_format($row['total_locacoes'] ?? 0, 0, ',', '.') ?></td>
                <td class="center"><?= !empty($row['primeira']) ? format_date($row['primeira']) : '-' ?></td>
                <td class="center"><?= !empty($row['ultima']) ? format_date($row['ultima']) : '-' ?></td>
                <td class="center"><?= isset($row['intervalo_medio']) && $row['intervalo_medio'] !== null ? $row['intervalo_medio'] . 'd' : '-' ?></td>
                <td class="center"><?= htmlspecialchars($classeLabels[$row['classificacao']] ?? $row['classificacao']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
