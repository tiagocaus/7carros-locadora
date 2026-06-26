<!DOCTYPE html>
<?php $localeInfo = locale_info() ?? ['code' => 'pt-BR']; ?>
<html lang="<?= htmlspecialchars($localeInfo['code'] ?? 'pt-BR') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? t('modules.assinatura.generic_error_title')) ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="card p-8 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-6">
                <i class="fas fa-exclamation-triangle text-4xl text-red-600"></i>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-3">
                <?= htmlspecialchars($titulo ?? t('modules.assinatura.generic_error_title')) ?>
            </h1>

            <p class="text-gray-600 mb-6">
                <?= htmlspecialchars($mensagem ?? t('modules.assinatura.generic_error_message')) ?>
            </p>

            <div class="text-sm text-gray-500">
                <p><?= htmlspecialchars(t('modules.assinatura.generic_error_help')) ?></p>
            </div>
        </div>
    </div>
</body>
</html>
