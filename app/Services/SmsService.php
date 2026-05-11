<?php

namespace App\Services;

use App\Models\Sms;
use App\Services\Sms\SmsProviderFactory;

/**
 * Service para processar envio de mensagens SMS
 *
 * Usado pelo sistema de mensageria (RabbitMQ) para enviar SMS
 * de forma assincrona.
 */
class SmsService
{
    /**
     * Processa e envia uma mensagem SMS
     *
     * @param array $payload Dados da mensagem:
     *   - 'to': Numero do telefone do destinatario (obrigatorio)
     *   - 'message': Mensagem de texto (obrigatorio)
     *   - 'id_matriz_filial': ID da filial (obrigatorio para buscar conexao)
     *   - 'chave': Chave do tenant (opcional, usado como fallback)
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     * @throws \InvalidArgumentException Se dados obrigatorios estiverem faltando
     */
    public function send(array $payload): array
    {
        // Valida dados obrigatorios
        if (empty($payload['to'])) {
            throw new \InvalidArgumentException("Campo 'to' e obrigatorio");
        }

        if (empty($payload['message'])) {
            throw new \InvalidArgumentException("Campo 'message' e obrigatorio");
        }

        if (empty($payload['id_matriz_filial'])) {
            throw new \InvalidArgumentException("Campo 'id_matriz_filial' e obrigatorio");
        }

        try {
            // Buscar conexao SMS validada para a filial
            $smsModel = new Sms();
            $smsConnection = $smsModel->buscarValidadaPorFilial((int) $payload['id_matriz_filial']);

            if (!$smsConnection) {
                return [
                    'success' => false,
                    'message' => 'Nenhuma conexao SMS configurada ou validada para esta filial',
                ];
            }

            // Descriptografar API key
            $apiKey = decrypt($smsConnection['api_key']);

            // Criar provedor
            $provider = SmsProviderFactory::create(
                $smsConnection['provider'],
                $smsConnection['username'],
                $apiKey
            );

            // Formatar telefone
            $telefone = $this->formatarTelefone($payload['to']);

            // Enviar
            $result = $provider->send(
                $telefone,
                $payload['message'],
                $smsConnection['sender_id']
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'SMS enviado com sucesso',
                    'data' => [
                        'to' => $telefone,
                        'sender_id' => $smsConnection['sender_id'],
                        'provider' => $smsConnection['provider'],
                        'api_response' => $result['data'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Erro ao enviar SMS: ' . ($result['message'] ?? 'Erro desconhecido'),
                'data' => $result['data'] ?? null,
            ];
        } catch (\Exception $e) {
            error_log("Erro ao enviar SMS: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar SMS: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Formata numero de telefone para formato internacional
     *
     * Remove caracteres nao numericos e adiciona codigo do pais se necessario
     *
     * @param string $telefone Numero de telefone
     * @return string Numero formatado
     */
    private function formatarTelefone(string $telefone): string
    {
        // Remove caracteres nao numericos
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        // Adiciona codigo do pais (55 = Brasil) se nao tiver
        if (strlen($telefone) === 11 || strlen($telefone) === 10) {
            $telefone = '55' . $telefone;
        }

        return $telefone;
    }
}
