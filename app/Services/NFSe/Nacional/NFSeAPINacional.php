<?php

namespace App\Services\NFSe\Nacional;

use App\Services\NFSe\NFSeAPIInterface;
use App\Services\NFSe\NFSeErros;

/**
 * Comunicacao com API Nacional SEFIN via REST + mTLS
 *
 * URLs:
 * - Producao: https://sefin.nfse.gov.br/SefinNacional
 * - Homologacao: https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional
 *
 * Timeouts: conexao 30s, requisicao 60s
 */
class NFSeAPINacional implements NFSeAPIInterface
{
    private const URL_PRODUCAO = 'https://sefin.nfse.gov.br/SefinNacional';
    private const URL_HOMOLOGACAO = 'https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional';

    private const TIMEOUT_CONEXAO = 30;
    private const TIMEOUT_REQUISICAO = 60;

    public function enviar(string $xml, string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente) . '/nfse';

        // Preparar payload (gzip + base64)
        $xmlNacional = new NFSeXMLNacional();
        $dpsXmlGZipB64 = $xmlNacional->prepararParaEnvio($xml);

        $payload = json_encode(['dpsXmlGZipB64' => $dpsXmlGZipB64]);

        return $this->request('POST', $url, $certPath, $keyPath, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente) . '/nfse/' . urlencode($chaveAcesso);

        return $this->request('GET', $url, $certPath, $keyPath, null, [
            'Accept: application/json',
        ]);
    }

    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente) . '/nfse/' . urlencode($chaveAcesso) . '/cancelar';

        $xmlNacional = new NFSeXMLNacional();
        $gzipB64 = $xmlNacional->prepararParaEnvio($xml);

        $payload = json_encode(['pedidoCancelamentoXmlGZipB64' => $gzipB64]);

        return $this->request('POST', $url, $certPath, $keyPath, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente);

        $result = $this->request('GET', $url, $certPath, $keyPath, null, [
            'Accept: application/json',
        ]);

        if ($result['sucesso']) {
            return ['sucesso' => true, 'mensagem' => 'Conexão com SEFIN Nacional estabelecida com sucesso.'];
        }

        return ['sucesso' => false, 'mensagem' => $result['erro'] ?? 'Falha na conexão.'];
    }

    /**
     * Executa requisicao HTTP com mTLS
     */
    private function request(string $method, string $url, string $certPath, string $keyPath, ?string $payload, array $headers): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLKEY => $keyPath,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXAO,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUISICAO,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            $codigoErro = $this->mapearErroCurl($curlErrno);
            return [
                'sucesso' => false,
                'resposta' => '',
                'httpCode' => 0,
                'erro' => NFSeErros::getMensagem($codigoErro),
                'codigoErro' => $codigoErro,
            ];
        }

        $sucesso = $httpCode >= 200 && $httpCode < 300;

        return [
            'sucesso' => $sucesso,
            'resposta' => $response ?: '',
            'httpCode' => $httpCode,
        ];
    }

    private function getBaseUrl(int $ambiente): string
    {
        return $ambiente === 1 ? self::URL_PRODUCAO : self::URL_HOMOLOGACAO;
    }

    private function mapearErroCurl(int $errno): string
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEOUTED => 'CONN_TIMEOUT',
            CURLE_COULDNT_CONNECT => 'CONN_REFUSED',
            CURLE_SSL_CONNECT_ERROR,
            CURLE_SSL_CERTPROBLEM,
            CURLE_SSL_CACERT => 'CONN_SSL',
            default => 'CONN_CURL',
        };
    }
}
