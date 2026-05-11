<!DOCTYPE html>
<html lang="<?= current_locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($documento['titulo'] ?? t('modules.multas.pdf.document_title')) ?></title>
    <style>
        /* Margens top/bottom do corpo: PdfHelper::DOCUMENTO_* no MultasController */
        @page {
            margin-header: 5mm;
            margin-footer: 5mm;
        }
        <?php include __DIR__ . '/_partials/_css_base.php'; ?>
        .documento-conteudo { font-size: 10pt; line-height: 1.6; padding: 6px 0; }
        .documento-conteudo p { margin-bottom: 8px; }
        .documento-titulo { font-size: 14pt; font-weight: bold; text-align: center; margin: 12px 0; }
        .page-number { text-align: center; font-size: 8pt; color: #999; }
    </style>
</head>
<body>
    <?php if (!empty($documento['titulo'])): ?>
    <div class="documento-titulo"><?= htmlspecialchars($documento['titulo']) ?></div>
    <?php endif; ?>

    <div class="documento-conteudo">
        <?= $documento['texto'] ?? '' ?>
    </div>

    <div class="local-data" style="margin-top: 30px;">
        <?php $_fmt = new IntlDateFormatter(current_locale(), IntlDateFormatter::LONG, IntlDateFormatter::NONE); ?>
        <?= htmlspecialchars(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? '')) ?>, <?= $_fmt->format(new DateTime()) ?>
    </div>

    <div class="assinatura">
        <div class="linha">
            <div class="nome"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora') ?></div>
            <div class="doc">CNPJ: <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
        </div>
    </div>
</body>
</html>
