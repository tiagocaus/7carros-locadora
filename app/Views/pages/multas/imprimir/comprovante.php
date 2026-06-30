<!DOCTYPE html>
<html lang="<?= current_locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.multas.pdf.receipt_title') ?> - <?= htmlspecialchars($multa['n_infracao'] ?? '') ?></title>
    <style>
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
    </style>
</head>
<body>
    <?php $_docTitulo = strtoupper(t('modules.multas.pdf.receipt_title')); include __DIR__ . '/_partials/_header.php'; ?>

    <!-- Valor pago em destaque -->
    <div class="valor-destaque">
        <div class="label"><?= t('modules.multas.pdf.amount_paid_label') ?></div>
        <div class="valor pago"><?= currency_format((float) ($multa['valor'] ?? 0)) ?></div>
    </div>

    <div class="valor-extenso">
        (<?= currency_extenso((float) ($multa['valor'] ?? 0)) ?>)
    </div>

    <!-- Texto do recibo -->
    <div class="texto-doc">
        <?= t('modules.multas.pdf.receipt_text', [
            'client' => '<strong>' . htmlspecialchars($multa['cliente_nome'] ?? '-') . '</strong>',
            'document' => '<strong>' . htmlspecialchars($multa['cliente_cpf_cnpj'] ?? '-') . '</strong>',
            'value' => '<strong>' . currency_format((float) ($multa['valor'] ?? 0)) . '</strong>',
            'fine_number' => '<strong>' . htmlspecialchars($multa['n_infracao'] ?? '-') . '</strong>',
            'plate' => '<strong>' . htmlspecialchars($multa['veiculo_placa'] ?? '-') . '</strong>',
            'fine_date' => '<strong>' . (!empty($multa['data_hora']) ? format_date(substr($multa['data_hora'], 0, 10)) : '-') . '</strong>',
        ]) ?>
    </div>

    <!-- Bloco: Dados da multa de origem -->
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.fine_origin_section') ?></div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell label w25"><?= t('modules.multas.pdf.fine_number_label') ?></div>
                <div class="grid-cell w25"><?= htmlspecialchars($multa['n_infracao'] ?? '-') ?></div>
                <div class="grid-cell label w25"><?= t('modules.multas.pdf.ait_label') ?></div>
                <div class="grid-cell w25"><?= htmlspecialchars($multa['numero_ait'] ?? '-') ?></div>
            </div>
            <div class="grid-row">
                <div class="grid-cell label"><?= t('modules.multas.pdf.issuing_body_label') ?></div>
                <div class="grid-cell"><?= htmlspecialchars($multa['orgao_autuador'] ?? '-') ?></div>
                <div class="grid-cell label"><?= t('modules.multas.pdf.fine_date_label') ?></div>
                <div class="grid-cell"><?= !empty($multa['data_hora']) ? format_operational_datetime($multa['data_hora']) : '-' ?></div>
            </div>
        </div>
    </div>

    <!-- Local + data atual -->
    <div class="local-data">
        <?php $_fmt = new IntlDateFormatter(current_locale(), IntlDateFormatter::LONG, IntlDateFormatter::NONE); ?>
        <?= htmlspecialchars(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? '')) ?>, <?= $_fmt->format(new DateTime()) ?>
    </div>

    <!-- Assinatura da empresa -->
    <div class="assinatura">
        <div class="linha">
            <div class="nome"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora') ?></div>
            <div class="doc">CNPJ: <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
        </div>
    </div>

    <div class="footer">
        <p><?= t('modules.multas.pdf.receipt_validity') ?></p>
        <p><?= t('modules.multas.pdf.generated_at', ['datetime' => format_datetime(now())]) ?></p>
    </div>
</body>
</html>
