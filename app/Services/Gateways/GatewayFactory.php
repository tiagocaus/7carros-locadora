<?php

namespace App\Services\Gateways;

/**
 * Factory para criação de instâncias de gateways de pagamento
 *
 * Centraliza a criação de gateways e fornece lista de gateways disponíveis.
 */
class GatewayFactory
{
    /**
     * Mapeamento de código para classe do gateway
     *
     * @var array<string, class-string<PaymentGatewayInterface>>
     */
    private static array $gateways = [
        'asaas' => AsaasGateway::class,
        'stripe' => StripeGateway::class,
        'square' => SquareGateway::class,
        'cora' => CoraGateway::class,
        'efipay' => EfipayGateway::class,
        'inter' => InterGateway::class,
        'bradesco' => BradescoGateway::class,
        'itau' => ItauGateway::class,
        'bancard' => BancardGateway::class,
        'pagopar' => PagoparGateway::class,
    ];

    /**
     * Cria instância do gateway
     *
     * @param string $code Código do gateway (asaas, stripe, etc.)
     * @param array<string, mixed> $credentials Credenciais do gateway
     * @param bool $sandbox Se está em modo sandbox
     * @param int|null $gatewayId ID do gateway no banco
     * @return PaymentGatewayInterface
     * @throws \InvalidArgumentException Se o gateway não existir
     */
    public static function create(
        string $code,
        array $credentials,
        bool $sandbox = false,
        ?int $gatewayId = null
    ): PaymentGatewayInterface {
        $code = strtolower($code);

        if (!isset(self::$gateways[$code])) {
            throw new \InvalidArgumentException("Gateway desconhecido: {$code}");
        }

        $class = self::$gateways[$code];

        // Verificar se a classe existe
        if (!class_exists($class)) {
            throw new \RuntimeException("Classe do gateway não encontrada: {$class}");
        }

        return new $class($credentials, $sandbox, $gatewayId);
    }

    /**
     * Verifica se um gateway está registrado
     *
     * @param string $code
     * @return bool
     */
    public static function exists(string $code): bool
    {
        return isset(self::$gateways[strtolower($code)]);
    }

    /**
     * Retorna lista de gateways disponíveis com metadados
     *
     * @return array<int, array{code: string, name: string, country: string, methods: array, config_schema: array}>
     */
    public static function getAvailableGateways(): array
    {
        $list = [];

        foreach (self::$gateways as $code => $class) {
            if (!class_exists($class)) {
                continue;
            }

            try {
                /** @var PaymentGatewayInterface $instance */
                $instance = new $class([], false);

                $list[] = [
                    'code' => $instance->getCode(),
                    'name' => $instance->getName(),
                    'country' => $instance->getCountry(),
                    'methods' => $instance->getSupportedMethods(),
                    'supported_currencies' => $instance->getSupportedCurrencies(),
                    'config_schema' => $instance->getConfigSchema(),
                    'documentation_url' => $instance->getDocumentationUrl(),
                ];
            } catch (\Exception $e) {
                // Gateway com erro de inicialização
                continue;
            }
        }

        return $list;
    }

    /**
     * Retorna gateways filtrados por país
     *
     * @param string $country Código do país (BR, PY, INTL)
     * @return array<int, array<string, mixed>>
     */
    public static function getGatewaysByCountry(string $country): array
    {
        $country = strtoupper($country);

        return array_values(array_filter(
            self::getAvailableGateways(),
            function ($gateway) use ($country) {
                // INTL (internacional) funciona para qualquer país
                return $gateway['country'] === $country || $gateway['country'] === 'INTL';
            }
        ));
    }

    /**
     * Retorna gateways filtrados por método de pagamento
     *
     * @param string $method Método de pagamento (pix, boleto, credit_card, debit_card)
     * @return array<int, array<string, mixed>>
     */
    public static function getGatewaysByMethod(string $method): array
    {
        $method = strtolower($method);

        return array_values(array_filter(
            self::getAvailableGateways(),
            function ($gateway) use ($method) {
                return in_array($method, $gateway['methods'], true);
            }
        ));
    }

    /**
     * Retorna lista de códigos de gateway
     *
     * @return array<string>
     */
    public static function getGatewayCodes(): array
    {
        return array_keys(self::$gateways);
    }

    /**
     * Retorna informações de um gateway específico (sem credenciais)
     *
     * @param string $code
     * @return array<string, mixed>|null
     */
    public static function getGatewayInfo(string $code): ?array
    {
        $code = strtolower($code);

        if (!isset(self::$gateways[$code])) {
            return null;
        }

        $class = self::$gateways[$code];

        if (!class_exists($class)) {
            return null;
        }

        try {
            /** @var PaymentGatewayInterface $instance */
            $instance = new $class([], false);

            return [
                'code' => $instance->getCode(),
                'name' => $instance->getName(),
                'country' => $instance->getCountry(),
                'methods' => $instance->getSupportedMethods(),
                'supported_currencies' => $instance->getSupportedCurrencies(),
                'config_schema' => $instance->getConfigSchema(),
                'documentation_url' => $instance->getDocumentationUrl(),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Registra um novo gateway (útil para extensibilidade)
     *
     * @param string $code Código único do gateway
     * @param class-string<PaymentGatewayInterface> $class Classe do gateway
     */
    public static function register(string $code, string $class): void
    {
        if (!is_subclass_of($class, PaymentGatewayInterface::class)) {
            throw new \InvalidArgumentException(
                "A classe {$class} deve implementar PaymentGatewayInterface"
            );
        }

        self::$gateways[strtolower($code)] = $class;
    }

    /**
     * Remove um gateway registrado
     *
     * @param string $code
     */
    public static function unregister(string $code): void
    {
        unset(self::$gateways[strtolower($code)]);
    }
}
