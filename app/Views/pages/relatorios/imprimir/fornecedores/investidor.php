<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style><?php include __DIR__ . '/_css.php'; ?></style>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <?php
    $statusLabels = [
        'comissao_gerada' => t('modules.relatorios.fornecedores.investidor.status_comissao_gerada'),
        'sem_fatura_paga' => t('modules.relatorios.fornecedores.investidor.status_sem_fatura_paga'),
        'grupo_sem_comissao' => t('modules.relatorios.fornecedores.investidor.status_grupo_sem_comissao'),
        'comissao_mensal_nao_gerada' => t('modules.relatorios.fornecedores.investidor.status_comissao_mensal_nao_gerada'),
        'veiculo_inativo_com_comissao' => t('modules.relatorios.fornecedores.investidor.status_veiculo_inativo_com_comissao'),
    ];
    $tipoLabels = [
        'percentual_locadora' => t('modules.relatorios.fornecedores.investidor.tipo_percentual_locadora'),
        'fixo_locadora' => t('modules.relatorios.fornecedores.investidor.tipo_fixo_locadora'),
        'fixo_locadora_mensal' => t('modules.relatorios.fornecedores.investidor.tipo_fixo_locadora_mensal'),
        'fixo_investidor_mensal' => t('modules.relatorios.fornecedores.investidor.tipo_fixo_investidor_mensal'),
    ];
    $formatConfig = static function (array $row): string {
        if (empty($row['tipo_comissao'])) {
            return '-';
        }
        if ($row['tipo_comissao'] === 'percentual_locadora') {
            return number_format((float) $row['valor_comissao_config'], 2, ',', '.') . '%';
        }
        return currency_format((float) $row['valor_comissao_config']);
    };
    $modelo = ($modelo ?? 'detalhado') === 'agrupado' ? 'agrupado' : 'detalhado';
    ?>

    <p style="margin-bottom: 10px; padding: 6px 8px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 8pt;">
        <?= htmlspecialchars(t('modules.relatorios.fornecedores.investidor.generated_notice')) ?>
    </p>

    <table class="totals-table">
        <tr>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.qtd_investidores') ?></div><div class="totals-value"><?= number_format($totals['qtd_investidores'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.qtd_veiculos') ?></div><div class="totals-value"><?= number_format($totals['qtd_veiculos'], 0, ',', '.') ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.valor_investido') ?></div><div class="totals-value"><?= currency_format($totals['valor_investido']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.receita_gerada') ?></div><div class="totals-value"><?= currency_format($totals['receita_gerada']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.comissao_devida') ?></div><div class="totals-value"><?= currency_format($totals['comissao_devida']) ?></div></td>
            <td><div class="totals-label"><?= t('modules.relatorios.fornecedores.investidor.comissao_paga') ?></div><div class="totals-value"><?= currency_format($totals['comissao_paga']) ?></div></td>
        </tr>
    </table>

    <?php if (!empty($details)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('modules.relatorios.fornecedores.investidor.col_investidor') ?></th>
                <th><?= t('modules.relatorios.fornecedores.investidor.col_cnpj') ?></th>
                <th class="center"><?= t('modules.relatorios.fornecedores.investidor.col_veiculos') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_valor_investido') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_receita_gerada') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_devida') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_paga') ?></th>
                <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_saldo') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $row): ?>
            <?php $badgeClass = $row['saldo'] > 0 ? 'badge-yellow' : 'badge-green'; ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['investidor']) ?></strong></td>
                <td><?= htmlspecialchars($row['cpf_cnpj'] ?? '-') ?></td>
                <td class="center"><?= (int) $row['qtd_veiculos'] ?></td>
                <td class="right"><?= currency_format($row['valor_investido']) ?></td>
                <td class="right"><?= currency_format($row['receita_gerada']) ?></td>
                <td class="right"><?= currency_format($row['comissao_devida']) ?></td>
                <td class="right"><?= currency_format($row['comissao_paga']) ?></td>
                <td class="right"><span class="badge <?= $badgeClass ?>"><?= currency_format($row['saldo']) ?></span></td>
            </tr>
            <?php if ($modelo === 'detalhado'): ?>
            <tr>
                <td colspan="8" style="padding: 0;">
                    <div style="margin-left: 18px; padding: 6px 8px; background: #f8fafc;">
                        <div style="margin-left: 14px;">
                            <strong style="font-size: 8pt; color: #475569;"><?= t('modules.relatorios.fornecedores.investidor.vehicle_details') ?></strong>
                        </div>
                        <?php if (empty($row['veiculos'])): ?>
                            <p style="margin: 5px 0 0 14px; color: #64748b; font-size: 8pt;">
                                <?= htmlspecialchars(t('modules.relatorios.fornecedores.investidor.no_vehicle_details')) ?>
                            </p>
                        <?php else: ?>
                        <table class="data-table" style="margin-top: 5px; margin-bottom: 0; margin-left: 14px; width: 96%;">
                            <thead>
                                <tr>
                                    <th><?= t('modules.relatorios.fornecedores.investidor.col_placa') ?></th>
                                    <th><?= t('modules.relatorios.fornecedores.investidor.col_veiculo') ?></th>
                                    <th><?= t('modules.relatorios.fornecedores.investidor.col_grupo') ?></th>
                                    <th><?= t('modules.relatorios.fornecedores.investidor.col_tipo_comissao') ?></th>
                                    <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_valor_configurado') ?></th>
                                    <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_receita_gerada') ?></th>
                                    <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_devida') ?></th>
                                    <th class="right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_paga') ?></th>
                                    <th><?= t('modules.relatorios.fornecedores.investidor.col_diagnostico') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($row['veiculos'] as $veiculo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($veiculo['placa'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($veiculo['veiculo'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($veiculo['grupo'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($tipoLabels[$veiculo['tipo_comissao']] ?? '-') ?></td>
                                    <td class="right"><?= $formatConfig($veiculo) ?></td>
                                    <td class="right"><?= currency_format($veiculo['receita_gerada']) ?></td>
                                    <td class="right"><?= currency_format($veiculo['comissao_devida']) ?></td>
                                    <td class="right"><?= currency_format($veiculo['comissao_paga']) ?></td>
                                    <td><?= htmlspecialchars($statusLabels[$veiculo['status_diagnostico']] ?? t('modules.relatorios.fornecedores.investidor.status_desconhecido')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
