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

        return $this->request('POST', $url, $certPath, $keyPath, (int) $ambiente, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente) . '/nfse/' . urlencode($chaveAcesso);

        return $this->request('GET', $url, $certPath, $keyPath, (int) $ambiente, null, [
            'Accept: application/json',
        ]);
    }

    /**
     * Recupera a chave de acesso da NFS-e gerada a partir de uma DPS.
     * Endpoint oficial: GET /dps/{id}.
     */
    public function consultarPorDps(string $idDPS, string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente) . '/dps/' . urlencode($idDPS);

        return $this->request('GET', $url, $certPath, $keyPath, $ambiente, null, [
            'Accept: application/json',
        ]);
    }

    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente) . '/nfse/' . urlencode($chaveAcesso) . '/eventos';

        $xmlNacional = new NFSeXMLNacional();
        $gzipB64 = $xmlNacional->prepararParaEnvio($xml);

        $payload = json_encode(['pedidoRegistroEventoXmlGZipB64' => $gzipB64]);

        return $this->request('POST', $url, $certPath, $keyPath, (int) $ambiente, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array
    {
        $url = $this->getBaseUrl($ambiente);

        $result = $this->request('GET', $url, $certPath, $keyPath, (int) $ambiente, null, [
            'Accept: application/json',
        ]);

        if ($result['sucesso']) {
            return [
                'sucesso' => true,
                'mensagem' => 'Conexão com SEFIN Nacional estabelecida com sucesso.',
                'diagnostico' => $result['diagnostico'] ?? [],
            ];
        }

        if (($result['httpCode'] ?? 0) > 0) {
            return [
                'sucesso' => true,
                'mensagem' => 'Conexão com SEFIN Nacional estabelecida.',
                'diagnostico' => $result['diagnostico'] ?? [],
            ];
        }

        return [
            'sucesso' => false,
            'mensagem' => $result['erro'] ?? 'Falha na conexão.',
            'diagnostico' => $result['diagnostico'] ?? [],
        ];
    }

    /**
     * Executa requisicao HTTP com mTLS
     */
    private function request(string $method, string $url, string $certPath, string $keyPath, int $ambiente, ?string $payload, array $headers): array
    {
        $ch = curl_init();
        $caBundlePath = $this->getCaBundlePath();

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLKEY => $keyPath,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXAO,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUISICAO,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($caBundlePath !== null) {
            $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
        }

        curl_setopt_array($ch, $curlOptions);

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
            $diagnostico = $this->diagnosticoCurl($url, $ambiente, $curlErrno, $curlError, 0, $caBundlePath !== null);

            return [
                'sucesso' => false,
                'resposta' => '',
                'httpCode' => 0,
                'erro' => NFSeErros::getMensagem($codigoErro),
                'codigoErro' => $codigoErro,
                'erroTecnico' => $diagnostico['curl_error'],
                'diagnostico' => $diagnostico,
            ];
        }

        $sucesso = $httpCode >= 200 && $httpCode < 300;

        return [
            'sucesso' => $sucesso,
            'resposta' => $response ?: '',
            'httpCode' => $httpCode,
            'diagnostico' => [
                'emissor' => 'nacional',
                'ambiente' => $ambiente,
                'url' => $url,
                'httpCode' => $httpCode,
                'caBundleLocal' => $caBundlePath !== null,
            ],
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
            CURLE_SSL_CACERT_BADFILE,
            CURLE_SSL_CACERT => 'CONN_SSL',
            default => 'CONN_CURL',
        };
    }

    private function getCaBundlePath(): ?string
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 4);
        $path = $appRoot . '/storage/certificates/certs/cacert.pem';

        return is_readable($path) ? $path : null;
    }

    private function diagnosticoCurl(string $url, int $ambiente, int $errno, string $error, int $httpCode, bool $caBundleLocal): array
    {
        return [
            'emissor' => 'nacional',
            'ambiente' => $ambiente,
            'url' => $url,
            'httpCode' => $httpCode,
            'curl_errno' => $errno,
            'curl_error' => $this->sanitizarErroCurl($error),
            'categoria' => $this->mapearErroCurl($errno),
            'caBundleLocal' => $caBundleLocal,
        ];
    }

    private function sanitizarErroCurl(string $error): string
    {
        $error = trim($error);
        if ($error === '') {
            return '';
        }

        $tmp = preg_quote(sys_get_temp_dir(), '/');
        $error = preg_replace('/' . $tmp . '\/nfse_(cert|key)_[A-Za-z0-9_.-]+\.pem/', '[arquivo-temporario-pem]', $error) ?? $error;

        return mb_substr($error, 0, 500);
    }
}
