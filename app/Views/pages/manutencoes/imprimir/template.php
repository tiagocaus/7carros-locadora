<?php $htmlLocale = locale_info()['code'] ?? 'pt-BR'; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.manutencao.print.title') ?> <?= htmlspecialchars($manutencao['os'] ?? '') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .logo-img {
            max-height: 40px;
            max-width: 180px;
            margin-bottom: 5px;
        }
        .empresa-nome {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .empresa-detalhe {
            font-size: 8pt;
            color: #666;
        }
        .doc-titulo {
            font-size: 12pt;
            font-weight: bold;
            text-align: right;
        }
        .doc-detalhe {
            font-size: 8pt;
            text-align: right;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            background: #f0f0f0;
            padding: 5px 10px;
            margin-bottom: 8px;
            margin-top: 15px;
            border-left: 3px solid #333;
        }
        .dados-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            border: 1px solid #ddd;
            background: #f5f5f5;
        }
        .dados-table td {
            padding: 5px 10px;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        .dados-label {
            font-weight: bold;
            color: #666;
            font-size: 8pt;
            white-space: nowrap;
            width: 20%;
        }
        .dados-value {
            font-size: 10pt;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-criada {
            background: #f1f5f9;
            color: #64748b;
        }
        .badge-aberta {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-fechada {
            background: #dcfce7;
            color: #166534;
        }
        .badge-pago {
            background: #dcfce7;
            color: #166534;
        }
        .badge-pendente {
            background: #fef2f2;
            color: #991b1b;
        }
        .col-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .col-table td {
            vertical-align: top;
            padding: 0;
        }
        .sub-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
            background: #f5f5f5;
        }
        .sub-table td {
            padding: 5px 10px;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        .sub-label {
            font-weight: bold;
            color: #666;
            font-size: 8pt;
            white-space: nowrap;
            width: 35%;
        }
        .sub-value {
            font-size: 10pt;
        }
        .itens-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .itens-table th {
            background: #f0f0f0;
            padding: 5px 8px;
            text-align: left;
            font-size: 9pt;
            border: 1px solid #ddd;
        }
        .itens-table td {
            padding: 4px 8px;
            font-size: 9pt;
            border: 1px solid #eee;
        }
        .motivo-box {
            border: 1px solid #ddd;
            padding: 10px;
            min-height: 30px;
            background: #fafafa;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        .totais-table {
            width: 40%;
            border-collapse: collapse;
            margin-left: auto;
            margin-top: 10px;
        }
        .totais-table td {
            padding: 4px 8px;
            font-size: 9pt;
            border: 1px solid #ddd;
        }
        .totais-label {
            font-weight: bold;
            text-align: right;
        }
        .totais-value {
            text-align: right;
            width: 120px;
        }
    </style>
</head>
<body>
    <htmlpagefooter name="paginacao">
        <div style="text-align: center; font-size: 8pt;">{PAGENO}/{nbpg}</div>
    </htmlpagefooter>
    <sethtmlpagefooter name="paginacao" value="on" />

    <?php
    // Preparar dados de status
    $statusLabel = '';
    $statusClass = '';
    switch ($manutencao['status'] ?? '') {
        case 'C':
            $statusLabel = t('modules.manutencao.status_options.created');
            $statusClass = 'badge-criada';
            break;
        case 'A':
            $statusLabel = t('modules.manutencao.status_options.open');
            $statusClass = 'badge-aberta';
            break;
        case 'F':
            $statusLabel = t('modules.manutencao.status_options.closed');
            $statusClass = 'badge-fechada';
            break;
    }

    // Helper para tanque
    function formatTanque($valor): string {
        if (empty($valor) && $valor !== '0' && $valor !== 0) return '-';
        $v = (int) $valor;
        if ($v == 8) return $v . ' (' . t('modules.manutencao.tank_levels.full') . ')';
        if ($v == 0) return $v . ' (' . t('modules.manutencao.tank_levels.reserve') . ')';
        return $v . '/8';
    }
    ?>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <?php if (!empty($logoPath)): ?>
                    <img src="<?= $logoPath ?>" class="logo-img" alt="Logo"><br>
                <?php endif; ?>
                <div class="empresa-nome"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora') ?></div>
                <div class="empresa-detalhe"><?= t('modules.manutencao.print.cpf_cnpj_label') ?> <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="doc-titulo"><?= t('modules.manutencao.print.title') ?></div>
                <div class="doc-detalhe"><strong><?= t('modules.manutencao.fields.os') ?>:</strong> <?= htmlspecialchars($manutencao['os'] ?? '-') ?></div>
                <div class="doc-detalhe"><strong><?= t('modules.manutencao.fields.status') ?>:</strong>
                    <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Dados Gerais -->
    <div class="section-title"><?= t('modules.manutencao.sections.maintenance_data') ?></div>
    <table class="dados-table">
        <tr>
            <td class="dados-label"><?= t('modules.veiculos.fields.plate') ?>:</td>
            <td class="dados-value"><?= htmlspecialchars($manutencao['veiculo_placa'] ?? '-') ?></td>
            <td class="dados-label"><?= t('modules.manutencao.fields.vehicle') ?>:</td>
            <td class="dados-value"><?= htmlspecialchars(trim(($manutencao['veiculo_marca'] ?? '') . ' ' . ($manutencao['veiculo_modelo'] ?? '')) ?: '-') ?></td>
        </tr>
        <tr>
            <td class="dados-label"><?= t('modules.manutencao.fields.workshop') ?>:</td>
            <td class="dados-value" colspan="3"><?= htmlspecialchars($manutencao['oficina_nome'] ?? '-') ?></td>
        </tr>
    </table>

    <!-- Envio e Retorno lado a lado -->
    <table class="col-table">
        <tr>
            <td style="width: 49%; padding-right: 4px;">
                <table class="sub-table" style="margin-top: 10px;">
                    <tr>
                        <td colspan="2" style="font-size: 10pt; font-weight: bold; background: #f0f0f0; border-left: 3px solid #333; padding: 5px 10px; border-bottom: 8px solid #fff;"><?= t('modules.manutencao.sections.send_to_workshop') ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.send_date') ?>:</td>
                        <td class="sub-value"><?= !empty($manutencao['data_enviado']) ? format_operational_datetime($manutencao['data_enviado']) : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.odometer') ?>:</td>
                        <td class="sub-value"><?= !empty($manutencao['odo_enviado']) ? number_format((float)$manutencao['odo_enviado'], 0, ',', '.') . ' km' : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.tank') ?>:</td>
                        <td class="sub-value"><?= formatTanque($manutencao['tanque_enviado'] ?? null) ?></td>
                    </tr>
                </table>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; padding-left: 4px;">
                <table class="sub-table" style="margin-top: 10px;">
                    <tr>
                        <td colspan="2" style="font-size: 10pt; font-weight: bold; background: #f0f0f0; border-left: 3px solid #333; padding: 5px 10px; border-bottom: 8px solid #fff;"><?= t('modules.manutencao.sections.return_from_workshop') ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.return_date') ?>:</td>
                        <td class="sub-value"><?= !empty($manutencao['data_retorno']) ? format_operational_datetime($manutencao['data_retorno']) : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.odometer') ?>:</td>
                        <td class="sub-value"><?= !empty($manutencao['odo_retorno']) ? number_format((float)$manutencao['odo_retorno'], 0, ',', '.') . ' km' : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.tank') ?>:</td>
                        <td class="sub-value"><?= formatTanque($manutencao['tanque_retorno'] ?? null) ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.changed_oil') ?>:</td>
                        <td class="sub-value"><?= ($manutencao['trocou_oleo'] ?? '') === 'S' ? t('common.labels.yes') : t('common.labels.no') ?></td>
                    </tr>
                    <tr>
                        <td class="sub-label"><?= t('modules.manutencao.fields.changed_tires') ?>:</td>
                        <td class="sub-value"><?= ($manutencao['trocou_pneus'] ?? '') === 'S' ? t('common.labels.yes') : t('common.labels.no') ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <?php if (!empty($manutencao['motivo'])): ?>
    <div style="font-size: 9pt; font-weight: bold; margin-top: 10px; margin-bottom: 4px;"><?= t('modules.manutencao.fields.send_reason') ?>:</div>
    <div class="motivo-box"><?= nl2br(htmlspecialchars($manutencao['motivo'])) ?></div>
    <?php endif; ?>

    <?php if (!empty($manutencao['obs_oficina'])): ?>
    <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.manutencao.fields.workshop_notes') ?>:</div>
    <div class="motivo-box"><?= nl2br(htmlspecialchars($manutencao['obs_oficina'])) ?></div>
    <?php endif; ?>

    <!-- Itens / Servicos -->
    <?php
    $itens = $manutencao['itens'] ?? [];
    $totalServicos = 0;
    $totalPago = 0;
    $totalPendente = 0;
    $totalDescontos = 0;
    foreach ($itens as $item) {
        $valorTotal = (float)($item['valor_total'] ?? 0);
        $totalDescontos += (float)($item['desconto'] ?? 0);
        $totalServicos += $valorTotal;
        if (($item['pago'] ?? 'N') === 'S') {
            $totalPago += $valorTotal;
        } else {
            $totalPendente += $valorTotal;
        }
    }
    ?>
    <div class="section-title"><?= t('modules.manutencao.sections.services_performed') ?></div>
    <?php if (!empty($itens)): ?>
    <table class="itens-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 38%;"><?= t('modules.manutencao.fields.description') ?></th>
                <th style="width: 10%; text-align: center;"><?= t('modules.manutencao.fields.qty') ?></th>
                <th style="width: 14%; text-align: right;"><?= t('modules.manutencao.fields.unit_value') ?></th>
                <th style="width: 13%; text-align: right;"><?= t('modules.manutencao.fields.discount') ?></th>
                <th style="width: 12%; text-align: right;"><?= t('modules.manutencao.fields.total_value') ?></th>
                <th style="width: 10%; text-align: center;"><?= t('modules.manutencao.fields.status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $i => $item): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($item['descricao'] ?? '-') ?></td>
                <td style="text-align: center;"><?= number_format((float)($item['quantidade'] ?? 1), 0) ?></td>
                <td style="text-align: right;"><?= currency_format((float)($item['valor_unitario'] ?? 0)) ?></td>
                <td style="text-align: right;"><?= currency_format((float)($item['desconto'] ?? 0)) ?></td>
                <td style="text-align: right;"><?= currency_format((float)($item['valor_total'] ?? 0)) ?></td>
                <td style="text-align: center;">
                    <?php if (($item['pago'] ?? 'N') === 'S'): ?>
                        <span class="badge badge-pago"><?= t('modules.manutencao.badges.paid') ?></span>
                    <?php else: ?>
                        <span class="badge badge-pendente"><?= t('modules.manutencao.badges.pending') ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totais -->
    <table class="totais-table">
        <tr>
            <td class="totais-label"><?= t('modules.manutencao.fields.discount') ?></td>
            <td class="totais-value"><?= currency_format($totalDescontos) ?></td>
        </tr>
        <tr>
            <td class="totais-label"><?= t('modules.manutencao.table.totals') ?></td>
            <td class="totais-value"><?= currency_format($totalServicos) ?></td>
        </tr>
        <tr>
            <td class="totais-label"><?= t('modules.manutencao.table.total_paid') ?></td>
            <td class="totais-value"><?= currency_format($totalPago) ?></td>
        </tr>
        <tr>
            <td class="totais-label"><?= t('modules.manutencao.table.total_pending') ?></td>
            <td class="totais-value"><?= currency_format($totalPendente) ?></td>
        </tr>
    </table>
    <?php else: ?>
    <p style="font-size: 9pt; color: #999;"><?= t('modules.manutencao.messages.no_items') ?></p>
    <?php endif; ?>
</body>
</html>
