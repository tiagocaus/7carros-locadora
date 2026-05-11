<!DOCTYPE html>
<html lang="<?= current_locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.multas.pdf.notification_title') ?> - <?= htmlspecialchars($multa['n_infracao'] ?? '') ?></title>
    <style>
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
    </style>
</head>
<body>
    <?php $_docTitulo = strtoupper(t('modules.multas.pdf.notification_title')); include __DIR__ . '/_partials/_header.php'; ?>

    <!-- Bloco: Identificacao do Cliente -->
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.client_section') ?></div>
        <table class="kv" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label w25"><?= t('modules.multas.pdf.client_name') ?></td>
                <td class="w50"><?= htmlspecialchars($multa['cliente_nome'] ?? '-') ?></td>
                <td class="label w25"><?= t('modules.multas.pdf.client_document') ?></td>
                <td><?= htmlspecialchars($multa['cliente_cpf_cnpj'] ?? '-') ?></td>
            </tr>
        </table>
    </div>

    <!-- Bloco: Dados da Infracao -->
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.fine_data_section') ?></div>
        <table class="kv" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label w25"><?= t('modules.multas.pdf.ait_label') ?></td>
                <td class="w25"><?= htmlspecialchars($multa['numero_ait'] ?? '-') ?></td>
                <td class="label w25"><?= t('modules.multas.pdf.infraction_code_label') ?></td>
                <td class="w25"><?= htmlspecialchars($multa['codigo_infracao'] ?? $multa['n_infracao'] ?? '-') ?></td>
            </tr>
            <tr>
                <td class="label"><?= t('modules.multas.pdf.issuing_body_label') ?></td>
                <td colspan="3"><?= htmlspecialchars($multa['orgao_autuador'] ?? '-') ?></td>
            </tr>
            <tr>
                <td class="label"><?= t('modules.multas.pdf.location_label') ?></td>
                <td><?= htmlspecialchars($multa['local'] ?? '-') ?></td>
                <td class="label"><?= t('modules.multas.pdf.city_state_label') ?></td>
                <td><?= htmlspecialchars(($multa['cidade'] ?? '') . '/' . ($multa['estado'] ?? '')) ?></td>
            </tr>
            <tr>
                <td class="label"><?= t('modules.multas.pdf.date_time_label') ?></td>
                <td><?= !empty($multa['data_hora']) ? format_datetime($multa['data_hora']) : '-' ?></td>
                <td class="label"><?= t('modules.multas.pdf.description_label') ?></td>
                <td><?= htmlspecialchars($multa['descri'] ?? '-') ?></td>
            </tr>
        </table>
    </div>

    <!-- Bloco: Veiculo -->
    <?php if (!empty($multa['veiculo_placa'])): ?>
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.vehicle_data_section') ?></div>
        <table class="kv" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label w25"><?= t('modules.multas.pdf.plate_label') ?></td>
                <td class="w25"><?= htmlspecialchars($multa['veiculo_placa']) ?></td>
                <td class="label w25"><?= t('modules.multas.pdf.brand_model_label') ?></td>
                <td class="w25"><?= htmlspecialchars(trim(($multa['veiculo_marca'] ?? '') . ' ' . ($multa['veiculo_modelo'] ?? ''))) ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Valor a pagar -->
    <div class="valor-destaque">
        <div class="label"><?= t('modules.multas.pdf.value_label') ?></div>
        <div class="valor"><?= currency_format((float) ($multa['valor'] ?? 0)) ?></div>
        <?php if (!empty($multa['valor_desconto_40'])): ?>
        <div style="font-size: 9pt; color: #15803d; margin-top: 4px;">
            <?= t('modules.multas.pdf.discount_40_label') ?>: <strong><?= currency_format((float) $multa['valor_desconto_40']) ?></strong>
        </div>
        <?php endif; ?>
        <?php if (!empty($multa['data_vencimento'])): ?>
        <div style="font-size: 9pt; color: #b91c1c; margin-top: 4px;">
            <?= t('modules.multas.pdf.due_date_label') ?>: <strong><?= format_date($multa['data_vencimento']) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <!-- Texto de notificacao -->
    <div class="texto-doc">
        <?= t('modules.multas.pdf.notification_text', [
            'client' => '<strong>' . htmlspecialchars($multa['cliente_nome'] ?? '-') . '</strong>',
            'plate' => '<strong>' . htmlspecialchars($multa['veiculo_placa'] ?? '-') . '</strong>',
            'value' => '<strong>' . currency_format((float) ($multa['valor'] ?? 0)) . '</strong>',
            'due' => '<strong>' . (!empty($multa['data_vencimento']) ? format_date($multa['data_vencimento']) : '-') . '</strong>',
        ]) ?>
    </div>

    <!-- Local + data -->
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
        <p><?= t('modules.multas.pdf.generated_at', ['datetime' => format_datetime(date('Y-m-d H:i:s'))]) ?></p>
    </div>
</body>
</html>
