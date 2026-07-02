<?php

namespace App\Services\NFSe\ISSNet;

use App\Services\NFSe\NFSeAPIInterface;
use App\Services\NFSe\NFSeErros;

class NFSeAPIISSNet implements NFSeAPIInterface
{
    private const URL_PRODUCAO_DF = 'https://df.issnetonline.com.br/webservicenfse204/nfse.asmx';
    private const URL_HOMOLOGACAO = 'https://www.issnetonline.com.br/homologa/webservicenfse204/nfse.asmx';
    private const TIMEOUT_CONEXAO = 30;
    private const TIMEOUT_REQUISICAO = 90;

    public function __construct(private array $config = [])
    {
    }

    public function enviar(string $xml, string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->soapRequest('GerarNfse', $xml, $certPath, $keyPath, $ambiente);
    }

    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        return [
            'sucesso' => false,
            'resposta' => '',
            'httpCode' => 0,
            'erro' => 'ISSNet deve ser consultado por RPS.',
            'codigoErro' => 'NOTA_NAO_ENCONTRADA',
        ];
    }

    public function consultarPorRps(string $xml, string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->soapRequest('ConsultarNfsePorRps', $xml, $certPath, $keyPath, $ambiente);
    }

    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        return $this->soapRequest('CancelarNfse', $xml, $certPath, $keyPath, $ambiente);
    }

    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array
    {
        $resultado = $this->soapRequest(
            'ConsultarDadosCadastrais',
            '<ConsultarDadosCadastraisEnvio xmlns="http://www.abrasf.org.br/nfse.xsd"/>',
            $certPath,
            $keyPath,
            $ambiente
        );

        if (($resultado['httpCode'] ?? 0) > 0) {
            return ['sucesso' => true, 'mensagem' => 'Conexão com ISSNet estabelecida.', 'diagnostico' => $resultado['diagnostico'] ?? []];
        }

        return ['sucesso' => false, 'mensagem' => $resultado['erro'] ?? 'Falha na conexão com ISSNet.', 'diagnostico' => $resultado['diagnostico'] ?? []];
    }

    private function soapRequest(string $action, string $dadosXml, string $certPath, string $keyPath, int $ambiente): array
    {
        $xml = new NFSeXMLISSNet();
        $payload = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfse="http://nfse.abrasf.org.br">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<nfse:' . $action . '>'
            . '<nfseCabecMsg>' . htmlspecialchars($xml->cabecalho(), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</nfseCabecMsg>'
            . '<nfseDadosMsg>' . htmlspecialchars($this->semDeclaracaoXml($dadosXml), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</nfseDadosMsg>'
            . '</nfse:' . $action . '>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $url = $this->endpoint($ambiente);
        $caBundlePath = $this->getCaBundlePath();
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLKEY => $keyPath,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXAO,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUISICAO,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "http://nfse.abrasf.org.br/' . $action . '"',
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

        $diagnostico = [
            'emissor' => 'issnet',
            'ambiente' => $ambiente,
            'url' => $url,
            'soapAction' => $action,
            'httpCode' => $httpCode,
            'caBundleLocal' => $caBundlePath !== null,
        ];

        if ($curlErrno !== 0) {
            $codigoErro = $this->mapearErroCurl($curlErrno);
            $diagnostico['curl_errno'] = $curlErrno;
            $diagnostico['curl_error'] = $this->sanitizarErroCurl($curlError);
            $diagnostico['categoria'] = $codigoErro;

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
            'diagnostico' => $diagnostico,
        ];
    }

    private function endpoint(int $ambiente): string
    {
        return $ambiente === 1 ? self::URL_PRODUCAO_DF : self::URL_HOMOLOGACAO;
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
