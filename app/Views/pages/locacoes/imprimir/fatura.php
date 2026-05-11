<?php $htmlLocale = locale_info()["code"] ?? "pt-BR"; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, "UTF-8") ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.locacoes.print.invoice') ?> - <?= t('modules.locacoes.print.rental_label') ?> <?= htmlspecialchars($locacao['codigo']) ?></title>
    <style>
        @page {
            margin-bottom: 45mm;
            margin-footer: 5mm;
        }
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
        <?php include __DIR__ . '/_partials/_css_fatura.php'; ?>
        <?php include __DIR__ . '/_partials/_css_assinatura.php'; ?>
    </style>
</head>
<body>
    <?php
        $_faturaStandalone = true;
        include __DIR__ . '/_partials/_fatura_content.php';
    ?>
</body>
</html>
