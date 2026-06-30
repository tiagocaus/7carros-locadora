<?php $htmlLocale = locale_info()["code"] ?? "pt-BR"; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, "UTF-8") ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.locacoes.pdf.receipt_title') ?> - <?= t('modules.locacoes.print.rental_label') ?> <?= htmlspecialchars($locacao['codigo']) ?></title>
    <style>
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>

        .recibo-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 18px;
        }
        .valor-destaque { text-align: center; margin: 10px 0; }
        .valor-destaque .label { font-size: 9pt; color: #666; }
        .valor-destaque .valor { font-size: 18pt; font-weight: bold; color: #333; }
        .valor-extenso {
            text-align: center;
            font-style: italic;
            font-size: 9pt;
            color: #666;
            margin-bottom: 10px;
            padding: 5px;
            background: #fafafa;
            border: 1px dashed #ddd;
        }
        .texto-recibo {
            text-align: justify;
            margin: 10px 0;
            line-height: 1.5;
        }
        .texto-recibo strong { color: #000; }
        .local-data {
            text-align: right;
            margin: 12px 0;
            font-size: 9pt;
        }
        .assinatura {
            text-align: center;
            margin-top: 40px;
        }
        .assinatura .linha {
            border-top: 1px solid #333;
            width: 300px;
            margin: 0 auto;
            padding-top: 5px;
        }
        .assinatura .nome { font-weight: bold; }
        .assinatura .doc { font-size: 8pt; color: #666; }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-size: 7pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <?php $_docTitulo = t('modules.locacoes.pdf.receipt_title'); include __DIR__ . '/_partials/_header.php'; ?>

    <div class="recibo-container">
        <div class="valor-destaque">
            <div class="label"><?= t('modules.locacoes.pdf.receipt_value') ?></div>
            <div class="valor"><?= currency_format((float) ($locacao['total_pagar'] ?? $locacao['valor_total'] ?? 0)) ?></div>
        </div>

        <div class="valor-extenso">
            (<?= currency_extenso((float) ($locacao['total_pagar'] ?? $locacao['valor_total'] ?? 0)) ?>)
        </div>

        <div class="texto-recibo">
            <?php
                $vehicleText = '';
                if ($veiculo) {
                    $vehicleText = t('modules.locacoes.pdf.receipt_vehicle_text', ['vehicle' => htmlspecialchars(($veiculo['placa'] ?? $locacao['veiculo_placa'] ?? '') . ' - ' . ($veiculo['modelo'] ?? ''))]);
                }
                echo t('modules.locacoes.pdf.receipt_text', [
                    'client' => '<strong>' . htmlspecialchars($locacao['cliente_nome_completo'] ?? 'N/A') . '</strong>',
                    'document' => '<strong>' . htmlspecialchars($locacao['cliente_cpf_cnpj'] ?? 'N/A') . '</strong>',
                    'code' => '<strong>' . htmlspecialchars($locacao['codigo']) . '</strong>',
                    'vehicle' => $vehicleText ? '<strong>' . htmlspecialchars(($veiculo['placa'] ?? '') . ' - ' . ($veiculo['modelo'] ?? '')) . '</strong>' : '',
                    'start' => '<strong>' . format_date($locacao['data_saida']) . '</strong>',
                    'end' => '<strong>' . format_date($locacao['data_prevista']) . '</strong>',
                    'payment' => '<strong>' . htmlspecialchars($locacao['forma_pagamento_descricao'] ?? 'N/A') . '</strong>',
                ]);
            ?>
        </div>

        <div class="local-data">
            <?php $_fmt = new IntlDateFormatter(current_locale(), IntlDateFormatter::LONG, IntlDateFormatter::NONE); ?>
            <?= htmlspecialchars(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? '')) ?>, <?= $_fmt->format(new DateTime()) ?>
        </div>

        <div class="assinatura">
            <div class="linha">
                <div class="nome"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? t('modules.locacoes.pdf.company_fallback')) ?></div>
                <div class="doc"><?= t('modules.locacoes.pdf.cpf_cnpj_label') ?> <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            </div>
        </div>

        <div class="footer">
            <p><?= t('modules.locacoes.pdf.receipt_validity') ?></p>
            <p><?= t('modules.locacoes.pdf.generated_at', ['datetime' => format_datetime(now())]) ?></p>
        </div>
    </div>
</body>
</html>
