<?php
/**
 * Footer do site público — fiel ao modelo temp/html/*.
 * Variáveis esperadas: $config, $links, $conteudosGlobal.
 */
$footerEmpresa = $conteudosGlobal['footer_empresa'] ?? '';

$iconesFa4 = [
    'whatsapp'  => 'fa-whatsapp',
    'instagram' => 'fa-instagram',
    'facebook'  => 'fa-facebook',
    'twitter'   => 'fa-twitter',
    'youtube'   => 'fa-youtube',
    'linkedin'  => 'fa-linkedin',
    'tiktok'    => 'fa-music', // FA4 não tem tiktok
];
?>
<!-- FOOTER -->
<footer>
    <div class="bg-2 p20"></div>

    <div class="bg-2b">
        <div class="container">
            <div class="row">
                <!-- Menu -->
                <div class="col-sm-4">
                    <div class="row footer-menu">
                        <div class="col-sm-5"><a href="<?= langUrl('sobre.php') ?>" data-track="footer_sobre"><?= t('nav.sobre') ?></a></div>
                        <div class="col-sm-5"><a href="<?= langUrl('veiculos.php') ?>" data-track="footer_veiculos"><?= t('nav.veiculos') ?></a></div>
                        <div class="col-sm-5"><a href="<?= langUrl('contato.php') ?>" data-track="footer_contato"><?= t('nav.contato') ?></a></div>
                        <div class="col-sm-5"><a href="<?= langUrl('painel.php') ?>" data-track="footer_painel_cliente"><?= t('nav.painel_cliente') ?></a></div>
                    </div>
                </div>

                <!-- Dados da empresa (editável via "Website > Conteúdos" > Global > footer_empresa) -->
                <div class="col-sm-4 text-center footer-centro text-white">
                    <?= $footerEmpresa ?>
                </div>

                <!-- Redes sociais (vêm de site_links) -->
                <div class="col-sm-4 text-right text-white">
                    <?php foreach ($links ?? [] as $link): ?>
                        <?php if (empty($link['url']) || (int) ($link['ativo'] ?? 1) !== 1) continue; ?>
                        <?php $icone = $iconesFa4[$link['tipo']] ?? 'fa-link'; ?>
                        <a href="<?= e($link['url']) ?>" title="<?= e(ucfirst($link['tipo'])) ?>" target="_blank" data-track="social_<?= e($link['tipo']) ?>">
                            <i class="fa <?= $icone ?> fa-2x" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-2 footer-copy">
        <div class="container">
            <span>&copy; <?= e(strtoupper($config['nome_empresa'])) ?>, <?= date('Y') ?> <?= t('footer.direitos') ?>.</span>
            <a href="https://www.7carros.com.br" rel="nofollow" target="_blank">7Carros.com</a>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
<script src="assets/js/chosen-select.min.js?v=<?= e($config['deploy'] ?? '1') ?>"></script>

<!-- Dados das filiais (horarios, excecoes, feriados) injetados do backend -->
<script>
    window.FILIAIS_DATA = <?= json_encode(
        array_column($dados['filiais'] ?? [], null, 'id'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    window.FORMAS_PAGAMENTO_SITE = <?= json_encode(
        $dados['formas_pagamento_site'] ?? [],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    window.PAGAMENTO_ANTECIPADO_SITE = <?= !empty($dados['pagamento_antecipado']) ? 'true' : 'false' ?>;
    window.SEGURO_CARRO_OBRIGATORIO = <?= $seguroCarroObrigatorio ? 'true' : 'false' ?>;
    window.SEGURO_TERCEIROS_OBRIGATORIO = <?= $seguroTerceirosObrigatorio ? 'true' : 'false' ?>;
    window.I18N_WEBSITE = {
        diaria: <?= json_encode(t('reserva.diaria_sufixo'), JSON_UNESCAPED_UNICODE) ?>,
        plano_km_livre: <?= json_encode(t('reserva.plano_km_livre'), JSON_UNESCAPED_UNICODE) ?>,
        plano_km_controlado: <?= json_encode(t('reserva.plano_km_controlado'), JSON_UNESCAPED_UNICODE) ?>,
        plano_km_pago: <?= json_encode(t('reserva.plano_km_pago'), JSON_UNESCAPED_UNICODE) ?>,
        btn_selecione_plano: <?= json_encode(t('reserva.btn_selecione_plano'), JSON_UNESCAPED_UNICODE) ?>,
        btn_esgotado: <?= json_encode(t('reserva.btn_esgotado'), JSON_UNESCAPED_UNICODE) ?>,
        btn_selecionar: <?= json_encode(t('reserva.btn_selecionar'), JSON_UNESCAPED_UNICODE) ?>,
        seguro_veiculo: <?= json_encode(t('reserva.seguro_veiculo'), JSON_UNESCAPED_UNICODE) ?>,
        seguro_terceiros: <?= json_encode(t('reserva.seguro_terceiros'), JSON_UNESCAPED_UNICODE) ?>,
        obrigatorio: <?= json_encode(t('reserva.obrigatorio'), JSON_UNESCAPED_UNICODE) ?>,
        adicionar: <?= json_encode(t('reserva.adicionar'), JSON_UNESCAPED_UNICODE) ?>,
        gratis: <?= json_encode(t('reserva.gratis'), JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<script src="assets/js/custom.min.js?v=<?= e($config['deploy'] ?? '1') ?>"></script>
<!-- Busca de CEP (ViaCEP / zippopotam) - autofill de endereco no pre-cadastro -->
<script src="assets/js/cep.min.js?v=<?= e($config['deploy'] ?? '1') ?>"></script>

<!-- Integrações body_fim -->
<?php foreach ($integracoes['body_fim'] ?? [] as $code): ?>
<?= $code['codigo'] ?>
<?php endforeach; ?>
