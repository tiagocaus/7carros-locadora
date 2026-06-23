<?php

/**
 * Verifica traduções perigosas renderizadas em JavaScript inline.
 *
 * Execute: php tests/test_i18n_js_escaping.php
 */

$root = dirname(__DIR__);

$loadLangFile = static function (string $path): array {
    $data = require $path;
    return is_array($data) ? $data : [];
};

$flatten = static function (array $values, string $prefix = '') use (&$flatten): array {
    $flat = [];

    foreach ($values as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (is_array($value)) {
            $flat += $flatten($value, $fullKey);
            continue;
        }

        $flat[$fullKey] = (string) $value;
    }

    return $flat;
};

$dangerousKeys = [];
$langDir = $root . '/app/Lang/it_IT';
$langFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($langDir));

foreach ($langFiles as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $relativePath = substr($file->getPathname(), strlen($langDir) + 1);
    $prefix = str_replace('/', '.', substr($relativePath, 0, -4));

    foreach ($flatten($loadLangFile($file->getPathname()), $prefix) as $key => $value) {
        if (preg_match('/[\\\\\'"\\n\\r<>&]/', $value)) {
            $dangerousKeys[$key] = $value;
        }
    }
}

$failures = [];
$viewDir = $root . '/app/Views';
$viewFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewDir));

foreach ($viewFiles as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);

    if (!preg_match_all('/<script\\b[^>]*>(.*?)<\\/script>/is', $content, $scripts, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($scripts[1] as [$script, $offset]) {
        foreach ($dangerousKeys as $key => $value) {
            if (!str_contains($script, $key)) {
                continue;
            }

            $pattern = '/<\\?=\\s*(?:addslashes\\()?t\\(\\s*[\'"]' . preg_quote($key, '/') . '[\'"]/m';

            if (!preg_match_all($pattern, $script, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [, $matchOffset]) {
                $line = substr_count(substr($content, 0, $offset + $matchOffset), "\n") + 1;
                $failures[] = substr($path, strlen($root) + 1) . ':' . $line . ' usa t()/addslashes(t()) em JS para ' . $key;
            }
        }
    }
}

if ($failures !== []) {
    echo "Falhas de escape JS i18n encontradas:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "OK: nenhuma tradução italiana perigosa foi renderizada com t()/addslashes(t()) dentro de <script>.\n";
