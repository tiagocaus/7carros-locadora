<?php

/**
 * Regressao: o minificador do website deve preservar operadores de calc().
 *
 * Execute: php tests/test_website_css_minification.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\WebsiteCssService;

function assertWebsiteCss(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new WebsiteCssService();
$input = <<<'CSS'
/* comentario removido */
.item + .item {
    width: calc(100% + 1rem);
    margin: calc(100% - 2px);
    color: red;
}
CSS;

$minified = $service->minificar($input);

assertWebsiteCss(
    $minified === '.item + .item{width:calc(100% + 1rem);margin:calc(100% - 2px);color:red}',
    'A minificacao deve preservar operadores aritmeticos validos e remover whitespace desnecessario.'
);

$templateCss = file_get_contents(
    dirname(__DIR__) . '/storage/templates/website/assets/css/style.css'
);
assertWebsiteCss($templateCss !== false, 'O CSS fonte do website deve estar disponivel.');

$templateMinified = $service->minificar($templateCss);
assertWebsiteCss(
    str_contains($templateMinified, 'calc(6.75rem + env(safe-area-inset-top,0px))'),
    'O padding do menu mobile deve manter o calc() valido apos a minificacao.'
);
assertWebsiteCss(
    !str_contains($templateMinified, 'calc(6.75rem+env('),
    'O minificador nao pode remover os espacos obrigatorios do operador + em calc().'
);

echo "OK: minificador preserva operadores de calc() no CSS do website.\n";
