<?php
require_once __DIR__ . '/includes/functions.php';

if ($manutencaoAtiva) { include __DIR__ . '/includes/manutencao.php'; exit; }

$pagina = 'sobre';
$seo = $seoAll[$pagina] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= substr($idioma, 0, 2) ?>">
<head><?php include __DIR__ . '/includes/head.php'; ?></head>
<body>

<?php foreach ($integracoes['body_inicio'] ?? [] as $code) { echo $code['codigo']; } ?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div id="reserva" style="height: 70px"></div>

<main>
    <div class="container p50">
        <h2><?= e(secao('sobre', 'titulo', 'Sobre a empresa')) ?></h2>
        <?php $subtitulo = secao('sobre', 'subtitulo', ''); ?>
        <?php if ($subtitulo): ?><h3><?= e($subtitulo) ?></h3><?php endif; ?>
        <?= secao('sobre', 'texto', '') ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php if ($whatsappFlutuante) include __DIR__ . '/includes/whatsapp-float.php'; ?>
</body>
</html>
