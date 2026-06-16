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

    public function enviar(string $xml, string $certPath, string $keyPath, int $ambiente): array
    {
        $body = '<RecepcionarDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">' . $this->semDeclaracaoXml($xml) . '</RecepcionarDpsEnvio>';
        return $this->soapRequest($body, 'RecepcionarDps', $certPath, $keyPath);
    }

    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        $body = '<ConsultarStatusDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">'
            . '<protocolo>' . htmlspecialchars($chaveAcesso, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</protocolo>'
            . '</ConsultarStatusDpsEnvio>';

        return $this->soapRequest($body, 'ConsultarStatusDps', $certPath, $keyPath);
    }

    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->soapRequest($this->semDeclaracaoXml($xml), 'RecepcionarEventoCancelamento', $certPath, $keyPath);
    }

    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array
    {
        $resultado = $this->soapRequest(
            '<ConsultarStatusDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps"><protocolo>0</protocolo></ConsultarStatusDpsEnvio>',
            'ConsultarStatusDps',
            $certPath,
            $keyPath
        );

        if (($resultado['httpCode'] ?? 0) > 0) {
            return ['sucesso' => true, 'mensagem' => 'Conexão com Betha Cloud estabelecida.'];
        }

        return ['sucesso' => false, 'mensagem' => $resultado['erro'] ?? 'Falha na conexão com Betha Cloud.'];
    }

    private function soapRequest(string $body, string $action, string $certPath, string $keyPath): array
    {
        $payload = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>' . $body . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $ch = curl_init();
        curl_setopt_array($ch, [
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
        ]);

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

        return [
            'sucesso' => $httpCode >= 200 && $httpCode < 300,
            'resposta' => $response ?: '',
            'httpCode' => $httpCode,
            'erro' => $httpCode >= 400 ? $curlError : null,
        ];
    }

    private function semDeclaracaoXml(string $xml): string
    {
        return trim(preg_replace('/^<\?xml[^>]*>\s*/', '', $xml) ?? $xml);
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
