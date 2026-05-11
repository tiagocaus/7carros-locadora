<?php
/**
 * Helpers do site público: tradução, detecção de idioma, conteúdos editáveis, formatação.
 *
 * Este arquivo é incluído no topo de toda página e deixa disponível:
 * - $config   : config.php gerado no build
 * - $api      : instância de SiteApi
 * - $idioma   : idioma atual (detectado pela URL)
 * - $dados    : dados do tenant (filiais, grupos, empresa, serviços)
 * - $conteudos, $seoAll, $banners, $links, $integracoes : vindos de /api/public/conteudos
 * - $conteudosGlobal : mapa de seções editáveis da página "global" (compartilhadas)
 * - helper t()        : tradução
 * - helper secao()    : conteúdo editável do CMS
 * - helper langUrl()  : URL com prefixo de idioma
 * - helper e()        : escapa HTML
 * - helper formatarMoeda()
 */

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/api.php';
$api = new SiteApi($config);

$idioma = detectarIdioma($config);

$traducoes = [];
$langFile = __DIR__ . '/../lang/' . $idioma . '.php';
if (file_exists($langFile)) {
    $traducoes = require $langFile;
}

// Carrega dados do tenant e conteúdos editáveis (em cache local via SiteApi)
$dados = $api->getDadosSite();
$conteudosApi = $api->getConteudos($idioma);

$conteudos   = $conteudosApi['paginas']     ?? [];
$seoAll      = $conteudosApi['seo']         ?? [];
$banners     = $conteudosApi['banners']     ?? [];
$links       = $conteudosApi['links']       ?? [];
$integracoes = $conteudosApi['integracoes'] ?? [];

// Flags runtime (sem cache) — refletem mudanças do backoffice imediatamente
$siteStatus = $api->getStatus();
$runtimeOk = !empty($siteStatus['success']);

$manutencaoAtiva   = $runtimeOk ? !empty($siteStatus['manutencao'])       : (bool) ($config['manutencao'] ?? false);
$reservaOnline     = $runtimeOk ? !empty($siteStatus['reserva_online'])   : (bool) ($config['reserva_online'] ?? true);
$whatsappFlutuante = $runtimeOk ? !empty($siteStatus['whatsapp_flutuante']): (bool) ($config['whatsapp_flutuante'] ?? false);
$whatsappNumero    = $runtimeOk ? (string) ($siteStatus['whatsapp_numero']   ?? '') : (string) ($config['whatsapp_numero'] ?? '');
$whatsappMensagem  = $runtimeOk ? (string) ($siteStatus['whatsapp_mensagem'] ?? '') : (string) ($config['whatsapp_mensagem'] ?? '');

// Logo e favicon vem do proprio deploy (assets/img/) — independentes da API
$logoUrl    = (string) ($config['logo_url'] ?? '');
$faviconUrl = (string) ($config['favicon_url'] ?? '');

// Flags de aparencia permanecem runtime (ajuste visual sem redeploy)
$logoFundoBranco = $runtimeOk ? !empty($siteStatus['logo_fundo_branco'])       : (bool) ($config['logo_fundo_branco'] ?? true);
$logoAlinhamento = $runtimeOk ? (string) ($siteStatus['logo_alinhamento']  ?? 'centro') : (string) ($config['logo_alinhamento'] ?? 'centro');

// Mapa [pagina][secao] => conteudo, consumido pelo helper secao()
$_secoes_map = [];
foreach ($conteudos as $pagina => $secoes) {
    $_secoes_map[$pagina] = array_column($secoes, 'conteudo', 'secao');
}
$conteudosGlobal = $_secoes_map['global'] ?? [];

/**
 * Tradução — t('nav.inicio') retorna "Início"
 */
function t(string $key): string
{
    global $traducoes;
    $keys = explode('.', $key);
    $value = $traducoes;
    foreach ($keys as $k) {
        if (!is_array($value) || !isset($value[$k])) {
            return $key;
        }
        $value = $value[$k];
    }
    return is_string($value) ? $value : $key;
}

/**
 * Conteúdo editável do CMS — secao('inicio', 'por_que_1_titulo', 'Atendimento').
 * Se a seção não existir no BD, retorna o default.
 */
function secao(string $pagina, string $secao, string $default = ''): string
{
    global $_secoes_map;
    return $_secoes_map[$pagina][$secao] ?? $default;
}

/**
 * Detecta idioma em ordem de prioridade:
 * 1) $_GET['lang']          — troca explícita (grava cookie)
 * 2) $_COOKIE['lang']       — mantém escolha anterior
 * 3) URL prefix /en/, /es/  — caso de subpastas (opcional)
 * 4) HTTP_ACCEPT_LANGUAGE   — idioma do navegador
 * 5) idioma_padrao          — fallback
 */
function detectarIdioma(array $config): string
{
    $ativos = $config['idiomas_ativos'] ?? [$config['idioma_padrao']];

    // 1) Query string — troca explícita
    $getLang = $_GET['lang'] ?? '';
    if ($getLang) {
        foreach ($ativos as $l) {
            if (strcasecmp($l, $getLang) === 0 || strcasecmp(substr($l, 0, 2), substr($getLang, 0, 2)) === 0) {
                // Persiste em cookie (30 dias) pra manter entre navegações
                @setcookie('lang', $l, time() + 60 * 60 * 24 * 30, '/');
                $_COOKIE['lang'] = $l;
                return $l;
            }
        }
    }

    // 2) Cookie
    $cookieLang = $_COOKIE['lang'] ?? '';
    if ($cookieLang && in_array($cookieLang, $ativos, true)) {
        return $cookieLang;
    }

    // 3) URL prefix (caso o tenant use subpastas no futuro)
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    foreach ($ativos as $l) {
        $prefix = '/' . substr($l, 0, 2) . '/';
        if (strpos($uri, $prefix) !== false) {
            return $l;
        }
    }

    // 4) Accept-Language do navegador
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($accept) {
        $first2 = strtolower(substr($accept, 0, 2));
        foreach ($ativos as $l) {
            if (strcasecmp(substr($l, 0, 2), $first2) === 0) {
                return $l;
            }
        }
    }

    return $config['idioma_padrao'];
}

/**
 * Gera URL relativa para uma página. URL sempre limpa — o idioma é mantido
 * via cookie (gravado no clique do seletor), então não precisa de query string.
 */
function langUrl(string $page, string $idioma = ''): string
{
    return ltrim($page, '/');
}

/**
 * Formata valor monetário usando dados da filial
 */
function formatarMoeda(float $valor, string $simbolo = 'R$', string $decimalSep = ',', string $milharSep = '.'): string
{
    return $simbolo . ' ' . number_format($valor, 2, $decimalSep, $milharSep);
}

/**
 * Escapa HTML para saída segura
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
