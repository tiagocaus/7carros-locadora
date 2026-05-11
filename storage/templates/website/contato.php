<?php
require_once __DIR__ . '/includes/functions.php';

if ($manutencaoAtiva) { include __DIR__ . '/includes/manutencao.php'; exit; }

$pagina = 'contato';
$seo = $seoAll[$pagina] ?? [];
$filiais = $dados['filiais'] ?? [];
$empresa = $dados['empresa'] ?? [];
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
        <h2 class="titulo-1 bold verde"><?= e(secao('contato', 'titulo', 'Formulário de contato')) ?></h2>

        <?php $texto = secao('contato', 'texto', ''); ?>
        <?php if ($texto): ?>
        <div class="row pb-4"><div class="col-12"><?= $texto ?></div></div>
        <?php endif; ?>

        <div class="row pb-4">
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-md-7">
                        <div id="contato-sucesso" class="alert alert-success" role="alert" style="display: none;">
                            <strong>Sucesso!</strong> Sua mensagem foi enviada com sucesso. Entraremos em contato o mais breve possível.
                        </div>

                        <form id="contato-form" action="<?= e($config['api_url']) ?>/api/public/contato" method="POST">
                            <input type="hidden" name="chave" value="<?= e($config['chave']) ?>">
                            <div class="form-group">
                                <label for="nome">Nome</label>
                                <input name="nome" type="text" class="form-control" id="nome" required>
                            </div>
                            <div class="form-group">
                                <label for="email_contato">Email</label>
                                <input name="email" type="email" class="form-control" id="email_contato" required>
                            </div>
                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input name="telefone" type="text" class="form-control" id="telefone" placeholder="(DDD) 9 1234-5678" required>
                            </div>
                            <div class="form-group">
                                <label for="mensagem">Mensagem</label>
                                <textarea name="mensagem" class="form-control" id="mensagem" rows="3" required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" data-track="contato_enviar">Enviar</button>
                        </form>
                    </div>

                    <div class="col-md-5">
                        <ul class="contato-detalhes">
                            <?php foreach ($filiais as $f): ?>
                            <li>
                                <i class="fa fa-map-marker fa-lg" aria-hidden="true"></i>
                                <span>
                                    <strong><?= e($f['nome'] ?? '') ?></strong><br>
                                    <?= e(trim(($f['rua'] ?? '') . (!empty($f['num']) ? ', ' . $f['num'] : ''))) ?><br>
                                    <?= e(trim(($f['bairro'] ?? '') . ' - ' . ($f['cidade'] ?? '') . ' - ' . ($f['estado'] ?? ''))) ?>
                                    <?php if (!empty($f['cep'])): ?><br><?= e($f['cep']) ?><?php endif; ?>
                                </span>
                            </li>
                            <?php if (!empty($f['tel_fixo']) || !empty($f['celular'])): ?>
                            <li>
                                <i class="fa fa-phone fa-lg" aria-hidden="true"></i>
                                <?= e($f['celular'] ?? $f['tel_fixo'] ?? '') ?>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($f['email'])): ?>
                            <li>
                                <i class="fa fa-envelope fa-lg" aria-hidden="true"></i>
                                <?= e($f['email']) ?>
                            </li>
                            <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (empty($filiais)): ?>
                            <li>
                                <i class="fa fa-link fa-lg" aria-hidden="true"></i>
                                www.<?= e($config['dominio']) ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php if ($whatsappFlutuante) include __DIR__ . '/includes/whatsapp-float.php'; ?>
</body>
</html>
