<?php

namespace App\Services\Sms;

/**
 * Interface para provedores de SMS
 *
 * Define o contrato que todos os provedores de SMS devem implementar,
 * permitindo facilmente adicionar novos provedores no futuro.
 */
interface SmsProviderInterface
{
    /**
     * Envia uma mensagem SMS
     *
     * @param string $to Numero de telefone do destinatario
     * @param string $message Conteudo da mensagem
     * @param string $senderId ID do remetente (max 11 chars alfanumericos)
     * @return array ['success' => bool, 'message' => string, 'data' => ?array]
     */
    public function send(string $to, string $message, string $senderId): array;

    /**
     * Valida as credenciais do provedor
     *
     * Verifica se username e api_key sao validos consultando a API
     *
     * @return array ['success' => bool, 'message' => string, 'balance' => ?float]
     */
    public function validateCredentials(): array;

    /**
     * Retorna o saldo disponivel na conta
     *
     * @return array ['success' => bool, 'balance' => float, 'currency' => string, 'message' => ?string]
     */
    public function getBalance(): array;
}
