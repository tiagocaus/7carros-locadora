<?php
/**
 * Pagina de manutencao
 * Variaveis esperadas: $config
 */
?>
<!DOCTYPE html>
<html lang="<?= substr($config['idioma_padrao'], 0, 2) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($config['nome_empresa']) ?> - <?= t('manutencao.titulo') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { text-align: center; padding: 40px; max-width: 500px; }
        .logo { max-width: 200px; margin-bottom: 30px; }
        h1 { color: #333; font-size: 1.5rem; margin-bottom: 15px; }
        p { color: #666; line-height: 1.6; }
        .icon { font-size: 3rem; color: #f59e0b; margin-bottom: 20px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php if (!empty($config['logo_url'])): ?>
        <img src="<?= e($config['logo_url']) ?>?v=<?= e($config['deploy'] ?? '1') ?>" alt="<?= e($config['nome_empresa']) ?>" class="logo">
        <?php endif; ?>
        <div class="icon"><i class="fas fa-tools"></i></div>
        <h1><?= t('manutencao.titulo') ?></h1>
        <p><?= t('manutencao.mensagem') ?></p>
    </div>
</body>
</html>
