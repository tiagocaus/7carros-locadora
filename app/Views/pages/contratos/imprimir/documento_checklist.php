<?php $htmlLocale = locale_info()["code"] ?? "pt-BR"; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, "UTF-8") ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.contratos.print.document_checklist') ?> - <?= t('modules.contratos.print.contract_label') ?> <?= htmlspecialchars($contrato['codigo']) ?></title>
    <style>
        @page {
            margin-top: <?= \App\Helpers\PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM ?>mm;
            margin-bottom: <?= \App\Helpers\PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM ?>mm;
            margin-header: 5mm;
            margin-footer: 5mm;
        }
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
        <?php include __DIR__ . '/_partials/_css_documento.php'; ?>
        <?php include __DIR__ . '/_partials/_css_checklist.php'; ?>
        <?php include __DIR__ . '/_partials/_css_assinatura.php'; ?>
    </style>
</head>
<body>
    <htmlpageheader name="documento_header">
        <?php $_docTitulo = t('modules.contratos.pdf.document_title'); include __DIR__ . '/_partials/_header.php'; ?>
    </htmlpageheader>
    <htmlpagefooter name="assinatura">
        <?php include __DIR__ . '/_partials/_footer_assinatura.php'; ?>
    </htmlpagefooter>
    <sethtmlpagefooter name="assinatura" value="on" show-this-page="1" />
    <sethtmlpageheader name="documento_header" value="on" show-this-page="1" />
    <?php $_pdfFooterFixo = true; ?>

    <?php include __DIR__ . '/_partials/_documento_content.php'; ?>
    <sethtmlpageheader name="documento_header" value="off" />
    <pagebreak margin-top="5mm" margin-bottom="45mm" margin-footer="5mm" />
    <?php $_checklistShowClienteData = false; include __DIR__ . '/_partials/_checklist_content.php'; ?>
</body>
</html>
