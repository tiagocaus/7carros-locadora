<?php

namespace App\Services\Gateways;

/**
 * Interface para gateways que suportam authorization holds (pre-autorizacao)
 *
 * Authorization hold reserva um valor no limite do cartao do cliente
 * sem efetuar a cobranca. O hold pode ser capturado (cobrado) ou
 * liberado (cancelado) posteriormente.
 *
 * Gateways que suportam: Stripe, Square
 */
interface AuthorizationHoldInterface
{
    /**
     * Verifica se o gateway suporta authorization holds
     */
    public function supportsAuthorizationHold(): bool;

    /**
     * Cria um authorization hold (pre-autorizacao) no cartao
     *
     * @param array{
     *     chave?: string,
     *     payment_method_id: string,
     *     amount: float,
     *     currency?: string,
     *     description?: string,
     *     metadata?: array,
     *     id_financeiro?: int,
     *     extended_authorization?: bool
     * } $data
     * @return array{
     *     success: bool,
     *     message?: string,
     *     external_id?: string,
     *     status?: string,
     *     expires_at?: string,
     *     client_secret?: string,
     *     raw?: array
     * }
     */
    public function createHold(array $data): array;

    /**
     * Captura (total ou parcial) um hold autorizado
     *
     * @param string $externalId ID externo do hold (pi_xxx)
     * @param float|null $amount Valor a capturar (null = total do hold)
     * @return array{
     *     success: bool,
     *     message?: string,
     *     raw?: array
     * }
     */
    public function captureHold(string $externalId, ?float $amount = null): array;

    /**
     * Libera (cancela) um hold autorizado sem cobrar
     *
     * @param string $externalId ID externo do hold (pi_xxx)
     * @return array{
     *     success: bool,
     *     message?: string,
     *     raw?: array
     * }
     */
    public function releaseHold(string $externalId): array;

    /**
     * Consulta o status de um hold
     *
     * @param string $externalId ID externo do hold
     * @return array{
     *     success: bool,
     *     status?: string,
     *     amount?: float,
     *     captured_amount?: float,
     *     expires_at?: string,
     *     raw?: array
     * }
     */
    public function getHoldStatus(string $externalId): array;
}
