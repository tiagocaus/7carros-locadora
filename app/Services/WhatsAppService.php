<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Whatsapp;

/**
 * Service para processar envio de mensagens WhatsApp
 *
 * Usa um provedor externo de WhatsApp (configurado em WHATSAPP_API_*).
 * A URL e o admin token sao centralizadas (ENV), mas o token de instancia
 * e resolvido por tenant (vem da tabela whatsapp.instanceName).
 *
 * Resolucao de instancia:
 * 1. _system_message = true → usa WHATSAPP_API_INSTANCE_TOKEN do ENV
 * 2. id_matriz_filial → busca Whatsapp::buscarConectadaPorFilial()
 *    - Encontrou instancia conectada → usa instanceName do tenant
 *    - Nao encontrou → FALHA (nao envia)
 * 3. Sem id_matriz_filial → FALHA
 */
class WhatsAppService
{
    private string $baseUrl;
    private string $systemToken;

    public function __construct()
    {
        $this->baseUrl = Database::env('WHATSAPP_API_URL', '');
        $this->systemToken = Database::env('WHATSAPP_API_INSTANCE_TOKEN', '');
    }

    /**
     * Processa e envia uma mensagem WhatsApp
     *
     * @param array $payload Dados da mensagem:
     *   - 'to': Numero do telefone do destinatario (obrigatorio)
     *   - 'message': Mensagem de texto (obrigatorio, ou media_url)
     *   - 'media_url': URL do arquivo de midia (opcional)
     *   - 'caption': Legenda para midia (opcional)
     *   - 'id_matriz_filial': ID da filial para resolver instancia (obrigatorio para tenant)
     *   - '_system_message': Se true, usa instancia do ENV (opcional)
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function send(array $payload): array
    {
        if (empty($payload['to'])) {
            throw new \InvalidArgumentException("Campo 'to' e obrigatorio");
        }

        if (empty($payload['message']) && empty($payload['media_url'])) {
            throw new \InvalidArgumentException("Campo 'message' ou 'media_url' e obrigatorio");
        }

        if (empty($this->baseUrl)) {
            return [
                'success' => false,
                'message' => 'WHATSAPP_API_URL nao configurada',
                'retryable' => true,
            ];
        }

        $instanceToken = $this->resolveInstance($payload);
        if ($instanceToken === null) {
            return [
                'success' => false,
                'message' => 'Nenhuma instancia WhatsApp conectada para esta filial',
                'retryable' => true,
            ];
        }

        try {
            if (!empty($payload['media_url'])) {
                return $this->sendMedia($payload, $instanceToken);
            }

            return $this->sendText($payload, $instanceToken);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Resultado do envio WhatsApp nao confirmado',
                'uncertain' => true,
            ];
        }
    }

    /**
     * Resolve o token da instancia para envio.
     *
     * Prioridade:
     * 1. _system_message → WHATSAPP_API_INSTANCE_TOKEN do ENV
     * 2. id_matriz_filial → instanceName da conexao do tenant (usado como token)
     * 3. Nenhum → null (falha)
     */
    private function resolveInstance(array $payload): ?string
    {
        if (!empty($payload['_system_message'])) {
            return $this->systemToken !== '' ? $this->systemToken : null;
        }

        if (!empty($payload['id_matriz_filial'])) {
            $whatsappModel = new Whatsapp();
            $connection = $whatsappModel->buscarConectadaPorFilial((int) $payload['id_matriz_filial']);

            return $connection ? $connection['instanceName'] : null;
        }

        return null;
    }

    /**
     * Envia mensagem de texto.
     */
    private function sendText(array $payload, string $instanceToken): array
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/send/text';

        return $this->sendWithPhoneFallback($url, $instanceToken, (string) $payload['to'], [
            'Body' => $payload['message'],
        ], 'Mensagem WhatsApp enviada com sucesso');
    }

    /**
     * Envia midia (imagem ou documento).
     *
     * Baixa o arquivo da URL fornecida, converte para base64 e usa o endpoint
     * adequado conforme o mime-type detectado.
     */
    private function sendMedia(array $payload, string $instanceToken): array
    {
        $mediaUrl = $payload['media_url'];
        $download = $this->downloadMedia($mediaUrl);

        if (!$download['success']) {
            return [
                'success' => false,
                'message' => 'Erro ao baixar midia: ' . $download['message'],
                'retryable' => true,
            ];
        }

        $mime = $download['mime'];
        $base64 = base64_encode($download['body']);
        $dataUri = "data:{$mime};base64,{$base64}";

        $isImage = str_starts_with($mime, 'image/');

        if ($isImage) {
            $url = rtrim($this->baseUrl, '/') . '/chat/send/image';
            $data = [
                'Image' => $dataUri,
            ];
            if (!empty($payload['caption'])) {
                $data['Caption'] = $payload['caption'];
            }
        } else {
            $url = rtrim($this->baseUrl, '/') . '/chat/send/document';
            $data = [
                'Document' => $dataUri,
                'FileName' => $download['filename'],
            ];
        }

        return $this->sendWithPhoneFallback(
            $url,
            $instanceToken,
            (string) $payload['to'],
            $data,
            'Midia WhatsApp enviada com sucesso'
        );
    }

    /**
     * O fallback consulta candidatos antes da entrega; nunca envia duas variantes.
     * Texto, imagem e documento usam a mesma resolucao e a mesma instancia.
     */
    private function sendWithPhoneFallback(
        string $url,
        string $instanceToken,
        string $telefone,
        array $data,
        string $successMessage
    ): array {
        try {
            $resolved = $this->resolvePhone($instanceToken, self::gerarTelefonesCandidatos($telefone));
        } catch (\Throwable $e) {
            return $this->failure('lookup', 'exception', [], true);
        }
        if (!$resolved['success']) {
            return $resolved;
        }

        $data['Phone'] = $resolved['phone'];
        try {
            $response = $this->makeRequest($url, $instanceToken, $data);
        } catch (\Throwable $e) {
            return $this->failure('send', 'exception', [], false, true);
        }
        if ($response['http_code'] === 200 && empty($response['curl_errno'])
            && is_array($response['body']) && ($response['body']['success'] ?? null) === true) {
            return ['success' => true, 'message' => $successMessage, 'data' => $response['body']];
        }
        if (!empty($response['retryable'])) {
            return $this->failure('send', 'connection_not_established', $response, true);
        }
        // Uma rejeicao explicita encerra; nunca tenta outro numero apos o POST de envio.
        $body = is_array($response['body']) ? json_encode($response['body']) : (string) $response['body'];
        $invalidPhone = empty($response['curl_errno']) && in_array($response['http_code'], [400, 422], true)
            && preg_match('/not (?:on whatsapp|registered)|invalid (?:phone|number|jid)/i', $body);
        return $this->failure('send', $invalidPhone ? 'number_rejected' : 'unconfirmed_response',
            $response, false, !$invalidPhone);
    }

    /** Consulta sem enviar. Valida o telefone consultado antes de aceitar seu JID ou LID. */
    private function resolvePhone(string $instanceToken, array $candidates): array
    {
        $url = rtrim($this->baseUrl, '/') . '/user/check';
        foreach ($candidates as $candidate) {
            $response = $this->makeRequest($url, $instanceToken, ['Phone' => [$candidate]]);
            $body = $response['body'];
            if ($response['http_code'] !== 200 || !empty($response['curl_errno']) || !is_array($body)
                || (array_key_exists('success', $body) && $body['success'] !== true)) {
                return $this->failure('lookup', 'invalid_response', $response, true);
            }
            $users = $body['data']['Users'] ?? $body['Users']
                ?? $body['data']['users'] ?? $body['users'] ?? null;
            // Uma consulta por vez: vazio, duplicado ou ambiguo nunca vira envio.
            if (!is_array($users) || count($users) !== 1 || !is_array(reset($users))) {
                return $this->failure('lookup', 'invalid_response', $response, true);
            }
            $user = reset($users);
            $query = $user['Query'] ?? $user['query'] ?? null;
            $exists = $user['IsInWhatsapp'] ?? $user['isInWhatsapp'] ?? $user['is_in_whatsapp'] ?? null;
            $jid = $user['JID'] ?? $user['jid'] ?? '';
            if (!is_string($query) || ltrim($query, '+') !== $candidate) {
                return $this->failure('lookup', 'recipient_mismatch', $response, true);
            }
            if (in_array($exists, [false, 0, 'false', '0'], true)) {
                if ($jid !== '' && $jid !== null) {
                    return $this->failure('lookup', 'invalid_response', $response, true);
                }
                continue;
            }
            if (!in_array($exists, [true, 1, 'true', '1'], true) || !is_string($jid)) {
                return $this->failure('lookup', 'recipient_mismatch', $response, true);
            }
            // LID e um identificador opaco, nao um telefone. A associacao vem da
            // resposta unica com Query validada acima, na mesma instancia do envio.
            // Preservar @lid: remover o sufixo faria a API interpreta-lo como telefone.
            if (preg_match('/^[1-9][0-9]*@lid$/D', $jid)) {
                return ['success' => true, 'phone' => $jid];
            }
            if (!preg_match('/^([0-9]{7,15})@(?:s\.whatsapp\.net|c\.us)$/D', $jid, $matches)
                || !in_array($matches[1], $candidates, true)) {
                return $this->failure('lookup', 'recipient_mismatch', $response, true);
            }
            return ['success' => true, 'phone' => $matches[1]];
        }
        return $this->failure('lookup', 'number_not_registered', $response ?? [], false);
    }

    /** Somente codigos controlados; nunca retorna resposta bruta em falhas. */
    private function failure(string $stage, string $reason, array $response, bool $retryable, bool $uncertain = false): array
    {
        return [
            'success' => false, 'retryable' => $retryable, 'uncertain' => $uncertain,
            'message' => ($stage === 'lookup' ? 'Falha na consulta WhatsApp: ' : 'Falha no envio WhatsApp: ') . $reason,
            'diagnostic' => [
                'stage' => $stage, 'reason' => $reason,
                'http_code' => (int) ($response['http_code'] ?? 0),
                'curl_errno' => (int) ($response['curl_errno'] ?? 0),
            ],
        ];
    }

    /**
     * Baixa o conteudo da midia da URL e detecta mime/filename.
     *
     * @return array ['success'=>bool, 'body'=>string, 'mime'=>string, 'filename'=>string, 'message'=>string]
     */
    protected function downloadMedia(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300 || $body === false) {
            return [
                'success' => false,
                'message' => $error ?: "HTTP {$httpCode}",
            ];
        }

        $mime = trim(explode(';', $contentType)[0] ?? '');
        if ($mime === '' || $mime === 'application/octet-stream') {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
            if ($detected) {
                $mime = $detected;
            }
        }
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        $filename = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'arquivo';

        return [
            'success' => true,
            'body' => $body,
            'mime' => $mime,
            'filename' => $filename,
        ];
    }

    /**
     * Faz requisicao HTTP autenticada com o token da instancia.
     */
    protected function makeRequest(string $url, string $instanceToken, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instanceToken,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            return ['http_code' => $httpCode, 'body' => null, 'curl_errno' => $errno,
                'retryable' => in_array($errno, [CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true)];
        }

        return [
            'http_code' => $httpCode,
            'curl_errno' => 0,
            'body' => json_decode($body, true) ?? $body,
        ];
    }

    /**
     * Gera telefones candidatos para envio.
     *
     * O cadastro ja salva telefone internacionalizado. A unica regra adicional
     * aqui e compatibilidade com WhatsApp no Brasil: alguns numeros so existem
     * sem o nono digito na API do provedor.
     *
     * @return array<int,string>
     */
    public static function gerarTelefonesCandidatos(string $telefone): array
    {
        $international = str_starts_with(trim($telefone), '+');
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        if (!$international && (strlen($telefone) === 10 || strlen($telefone) === 11)) {
            $telefone = '55' . $telefone;
        }

        $telefones = [$telefone];

        if (strlen($telefone) === 13 && str_starts_with($telefone, '55') && $telefone[4] === '9') {
            $telefones[] = substr($telefone, 0, 4) . substr($telefone, 5);
        }

        return array_values(array_unique(array_filter($telefones)));
    }
}
