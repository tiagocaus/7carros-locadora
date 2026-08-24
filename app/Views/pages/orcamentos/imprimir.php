<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; font-size: 10pt; }
        h1 { color: #25658d; font-size: 22pt; margin: 0; }
        h2 { color: #25658d; font-size: 12pt; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e8f1f6; text-align: left; color: #25658d; }
        th, td { padding: 7px; border-bottom: 1px solid #e2e8f0; }
        .header { border-bottom: 3px solid #25658d; padding-bottom: 12px; table-layout: fixed; }
        .header td { border: 0; padding-top: 0; padding-bottom: 0; vertical-align: middle; }
        .header-logo { width: 28%; padding-left: 0; text-align: left; }
        .header-title { width: 44%; text-align: center; }
        .header-meta { width: 28%; padding-right: 0; text-align: right; }
        .logo-img { max-width: 120px; max-height: 70px; }
        .company-name { margin-top: 4px; color: #64748b; font-size: 9pt; font-weight: bold; }
        .right { text-align: right; }
        .muted { color: #64748b; font-size: 9pt; }
        .total { font-size: 15pt; font-weight: bold; color: #25658d; }
        .notice { margin-top: 18px; padding: 10px; background: #fff7ed; border: 1px solid #fed7aa; }
    </style>
</head>
<body>
    <?php $logoPath = $logoPath ?? ''; ?>
    <table class="header">
        <tr>
            <td class="header-logo">
                <?php if ($logoPath !== ''): ?><img src="<?= e($logoPath) ?>" class="logo-img" alt="Logo"><?php endif; ?>
                <div class="company-name"><?= e($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? '') ?></div>
            </td>
            <td class="header-title"><h1>ORÇAMENTO</h1></td>
            <td class="header-meta"><strong><?= e($orcamento['codigo']) ?></strong><br><span class="muted">Emitido em <?= e(\App\Helpers\DateHelper::format($orcamento['created_at'])) ?><br>Válido até <?= e(\App\Helpers\DateHelper::format($orcamento['validade'])) ?></span></td>
        </tr>
    </table>

    <h2>Cliente</h2>
    <table><tr><td><strong><?= e($orcamento['cliente_nome']) ?></strong><br><span class="muted"><?= e($orcamento['cliente_documento'] ?? '') ?></span></td><td><?= e(trim(implode(', ', array_filter([$orcamento['cliente_rua'] ?? '', $orcamento['cliente_numero'] ?? '', $orcamento['cliente_bairro'] ?? '', $orcamento['cliente_cidade'] ?? '', $orcamento['cliente_estado'] ?? ''])))) ?></td></tr></table>

    <h2>Período e veículo</h2>
    <table>
        <tr><th>Retirada</th><th>Devolução</th><th>Período</th></tr>
        <tr><td><?= e(\App\Helpers\DateHelper::formatDateTime($orcamento['data_saida'])) ?><br><span class="muted"><?= e($orcamento['filial_retirada_nome'] ?? '') ?></span></td><td><?= e(\App\Helpers\DateHelper::formatDateTime($orcamento['data_prevista'])) ?><br><span class="muted"><?= e($orcamento['filial_devolucao_nome'] ?? '') ?></span></td><td><?= (int)$orcamento['dias'] ?> diária(s)</td></tr>
        <tr><th>Grupo</th><th>Plano</th><th>Preferência</th></tr>
        <tr><td><?= e($orcamento['grupo_nome']) ?></td><td><?= e(['KL'=>'Km Livre','KMC'=>'Km Controlado','DI'=>'Km Pago','KP'=>'Km Pago'][$orcamento['plano']] ?? $orcamento['plano']) ?></td><td><?= e(trim(($orcamento['veiculo_placa'] ?? '') . ' ' . ($orcamento['veiculo_marca'] ?? '') . ' ' . ($orcamento['veiculo_modelo'] ?? '')) ?: 'Veículo definido na retirada') ?></td></tr>
    </table>

    <h2>Composição</h2>
    <table>
        <tr><th>Descrição</th><th class="right">Quantidade</th><th class="right">Valor</th><th class="right">Total</th></tr>
        <tr><td>Diária — <?= e($orcamento['grupo_nome']) ?></td><td class="right"><?= (int)$orcamento['dias'] ?></td><td class="right"><?= currency_format($orcamento['diaria_valor']) ?></td><td class="right"><?= currency_format($orcamento['subtotal_diarias']) ?></td></tr>
        <?php if (!empty($orcamento['seguro_carro'])): ?><tr><td>Proteção do veículo</td><td class="right"><?= (int)$orcamento['dias'] ?></td><td class="right"><?= currency_format($orcamento['valor_seguro_carro']) ?></td><td class="right"><?= currency_format($orcamento['valor_seguro_carro'] * $orcamento['dias']) ?></td></tr><?php endif; ?>
        <?php if (!empty($orcamento['seguro_terceiros'])): ?><tr><td>Proteção para terceiros</td><td class="right"><?= (int)$orcamento['dias'] ?></td><td class="right"><?= currency_format($orcamento['valor_seguro_terceiros']) ?></td><td class="right"><?= currency_format($orcamento['valor_seguro_terceiros'] * $orcamento['dias']) ?></td></tr><?php endif; ?>
        <?php foreach (($orcamento['taxas'] ?? []) as $taxa): ?><tr><td><?= e($taxa['nome'] ?? 'Taxa/serviço') ?></td><td class="right"><?= (int)($taxa['quantidade'] ?? 1) ?></td><td class="right"><?= currency_format($taxa['valor_unitario'] ?? 0) ?></td><td class="right"><?= currency_format($taxa['valor_total'] ?? 0) ?></td></tr><?php endforeach; ?>
        <tr><td colspan="3" class="right">Subtotal</td><td class="right"><?= currency_format($orcamento['total_fatura']) ?></td></tr>
        <?php if ((float)$orcamento['valor_desconto'] > 0): ?><tr><td colspan="3" class="right">Desconto</td><td class="right">- <?= currency_format($orcamento['valor_desconto']) ?></td></tr><?php endif; ?>
        <tr><td colspan="3" class="right total">TOTAL</td><td class="right total"><?= currency_format($orcamento['total_pagar']) ?></td></tr>
    </table>

    <?php if (!empty($orcamento['forma_pagamento_nome']) || !empty($orcamento['condicao_pagamento'])): ?><p><strong>Condição pretendida:</strong> <?= e(trim(($orcamento['forma_pagamento_nome'] ?? '') . ' — ' . ($orcamento['condicao_pagamento'] ?? ''), ' —')) ?></p><?php endif; ?>
    <?php if (!empty($orcamento['observacoes_cliente'])): ?><p><strong>Observações:</strong><br><?= nl2br(e($orcamento['observacoes_cliente'])) ?></p><?php endif; ?>
    <div class="notice"><strong>Atenção:</strong> este orçamento não bloqueia veículo nem garante disponibilidade. A disponibilidade será confirmada no momento da conversão em reserva.</div>
</body>
</html>
