<?php

namespace App\Config;

/**
 * Configuração dos Planos de Assinatura
 * 
 * Define os planos disponíveis no sistema com suas respectivas
 * limitações e características.
 * 
 * SOBRE OS PLANOS:
 * - plano_nome:    => Nome do plano
 * - matrizfilial:  => Quantidade de matrizes/filiais que o plano permite (0 = Nenhuma)
 * - veiculos:      => Quantidade de veículos que o plano permite (0 = Nenhum)
 * - whatsapp:      => Quantidade de conexões WhatsApp que o plano permite (0 = Nenhuma)
 * - sms:           => Quantidade de conexões SMS que o plano permite (0 = Nenhuma)
 * - smtp:          => Quantidade de conexões SMTP que o plano permite (0 = Nenhuma)
 */
class Planos
{
    /**
     * Array com todos os planos disponíveis
     */
    public const PLANOS = [
        "G" => [
            "plano_nome"     => "Gratuito",
            "matrizfilial"   => 1,
            "veiculos"       => 3,
            "whatsapp"       => 0,
            "sms"            => 0,
            "smtp"           => 1
        ],
        "P0" => [
            "plano_nome"     => "Junior",
            "matrizfilial"   => 1,
            "veiculos"       => 3,
            "whatsapp"       => 0,
            "sms"            => 0,
            "smtp"           => 1
        ],
        "P1" => [
            "plano_nome"     => "Iniciante",
            "matrizfilial"   => 1,
            "veiculos"       => 5,
            "whatsapp"       => 0,
            "sms"            => 0,
            "smtp"           => 1
        ],
        "P2" => [
            "plano_nome"     => "Intermediário",
            "matrizfilial"   => 1,
            "veiculos"       => 10,
            "whatsapp"       => 0,
            "sms"            => 0,
            "smtp"           => 1
        ],
        "P3" => [
            "plano_nome"     => "Avançado",
            "matrizfilial"   => 3,
            "veiculos"       => 20,
            "whatsapp"       => 0,
            "sms"            => 0,
            "smtp"           => 1
        ],
        "P4" => [
            "plano_nome"     => "Ilimitado",
            "matrizfilial"   => 9999999,
            "veiculos"       => 9999999,
            "whatsapp"       => 1,
            "sms"            => 9999999,
            "smtp"           => 9999999
        ],
    ];

    /**
     * Obtém o nome do plano pelo código
     * 
     * @param string $codigo Código do plano (ex: "P4", "G", "P1")
     * @return string Nome do plano ou código se não encontrado
     */
    public static function getNome(string $codigo): string
    {
        return self::PLANOS[$codigo]['plano_nome'] ?? $codigo;
    }

    /**
     * Obtém todas as informações de um plano
     * 
     * @param string $codigo Código do plano
     * @return array|null Array com informações do plano ou null se não encontrado
     */
    public static function getPlano(string $codigo): ?array
    {
        return self::PLANOS[$codigo] ?? null;
    }

    /**
     * Verifica se um plano existe
     * 
     * @param string $codigo Código do plano
     * @return bool
     */
    public static function existe(string $codigo): bool
    {
        return isset(self::PLANOS[$codigo]);
    }

    /**
     * Obtém todos os planos disponíveis
     * 
     * @return array
     */
    public static function todos(): array
    {
        return self::PLANOS;
    }
}
