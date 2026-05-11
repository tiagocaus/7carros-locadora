<?php
require_once __DIR__ . '/includes/functions.php';

if ($manutencaoAtiva) { include __DIR__ . '/includes/manutencao.php'; exit; }

$pagina = 'veiculos';
$seo = $seoAll[$pagina] ?? [];
$grupos = $dados['grupos'] ?? [];
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
        <h2 class="titulo-1 bold verde"><?= e(secao('veiculos', 'titulo', 'Nossos grupos de veículos')) ?></h2>

        <?php $texto = secao('veiculos', 'texto', ''); ?>
        <?php if ($texto): ?>
        <div class="row pb-4"><div class="col-12"><?= $texto ?></div></div>
        <?php endif; ?>

        <?php if (!empty($grupos)): ?>
        <div class="row pb-4 justify-content-md-center">
            <?php foreach ($grupos as $grupo): ?>
            <div class="col-sm-4 text-center">
                <?php if (!empty($grupo['foto_url'])): ?>
                <img src="<?= e($grupo['foto_url']) ?>" class="img-fluid" alt="<?= e($grupo['nome']) ?>" style="width: 70%;">
                <?php endif; ?>
                <h3><strong><?= e($grupo['nome']) ?></strong></h3>
                <?php if (!empty($grupo['descricao'])): ?>
                <h5><?= e($grupo['descricao']) ?></h5>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="row pb-4">
            <div class="col-12 text-center text-muted">
                <p>Em breve, nossa frota completa será exibida aqui.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php if ($whatsappFlutuante) include __DIR__ . '/includes/whatsapp-float.php'; ?>
</body>
</html>
