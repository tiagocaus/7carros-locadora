<?php
$prefix = 'modules.relatorios.veicular.historico_odometros.';
$periodoLabel = $dataInicio && $dataFim ? format_date($dataInicio) . ' - ' . format_date($dataFim)
    : ($dataInicio ? t($prefix . 'from') . ' ' . format_date($dataInicio)
        : ($dataFim ? t($prefix . 'until') . ' ' . format_date($dataFim) : t($prefix . 'all_history')));
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style><?php include __DIR__ . '/_css.php'; ?></style>
</head><body>
<?php include __DIR__ . '/_header.php'; ?>
<table class="totals-table"><tr>
<?php foreach (['registros', 'veiculos', 'contratos'] as $key): ?>
<td><div class="totals-label"><?= t($prefix . $key) ?></div><div class="totals-value"><?= (int) $totals[$key] ?></div></td>
<?php endforeach; ?>
</tr></table>
<table class="data-table"><thead><tr>
<?php foreach (['data', 'veiculo', 'contrato', 'cliente', 'filial', 'grupo', 'odometro', 'obs', 'created_at'] as $key): ?>
<th><?= t($prefix . $key) ?></th>
<?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($details as $row): ?>
<tr>
<td><?= e($row['data_referencia'] ? format_operational_datetime($row['data_referencia']) : format_date($row['data'])) ?></td>
<td><?= e(trim(($row['placa'] ?? '') . ' ' . ($row['modelo'] ?? ''))) ?></td>
<td><?= e($row['contrato'] ?? $row['contrato_codigo']) ?></td>
<td><?= e($row['cliente'] ?? '—') ?></td><td><?= e($row['filial'] ?? '—') ?></td><td><?= e($row['grupo'] ?? '—') ?></td>
<td class="right"><?= number_format((int) $row['odometro'], 0, ',', '.') ?></td>
<td><?= nl2br(e($row['obs'] ?? '—')) ?></td>
<td><?= e(format_datetime($row['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$details): ?><tr><td colspan="9"><?= t($prefix . 'empty') ?></td></tr><?php endif; ?>
</tbody></table>
<?php include __DIR__ . '/_footer.php'; ?>
</body></html>
