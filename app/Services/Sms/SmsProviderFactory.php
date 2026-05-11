<?php

namespace App\Services\Sms;

/**
 * Factory para criar instancias de provedores SMS
 *
 * Permite criar o provedor adequado com base no nome do provider
 * configurado na conexao SMS.
 */
class SmsProviderFactory
{
    /**
     * Cria uma instancia do provedor SMS
     *
     * @param string $provider Nome do provedor (clicksend, twilio, etc)
     * @param string $username Username/email do provedor
     * @param string $apiKey API Key do provedor
     * @return SmsProviderInterface
     * @throws \InvalidArgumentException Se o provedor nao for suportado
     */
    public static function create(string $provider, string $username, string $apiKey): SmsProviderInterface
    {
        return match ($provider) {
            'clicksend' => new ClickSendProvider($username, $apiKey),
            // Futuros provedores:
            // 'twilio' => new TwilioProvider($username, $apiKey),
            // 'aws_sns' => new AwsSnsProvider($username, $apiKey),
            // 'zenvia' => new ZenviaProvider($username, $apiKey),
            default => throw new \InvalidArgumentException("Provedor SMS desconhecido: {$provider}"),
        };
    }

    /**
     * Retorna lista de provedores suportados
     *
     * @return array Lista de provedores com nome e label
     */
    public static function getAvailableProviders(): array
    {
        return [
            [
                'value' => 'clicksend',
                'label' => 'ClickSend',
                'description' => 'Provedor global de SMS com precos competitivos',
            ],
            // Futuros provedores:
            // [
            //     'value' => 'twilio',
            //     'label' => 'Twilio',
            //     'description' => 'Provedor popular com API robusta',
            // ],
        ];
    }
}
