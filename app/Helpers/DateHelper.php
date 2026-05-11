<?php

namespace App\Helpers;

use App\Models\MatrizFilial;

/**
 * Date Helper
 *
 * Formatação e conversão de datas multi-tenant
 *
 * Cada empresa pode ter sua configuração de formato de data.
 * No banco de dados, datas são sempre armazenadas no formato internacional (Y-m-d).
 */
class DateHelper
{
    /**
     * Cache da configuração da empresa atual
     */
    private static ?array $configCache = null;

    /**
     * Retorna a configuração de data da empresa ativa na sessão
     *
     * @return array {date_format, datetime_format}
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
        ];

        // Tentar obter configuração da empresa da sessão
        if (isset($_SESSION['chave'])) {
            try {
                $matrizFilial = new MatrizFilial();
                $matriz = $matrizFilial->buscarConfigData();

                if ($matriz) {
                    $config['date_format'] = $matriz['date_format'] ?: 'd/m/Y';
                    $config['datetime_format'] = $matriz['datetime_format'] ?: 'd/m/Y H:i:s';
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
            $dt = new \DateTime($datetime);
            return $dt->format($config['datetime_format']);
        } catch (\Exception $e) {
            return '';
        }
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
            $dt = \DateTime::createFromFormat($config['date_format'], $date);

            if ($dt === false) {
                // Tentar parse automático
                $dt = new \DateTime($date);
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
            $dt = \DateTime::createFromFormat($config['datetime_format'], $datetime);

            if ($dt === false) {
                // Tentar parse automático
                $dt = new \DateTime($datetime);
            }

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
        $dt = \DateTime::createFromFormat($config['date_format'], $date);

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
        $dt = \DateTime::createFromFormat($config['datetime_format'], $datetime);

        return $dt !== false;
    }
}
