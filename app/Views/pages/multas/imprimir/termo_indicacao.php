<!DOCTYPE html>
<html lang="<?= current_locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.multas.pdf.indication_title') ?> - <?= htmlspecialchars($multa['numero_ait'] ?? '') ?></title>
    <style>
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
    </style>
</head>
<body>
    <?php $_docTitulo = strtoupper(t('modules.multas.pdf.indication_title')); include __DIR__ . '/_partials/_header.php'; ?>

    <!-- Bloco: Dados da Infracao -->
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.fine_data_section') ?></div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell label w25"><?= t('modules.multas.pdf.ait_label') ?></div>
                <div class="grid-cell w25"><?= htmlspecialchars($multa['numero_ait'] ?? '-') ?></div>
                <div class="grid-cell label w25"><?= t('modules.multas.pdf.fine_date_label') ?></div>
                <div class="grid-cell w25"><?= !empty($multa['data_hora']) ? format_datetime($multa['data_hora']) : '-' ?></div>
            </div>
            <div class="grid-row">
                <div class="grid-cell label"><?= t('modules.multas.pdf.location_label') ?></div>
                <div class="grid-cell" colspan="3"><?= htmlspecialchars(($multa['local'] ?? '') . ' - ' . ($multa['cidade'] ?? '') . '/' . ($multa['estado'] ?? '')) ?></div>
            </div>
            <div class="grid-row">
                <div class="grid-cell label"><?= t('modules.multas.pdf.issuing_body_label') ?></div>
                <div class="grid-cell"><?= htmlspecialchars($multa['orgao_autuador'] ?? '-') ?></div>
                <div class="grid-cell label"><?= t('modules.multas.pdf.plate_label') ?></div>
                <div class="grid-cell"><?= htmlspecialchars($multa['veiculo_placa'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <!-- Bloco: Dados do Proprietario -->
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.owner_section') ?></div>
        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell label w25"><?= t('modules.multas.pdf.company_name_label') ?></div>
                <div class="grid-cell w50"><?= htmlspecialchars($empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? '-') ?></div>
                <div class="grid-cell label w25">CNPJ</div>
                <div class="grid-cell"><?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <!-- Bloco: Dados do Condutor (em branco para preencher) -->
    <div class="section">
        <div class="section-title"><?= t('modules.multas.pdf.driver_section') ?></div>
        <table class="data-table">
            <tr>
                <td style="width: 25%; font-weight: bold;"><?= t('modules.multas.pdf.driver_name') ?>:</td>
                <td colspan="3"><div class="linha-preencher"></div></td>
            </tr>
            <tr>
                <td style="font-weight: bold;"><?= t('modules.multas.pdf.driver_cpf') ?>:</td>
                <td style="width: 25%;"><div class="linha-preencher"></div></td>
                <td style="width: 20%; font-weight: bold;"><?= t('modules.multas.pdf.driver_cnh') ?>:</td>
                <td><div class="linha-preencher"></div></td>
            </tr>
            <tr>
                <td style="font-weight: bold;"><?= t('modules.multas.pdf.driver_address') ?>:</td>
                <td colspan="3"><div class="linha-preencher"></div></td>
            </tr>
            <tr>
                <td style="font-weight: bold;"><?= t('modules.multas.pdf.driver_city') ?>:</td>
                <td><div class="linha-preencher"></div></td>
                <td style="font-weight: bold;"><?= t('modules.multas.pdf.driver_phone') ?>:</td>
                <td><div class="linha-preencher"></div></td>
            </tr>
        </table>
    </div>

    <!-- Declaracao -->
    <div class="texto-doc" style="margin-top: 15px;">
        <?= t('modules.multas.pdf.indication_declaration') ?>
    </div>

    <!-- Local + data para preencher -->
    <div style="margin-top: 25px; font-size: 9pt;">
        <?= t('modules.multas.pdf.signature_place_label') ?>: <span class="campo-preencher" style="min-width: 250px;"></span>,
        <?= t('modules.multas.pdf.signature_date_label') ?>: <span class="campo-preencher" style="min-width: 150px;"></span>
    </div>

    <!-- Assinaturas (proprietario, condutor, testemunhas) -->
    <table style="width: 100%; margin-top: 50px;">
        <tr>
            <td style="width: 50%; text-align: center; padding: 0 10px;">
                <div style="border-top: 1px solid #333; padding-top: 4px;">
                    <strong><?= t('modules.multas.pdf.owner_signature') ?></strong>
                </div>
            </td>
            <td style="width: 50%; text-align: center; padding: 0 10px;">
                <div style="border-top: 1px solid #333; padding-top: 4px;">
                    <strong><?= t('modules.multas.pdf.driver_signature') ?></strong>
                </div>
            </td>
        </tr>
        <tr><td colspan="2" style="height: 40px;"></td></tr>
        <tr>
            <td style="text-align: center; padding: 0 10px;">
                <div style="border-top: 1px solid #333; padding-top: 4px; font-size: 9pt;">
                    <strong><?= t('modules.multas.pdf.witness_1') ?></strong>
                </div>
            </td>
            <td style="text-align: center; padding: 0 10px;">
                <div style="border-top: 1px solid #333; padding-top: 4px; font-size: 9pt;">
                    <strong><?= t('modules.multas.pdf.witness_2') ?></strong>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <p><?= t('modules.multas.pdf.indication_footer') ?></p>
        <p><?= t('modules.multas.pdf.generated_at', ['datetime' => format_datetime(date('Y-m-d H:i:s'))]) ?></p>
    </div>
</body>
</html>
