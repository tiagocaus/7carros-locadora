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
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.veiculo_cliente.qtd_locacoes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_locacoes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.veiculo_cliente.qtd_clientes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_clientes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.veiculo_cliente.receita_total') ?></div>
                <div class="totals-value"><?= currency_format($totals['receita_total']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.veiculo_cliente.dias_total') ?></div>
                <div class="totals-value"><?= number_format($totals['dias_total'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.veicular.veiculo_cliente.km_total') ?></div>
                <div class="totals-value"><?= number_format($totals['km_total'], 0, ',', '.') ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.veicular.veiculo_cliente.col_tipo') ?></th>
                <th><?= t('modules.relatorios.veicular.veiculo_cliente.col_codigo') ?></th>
                <th><?= t('modules.relatorios.veicular.veiculo_cliente.col_placa') ?></th>
                <th><?= t('modules.relatorios.veicular.veiculo_cliente.col_veiculo') ?></th>
                <th><?= t('modules.relatorios.veicular.veiculo_cliente.col_cliente') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.veiculo_cliente.col_data_inicio') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.veiculo_cliente.col_data_fim') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.veiculo_cliente.col_dias') ?></th>
                <th class="center"><?= t('modules.relatorios.veicular.veiculo_cliente.col_km_rodado') ?></th>
                <th class="right"><?= t('modules.relatorios.veicular.veiculo_cliente.col_valor') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $badgeClass = $row['tipo'] === 'Locação' ? 'badge-blue' : 'badge-green'; ?>
            <tr>
                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['tipo']) ?></span></td>
                <td><?= htmlspecialchars($row['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['placa'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['veiculo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cliente'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['data_inicio']) ? format_date($row['data_inicio']) : '-' ?></td>
                <td class="center"><?= !empty($row['data_fim']) ? format_date($row['data_fim']) : 'Em uso' ?></td>
                <td class="center"><?= (int) $row['dias'] ?></td>
                <td class="center"><?= number_format((int) $row['km_rodado'], 0, ',', '.') ?></td>
                <td class="right"><?= currency_format($row['valor']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
