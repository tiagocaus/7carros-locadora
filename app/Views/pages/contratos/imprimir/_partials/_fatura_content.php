<?php
/**
 * Partial: Conteudo completo da fatura
 *
 * Variaveis esperadas do controller:
 *   $contrato, $empresa, $veiculo, $assinatura, $logoPath, $qrPath
 *
 * Flag de controle (definida pelo arquivo principal antes do include):
 *   $_faturaStandalone (bool) - true: usa htmlpagefooter do mPDF; false: assinatura inline
 */
$_faturaStandalone = $_faturaStandalone ?? false;
$_formatarDataContratoFatura = static function($valor, bool $comHora = false): string {
    if (empty($valor)) {
        return '-';
    }

    $formatado = $comHora ? format_operational_datetime((string) $valor) : format_date((string) $valor);

    return $formatado !== '' ? $formatado : '-';
};
$_formatarCombustivelContratoFatura = static function($nivel, array $item): string {
    if ($nivel === null || $nivel === '') {
        return '-';
    }

    $labels = [
        8 => t('modules.contratos.fuel_levels.full'),
        7 => '7/8',
        6 => '3/4',
        5 => '5/8',
        4 => '1/2',
        3 => '3/8',
        2 => '1/4',
        1 => '1/8',
        0 => t('modules.contratos.fuel_levels.reserve'),
    ];

    if (($item['veiculo_tipo_combustivel'] ?? '') === 'HE') {
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
$_formatarOdometroContratoFatura = static function($valor): string {
    $odometro = (int) ($valor ?? 0);

    return $odometro > 0 ? number_format($odometro, 0, ',', '.') . ' km' : '-';
};
$_formatarSeguroContratoFatura = static function(bool $contratado, float $valor): string {
    if (!$contratado) {
        return t('modules.contratos.pdf.insurance_not_contracted');
    }

    $label = t('modules.contratos.pdf.insurance_contracted');

    return $valor > 0 ? $label . ' (' . currency_format($valor) . ')' : $label;
};
$_formatarVeiculoContratoFatura = static function(array $item): string {
    $placa = trim((string) ($item['veiculo_placa'] ?? ''));
    $nome = trim((string) (($item['veiculo_marca'] ?? '') . ' ' . ($item['veiculo_modelo'] ?? '')));
    $grupo = trim((string) ($item['grupo_nome'] ?? ''));

    if ($placa !== '' || $nome !== '') {
        return trim($placa . ($nome !== '' ? ' - ' . $nome : ''));
    }

    return $grupo !== '' ? t('modules.contratos.pdf.group_header') . ': ' . $grupo : '-';
};
?>

<!-- HEADER -->
<?php $_docTitulo = t('modules.contratos.pdf.invoice_title'); include __DIR__ . '/_header.php'; ?>

<!-- DADOS DO CLIENTE -->
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.client_data') ?></div>
    <div class="section-body">
        <table class="fields-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="w25">
                    <div class="field-label"><?= t('modules.contratos.pdf.client_label') ?></div>
                    <div class="field-value"><?= htmlspecialchars($contrato['cliente_nome'] ?? '-') ?></div>
                </td>
                <td class="w25">
                    <div class="field-label"><?= t('modules.contratos.pdf.cpf_cnpj_label') ?></div>
                    <div class="field-value"><?= htmlspecialchars($contrato['cliente_cpf_cnpj'] ?? '-') ?></div>
                </td>
                <td class="w25">
                    <div class="field-label"><?= t('modules.contratos.pdf.phone_label') ?></div>
                    <div class="field-value"><?= htmlspecialchars($contrato['cliente_telefone'] ?? '-') ?></div>
                </td>
                <td class="w25">
                    <div class="field-label"><?= t('modules.contratos.pdf.email_label') ?></div>
                    <div class="field-value"><?= htmlspecialchars($contrato['cliente_email'] ?? '-') ?></div>
                </td>
            </tr>
            <?php if (!empty($contrato['cliente_endereco_completo'])): ?>
            <tr>
                <td class="w100" colspan="4">
                    <div class="field-label"><?= t('modules.contratos.pdf.address_label') ?></div>
                    <div class="field-value"><?= htmlspecialchars($contrato['cliente_endereco_completo']) ?></div>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- DADOS DO CONTRATO -->
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.contract_data') ?></div>
    <?php
        $periodoLabel = match($contrato['contagem'] ?? 'dia') {
            'dia' => t('modules.contratos.pdf.period_labels.days'),
            'semana' => t('modules.contratos.pdf.period_labels.weeks'),
            'mes' => t('modules.contratos.pdf.period_labels.months'),
            'ano' => t('modules.contratos.pdf.period_labels.years'),
            default => ucfirst($contrato['contagem'] ?? '-')
        };
        $autoRenovacaoNome = match($contrato['auto_renovacao'] ?? '') {
            '', null => t('modules.contratos.pdf.renewal_disabled'),
            'auto' => t('modules.contratos.pdf.renewal_until_return'),
            default => $contrato['auto_renovacao'] . 'x'
        };
    ?>
    <div class="section-body">
        <?php $colunasContratoPdf = !empty($contrato['data_renovacao']) ? 6 : 5; ?>
        <table class="fields-table" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: <?= (int) floor(100 / $colunasContratoPdf) ?>%;">
                    <div class="field-label"><?= t('modules.contratos.pdf.start_label') ?></div>
                    <div class="field-value"><?= $_formatarDataContratoFatura($contrato['data_ini'] ?? null, true) ?></div>
                </td>
                <td style="width: <?= (int) floor(100 / $colunasContratoPdf) ?>%;">
                    <div class="field-label"><?= t('modules.contratos.pdf.end_label') ?></div>
                    <div class="field-value"><?= !empty($contrato['auto_renovacao']) && $contrato['auto_renovacao'] === 'auto' ? t('modules.contratos.pdf.indeterminate') : $_formatarDataContratoFatura($contrato['data_fim'] ?? null, true) ?></div>
                </td>
                <td style="width: <?= (int) floor(100 / $colunasContratoPdf) ?>%;">
                    <div class="field-label"><?= htmlspecialchars($periodoLabel) ?></div>
                    <div class="field-value"><?= (int) $contrato['dias'] ?></div>
                </td>
                <td style="width: <?= (int) floor(100 / $colunasContratoPdf) ?>%;">
                    <div class="field-label"><?= t('modules.contratos.pdf.method_label') ?></div>
                    <div class="field-value"><?= htmlspecialchars($contrato['forma_pagamento_tipo'] ?? $contrato['forma_pagamento_descricao'] ?? '-') ?></div>
                </td>
                <td style="width: <?= (int) floor(100 / $colunasContratoPdf) ?>%;">
                    <div class="field-label"><?= t('modules.contratos.pdf.renewal_label') ?></div>
                    <div class="field-value"><?= $autoRenovacaoNome ?></div>
                </td>
                <?php if (!empty($contrato['data_renovacao'])): ?>
                <td style="width: <?= (int) floor(100 / $colunasContratoPdf) ?>%;">
                    <div class="field-label"><?= t('modules.contratos.pdf.next_label') ?></div>
                    <div class="field-value"><?= $_formatarDataContratoFatura($contrato['data_renovacao']) ?></div>
                </td>
                <?php endif; ?>
            </tr>
        </table>
    </div>
</div>

<!-- CONDUTOR ADICIONAL -->
<?php
    $condutores = !empty($contrato['condutor_adicional']) ? json_decode($contrato['condutor_adicional'], true) : [];
    if (!empty($condutores)):
?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.additional_driver') ?></div>
    <div class="section-body">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40%;"><?= t('modules.contratos.pdf.name_header') ?></th>
                <th style="width: 20%;"><?= t('modules.contratos.pdf.cpf_header') ?></th>
                <th style="width: 20%;"><?= t('modules.contratos.pdf.cnh_header') ?></th>
                <th style="width: 20%;"><?= t('modules.contratos.pdf.cnh_validity_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($condutores as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cc'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cn'] ?? '-') ?></td>
                <td><?= $_formatarDataContratoFatura($c['va'] ?? null) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- FIADORES, AVALISTAS, TESTEMUNHAS -->
<?php
    $fiadores = !empty($contrato['array_fiadores']) ? json_decode($contrato['array_fiadores'], true) : [];
    $avalistas = !empty($contrato['array_avalistas']) ? json_decode($contrato['array_avalistas'], true) : [];
    $testemunhas = !empty($contrato['array_testemunhas']) ? json_decode($contrato['array_testemunhas'], true) : [];
    $pessoasExtras = [];
    foreach ($fiadores as $f) { $pessoasExtras[] = ['tipo' => t('modules.contratos.pdf.guarantor_type'), 'nome' => $f['nome'] ?? '-', 'doc' => $f['cc'] ?? '-']; }
    foreach ($avalistas as $a) { $pessoasExtras[] = ['tipo' => t('modules.contratos.pdf.endorser_type'), 'nome' => $a['nome'] ?? '-', 'doc' => $a['cc'] ?? '-']; }
    foreach ($testemunhas as $t) { $pessoasExtras[] = ['tipo' => t('modules.contratos.pdf.witness_type'), 'nome' => $t['nome'] ?? '-', 'doc' => $t['cc'] ?? '-']; }
    if (!empty($pessoasExtras)):
?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.guarantors_endorsers_witnesses') ?></div>
    <div class="section-body">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;"><?= t('modules.contratos.pdf.type_header') ?></th>
                <th style="width: 45%;"><?= t('modules.contratos.pdf.name_table_header') ?></th>
                <th style="width: 35%;"><?= t('modules.contratos.pdf.cpf_cnpj_table_header') ?></th>
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
</div>
<?php endif; ?>

<!-- VEICULOS -->
<?php if (!empty($contrato['veiculos'])): ?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.vehicles_section') ?></div>
    <div class="section-body">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;"><?= t('modules.contratos.pdf.vehicle_header') ?></th>
                <th style="width: 17%;"><?= t('modules.contratos.pdf.plan_header') ?></th>
                <th style="width: 16%;"><?= t('modules.contratos.pdf.withdrawal_header') ?></th>
                <th style="width: 16%;"><?= t('modules.contratos.pdf.return_details_header') ?></th>
                <th style="width: 13%;" class="text-right"><?= t('modules.contratos.pdf.value_header') ?></th>
                <th style="width: 13%;" class="text-right"><?= t('modules.contratos.pdf.total_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contrato['veiculos'] as $vIndex => $v): ?>
            <?php
                $planoNome = match($v['plano'] ?? 'KL') {
                    'KL' => t('modules.contratos.vehicles.plan_km_free'),
                    'KMC' => t('modules.contratos.vehicles.plan_km_controlled'),
                    'KP' => t('modules.contratos.vehicles.plan_km_paid'),
                    default => $v['plano'] ?? '-'
                };
                $valorPlano = match($v['plano'] ?? 'KL') {
                    'KL' => (float) ($v['valor_plano_km_livre'] ?? 0),
                    'KMC' => (float) ($v['valor_plano_km_controlado'] ?? 0),
                    'KP' => (float) ($v['valor_plano_km_pago'] ?? 0),
                    default => 0
                };
                $segVeicContratado = !empty($v['seguro_carro']);
                $segTercContratado = !empty($v['seguro_terceiros']);
                $segVeic = $segVeicContratado ? (float) ($v['valor_seguro_carro'] ?? 0) : 0;
                $segTerc = $segTercContratado ? (float) ($v['valor_seguro_terceiros'] ?? 0) : 0;
                $totalDia = $valorPlano + $segVeic + $segTerc;
                $kmFranquia = (int) ($v['km_franquia'] ?? 0);
                $contagemLabel = match($contrato['contagem'] ?? '') {
                    'dia' => t('modules.contratos.pdf.counting_labels.day'),
                    'semana' => t('modules.contratos.pdf.counting_labels.week'),
                    'mes' => t('modules.contratos.pdf.counting_labels.month'),
                    'ano' => t('modules.contratos.pdf.counting_labels.year'),
                    default => '-',
                };
                $veiculoNome = trim((string) (($v['veiculo_marca'] ?? '') . ' ' . ($v['veiculo_modelo'] ?? '')));
                $veiculoMeta = array_values(array_filter([
                    trim((string) ($v['veiculo_placa'] ?? '')),
                    trim((string) ($v['grupo_nome'] ?? '')) !== ''
                        ? t('modules.contratos.pdf.group_header') . ' ' . trim((string) $v['grupo_nome'])
                        : '',
                ], static fn(string $valor): bool => $valor !== ''));
                $combustivelSaida = $_formatarCombustivelContratoFatura($v['combustivel_saida'] ?? null, $v);
                $combustivelEntrada = $_formatarCombustivelContratoFatura($v['combustivel_entrada'] ?? null, $v);
                $combustivelEntradaRaw = $v['combustivel_entrada'] ?? null;
                $temDadosDevolucao = (int) ($v['odometro_entrada'] ?? 0) > 0
                    || ($combustivelEntradaRaw !== null && $combustivelEntradaRaw !== '');
                $isLastVehicle = $vIndex === count($contrato['veiculos']) - 1;
            ?>
            <tr class="vehicle-group<?= $isLastVehicle ? ' vehicle-group-last' : '' ?>">
                <td>
                    <div class="vehicle-primary"><?= htmlspecialchars($veiculoNome !== '' ? $veiculoNome : '-') ?></div>
                    <?php if ($veiculoMeta): ?>
                    <div class="vehicle-meta"><?= htmlspecialchars(implode(' · ', $veiculoMeta)) ?></div>
                    <?php endif; ?>
                    <div class="vehicle-meta">
                        <strong><?= t('modules.contratos.pdf.insurances_label') ?>:</strong>
                        <?= t('modules.contratos.pdf.vehicle_insurance_short') ?> - <?= htmlspecialchars($_formatarSeguroContratoFatura($segVeicContratado, $segVeic)) ?>
                        <span class="vehicle-insurance-separator">·</span>
                        <?= t('modules.contratos.pdf.third_party_insurance_short') ?> - <?= htmlspecialchars($_formatarSeguroContratoFatura($segTercContratado, $segTerc)) ?>
                    </div>
                </td>
                <td>
                    <div><?= htmlspecialchars($planoNome) ?></div>
                    <?php if (($v['plano'] ?? '') === 'KMC' && $kmFranquia > 0): ?>
                    <div class="vehicle-meta"><?= number_format($kmFranquia, 0, ',', '.') ?> km/<?= htmlspecialchars($contagemLabel) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div><?= $_formatarOdometroContratoFatura($v['odometro_saida'] ?? null) ?></div>
                    <div class="vehicle-meta"><?= t('modules.contratos.pdf.fuel_short_label') ?>: <?= htmlspecialchars($combustivelSaida) ?></div>
                </td>
                <td>
                    <?php if ($temDadosDevolucao): ?>
                    <div><?= $_formatarOdometroContratoFatura($v['odometro_entrada'] ?? null) ?></div>
                    <div class="vehicle-meta"><?= t('modules.contratos.pdf.fuel_short_label') ?>: <?= htmlspecialchars($combustivelEntrada) ?></div>
                    <?php else: ?>
                    <div>-</div>
                    <?php endif; ?>
                </td>
                <td class="text-right"><?= currency_format($valorPlano) ?></td>
                <td class="text-right"><?= currency_format($totalDia) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- HISTORICO DE VEICULOS / SUBSTITUICOES -->
<?php
    $historicoVeiculosContratoPdf = is_array($contrato['veiculos'] ?? null) ? $contrato['veiculos'] : [];
    $mostrarHistoricoVeiculosContratoPdf = count($historicoVeiculosContratoPdf) > 1;
    foreach ($historicoVeiculosContratoPdf as $historicoVeiculoContratoPdf) {
        if (!empty($historicoVeiculoContratoPdf['data_entrada']) || !empty($historicoVeiculoContratoPdf['motivo_saida'])) {
            $mostrarHistoricoVeiculosContratoPdf = true;
            break;
        }
    }
?>
<?php if ($mostrarHistoricoVeiculosContratoPdf): ?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.vehicle_history_substitutions') ?></div>
    <div class="section-body">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 38%;"><?= t('modules.contratos.pdf.vehicle_header') ?></th>
                <th style="width: 18%;"><?= t('modules.contratos.pdf.checkout_header') ?></th>
                <th style="width: 18%;"><?= t('modules.contratos.pdf.return_header') ?></th>
                <th style="width: 26%;"><?= t('modules.contratos.pdf.reason_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historicoVeiculosContratoPdf as $historicoVeiculoContratoPdf): ?>
            <tr>
                <td><?= htmlspecialchars($_formatarVeiculoContratoFatura($historicoVeiculoContratoPdf)) ?></td>
                <td><?= $_formatarDataContratoFatura($historicoVeiculoContratoPdf['data_saida'] ?? null, true) ?></td>
                <td><?= !empty($historicoVeiculoContratoPdf['data_entrada']) ? $_formatarDataContratoFatura($historicoVeiculoContratoPdf['data_entrada'], true) : t('modules.contratos.pdf.current_vehicle_label') ?></td>
                <td><?= htmlspecialchars(!empty($historicoVeiculoContratoPdf['motivo_saida']) ? $historicoVeiculoContratoPdf['motivo_saida'] : (!empty($historicoVeiculoContratoPdf['data_entrada']) ? t('modules.contratos.pdf.returned_vehicle_label') : t('modules.contratos.pdf.current_vehicle_label'))) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- TAXAS E SERVICOS -->
<?php if (!empty($contrato['taxas'])): ?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.fees_section') ?></div>
    <div class="section-body">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50%;"><?= t('modules.contratos.pdf.description_header') ?></th>
                <th style="width: 10%;" class="text-center"><?= t('modules.contratos.pdf.qty_header') ?></th>
                <th style="width: 20%;" class="text-right"><?= t('modules.contratos.pdf.unit_value_header') ?></th>
                <th style="width: 20%;" class="text-right"><?= t('modules.contratos.pdf.total_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contrato['taxas'] as $taxa): ?>
            <tr>
                <td><?= htmlspecialchars($taxa['nome'] ?? '-') ?></td>
                <td class="text-center"><?= (int) ($taxa['quantidade'] ?? 1) ?></td>
                <td class="text-right"><?= currency_format((float) ($taxa['valor_unitario'] ?? 0)) ?></td>
                <td class="text-right"><?= currency_format((float) ($taxa['valor_total'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- TOTAIS -->
<div class="totals">
    <table class="totals-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="label-col"><?= t('modules.contratos.pdf.subtotal_label') ?></td>
            <td class="value-col"><?= currency_format((float) ($contrato['total_fatura'] ?? 0)) ?></td>
        </tr>
        <?php if ((float) ($contrato['valor_desconto'] ?? 0) > 0): ?>
        <tr>
            <td class="label-col"><?= t('modules.contratos.pdf.discount_label') ?></td>
            <td class="value-col">- <?= currency_format((float) $contrato['valor_desconto']) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
            <td class="label-col"><?= t('modules.contratos.pdf.total_to_pay') ?></td>
            <td class="value-col"><?= currency_format((float) ($contrato['total_pagar'] ?? 0)) ?></td>
        </tr>
    </table>
</div>

<!-- GARANTIAS (Bloqueio) -->
<?php
    $bloqueioValorPdf = 0;
    $bloqueioStatusPdf = $contrato['bloqueio_status'] ?? null;
    if ($bloqueioStatusPdf) {
        $bloqueioValorPdf = (float) ($bloqueioStatusPdf === 'captured'
            ? ($contrato['bloqueio_valor_capturado'] ?? $contrato['bloqueio_hold_valor'] ?? 0)
            : ($contrato['bloqueio_hold_valor'] ?? 0));
    }

    if ($bloqueioValorPdf > 0):
?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.summary_section.guarantees') ?></div>
    <div class="section-body">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40%;"><?= t('modules.contratos.pdf.description_header') ?></th>
                <th style="width: 25%;"><?= t('modules.contratos.block.status') ?></th>
                <th style="width: 15%;" class="text-right"><?= t('modules.contratos.pdf.total_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= t('modules.contratos.block.title') ?>
                    <?php if (!empty($contrato['bloqueio_cartao_bandeira'])): ?>
                        <span style="font-size: 9px;">(**** <?= htmlspecialchars($contrato['bloqueio_cartao_ultimos_digitos'] ?? '') ?> <?= htmlspecialchars($contrato['bloqueio_cartao_bandeira'] ?? '') ?>)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                        $statusLabels = [
                            'authorized' => t('modules.contratos.block.authorized'),
                            'captured' => t('modules.contratos.block.captured'),
                            'released' => t('modules.contratos.block.released'),
                            'expired' => t('modules.contratos.block.expired'),
                            'pending' => t('modules.contratos.financial.pending'),
                        ];
                        echo $statusLabels[$bloqueioStatusPdf] ?? $bloqueioStatusPdf;
                    ?>
                </td>
                <td class="text-right"><?= currency_format($bloqueioValorPdf) ?></td>
            </tr>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- OBSERVACOES -->
<?php if (!empty($contrato['obs'])): ?>
<div class="section">
    <div class="section-title"><?= t('modules.contratos.pdf.observations_section') ?></div>
    <div class="section-body">
        <div class="obs-box"><?= nl2br(htmlspecialchars($contrato['obs'])) ?></div>
    </div>
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
