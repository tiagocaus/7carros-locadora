<?php
    $htmlLocale = locale_info()['code'] ?? 'pt-BR';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '7Carros Locadora')</title>
    @csrfMeta

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= asset('css/base.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/utilities.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/custom-classes.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/iframe-pages.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/chosen-select.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/intl-phone.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/nestable-list.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/components.min.css'); ?>" rel="stylesheet">
</head>
<body class="iframe-page">
    @yield('content')

    <!-- App Config -->
    <script>
        window.APP_CONFIG = {
            currency: <?= json_encode(currency_config()) ?>,
            date: <?= json_encode(date_config()) ?>
        };
        window.APP_I18N = window.APP_I18N || {};
        window.APP_I18N.common = <?= json_encode(\App\I18n\Translator::getInstance()->getFile('common'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

    <!-- JavaScript -->
    <script src="<?= asset('js/api.js'); ?>"></script>
    <script src="<?= asset('js/currency.js'); ?>"></script>
    <script src="<?= asset('js/percent.min.js'); ?>"></script>
    <script src="<?= asset('js/date.js'); ?>"></script>
    <script src="<?= asset('js/chosen-select.min.js'); ?>"></script>
    <script src="<?= asset('js/country-data.min.js'); ?>"></script>
    <script src="<?= asset('js/intl-phone.min.js'); ?>"></script>
    <script src="<?= asset('js/cep.min.js'); ?>"></script>
    <script src="<?= asset('js/form-validation.js'); ?>"></script>
    <script src="<?= asset('js/toast.js'); ?>"></script>
    <script src="<?= asset('js/components.js'); ?>"></script>
    <script src="<?= asset('js/autocomplete-guard.min.js'); ?>"></script>
    <script src="<?= asset('js/form-audit.min.js'); ?>"></script>

    <!-- Audit Handlers Especializados (carregamento condicional) -->
    <?php
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, 'financeiro/adicionar') !== false): ?>
        <script src="<?= asset('js/audit-handlers/financeiro-adicionar.min.js'); ?>"></script>
    <?php endif; ?>
    <?php if (strpos($requestUri, 'manutencoes/adicionar') !== false): ?>
        <script src="<?= asset('js/audit-handlers/manutencoes-adicionar.min.js'); ?>"></script>
    <?php endif; ?>

    <!-- Helper para controle de loading -->
    <script>
        window.pageLoading = {
            _pending: 0,
            _sent: false,

            start: function() {
                this._pending++;
            },

            done: function() {
                this._pending--;
                this._notify();
            },

            _notify: function() {
                if (this._pending <= 0 && !this._sent) {
                    this._sent = true;
                    if (window.parent !== window) {
                        window.parent.postMessage({ action: 'iframeReady' }, '*');
                    }
                }
            }
        };

        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.pageLoading._notify();
            }, 10);
        });

        // Receber token CSRF atualizado do parent ou sibling
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'csrfTokenRefreshed' && event.data.csrfToken) {
                if (window.API && typeof window.API.syncCsrfToken === 'function') {
                    window.API.syncCsrfToken(event.data.csrfToken);
                } else {
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = event.data.csrfToken;
                    document.querySelectorAll('input[name="_token"]').forEach(function(input) {
                        input.value = event.data.csrfToken;
                    });
                }
            }
        });

        // Ctrl+K / Cmd+K: encaminhar para o parent abrir o Spotlight
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                if (window.parent !== window) {
                    window.parent.postMessage({ action: 'openSpotlight' }, '*');
                }
            }
        });
    </script>

    @yield('scripts')
</body>
</html>
