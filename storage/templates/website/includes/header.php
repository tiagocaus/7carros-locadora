<?php
/**
 * Navbar do site público — fiel ao modelo temp/html/*.
 * Variáveis esperadas: $config, $idioma.
 */
// $logoUrl, $logoFundoBranco, $logoAlinhamento vêm do functions.php (runtime)
$pagAtual = basename($_SERVER['SCRIPT_NAME'], '.php');
$idiomasAtivos = $config['idiomas_ativos'] ?? [$config['idioma_padrao']];
$langNames = [
    'pt_BR' => 'Português (BR)',
    'pt_PT' => 'Português (PT)',
    'en_US' => 'English',
    'es_ES' => 'Español',
    'it_IT' => 'Italiano',
];
/**
 * Badge (2 letras) do seletor. Usa a parte depois do underscore (country code),
 * que diferencia pt_BR (BR) de pt_PT (PT). Fallback: prefixo do idioma.
 */
$langBadge = function (string $code): string {
    $parts = explode('_', $code);
    return strtoupper($parts[1] ?? $parts[0] ?? $code);
};
?>
<header>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-2 p15">
        <div class="container navbar-topbar">
            <a class="navbar-brand text-center logo-align-<?= e($logoAlinhamento ?: 'centro') ?> <?= $logoFundoBranco ? 'logo-bg-white' : '' ?>" href="<?= langUrl('index.php') ?>">
                <?php if ($logoUrl): ?>
                <img class="img-responsive logo" src="<?= e($logoUrl) ?>?v=<?= e($config['deploy'] ?? '1') ?>" alt="<?= e($config['nome_empresa']) ?>">
                <?php else: ?>
                <span class="h4 text-white mb-0"><?= e($config['nome_empresa']) ?></span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler ml-auto" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir ou fechar menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item <?= $pagAtual === 'index' ? 'active' : '' ?>">
                        <a class="nav-link text-white" href="<?= langUrl('index.php') ?>" data-track="nav_inicio"><?= t('nav.inicio') ?></a>
                    </li>
                    <li class="nav-item <?= $pagAtual === 'sobre' ? 'active' : '' ?>">
                        <a class="nav-link text-white" href="<?= langUrl('sobre.php') ?>" data-track="nav_sobre"><?= t('nav.sobre') ?></a>
                    </li>
                    <li class="nav-item <?= $pagAtual === 'veiculos' ? 'active' : '' ?>">
                        <a class="nav-link text-white" href="<?= langUrl('veiculos.php') ?>" data-track="nav_veiculos"><?= t('nav.veiculos') ?></a>
                    </li>
                    <li class="nav-item <?= $pagAtual === 'contato' ? 'active' : '' ?>">
                        <a class="nav-link text-white" href="<?= langUrl('contato.php') ?>" data-track="nav_contato"><?= t('nav.contato') ?></a>
                    </li>
                    <li class="nav-item">
                        <!-- TODO: painel do cliente — provavelmente virará iframe apontando para locadoranovo.7carros.com/painelcliente -->
                        <a class="nav-link text-white" href="#" data-track="nav_painel_cliente"><?= t('nav.painel_cliente') ?></a>
                    </li>

                    <?php if (count($idiomasAtivos) > 1): ?>
                    <!-- Seletor de idioma (grava cookie via JS e recarrega sem query string) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link text-white dropdown-toggle" href="#" id="navLangDropdown" role="button"
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-globe" aria-hidden="true"></i>
                            <?= $langBadge($idioma) ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navLangDropdown">
                            <?php foreach ($idiomasAtivos as $l): ?>
                            <a class="dropdown-item <?= $l === $idioma ? 'active' : '' ?>"
                               href="#" data-lang="<?= e($l) ?>" onclick="return setSiteLang(this.dataset.lang)">
                                <?= e($langNames[$l] ?? $l) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="navbar-slide-backdrop" id="navbarSlideBackdrop" aria-hidden="true"></div>
    </nav>
</header>
