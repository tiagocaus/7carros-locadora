<?php
/**
 * Partial: Conteudo completo da fatura de locacao
 *
 * Variaveis esperadas do controller:
 *   $locacao, $empresa, $veiculo, $assinatura, $logoPath, $qrPath, $taxas
 *
 * Flag de controle (definida pelo arquivo principal antes do include):
 *   $_faturaStandalone (bool) - true: usa htmlpagefooter do mPDF; false: assinatura inline
 */
$_faturaStandalone = $_faturaStandalone ?? false;
$_faturaDocTitulo = $_faturaDocTitulo ?? t('modules.locacoes.pdf.invoice_title');
$_faturaDadosTitulo = $_faturaDadosTitulo ?? t('modules.locacoes.pdf.rental_data');
$_faturaTotalRegistroLabel = $_faturaTotalRegistroLabel ?? t('modules.locacoes.pdf.total_rental_label');
$_formatarOdometroFatura = static function($valor): string {
    if ($valor === null || $valor === '') {
        return '-';
    }

    return number_format((int) $valor, 0, ',', '.') . ' km';
};
$_formatarCombustivelFatura = static function($nivel) use (&$locacao): string {
    if ($nivel === null || $nivel === '') {
        return '-';
    }

    $labels = [
        8 => t('modules.locacoes.fuel_levels.full'),
        7 => '7/8',
        6 => '3/4',
        5 => '5/8',
        4 => '1/2',
        3 => '3/8',
        2 => '1/4',
        1 => '1/8',
        0 => t('modules.locacoes.fuel_levels.reserve'),
    ];

    if (($locacao['veiculo_tipo_combustivel'] ?? '') === 'HE') {
        $labels = [
            8 => '100%',
            7 => '87%',
            6 => '75%',
            5 => '62%',
            4 => '50%',
            3 => '37%',
            2 => '25%',
            1 => '12%',
            0 => '0%',
        ];
    }

    return $labels[(int) $nivel] ?? '-';
};
$_formatarDataFatura = static function($valor, bool $comHora = false): string {
    if (empty($valor)) {
        return '-';
    }

    $formatado = $comHora ? format_operational_datetime((string) $valor) : format_date((string) $valor);

    return $formatado !== '' ? $formatado : '-';
};
$_formatarVeiculoFatura = static function(array $item): string {
    $placa = trim((string) ($item['veiculo_placa'] ?? ''));
    $nome = trim((string) (($item['veiculo_marca'] ?? '') . ' ' . ($item['veiculo_modelo'] ?? '')));
    $grupo = trim((string) ($item['grupo_nome'] ?? ''));

    if ($placa !== '' || $nome !== '') {
        return trim($placa . ($nome !== '' ? ' - ' . $nome : ''));
    }

    return $grupo !== '' ? t('modules.locacoes.pdf.group_category_label') . ': ' . $grupo : '-';
};

$_dataDevolucaoFatura = !empty($locacao['data_chegada'])
    ? $locacao['data_chegada']
    : ($locacao['data_prevista'] ?? null);
$_labelDevolucaoFatura = !empty($locacao['data_chegada'])
    ? t('modules.locacoes.fields.return_date') . ':'
    : t('modules.locacoes.pdf.expected_return_label');
?>

<!-- HEADER -->
<?php $_docTitulo = $_faturaDocTitulo; include __DIR__ . '/_header.php'; ?>

<!-- DADOS DO CLIENTE -->
<div class="section">
    <div class="section-title"><?= t('modules.locacoes.pdf.client_data') ?></div>
    <table class="data-table">
        <tr>
            <td style="width: 15%;"><strong><?= t('modules.locacoes.pdf.client_label') ?></strong></td>
            <td style="width: 35%;"><?= htmlspecialchars($locacao['cliente_nome_completo'] ?? '-') ?></td>
            <td style="width: 15%;"><strong><?= t('modules.locacoes.pdf.cpf_cnpj_label') ?></strong></td>
            <td style="width: 35%;"><?= htmlspecialchars($locacao['cliente_cpf_cnpj'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong><?= t('modules.locacoes.pdf.phone_label') ?></strong></td>
            <td><?= htmlspecialchars($locacao['cliente_telefone'] ?? '-') ?></td>
            <td><strong><?= t('modules.locacoes.pdf.email_label') ?></strong></td>
            <td><?= htmlspecialchars($locacao['cliente_email'] ?? '-') ?></td>
        </tr>
        <?php if (!empty($locacao['cliente_endereco_completo'])): ?>
        <tr>
            <td><strong><?= t('modules.locacoes.pdf.address_label') ?></strong></td>
            <td colspan="3"><?= htmlspecialchars($locacao['cliente_endereco_completo']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- DADOS DA LOCACAO -->
<div class="section">
    <div class="section-title"><?= $_faturaDadosTitulo ?></div>
    <table class="data-table">
        <tr>
            <td style="width: 25%;"><strong><?= t('modules.locacoes.pdf.checkout_label') ?></strong></td>
            <td style="width: 25%;"><?= $_formatarDataFatura($locacao['data_saida'] ?? null, true) ?></td>
            <td style="width: 25%;"><strong><?= $_labelDevolucaoFatura ?></strong></td>
            <td style="width: 25%;"><?= $_formatarDataFatura($_dataDevolucaoFatura, true) ?></td>
        </tr>
        <tr>
            <td><strong><?= t('modules.locacoes.odometer_fuel.odometer_out') ?></strong></td>
            <td><?= $_formatarOdometroFatura($locacao['odometro_ini'] ?? null) ?></td>
            <td><strong><?= t('modules.locacoes.odometer_fuel.odometer_return') ?></strong></td>
            <td><?= $_formatarOdometroFatura($locacao['odometro_fim'] ?? null) ?></td>
        </tr>
        <tr>
            <td><strong><?= t('modules.locacoes.odometer_fuel.fuel_out') ?></strong></td>
            <td><?= $_formatarCombustivelFatura($locacao['combustivel_ini'] ?? null) ?></td>
            <td><strong><?= t('modules.locacoes.odometer_fuel.fuel_return') ?></strong></td>
            <td><?= $_formatarCombustivelFatura($locacao['combustivel_fim'] ?? null) ?></td>
        </tr>
        <tr>
            <td><strong><?= t('modules.locacoes.pdf.days_label') ?></strong></td>
            <td><?= (int) ($locacao['dias'] ?? $locacao['quantidade_dias'] ?? 0) ?></td>
            <td><strong><?= t('modules.locacoes.pdf.method_label') ?></strong></td>
            <td><?= htmlspecialchars($locacao['forma_pagamento_descricao'] ?? '-') ?></td>
        </tr>
    </table>
</div>

<!-- COMPOSICAO DA FATURA -->
<?php
    $diasFatura = max(1, (int) ($locacao['dias'] ?? $locacao['quantidade_dias'] ?? 1));
    $planoComposicao = match($locacao['plano'] ?? 'KL') {
        'KL' => t('modules.locacoes.plans.km_free'),
        'KMC' => t('modules.locacoes.plans.km_controlled'),
        'DI' => t('modules.locacoes.plans.km_paid'),
        default => $locacao['plano'] ?? '-'
    };
    $valorPlanoComposicao = match($locacao['plano'] ?? 'KL') {
        'KL' => (float) ($locacao['km_livre_valor'] ?? 0),
        'KMC' => (float) ($locacao['km_controlado_valor'] ?? 0),
        'DI' => (float) ($locacao['diaria_valor'] ?? 0),
        default => 0
    };
    $linhasFatura = [];
    $veiculoPlacaFatura = trim((string) ($veiculo['placa'] ?? $locacao['veiculo_placa'] ?? ''));
    $veiculoNomeFatura = trim((string) (($veiculo['marca'] ?? '') . ' ' . ($veiculo['modelo'] ?? '')));
    $veiculoReferenciaFatura = trim($veiculoPlacaFatura . ' ' . $veiculoNomeFatura);
    if ($veiculoReferenciaFatura === '') {
        $veiculoReferenciaFatura = trim((string) ($locacao['veiculo_info'] ?? ''));
    }
    if ($veiculoReferenciaFatura === '' && !empty($locacao['grupo_nome'])) {
        $veiculoReferenciaFatura = t('modules.locacoes.pdf.group_category_label') . ': ' . trim((string) $locacao['grupo_nome']);
    }
    $descricaoPlanoFatura = trim($planoComposicao . ($veiculoReferenciaFatura !== '' ? ' - ' . $veiculoReferenciaFatura : ''));

    if ($valorPlanoComposicao > 0) {
        $kmFranquiaFatura = (int) ($locacao['km_controlado_franquia'] ?? 0);
        $mostrarInfoKmFranquia = in_array(($locacao['status'] ?? ''), ['R', 'A'], true)
            && ($locacao['plano'] ?? '') === 'KMC'
            && $kmFranquiaFatura > 0;

        $linhasFatura[] = [
            'descricao' => $descricaoPlanoFatura,
            'qtd' => t_choice('modules.locacoes.pdf.day_count', $diasFatura),
            'unitario' => $valorPlanoComposicao,
            'total' => $valorPlanoComposicao * $diasFatura,
            'km_franquia_info' => $mostrarInfoKmFranquia ? t('modules.locacoes.pdf.km_allowance_info', [
                'franquia' => number_format($kmFranquiaFatura, 0, ',', '.') . 'km',
                'unidade' => t('modules.locacoes.pdf.km_allowance_unit_day'),
                'total' => number_format($kmFranquiaFatura * $diasFatura, 0, ',', '.') . 'Km',
            ]) : null,
        ];
    }

    if (($locacao['seguro_carro'] ?? 'N') === 'S') {
        $valor = (float) ($locacao['seguro_carro_valor'] ?? 0);
        if ($valor > 0) {
            $linhasFatura[] = [
                'descricao' => t('modules.locacoes.pdf.vehicle_insurance_header'),
                'qtd' => t_choice('modules.locacoes.pdf.day_count', $diasFatura),
                'unitario' => $valor,
                'total' => $valor * $diasFatura,
            ];
        }
    }

    if (($locacao['seguro_terceiros'] ?? 'N') === 'S') {
        $valor = (float) ($locacao['seguro_terceiros_valor'] ?? 0);
        if ($valor > 0) {
            $linhasFatura[] = [
                'descricao' => t('modules.locacoes.pdf.third_party_insurance_header'),
                'qtd' => t_choice('modules.locacoes.pdf.day_count', $diasFatura),
                'unitario' => $valor,
                'total' => $valor * $diasFatura,
            ];
        }
    }

    foreach (($taxas ?? []) as $taxa) {
        $linhasFatura[] = [
            'descricao' => $taxa['nome'] ?? '-',
            'qtd' => (string) ((int) ($taxa['quantidade'] ?? 1)),
            'unitario' => (float) ($taxa['valor_unitario'] ?? 0),
            'total' => (float) ($taxa['valor_total'] ?? 0),
        ];
    }

    $totaisResumoFatura = is_array($totaisResumoFatura ?? null) ? $totaisResumoFatura : [];
    $kmExcedenteFatura = (int) ($totaisResumoFatura['km_excedente'] ?? $locacao['kmlExcedente'] ?? $locacao['km_excedente'] ?? 0);
    $valorKmExcedenteFatura = (float) ($totaisResumoFatura['valor_km_excedente'] ?? $locacao['km_valor'] ?? 0);
    if ($kmExcedenteFatura > 0 && $valorKmExcedenteFatura > 0) {
        $linhasFatura[] = [
            'descricao' => t('modules.locacoes.pdf.km_excess_label'),
            'qtd' => $kmExcedenteFatura . ' km',
            'unitario' => $valorKmExcedenteFatura,
            'total' => $kmExcedenteFatura * $valorKmExcedenteFatura,
        ];
    }

    $combustivelValorFatura = (float) ($totaisResumoFatura['total_combustivel'] ?? $locacao['combustivel_valor'] ?? 0);
    if ($combustivelValorFatura > 0) {
        $combustivelUsado = max(1, (int) ($totaisResumoFatura['combustivel_usado'] ?? $locacao['combustivel_usado'] ?? 1));
        $valorCombustivelUnitario = (float) ($totaisResumoFatura['valor_combustivel_unitario'] ?? 0);
        $linhasFatura[] = [
            'descricao' => t('modules.locacoes.pdf.fuel_charge_label') . ' - ' . $_formatarCombustivelFatura($locacao['combustivel_ini'] ?? null) . ' ' . t('modules.locacoes.pdf.to_label') . ' ' . $_formatarCombustivelFatura($locacao['combustivel_fim'] ?? null),
            'qtd' => t_choice('modules.locacoes.pdf.fraction_count', $combustivelUsado),
            'unitario' => $valorCombustivelUnitario > 0 ? $valorCombustivelUnitario : $combustivelValorFatura / $combustivelUsado,
            'total' => $combustivelValorFatura,
        ];
    }

    foreach (($multas ?? []) as $multa) {
        $valorMulta = (float) ($multa['valor'] ?? 0);
        $descricaoMulta = ($multa['descri'] ?? '') ?: (($multa['n_infracao'] ?? '') ?: ($multa['numero_ait'] ?? '-'));
        $linhasFatura[] = [
            'descricao' => t('modules.locacoes.pdf.fine_label') . ': ' . $descricaoMulta,
            'qtd' => t_choice('modules.locacoes.pdf.fine_count', 1),
            'unitario' => $valorMulta,
            'total' => $valorMulta,
        ];
    }

    $kmRodadosFatura = null;
    if (($locacao['odometro_fim'] ?? null) !== null && ($locacao['odometro_ini'] ?? null) !== null) {
        $kmRodadosFatura = max(0, (int) $locacao['odometro_fim'] - (int) $locacao['odometro_ini']);
    }
?>
<?php if (!empty($linhasFatura)): ?>
<div class="section">
    <div class="section-title"><?= t('modules.locacoes.pdf.invoice_composition') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 46%;"><?= t('modules.locacoes.pdf.description_header') ?></th>
                <th style="width: 14%;" class="text-center"><?= t('modules.locacoes.pdf.qty_header') ?></th>
                <th style="width: 20%;" class="text-right"><?= t('modules.locacoes.pdf.unit_value_header') ?></th>
                <th style="width: 20%;" class="text-right"><?= t('modules.locacoes.pdf.total_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($linhasFatura as $linha): ?>
            <tr<?= !empty($linha['km_franquia_info']) ? ' class="has-km-franquia"' : '' ?>>
                <td><?= htmlspecialchars($linha['descricao']) ?></td>
                <td class="text-center"><?= htmlspecialchars($linha['qtd']) ?></td>
                <td class="text-right"><?= currency_format((float) $linha['unitario']) ?></td>
                <td class="text-right"><?= currency_format((float) $linha['total']) ?></td>
            </tr>
            <?php if (!empty($linha['km_franquia_info'])): ?>
            <tr class="km-franquia-row">
                <td class="km-franquia-info"><?= htmlspecialchars($linha['km_franquia_info']) ?></td>
                <td class="text-center">&nbsp;</td>
                <td class="text-right">&nbsp;</td>
                <td class="text-right">&nbsp;</td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($kmRodadosFatura !== null): ?>
            <tr>
                <td colspan="4" style="font-size: 8pt; color: #666;">
                    <?= t('modules.locacoes.pdf.odometer_label') ?>: <?= number_format((int) $locacao['odometro_ini'], 0, ',', '.') ?>
                    <?= t('modules.locacoes.pdf.to_label') ?> <?= number_format((int) $locacao['odometro_fim'], 0, ',', '.') ?>
                    (<?= number_format($kmRodadosFatura, 0, ',', '.') ?> km)
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- TOTAIS -->
<?php
    $totalLocacaoPdf = (float) ($locacao['total_pagar'] ?? $locacao['valor_total'] ?? 0);
    $totalMultasPdf = (float) ($totalMultas ?? 0);
    $valorDescontoPdf = (float) ($locacao['valor_desconto'] ?? 0);
    $codigoPromocaoPdf = trim((string) ($locacao['promocao_codigo'] ?? ''));
    $descontoLabelPdf = rtrim(t('modules.locacoes.pdf.discount_label'), " \t\n\r\0\x0B:");
    if ($codigoPromocaoPdf !== '') {
        $descontoLabelPdf .= ' (' . $codigoPromocaoPdf . ')';
    }
    $parcelasFinanceirasPdf = is_array($parcelasFinanceiras ?? null) ? $parcelasFinanceiras : [];
    $resumoFinanceiroPdf = is_array($resumoFinanceiro ?? null) ? $resumoFinanceiro : [];
    $totalAvariasPdf = (float) ($resumoFinanceiroPdf['total_avarias'] ?? 0);
    $totalGeralPdf = $totalLocacaoPdf + $totalAvariasPdf + $totalMultasPdf;
    $totalPagoPdf = (float) ($resumoFinanceiroPdf['total_pago'] ?? 0);
    $totalReembolsadoPdf = (float) ($resumoFinanceiroPdf['total_credito_devolucao'] ?? 0);
    $totalAPagarPdf = max(0, $totalGeralPdf - $totalPagoPdf - $totalReembolsadoPdf);
?>
<div class="totals">
    <table class="totals-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="label-col"><?= t('modules.locacoes.pdf.subtotal_label') ?></td>
            <td class="value-col"><?= currency_format((float) ($locacao['total_fatura'] ?? $locacao['valor_total'] ?? 0)) ?></td>
        </tr>
        <tr>
            <td class="label-col"><?= htmlspecialchars($descontoLabelPdf) ?></td>
            <td class="value-col" style="color: #c00;">- <?= currency_format($valorDescontoPdf) ?></td>
        </tr>
        <tr>
            <td class="label-col"><?= $_faturaTotalRegistroLabel ?></td>
            <td class="value-col"><?= currency_format($totalLocacaoPdf) ?></td>
        </tr>
        <?php if ($totalAvariasPdf > 0): ?>
        <tr>
            <td class="label-col"><?= t('modules.locacoes.installments.total_damages') ?></td>
            <td class="value-col"><?= currency_format($totalAvariasPdf) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalMultasPdf > 0): ?>
        <tr>
            <td class="label-col"><?= t('modules.locacoes.pdf.total_fines_label') ?></td>
            <td class="value-col"><?= currency_format($totalMultasPdf) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalPagoPdf > 0): ?>
        <tr>
            <td class="label-col"><?= t('modules.locacoes.installments.total_paid') ?></td>
            <td class="value-col" style="color: #07803a;">- <?= currency_format($totalPagoPdf) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalReembolsadoPdf > 0): ?>
        <tr>
            <td class="label-col"><?= t('modules.locacoes.pdf.total_refunded_label') ?></td>
            <td class="value-col" style="color: #be123c;">- <?= currency_format($totalReembolsadoPdf) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
            <td class="label-col"><?= t('modules.locacoes.pdf.total_to_pay') ?></td>
            <td class="value-col"><?= currency_format($totalAPagarPdf) ?></td>
        </tr>
    </table>
</div>

<!-- PAGAMENTOS -->
<?php if (!empty($parcelasFinanceirasPdf)): ?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.installments.payments') ?></div>
    <table class="data-table" style="margin-top: 6px;">
        <thead>
            <tr>
                <th style="width: 12%;"><?= t('modules.locacoes.installments.title') ?></th>
                <th style="width: 16%;"><?= t('modules.locacoes.installments.due_date') ?></th>
                <th style="width: 16%;"><?= t('modules.locacoes.installments.payment_date') ?></th>
                <th style="width: 22%;"><?= t('modules.locacoes.installments.payment_method_short') ?></th>
                <th style="width: 14%;"><?= t('modules.locacoes.pdf.status_label') ?></th>
                <th style="width: 20%;" class="text-right"><?= t('modules.locacoes.installments.value') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($parcelasFinanceirasPdf as $parcela): ?>
                <?php
                    $parcelaNumero = (int) ($parcela['parcela'] ?? 0);
                    $totalParcelas = (int) ($parcela['total_parcelas'] ?? 0);
                    $parcelaLabel = $parcelaNumero > 0
                        ? $parcelaNumero . ($totalParcelas > 0 ? '/' . $totalParcelas : '')
                        : '-';
                    $parcelaPaga = ($parcela['pago'] ?? 'N') === 'S';
                ?>
                <tr>
                    <td><?= htmlspecialchars($parcelaLabel) ?></td>
                    <td><?= $_formatarDataFatura($parcela['data_venci'] ?? null) ?></td>
                    <td><?= $_formatarDataFatura($parcela['data_pago'] ?? null) ?></td>
                    <td><?= htmlspecialchars($parcela['forma_pagamento_descricao'] ?? '-') ?></td>
                    <td><?= $parcelaPaga ? t('modules.locacoes.installments.paid') : t('modules.locacoes.installments.pending') ?></td>
                    <td class="text-right"><?= currency_format((float) ($parcela['valor_total'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- CONDUTOR ADICIONAL -->
<?php
    $condutores = !empty($locacao['condutor_adicional']) ? json_decode($locacao['condutor_adicional'], true) : [];
    if (!empty($condutores)):
?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.pdf.additional_driver') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40%;"><?= t('modules.locacoes.pdf.name_header') ?></th>
                <th style="width: 20%;"><?= t('modules.locacoes.pdf.cpf_header') ?></th>
                <th style="width: 20%;"><?= t('modules.locacoes.pdf.cnh_header') ?></th>
                <th style="width: 20%;"><?= t('modules.locacoes.pdf.cnh_validity_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($condutores as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cc'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cn'] ?? $c['cnh'] ?? '-') ?></td>
                <td><?= $_formatarDataFatura($c['va'] ?? $c['cnh_validade'] ?? null) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- REFERENCIAS / INTERVENIENTES -->
<?php if (!empty($referenciasFatura ?? [])): ?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.pdf.references_interveners') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 16%;"><?= t('modules.locacoes.pdf.type_header') ?></th>
                <th style="width: 32%;"><?= t('modules.locacoes.pdf.name_table_header') ?></th>
                <th style="width: 20%;"><?= t('modules.locacoes.pdf.cpf_cnpj_table_header') ?></th>
                <th style="width: 32%;"><?= t('modules.locacoes.pdf.phone_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($referenciasFatura as $referencia): ?>
            <tr>
                <td><?= htmlspecialchars($referencia['tipo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($referencia['nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($referencia['doc'] ?? '-') ?></td>
                <td><?= htmlspecialchars($referencia['telefone'] ?? '-') ?></td>
            </tr>
            <?php if (!empty($referencia['endereco'])): ?>
            <tr>
                <td>&nbsp;</td>
                <td colspan="3" style="font-size: 8pt; color: #666;">
                    <strong><?= t('modules.locacoes.pdf.address_label') ?></strong>
                    <?= htmlspecialchars($referencia['endereco']) ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- HISTORICO DE VEICULOS / SUBSTITUICOES -->
<?php
    $historicoVeiculosPdf = is_array($historicoVeiculos ?? null) ? $historicoVeiculos : [];
    $mostrarHistoricoVeiculosPdf = count($historicoVeiculosPdf) > 1;
    foreach ($historicoVeiculosPdf as $historicoVeiculoPdf) {
        if (!empty($historicoVeiculoPdf['data_entrada']) || !empty($historicoVeiculoPdf['motivo_saida'])) {
            $mostrarHistoricoVeiculosPdf = true;
            break;
        }
    }
?>
<?php if ($mostrarHistoricoVeiculosPdf): ?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.pdf.vehicle_history_substitutions') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 38%;"><?= t('modules.locacoes.pdf.vehicle_header') ?></th>
                <th style="width: 18%;"><?= t('modules.locacoes.pdf.checkout_header') ?></th>
                <th style="width: 18%;"><?= t('modules.locacoes.pdf.return_header') ?></th>
                <th style="width: 26%;"><?= t('modules.locacoes.pdf.reason_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historicoVeiculosPdf as $historicoVeiculoPdf): ?>
            <tr>
                <td><?= htmlspecialchars($_formatarVeiculoFatura($historicoVeiculoPdf)) ?></td>
                <td><?= $_formatarDataFatura($historicoVeiculoPdf['data_saida'] ?? null, true) ?></td>
                <td><?= !empty($historicoVeiculoPdf['data_entrada']) ? $_formatarDataFatura($historicoVeiculoPdf['data_entrada'], true) : t('modules.locacoes.pdf.current_vehicle_label') ?></td>
                <td><?= htmlspecialchars(!empty($historicoVeiculoPdf['motivo_saida']) ? $historicoVeiculoPdf['motivo_saida'] : (!empty($historicoVeiculoPdf['data_entrada']) ? t('modules.locacoes.pdf.returned_vehicle_label') : t('modules.locacoes.pdf.current_vehicle_label'))) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- MULTAS VINCULADAS -->
<?php if (!empty($multas ?? [])): ?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.pdf.linked_fines_section') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 16%;"><?= t('modules.locacoes.pdf.date_header') ?></th>
                <th style="width: 20%;"><?= t('modules.locacoes.pdf.vehicle_plate_header') ?></th>
                <th style="width: 18%;" class="text-right"><?= t('modules.locacoes.installments.value') ?></th>
                <th style="width: 46%;"><?= t('modules.locacoes.pdf.description_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($multas as $multa): ?>
            <?php $descricaoMultaDetalhe = ($multa['descri'] ?? '') ?: (($multa['n_infracao'] ?? '') ?: ($multa['numero_ait'] ?? '-')); ?>
            <tr>
                <td><?= $_formatarDataFatura($multa['data_hora'] ?? null) ?></td>
                <td><?= htmlspecialchars($multa['veiculo_placa'] ?? '-') ?></td>
                <td class="text-right"><?= currency_format((float) ($multa['valor'] ?? 0)) ?></td>
                <td><?= htmlspecialchars($descricaoMultaDetalhe) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- GARANTIAS (Bloqueio + Caucao) -->
<?php
    $bloqueioValorPdf = (float) ($locacao['bloqueio_status'] === 'captured'
        ? ($locacao['bloqueio_valor_capturado'] ?? $locacao['bloqueio_hold_valor'] ?? 0)
        : ($locacao['bloqueio_hold_valor'] ?? 0));
    $caucaoValorPdf = (float) ($locacao['caucao_valor'] ?? 0);
    $bloqueioStatusPdf = $locacao['bloqueio_status'] ?? null;

    if ($bloqueioValorPdf > 0 || $caucaoValorPdf > 0):
?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.summary_section.guarantees') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40%;"><?= t('modules.locacoes.pdf.description_header') ?></th>
                <th style="width: 25%;"><?= t('modules.locacoes.block.status') ?></th>
                <th style="width: 15%;" class="text-right"><?= t('modules.locacoes.pdf.value_per_day_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($bloqueioValorPdf > 0): ?>
            <tr>
                <td><?= t('modules.locacoes.sections.block') ?>
                    <?php if (!empty($locacao['bloqueio_cartao_bandeira'])): ?>
                        <span style="color: #999; font-size: 9px;">(**** <?= htmlspecialchars($locacao['bloqueio_cartao_ultimos_digitos'] ?? '') ?> <?= htmlspecialchars($locacao['bloqueio_cartao_bandeira'] ?? '') ?>)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                        $statusLabels = [
                            'authorized' => t('modules.locacoes.block.authorized'),
                            'captured' => t('modules.locacoes.block.captured'),
                            'released' => t('modules.locacoes.block.released'),
                            'expired' => t('modules.locacoes.block.expired'),
                            'pending' => t('modules.locacoes.deposit.pending'),
                        ];
                        echo $statusLabels[$bloqueioStatusPdf] ?? $bloqueioStatusPdf;
                    ?>
                </td>
                <td class="text-right"><?= currency_format($bloqueioValorPdf) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($caucaoValorPdf > 0): ?>
            <tr>
                <td><?= t('modules.locacoes.sections.deposit') ?>
                    <?php $formaPagamentoCaucao = $locacao['forma_pagamento_caucao_descricao'] ?? $locacao['caucao_tipo'] ?? ''; ?>
                    <?php if (!empty($formaPagamentoCaucao)): ?>
                        <span style="color: #999; font-size: 9px;">(<?= htmlspecialchars($formaPagamentoCaucao) ?>)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($locacao['caucao_data_devolucao'])): ?>
                        <?= t('modules.locacoes.deposit.returned') ?> (<?= $_formatarDataFatura($locacao['caucao_data_devolucao']) ?>)
                    <?php elseif (isset($locacao['caucao_prazo_devolucao']) && $locacao['caucao_prazo_devolucao'] !== ''): ?>
                        <?= t('modules.locacoes.deposit.return_days') ?>: <?= (int) $locacao['caucao_prazo_devolucao'] === 0 ? t('modules.locacoes.deposit.return_on_closing') : (int) $locacao['caucao_prazo_devolucao'] . ' ' . t('modules.locacoes.summary_section.days') ?>
                    <?php else: ?>
                        <?= t('modules.locacoes.deposit.pending') ?>
                    <?php endif; ?>
                </td>
                <td class="text-right"><?= currency_format($caucaoValorPdf) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- OBSERVACOES -->
<?php if (!empty($locacao['obs'])): ?>
<div class="section" style="margin-top: 12px;">
    <div class="section-title"><?= t('modules.locacoes.pdf.observations_section') ?></div>
    <div class="obs-box"><?= nl2br(htmlspecialchars($locacao['obs'])) ?></div>
</div>
<?php endif; ?>

<!-- ASSINATURAS -->
<?php if (!empty($_pdfFooterFixo)): ?>
    <?php /* footer fixo já configurado pelo wrapper */ ?>
<?php elseif ($_faturaStandalone): ?>
    <!-- Modo standalone: footer fixo via htmlpagefooter do mPDF -->
    <htmlpagefooter name="assinatura">
        <?php include __DIR__ . '/_footer_assinatura.php'; ?>
    </htmlpagefooter>
    <sethtmlpagefooter name="assinatura" value="on" show-this-page="1" />
<?php else: ?>
    <!-- Modo combo: assinatura inline -->
    <?php include __DIR__ . '/_footer_assinatura.php'; ?>
<?php endif; ?>
