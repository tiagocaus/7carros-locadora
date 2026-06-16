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
            ];
        }

        $instanceToken = $this->resolveInstance($payload);
        if ($instanceToken === null) {
            return [
                'success' => false,
                'message' => 'Nenhuma instancia WhatsApp conectada para esta filial',
            ];
        }

        try {
            if (!empty($payload['media_url'])) {
                return $this->sendMedia($payload, $instanceToken);
            }

            return $this->sendText($payload, $instanceToken);
        } catch (\Exception $e) {
            error_log("Erro ao enviar WhatsApp: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar WhatsApp: ' . $e->getMessage(),
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
     * Envia para o telefone original e, se o provedor falhar, tenta a variante
     * brasileira sem o nono digito. Nao duplica envio quando a primeira tentativa
     * tem sucesso.
     */
    private function sendWithPhoneFallback(
        string $url,
        string $instanceToken,
        string $telefone,
        array $data,
        string $successMessage
    ): array {
        $telefones = self::gerarTelefonesCandidatos($telefone);
        $lastResponse = null;

        foreach ($telefones as $phone) {
            $payload = $data;
            $payload['Phone'] = $phone;

            $response = $this->makeRequest($url, $instanceToken, $payload);
            $lastResponse = $response;

            if ($response['http_code'] === 200) {
                return [
                    'success' => true,
                    'message' => $successMessage,
                    'data' => $response['body'],
                ];
            }
        }

        $httpCode = $lastResponse['http_code'] ?? 0;

        return [
            'success' => false,
            'message' => "Erro na API WhatsApp: HTTP {$httpCode}. Telefones tentados: " . implode(', ', $telefones),
            'data' => $lastResponse['body'] ?? null,
        ];
    }

    /**
     * Baixa o conteudo da midia da URL e detecta mime/filename.
     *
     * @return array ['success'=>bool, 'body'=>string, 'mime'=>string, 'filename'=>string, 'message'=>string]
     */
    private function downloadMedia(string $url): array
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
    private function makeRequest(string $url, string $instanceToken, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instanceToken,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Erro cURL: {$error}");
        }

        return [
            'http_code' => $httpCode,
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
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        if (strlen($telefone) === 10 || strlen($telefone) === 11) {
            $telefone = '55' . $telefone;
        }

        $telefones = [$telefone];

        if (strlen($telefone) === 13 && str_starts_with($telefone, '55') && $telefone[4] === '9') {
            $telefones[] = substr($telefone, 0, 4) . substr($telefone, 5);
        }

        return array_values(array_unique(array_filter($telefones)));
    }
}
