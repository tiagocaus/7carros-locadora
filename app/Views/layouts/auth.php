<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login - 7Carros Locadora')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= asset('css/base.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/components.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/login.min.css'); ?>" rel="stylesheet">
</head>
<body>
    @yield('content')

    @yield('scripts')
</body>
</html>
