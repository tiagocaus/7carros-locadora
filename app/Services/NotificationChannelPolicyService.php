<?php

namespace App\Services;

use App\Models\MatrizFilial;

/**
 * Aplica o bloqueio mestre de notificacoes da matriz/filial.
 */
class NotificationChannelPolicyService
{
    private const CHANNEL_FIELDS = [
        'email' => 'notificacao_email',
        'sms' => 'notificacao_sms',
        'whatsapp' => 'notificacao_whatsapp',
    ];

    public function __construct(private ?MatrizFilial $matrizFilial = null)
    {
        $this->matrizFilial ??= new MatrizFilial();
    }

    /**
     * @return array{allowed:bool,message:string,id_matriz_filial:?int}
     */
    public function evaluate(
        string $channel,
        array $payload,
        ?string $chave = null,
        bool $allowLegacyMatrixFallback = false
    ): array {
        if (!isset(self::CHANNEL_FIELDS[$channel])) {
            return [
                'allowed' => false,
                'message' => "Canal de notificacao invalido: {$channel}",
                'id_matriz_filial' => null,
            ];
        }

        if ($this->isBypassAllowed($payload)) {
            return [
                'allowed' => true,
                'message' => '',
                'id_matriz_filial' => isset($payload['id_matriz_filial'])
                    ? (int) $payload['id_matriz_filial']
                    : null,
            ];
        }

        $idMatrizFilial = (int) ($payload['id_matriz_filial'] ?? 0);
        $empresa = null;

        if ($idMatrizFilial > 0) {
            $empresa = $this->matrizFilial->buscarConfiguracoesNotificacao($idMatrizFilial, $chave);
        } elseif ($allowLegacyMatrixFallback && !empty($chave)) {
            $empresa = $this->matrizFilial->buscarConfiguracoesNotificacaoMatriz($chave);
            $idMatrizFilial = (int) ($empresa['id'] ?? 0);
        }

        if ($idMatrizFilial <= 0) {
            return [
                'allowed' => false,
                'message' => "Empresa/filial nao informada para envio por {$channel}",
                'id_matriz_filial' => null,
            ];
        }

        if (!$empresa) {
            return [
                'allowed' => false,
                'message' => 'Empresa/filial nao encontrada para validar o envio',
                'id_matriz_filial' => $idMatrizFilial,
            ];
        }

        $field = self::CHANNEL_FIELDS[$channel];
        if (($empresa[$field] ?? 'N') !== 'S') {
            $channelLabel = match ($channel) {
                'email' => 'e-mail',
                'sms' => 'SMS',
                'whatsapp' => 'WhatsApp',
            };
            $empresaLabel = trim((string) ($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? ''));
            $suffix = $empresaLabel !== '' ? " ({$empresaLabel})" : '';

            return [
                'allowed' => false,
                'message' => "Envio por {$channelLabel} desativado nas configuracoes desta empresa/filial{$suffix}",
                'id_matriz_filial' => $idMatrizFilial,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'id_matriz_filial' => $idMatrizFilial,
        ];
    }

    public function assertAllowed(string $channel, array $payload, ?string $chave = null): void
    {
        $decision = $this->evaluate($channel, $payload, $chave);
        if (!$decision['allowed']) {
            throw new \InvalidArgumentException($decision['message']);
        }
    }

    private function isBypassAllowed(array $payload): bool
    {
        return ($payload['_company_channel_bypass'] ?? '') === 'platform'
            || ($payload['_email_preference_bypass'] ?? '') === 'cliente_password_reset';
    }
}
