<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('modules.promissorias.title_singular') ?> <?= htmlspecialchars($promissoria['codigo_base'] ?? '') ?></title>
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
        .valor-extenso {
            text-align: center;
            font-style: italic;
            font-size: 10pt;
            color: #666;
            margin-bottom: 25px;
            padding: 10px;
            background: #fafafa;
            border: 1px dashed #ddd;
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
        .status-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px 15px;
            margin: 20px 0;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-label {
            font-size: 10pt;
            color: #666;
        }
        .status-value {
            font-weight: bold;
            font-size: 11pt;
        }
        .status-pago {
            color: #16a34a;
        }
        .status-pendente {
            color: #ca8a04;
        }
        .parcelas-section {
            margin: 25px 0;
        }
        .parcelas-section h3 {
            font-size: 12pt;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .parcelas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        .parcelas-table th,
        .parcelas-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .parcelas-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .parcelas-table .text-center {
            text-align: center;
        }
        .parcelas-table .text-right {
            text-align: right;
        }
        .assinatura-section {
            margin-top: 60px;
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
        <div class="titulo-promissoria"><?= t('modules.promissorias.print.promissory_note') ?></div>

        <!-- Numero -->
        <div class="numero-promissoria">
            <?= t('modules.promissorias.print.code') ?>: <?= htmlspecialchars($promissoria['codigo_base'] ?? '') ?>
        </div>

        <!-- Valor em Destaque -->
        <div class="valor-destaque">
            <div class="label"><?= t('modules.promissorias.print.total_value') ?></div>
            <div class="valor"><?= currency_format((float) ($promissoria['valor_total'] ?? 0)) ?></div>
        </div>

        <!-- Status (calculado antes do texto legal) -->
        <?php
        $todasPagas = (int)($promissoria['qtd_pagas'] ?? 0) === (int)($promissoria['qtd_parcelas'] ?? 0) && (int)($promissoria['qtd_parcelas'] ?? 0) > 0;
        ?>

        <!-- Texto Legal -->
        <div class="texto-legal">
            <?php if (!empty($textoLegal)): ?>
                <?= $textoLegal ?>
            <?php else: ?>
                <!-- Fallback caso template nao seja encontrado -->
                <?php if ($todasPagas): ?>
                Pelo presente instrumento particular, declara-se que
                <strong><?= htmlspecialchars($promissoria['cliente_nome'] ?? 'N/A') ?></strong>,
                inscrito(a) no CPF/CNPJ sob o n. <strong><?= htmlspecialchars($promissoria['cliente_cpf_cnpj'] ?? 'N/A') ?></strong>,
                <strong>PAGOU</strong> ao <strong>CREDOR</strong> a importancia total de
                <strong><?= currency_format_extenso((float) ($promissoria['valor_total'] ?? 0)) ?></strong>,
                em <strong><?= htmlspecialchars($promissoria['qtd_parcelas'] ?? 0) ?></strong> parcela(s),
                dando-se por <strong>QUITADA</strong> a presente promissoria.
                <?php else: ?>
                Pelo presente instrumento particular de confissao de divida,
                <strong><?= htmlspecialchars($promissoria['cliente_nome'] ?? 'N/A') ?></strong>,
                inscrito(a) no CPF/CNPJ sob o n. <strong><?= htmlspecialchars($promissoria['cliente_cpf_cnpj'] ?? 'N/A') ?></strong>,
                doravante denominado(a) <strong>DEVEDOR(A)</strong>,
                promete pagar ao <strong>CREDOR</strong> ou a sua ordem, a importancia total de
                <strong><?= currency_format_extenso((float) ($promissoria['valor_total'] ?? 0)) ?></strong>,
                em <strong><?= htmlspecialchars($promissoria['qtd_parcelas'] ?? 0) ?></strong> parcela(s),
                conforme discriminado abaixo, pagavel na praca de
                <strong><?= htmlspecialchars($promissoria['cliente_cidade'] ?? ($empresa['cidade'] ?? '')) ?></strong>.
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="status-box">
            <div>
                <span class="status-label"><?= t('modules.promissorias.print.paid_installments') ?>:</span>
                <span class="status-value"><?= htmlspecialchars($promissoria['qtd_pagas'] ?? 0) ?>/<?= htmlspecialchars($promissoria['qtd_parcelas'] ?? 0) ?></span>
            </div>
            <div>
                <span class="status-label"><?= t('modules.promissorias.print.status') ?>:</span>
                <span class="status-value <?= $todasPagas ? 'status-pago' : 'status-pendente' ?>">
                    <?= $todasPagas ? t('modules.promissorias.status.paid_off_upper') : t('modules.promissorias.status.pending_upper') ?>
                </span>
            </div>
        </div>

        <!-- Parcelas -->
        <?php if (!empty($promissoria['parcelas']) && count($promissoria['parcelas']) > 0): ?>
        <div class="parcelas-section">
            <h3><?= t('modules.promissorias.print.installments') ?></h3>
            <table class="parcelas-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;"><?= t('modules.promissorias.print.installment_short') ?></th>
                        <th class="text-center" style="width: 100px;"><?= t('modules.promissorias.print.due_date') ?></th>
                        <th class="text-right"><?= t('modules.promissorias.print.total_value') ?></th>
                        <th class="text-center" style="width: 80px;"><?= t('modules.promissorias.print.status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promissoria['parcelas'] as $i => $parcela): ?>
                    <tr>
                        <td class="text-center"><?= ($parcela['numero_parcela'] ?? ($i + 1)) ?></td>
                        <td class="text-center">
                            <?php
                            if (!empty($parcela['data_vencimento'])) {
                                $data = new DateTime($parcela['data_vencimento']);
                                echo $data->format('d/m/Y');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="text-right"><?= currency_format((float) ($parcela['valor_parcela'] ?? 0)) ?></td>
                        <td class="text-center <?= ($parcela['pago'] ?? 'N') === 'S' ? 'status-pago' : 'status-pendente' ?>">
                            <?= ($parcela['pago'] ?? 'N') === 'S' ? t('modules.promissorias.status.paid') : t('modules.promissorias.status.pending') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Area de Assinatura -->
        <div class="assinatura-section">
            <div class="assinatura-linha">
                <div class="assinatura-nome"><?= htmlspecialchars($promissoria['cliente_nome'] ?? t('modules.promissorias.print.debtor')) ?></div>
                <div class="assinatura-doc"><?= htmlspecialchars($promissoria['cliente_cpf_cnpj'] ?? '') ?></div>
            </div>
        </div>

        <!-- Data de Emissao -->
        <div class="data-emissao">
            <?php
            $mesAtual = t('common.months.' . (int)\App\Helpers\DateHelper::todayForDatabase('m'));
            ?>
            <?= htmlspecialchars($promissoria['cliente_cidade'] ?? ($empresa['cidade'] ?? '')) ?>, <?= \App\Helpers\DateHelper::todayForDatabase('d') ?> de <?= $mesAtual ?> de <?= \App\Helpers\DateHelper::todayForDatabase('Y') ?>
        </div>

        
    </div>

    <!-- Auto-print removido - impressao controlada pelo modal -->
</body>
</html>
