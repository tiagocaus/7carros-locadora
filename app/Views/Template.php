<?php

namespace App\Views;

use App\Core\Session;
use App\Core\Auth;
use App\Services\NotificationService;

class Template
{
    private static string $viewsPath = '';
    private static string $cachePath = '';
    private static array $sections = [];
    private static array $sectionStack = [];
    private static ?string $extending = null;

    public static function init(): void
    {
        self::$viewsPath = __DIR__ . '/';
        self::$cachePath = dirname(__DIR__, 2) . '/storage/cache/views/';

        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0755, true);
        }
    }

    public static function render(string $view, array $data = []): string
    {
        self::init();

        $viewPath = self::$viewsPath . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: $view");
        }

        $cachedFile = self::compile($viewPath);

        extract($data);
        extract(self::getSharedData());

        ob_start();
        include $cachedFile;
        $content = ob_get_clean();

        if (self::$extending !== null) {
            $layoutPath = self::$viewsPath . str_replace('.', '/', self::$extending) . '.php';
            $cachedLayout = self::compile($layoutPath);

            self::$extending = null;

            ob_start();
            include $cachedLayout;
            $content = ob_get_clean();
        }

        self::clearState();

        return $content;
    }

    private static function compile(string $viewPath): string
    {
        $cacheFile = self::$cachePath . md5($viewPath) . '.php';

        if (!file_exists($cacheFile) || filemtime($viewPath) > filemtime($cacheFile)) {
            $content = file_get_contents($viewPath);
            $compiled = self::compileDirectives($content);
            file_put_contents($cacheFile, $compiled);
        }

        return $cacheFile;
    }

    private static function compileDirectives(string $content): string
    {
        // @extends
        $content = preg_replace('/@extends\(([\'"])(.*?)\1\)/', '<?php \\App\\Views\\Template::extend("$2"); ?>', $content);

        // @section com 2 parâmetros (inline): @section('name', value)
        // Suporta: @section('title', text('key')) ou @section('title', 'default')
        // Usa regex com suporte a parênteses aninhados (PHP 7.3+ com recursão)
        $content = preg_replace_callback(
            '/@section\(([\'"])([^\'"]+)\1,\s*((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/',
            function($matches) {
                $sectionName = $matches[2];
                $sectionContent = trim($matches[3]);
                return "<?php \\App\\Views\\Template::startSection(\"$sectionName\"); echo $sectionContent; \\App\\Views\\Template::endSection(); ?>";
            },
            $content
        );

        // @section com 1 parâmetro (bloco): @section('name')
        $content = preg_replace('/@section\(([\'"])(.*?)\1\)/', '<?php \\App\\Views\\Template::startSection("$2"); ?>', $content);

        // @endsection
        $content = preg_replace('/@endsection/', '<?php \\App\\Views\\Template::endSection(); ?>', $content);

        // @yield
        $content = preg_replace('/@yield\(([\'"])(.*?)\1(?:,\s*([\'"])(.*?)\3)?\)/', '<?= \\App\\Views\\Template::yieldSection("$2", "$4"); ?>', $content);

        // @include
        $content = preg_replace_callback('/@include\(([\'"])(.*?)\1(?:,\s*(\[.*?\]))?\)/', function($matches) {
            $view = $matches[2];
            $data = isset($matches[3]) && $matches[3] ? $matches[3] : '[]';
            return "<?= \\App\\Views\\Template::includePartial(\"{$view}\", {$data}); ?>";
        }, $content);

        // {{ $var }} - Escaped
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?= htmlspecialchars($1 ?? "", ENT_QUOTES, "UTF-8"); ?>', $content);

        // {!! $var !!} - Raw
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '<?= $1; ?>', $content);

        // @if (suporta parênteses aninhados)
        $content = preg_replace_callback('/@if\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/', function($matches) {
            return '<?php if (' . $matches[1] . '): ?>';
        }, $content);

        // @elseif (suporta parênteses aninhados)
        $content = preg_replace_callback('/@elseif\s*\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/', function($matches) {
            return '<?php elseif (' . $matches[1] . '): ?>';
        }, $content);

        // @else
        $content = preg_replace('/@else/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        // @foreach
        $content = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach ($1): ?>', $content);

        // @endforeach
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        // @for
        $content = preg_replace('/@for\s*\((.*?)\)/', '<?php for ($1): ?>', $content);

        // @endfor
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace('/@while\s*\((.*?)\)/', '<?php while ($1): ?>', $content);

        // @endwhile
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        // @auth
        $content = preg_replace('/@auth/', '<?php if (\\App\\Core\\Auth::check()): ?>', $content);

        // @guest
        $content = preg_replace('/@guest/', '<?php if (\\App\\Core\\Auth::guest()): ?>', $content);

        // @endauth / @endguest
        $content = preg_replace('/@end(auth|guest)/', '<?php endif; ?>', $content);

        // @csrfMeta (processar ANTES de @csrf para evitar substituição parcial)
        $content = preg_replace('/@csrfMeta/', '<?= \\App\\Views\\Template::csrfMeta(); ?>', $content);

        // @csrf
        $content = preg_replace('/@csrf/', '<?= \\App\\Views\\Template::csrf(); ?>', $content);

        // @method
        $content = preg_replace('/@method\(([\'"])(.*?)\1\)/', '<input type="hidden" name="_method" value="$2">', $content);

        // @php
        $content = preg_replace('/@php/', '<?php', $content);

        // @endphp
        $content = preg_replace('/@endphp/', '?>', $content);

        // @json
        $content = preg_replace('/@json\s*\((.*?)\)/', '<?= json_encode($1); ?>', $content);

        // @isset
        $content = preg_replace('/@isset\s*\((.*?)\)/', '<?php if (isset($1)): ?>', $content);

        // @endisset
        $content = preg_replace('/@endisset/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace('/@empty\s*\((.*?)\)/', '<?php if (empty($1)): ?>', $content);

        // @endempty
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        return $content;
    }

    public static function extend(string $layout): void
    {
        self::$extending = $layout;
    }

    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        if (empty(self::$sectionStack)) {
            throw new \Exception("No section started");
        }

        $section = array_pop(self::$sectionStack);
        self::$sections[$section] = ob_get_clean();
    }

    public static function yieldSection(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function includePartial(string $partial, array $data = []): string
    {
        // Rejeita nomes que tentam escapar do diretorio (defesa em camadas)
        if (strpos($partial, '..') !== false || strpos($partial, "\0") !== false) {
            throw new \Exception("Partial invalido: $partial");
        }

        $partialPath = self::$viewsPath . str_replace('.', '/', $partial) . '.php';

        if (!file_exists($partialPath)) {
            throw new \Exception("Partial not found: $partial");
        }

        // Garante que o path resolvido permanece dentro de viewsPath
        $resolved = realpath($partialPath);
        $base = realpath(self::$viewsPath);
        if ($resolved === false || $base === false || strpos($resolved, $base . DIRECTORY_SEPARATOR) !== 0) {
            throw new \Exception("Partial fora do diretorio permitido: $partial");
        }

        $cachedFile = self::compile($resolved);

        extract($data);

        ob_start();
        include $cachedFile;
        return ob_get_clean();
    }

    public static function csrf(): string
    {
        // Regenerar token CSRF periodicamente (a cada 15 minutos)
        $lastRegeneration = Session::get('csrf_token_time', 0);
        $now = \App\Helpers\DateHelper::timestamp();

        if (!Session::has('csrf_token') || ($now - $lastRegeneration) > 900) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
            Session::set('csrf_token_time', $now);
        }

        $token = Session::get('csrf_token');
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Gera meta tag CSRF para requisições AJAX
     */
    public static function csrfMeta(): string
    {
        // Regenerar token CSRF periodicamente (a cada 15 minutos)
        $lastRegeneration = Session::get('csrf_token_time', 0);
        $now = \App\Helpers\DateHelper::timestamp();

        if (!Session::has('csrf_token') || ($now - $lastRegeneration) > 900) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
            Session::set('csrf_token_time', $now);
        }

        $token = Session::get('csrf_token');
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    private static function getSharedData(): array
    {
        $flash = [
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'errors' => Session::getFlash('errors', []),
            'old' => Session::getFlash('old', [])
        ];

        $auth = [
            'auth' => Auth::check() ? Auth::user() : null,
            'auth_id' => Auth::id()
        ];

        $notifications = [];
        if (Auth::check()) {
            $notifications = (new NotificationService())->getCounts();
        }

        return array_merge($flash, $auth, ['notifications' => $notifications]);
    }

    private static function clearState(): void
    {
        self::$sections = [];
        self::$sectionStack = [];
        self::$extending = null;
    }
}
