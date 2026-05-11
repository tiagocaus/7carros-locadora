<?php

namespace App\Services\NFSe\ABRASF;

use App\Services\NFSe\NFSeAPIInterface;
use App\Services\NFSe\NFSeErros;

/**
 * Comunicacao com API ABRASF (Municipal) via SOAP 1.1
 *
 * Usa cURL diretamente para montar envelope SOAP (nao SoapClient).
 * mTLS obrigatorio via certificado digital.
 *
 * URLs padrao (ISSNet - Brasilia/DF):
 * - Producao: https://df.issnetonline.com.br/webservicenfse204/nfse.asmx
 * - Homologacao: https://www.issnetonline.com.br/homologaabrasf/webservicenfse204/nfse.asmx
 */
class NFSeAPIAbrasf implements NFSeAPIInterface
{
    private const URL_PRODUCAO = 'https://df.issnetonline.com.br/webservicenfse204/nfse.asmx';
    private const URL_HOMOLOGACAO = 'https://www.issnetonline.com.br/homologaabrasf/webservicenfse204/nfse.asmx';

    private const TIMEOUT_CONEXAO = 30;
    private const TIMEOUT_REQUISICAO = 60;

    public function enviar(string $xml, string $certPath, string $keyPath, int $ambiente): array
    {
        $xmlAbrasf = new NFSeXMLAbrasf();
        $cabecalho = $xmlAbrasf->gerarCabecalho();

        $soapBody = $this->montarEnvelopeSOAP('GerarNfse', $cabecalho, $xml);

        return $this->request(
            $this->getBaseUrl($ambiente),
            $soapBody,
            'http://nfse.abrasf.org.br/GerarNfse',
            $certPath,
            $keyPath
        );
    }

    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        // Extrair numero do RPS da chave de acesso
        $partes = explode('-', $chaveAcesso);
        $numero = $partes[0] ?? '';

        $xmlConsulta = '<ConsultarNfsePorRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<IdentificacaoRps>'
            . '<Numero>' . htmlspecialchars($numero) . '</Numero>'
            . '</IdentificacaoRps>'
            . '</ConsultarNfsePorRpsEnvio>';

        $xmlAbrasf = new NFSeXMLAbrasf();
        $cabecalho = $xmlAbrasf->gerarCabecalho();
        $soapBody = $this->montarEnvelopeSOAP('ConsultarNfsePorRps', $cabecalho, $xmlConsulta);

        return $this->request(
            $this->getBaseUrl($ambiente),
            $soapBody,
            'http://nfse.abrasf.org.br/ConsultarNfsePorRps',
            $certPath,
            $keyPath
        );
    }

    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array
    {
        $xmlAbrasf = new NFSeXMLAbrasf();
        $cabecalho = $xmlAbrasf->gerarCabecalho();

        $soapBody = $this->montarEnvelopeSOAP('CancelarNfse', $cabecalho, $xml);

        return $this->request(
            $this->getBaseUrl($ambiente),
            $soapBody,
            'http://nfse.abrasf.org.br/CancelarNfse',
            $certPath,
            $keyPath
        );
    }

    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array
    {
        // Enviar uma consulta simples para testar conexao
        $xmlAbrasf = new NFSeXMLAbrasf();
        $cabecalho = $xmlAbrasf->gerarCabecalho();

        $xmlTeste = '<ConsultarNfsePorRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<IdentificacaoRps><Numero>0</Numero></IdentificacaoRps>'
            . '</ConsultarNfsePorRpsEnvio>';

        $soapBody = $this->montarEnvelopeSOAP('ConsultarNfsePorRps', $cabecalho, $xmlTeste);

        $result = $this->request(
            $this->getBaseUrl($ambiente),
            $soapBody,
            'http://nfse.abrasf.org.br/ConsultarNfsePorRps',
            $certPath,
            $keyPath
        );

        // Qualquer resposta HTTP (mesmo com erro de negocio) indica conexao OK
        if ($result['httpCode'] > 0) {
            return ['sucesso' => true, 'mensagem' => 'Conexão com sistema municipal estabelecida com sucesso.'];
        }

        return ['sucesso' => false, 'mensagem' => $result['erro'] ?? 'Falha na conexão com o sistema municipal.'];
    }

    /**
     * Monta envelope SOAP 1.1
     */
    private function montarEnvelopeSOAP(string $operacao, string $cabecalho, string $xmlDados): string
    {
        $cabecalhoCDATA = '<![CDATA[' . $cabecalho . ']]>';
        $dadosCDATA = '<![CDATA[' . $xmlDados . ']]>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfse="http://nfse.abrasf.org.br">'
            . '<soap:Body>'
            . '<nfse:' . $operacao . 'Request>'
            . '<nfseCabecMsg>' . $cabecalhoCDATA . '</nfseCabecMsg>'
            . '<nfseDadosMsg>' . $dadosCDATA . '</nfseDadosMsg>'
            . '</nfse:' . $operacao . 'Request>'
            . '</soap:Body>'
            . '</soap:Envelope>';
    }

    /**
     * Executa requisicao SOAP com mTLS
     */
    private function request(string $url, string $soapBody, string $soapAction, string $certPath, string $keyPath): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapBody,
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLKEY => $keyPath,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONEXAO,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUISICAO,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"',
                'Content-Length: ' . strlen($soapBody),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

        // Extrair conteudo do Body SOAP
        $conteudo = $this->extrairBodySOAP($response ?: '');

        $sucesso = $httpCode >= 200 && $httpCode < 300;

        return [
            'sucesso' => $sucesso,
            'resposta' => $conteudo,
            'httpCode' => $httpCode,
        ];
    }

    /**
     * Extrai conteudo de dentro do envelope SOAP
     */
    private function extrairBodySOAP(string $soapResponse): string
    {
        if (empty($soapResponse)) {
            return '';
        }

        // Extrair resultado de dentro do Body/xxxResponse/xxxResult
        if (preg_match('/<\w+Result[^>]*>(.*?)<\/\w+Result>/s', $soapResponse, $matches)) {
            $content = $matches[1];
            // Decodificar HTML entities se necessario
            if (str_contains($content, '&lt;')) {
                $content = html_entity_decode($content);
            }
            return $content;
        }

        // Fallback: extrair o Body inteiro
        if (preg_match('/<soap:Body[^>]*>(.*?)<\/soap:Body>/s', $soapResponse, $matches)) {
            return $matches[1];
        }

        return $soapResponse;
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
