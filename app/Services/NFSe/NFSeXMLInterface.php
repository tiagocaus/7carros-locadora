<?php

namespace App\Services\NFSe;

/**
 * Interface para geracao de XML de NFS-e
 *
 * Implementada por NFSeXMLNacional (DPS) e NFSeXMLAbrasf (RPS).
 */
interface NFSeXMLInterface
{
    /**
     * Gera XML para emissao
     *
     * @param array $dados Dados da NFS-e (prestador, tomador, servico, valores)
     * @return string XML gerado
     */
    public function gerarXML(array $dados): string;

    /**
     * Gera XML de cancelamento
     *
     * @param string $chaveAcesso Chave de acesso da NFS-e
     * @param string $motivo Motivo do cancelamento
     * @param array $dados Dados adicionais (CNPJ, IM, municipio)
     * @return string XML de cancelamento
     */
    public function gerarXMLCancelamento(string $chaveAcesso, string $motivo, array $dados): string;

    /**
     * Faz parse do retorno da API de emissao
     *
     * @param string $resposta Resposta da API (XML ou JSON)
     * @return array Dados extraidos (numero, chave_acesso, codigo_verificacao, xml_retorno)
     */
    public function parseRetorno(string $resposta): array;

    /**
     * Faz parse do retorno da API de cancelamento
     *
     * @param string $resposta Resposta da API
     * @return array Dados extraidos (sucesso, mensagem)
     */
    public function parseRetornoCancelamento(string $resposta): array;
}
