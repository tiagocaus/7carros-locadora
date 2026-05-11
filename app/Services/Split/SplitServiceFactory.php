<?php

namespace App\Services\Split;

/**
 * Factory para criar instancias de split service por gateway.
 * Gateways nao suportados retornam NullSplitService.
 */
class SplitServiceFactory
{
    /** @var array<string, class-string<SplitServiceInterface>> */
    private static array $services = [
        'asaas' => AsaasSplitService::class,
    ];

    /**
     * Cria instancia do split service para o gateway informado
     *
     * @param string $gatewayCode Codigo do gateway (asaas, stripe, etc)
     * @param array $credentials Credenciais do gateway
     * @param bool $sandbox Modo sandbox
     * @return SplitServiceInterface
     */
    public static function create(string $gatewayCode, array $credentials = [], bool $sandbox = false): SplitServiceInterface
    {
        $code = strtolower($gatewayCode);

        if (!isset(self::$services[$code])) {
            return new NullSplitService($code);
        }

        $class = self::$services[$code];
        return new $class($credentials, $sandbox);
    }

    /**
     * Verifica se um gateway suporta split
     */
    public static function supports(string $gatewayCode): bool
    {
        return isset(self::$services[strtolower($gatewayCode)]);
    }
}
