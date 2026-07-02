<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style><?php include __DIR__ . '/../kpis/_css.php'; ?> .descricao-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 230px; max-width: 230px; }</style></head>
<body>
    <?php include __DIR__ . '/../kpis/_header.php'; ?>
    <?php $considerarSaldoInicial = !in_array($totals['considerar_saldo_inicial'] ?? true, [false, 0, '0'], true); ?>

    <!-- Totalizadores -->
    <table class="totals-table">
        <tr>
            <?php if ($considerarSaldoInicial): ?>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.saldo_inicial') ?></div>
                <div class="totals-value"><?= currency_format($totals['saldo_inicial']) ?></div>
            </td>
            <?php endif; ?>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.total_entradas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_entradas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.total_saidas') ?></div>
                <div class="totals-value"><?= currency_format($totals['total_saidas']) ?></div>
            </td>
            <td>
                <div class="totals-label"><?= t('modules.relatorios.financeiro.livro_caixa.saldo_final') ?></div>
                <div class="totals-value"><?= currency_format($totals['saldo_final']) ?></div>
            </td>
        </tr>
    </table>

    <!-- Tabela detalhada -->
    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_data') ?></th>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_pessoa') ?></th>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_descricao') ?></th>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_conta') ?></th>
                <th><?= t('modules.relatorios.financeiro.livro_caixa.col_forma_pagamento') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.livro_caixa.col_entrada') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.livro_caixa.col_saida') ?></th>
                <th class="right"><?= t('modules.relatorios.financeiro.livro_caixa.col_saldo') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php
                $pessoaLabel = ($row['pessoa_tipo'] ?? '') === 'cliente'
                    ? t('modules.relatorios.financeiro.livro_caixa.pessoa_cliente')
                    : ((($row['pessoa_tipo'] ?? '') === 'fornecedor') ? t('modules.relatorios.financeiro.livro_caixa.pessoa_fornecedor') : '');
                $pessoa = !empty($row['pessoa_nome']) && $pessoaLabel !== ''
                    ? $pessoaLabel . ': ' . $row['pessoa_nome']
                    : '-';
                $descricao = $row['descricao'] ?? $row['historico'] ?? '-';
                $conta = $row['conta'] ?? '-';
                $formaPagamento = $row['forma_pagamento'] ?? '-';
            ?>
            <tr>
                <td><?= format_date($row['data'] ?? '') ?></td>
                <td><?= htmlspecialchars($pessoa) ?></td>
                <td class="descricao-cell"><?= htmlspecialchars($descricao) ?></td>
                <td><?= htmlspecialchars($conta) ?></td>
                <td><?= htmlspecialchars($formaPagamento) ?></td>
                <td class="right" style="color: #166534;"><?= ($row['entrada'] ?? 0) > 0 ? currency_format($row['entrada']) : '-' ?></td>
                <td class="right" style="color: #991b1b;"><?= ($row['saida'] ?? 0) > 0 ? currency_format($row['saida']) : '-' ?></td>
                <td class="right" style="font-weight: bold;"><?= currency_format($row['saldo'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/../kpis/_footer.php'; ?>
</body>
</html>
