<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('modules.promissorias.print.installment_short') ?> <?= htmlspecialchars($parcela['numero_parcela']) ?> - <?= htmlspecialchars($parcela['codigo_base'] ?? '') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            padding: 40px;
        }
        .promissoria-container {
            margin: 0 auto;
            border: 2px solid #333;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 14pt;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 9pt;
            color: #666;
        }
        .titulo-promissoria {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 20px 0;
            padding: 10px;
            background: #f0f0f0;
            border: 1px solid #ddd;
        }
        .numero-promissoria {
            text-align: right;
            font-size: 10pt;
            color: #666;
            margin-bottom: 20px;
        }
        .valor-destaque {
            text-align: center;
            margin: 25px 0;
        }
        .valor-destaque .label {
            font-size: 10pt;
            color: #666;
        }
        .valor-destaque .valor {
            font-size: 24pt;
            font-weight: bold;
            color: #333;
        }
        .info-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        .info-label {
            font-size: 10pt;
            color: #666;
        }
        .info-value {
            font-weight: bold;
        }
        .status-pago {
            color: #16a34a;
        }
        .status-pendente {
            color: #ca8a04;
        }
        .texto-legal {
            text-align: justify;
            margin: 25px 0;
            font-size: 10pt;
            line-height: 1.8;
        }
        .texto-legal strong {
            color: #000;
        }
        .assinatura-section {
            margin-top: 200px;
            text-align: center;
        }
        .assinatura-linha {
            border-top: 1px solid #333;
            width: 350px;
            margin: 0 auto;
            padding-top: 10px;
        }
        .assinatura-nome {
            font-size: 10pt;
        }
        .assinatura-doc {
            font-size: 9pt;
        }
        .data-emissao {
            text-align: right;
            margin-top: 40px;
            font-size: 10pt;
        }
        @media print {
            body {
                padding: 0;
            }
            .promissoria-container {
                border: none;
            }
            @page {
                margin: 2cm;
            }
        }
    </style>
</head>
<body>
    <div class="promissoria-container">
        <!-- Cabecalho da Empresa -->
        <div class="header">
            <?php if (!empty($empresa)): ?>
                <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora') ?></h1>
                <?php if (!empty($empresa['cnpj'])): ?>
                    <p>CNPJ: <?= htmlspecialchars($empresa['cnpj']) ?></p>
                <?php endif; ?>
                <?php if (!empty($empresa['endereco'])): ?>
                    <p><?= htmlspecialchars($empresa['endereco']) ?><?= !empty($empresa['numero']) ? ', ' . htmlspecialchars($empresa['numero']) : '' ?> - <?= htmlspecialchars($empresa['cidade'] ?? '') ?>/<?= htmlspecialchars($empresa['estado'] ?? '') ?></p>
                <?php endif; ?>
            <?php else: ?>
                <h1>Locadora</h1>
            <?php endif; ?>
        </div>

        <!-- Titulo -->
        <div class="titulo-promissoria"><?= t('modules.promissorias.print.promissory_note_installment', ['num' => htmlspecialchars($parcela['numero_parcela']), 'total' => htmlspecialchars($parcela['total_parcelas'])]) ?></div>

        <!-- Numero -->
        <div class="numero-promissoria">
            <?= t('modules.promissorias.print.code') ?>: <?= htmlspecialchars($parcela['codigo'] ?? $parcela['codigo_base'] . '-' . $parcela['numero_parcela']) ?>
        </div>

        <!-- Valor em Destaque -->
        <div class="valor-destaque">
            <div class="label"><?= t('modules.promissorias.print.installment_value') ?></div>
            <div class="valor"><?= currency_format((float) ($parcela['valor_parcela'] ?? 0)) ?></div>
        </div>

        <!-- Informacoes da Parcela -->
        <div class="info-box">
            <div class="info-row">
                <span class="info-label"><?= t('modules.promissorias.print.due_date') ?>:</span>
                <span class="info-value">
                    <?php
                    if (!empty($parcela['data_vencimento'])) {
                        $data = new DateTime($parcela['data_vencimento']);
                        echo $data->format('d/m/Y');
                    } else {
                        echo '-';
                    }
                    ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label"><?= t('modules.promissorias.print.status') ?>:</span>
                <span class="info-value <?= ($parcela['pago'] ?? 'N') === 'S' ? 'status-pago' : 'status-pendente' ?>">
                    <?= ($parcela['pago'] ?? 'N') === 'S' ? t('modules.promissorias.status.paid_upper') : t('modules.promissorias.status.pending_upper') ?>
                </span>
            </div>
            <?php if (($parcela['pago'] ?? 'N') === 'S' && !empty($parcela['data_pagamento'])): ?>
            <div class="info-row">
                <span class="info-label"><?= t('modules.promissorias.print.payment_date') ?>:</span>
                <span class="info-value">
                    <?php
                    $dataPag = new DateTime($parcela['data_pagamento']);
                    echo $dataPag->format('d/m/Y');
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Texto Legal -->
        <div class="texto-legal">
            <?php if (!empty($textoLegal)): ?>
                <?= $textoLegal ?>
            <?php else: ?>
                <!-- Fallback caso template nao seja encontrado -->
                <?php if (($parcela['pago'] ?? 'N') === 'S'): ?>
                Pelo presente instrumento particular, declara-se que
                <strong><?= htmlspecialchars($parcela['cliente_nome'] ?? 'N/A') ?></strong>,
                inscrito(a) no CPF/CNPJ sob o n. <strong><?= htmlspecialchars($parcela['cliente_cpf_cnpj'] ?? 'N/A') ?></strong>,
                <strong>PAGOU</strong> ao <strong>CREDOR</strong> a importancia de
                <strong><?= currency_format_extenso((float) ($parcela['valor_parcela'] ?? 0)) ?></strong>,
                referente a parcela <strong><?= htmlspecialchars($parcela['numero_parcela']) ?></strong> de <strong><?= htmlspecialchars($parcela['total_parcelas']) ?></strong>,
                dando-se por <strong>QUITADA</strong> a presente parcela.
                <?php else: ?>
                Pelo presente instrumento particular de confissao de divida,
                <strong><?= htmlspecialchars($parcela['cliente_nome'] ?? 'N/A') ?></strong>,
                inscrito(a) no CPF/CNPJ sob o n. <strong><?= htmlspecialchars($parcela['cliente_cpf_cnpj'] ?? 'N/A') ?></strong>,
                doravante denominado(a) <strong>DEVEDOR(A)</strong>,
                promete pagar ao <strong>CREDOR</strong> ou a sua ordem, a importancia de
                <strong><?= currency_format_extenso((float) ($parcela['valor_parcela'] ?? 0)) ?></strong>,
                referente a parcela <strong><?= htmlspecialchars($parcela['numero_parcela']) ?></strong> de <strong><?= htmlspecialchars($parcela['total_parcelas']) ?></strong>,
                com vencimento em <strong><?php
                if (!empty($parcela['data_vencimento'])) {
                    $dataVenc = new DateTime($parcela['data_vencimento']);
                    echo $dataVenc->format('d/m/Y');
                }
                ?></strong>,
                pagavel na praca de
                <strong><?= htmlspecialchars($parcela['cliente_cidade'] ?? ($empresa['cidade'] ?? '')) ?></strong>.
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Area de Assinatura -->
        <div class="assinatura-section">
            <div class="assinatura-linha">
                <div class="assinatura-nome"><?= htmlspecialchars($parcela['cliente_nome'] ?? t('modules.promissorias.print.debtor')) ?></div>
                <div class="assinatura-doc"><?= htmlspecialchars($parcela['cliente_cpf_cnpj'] ?? '') ?></div>
            </div>
        </div>

        <!-- Data de Emissao -->
        <div class="data-emissao">
            <?php
            $mesAtual = t('common.months.' . (int)\App\Helpers\DateHelper::todayForDatabase('m'));
            ?>
            <?= htmlspecialchars($parcela['cliente_cidade'] ?? ($empresa['cidade'] ?? '')) ?>, <?= \App\Helpers\DateHelper::todayForDatabase('d') ?> de <?= $mesAtual ?> de <?= \App\Helpers\DateHelper::todayForDatabase('Y') ?>
        </div>
    </div>
</body>
</html>
