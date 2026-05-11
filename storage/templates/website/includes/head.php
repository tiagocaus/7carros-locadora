<?php
/**
 * <head> compartilhado — SEO, CSS, integracoes
 * Variaveis esperadas: $config, $seo, $integracoes, $idioma, $pagina
 */
$pageTitle = !empty($seo['meta_titulo']) ? e($seo['meta_titulo']) : e($config['nome_empresa']);
$pageDesc = !empty($seo['meta_descricao']) ? e($seo['meta_descricao']) : '';
$pageKeywords = !empty($seo['meta_keywords']) ? e($seo['meta_keywords']) : '';
$ogTitle = !empty($seo['og_titulo']) ? e($seo['og_titulo']) : $pageTitle;
$ogDesc = !empty($seo['og_descricao']) ? e($seo['og_descricao']) : $pageDesc;
$ogImage = !empty($seo['og_imagem']) ? e($seo['og_imagem']) : '';
$canonical = 'https://' . e($config['dominio']) . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<?php if ($pageDesc): ?><meta name="description" content="<?= $pageDesc ?>"><?php endif; ?>
<?php if ($pageKeywords): ?><meta name="keywords" content="<?= $pageKeywords ?>"><?php endif; ?>
<link rel="canonical" href="<?= $canonical ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= $ogTitle ?>">
<?php if ($ogDesc): ?><meta property="og:description" content="<?= $ogDesc ?>"><?php endif; ?>
<?php if ($ogImage): ?><meta property="og:image" content="<?= $ogImage ?>"><?php endif; ?>
<meta property="og:url" content="<?= $canonical ?>">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $ogTitle ?>">
<?php if ($ogDesc): ?><meta name="twitter:description" content="<?= $ogDesc ?>"><?php endif; ?>
<?php if ($ogImage): ?><meta name="twitter:image" content="<?= $ogImage ?>"><?php endif; ?>

<!-- Hreflang -->
<?php foreach ($config['idiomas_ativos'] as $lang): ?>
<link rel="alternate" hreflang="<?= substr($lang, 0, 2) ?>" href="https://<?= e($config['dominio']) ?><?= $lang === $config['idioma_padrao'] ? '/' : '/' . substr($lang, 0, 2) . '/' ?>">
<?php endforeach; ?>

<!-- Favicon -->
<?php if (!empty($config['favicon_url'])): ?>
<link rel="icon" href="<?= e($config['favicon_url']) ?>?v=<?= e($config['deploy'] ?? '1') ?>">
<?php endif; ?>

<!-- Fonte -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= !empty($config['fonte_url']) ? e($config['fonte_url']) : 'https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap' ?>">

<!-- CSS libs -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css">

<!-- Chosen Select (selects com busca) -->
<link rel="stylesheet" href="assets/css/chosen-select.min.css?v=<?= e($config['deploy'] ?? '1') ?>">

<!-- CSS do tenant (cores + customizações) — versionado pra cache-busting por deploy -->
<link rel="stylesheet" href="assets/css/style.min.css?v=<?= e($config['deploy'] ?? '1') ?>">

<!-- Integracoes head -->
<?php foreach ($integracoes['head'] ?? [] as $code): ?>
<?= $code['codigo'] ?>
<?php endforeach; ?>
