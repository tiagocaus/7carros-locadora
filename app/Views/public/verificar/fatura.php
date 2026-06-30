<?php
$codigo = $lancamento['codigo'] ?? '';
$sequencia = $lancamento['sequencia'] ?? '';
$pago = ($lancamento['pago'] ?? 'N') === 'S';
$dataVenci = !empty($lancamento['data_venci']) ? format_date($lancamento['data_venci']) : '-';
$dataEmissao = !empty($lancamento['data_criada']) ? format_date($lancamento['data_criada']) : '-';
$valorBR = 'R$ ' . number_format((float) ($lancamento['valor_total'] ?? 0), 2, ',', '.');

if ($pago) {
    $statusLabel = 'Paga';
    $statusBg = 'bg-green-100';
    $statusText = 'text-green-700';
} else {
    $hoje = strtotime(today());
    $venci = !empty($lancamento['data_venci']) ? strtotime($lancamento['data_venci']) : 0;
    if ($venci && $venci < $hoje) {
        $statusLabel = 'Vencida';
        $statusBg = 'bg-red-100';
        $statusText = 'text-red-700';
    } else {
        $statusLabel = 'Em aberto';
        $statusBg = 'bg-amber-100';
        $statusText = 'text-amber-700';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura Verificada - <?= htmlspecialchars($codigo ?: '#' . ($lancamento['id'] ?? '')) ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                Fatura Verificada
            </h1>

            <p class="text-gray-500 text-sm mb-6">
                Esta fatura foi registrada e validada pelo sistema 7Carros.com.br
            </p>

            <div class="bg-gray-50 rounded-lg p-5 text-left mb-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Numero</span>
                        <span class="text-sm font-semibold text-gray-900">
                            <?= !empty($sequencia) ? htmlspecialchars((string) $sequencia) : '-' ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $statusBg ?> <?= $statusText ?>">
                            <?= $statusLabel ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Valor</span>
                        <span class="text-sm font-semibold text-gray-900"><?= $valorBR ?></span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-2">
                        <span class="text-sm text-gray-500">Vencimento</span>
                        <span class="text-sm font-semibold text-gray-900"><?= $dataVenci ?></span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Emissao</span>
                        <span class="text-sm font-semibold text-gray-900"><?= $dataEmissao ?></span>
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
