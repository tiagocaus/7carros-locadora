<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Pagamento - 7Carros')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .payment-option {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #e2e8f0;
        }

        .payment-option:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }

        .payment-option.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .pix-code {
            font-family: monospace;
            font-size: 0.75rem;
            word-break: break-all;
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
        }

        .qrcode-container {
            background: white;
            padding: 16px;
            border-radius: 12px;
            display: inline-block;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    @yield('styles')
</head>
<body class="py-8 px-4">
    <div class="max-w-lg mx-auto">
        @yield('content')

        <!-- Footer -->
        <div class="text-center mt-8 text-sm text-slate-500">
            <p>Um sistema desenvolvido por</p>
            <p class="font-semibold text-slate-700 mt-1">© 7Carros.com</p>
        </div>
    </div>

    @yield('scripts')
</body>
</html>
