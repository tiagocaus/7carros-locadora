<?php

namespace App\Services\Sms;

use ClickSend\Configuration;
use ClickSend\Api\SMSApi;
use ClickSend\Api\AccountApi;
use ClickSend\Model\SmsMessage;
use ClickSend\Model\SmsMessageCollection;
use GuzzleHttp\Client;

/**
 * Provedor SMS ClickSend
 *
 * Implementa a integracao com a API ClickSend para envio de SMS
 *
 * @see https://developers.clicksend.com/docs/rest/v3/
 */
class ClickSendProvider implements SmsProviderInterface
{
    private string $username;
    private string $apiKey;
    private Configuration $config;

    /**
     * @param string $username Email/username da conta ClickSend
     * @param string $apiKey API Key da conta ClickSend
     */
    public function __construct(string $username, string $apiKey)
    {
        $this->username = $username;
        $this->apiKey = $apiKey;

        $this->config = Configuration::getDefaultConfiguration()
            ->setUsername($username)
            ->setPassword($apiKey);
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $to, string $message, string $senderId): array
    {
        try {
            $apiInstance = new SMSApi(new Client(), $this->config);

            $msg = new SmsMessage();
            $msg->setBody($message);
            $msg->setTo($this->formatPhone($to));
            $msg->setSource('7carros');
            $msg->setFrom($senderId);

            $smsCollection = new SmsMessageCollection();
            $smsCollection->setMessages([$msg]);

            $result = $apiInstance->smsSendPost($smsCollection);
            $data = json_decode($result, true);

            if (isset($data['response_code']) && $data['response_code'] === 'SUCCESS') {
                return [
                    'success' => true,
                    'message' => 'SMS enviado com sucesso',
                    'data' => $data,
                ];
            }

            $errorMessage = $data['response_msg'] ?? 'Erro desconhecido';

            // Tratar erros especificos
            if (isset($data['data']['messages'][0]['status'])) {
                $status = $data['data']['messages'][0]['status'];
                if ($status === 'INVALID_RECIPIENT') {
                    $errorMessage = 'Numero de telefone invalido';
                } elseif ($status === 'INSUFFICIENT_CREDIT') {
                    $errorMessage = 'Creditos insuficientes na conta ClickSend';
                }
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao enviar SMS: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(): array
    {
        try {
            $result = $this->getBalance();

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Credenciais validadas com sucesso',
                    'balance' => $result['balance'],
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Credenciais invalidas',
                'balance' => null,
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Tratar erro de autenticacao
            if (strpos($errorMessage, '401') !== false || strpos($errorMessage, 'Unauthorized') !== false) {
                $errorMessage = 'Credenciais invalidas. Verifique username e API Key.';
            }

            return [
                'success' => false,
                'message' => 'Erro ao validar credenciais: ' . $errorMessage,
                'balance' => null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(): array
    {
        try {
            $apiInstance = new AccountApi(new Client(), $this->config);
            $result = $apiInstance->accountGet();
            $data = json_decode($result, true);

            if (isset($data['data']['balance'])) {
                return [
                    'success' => true,
                    'balance' => (float) $data['data']['balance'],
                    'currency' => $data['data']['currency']['currency_name_short'] ?? 'USD',
                ];
            }

            return [
                'success' => false,
                'balance' => 0,
                'currency' => 'USD',
                'message' => 'Nao foi possivel obter o saldo',
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Tratar erro de autenticacao
            if (strpos($errorMessage, '401') !== false || strpos($errorMessage, 'Unauthorized') !== false) {
                $errorMessage = 'Credenciais invalidas';
            }

            return [
                'success' => false,
                'balance' => 0,
                'currency' => 'USD',
                'message' => $errorMessage,
            ];
        }
    }

    /**
     * Formata numero de telefone para formato internacional
     *
     * @param string $phone Numero de telefone
     * @return string Numero formatado com codigo do pais
     */
    private function formatPhone(string $phone): string
    {
        // Remove caracteres nao numericos
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Adiciona codigo do pais Brasil se nao tiver
        if (strlen($phone) === 11 || strlen($phone) === 10) {
            $phone = '+55' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            // Se ja tem codigo do pais mas sem +
            if (strlen($phone) >= 12) {
                $phone = '+' . $phone;
            } else {
                // Assume Brasil
                $phone = '+55' . $phone;
            }
        }

        return $phone;
    }
}
