<?php

namespace App\Helpers;

use App\Models\MatrizFilial;
use NumberFormatter;

/**
 * Currency Helper
 *
 * Formatação e conversão de valores monetários multi-tenant
 *
 * Cada empresa pode ter sua configuração de locale e moeda.
 * No banco de dados, valores são sempre armazenados no formato internacional (1234.56).
 */
class CurrencyHelper
{
    /**
     * Configurações de formatação por locale
     */
    private static array $localeConfigs = [
        'pt_BR' => [
            'decimal' => ',',
            'thousands' => '.',
            'symbol' => 'R$',
            'symbolPosition' => 'before', // R$ 1.234,56
        ],
        'en_US' => [
            'decimal' => '.',
            'thousands' => ',',
            'symbol' => '$',
            'symbolPosition' => 'before', // $1,234.56
        ],
        'pt_PT' => [
            'decimal' => ',',
            'thousands' => '.',
            'symbol' => '€',
            'symbolPosition' => 'after', // 1.234,56 €
        ],
        'es_ES' => [
            'decimal' => ',',
            'thousands' => '.',
            'symbol' => '€',
            'symbolPosition' => 'after', // 1.234,56 €
        ],
    ];

    /**
     * Cache da configuração da empresa atual
     */
    private static ?array $configCache = null;

    /**
     * Retorna a configuração de moeda da empresa ativa na sessão
     *
     * @return array {locale, currency, symbol, decimal, thousands, symbolPosition}
     */
    public static function getConfig(): array
    {
        // Usar cache se disponível
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        // Valores padrão (Brasil)
        $config = [
            'locale' => 'pt_BR',
            'currency' => 'BRL',
            'symbol' => 'R$',
            'decimal' => ',',
            'thousands' => '.',
            'symbolPosition' => 'before',
        ];

        // Tentar obter configuração da empresa da sessão
        if (isset($_SESSION['chave'])) {
            try {
                $matrizFilial = new MatrizFilial();
                $matriz = $matrizFilial->buscarConfigMoeda();

                if ($matriz) {
                    $locale = $matriz['locale'] ?? 'pt_BR';
                    $currency = $matriz['currency_code'] ?? 'BRL';

                    // Obter configuração do locale
                    $localeConfig = self::$localeConfigs[$locale] ?? self::$localeConfigs['pt_BR'];

                    // Atualizar símbolo baseado na moeda
                    $symbols = [
                        'BRL' => 'R$',
                        'USD' => '$',
                        'EUR' => '€',
                        'GBP' => '£',
                    ];

                    $config = [
                        'locale' => $locale,
                        'currency' => $currency,
                        'symbol' => $symbols[$currency] ?? $currency,
                        'decimal' => $localeConfig['decimal'],
                        'thousands' => $localeConfig['thousands'],
                        'symbolPosition' => $localeConfig['symbolPosition'],
                    ];
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
     * Formata um valor numérico para exibição no front-end
     *
     * @param float|int|string|null $value Valor a formatar
     * @param bool $showSymbol Incluir símbolo da moeda (padrão: true)
     * @param int|null $matrizId ID da matriz/filial (opcional, usa sessão se não informado)
     * @return string Valor formatado (ex: "R$ 1.234,56")
     */
    public static function format(float|int|string|null $value, bool $showSymbol = true, ?int $matrizId = null): string
    {
        // Converter para float
        $value = (float) ($value ?? 0);

        // Obter configuração
        $config = self::getConfig();

        // Se informou matrizId específico, buscar config dessa matriz
        if ($matrizId !== null) {
            try {
                $matrizFilial = new MatrizFilial();
                $formatted = $matrizFilial->formatarMoeda($value, $matrizId);

                // Se não quer símbolo, remover
                if (!$showSymbol) {
                    $formatted = preg_replace('/[^\d,.\s-]/', '', $formatted);
                    $formatted = trim($formatted);
                }

                return $formatted;
            } catch (\Exception $e) {
                // Fallback para formatação manual
            }
        }

        // Formatação manual baseada na config
        $formatted = number_format($value, 2, $config['decimal'], $config['thousands']);

        if ($showSymbol) {
            if ($config['symbolPosition'] === 'before') {
                $formatted = $config['symbol'] . ' ' . $formatted;
            } else {
                $formatted = $formatted . ' ' . $config['symbol'];
            }
        }

        return $formatted;
    }

    /**
     * Converte um valor formatado do front-end para float (formato internacional)
     *
     * @param string|float|int|null $value Valor formatado (ex: "R$ 1.234,56" ou "1.234,56")
     * @param string|null $locale Locale para interpretar (opcional, usa sessão se não informado)
     * @return float Valor numérico (ex: 1234.56)
     */
    public static function parse(string|float|int|null $value, ?string $locale = null): float
    {
        // Se já é numérico, retornar como float
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        // Se é null ou vazio, retornar 0
        if ($value === null || trim($value) === '') {
            return 0.0;
        }

        $value = (string) $value;

        // Remover símbolo de moeda e espaços extras
        $value = preg_replace('/[^\d,.\-]/', '', $value);
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        // Determinar o locale
        $config = self::getConfig();
        $currentLocale = $locale ?? $config['locale'];
        $localeConfig = self::$localeConfigs[$currentLocale] ?? self::$localeConfigs['pt_BR'];

        $decimal = $localeConfig['decimal'];
        $thousands = $localeConfig['thousands'];

        // Detectar formato automaticamente se houver ambiguidade
        // Ex: "1.234" pode ser 1234 (BR) ou 1.234 (US)
        $hasComma = strpos($value, ',') !== false;
        $hasDot = strpos($value, '.') !== false;

        if ($hasComma && $hasDot) {
            // Tem ambos: o último é o decimal
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                // Formato BR: 1.234,56
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // Formato US: 1,234.56
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasComma) {
            // Só tem vírgula: pode ser decimal BR ou milhares US
            // Se tem apenas uma vírgula e 2 dígitos após, é decimal
            $parts = explode(',', $value);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // Decimal BR: 1234,56
                $value = str_replace(',', '.', $value);
            } else {
                // Milhares US: 1,234,567
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasDot) {
            // Só tem ponto: pode ser decimal US ou milhares BR
            // Se tem apenas um ponto e 2 dígitos após, é decimal
            $parts = explode('.', $value);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // Decimal US: 1234.56 - já está correto
            } else {
                // Milhares BR: 1.234.567
                $value = str_replace('.', '', $value);
            }
        }

        return (float) $value;
    }

    /**
     * Formata para input HTML (sem símbolo)
     *
     * @param float|int|string|null $value Valor a formatar
     * @return string Valor formatado sem símbolo
     */
    public static function formatForInput(float|int|string|null $value): string
    {
        return self::format($value, false);
    }

    /**
     * Valida se um valor está no formato correto para o locale atual
     *
     * @param string $value Valor a validar
     * @return bool
     */
    public static function isValidFormat(string $value): bool
    {
        // Remover símbolo e espaços
        $value = preg_replace('/[^\d,.\-]/', '', trim($value));

        if ($value === '' || $value === '-') {
            return true; // Vazio é válido (será convertido para 0)
        }

        // Verificar se é um número válido após conversão
        $parsed = self::parse($value);

        return is_numeric($parsed);
    }

    /**
     * Formata um valor monetário com valor por extenso entre parênteses
     *
     * @param float|int|string|null $value Valor a formatar
     * @param int|null $matrizId ID da matriz/filial (opcional)
     * @return string Valor formatado com extenso
     *
     * @example
     * CurrencyHelper::formatWithWords(1234.56)
     * // "R$ 1.234,56 (mil duzentos e trinta e quatro reais e cinquenta e seis centavos)"
     */
    public static function formatWithWords(float|int|string|null $value, ?int $matrizId = null): string
    {
        $numericValue = (float) ($value ?? 0);
        $formatted = self::format($numericValue, true, $matrizId);
        $config = self::getConfig();

        $extenso = NumberToWordsHelper::convert($numericValue, $config['locale'], $config['currency']);

        return $formatted . ' (' . $extenso . ')';
    }

    /**
     * Retorna apenas o valor por extenso (sem o valor numérico formatado)
     *
     * @param float|int|string|null $value Valor a converter
     * @param int|null $matrizId ID da matriz/filial (opcional)
     * @return string Valor por extenso
     *
     * @example
     * CurrencyHelper::toWords(1234.56)
     * // "mil duzentos e trinta e quatro reais e cinquenta e seis centavos"
     */
    public static function toWords(float|int|string|null $value, ?int $matrizId = null): string
    {
        $numericValue = (float) ($value ?? 0);
        $config = self::getConfig();

        return NumberToWordsHelper::convert($numericValue, $config['locale'], $config['currency']);
    }
}
