<?php

namespace App\Services\NFSe;

use DOMDocument;
use DOMElement;

/**
 * Assinatura Digital XML (XMLDSIG)
 *
 * Assina XML para NFS-e usando enveloped-signature com canonicalizacao C14N.
 * Suporta SHA256 para DPS Nacional e Betha.
 */
class NFSeAssinatura
{
    /**
     * Assina um XML com XMLDSIG
     *
     * @param string $xml XML a assinar
     * @param string $certPath Caminho do certificado PEM
     * @param string $keyPath Caminho da chave privada PEM
     * @param string $tagToSign Tag do elemento a assinar (ex: 'infDPS', 'InfDeclaracaoPrestacaoServico')
     * @param string $algoritmo Algoritmo de assinatura ('sha256' por padrao)
     * @param string $idAttribute Nome do atributo de ID ('Id' ou 'id')
     * @return array ['sucesso' => bool, 'xml' => string, 'mensagem' => string]
     */
    public function assinar(
        string $xml,
        string $certPath,
        string $keyPath,
        string $tagToSign,
        string $algoritmo = 'sha256',
        string $idAttribute = 'Id'
    ): array {
        try {
            // Carregar chave privada
            $privateKey = file_get_contents($keyPath);
            if ($privateKey === false) {
                return ['sucesso' => false, 'xml' => '', 'mensagem' => 'Erro ao ler chave privada.'];
            }

            $pkeyResource = openssl_pkey_get_private($privateKey);
            if ($pkeyResource === false) {
                return ['sucesso' => false, 'xml' => '', 'mensagem' => 'Chave privada inválida.'];
            }

            // Carregar certificado publico
            $certContent = file_get_contents($certPath);
            if ($certContent === false) {
                return ['sucesso' => false, 'xml' => '', 'mensagem' => 'Erro ao ler certificado.'];
            }

            // Extrair certificado X509 (sem headers PEM)
            $x509Certificate = $this->extrairX509($certContent);

            // Carregar XML
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = false;
            $doc->loadXML($xml);

            // Encontrar elemento a assinar
            $node = $doc->getElementsByTagName($tagToSign)->item(0);
            if (!$node) {
                return ['sucesso' => false, 'xml' => '', 'mensagem' => "Tag '{$tagToSign}' não encontrada no XML."];
            }

            $id = $node->getAttribute($idAttribute);
            if (empty($id)) {
                return ['sucesso' => false, 'xml' => '', 'mensagem' => "Atributo '{$idAttribute}' não encontrado na tag '{$tagToSign}'."];
            }

            // Definir algoritmos
            $digestAlgo = $algoritmo === 'sha1' ? 'sha1' : 'sha256';
            $signAlgo = $algoritmo === 'sha1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256;
            $signMethodUri = $algoritmo === 'sha1'
                ? 'http://www.w3.org/2000/09/xmldsig#rsa-sha1'
                : 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
            $digestMethodUri = $algoritmo === 'sha1'
                ? 'http://www.w3.org/2000/09/xmldsig#sha1'
                : 'http://www.w3.org/2001/04/xmlenc#sha256';

            // Canonicalizar elemento
            $canonical = $node->C14N(false, false);

            // Calcular digest
            $digestValue = base64_encode(hash($digestAlgo, $canonical, true));

            // Montar SignedInfo
            $signedInfoXml = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">'
                . '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
                . '<SignatureMethod Algorithm="' . $signMethodUri . '"/>'
                . '<Reference URI="#' . $id . '">'
                . '<Transforms>'
                . '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
                . '<Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
                . '</Transforms>'
                . '<DigestMethod Algorithm="' . $digestMethodUri . '"/>'
                . '<DigestValue>' . $digestValue . '</DigestValue>'
                . '</Reference>'
                . '</SignedInfo>';

            // Canonicalizar SignedInfo para assinatura
            $signedInfoDoc = new DOMDocument('1.0', 'UTF-8');
            $signedInfoDoc->loadXML($signedInfoXml);
            $signedInfoCanonical = $signedInfoDoc->documentElement->C14N(false, false);

            // Assinar
            $signature = '';
            if (!openssl_sign($signedInfoCanonical, $signature, $pkeyResource, $signAlgo)) {
                return ['sucesso' => false, 'xml' => '', 'mensagem' => 'Erro ao gerar assinatura digital.'];
            }
            $signatureValue = base64_encode($signature);

            // Montar elemento Signature completo
            $signatureXml = '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">'
                . $signedInfoXml
                . '<SignatureValue>' . $signatureValue . '</SignatureValue>'
                . '<KeyInfo>'
                . '<X509Data>'
                . '<X509Certificate>' . $x509Certificate . '</X509Certificate>'
                . '</X509Data>'
                . '</KeyInfo>'
                . '</Signature>';

            // Inserir Signature no XML
            $signatureDoc = new DOMDocument('1.0', 'UTF-8');
            $signatureDoc->loadXML($signatureXml);

            $signatureNode = $doc->importNode($signatureDoc->documentElement, true);
            $parentNode = $node->parentNode instanceof DOMElement ? $node->parentNode : $doc->documentElement;
            $parentNode->appendChild($signatureNode);

            $xmlAssinado = $doc->saveXML();

            return [
                'sucesso' => true,
                'xml' => $xmlAssinado,
                'mensagem' => 'XML assinado com sucesso.',
            ];
        } catch (\Throwable $e) {
            return [
                'sucesso' => false,
                'xml' => '',
                'mensagem' => 'Erro na assinatura: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica se o XML possui assinatura
     */
    public function possuiAssinatura(string $xml): bool
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        return $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->length > 0;
    }

    /**
     * Extrai conteudo X509 do PEM (remove headers)
     */
    private function extrairX509(string $certPem): string
    {
        $cert = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $certPem);
        $cert = preg_replace('/-----END CERTIFICATE-----/', '', $cert);
        return trim(str_replace(["\r", "\n", ' '], '', $cert));
    }
}
