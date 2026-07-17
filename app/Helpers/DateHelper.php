<?php

namespace App\Helpers;

use App\Models\MatrizFilial;

/**
 * Date Helper
 *
 * Formatação e conversão de datas multi-tenant
 *
 * Cada empresa pode ter sua configuração de formato de data e timezone.
 * No banco de dados, datas são sempre armazenadas no formato internacional (Y-m-d).
 */
class DateHelper
{
    private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    /**
     * Cache da configuração da empresa atual
     */
    private static ?array $configCache = null;

    /**
     * Retorna a configuração de data da empresa ativa na sessão
     *
     * @return array {date_format, datetime_format, timezone, app_timezone}
     */
    public static function getConfig(): array
    {
        // Usar cache se disponível
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        // Valores padrão (Brasil)
        $config = [
            'date_format' => 'd/m/Y',
            'datetime_format' => 'd/m/Y H:i:s',
            'timezone' => self::DEFAULT_TIMEZONE,
            'app_timezone' => date_default_timezone_get() ?: self::DEFAULT_TIMEZONE,
        ];

        // Tentar obter configuração da empresa da sessão
        if (isset($_SESSION['chave'])) {
            try {
                $matrizFilial = new MatrizFilial();
                $matriz = $matrizFilial->buscarConfigData();

                if ($matriz) {
                    $config['date_format'] = $matriz['date_format'] ?: 'd/m/Y';
                    $config['datetime_format'] = $matriz['datetime_format'] ?: 'd/m/Y H:i:s';
                    $config['timezone'] = self::validTimezone($matriz['timezone'] ?? null)
                        ? $matriz['timezone']
                        : self::DEFAULT_TIMEZONE;
                }
            } catch (\Exception $e) {
                // Em caso de erro, usar configuração padrão
            }
        }

        self::$configCache = $config;
        return $config;
    }

    /**
     * Limpa o cache de configuração
     * Útil quando a empresa muda durante a sessão
     */
    public static function clearCache(): void
    {
        self::$configCache = null;
    }

    /**
     * Retorna a data atual de negocio no timezone da matriz/filial.
     */
    public static function todayForDatabase(string $format = 'Y-m-d'): string
    {
        $config = self::getConfig();
        return (new \DateTimeImmutable('now', new \DateTimeZone($config['timezone'])))->format($format);
    }

    /**
     * Normaliza vencimento de cobranca externa sem converter campos DATE por timezone.
     *
     * Datas de financeiro sao armazenadas como DATE (Y-m-d), entao devem ser
     * comparadas como data civil. Se o vencimento ja passou, o gateway recebe hoje.
     */
    public static function normalizeDueDateForGateway(?string $dueDate = null, ?string $today = null): string
    {
        $today = self::normalizeDateOnly($today) ?? self::todayForDatabase();
        $normalized = self::normalizeDateOnly($dueDate);

        if ($normalized === null) {
            return $today;
        }

        return $normalized < $today ? $today : $normalized;
    }

    /**
     * Retorna o instante atual para gravacao em campos DATETIME do banco.
     *
     * DATETIME sem offset permanece referenciado no timezone da aplicacao para
     * compatibilidade com dados existentes, enquanto a exibicao converte para a filial.
     */
    public static function nowForDatabase(string $format = 'Y-m-d H:i:s'): string
    {
        $config = self::getConfig();
        return (new \DateTimeImmutable('now', new \DateTimeZone($config['app_timezone'])))->format($format);
    }

    /**
     * Retorna a data atual tecnica no timezone da aplicacao.
     */
    public static function systemToday(string $format = 'Y-m-d'): string
    {
        $config = self::getConfig();
        return (new \DateTimeImmutable('now', new \DateTimeZone($config['app_timezone'])))->format($format);
    }

    /**
     * Retorna data/hora tecnica no timezone da aplicacao.
     */
    public static function systemNow(string $format = 'Y-m-d H:i:s'): string
    {
        $config = self::getConfig();
        return (new \DateTimeImmutable('now', new \DateTimeZone($config['app_timezone'])))->format($format);
    }

    /**
     * Retorna data de negocio somando dias no timezone da matriz/filial.
     */
    public static function addDaysForDatabase(int $days, ?string $baseDate = null, string $format = 'Y-m-d'): string
    {
        $config = self::getConfig();
        $timezone = new \DateTimeZone($config['timezone']);
        $date = $baseDate ? new \DateTimeImmutable($baseDate, $timezone) : new \DateTimeImmutable('now', $timezone);

        return $date->modify(($days >= 0 ? '+' : '') . $days . ' days')->format($format);
    }

    /**
     * Retorna data de negocio somando meses no timezone da matriz/filial.
     */
    public static function addMonthsForDatabase(int $months, ?string $baseDate = null, string $format = 'Y-m-d'): string
    {
        $config = self::getConfig();
        $timezone = new \DateTimeZone($config['timezone']);
        $date = $baseDate ? new \DateTimeImmutable($baseDate, $timezone) : new \DateTimeImmutable('now', $timezone);

        return $date->modify(($months >= 0 ? '+' : '') . $months . ' months')->format($format);
    }

    /**
     * Timestamp Unix tecnico para nomes de arquivo, cache e integracoes.
     */
    public static function timestamp(): int
    {
        return time();
    }

    /**
     * Data/hora tecnica em ISO 8601 no timezone da aplicacao.
     */
    public static function isoNow(): string
    {
        $config = self::getConfig();
        return (new \DateTimeImmutable('now', new \DateTimeZone($config['app_timezone'])))->format(\DateTimeInterface::ATOM);
    }

    /**
     * Formata um timestamp Unix no timezone de negocio ou no timezone tecnico.
     */
    public static function formatTimestamp(int $timestamp, string $format, bool $businessTimezone = true): string
    {
        $config = self::getConfig();
        $timezone = new \DateTimeZone($businessTimezone ? $config['timezone'] : $config['app_timezone']);

        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
    }

    /**
     * Converte um instante tecnico do banco para a data civil do tenant.
     */
    public static function businessDateFromDateTime(?string $datetime): ?string
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $config = self::getConfig();
            $date = new \DateTimeImmutable($datetime, new \DateTimeZone($config['app_timezone']));

            return $date
                ->setTimezone(new \DateTimeZone($config['timezone']))
                ->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private static function normalizeDateOnly(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '' || $date === '0000-00-00') {
            return null;
        }

        if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T].*)?$/', $date, $matches)) {
            return null;
        }

        $normalized = $matches[1];
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
        if (!$dt || $dt->format('Y-m-d') !== $normalized) {
            return null;
        }

        return $normalized;
    }

    /**
     * Formata uma data para exibição no front-end
     *
     * @param string|null $date Data no formato internacional (Y-m-d)
     * @return string Data formatada (ex: "15/01/2024")
     */
    public static function format(?string $date): string
    {
        if (empty($date) || $date === '0000-00-00') {
            return '';
        }

        try {
            $config = self::getConfig();
            $dt = new \DateTime($date);
            return $dt->format($config['date_format']);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Formata uma data/hora para exibição no front-end
     *
     * @param string|null $datetime Data/hora no formato internacional (Y-m-d H:i:s)
     * @return string Data/hora formatada (ex: "15/01/2024 14:30:00")
     */
    public static function formatDateTime(?string $datetime): string
    {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '';
        }

        try {
            $config = self::getConfig();
            $dt = new \DateTime($datetime, new \DateTimeZone($config['app_timezone']));
            $dt->setTimezone(new \DateTimeZone($config['timezone']));
            return $dt->format($config['datetime_format']);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Formata data/hora operacional sem converter timezone.
     *
     * Use para horarios escolhidos pelo usuario e gravados como valor local no banco
     * (retirada, devolucao, inicio/fim de contrato, checklist, multa, agenda).
     */
    public static function formatOperationalDateTime(?string $datetime, bool $withoutSeconds = true, ?string $format = null): string
    {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '';
        }

        $text = trim($datetime);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/', $text, $match)) {
            return $withoutSeconds ? (preg_replace('/(\d{1,2}:\d{2}):\d{2}\b/', '$1', $text) ?? $text) : $text;
        }

        $config = self::getConfig();
        $outputFormat = $format ?: ($config['datetime_format'] ?? 'd/m/Y H:i:s');
        if ($withoutSeconds) {
            $outputFormat = trim((string) preg_replace(['/:s\b/', '/\bs\b/'], '', $outputFormat));
        }

        $parts = [
            'd' => $match[3],
            'j' => (string) (int) $match[3],
            'm' => $match[2],
            'n' => (string) (int) $match[2],
            'Y' => $match[1],
            'y' => substr($match[1], -2),
            'H' => $match[4] ?? '00',
            'G' => (string) (int) ($match[4] ?? '0'),
            'i' => $match[5] ?? '00',
            's' => $match[6] ?? '00',
        ];

        return (string) preg_replace_callback('/[dmYyHGis]/', static fn($m) => $parts[$m[0]] ?? $m[0], $outputFormat);
    }

    /**
     * Converte uma data do formato local para formato internacional
     *
     * @param string|null $date Data no formato local (ex: "15/01/2024")
     * @return string|null Data no formato internacional (Y-m-d) ou null se inválida
     */
    public static function parse(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $config = self::getConfig();
            $timezone = new \DateTimeZone($config['timezone']);
            $dt = \DateTime::createFromFormat($config['date_format'], $date, $timezone);

            if ($dt === false) {
                // Tentar parse automático
                $dt = new \DateTime($date, $timezone);
            }

            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Converte uma data/hora do formato local para formato internacional
     *
     * @param string|null $datetime Data/hora no formato local (ex: "15/01/2024 14:30:00")
     * @return string|null Data/hora no formato internacional (Y-m-d H:i:s) ou null se inválida
     */
    public static function parseDateTime(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            $config = self::getConfig();
            $timezone = new \DateTimeZone($config['timezone']);
            $dt = \DateTime::createFromFormat($config['datetime_format'], $datetime, $timezone);

            if ($dt === false) {
                // Tentar parse automático
                $dt = new \DateTime($datetime, $timezone);
            }

            $dt->setTimezone(new \DateTimeZone($config['app_timezone']));

            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formata para input HTML (mantém formato local)
     *
     * @param string|null $date Data no formato internacional
     * @return string Data formatada para input
     */
    public static function formatForInput(?string $date): string
    {
        return self::format($date);
    }

    /**
     * Formata data/hora para input HTML
     *
     * @param string|null $datetime Data/hora no formato internacional
     * @return string Data/hora formatada para input
     */
    public static function formatDateTimeForInput(?string $datetime): string
    {
        return self::formatDateTime($datetime);
    }

    /**
     * Valida se uma data está no formato correto
     *
     * @param string $date Data a validar
     * @return bool
     */
    public static function isValidFormat(string $date): bool
    {
        if (empty($date)) {
            return true; // Vazio é válido
        }

        $config = self::getConfig();
        $dt = \DateTime::createFromFormat($config['date_format'], $date, new \DateTimeZone($config['timezone']));

        return $dt !== false;
    }

    /**
     * Valida se uma data/hora está no formato correto
     *
     * @param string $datetime Data/hora a validar
     * @return bool
     */
    public static function isValidDateTimeFormat(string $datetime): bool
    {
        if (empty($datetime)) {
            return true; // Vazio é válido
        }

        $config = self::getConfig();
        $dt = \DateTime::createFromFormat($config['datetime_format'], $datetime, new \DateTimeZone($config['timezone']));

        return $dt !== false;
    }

    private static function validTimezone(?string $timezone): bool
    {
        return is_string($timezone)
            && $timezone !== ''
            && in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }
}
