<?php
/**
 * Template PDF: Fatura financeira (lancamento)
 *
 * Variaveis esperadas do controller:
 *   $lancamento (com 'itens' carregados via buscarPorIdComItens)
 *   $empresa, $cliente, $fornecedor, $contraparte, $logoPath, $linkPagamento (string|null)
 */

$tipoReceita = $tipoReceita ?? (($lancamento['tipo'] ?? 'D') === 'R');
$contraparte = $contraparte ?? ($tipoReceita ? ($cliente ?? []) : ($fornecedor ?? []));
$pago = ($lancamento['pago'] ?? 'N') === 'S';
$codigo = $lancamento['codigo'] ?? '';
$sequencia = $lancamento['sequencia'] ?? '';
$dataEmissao = !empty($lancamento['data_criada']) ? format_date($lancamento['data_criada']) : '-';
$dataVenci = !empty($lancamento['data_venci']) ? format_date($lancamento['data_venci']) : '-';
$dataPago = !empty($lancamento['data_pago']) ? format_date($lancamento['data_pago']) : null;

$money = fn($v) => currency_format((float) $v);

$endereco = trim(($empresa['rua'] ?? '') . ', ' . ($empresa['num'] ?? '') . ' - ' . ($empresa['bairro'] ?? ''), ' -,');
$cidadeUf = trim(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? ''), '/');

$contraparteNumero = $contraparte['numero'] ?? $contraparte['num'] ?? '';
$enderecoContraparte = trim(($contraparte['rua'] ?? '') . ', ' . $contraparteNumero . ' - ' . ($contraparte['bairro'] ?? ''), ' -,');
$cidadeUfContraparte = trim(($contraparte['cidade'] ?? '') . '/' . ($contraparte['estado'] ?? ''), '/');
$contraparteNome = $contraparte['nome_rsocial'] ?? ($tipoReceita ? ($lancamento['cliente_nome'] ?? '-') : ($lancamento['fornecedor_nome'] ?? '-'));
$contraparteDocumento = $contraparte['cpf_cnpj'] ?? ($tipoReceita ? ($lancamento['cliente_cpf_cnpj'] ?? '-') : '-');
$contraparteTelefone = $contraparte['celular'] ?? $contraparte['telefone'] ?? $contraparte['tel1'] ?? $contraparte['tel2'] ?? '';
$contraparteLabel = $tipoReceita ? t('modules.financeiro.print_pdf.customer') : t('modules.financeiro.print_pdf.supplier');

if ($pago) {
    $statusLabel = t('modules.financeiro.print_pdf.status_paid');
    $statusColor = '#16a34a';
} else {
    $hoje = strtotime(date('Y-m-d'));
    $venci = !empty($lancamento['data_venci']) ? strtotime($lancamento['data_venci']) : 0;
    if ($venci && $venci < $hoje) {
        $statusLabel = t('modules.financeiro.print_pdf.status_overdue');
        $statusColor = '#dc2626';
    } else {
        $statusLabel = t('modules.financeiro.print_pdf.status_open');
        $statusColor = '#d97706';
    }
}
$htmlLocale = locale_info()['code'] ?? 'pt-BR';
$documentCode = $codigo ?: '#' . ($lancamento['id'] ?? '');
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(t('modules.financeiro.print_pdf.title', ['number' => $documentCode]), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; color: #333; padding: 15px; }
        .header-table { width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .header-table td { vertical-align: top; padding: 0; }
        .empresa-nome { font-size: 14pt; font-weight: bold; margin-bottom: 3px; }
        .empresa-detalhe { font-size: 8pt; color: #666; }
        .doc-titulo { font-size: 13pt; font-weight: bold; text-align: right; margin-bottom: 5px; }
        .doc-detalhe { font-size: 9pt; text-align: right; color: #555; }
        .logo-img { max-height: 70px; max-width: 120px; margin-bottom: 5px; }
        .qr-img { width: 70px; height: 70px; }
        .section { margin-bottom: 12px; }
        .section-title { font-size: 9pt; font-weight: bold; background: #f0f0f0; padding: 4px 8px; margin-bottom: 8px; border-left: 3px solid #333; text-transform: uppercase; }
        .grid { display: table; width: 100%; }
        .grid-cell { display: table-cell; padding: 3px 8px; font-size: 9pt; border-bottom: 1px solid #eee; }
        .grid-cell.label { font-weight: bold; color: #666; font-size: 8pt; white-space: nowrap; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data-table th { background: #f0f0f0; padding: 5px 8px; text-align: left; font-size: 8pt; font-weight: bold; border: 1px solid #ddd; text-transform: uppercase; }
        table.data-table td { padding: 5px 8px; font-size: 9pt; border: 1px solid #eee; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 3px 8px; font-size: 9pt; border-bottom: 1px solid #eee; vertical-align: top; }
        table.kv td.label { font-weight: bold; color: #666; font-size: 8pt; white-space: nowrap; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totais-wrap { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .totais { width: 100%; border-collapse: collapse; }
        .totais td { padding: 4px 8px; font-size: 9pt; }
        .totais tr.total-final td { font-weight: bold; font-size: 11pt; border-top: 2px solid #333; padding-top: 8px; }
        .status-badge { display: inline-block; padding: 4px 10px; color: #fff; font-weight: bold; font-size: 9pt; border-radius: 3px; }
        .obs-box { background: #f9f9f9; border: 1px solid #eee; padding: 8px 10px; font-size: 9pt; margin-top: 8px; }
        .pagamento-box { margin-top: 18px; padding: 12px; border: 1px dashed #2563eb; background: #eff6ff; font-size: 9pt; word-break: break-all; }
        .pagamento-box strong { display: block; margin-bottom: 4px; color: #1e40af; }
        .footer { text-align: center; font-size: 8pt; color: #999; margin-top: 25px; padding-top: 8px; border-top: 1px solid #eee; }
    </style>
</head>
<body>

<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width: 18%; vertical-align: middle;">
            <?php if (!empty($logoPath)): ?>
            <img src="<?= $logoPath ?>" class="logo-img" alt="Logo">
            <?php endif; ?>
        </td>
        <td style="width: 42%;">
            <div class="empresa-nome"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? t('modules.financeiro.print_pdf.default_company')) ?></div>
            <div class="empresa-detalhe"><?= t('modules.financeiro.print_pdf.company_tax_id') ?>: <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            <?php if ($endereco): ?>
            <div class="empresa-detalhe"><?= htmlspecialchars($endereco) ?></div>
            <?php endif; ?>
            <?php if ($cidadeUf): ?>
            <div class="empresa-detalhe"><?= htmlspecialchars($cidadeUf) ?><?= !empty($empresa['cep']) ? ' - ' . t('modules.financeiro.print_pdf.zip') . ': ' . htmlspecialchars($empresa['cep']) : '' ?></div>
            <?php endif; ?>
            <?php if (!empty($empresa['celular'])): ?>
            <div class="empresa-detalhe"><?= t('modules.financeiro.print_pdf.phone_short') ?>: <?= htmlspecialchars($empresa['celular']) ?></div>
            <?php elseif (!empty($empresa['fixo'])): ?>
            <div class="empresa-detalhe"><?= t('modules.financeiro.print_pdf.phone_short') ?>: <?= htmlspecialchars($empresa['fixo']) ?></div>
            <?php endif; ?>
        </td>
        <td style="width: 25%;">
            <div class="doc-titulo"><?= t('modules.financeiro.print_pdf.invoice') ?></div>
            <div class="doc-detalhe"><strong><?= t('modules.financeiro.print_pdf.number') ?>:</strong> <?= htmlspecialchars($documentCode) ?><?= $sequencia ? '-' . htmlspecialchars($sequencia) : '' ?></div>
            <div class="doc-detalhe"><strong><?= t('modules.financeiro.print_pdf.issue_date') ?>:</strong> <?= $dataEmissao ?></div>
            <div class="doc-detalhe"><strong><?= t('modules.financeiro.print_pdf.due_date') ?>:</strong> <?= $dataVenci ?></div>
            <?php if ($dataPago): ?>
            <div class="doc-detalhe"><strong><?= t('modules.financeiro.print_pdf.paid_at') ?>:</strong> <?= $dataPago ?></div>
            <?php endif; ?>
            <div class="doc-detalhe" style="margin-top: 6px;">
                <span class="status-badge" style="background-color: <?= $statusColor ?>;"><?= $statusLabel ?></span>
            </div>
        </td>
        <td style="width: 15%; text-align: right; vertical-align: top;">
            <?php if (!empty($qrPath)): ?>
            <img src="<?= $qrPath ?>" class="qr-img" alt="QR Code">
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- Cliente/Fornecedor -->
<div class="section">
    <div class="section-title"><?= $contraparteLabel ?></div>
    <table class="kv" cellpadding="0" cellspacing="0">
        <tr>
            <td class="label" style="width:12%"><?= t('modules.financeiro.print_pdf.name') ?>:</td>
            <td style="width:55%"><?= htmlspecialchars($contraparteNome) ?></td>
            <td class="label" style="width:13%"><?= t('modules.financeiro.print_pdf.tax_id') ?>:</td>
            <td style="width:20%"><?= htmlspecialchars($contraparteDocumento) ?></td>
        </tr>
        <?php if ($enderecoContraparte || $cidadeUfContraparte): ?>
        <tr>
            <td class="label"><?= t('modules.financeiro.print_pdf.address') ?>:</td>
            <td><?= htmlspecialchars($enderecoContraparte ?: '-') ?></td>
            <td class="label"><?= t('modules.financeiro.print_pdf.city_state') ?>:</td>
            <td><?= htmlspecialchars($cidadeUfContraparte ?: '-') ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($contraparte['email']) || $contraparteTelefone !== ''): ?>
        <tr>
            <td class="label"><?= t('modules.financeiro.print_pdf.email') ?>:</td>
            <td><?= htmlspecialchars($contraparte['email'] ?? '-') ?></td>
            <td class="label"><?= t('modules.financeiro.print_pdf.phone') ?>:</td>
            <td><?= htmlspecialchars($contraparteTelefone ?: '-') ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- Descricao do lancamento -->
<div class="section">
    <div class="section-title"><?= t('modules.financeiro.print_pdf.description') ?></div>
    <div style="padding: 6px 8px; font-size: 10pt;"><?= htmlspecialchars($lancamento['descricao'] ?? '-') ?></div>
</div>

<!-- Itens (se houver) -->
<?php if (!empty($lancamento['itens']) && is_array($lancamento['itens'])): ?>
<div class="section">
    <div class="section-title"><?= t('modules.financeiro.print_pdf.items') ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70%;"><?= t('modules.financeiro.print_pdf.description') ?></th>
                <th class="text-right" style="width: 30%;"><?= t('modules.financeiro.print_pdf.value') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lancamento['itens'] as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['descricao'] ?? '-') ?></td>
                <td class="text-right"><?= $money($item['valor'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
    // Subtotal coerente: TOTAL - juros - multa + desconto. valor_subtotal pode estar
    // desatualizado em registros antigos (trigger calcula valor_total a partir dos itens).
    $jurosV = (float) ($lancamento['juros'] ?? 0);
    $multaV = (float) ($lancamento['multa'] ?? 0);
    $descontoV = (float) ($lancamento['desconto'] ?? 0);
    $totalV = (float) ($lancamento['valor_total'] ?? 0);
    $subtotalV = $totalV - $jurosV - $multaV + $descontoV;
?>
<!-- Totais -->
<table class="totais-wrap" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;"></td>
        <td style="width:50%;">
            <table class="totais" cellpadding="0" cellspacing="0">
                <tr>
                    <td><?= t('modules.financeiro.print_pdf.subtotal') ?>:</td>
                    <td class="text-right"><?= $money($subtotalV) ?></td>
                </tr>
                <?php if ($jurosV > 0): ?>
                <tr>
                    <td><?= t('modules.financeiro.print_pdf.interest') ?>:</td>
                    <td class="text-right"><?= $money($jurosV) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($multaV > 0): ?>
                <tr>
                    <td><?= t('modules.financeiro.print_pdf.penalty') ?>:</td>
                    <td class="text-right"><?= $money($multaV) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($descontoV > 0): ?>
                <tr>
                    <td><?= t('modules.financeiro.print_pdf.discount') ?>:</td>
                    <td class="text-right">- <?= $money($descontoV) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-final">
                    <td><?= t('modules.financeiro.print_pdf.total') ?>:</td>
                    <td class="text-right"><?= $money($totalV) ?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<?php if (!empty($lancamento['observacao'])): ?>
<div class="section" style="margin-top: 15px;">
    <div class="section-title"><?= t('modules.financeiro.print_pdf.observations') ?></div>
    <div class="obs-box"><?= nl2br(htmlspecialchars($lancamento['observacao'])) ?></div>
</div>
<?php endif; ?>

<?php if ($linkPagamento && !$pago): ?>
<div class="pagamento-box">
    <strong><?= t('modules.financeiro.print_pdf.online_payment_link') ?>:</strong>
    <?= htmlspecialchars($linkPagamento) ?>
</div>
<?php endif; ?>

<div class="footer">
    <?= t('modules.financeiro.print_pdf.generated_at', ['date' => format_datetime(date('Y-m-d H:i:s'))]) ?>
</div>

</body>
</html>
