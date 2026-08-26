<?php

namespace App\Services\NFSe\Betha;

use App\Services\NFSe\NFSeAPIInterface;
use App\Services\NFSe\NFSeErros;

/**
 * Comunicacao com Betha Cloud DPS via SOAP 1.1 + mTLS.
 */
class NFSeAPIBetha implements NFSeAPIInterface
{
    private const URL = 'https://nota-eletronica.betha.cloud/dps/ws';
    private const TIMEOUT_CONEXAO = 30;
    private const TIMEOUT_REQUISICAO = 90;
    private const TIPO_INTEGRACAO_EMISSAO = 'EMISSAO';
    private const TIPOS_INTEGRACAO = [
        'EMISSAO',
        'CANCELAMENTO',
        'CANCELAMENTO_POR_SUBSTITUICAO',
    ];

    public function enviar(string $xml, string $certPath, string $keyPath, int $ambiente): array
    {
        $body = '<RecepcionarDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">' . $this->semDeclaracaoXml($xml) . '</RecepcionarDpsEnvio>';
        return $this->soapRequest($body, 'RecepcionarDps', $certPath, $keyPath, $ambiente);
    }

    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->consultarStatusDps($chaveAcesso, '', '', $certPath, $keyPath, $ambiente);
    }

    public function consultarStatusDps(
        string $protocolo,
        string $codigoIbge,
        string $cpfCnpjPrestador,
        string $certPath,
        string $keyPath,
        int $ambiente,
        string $tipoIntegracao = self::TIPO_INTEGRACAO_EMISSAO
    ): array
    {
        if (!in_array($tipoIntegracao, self::TIPOS_INTEGRACAO, true)) {
            throw new \InvalidArgumentException('Tipo de integração Betha inválido.');
        }

        $body = '<ConsultarStatusDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">'
            . '<tpAmb>' . (int) $ambiente . '</tpAmb>'
            . '<codigoIbge>' . htmlspecialchars($this->somenteDigitos($codigoIbge), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</codigoIbge>'
            . '<cpfCnpjPrestador>' . htmlspecialchars($this->somenteDigitos($cpfCnpjPrestador), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</cpfCnpjPrestador>'
            . '<protocolo>' . htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</protocolo>'
            . '<tipoIntegracao>' . $tipoIntegracao . '</tipoIntegracao>'
            . '</ConsultarStatusDpsEnvio>';

        return $this->soapRequest($body, 'ConsultarStatusDps', $certPath, $keyPath, $ambiente);
    }

    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->soapRequest($this->semDeclaracaoXml($xml), 'RecepcionarEventoCancelamento', $certPath, $keyPath, $ambiente);
    }

    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->testarConexaoMunicipio($certPath, $keyPath, $ambiente, '', '');
    }

    public function testarConexaoMunicipio(string $certPath, string $keyPath, int $ambiente, string $codigoIbge, string $cpfCnpjPrestador): array
    {
        $resultado = $this->soapRequest(
            '<ConsultarStatusDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps"><tpAmb>' . (int) $ambiente . '</tpAmb><codigoIbge>' . htmlspecialchars($this->somenteDigitos($codigoIbge), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</codigoIbge><cpfCnpjPrestador>' . htmlspecialchars($this->somenteDigitos($cpfCnpjPrestador), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</cpfCnpjPrestador><protocolo>0</protocolo><tipoIntegracao>' . self::TIPO_INTEGRACAO_EMISSAO . '</tipoIntegracao></ConsultarStatusDpsEnvio>',
            'ConsultarStatusDps',
            $certPath,
            $keyPath,
            $ambiente
        );

        if (($resultado['httpCode'] ?? 0) > 0) {
            return ['sucesso' => true, 'mensagem' => 'Conexão com Betha Cloud estabelecida.'];
        }

        return ['sucesso' => false, 'mensagem' => $resultado['erro'] ?? 'Falha na conexão com Betha Cloud.'];
    }

    private function soapRequest(string $body, string $action, string $certPath, string $keyPath, int $ambiente): array
    {
        $payload = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>' . $body . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $ch = curl_init();
        $caBundlePath = $this->getCaBundlePath();
        $curlOptions = [
            CURLOPT_URL => self::URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLKEY => $keyPath,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXAO,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUISICAO,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $action . '"',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($caBundlePath !== null) {
            $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            $codigoErro = $this->mapearErroCurl($curlErrno);
            $diagnostico = $this->diagnosticoCurl($action, $ambiente, $curlErrno, $curlError, 0, $caBundlePath !== null);

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

        return [
            'sucesso' => $httpCode >= 200 && $httpCode < 300,
            'resposta' => $response ?: '',
            'httpCode' => $httpCode,
            'erro' => $httpCode >= 400 ? $curlError : null,
            'diagnostico' => [
                'emissor' => 'betha',
                'ambiente' => $ambiente,
                'url' => self::URL,
                'soapAction' => $action,
                'httpCode' => $httpCode,
                'caBundleLocal' => $caBundlePath !== null,
            ],
        ];
    }

    private function semDeclaracaoXml(string $xml): string
    {
        return trim(preg_replace('/^<\?xml[^>]*>\s*/', '', $xml) ?? $xml);
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
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

    private function diagnosticoCurl(string $action, int $ambiente, int $errno, string $error, int $httpCode, bool $caBundleLocal): array
    {
        return [
            'emissor' => 'betha',
            'ambiente' => $ambiente,
            'url' => self::URL,
            'soapAction' => $action,
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
