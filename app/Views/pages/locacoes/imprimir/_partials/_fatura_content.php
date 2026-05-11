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
    </table>
</div>

<!-- DADOS DA LOCACAO -->
<div class="section">
    <div class="section-title"><?= $_faturaDadosTitulo ?></div>
    <table class="data-table">
        <tr>
            <td style="width: 15%;"><strong><?= t('modules.locacoes.pdf.checkout_label') ?></strong></td>
            <td style="width: 35%;"><?= !empty($locacao['data_saida']) ? date('d/m/Y H:i', strtotime($locacao['data_saida'])) : '-' ?></td>
            <td style="width: 15%;"><strong><?= t('modules.locacoes.pdf.expected_return_label') ?></strong></td>
            <td style="width: 35%;"><?= !empty($locacao['data_prevista']) ? date('d/m/Y H:i', strtotime($locacao['data_prevista'])) : '-' ?></td>
        </tr>
        <tr>
            <td><strong><?= t('modules.locacoes.pdf.days_label') ?></strong></td>
            <td><?= (int) ($locacao['dias'] ?? $locacao['quantidade_dias'] ?? 0) ?></td>
            <td><strong><?= t('modules.locacoes.pdf.method_label') ?></strong></td>
            <td><?= htmlspecialchars($locacao['forma_pagamento_descricao'] ?? '-') ?></td>
        </tr>
    </table>
</div>

<!-- CONDUTOR ADICIONAL -->
<?php
    $condutores = !empty($locacao['condutor_adicional']) ? json_decode($locacao['condutor_adicional'], true) : [];
    if (!empty($condutores)):
?>
<div class="section">
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
                <td><?= htmlspecialchars($c['cn'] ?? '-') ?></td>
                <td><?= !empty($c['va']) ? date('d/m/Y', strtotime($c['va'])) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- FIADORES, AVALISTAS, TESTEMUNHAS -->
<?php
    $fiadores = !empty($locacao['array_fiadores']) ? json_decode($locacao['array_fiadores'], true) : [];
    $avalistas = !empty($locacao['array_avalistas']) ? json_decode($locacao['array_avalistas'], true) : [];
    $testemunhas = !empty($locacao['array_testemunhas']) ? json_decode($locacao['array_testemunhas'], true) : [];
    $pessoasExtras = [];
    foreach ($fiadores as $f) { $pessoasExtras[] = ['tipo' => t('modules.locacoes.pdf.guarantor_type'), 'nome' => $f['nome'] ?? '-', 'doc' => $f['cc'] ?? '-']; }
    foreach ($avalistas as $a) { $pessoasExtras[] = ['tipo' => t('modules.locacoes.pdf.endorser_type'), 'nome' => $a['nome'] ?? '-', 'doc' => $a['cc'] ?? '-']; }
    foreach ($testemunhas as $tw) { $pessoasExtras[] = ['tipo' => t('modules.locacoes.pdf.witness_type'), 'nome' => $tw['nome'] ?? '-', 'doc' => $tw['cc'] ?? '-']; }
    if (!empty($pessoasExtras)):
?>
<div class="section">
    <div class="section-title"><?= t('modules.locacoes.pdf.guarantors_endorsers_witnesses') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;"><?= t('modules.locacoes.pdf.type_header') ?></th>
                <th style="width: 45%;"><?= t('modules.locacoes.pdf.name_table_header') ?></th>
                <th style="width: 35%;"><?= t('modules.locacoes.pdf.cpf_cnpj_table_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pessoasExtras as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['tipo']) ?></td>
                <td><?= htmlspecialchars($p['nome']) ?></td>
                <td><?= htmlspecialchars($p['doc']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

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
    $descricaoPlanoFatura = trim($planoComposicao . ($veiculoReferenciaFatura !== '' ? ' - ' . $veiculoReferenciaFatura : ''));

    if ($valorPlanoComposicao > 0) {
        $linhasFatura[] = [
            'descricao' => $descricaoPlanoFatura,
            'qtd' => t_choice('modules.locacoes.pdf.day_count', $diasFatura),
            'unitario' => $valorPlanoComposicao,
            'total' => $valorPlanoComposicao * $diasFatura,
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

    $kmExcedenteFatura = (int) ($locacao['kmlExcedente'] ?? $locacao['km_excedente'] ?? 0);
    $valorKmExcedenteFatura = (float) ($locacao['km_valor'] ?? 0);
    if ($kmExcedenteFatura > 0 && $valorKmExcedenteFatura > 0) {
        $linhasFatura[] = [
            'descricao' => t('modules.locacoes.pdf.km_excess_label'),
            'qtd' => $kmExcedenteFatura . ' km',
            'unitario' => $valorKmExcedenteFatura,
            'total' => $kmExcedenteFatura * $valorKmExcedenteFatura,
        ];
    }

    $fuelLabel = function($nivel) use ($locacao) {
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

    $combustivelValorFatura = (float) ($locacao['combustivel_valor'] ?? 0);
    if ($combustivelValorFatura > 0) {
        $combustivelUsado = max(1, (int) ($locacao['combustivel_usado'] ?? 1));
        $linhasFatura[] = [
            'descricao' => t('modules.locacoes.pdf.fuel_charge_label') . ' - ' . $fuelLabel($locacao['combustivel_ini'] ?? null) . ' ' . t('modules.locacoes.pdf.to_label') . ' ' . $fuelLabel($locacao['combustivel_fim'] ?? null),
            'qtd' => t_choice('modules.locacoes.pdf.fraction_count', $combustivelUsado),
            'unitario' => $combustivelValorFatura / $combustivelUsado,
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
            <tr>
                <td><?= htmlspecialchars($linha['descricao']) ?></td>
                <td class="text-center"><?= htmlspecialchars($linha['qtd']) ?></td>
                <td class="text-right"><?= currency_format((float) $linha['unitario']) ?></td>
                <td class="text-right"><?= currency_format((float) $linha['total']) ?></td>
            </tr>
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
    $totalGeralPdf = $totalLocacaoPdf + $totalMultasPdf;
    $valorDescontoPdf = (float) ($locacao['valor_desconto'] ?? 0);
    $codigoPromocaoPdf = trim((string) ($locacao['promocao_codigo'] ?? ''));
    $descontoLabelPdf = rtrim(t('modules.locacoes.pdf.discount_label'), " \t\n\r\0\x0B:");
    if ($codigoPromocaoPdf !== '') {
        $descontoLabelPdf .= ' (' . $codigoPromocaoPdf . ')';
    }
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
        <?php if ($totalMultasPdf > 0): ?>
        <tr>
            <td class="label-col"><?= t('modules.locacoes.pdf.total_fines_label') ?></td>
            <td class="value-col"><?= currency_format($totalMultasPdf) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
            <td class="label-col"><?= t('modules.locacoes.pdf.total_to_pay') ?></td>
            <td class="value-col"><?= currency_format($totalGeralPdf) ?></td>
        </tr>
    </table>
</div>

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
                    <?php if (!empty($locacao['caucao_tipo'])): ?>
                        <span style="color: #999; font-size: 9px;">(<?= htmlspecialchars(ucfirst($locacao['caucao_tipo'])) ?>)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($locacao['caucao_data_devolucao'])): ?>
                        <?= t('modules.locacoes.deposit.returned') ?> (<?= date('d/m/Y', strtotime($locacao['caucao_data_devolucao'])) ?>)
                    <?php elseif (!empty($locacao['caucao_prazo_devolucao'])): ?>
                        <?= t('modules.locacoes.deposit.return_days') ?>: <?= (int) $locacao['caucao_prazo_devolucao'] ?> <?= t('modules.locacoes.summary_section.days') ?>
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
