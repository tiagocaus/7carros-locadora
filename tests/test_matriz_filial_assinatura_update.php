#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

use App\Controllers\MatrizFilialController;
use App\Helpers\FileHelper;

function assertAssinaturaMatriz(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$chave = '1111111111111';
$arquivoCriado = null;
$controller = new MatrizFilialController();
$salvarAssinatura = new ReflectionMethod($controller, 'salvarAssinaturaImagem');

// PNG 1x1 transparente: o helper de assinatura deve gerar WebP sobre fundo branco.
$png = imagecreatetruecolor(1, 1);
imagealphablending($png, false);
imagesavealpha($png, true);
$transparente = imagecolorallocatealpha($png, 0, 0, 0, 127);
imagefill($png, 0, 0, $transparente);
ob_start();
imagepng($png);
$pngData = ob_get_clean();
imagedestroy($png);
$pngBase64 = 'data:image/png;base64,' . base64_encode($pngData);

try {
    $arquivoCriado = $salvarAssinatura->invoke($controller, $pngBase64, $chave);
    assertAssinaturaMatriz(
        preg_match('/^assinatura_[a-f0-9]+\.webp$/', $arquivoCriado) === 1,
        'A assinatura nao foi salva com o nome/formato WebP esperado.'
    );

    $caminho = FileHelper::getPath($arquivoCriado, $chave);
    assertAssinaturaMatriz(is_file($caminho), 'O arquivo da assinatura nao foi criado.');
    assertAssinaturaMatriz(
        (new finfo(FILEINFO_MIME_TYPE))->file($caminho) === 'image/webp',
        'O conteudo salvo nao e uma imagem WebP.'
    );

    $imagem = imagecreatefromwebp($caminho);
    assertAssinaturaMatriz($imagem instanceof GdImage, 'Nao foi possivel abrir a assinatura WebP.');
    $cor = imagecolorat($imagem, 0, 0);
    imagedestroy($imagem);
    assertAssinaturaMatriz(
        (($cor >> 16) & 0xFF) === 255 && (($cor >> 8) & 0xFF) === 255 && ($cor & 0xFF) === 255,
        'A transparencia da assinatura nao foi composta sobre fundo branco.'
    );

    $arquivoMaiorQueLimite = 'data:image/png;base64,' . base64_encode(
        "\x89PNG\r\n\x1a\n" . str_repeat('A', (5 * 1024 * 1024) + 1)
    );
    foreach (
        ['base64-invalido', 'data:application/pdf;base64,JVBERi0xLjQK', $arquivoMaiorQueLimite]
        as $invalido
    ) {
        $rejeitado = false;
        try {
            $salvarAssinatura->invoke($controller, $invalido, $chave);
        } catch (ReflectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            $rejeitado = $e instanceof InvalidArgumentException;
        }
        assertAssinaturaMatriz($rejeitado, 'Uma assinatura invalida foi aceita.');
    }

    $source = (string) file_get_contents(APP_ROOT . '/app/Controllers/MatrizFilialController.php');
    assertAssinaturaMatriz(
        str_contains($source, "input('assinatura_base64', '')")
            && str_contains($source, "\$dados['assinatura'] = \$assinaturaSalva"),
        'O endpoint de atualizacao nao consome assinatura_base64.'
    );

    echo "OK: assinatura_base64 da matriz/filial validada e salva em WebP com fundo branco.\n";
} finally {
    if ($arquivoCriado !== null) {
        FileHelper::delete($arquivoCriado, $chave);
    }
}
