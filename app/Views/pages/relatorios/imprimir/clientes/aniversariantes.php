<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?></style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>

    <table class="totals-table">
        <tr>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.aniversariantes.qtd_aniversariantes') ?></div>
                <div class="totals-value"><?= number_format($totals['qtd_aniversariantes'], 0, ',', '.') ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.clientes.aniversariantes.idade_media') ?></div>
                <div class="totals-value"><?= number_format($totals['idade_media'], 1, ',', '.') ?></div>
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
                <th><?= t('modules.relatorios.clientes.aniversariantes.col_cliente') ?></th>
                <th><?= t('modules.relatorios.clientes.aniversariantes.col_cpf_cnpj') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.aniversariantes.col_nascimento') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.aniversariantes.col_idade') ?></th>
                <th><?= t('modules.relatorios.clientes.aniversariantes.col_telefone') ?></th>
                <th><?= t('modules.relatorios.clientes.aniversariantes.col_email') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.aniversariantes.col_ultima') ?></th>
                <th class="center"><?= t('modules.relatorios.clientes.aniversariantes.col_total_locacoes') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lista as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '') ?></td>
                <td class="center"><?= !empty($row['nascimento']) ? format_date($row['nascimento']) : '-' ?></td>
                <td class="center"><?= number_format($row['idade'] ?? 0, 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($row['telefone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                <td class="center"><?= !empty($row['ultima_locacao']) ? format_date($row['ultima_locacao']) : '-' ?></td>
                <td class="center"><?= number_format($row['total_locacoes'] ?? 0, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
