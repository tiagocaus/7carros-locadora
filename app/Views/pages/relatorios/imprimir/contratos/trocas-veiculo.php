<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.trocas_veiculo.qtd_trocas') ?></div><div class="totals-value"><?= number_format($totals['qtd_trocas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.trocas_veiculo.qtd_locacoes_afetadas') ?></div><div class="totals-value"><?= number_format($totals['qtd_locacoes_afetadas'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.trocas_veiculo.media_diferenca') ?></div><div class="totals-value" style="color:<?= $totals['media_diferenca'] >= 0 ? '#16a34a' : '#b91c1c' ?>;"><?= currency_format($totals['media_diferenca']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.contratos.trocas_veiculo.soma_diferenca') ?></div><div class="totals-value" style="color:<?= $totals['soma_diferenca'] >= 0 ? '#16a34a' : '#b91c1c' ?>;"><?= currency_format($totals['soma_diferenca']) ?></div></td>
        </tr>
    </table>

    <?php $lista = $details['lista'] ?? []; ?>
    <?php if (empty($lista)): ?>
        <p style="font-size: 9pt; color: #64748b; padding: 10px;"><?= t('modules.relatorios.common.no_data') ?></p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.contratos.trocas_veiculo.col_contrato') ?></th>
                <th><?= t('modules.relatorios.contratos.trocas_veiculo.col_cliente') ?></th>
                <th><?= t('modules.relatorios.contratos.trocas_veiculo.col_veiculo_old') ?></th>
                <th><?= t('modules.relatorios.contratos.trocas_veiculo.col_veiculo_new') ?></th>
                <th class="center"><?= t('modules.relatorios.contratos.trocas_veiculo.col_data_troca') ?></th>
                <th><?= t('modules.relatorios.contratos.trocas_veiculo.col_motivo') ?></th>
                <th class="right"><?= t('modules.relatorios.contratos.trocas_veiculo.col_diferenca') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $r): ?>
            <?php $dif = (float) ($r['diferenca'] ?? 0); $cor = $dif > 0 ? '#16a34a' : ($dif < 0 ? '#b91c1c' : '#64748b'); $sinal = $dif > 0 ? '+' : ''; ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['codigo'] ?? '-') ?></strong></td>
                <td><?= htmlspecialchars($r['cliente'] ?? '-') ?></td>
                <td>
                    <strong><?= htmlspecialchars($r['veiculo_old_placa'] ?? '-') ?></strong>
                    <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($r['veiculo_old_descricao'] ?? '') ?></span>
                </td>
                <td>
                    <strong><?= htmlspecialchars($r['veiculo_new_placa'] ?? '-') ?></strong>
                    <span style="color:#64748b; font-size:8pt;"><?= htmlspecialchars($r['veiculo_new_descricao'] ?? '') ?></span>
                </td>
                <td class="center"><?= !empty($r['data_troca']) ? format_date($r['data_troca']) : '-' ?></td>
                <td><?= htmlspecialchars($r['motivo'] ?? '-') ?></td>
                <td class="right" style="color:<?= $cor ?>; font-weight:bold;"><?= $sinal . currency_format($dif) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
