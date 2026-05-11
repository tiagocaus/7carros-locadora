<?php

namespace App\Services\NFSe\ABRASF;

use App\Services\NFSe\NFSeXMLInterface;

/**
 * Geracao de XML ABRASF - RPS (Recibo Provisorio de Servico) v2.04
 *
 * Formato municipal ABRASF.
 * Namespace: http://www.abrasf.org.br/nfse.xsd
 * Versao: 2.04
 */
class NFSeXMLAbrasf implements NFSeXMLInterface
{
    private const NAMESPACE = 'http://www.abrasf.org.br/nfse.xsd';
    private const VERSAO = '2.04';

    public function gerarXML(array $dados): string
    {
        $prest = $dados['prestador'] ?? [];
        $tomador = $dados['tomador'] ?? [];
        $servico = $dados['servico'] ?? [];
        $valores = $dados['valores'] ?? [];
        $abrasf = $dados['abrasf'] ?? [];

        $cnpjPrestador = preg_replace('/\D/', '', $prest['cnpj'] ?? '');
        $serie = $dados['serie'] ?? 'DPS';
        $numero = $dados['numero'] ?? 0;
        $idRps = "rps_{$cnpjPrestador}_{$serie}_{$numero}";

        $valorServicos = number_format((float) ($valores['servicos'] ?? 0), 2, '.', '');
        $valorDeducoes = number_format((float) ($valores['deducoes'] ?? 0), 2, '.', '');
        $baseCalculo = (float) ($valores['servicos'] ?? 0) - (float) ($valores['deducoes'] ?? 0);
        $aliquotaISS = (float) ($valores['aliquota_iss'] ?? 0);
        $tribISSQN = (int) ($valores['trib_issqn'] ?? 4);
        $valorISS = $tribISSQN === 1 ? $baseCalculo * ($aliquotaISS / 100) : 0;
        $issRetido = ($valores['iss_retido'] ?? 'N') === 'S' ? '1' : '2';
        $valorLiquido = (float) $valorServicos;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<GerarNfseEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<Rps>';
        $xml .= '<InfDeclaracaoPrestacaoServico Id="' . $idRps . '">';

        // RPS
        $xml .= '<Rps>';
        $xml .= '<IdentificacaoRps>';
        $xml .= '<Numero>' . $numero . '</Numero>';
        $xml .= '<Serie>' . htmlspecialchars($serie) . '</Serie>';
        $xml .= '<Tipo>1</Tipo>';
        $xml .= '</IdentificacaoRps>';
        $xml .= '<DataEmissao>' . ($dados['data_emissao'] ?? date('Y-m-d\TH:i:s')) . '</DataEmissao>';
        $xml .= '<Status>1</Status>';
        $xml .= '</Rps>';

        // Competencia
        $xml .= '<Competencia>' . ($dados['data_competencia'] ?? date('Y-m-d')) . '</Competencia>';

        // Servico
        $xml .= '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . $valorServicos . '</ValorServicos>';
        $xml .= '<ValorDeducoes>' . $valorDeducoes . '</ValorDeducoes>';
        $xml .= '<ValorPis>0.00</ValorPis>';
        $xml .= '<ValorCofins>0.00</ValorCofins>';
        $xml .= '<ValorInss>0.00</ValorInss>';
        $xml .= '<ValorIr>0.00</ValorIr>';
        $xml .= '<ValorCsll>0.00</ValorCsll>';
        $xml .= '<IssRetido>' . $issRetido . '</IssRetido>';
        $xml .= '<ValorIss>' . number_format($valorISS, 2, '.', '') . '</ValorIss>';
        $xml .= '<BaseCalculo>' . number_format($baseCalculo, 2, '.', '') . '</BaseCalculo>';
        $xml .= '<Aliquota>' . number_format($aliquotaISS, 2, '.', '') . '</Aliquota>';
        $xml .= '<ValorLiquidoNfse>' . number_format($valorLiquido, 2, '.', '') . '</ValorLiquidoNfse>';
        $xml .= '</Valores>';
        $xml .= '<IssRetido>' . $issRetido . '</IssRetido>';
        $xml .= '<ItemListaServico>' . htmlspecialchars($abrasf['item_lista_servico'] ?? '') . '</ItemListaServico>';
        $xml .= '<CodigoCnae>' . htmlspecialchars($abrasf['codigo_cnae'] ?? '') . '</CodigoCnae>';
        $xml .= '<CodigoTributacaoMunicipio>' . htmlspecialchars($abrasf['codigo_trib_municipio'] ?? '') . '</CodigoTributacaoMunicipio>';

        $nbs = $this->converterNBS($servico['codigo'] ?? '1.1101.11');
        $xml .= '<CodigoNbs>' . $nbs . '</CodigoNbs>';
        $xml .= '<Discriminacao>' . htmlspecialchars($servico['descricao'] ?? '') . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . ($dados['municipio_codigo'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<ExigibilidadeISS>' . ($abrasf['exigibilidade_iss'] ?? '1') . '</ExigibilidadeISS>';
        $xml .= '<MunicipioIncidencia>' . ($dados['municipio_codigo'] ?? '') . '</MunicipioIncidencia>';
        $xml .= '</Servico>';

        // Prestador
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . $cnpjPrestador . '</Cnpj></CpfCnpj>';
        if (!empty($prest['inscricao_municipal'])) {
            $xml .= '<InscricaoMunicipal>' . htmlspecialchars($prest['inscricao_municipal']) . '</InscricaoMunicipal>';
        }
        $xml .= '</Prestador>';

        // Tomador
        $cpfCnpjTomador = preg_replace('/\D/', '', $tomador['cpf_cnpj'] ?? '');
        $xml .= '<TomadorServico>';
        $xml .= '<IdentificacaoTomador>';
        $xml .= '<CpfCnpj>';
        if (strlen($cpfCnpjTomador) === 14) {
            $xml .= '<Cnpj>' . $cpfCnpjTomador . '</Cnpj>';
        } elseif (strlen($cpfCnpjTomador) === 11) {
            $xml .= '<Cpf>' . $cpfCnpjTomador . '</Cpf>';
        }
        $xml .= '</CpfCnpj>';
        $xml .= '</IdentificacaoTomador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($tomador['nome'] ?? '') . '</RazaoSocial>';

        // Endereco do tomador
        $endereco = $tomador['endereco'] ?? [];
        if (is_string($endereco)) {
            $endereco = json_decode($endereco, true) ?? [];
        }
        if (!empty($endereco)) {
            $xml .= '<Endereco>';
            if (!empty($endereco['logradouro'])) {
                $xml .= '<Endereco>' . htmlspecialchars($endereco['logradouro']) . '</Endereco>';
            }
            if (!empty($endereco['numero'])) {
                $xml .= '<Numero>' . htmlspecialchars($endereco['numero']) . '</Numero>';
            }
            if (!empty($endereco['bairro'])) {
                $xml .= '<Bairro>' . htmlspecialchars($endereco['bairro']) . '</Bairro>';
            }
            if (!empty($endereco['codigo_municipio'])) {
                $xml .= '<CodigoMunicipio>' . $endereco['codigo_municipio'] . '</CodigoMunicipio>';
            } elseif (!empty($dados['municipio_codigo'])) {
                $xml .= '<CodigoMunicipio>' . $dados['municipio_codigo'] . '</CodigoMunicipio>';
            }
            if (!empty($endereco['uf'])) {
                $xml .= '<Uf>' . htmlspecialchars($endereco['uf']) . '</Uf>';
            }
            if (!empty($endereco['cep'])) {
                $xml .= '<Cep>' . preg_replace('/\D/', '', $endereco['cep']) . '</Cep>';
            }
            $xml .= '</Endereco>';
        }
        $xml .= '</TomadorServico>';

        // Opcoes
        $regime = (int) ($prest['regime_tributario'] ?? 1);
        $xml .= '<OptanteSimplesNacional>' . ($regime === 1 ? '1' : '2') . '</OptanteSimplesNacional>';
        $xml .= '<IncentivoFiscal>' . (($abrasf['incentivo_fiscal'] ?? 'N') === 'S' ? '1' : '2') . '</IncentivoFiscal>';

        $xml .= '</InfDeclaracaoPrestacaoServico>';
        $xml .= '</Rps>';
        $xml .= '</GerarNfseEnvio>';

        return $xml;
    }

    public function gerarXMLCancelamento(string $chaveAcesso, string $motivo, array $dados): string
    {
        $cnpj = preg_replace('/\D/', '', $dados['cnpj'] ?? '');
        $numero = $dados['numero'] ?? '';
        $idCancel = "cancel_{$cnpj}_{$numero}";

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<CancelarNfseEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<Pedido>';
        $xml .= '<InfPedidoCancelamento Id="' . $idCancel . '">';
        $xml .= '<IdentificacaoNfse>';
        $xml .= '<Numero>' . htmlspecialchars($numero) . '</Numero>';
        $xml .= '<CpfCnpj><Cnpj>' . $cnpj . '</Cnpj></CpfCnpj>';
        if (!empty($dados['inscricao_municipal'])) {
            $xml .= '<InscricaoMunicipal>' . htmlspecialchars($dados['inscricao_municipal']) . '</InscricaoMunicipal>';
        }
        $xml .= '<CodigoMunicipio>' . ($dados['codigo_municipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '</IdentificacaoNfse>';
        $xml .= '<CodigoCancelamento>1</CodigoCancelamento>';
        $xml .= '</InfPedidoCancelamento>';
        $xml .= '</Pedido>';
        $xml .= '</CancelarNfseEnvio>';

        return $xml;
    }

    public function parseRetorno(string $resposta): array
    {
        $resultado = [
            'sucesso' => false,
            'numero' => null,
            'chave_acesso' => null,
            'codigo_verificacao' => null,
            'xml_retorno' => $resposta,
            'erros' => [],
        ];

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!$doc->loadXML($resposta)) {
            $resultado['erros'][] = ['codigo' => 'ABRASF_XML_INVALIDO', 'mensagem' => 'Resposta XML inválida.'];
            libxml_clear_errors();
            return $resultado;
        }
        libxml_clear_errors();

        // Verificar erros (ListaMensagemRetorno)
        $mensagens = $doc->getElementsByTagName('MensagemRetorno');
        if ($mensagens->length > 0) {
            foreach ($mensagens as $msg) {
                $codigo = '';
                $mensagem = '';
                foreach ($msg->childNodes as $child) {
                    if ($child->nodeName === 'Codigo') $codigo = $child->nodeValue;
                    if ($child->nodeName === 'Mensagem') $mensagem = $child->nodeValue;
                }
                $resultado['erros'][] = ['codigo' => $codigo, 'mensagem' => $mensagem];
            }
            if (!empty($resultado['erros'])) {
                return $resultado;
            }
        }

        // Sucesso - extrair dados da NFS-e
        $nfseNodes = $doc->getElementsByTagName('Nfse');
        if ($nfseNodes->length > 0) {
            $resultado['sucesso'] = true;

            $numero = $doc->getElementsByTagName('Numero');
            if ($numero->length > 0) {
                $resultado['numero'] = (int) $numero->item(0)->nodeValue;
            }

            $codigoVerif = $doc->getElementsByTagName('CodigoVerificacao');
            if ($codigoVerif->length > 0) {
                $resultado['codigo_verificacao'] = $codigoVerif->item(0)->nodeValue;
            }

            // Montar chave de acesso a partir dos dados retornados
            $resultado['chave_acesso'] = $resultado['numero'] . '-' . ($resultado['codigo_verificacao'] ?? '');
        }

        return $resultado;
    }

    public function parseRetornoCancelamento(string $resposta): array
    {
        $resultado = [
            'sucesso' => false,
            'mensagem' => '',
            'erros' => [],
        ];

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!$doc->loadXML($resposta)) {
            $resultado['erros'][] = ['codigo' => 'ABRASF_XML_INVALIDO', 'mensagem' => 'Resposta XML inválida.'];
            libxml_clear_errors();
            return $resultado;
        }
        libxml_clear_errors();

        // Verificar erros
        $mensagens = $doc->getElementsByTagName('MensagemRetorno');
        if ($mensagens->length > 0) {
            foreach ($mensagens as $msg) {
                $codigo = '';
                $mensagem = '';
                foreach ($msg->childNodes as $child) {
                    if ($child->nodeName === 'Codigo') $codigo = $child->nodeValue;
                    if ($child->nodeName === 'Mensagem') $mensagem = $child->nodeValue;
                }
                $resultado['erros'][] = ['codigo' => $codigo, 'mensagem' => $mensagem];
            }
            return $resultado;
        }

        // Sucesso
        $cancelamento = $doc->getElementsByTagName('Cancelamento');
        if ($cancelamento->length > 0) {
            $resultado['sucesso'] = true;
            $resultado['mensagem'] = 'NFS-e cancelada com sucesso.';
        }

        return $resultado;
    }

    /**
     * Gera cabecalho XML ABRASF para envelope SOAP
     */
    public function gerarCabecalho(): string
    {
        return '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="' . self::VERSAO . '">'
            . '<versaoDados>' . self::VERSAO . '</versaoDados>'
            . '</cabecalho>';
    }

    private function converterNBS(string $nbs): string
    {
        $limpo = str_replace('.', '', $nbs);
        return str_pad($limpo, 9, '0', STR_PAD_RIGHT);
    }
}
