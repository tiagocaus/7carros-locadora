<?php

namespace App\Services\NFSe\ISSNet;

use App\Services\NFSe\NFSeXMLInterface;

class NFSeXMLISSNet implements NFSeXMLInterface
{
    private const NAMESPACE = 'http://www.abrasf.org.br/nfse.xsd';
    private const VERSAO = '2.04';

    public function gerarXML(array $dados): string
    {
        $prest = $dados['prestador'] ?? [];
        $tomador = $dados['tomador'] ?? [];
        $servico = $dados['servico'] ?? [];
        $valores = $dados['valores'] ?? [];
        $numero = (int) ($dados['numero'] ?? 0);
        $serie = $this->normalizarSerie($dados['serie'] ?? null);
        $id = 'rps_' . $numero;
        $cpfCnpjPrestador = $this->somenteDigitos((string) ($prest['cnpj'] ?? ''));
        $imPrestador = $this->somenteDigitos((string) ($prest['inscricao_municipal'] ?? ''));
        $cpfCnpjTomador = $this->somenteDigitos((string) ($tomador['cpf_cnpj'] ?? ''));
        $data = $this->data((string) ($dados['data_competencia'] ?? ''));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<GerarNfseEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<Rps>';
        $xml .= '<InfDeclaracaoPrestacaoServico Id="' . $this->escape($id) . '">';
        $xml .= '<Rps><IdentificacaoRps>';
        $xml .= '<Numero>' . $numero . '</Numero>';
        $xml .= '<Serie>' . $this->escape($serie) . '</Serie>';
        $xml .= '<Tipo>1</Tipo>';
        $xml .= '</IdentificacaoRps>';
        $xml .= '<DataEmissao>' . $this->data((string) ($dados['data_emissao'] ?? '')) . '</DataEmissao>';
        $xml .= '<Status>1</Status></Rps>';
        $xml .= '<Competencia>' . $data . '</Competencia>';
        $xml .= '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . $this->decimal($valores['servicos'] ?? 0) . '</ValorServicos>';
        if ((float) ($valores['deducoes'] ?? 0) > 0) {
            $xml .= '<ValorDeducoes>' . $this->decimal($valores['deducoes']) . '</ValorDeducoes>';
        }
        if ((float) ($valores['valor_iss'] ?? 0) > 0) {
            $xml .= '<ValorIss>' . $this->decimal($valores['valor_iss']) . '</ValorIss>';
        }
        if ((float) ($valores['aliquota_iss'] ?? 0) > 0) {
            $xml .= '<Aliquota>' . $this->decimal($valores['aliquota_iss'], 4) . '</Aliquota>';
        }
        $xml .= '</Valores>';
        $xml .= '<IssRetido>' . (($valores['iss_retido'] ?? 'N') === 'S' ? '1' : '2') . '</IssRetido>';
        $xml .= '<ItemListaServico>' . $this->escape((string) ($servico['item_lista_servico'] ?? '')) . '</ItemListaServico>';
        if (!empty($servico['codigo_cnae'])) {
            $xml .= '<CodigoCnae>' . $this->somenteDigitos((string) $servico['codigo_cnae']) . '</CodigoCnae>';
        }
        if (!empty($servico['codigo_tributacao_municipio'])) {
            $xml .= '<CodigoTributacaoMunicipio>' . $this->escape((string) $servico['codigo_tributacao_municipio']) . '</CodigoTributacaoMunicipio>';
        }
        if (!empty($servico['codigo'])) {
            $xml .= '<CodigoNbs>' . $this->converterNBS((string) $servico['codigo']) . '</CodigoNbs>';
        }
        $xml .= '<Discriminacao>' . $this->texto((string) ($servico['descricao'] ?? '')) . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . $this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')) . '</CodigoMunicipio>';
        $xml .= '<ExigibilidadeISS>' . (int) ($valores['exigibilidade_iss'] ?? 1) . '</ExigibilidadeISS>';
        $xml .= '</Servico>';
        $xml .= '<Prestador><CpfCnpj><Cnpj>' . $cpfCnpjPrestador . '</Cnpj></CpfCnpj>';
        if ($imPrestador !== '') {
            $xml .= '<InscricaoMunicipal>' . $imPrestador . '</InscricaoMunicipal>';
        }
        $xml .= '</Prestador>';
        $xml .= $this->gerarTomador($tomador, $cpfCnpjTomador);
        $xml .= '<OptanteSimplesNacional>' . ((int) ($prest['regime_tributario'] ?? 1) === 1 ? '1' : '2') . '</OptanteSimplesNacional>';
        $xml .= '<IncentivoFiscal>' . (($dados['incentivo_fiscal'] ?? 'N') === 'S' ? '1' : '2') . '</IncentivoFiscal>';
        $xml .= '</InfDeclaracaoPrestacaoServico>';
        $xml .= '</Rps>';
        $xml .= '</GerarNfseEnvio>';

        return $xml;
    }

    public function gerarXMLCancelamento(string $chaveAcesso, string $motivo, array $dados): string
    {
        $id = 'cancel_' . $this->somenteDigitos((string) ($dados['numero'] ?? $chaveAcesso));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<CancelarNfseEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<Pedido><InfPedidoCancelamento Id="' . $this->escape($id) . '">';
        $xml .= '<IdentificacaoNfse>';
        $xml .= '<Numero>' . (int) ($dados['numero'] ?? 0) . '</Numero>';
        $xml .= '<CpfCnpj><Cnpj>' . $this->somenteDigitos((string) ($dados['prestador_cnpj'] ?? '')) . '</Cnpj></CpfCnpj>';
        if (!empty($dados['prestador_inscricao_municipal'])) {
            $xml .= '<InscricaoMunicipal>' . $this->somenteDigitos((string) $dados['prestador_inscricao_municipal']) . '</InscricaoMunicipal>';
        }
        $xml .= '<CodigoMunicipio>' . $this->somenteDigitos((string) ($dados['codigo_municipio'] ?? '')) . '</CodigoMunicipio>';
        $xml .= '</IdentificacaoNfse>';
        $xml .= '<CodigoCancelamento>2</CodigoCancelamento>';
        $xml .= '</InfPedidoCancelamento></Pedido>';
        $xml .= '</CancelarNfseEnvio>';

        return $xml;
    }

    public function gerarXMLConsultaPorRps(array $nfse, array $config): string
    {
        $serie = $this->normalizarSerie($nfse['serie'] ?? $config['serie'] ?? null);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<ConsultarNfseRpsEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<IdentificacaoRps>';
        $xml .= '<Numero>' . (int) ($nfse['numero'] ?? 0) . '</Numero>';
        $xml .= '<Serie>' . $this->escape($serie) . '</Serie>';
        $xml .= '<Tipo>1</Tipo>';
        $xml .= '</IdentificacaoRps>';
        $xml .= '<Prestador><CpfCnpj><Cnpj>' . $this->somenteDigitos((string) ($nfse['prestador_cnpj'] ?? '')) . '</Cnpj></CpfCnpj>';
        if (!empty($nfse['prestador_inscricao_municipal'])) {
            $xml .= '<InscricaoMunicipal>' . $this->somenteDigitos((string) $nfse['prestador_inscricao_municipal']) . '</InscricaoMunicipal>';
        }
        $xml .= '</Prestador></ConsultarNfseRpsEnvio>';

        return $xml;
    }

    public function parseRetorno(string $resposta): array
    {
        $resultado = $this->base($resposta);
        $xml = $this->extrairOutputXML($resposta);
        $doc = $this->carregarXML($xml);
        if (!$doc) {
            return $resultado;
        }

        $erros = $this->parseMensagens($doc);
        if (!empty($erros)) {
            $resultado['erros'] = $erros;
            return $resultado;
        }

        $numero = $this->valorTag($doc, 'Numero');
        $codigoVerificacao = $this->valorTag($doc, 'CodigoVerificacao');
        if ($numero !== '' || $codigoVerificacao !== '') {
            $resultado['sucesso'] = true;
            $resultado['numero'] = $numero !== '' ? (int) $numero : null;
            $resultado['codigo_verificacao'] = $codigoVerificacao;
            $resultado['chave_acesso'] = $codigoVerificacao;
            $resultado['xml_retorno'] = $xml;
        }

        return $resultado;
    }

    public function parseRetornoCancelamento(string $resposta): array
    {
        $xml = $this->extrairOutputXML($resposta);
        $doc = $this->carregarXML($xml);
        if (!$doc) {
            return ['sucesso' => false, 'mensagem' => '', 'erros' => []];
        }

        $erros = $this->parseMensagens($doc);
        $sucesso = empty($erros) && (
            $this->valorTag($doc, 'Confirmacao') !== ''
            || $this->valorTag($doc, 'DataHora') !== ''
            || stripos($xml, 'Cancelamento') !== false
        );

        return [
            'sucesso' => $sucesso,
            'mensagem' => $erros[0]['mensagem'] ?? ($sucesso ? 'NFS-e cancelada com sucesso.' : ''),
            'erros' => $erros,
        ];
    }

    public function cabecalho(): string
    {
        return '<cabecalho xmlns="' . self::NAMESPACE . '" versao="' . self::VERSAO . '"><versaoDados>' . self::VERSAO . '</versaoDados></cabecalho>';
    }

    private function gerarTomador(array $tomador, string $cpfCnpj): string
    {
        $estrangeiro = ($tomador['tipo'] ?? '') === 'ES';
        $xml = '<TomadorServico>';
        if (!$estrangeiro) {
            $xml .= '<IdentificacaoTomador><CpfCnpj>';
            $xml .= strlen($cpfCnpj) === 14 ? '<Cnpj>' . $cpfCnpj . '</Cnpj>' : '<Cpf>' . $cpfCnpj . '</Cpf>';
            $xml .= '</CpfCnpj></IdentificacaoTomador>';
        }
        $xml .= '<RazaoSocial>' . $this->texto((string) ($tomador['nome'] ?? '')) . '</RazaoSocial>';

        $endereco = $tomador['endereco'] ?? [];
        if ($estrangeiro && is_array($endereco)) {
            $partes = array_filter([
                trim((string) ($endereco['logradouro'] ?? '')) . ', ' . trim((string) ($endereco['numero'] ?? '')),
                trim((string) ($endereco['complemento'] ?? '')),
                trim((string) ($endereco['bairro'] ?? '')),
                trim((string) ($endereco['cidade'] ?? '')),
                trim((string) ($endereco['uf'] ?? '')),
                trim((string) ($endereco['cep'] ?? '')),
            ], static fn(string $valor): bool => trim($valor, ' ,') !== '');
            $xml .= '<EnderecoExterior><CodigoPais>' . $this->escape((string) ($endereco['codigo_pais_bacen'] ?? '')) . '</CodigoPais>';
            $xml .= '<EnderecoCompletoExterior>' . $this->texto(mb_substr(implode(' - ', $partes), 0, 255)) . '</EnderecoCompletoExterior></EnderecoExterior>';
        } elseif (is_array($endereco) && $this->somenteDigitos((string) ($endereco['cep'] ?? '')) !== '') {
            $xml .= '<Endereco>';
            if (!empty($endereco['logradouro'])) {
                $xml .= '<Endereco>' . $this->texto((string) $endereco['logradouro']) . '</Endereco>';
            }
            if (!empty($endereco['numero'])) {
                $xml .= '<Numero>' . $this->texto((string) $endereco['numero']) . '</Numero>';
            }
            if (!empty($endereco['bairro'])) {
                $xml .= '<Bairro>' . $this->texto((string) $endereco['bairro']) . '</Bairro>';
            }
            if (!empty($endereco['codigo_municipio'])) {
                $xml .= '<CodigoMunicipio>' . $this->somenteDigitos((string) $endereco['codigo_municipio']) . '</CodigoMunicipio>';
            }
            if (!empty($endereco['uf'])) {
                $xml .= '<Uf>' . $this->escape((string) $endereco['uf']) . '</Uf>';
            }
            $xml .= '<Cep>' . $this->somenteDigitos((string) ($endereco['cep'] ?? '')) . '</Cep>';
            $xml .= '</Endereco>';
        }

        if (!empty($tomador['email'])) {
            $xml .= '<Contato><Email>' . $this->escape((string) $tomador['email']) . '</Email></Contato>';
        }
        $xml .= '</TomadorServico>';

        return $xml;
    }

    private function parseMensagens(\DOMDocument $doc): array
    {
        $erros = [];
        foreach ($doc->getElementsByTagName('MensagemRetorno') as $mensagem) {
            $codigo = '';
            $texto = '';
            foreach ($mensagem->childNodes as $child) {
                if ($child->localName === 'Codigo') {
                    $codigo = trim((string) $child->nodeValue);
                }
                if ($child->localName === 'Mensagem' || $child->localName === 'Correcao') {
                    $texto = trim($texto . ' ' . (string) $child->nodeValue);
                }
            }
            if ($codigo !== '' || $texto !== '') {
                $erros[] = ['codigo' => $codigo ?: 'ERRO_DESCONHECIDO', 'mensagem' => trim($texto)];
            }
        }

        return $erros;
    }

    private function extrairOutputXML(string $resposta): string
    {
        $doc = $this->carregarXML($resposta);
        if (!$doc) {
            return $resposta;
        }

        $nodes = $doc->getElementsByTagName('outputXML');
        if ($nodes->length === 0) {
            return $resposta;
        }

        return html_entity_decode((string) $nodes->item(0)->nodeValue, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function carregarXML(string $xml): ?\DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $ok = $doc->loadXML(trim($xml));
        libxml_clear_errors();

        return $ok ? $doc : null;
    }

    private function valorTag(\DOMDocument $doc, string $tag): string
    {
        $nodes = $doc->getElementsByTagName($tag);
        return $nodes->length > 0 ? trim((string) $nodes->item(0)->nodeValue) : '';
    }

    private function base(string $resposta): array
    {
        return [
            'sucesso' => false,
            'processando' => false,
            'protocolo' => null,
            'numero' => null,
            'chave_acesso' => null,
            'codigo_verificacao' => null,
            'xml_retorno' => $resposta,
            'erros' => [],
        ];
    }

    private function data(string $valor): string
    {
        try {
            return (new \DateTimeImmutable($valor ?: 'now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        } catch (\Throwable) {
            return (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        }
    }

    private function decimal(mixed $valor, int $casas = 2): string
    {
        return number_format((float) $valor, $casas, '.', '');
    }

    private function normalizarSerie(mixed $serie): string
    {
        $serie = trim((string) ($serie ?? ''));
        return $serie !== '' ? $serie : 'UNICA';
    }

    private function converterNBS(string $nbs): string
    {
        return str_pad($this->somenteDigitos($nbs), 9, '0', STR_PAD_RIGHT);
    }

    private function texto(string $valor): string
    {
        return $this->escape(mb_strtoupper($valor, 'UTF-8'));
    }

    private function escape(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }
}
