<?php

namespace App\Helpers;

/**
 * Helper para codigos publicos curtos.
 */
class CodigoHelper
{
    private const ALFABETO = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function gerarComPrefixo(string $prefixo, int $tamanho = 7): string
    {
        if ($tamanho < 1) {
            throw new \InvalidArgumentException('Tamanho do codigo deve ser maior que zero');
        }

        $codigo = strtoupper($prefixo);
        $limite = strlen(self::ALFABETO) - 1;

        for ($i = 0; $i < $tamanho; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $limite)];
        }

        return $codigo;
    }
}
