<?php

namespace App\Helpers;

/**
 * Number To Words Helper
 *
 * Converte valores numéricos para texto por extenso com suporte a múltiplos locales.
 * Utilizado principalmente para documentos financeiros (promissórias, contratos, etc).
 */
class NumberToWordsHelper
{
    /**
     * Palavras por locale para conversão de números
     */
    private static array $localeWords = [
        'pt_BR' => [
            'zero' => 'zero',
            'units' => ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'],
            'teens' => ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'],
            'tens' => ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'],
            'hundreds' => ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'],
            'hundred' => 'cem',
            'thousand' => ['mil', 'mil'],
            'million' => ['milhão', 'milhões'],
            'billion' => ['bilhão', 'bilhões'],
            'trillion' => ['trilhão', 'trilhões'],
            'and' => 'e',
            'of' => 'de',
            'connector' => 'e',
        ],
        'pt_PT' => [
            'zero' => 'zero',
            'units' => ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'],
            'teens' => ['dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezasseis', 'dezassete', 'dezoito', 'dezanove'],
            'tens' => ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'],
            'hundreds' => ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'],
            'hundred' => 'cem',
            'thousand' => ['mil', 'mil'],
            'million' => ['milhão', 'milhões'],
            'billion' => ['bilião', 'biliões'],
            'trillion' => ['trilião', 'triliões'],
            'and' => 'e',
            'of' => 'de',
            'connector' => 'e',
        ],
        'en_US' => [
            'zero' => 'zero',
            'units' => ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
            'teens' => ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'],
            'tens' => ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'],
            'hundreds' => ['', 'one hundred', 'two hundred', 'three hundred', 'four hundred', 'five hundred', 'six hundred', 'seven hundred', 'eight hundred', 'nine hundred'],
            'hundred' => 'one hundred',
            'thousand' => ['thousand', 'thousand'],
            'million' => ['million', 'million'],
            'billion' => ['billion', 'billion'],
            'trillion' => ['trillion', 'trillion'],
            'and' => 'and',
            'of' => '',
            'connector' => 'and',
        ],
        'es_ES' => [
            'zero' => 'cero',
            'units' => ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'],
            'teens' => ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'],
            'tens' => ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'],
            'twenties' => ['veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'],
            'hundreds' => ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'],
            'hundred' => 'cien',
            'thousand' => ['mil', 'mil'],
            'million' => ['millón', 'millones'],
            'billion' => ['mil millones', 'mil millones'],
            'trillion' => ['billón', 'billones'],
            'and' => 'y',
            'of' => 'de',
            'connector' => 'con',
        ],
    ];

    /**
     * Nomes das moedas por código de moeda e locale
     */
    private static array $currencyNames = [
        'BRL' => [
            'pt_BR' => ['singular' => 'real', 'plural' => 'reais', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
            'pt_PT' => ['singular' => 'real', 'plural' => 'reais', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
            'en_US' => ['singular' => 'real', 'plural' => 'reais', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
            'es_ES' => ['singular' => 'real', 'plural' => 'reales', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
        ],
        'USD' => [
            'pt_BR' => ['singular' => 'dólar', 'plural' => 'dólares', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
            'pt_PT' => ['singular' => 'dólar', 'plural' => 'dólares', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
            'en_US' => ['singular' => 'dollar', 'plural' => 'dollars', 'centSingular' => 'cent', 'centPlural' => 'cents'],
            'es_ES' => ['singular' => 'dólar', 'plural' => 'dólares', 'centSingular' => 'centavo', 'centPlural' => 'centavos'],
        ],
        'EUR' => [
            'pt_BR' => ['singular' => 'euro', 'plural' => 'euros', 'centSingular' => 'cêntimo', 'centPlural' => 'cêntimos'],
            'pt_PT' => ['singular' => 'euro', 'plural' => 'euros', 'centSingular' => 'cêntimo', 'centPlural' => 'cêntimos'],
            'en_US' => ['singular' => 'euro', 'plural' => 'euros', 'centSingular' => 'cent', 'centPlural' => 'cents'],
            'es_ES' => ['singular' => 'euro', 'plural' => 'euros', 'centSingular' => 'céntimo', 'centPlural' => 'céntimos'],
        ],
        'GBP' => [
            'pt_BR' => ['singular' => 'libra', 'plural' => 'libras', 'centSingular' => 'pence', 'centPlural' => 'pence'],
            'pt_PT' => ['singular' => 'libra', 'plural' => 'libras', 'centSingular' => 'pence', 'centPlural' => 'pence'],
            'en_US' => ['singular' => 'pound', 'plural' => 'pounds', 'centSingular' => 'penny', 'centPlural' => 'pence'],
            'es_ES' => ['singular' => 'libra', 'plural' => 'libras', 'centSingular' => 'penique', 'centPlural' => 'peniques'],
        ],
    ];

    /**
     * Converte um valor monetário para texto por extenso
     *
     * @param float $value Valor a converter
     * @param string|null $locale Locale (pt_BR, en_US, pt_PT, es_ES)
     * @param string|null $currencyCode Código da moeda (BRL, USD, EUR)
     * @return string Valor por extenso
     *
     * @example
     * NumberToWordsHelper::convert(1234.56, 'pt_BR', 'BRL')
     * // "mil duzentos e trinta e quatro reais e cinquenta e seis centavos"
     */
    public static function convert(float $value, ?string $locale = null, ?string $currencyCode = null): string
    {
        $locale = $locale ?? 'pt_BR';
        $currencyCode = $currencyCode ?? 'BRL';

        // Garantir que temos as configurações para o locale
        if (!isset(self::$localeWords[$locale])) {
            $locale = 'pt_BR';
        }

        // Separar parte inteira e centavos
        $value = abs($value);
        $integerPart = (int) floor($value);
        $decimalPart = (int) round(($value - $integerPart) * 100);

        // Obter nomes da moeda
        $currencyName = self::getCurrencyNames($currencyCode, $locale);

        // Converter parte inteira
        $integerWords = self::convertInteger($integerPart, $locale);

        // Determinar singular/plural para moeda
        $currencyWord = $integerPart === 1 ? $currencyName['singular'] : $currencyName['plural'];

        // Verificar se precisa do "de" (milhão de reais, bilhão de reais)
        $needsOf = self::needsOfConnector($integerPart, $locale);

        // Aplicar forma apocopada para espanhol ("uno" -> "un" antes de substantivo masculino)
        if ($locale === 'es_ES') {
            $integerWords = self::applySpanishApocope($integerWords);
        }

        // Construir resultado
        $result = '';

        if ($integerPart > 0) {
            $of = $needsOf ? ' ' . self::$localeWords[$locale]['of'] . ' ' : ' ';
            $result = $integerWords . $of . $currencyWord;
        }

        // Adicionar centavos
        if ($decimalPart > 0) {
            $centWords = self::convertInteger($decimalPart, $locale);

            // Aplicar forma apocopada para espanhol nos centavos também
            if ($locale === 'es_ES') {
                $centWords = self::applySpanishApocope($centWords);
            }

            $centWord = $decimalPart === 1 ? $currencyName['centSingular'] : $currencyName['centPlural'];

            if ($integerPart > 0) {
                $connector = self::$localeWords[$locale]['connector'] ?? 'e';
                $result .= ' ' . $connector . ' ' . $centWords . ' ' . $centWord;
            } else {
                $result = $centWords . ' ' . $centWord;
            }
        }

        // Se valor é zero
        if ($integerPart === 0 && $decimalPart === 0) {
            $result = self::$localeWords[$locale]['zero'] . ' ' . $currencyName['plural'];
        }

        return $result;
    }

    /**
     * Converte um número inteiro para texto por extenso
     *
     * @param int $number Número a converter
     * @param string $locale Locale para a conversão
     * @return string Número por extenso
     */
    public static function convertInteger(int $number, string $locale = 'pt_BR'): string
    {
        if (!isset(self::$localeWords[$locale])) {
            $locale = 'pt_BR';
        }

        $words = self::$localeWords[$locale];

        if ($number === 0) {
            return $words['zero'];
        }

        if ($number < 0) {
            return 'menos ' . self::convertInteger(abs($number), $locale);
        }

        return self::convertPositiveInteger($number, $locale);
    }

    /**
     * Converte número inteiro positivo
     */
    private static function convertPositiveInteger(int $number, string $locale): string
    {
        $words = self::$localeWords[$locale];
        $parts = [];

        // Trilhões
        if ($number >= 1000000000000) {
            $trillions = (int) floor($number / 1000000000000);
            $parts[] = self::convertGroup($trillions, $locale) . ' ' . ($trillions === 1 ? $words['trillion'][0] : $words['trillion'][1]);
            $number %= 1000000000000;
        }

        // Bilhões
        if ($number >= 1000000000) {
            $billions = (int) floor($number / 1000000000);
            $parts[] = self::convertGroup($billions, $locale) . ' ' . ($billions === 1 ? $words['billion'][0] : $words['billion'][1]);
            $number %= 1000000000;
        }

        // Milhões
        if ($number >= 1000000) {
            $millions = (int) floor($number / 1000000);
            $parts[] = self::convertGroup($millions, $locale) . ' ' . ($millions === 1 ? $words['million'][0] : $words['million'][1]);
            $number %= 1000000;
        }

        // Milhares
        if ($number >= 1000) {
            $thousands = (int) floor($number / 1000);
            // Em português/espanhol, "um mil" não é usado, apenas "mil"
            if ($thousands === 1 && in_array($locale, ['pt_BR', 'pt_PT', 'es_ES'], true)) {
                $parts[] = $words['thousand'][0];
            } else {
                $parts[] = self::convertGroup($thousands, $locale) . ' ' . $words['thousand'][0];
            }
            $number %= 1000;
        }

        // Centenas e dezenas
        if ($number > 0) {
            $parts[] = self::convertGroup($number, $locale);
        }

        // Juntar partes com "e" ou vírgula dependendo do locale
        return self::joinParts($parts, $locale);
    }

    /**
     * Converte um grupo de até 3 dígitos (centenas)
     */
    private static function convertGroup(int $number, string $locale): string
    {
        $words = self::$localeWords[$locale];
        $parts = [];

        // Centenas
        if ($number >= 100) {
            $hundreds = (int) floor($number / 100);
            $remainder = $number % 100;

            // "cem" vs "cento" em português
            if ($hundreds === 1 && $remainder === 0 && in_array($locale, ['pt_BR', 'pt_PT'], true)) {
                $parts[] = $words['hundred'];
            } elseif ($hundreds === 1 && $remainder === 0 && $locale === 'es_ES') {
                $parts[] = $words['hundred'];
            } else {
                $parts[] = $words['hundreds'][$hundreds];
            }

            $number = $remainder;
        }

        // Dezenas e unidades
        if ($number >= 20) {
            $tens = (int) floor($number / 10);
            $units = $number % 10;

            // Espanhol tem formas especiais para 21-29
            if ($locale === 'es_ES' && $tens === 2) {
                $parts[] = $words['twenties'][$units];
            } elseif ($locale === 'en_US') {
                // Inglês: "twenty-one", "thirty-five"
                if ($units > 0) {
                    $parts[] = $words['tens'][$tens] . '-' . $words['units'][$units];
                } else {
                    $parts[] = $words['tens'][$tens];
                }
            } else {
                // Português e espanhol
                $parts[] = $words['tens'][$tens];
                if ($units > 0) {
                    $parts[] = $words['units'][$units];
                }
            }
        } elseif ($number >= 10) {
            // 10-19 (teens)
            $parts[] = $words['teens'][$number - 10];
        } elseif ($number > 0) {
            // 1-9
            $parts[] = $words['units'][$number];
        }

        // Juntar com "e" ou "and" dependendo do locale
        $and = $words['and'];

        if (count($parts) === 0) {
            return '';
        }

        if ($locale === 'en_US') {
            // Inglês: "one hundred twenty-three" (sem "and" entre centena e dezena)
            return implode(' ', $parts);
        }

        // Português e espanhol: juntar com "e" ou "y"
        return implode(' ' . $and . ' ', $parts);
    }

    /**
     * Junta as partes do número com conectores apropriados
     */
    private static function joinParts(array $parts, string $locale): string
    {
        if (count($parts) === 0) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $and = self::$localeWords[$locale]['and'];

        // Em português, usar "e" entre as partes quando a última é menor que 100
        // Ex: "mil e cem", "mil duzentos e trinta e quatro"
        if (in_array($locale, ['pt_BR', 'pt_PT', 'es_ES'], true)) {
            $last = array_pop($parts);
            return implode(' ', $parts) . ' ' . $and . ' ' . $last;
        }

        // Inglês: vírgula entre grupos maiores
        return implode(', ', $parts);
    }

    /**
     * Verifica se precisa do conector "de" antes da moeda
     * Ex: "um milhão de reais" vs "mil reais"
     */
    private static function needsOfConnector(int $number, string $locale): bool
    {
        if (!in_array($locale, ['pt_BR', 'pt_PT', 'es_ES'], true)) {
            return false;
        }

        // Precisa de "de" quando termina em milhão, bilhão, trilhão exato
        if ($number === 0) {
            return false;
        }

        // Verifica se é múltiplo exato de milhão, bilhão ou trilhão
        if ($number % 1000000 === 0 && $number >= 1000000) {
            return true;
        }

        return false;
    }

    /**
     * Aplica a forma apocopada do espanhol (uno -> un, veintiuno -> veintiún)
     * Usado antes de substantivos masculinos
     */
    private static function applySpanishApocope(string $text): string
    {
        // "veintiuno" -> "veintiún" (no final)
        $text = preg_replace('/veintiuno$/', 'veintiún', $text);

        // "uno" -> "un" (no final ou seguido de espaço + palavra)
        $text = preg_replace('/\buno$/', 'un', $text);

        return $text;
    }

    /**
     * Obtém os nomes da moeda para um locale específico
     */
    private static function getCurrencyNames(string $currencyCode, string $locale): array
    {
        // Fallback para BRL se moeda não encontrada
        if (!isset(self::$currencyNames[$currencyCode])) {
            $currencyCode = 'BRL';
        }

        // Fallback para pt_BR se locale não encontrado para esta moeda
        if (!isset(self::$currencyNames[$currencyCode][$locale])) {
            $locale = 'pt_BR';
        }

        return self::$currencyNames[$currencyCode][$locale];
    }

    /**
     * Converte apenas número para texto (sem moeda)
     *
     * @param float $value Valor a converter
     * @param string|null $locale Locale
     * @return string Número por extenso
     */
    public static function convertNumber(float $value, ?string $locale = null): string
    {
        $locale = $locale ?? 'pt_BR';

        if (!isset(self::$localeWords[$locale])) {
            $locale = 'pt_BR';
        }

        $integerPart = (int) floor(abs($value));
        $decimalPart = (int) round((abs($value) - $integerPart) * 100);

        $result = self::convertInteger($integerPart, $locale);

        if ($decimalPart > 0) {
            $connector = $locale === 'es_ES' ? 'con' : (in_array($locale, ['pt_BR', 'pt_PT'], true) ? 'vírgula' : 'point');
            $result .= ' ' . $connector . ' ' . self::convertInteger($decimalPart, $locale);
        }

        if ($value < 0) {
            $result = 'menos ' . $result;
        }

        return $result;
    }
}
