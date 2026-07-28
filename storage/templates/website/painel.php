<?php

require_once __DIR__ . '/includes/portal-session.php';
portalSessionStart();
require_once __DIR__ . '/includes/functions.php';

$portalLogado = !empty($_SESSION['portal_token']) && in_array(
    $_SESSION['portal_perfil'] ?? '',
    ['cliente', 'investidor'],
    true
);
$portalPerfil = (string) ($_SESSION['portal_perfil'] ?? 'cliente');
$portalNome = (string) ($_SESSION['portal_nome'] ?? '');
$portalCsrf = (string) ($_SESSION['portal_csrf'] ?? '');
?>
<!doctype html>
<html lang="<?= e(str_replace('_', '-', $idioma)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(t('portal.title')) ?> — <?= e($config['nome_empresa']) ?></title>
    <?php if ($faviconUrl): ?><link rel="icon" href="<?= e($faviconUrl) ?>?v=<?= e($config['deploy'] ?? '1') ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.min.css?v=<?= e($config['deploy'] ?? '1') ?>">
    <link rel="stylesheet" href="assets/css/portal.min.css?v=<?= e($config['deploy'] ?? '1') ?>">
</head>
<body class="portal-body">
<div id="portalToast" class="portal-toast" role="status" aria-live="polite"></div>

<main id="loginView" class="portal-login" <?= $portalLogado ? 'hidden' : '' ?>>
    <section class="portal-login-brand">
        <div class="portal-login-brand-content">
            <a href="index.php" class="portal-logo">
                <?php if ($logoUrl): ?>
                    <span class="portal-logo-box <?= $logoFundoBranco ? 'white' : '' ?>">
                        <img src="<?= e($logoUrl) ?>?v=<?= e($config['deploy'] ?? '1') ?>" alt="<?= e($config['nome_empresa']) ?>">
                    </span>
                <?php else: ?>
                    <span class="portal-logo-icon"><i class="fa fa-car"></i></span>
                    <strong><?= e($config['nome_empresa']) ?></strong>
                <?php endif; ?>
            </a>
            <div class="portal-brand-message">
                <span class="portal-eyebrow"><?= e(t('portal.exclusive_area')) ?></span>
                <h1><?= e(t('portal.hero_title')) ?></h1>
                <p><?= e(t('portal.hero_text')) ?></p>
            </div>
            <div class="portal-benefits">
                <span><i class="fa fa-check-circle"></i> <?= e(t('portal.secure_access')) ?></span>
                <span><i class="fa fa-check-circle"></i> <?= e(t('portal.updated_data')) ?></span>
            </div>
        </div>
    </section>

    <section class="portal-login-panel">
        <div class="portal-login-card">
            <a href="index.php" class="portal-mobile-logo">
                <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="<?= e($config['nome_empresa']) ?>"><?php else: ?><strong><?= e($config['nome_empresa']) ?></strong><?php endif; ?>
            </a>
            <span class="portal-eyebrow"><?= e(t('portal.title')) ?></span>
            <h2><?= e(t('portal.welcome')) ?></h2>
            <p><?= e(t('portal.choose_profile')) ?></p>

            <div class="portal-role-switch" role="tablist">
                <button type="button" class="portal-role active" data-role="cliente" aria-selected="true">
                    <i class="fa fa-user"></i> <?= e(t('portal.client')) ?>
                </button>
                <button type="button" class="portal-role" data-role="investidor" aria-selected="false">
                    <i class="fa fa-line-chart"></i> <?= e(t('portal.investor')) ?>
                </button>
            </div>

            <form id="loginForm" novalidate>
                <label class="portal-field">
                    <span><?= e(t('portal.user_label')) ?></span>
                    <span class="portal-control"><i class="fa fa-user-o"></i><input id="loginUser" name="usuario" autocomplete="username" required></span>
                </label>
                <label class="portal-field">
                    <span class="portal-label-row"><span><?= e(t('portal.password')) ?></span><button type="button" id="forgotPassword"><?= e(t('portal.forgot')) ?></button></span>
                    <span class="portal-control">
                        <i class="fa fa-lock"></i>
                        <input id="loginPassword" name="senha" type="password" autocomplete="current-password" required>
                        <button type="button" id="togglePassword" class="portal-password-toggle" aria-label="<?= e(t('portal.show_password')) ?>"><i class="fa fa-eye"></i></button>
                    </span>
                </label>
                <button type="submit" class="portal-primary"><span><?= e(t('portal.sign_in')) ?></span><i class="fa fa-arrow-right"></i></button>
            </form>
            <div class="portal-login-foot"><a href="index.php"><i class="fa fa-arrow-left"></i> <?= e(t('portal.back_site')) ?></a><span><i class="fa fa-lock"></i> <?= e(t('portal.protected')) ?></span></div>
        </div>
    </section>
</main>

<div id="appView" class="portal-app" <?= $portalLogado ? '' : 'hidden' ?>>
    <div id="sidebarBackdrop" class="portal-sidebar-backdrop"></div>
    <aside id="sidebar" class="portal-sidebar">
        <a href="index.php" class="portal-sidebar-logo">
            <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="<?= e($config['nome_empresa']) ?>"><?php else: ?><i class="fa fa-car"></i><strong><?= e($config['nome_empresa']) ?></strong><?php endif; ?>
        </a>
        <button id="closeSidebar" class="portal-close-menu" aria-label="<?= e(t('portal.close_menu')) ?>"><i class="fa fa-times"></i></button>
        <nav id="portalNav" class="portal-nav"></nav>
        <a class="portal-help" href="contato.php"><i class="fa fa-life-ring"></i><span><strong><?= e(t('portal.need_help')) ?></strong><small><?= e(t('portal.contact_company')) ?></small></span></a>
    </aside>
    <section class="portal-main">
        <header class="portal-topbar">
            <button id="openSidebar" class="portal-menu-button" aria-label="<?= e(t('portal.open_menu')) ?>"><i class="fa fa-bars"></i></button>
            <div><small id="areaLabel"><?= e(t('portal.title')) ?></small><strong id="pageTitle"><?= e(t('portal.overview')) ?></strong></div>
            <div class="portal-account">
                <span id="profileInitials" class="portal-avatar">--</span>
                <span><strong id="profileName"><?= e($portalNome) ?></strong><small id="profileRole"><?= e($portalPerfil) ?></small></span>
                <button id="logoutButton" title="<?= e(t('portal.logout')) ?>"><i class="fa fa-sign-out"></i></button>
            </div>
        </header>
        <div id="portalContent" class="portal-content">
            <div class="portal-loading"><i class="fa fa-circle-o-notch fa-spin"></i><span><?= e(t('portal.loading')) ?></span></div>
        </div>
    </section>
</div>

<div id="forgotModal" class="portal-modal" hidden>
    <button class="portal-modal-backdrop" data-close-modal aria-label="<?= e(t('portal.close')) ?>"></button>
    <section class="portal-modal-card" role="dialog" aria-modal="true">
        <button class="portal-modal-close" data-close-modal><i class="fa fa-times"></i></button>
        <span class="portal-modal-icon"><i class="fa fa-envelope-o"></i></span>
        <h2><?= e(t('portal.recover_title')) ?></h2>
        <p><?= e(t('portal.recover_text')) ?></p>
        <button type="button" id="sendReset" class="portal-primary"><?= e(t('portal.send_instructions')) ?></button>
    </section>
</div>

<script>
window.PORTAL_BOOT = <?= json_encode([
    'logged' => $portalLogado,
    'profile' => $portalPerfil,
    'name' => $portalNome,
    'csrf' => $portalCsrf,
    'currency' => $dados['moeda_simbolo'] ?? 'R$',
    'reservation_url' => 'reserva.php',
    'referral_base' => 'reserva.php?indicacao=',
    'i18n' => $traducoes['portal'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/portal.min.js?v=<?= e($config['deploy'] ?? '1') ?>"></script>
</body>
</html>
