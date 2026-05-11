<?php
require_once __DIR__ . '/includes/functions.php';

if ($manutencaoAtiva) {
    include __DIR__ . '/includes/manutencao.php';
    exit;
}

$pagina = 'inicio';
$seo = $seoAll[$pagina] ?? [];
$filiais = $dados['filiais'] ?? [];
$grupos = $dados['grupos'] ?? [];
?>
<!DOCTYPE html>
<html lang="<?= substr($idioma, 0, 2) ?>">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <?php include __DIR__ . '/includes/structured-data.php'; ?>
</head>
<body>

<?php foreach ($integracoes['body_inicio'] ?? [] as $code) { echo $code['codigo']; } ?>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- FORMULÁRIO DE RESERVA + BANNERS + BARRA INFO -->
<div class="site-hero">
    <?php if ($reservaOnline): ?>
    <div id="reserva">
        <h1 class="p-4 text-center"><?= e(secao('inicio', 'titulo_reserva', 'Faça sua reserva online')) ?></h1>

        <div class="container pb-3">
            <form action="<?= langUrl('reserva.php') ?>" method="GET" name="reserva_top">
                <div class="row">
                    <!-- RETIRADA -->
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="localRetirada" class="text-white">Local de retirada</label>
                            <select class="form-control chosen-select" id="localRetirada" name="localRetirada" required
                                    data-chosen-placeholder="Selecione">
                                <option value="" disabled selected>Selecione</option>
                                <?php foreach ($filiais as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"
                                        data-local="<?= e($f['cidade'] ?? '') ?>, <?= e($f['estado'] ?? '') ?>"
                                        data-currency="<?= e($f['currency_code'] ?? 'BRL') ?>"
                                        data-locale="<?= e($f['locale'] ?? 'pt_BR') ?>"
                                        data-simbolo="<?= e($f['simbolo_moeda'] ?? 'R$') ?>">
                                    <?= e($f['label'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="text-white">Previsão de saída</label>
                            <div class="input-datetime saida">
                                <input type="date" id="dataSaida" name="dataSaida" required disabled>
                                <select id="horaSaida" name="horaSaida" disabled>
                                    <option value="" disabled selected>--:--</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- DEVOLUÇÃO -->
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="localDevolucao" class="text-white">Local de devolução</label>
                            <select class="form-control chosen-select" id="localDevolucao" name="localDevolucao" required
                                    data-chosen-placeholder="Selecione">
                                <option value="" disabled selected>Selecione</option>
                                <?php foreach ($filiais as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"
                                        data-local="<?= e($f['cidade'] ?? '') ?>, <?= e($f['estado'] ?? '') ?>">
                                    <?= e($f['label'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="text-white">Previsão de chegada</label>
                            <div class="input-datetime chegada">
                                <input type="date" id="dataPrevista" name="dataPrevista" required disabled>
                                <select id="horaDevolucao" name="horaDevolucao" disabled>
                                    <option value="" disabled selected>--:--</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-warning px-5" data-track="home_buscar_reserva">Continuar</button>
                        <input type="hidden" name="form-topo" value="1">
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- CAROUSEL DE BANNERS (sempre visível — mostra placeholder se vazio) -->
    <?php if (!empty($banners)): ?>
    <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
        <div class="carousel-inner">
            <?php foreach ($banners as $i => $banner): ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <?php if (!empty($banner['link_url'])): ?>
                <a href="<?= e($banner['link_url']) ?>" target="<?= e($banner['link_target'] ?? '_self') ?>">
                <?php endif; ?>
                <img class="d-block w-100" src="<?= e($banner['foto_url'] ?? $banner['foto'] ?? '') ?>" alt="<?= e($banner['alt_text'] ?? $banner['titulo'] ?? 'Banner ' . ($i + 1)) ?>" style="max-height: 500px;">
                <?php if (!empty($banner['link_url'])): ?></a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($banners) > 1): ?>
        <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Next</span>
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="banner-placeholder" style="background-color: var(--cor-3);">
        <h5>Nenhum banner publicado</h5>
        <p>Envie seus banners em <strong>Website &rsaquo; Banners</strong>.</p>
    </div>
    <?php endif; ?>

    <!-- BARRA INFO -->
    <div class="container-fluid barra-infor">
        <div class="container">
            <div class="row">
                <div class="col-6 col-sm-3 text-center text-white">
                    <i class="fa fa-phone fa-2x"></i><br>
                    <h3><?= e(secao('global', 'barra_info_atendimento_titulo', 'Atendimento')) ?></h3><br>
                    <?= secao('global', 'barra_info_atendimento_texto', '') ?>
                </div>
                <div class="col-6 col-sm-3 text-center text-white">
                    <i class="fa fa-whatsapp fa-2x"></i><br>
                    <h3><?= e(secao('global', 'barra_info_whatsapp_titulo', 'WhatsApp')) ?></h3><br>
                    <?= secao('global', 'barra_info_whatsapp_texto', '') ?>
                </div>
                <div class="col-6 col-sm-3 text-center text-white">
                    <i class="fa fa-life-ring fa-2x"></i><br>
                    <h3><?= e(secao('global', 'barra_info_assistencia_titulo', 'Assistência 24h')) ?></h3><br>
                    <?= secao('global', 'barra_info_assistencia_texto', '') ?>
                </div>
                <div class="col-6 col-sm-3 text-center text-white">
                    <i class="fa fa-clock-o fa-2x"></i><br>
                    <h3><?= e(secao('global', 'barra_info_horario_titulo', 'Horário')) ?></h3><br>
                    <?= secao('global', 'barra_info_horario_texto', '') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<main>

    <!-- POR QUE NOS ESCOLHER -->
    <div class="container-fluid p50" style="background-color: #f5f5f5;">
        <div class="container">
            <h2 class="titulo-1 bold"><?= e(secao('inicio', 'por_que_titulo', 'Por que nos escolher?')) ?></h2>
            <div class="row">
                <?php for ($n = 1; $n <= 4; $n++): ?>
                    <?php
                    $tituloCard = secao('inicio', "por_que_{$n}_titulo", '');
                    $textoCard  = secao('inicio', "por_que_{$n}_texto", '');
                    if (!$tituloCard && !$textoCard) continue;
                    ?>
                    <div class="col-sm-6 mb-4">
                        <?php if ($tituloCard): ?><h4><strong><?= e($tituloCard) ?></strong></h4><?php endif; ?>
                        <?php if ($textoCard): ?><p><?= e($textoCard) ?></p><?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- GRUPOS DE VEÍCULOS -->
    <div class="container-fluid bg-2b">
        <div class="container">
            <div class="row justify-content-md-center">
                <div class="col-12 text-center">
                    <h2 class="titulo-2 bold amarelo"><?= e(secao('inicio', 'grupos_titulo', 'Grupos de veículos')) ?></h2>
                </div>
                <div class="col-12 px-0 px-sm-3">
                    <div id="vehicles">
                        <div class="inside">
                            <?php if (!empty($grupos)): ?>
                            <ul class="vehicles-list">
                                <?php foreach ($grupos as $grupo): ?>
                                <li class="item">
                                    <?php if (!empty($grupo['foto_url'])): ?>
                                    <div class="thumb">
                                        <img src="<?= e($grupo['foto_url']) ?>" alt="<?= e($grupo['nome']) ?>">
                                    </div>
                                    <?php endif; ?>
                                    <div class="info">
                                        <h2><?= e($grupo['nome']) ?></h2>
                                        <?php if (!empty($grupo['descricao'])): ?>
                                        <p><?= e($grupo['descricao']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <div class="text-center text-white py-5">
                                <p style="font-size: 20px; opacity: .85;">Nenhum grupo de veículos cadastrado ainda.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NOSSOS DIFERENCIAIS -->
    <?php
    $temDiferencial = false;
    for ($n = 1; $n <= 4; $n++) {
        if (secao('inicio', "diferencial_esq_{$n}", '') || secao('inicio', "diferencial_dir_{$n}", '')) {
            $temDiferencial = true;
            break;
        }
    }
    ?>
    <?php if ($temDiferencial): ?>
    <div class="container-fluid p50">
        <div class="container">
            <h2 class="titulo-1 bold verde"><?= e(secao('inicio', 'diferenciais_titulo', 'Nossos diferenciais')) ?></h2>
            <div class="row">
                <?php foreach (['esq', 'dir'] as $coluna): ?>
                <div class="col-sm-6">
                    <ul style="list-style: none; padding: 0;">
                        <?php for ($n = 1; $n <= 4; $n++): ?>
                            <?php $texto = secao('inicio', "diferencial_{$coluna}_{$n}", ''); ?>
                            <?php if (!$texto) continue; ?>
                            <li style="margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px;">
                                <i class="fa fa-check-circle fa-lg" style="color: var(--cor-5); margin-top: 3px;"></i>
                                <span><?= e($texto) ?></span>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php if ($whatsappFlutuante) include __DIR__ . '/includes/whatsapp-float.php'; ?>

</body>
</html>
