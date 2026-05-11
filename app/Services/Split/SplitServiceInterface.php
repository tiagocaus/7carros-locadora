<?php

namespace App\Services\Split;

/**
 * Interface para servicos de split de pagamento.
 * Permite configurar repasse automatico ao investidor via gateway.
 */
interface SplitServiceInterface
{
    /**
     * Configura split para uma cobranca existente no gateway
     *
     * @param string $externalId ID da cobranca no gateway
     * @param array $splits [['wallet_id' => string, 'valor' => float], ...]
     * @return array ['success' => bool, 'message' => string, 'raw' => array]
     */
    public function configurarSplit(string $externalId, array $splits): array;

    /**
     * Remove split de uma cobranca
     *
     * @param string $externalId ID da cobranca no gateway
     * @return array ['success' => bool, 'message' => string]
     */
    public function removerSplit(string $externalId): array;

    /**
     * Consulta status do split
     *
     * @param string $externalId ID da cobranca no gateway
     * @return array ['success' => bool, 'data' => array]
     */
    public function consultarSplit(string $externalId): array;

    /**
     * Verifica se o gateway suporta split
     */
    public function suportaSplit(): bool;
}
