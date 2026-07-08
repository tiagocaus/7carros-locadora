<?php if (!empty($items)): ?>
<table class="detail-list-table">
    <thead>
        <tr>
            <th><?= t('modules.relatorios.veicular.lucro_veiculo.detail_data') ?></th>
            <th><?= t('modules.relatorios.veicular.lucro_veiculo.detail_descricao') ?></th>
            <th class="right"><?= t('modules.relatorios.veicular.lucro_veiculo.detail_valor') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= format_date($item['data'] ?? '') ?></td>
            <td><?= htmlspecialchars($item['descricao'] ?? '-') ?></td>
            <td class="right"><?= currency_format($item['valor'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div class="detail-empty"><?= t('modules.relatorios.veicular.lucro_veiculo.detail_empty') ?></div>
<?php endif; ?>
