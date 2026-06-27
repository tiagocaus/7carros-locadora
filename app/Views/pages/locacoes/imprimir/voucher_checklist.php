<?php $htmlLocale = locale_info()["code"] ?? "pt-BR"; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, "UTF-8") ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.locacoes.print.voucher_checklist') ?> - <?= t('modules.locacoes.print.reservation_label') ?> <?= htmlspecialchars($locacao['codigo']) ?></title>
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

    <?php
        $_faturaStandalone = false;
        $_faturaDocTitulo = t('modules.locacoes.pdf.voucher_title');
        $_docRegistroLabel = t('modules.locacoes.pdf.reservation_label');
        $_faturaDadosTitulo = t('modules.locacoes.pdf.reservation_data');
        $_faturaTotalRegistroLabel = t('modules.locacoes.pdf.total_reservation_label');
        include __DIR__ . '/_partials/_fatura_content.php';
    ?>
    <pagebreak />
    <?php $_checklistShowClienteData = false; include __DIR__ . '/_partials/_checklist_content.php'; ?>
</body>
</html>
