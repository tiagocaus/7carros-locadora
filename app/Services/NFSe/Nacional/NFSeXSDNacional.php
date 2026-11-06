<?php

namespace App\Services\NFSe\Nacional;

/** Valida o leiaute de produção sem consultas externas ou alterações no documento. */
final class NFSeXSDNacional
{
    private const SCHEMAS = __DIR__ . '/Schemas/v1.01-20260209/';

    public function validarDPS(string $xml): void
    {
        $this->validar($xml, 'DPS_v1.01.xsd');
    }

    public function validarEvento(string $xml): void
    {
        $this->validar($xml, 'pedRegEvento_v1.01.xsd');
    }

    private function validar(string $xml, string $schema): void
    {
        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $documento = new \DOMDocument();
            // Não expandir entidades nem permitir DTD ou acesso à rede.
            $carregado = $documento->loadXML($xml, LIBXML_NONET);
            if (!$carregado || $documento->doctype !== null) {
                throw new \InvalidArgumentException('XML inválido da NFS-e Nacional: documento malformado ou DTD não permitido.');
            }
            if (!$documento->schemaValidateSource($this->schemaParaLibxml($schema))) {
                $erros = array_slice(libxml_get_errors(), 0, 3);
                $detalhes = array_map(static fn (\LibXMLError $erro): string => trim($erro->message), $erros);
                throw new \InvalidArgumentException('XML inválido da NFS-e Nacional: ' . implode(' ', $detalhes));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }

    /**
     * Os originais permanecem intactos. O pacote oficial usa âncoras PCRE na
     * série, embora XSD 1.0 as interprete como caracteres literais. Corrigir só
     * essa expressão em memória permite validar séries numéricas no libxml.
     */
    private function schemaParaLibxml(string $arquivo): string
    {
        $namespace = 'http://www.w3.org/2001/XMLSchema';
        $schema = new \DOMDocument();
        $raiz = $schema->createElementNS($namespace, 'xs:schema');
        $raiz->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $raiz->setAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $raiz->setAttribute('targetNamespace', 'http://www.sped.fazenda.gov.br/nfse');
        $raiz->setAttribute('elementFormDefault', 'qualified');
        $schema->appendChild($raiz);
        $visitados = [];
        $importados = [];
        $incluir = function (string $nome) use (&$incluir, &$visitados, &$importados, $namespace, $schema, $raiz): void {
            if (isset($visitados[$nome])) {
                return;
            }
            $visitados[$nome] = true;
            $origem = new \DOMDocument();
            if (!$origem->load(self::SCHEMAS . $nome, LIBXML_NONET)) {
                throw new \RuntimeException('Não foi possível carregar o XSD Nacional de produção.');
            }
            foreach ($origem->documentElement->childNodes as $no) {
                if (!$no instanceof \DOMElement) {
                    continue;
                }
                if ($no->namespaceURI === $namespace && $no->localName === 'include') {
                    $incluir(basename($no->getAttribute('schemaLocation')));
                    continue;
                }
                if ($no->namespaceURI === $namespace && $no->localName === 'import') {
                    $ns = $no->getAttribute('namespace');
                    if (isset($importados[$ns])) {
                        continue;
                    }
                    $importados[$ns] = true;
                    $caminho = self::SCHEMAS . basename($no->getAttribute('schemaLocation'));
                    $no->setAttribute('schemaLocation', 'file://' . implode('/', array_map('rawurlencode', explode('/', $caminho))));
                }
                $raiz->appendChild($schema->importNode($no, true));
            }
        };
        $incluir($arquivo);
        $xpath = new \DOMXPath($schema);
        $xpath->registerNamespace('xs', $namespace);
        foreach ($xpath->query('//xs:simpleType[@name="TSSerieDPS"]/xs:restriction/xs:pattern') as $pattern) {
            if ($pattern->getAttribute('value') === '^0{0,4}\\d{1,5}$') {
                $pattern->setAttribute('value', '[0-9]{1,5}');
            }
        }
        return $schema->saveXML();
    }
}
