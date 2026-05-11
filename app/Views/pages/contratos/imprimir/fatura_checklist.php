<?php $htmlLocale = locale_info()["code"] ?? "pt-BR"; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, "UTF-8") ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.contratos.print.invoice_checklist') ?> - <?= t('modules.contratos.print.contract_label') ?> <?= htmlspecialchars($contrato['codigo']) ?></title>
    <style>
        @page { margin-bottom: 45mm; margin-footer: 5mm; }
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
        <?php include __DIR__ . '/_partials/_css_fatura.php'; ?>
        <?php include __DIR__ . '/_partials/_css_checklist.php'; ?>
        <?php include __DIR__ . '/_partials/_css_assinatura.php'; ?>
    </style>
</head>
<body>
    <htmlpagefooter name="assinatura">
        <?php include __DIR__ . '/_partials/_footer_assinatura.php'; ?>
    </htmlpagefooter>
    <sethtmlpagefooter name="assinatura" value="on" show-this-page="1" />
    <?php $_pdfFooterFixo = true; ?>

    <?php $_faturaStandalone = false; include __DIR__ . '/_partials/_fatura_content.php'; ?>
    <pagebreak />
    <?php $_checklistShowClienteData = false; include __DIR__ . '/_partials/_checklist_content.php'; ?>
</body>
</html>
