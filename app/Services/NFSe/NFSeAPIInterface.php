<?php

namespace App\Services\NFSe;

/**
 * Interface para comunicacao com APIs de NFS-e
 *
 * Implementada por NFSeAPINacional (REST) e NFSeAPIBetha (SOAP).
 */
interface NFSeAPIInterface
{
    /**
     * Envia XML para emissao de NFS-e
     *
     * @param string $xml XML assinado para envio
     * @param string $certPath Caminho do certificado PEM
     * @param string $keyPath Caminho da chave privada PEM
     * @param int $ambiente 1=Producao, 2=Homologacao
     * @return array ['sucesso' => bool, 'resposta' => string, 'httpCode' => int]
     */
    public function enviar(string $xml, string $certPath, string $keyPath, int $ambiente): array;

    /**
     * Consulta status de uma NFS-e
     *
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @param string $certPath Caminho do certificado PEM
     * @param string $keyPath Caminho da chave privada PEM
     * @param int $ambiente 1=Producao, 2=Homologacao
     * @return array ['sucesso' => bool, 'resposta' => string, 'httpCode' => int]
     */
    public function consultar(string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array;

    /**
     * Cancela uma NFS-e
     *
     * @param string $xml XML de cancelamento assinado
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @param string $certPath Caminho do certificado PEM
     * @param string $keyPath Caminho da chave privada PEM
     * @param int $ambiente 1=Producao, 2=Homologacao
     * @return array ['sucesso' => bool, 'resposta' => string, 'httpCode' => int]
     */
    public function cancelar(string $xml, string $chaveAcesso, string $certPath, string $keyPath, int $ambiente): array;

    /**
     * Testa conexao com o servico
     *
     * @param string $certPath Caminho do certificado PEM
     * @param string $keyPath Caminho da chave privada PEM
     * @param int $ambiente 1=Producao, 2=Homologacao
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public function testarConexao(string $certPath, string $keyPath, int $ambiente): array;
}
