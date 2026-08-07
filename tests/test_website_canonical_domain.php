<?php

/**
 * Regressao: websites usam HTTPS e dominio canonico sem www.
 *
 * Execute: php tests/test_website_canonical_domain.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\WebsiteDomain;

function assertWebsiteCanonical(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validos = [
    'EXEMPLO.COM.BR' => 'exemplo.com.br',
    'www.exemplo.com.br' => 'exemplo.com.br',
    'https://www.exemplo.com.br/caminho?origem=teste' => 'exemplo.com.br',
    'http://exemplo.com.br.' => 'exemplo.com.br',
];

foreach ($validos as $entrada => $esperado) {
    assertWebsiteCanonical(
        WebsiteDomain::normalizar($entrada) === $esperado,
        "Dominio nao foi normalizado: {$entrada}"
    );
}

foreach (['', 'localhost', 'https://', 'dominio com espaco.com'] as $invalido) {
    assertWebsiteCanonical(
        WebsiteDomain::normalizar($invalido) === '',
        "Dominio invalido foi aceito: {$invalido}"
    );
}

$htaccess = WebsiteDomain::gerarHtaccess('www.exemplo.com.br');
assertWebsiteCanonical(
    substr_count($htaccess, 'https://exemplo.com.br%{REQUEST_URI}') === 2,
    'Redirecionamentos de protocolo e host devem apontar ao dominio canonico.'
);
assertWebsiteCanonical(
    str_contains($htaccess, 'RewriteCond %{HTTPS} !=on')
        && str_contains($htaccess, 'RewriteCond %{HTTP:X-Forwarded-Proto} !^https$ [NC]'),
    'Redirecionamento HTTPS deve considerar proxy reverso.'
);
assertWebsiteCanonical(
    str_contains($htaccess, 'RewriteCond %{HTTP_HOST} ^(?:www\\.)?exemplo\\.com\\.br(?::[0-9]+)?$ [NC]')
        && str_contains($htaccess, 'RewriteCond %{HTTP_HOST} ^www\\.exemplo\\.com\\.br(?::[0-9]+)?$ [NC]'),
    'Regras devem tratar apenas o dominio configurado e seu alias www.'
);
assertWebsiteCanonical(
    !str_contains($htaccess, 'RewriteCond %{HTTP_HOST} !^'),
    'Hosts temporarios de homologacao nao devem ser redirecionados para dominio ainda sem DNS.'
);
assertWebsiteCanonical(
    str_contains($htaccess, '[R=301,L,NE]'),
    'Redirecionamento deve ser permanente e preservar a URI sem reescape.'
);

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$config = [
    'dominio' => 'exemplo.com.br',
    'nome_empresa' => 'Empresa Teste',
    'idioma_padrao' => 'pt_BR',
    'idiomas_ativos' => ['pt_BR', 'en_US'],
    'deploy' => 'teste',
];
$seo = [];
$integracoes = [];
$idioma = 'pt_BR';
$pagina = 'veiculos';
$_SERVER['REQUEST_URI'] = '/veiculos.php?utm_source=teste';

ob_start();
include dirname(__DIR__) . '/storage/templates/website/includes/head.php';
$head = ob_get_clean();

assertWebsiteCanonical(
    str_contains($head, '<link rel="canonical" href="https://exemplo.com.br/veiculos.php">'),
    'Canonical deve usar HTTPS sem www e remover a query string.'
);
assertWebsiteCanonical(
    str_contains($head, '<meta property="og:url" content="https://exemplo.com.br/veiculos.php">'),
    'Open Graph deve usar a mesma URL canonica.'
);
assertWebsiteCanonical(
    !str_contains($head, 'utm_source'),
    'Parametros da requisicao nao devem contaminar sinais canonicos.'
);
assertWebsiteCanonical(
    str_contains($head, 'href="https://exemplo.com.br/en/"'),
    'Hreflang deve usar a URL-base canonica.'
);

$dados = [];
ob_start();
include dirname(__DIR__) . '/storage/templates/website/includes/structured-data.php';
$structuredData = ob_get_clean();
assertWebsiteCanonical(
    str_contains($structuredData, '"url":"https://exemplo.com.br"'),
    'JSON-LD deve usar a URL-base canonica.'
);

echo "OK: website usa HTTPS e dominio canonico sem www.\n";
