<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\PdfHelper;

function assertPdfMemory(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$original = (string) ini_get('memory_limit');

try {
    ini_set('memory_limit', '128M');
    $pdf = PdfHelper::create(['watermark' => false]);
    assertPdfMemory(ini_get('memory_limit') === '256M', 'PDF deve elevar o limite de 128M para 256M.');
    unset($pdf);

    ini_set('memory_limit', '512M');
    $pdf = PdfHelper::create(['watermark' => false]);
    assertPdfMemory(ini_get('memory_limit') === '512M', 'PDF nao pode reduzir um limite maior do servidor.');
    unset($pdf);

    ini_set('memory_limit', '-1');
    $pdf = PdfHelper::create(['watermark' => false]);
    assertPdfMemory(ini_get('memory_limit') === '-1', 'PDF deve preservar memoria ilimitada configurada pelo servidor.');
    unset($pdf);

    echo "OK: limite de memoria do PDF elevado sem reduzir configuracoes maiores.\n";
} finally {
    ini_set('memory_limit', $original);
}
