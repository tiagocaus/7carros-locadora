<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Verificado - <?= htmlspecialchars($contrato['codigo'] ?? '') ?></title>

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
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
                <i class="fas fa-check-circle text-4xl text-green-600"></i>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                Contrato Verificado
            </h1>

            <p class="text-gray-500 text-sm mb-6">
                Este contrato foi registrado e validado pelo sistema 7Carros.com.br
            </p>

            <div class="bg-gray-50 rounded-lg p-5 text-left mb-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Codigo</span>
                        <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($contrato['codigo'] ?? '-') ?></span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="text-sm font-semibold">
                            <?php if (($contrato['status'] ?? '') === 'A'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Ativo</span>
                            <?php elseif (($contrato['status'] ?? '') === 'F'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Finalizado</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Cliente</span>
                        <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($contrato['cliente_nome'] ?? '-') ?></span>
                    </div>

                    <?php if (!empty($veiculo)): ?>
                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Veiculo</span>
                        <span class="text-sm font-semibold text-gray-900">
                            <?= htmlspecialchars($veiculo['veiculo_placa'] ?? '') ?> - <?= htmlspecialchars(($veiculo['veiculo_marca'] ?? '') . ' ' . ($veiculo['veiculo_modelo'] ?? '')) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Periodo</span>
                        <span class="text-sm font-semibold text-gray-900">
                            <?= !empty($contrato['data_ini']) ? format_date($contrato['data_ini']) : '-' ?>
                            a
                            <?php if (($contrato['status'] ?? '') === 'A' && ($contrato['auto_renovacao'] ?? '') === 'auto'): ?>
                                Indeterminado
                            <?php else: ?>
                                <?= !empty($contrato['data_fim']) ? format_date($contrato['data_fim']) : '-' ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Emissao</span>
                        <span class="text-sm font-semibold text-gray-900">
                            <?= !empty($contrato['created_at']) ? format_datetime($contrato['created_at']) : '-' ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if (!empty($empresa)): ?>
            <div class="text-sm text-gray-400 mb-4">
                <p class="font-medium text-gray-600"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? '') ?></p>
            </div>
            <?php endif; ?>

            <div class="text-xs text-gray-400">
                <p>Verificado em <?= format_date(today()) ?> as <?= \App\Helpers\DateHelper::todayForDatabase('H:i') ?></p>
            </div>
        </div>
    </div>
</body>
</html>
