<?php

namespace App\Services\Split;

/**
 * Null Object para gateways que nao suportam split.
 * Retorna respostas negativas sem lancar excecoes.
 */
class NullSplitService implements SplitServiceInterface
{
    private string $gatewayCode;

    public function __construct(string $gatewayCode)
    {
        $this->gatewayCode = $gatewayCode;
    }

    public function configurarSplit(string $externalId, array $splits): array
    {
        return [
            'success' => false,
            'message' => "Gateway '{$this->gatewayCode}' nao suporta split",
            'raw' => []
        ];
    }

    public function removerSplit(string $externalId): array
    {
        return [
            'success' => false,
            'message' => "Gateway '{$this->gatewayCode}' nao suporta split"
        ];
    }

    public function consultarSplit(string $externalId): array
    {
        return [
            'success' => false,
            'data' => []
        ];
    }

    public function suportaSplit(): bool
    {
        return false;
    }
}
