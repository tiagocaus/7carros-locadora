<?php $htmlLocale = locale_info()["code"] ?? "pt-BR"; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, "UTF-8") ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.contratos.print.document') ?> - <?= t('modules.contratos.print.contract_label') ?> <?= htmlspecialchars($contrato['codigo']) ?></title>
    <style>
        /* Margens top/bottom do corpo: PdfHelper::DOCUMENTO_* no ContratosController (orig_tMargin mPDF) */
        @page {
            margin-header: 5mm;
            margin-footer: 5mm;
        }
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
        <?php include __DIR__ . '/_partials/_css_documento.php'; ?>
        <?php include __DIR__ . '/_partials/_css_assinatura.php'; ?>
    </style>
</head>
<body>
    <?php include __DIR__ . '/_partials/_documento_content.php'; ?>
</body>
</html>
