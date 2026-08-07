<?php

namespace App\Services;

final class WebsiteDomain
{
    public static function normalizar(string $dominio): string
    {
        $dominio = strtolower(trim($dominio));
        $dominio = preg_replace('#^https?://#i', '', $dominio);
        $dominio = preg_split('/[\/?#]/', $dominio)[0] ?? '';
        $dominio = rtrim(trim($dominio), '.');

        if (str_starts_with($dominio, 'www.')) {
            $dominio = substr($dominio, 4);
        }

        if (
            $dominio === ''
            || !str_contains($dominio, '.')
            || filter_var($dominio, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            return '';
        }

        return $dominio;
    }

    public static function exigirValido(string $dominio): string
    {
        $normalizado = self::normalizar($dominio);
        if ($normalizado === '') {
            throw new \InvalidArgumentException('Dominio invalido');
        }

        return $normalizado;
    }

    public static function urlBase(string $dominio): string
    {
        return 'https://' . self::exigirValido($dominio);
    }

    public static function gerarHtaccess(string $dominio): string
    {
        $dominio = self::exigirValido($dominio);
        $hostPattern = preg_quote($dominio, '#');
        $urlBase = 'https://' . $dominio;

        return <<<HTACCESS
RewriteEngine On

# Canonical: HTTPS e dominio sem www.
RewriteCond %{HTTP_HOST} ^(?:www\.)?{$hostPattern}(?::[0-9]+)?$ [NC]
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !^https$ [NC]
RewriteRule ^ {$urlBase}%{REQUEST_URI} [R=301,L,NE]

RewriteCond %{HTTP_HOST} ^www\.{$hostPattern}(?::[0-9]+)?$ [NC]
RewriteRule ^ {$urlBase}%{REQUEST_URI} [R=301,L,NE]

HTACCESS;
    }
}
