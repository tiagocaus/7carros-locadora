<?php

namespace App\Services\NFSe\Nacional;

use App\Services\NFSe\NFSeXMLInterface;

/**
 * Geracao de XML Nacional - DPS (Declaracao de Prestacao de Servico)
 *
 * Formato SEFIN Nacional.
 * Namespace: http://www.sped.fazenda.gov.br/nfse
 * Versao: 1.01
 * Textos: MAIUSCULO obrigatorio
 */
class NFSeXMLNacional implements NFSeXMLInterface
{
    private const NAMESPACE = 'http://www.sped.fazenda.gov.br/nfse';
    private const VERSAO = '1.01';
    private const FISCAL_TIMEZONE = 'America/Sao_Paulo';

    public function gerarXML(array $dados): string
    {
        $idDPS = $this->gerarIdDPS($dados);
        $serie = $this->normalizarSerie($dados['serie'] ?? null);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<DPS xmlns="' . self::NAMESPACE . '" versao="' . self::VERSAO . '">';
        $xml .= '<infDPS Id="' . $idDPS . '">';

        // Identificacao
        $xml .= '<tpAmb>' . ($dados['ambiente'] ?? 2) . '</tpAmb>';
        $xml .= '<dhEmi>' . $this->formatarDataISO($dados['data_emissao'] ?? \App\Helpers\DateHelper::isoNow()) . '</dhEmi>';
        $xml .= '<verAplic>7Carros v8.3</verAplic>';
        $xml .= '<serie>' . $this->escapeXml($serie) . '</serie>';
        $xml .= '<nDPS>' . (int) ($dados['numero'] ?? 0) . '</nDPS>';
        $xml .= '<dCompet>' . $this->escapeXml((string) ($dados['data_competencia'] ?? today())) . '</dCompet>';
        $xml .= '<tpEmit>1</tpEmit>';
        $xml .= '<cLocEmi>' . $this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')) . '</cLocEmi>';

        // Prestador
        $prest = $dados['prestador'] ?? [];
        $xml .= '<prest>';
        $xml .= '<CNPJ>' . $this->somenteDigitos((string) ($prest['cnpj'] ?? '')) . '</CNPJ>';
        if (($prest['enviar_im'] ?? 'N') === 'S' && !empty($prest['inscricao_municipal'])) {
            $xml .= '<IM>' . $this->somenteDigitos((string) $prest['inscricao_municipal']) . '</IM>';
        }
        if (!empty($prest['telefone'])) {
            $xml .= '<fone>' . $this->somenteDigitos((string) $prest['telefone']) . '</fone>';
        }
        if (!empty($prest['email'])) {
            $xml .= '<email>' . $this->textoMaiusculo((string) $prest['email']) . '</email>';
        }
        $xml .= '<regTrib>';
        $regime = (int) ($prest['regime_tributario'] ?? 1);
        $xml .= '<opSimpNac>' . $this->mapearOpcaoSimples($regime) . '</opSimpNac>';
        if ($regime === 1) {
            $xml .= '<regApTribSN>' . (int) ($prest['reg_apuracao_sn'] ?? 1) . '</regApTribSN>';
        }
        $xml .= '<regEspTrib>0</regEspTrib>';
        $xml .= '</regTrib>';
        $xml .= '</prest>';

        // Tomador
        $tomador = $dados['tomador'] ?? [];
        $xml .= '<toma>';
        if (($tomador['tipo'] ?? '') === 'ES') {
            $xml .= '<cNaoNIF>0</cNaoNIF>';
        } else {
            $cpfCnpj = $this->somenteDigitos((string) ($tomador['cpf_cnpj'] ?? ''));
            if (strlen($cpfCnpj) === 14) {
                $xml .= '<CNPJ>' . $cpfCnpj . '</CNPJ>';
            } elseif (strlen($cpfCnpj) === 11) {
                $xml .= '<CPF>' . $cpfCnpj . '</CPF>';
            }
        }
        $xml .= '<xNome>' . $this->textoMaiusculo((string) ($tomador['nome'] ?? '')) . '</xNome>';
        $xml .= $this->gerarEnderecoTomador($tomador['endereco'] ?? []);
        $xml .= '</toma>';

        // Servico
        $servico = $dados['servico'] ?? [];
        $valores = $dados['valores'] ?? [];
        $xml .= '<serv>';
        $xml .= '<locPrest>';
        $xml .= '<cLocPrestacao>' . $this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')) . '</cLocPrestacao>';
        $xml .= '</locPrest>';
        $xml .= '<cServ>';

        // cTribNac baseado no tipo de tributacao
        $tribISSQN = (int) ($valores['trib_issqn'] ?? 4);
        $cTribNac = $this->mapearCTribNac($tribISSQN);
        $xml .= '<cTribNac>' . $cTribNac . '</cTribNac>';
        $xml .= '<xDescServ>' . $this->textoMaiusculo((string) ($servico['descricao'] ?? '')) . '</xDescServ>';

        // NBS: 1.1101.11 -> 111011100 (9 digitos)
        $nbs = $this->converterNBS($servico['codigo'] ?? '1.1101.11');
        $xml .= '<cNBS>' . $nbs . '</cNBS>';
        $xml .= '</cServ>';
        $xml .= '</serv>';

        // Valores
        $valorServicos = number_format((float) ($valores['servicos'] ?? 0), 2, '.', '');
        $baseCalculo = (float) ($valores['servicos'] ?? 0) - (float) ($valores['deducoes'] ?? 0);

        $xml .= '<valores>';
        $xml .= '<vServPrest>';
        $xml .= '<vServ>' . $valorServicos . '</vServ>';
        $xml .= '</vServPrest>';
        $xml .= '<trib>';
        $xml .= '<tribMun>';
        $xml .= '<tribISSQN>' . $tribISSQN . '</tribISSQN>';
        $xml .= '<tpRetISSQN>' . (($valores['iss_retido'] ?? 'N') === 'S' ? '2' : '1') . '</tpRetISSQN>';

        // Aliquota ISS (somente se tributavel)
        if ($tribISSQN === 1) {
            $aliquota = number_format((float) ($valores['aliquota_iss'] ?? 0), 2, '.', '');
            $valorISS = number_format($baseCalculo * ((float) ($valores['aliquota_iss'] ?? 0) / 100), 2, '.', '');
            $xml .= '<pAliq>' . $aliquota . '</pAliq>';
            $xml .= '<vISSQN>' . $valorISS . '</vISSQN>';
        }

        $xml .= '</tribMun>';

        $xml .= '<totTrib>';
        $xml .= '<pTotTrib>';
        $xml .= '<pTotTribFed>0.00</pTotTribFed>';
        $xml .= '<pTotTribEst>0.00</pTotTribEst>';
        $xml .= '<pTotTribMun>0.00</pTotTribMun>';
        $xml .= '</pTotTrib>';
        $xml .= '</totTrib>';
        $xml .= '</trib>';
        $xml .= '</valores>';

        $xml .= $this->gerarIBSCBS($valores);

        $xml .= '</infDPS>';
        $xml .= '</DPS>';

        return $xml;
    }

    public function gerarXMLCancelamento(string $chaveAcesso, string $motivo, array $dados): string
    {
        $id = 'PRE' . $this->somenteDigitos($chaveAcesso) . '101101';
        $cnpjAutor = $this->somenteDigitos((string) ($dados['prestador_cnpj'] ?? ''));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<pedRegEvento xmlns="' . self::NAMESPACE . '" versao="1.01">';
        $xml .= '<infPedReg Id="' . $this->escapeXml($id) . '">';
        $xml .= '<tpAmb>' . (int) ($dados['ambiente'] ?? 2) . '</tpAmb>';
        $xml .= '<verAplic>7Carros v8.3</verAplic>';
        $xml .= '<dhEvento>' . $this->formatarDataISO(\App\Helpers\DateHelper::isoNow()) . '</dhEvento>';
        $xml .= '<CNPJAutor>' . $cnpjAutor . '</CNPJAutor>';
        $xml .= '<chNFSe>' . $this->escapeXml($chaveAcesso) . '</chNFSe>';
        $xml .= '<e101101>';
        $xml .= '<xDesc>Cancelamento de NFS-e</xDesc>';
        $xml .= '<cMotivo>9</cMotivo>';
        $xml .= '<xMotivo>' . $this->textoMaiusculo($motivo) . '</xMotivo>';
        $xml .= '</e101101>';
        $xml .= '</infPedReg>';
        $xml .= '</pedRegEvento>';

        return $xml;
    }

    public function parseRetorno(string $resposta): array
    {
        $resultado = [
            'sucesso' => false,
            'numero' => null,
            'chave_acesso' => null,
            'codigo_verificacao' => null,
            'aliquota_ibs' => 0.0,
            'valor_ibs' => 0.0,
            'aliquota_cbs' => 0.0,
            'valor_cbs' => 0.0,
            'xml_retorno' => $resposta,
            'erros' => [],
        ];

        // Tentar JSON primeiro (API Nacional retorna JSON)
        $json = json_decode($resposta, true);
        if ($json !== null) {
            $xmlNFSe = $this->decodificarXmlGZipB64($json['nfseXmlGZipB64'] ?? null);
            if ($xmlNFSe !== null) {
                $resultado['xml_retorno'] = $xmlNFSe;
                $this->preencherDadosAutorizacaoXml($xmlNFSe, $resultado);
            }

            $chaveAcesso = $json['chaveAcesso'] ?? $json['chNFSe'] ?? $json['nfse']['chNFSe'] ?? null;
            if (!empty($chaveAcesso)) {
                $resultado['chave_acesso'] = $chaveAcesso;
            }

            if (isset($json['nNFSe']) || isset($json['nfse']['nNFSe'])) {
                $resultado['numero'] = (int) ($json['nNFSe'] ?? $json['nfse']['nNFSe']);
            }

            if (isset($json['cVerif']) || isset($json['nfse']['cVerif'])) {
                $resultado['codigo_verificacao'] = $json['cVerif'] ?? $json['nfse']['cVerif'];
            }

            if (!empty($resultado['chave_acesso']) || !empty($resultado['numero'])) {
                $resultado['sucesso'] = true;
            } elseif (isset($json['erros']) || isset($json['erro'])) {
                $resultado['erros'] = $this->extrairErrosJson($json);
            }
            return $resultado;
        }

        // Tentar XML
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if ($doc->loadXML($resposta)) {
            $this->preencherDadosAutorizacaoXml($resposta, $resultado);

            $chNFSe = $doc->getElementsByTagName('chNFSe');
            if ($chNFSe->length > 0) {
                $resultado['sucesso'] = true;
                $resultado['chave_acesso'] = $chNFSe->item(0)->nodeValue;
            }

            $nNFSe = $doc->getElementsByTagName('nNFSe');
            if ($nNFSe->length > 0) {
                $resultado['numero'] = (int) $nNFSe->item(0)->nodeValue;
            }

            $cVerif = $doc->getElementsByTagName('cVerif');
            if ($cVerif->length > 0) {
                $resultado['codigo_verificacao'] = $cVerif->item(0)->nodeValue;
            }
        }
        libxml_clear_errors();

        return $resultado;
    }

    private function decodificarXmlGZipB64(mixed $valor): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }

        $binario = base64_decode($valor, true);
        if ($binario === false) {
            return null;
        }

        $xml = gzdecode($binario);
        return is_string($xml) && trim($xml) !== '' ? $xml : null;
    }

    private function preencherDadosAutorizacaoXml(string $xml, array &$resultado): void
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!$doc->loadXML($xml)) {
            libxml_clear_errors();
            return;
        }

        $cStat = $doc->getElementsByTagName('cStat');
        if ($cStat->length > 0 && trim($cStat->item(0)->nodeValue) === '100') {
            $resultado['sucesso'] = true;
        }

        $nNFSe = $doc->getElementsByTagName('nNFSe');
        if ($nNFSe->length > 0) {
            $resultado['numero'] = (int) $nNFSe->item(0)->nodeValue;
        }

        $chNFSe = $doc->getElementsByTagName('chNFSe');
        if ($chNFSe->length > 0) {
            $resultado['chave_acesso'] = trim($chNFSe->item(0)->nodeValue);
        }

        $cVerif = $doc->getElementsByTagName('cVerif');
        if ($cVerif->length > 0) {
            $resultado['codigo_verificacao'] = trim($cVerif->item(0)->nodeValue);
        }

        $resultado['aliquota_ibs'] = $this->valorPrimeiraTag($doc, 'pIBSUF')
            + $this->valorPrimeiraTag($doc, 'pIBSMun');
        $resultado['valor_ibs'] = $this->valorPrimeiraTag($doc, 'vIBSTot');
        $resultado['aliquota_cbs'] = $this->valorPrimeiraTag($doc, 'pCBS');
        $resultado['valor_cbs'] = $this->valorPrimeiraTag($doc, 'vCBS');

        libxml_clear_errors();
    }

    private function valorPrimeiraTag(\DOMDocument $doc, string $tag): float
    {
        $elementos = $doc->getElementsByTagName($tag);
        if ($elementos->length === 0) {
            return 0.0;
        }

        return (float) trim($elementos->item(0)->nodeValue);
    }

    public function parseRetornoCancelamento(string $resposta): array
    {
        $resultado = [
            'sucesso' => false,
            'mensagem' => '',
            'erros' => [],
        ];

        $json = json_decode($resposta, true);
        if ($json !== null) {
            if (isset($json['sucesso']) && $json['sucesso'] === true) {
                $resultado['sucesso'] = true;
                $resultado['mensagem'] = $json['mensagem'] ?? 'NFS-e cancelada com sucesso.';
            } else {
                $resultado['erros'] = $this->extrairErrosJson($json);
            }
        }

        return $resultado;
    }

    private function extrairErrosJson(array $json): array
    {
        $itens = [];

        if (!empty($json['erros']) && is_array($json['erros'])) {
            $itens = $json['erros'];
        } elseif (!empty($json['erro']) && is_array($json['erro'])) {
            $itens = array_is_list($json['erro']) ? $json['erro'] : [$json['erro']];
        }

        $erros = [];
        foreach ($itens as $erro) {
            if (!is_array($erro)) {
                continue;
            }

            $codigo = (string) ($erro['Codigo'] ?? $erro['codigo'] ?? $erro['cod'] ?? 'ERRO_DESCONHECIDO');
            $descricao = (string) ($erro['Descricao'] ?? $erro['descricao'] ?? $erro['mensagem'] ?? $erro['Mensagem'] ?? $erro['msg'] ?? '');
            $complemento = (string) ($erro['Complemento'] ?? $erro['complemento'] ?? '');
            $mensagem = trim($descricao . ($complemento !== '' ? ' - ' . $complemento : ''));

            $erros[] = [
                'codigo' => $codigo !== '' ? $codigo : 'ERRO_DESCONHECIDO',
                'mensagem' => $mensagem,
            ];
        }

        return $erros;
    }

    /**
     * Prepara XML para envio: gzip + Base64
     */
    public function prepararParaEnvio(string $xml): string
    {
        $gzipped = gzencode($xml, 9);
        return base64_encode($gzipped);
    }

    /**
     * Gera ID DPS de 45 caracteres
     * DPS + cMun(7) + tpInsc(1) + nInsc(14) + serie(5) + nDPS(15)
     */
    private function gerarIdDPS(array $dados): string
    {
        $cMun = str_pad($this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')), 7, '0', STR_PAD_LEFT);
        $cnpj = $this->somenteDigitos((string) ($dados['prestador']['cnpj'] ?? ''));
        $tpInsc = strlen($cnpj) === 14 ? '2' : '1';
        $nInsc = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
        $serie = str_pad(substr($this->normalizarSerie($dados['serie'] ?? null), 0, 5), 5, '0', STR_PAD_LEFT);
        $nDPS = str_pad((string) ($dados['numero'] ?? '0'), 15, '0', STR_PAD_LEFT);

        return 'DPS' . $cMun . $tpInsc . $nInsc . $serie . $nDPS;
    }

    /**
     * Converte NBS: 1.1101.11 -> 111011100 (9 digitos)
     */
    private function converterNBS(string $nbs): string
    {
        $limpo = $this->somenteDigitos($nbs);
        return str_pad($limpo, 9, '0', STR_PAD_RIGHT);
    }

    /**
     * Mapeia trib_issqn para cTribNac
     */
    private function mapearCTribNac(int $tribISSQN): string
    {
        return match ($tribISSQN) {
            1 => '010101', // Tributavel
            2 => '020101', // Imunidade
            3 => '030101', // Exportacao Servico
            4 => '990101', // Nao Incidencia
            default => '990101',
        };
    }

    private function mapearOpcaoSimples(int $regime): string
    {
        return match ($regime) {
            1 => '3',
            4 => '2',
            default => '1',
        };
    }

    private function gerarIBSCBS(array $valores): string
    {
        if (($valores['preencher_ibscbs'] ?? 'N') !== 'S') {
            return '';
        }

        $cIndOp = $this->somenteDigitos((string) ($valores['c_ind_op_ibscbs'] ?? ''));
        $cst = $this->somenteDigitos((string) ($valores['cst_ibscbs'] ?? ''));
        $cClassTrib = $this->somenteDigitos((string) ($valores['c_class_trib_ibscbs'] ?? ''));

        if (strlen($cIndOp) !== 6 || strlen($cst) !== 3 || strlen($cClassTrib) !== 6) {
            throw new \InvalidArgumentException('Configuração de IBS/CBS incompleta para a DPS Nacional.');
        }

        $xml = '<IBSCBS>';
        $xml .= '<finNFSe>0</finNFSe>';
        $xml .= '<cIndOp>' . $cIndOp . '</cIndOp>';
        $xml .= '<indDest>0</indDest>';
        $xml .= '<valores><trib><gIBSCBS>';
        $xml .= '<CST>' . $cst . '</CST>';
        $xml .= '<cClassTrib>' . $cClassTrib . '</cClassTrib>';
        $xml .= '</gIBSCBS></trib></valores>';
        $xml .= '</IBSCBS>';

        return $xml;
    }

    /**
     * Formata data para ISO 8601 com timezone
     */
    private function formatarDataISO(string $data): string
    {
        try {
            $timezone = new \DateTimeZone(self::FISCAL_TIMEZONE);
            return (new \DateTimeImmutable($data))->setTimezone($timezone)->format('Y-m-d\TH:i:sP');
        } catch (\Throwable) {
            return (new \DateTimeImmutable('now', new \DateTimeZone(self::FISCAL_TIMEZONE)))->format('Y-m-d\TH:i:sP');
        }
    }

    private function gerarEnderecoTomador(mixed $endereco): string
    {
        if (is_string($endereco)) {
            $decoded = json_decode($endereco, true);
            $endereco = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($endereco)) {
            return '';
        }

        $pais = strtoupper(trim((string) ($endereco['pais'] ?? 'BR')));
        if ($pais !== '' && $pais !== 'BR') {
            $campos = [];
            foreach (['cep', 'cidade', 'uf', 'logradouro', 'numero', 'bairro'] as $campo) {
                $campos[$campo] = trim((string) ($endereco[$campo] ?? ''));
            }
            if (in_array('', $campos, true)) {
                return '';
            }

            $xml = '<end><endExt><cPais>' . $this->escapeXml($pais) . '</cPais>';
            $xml .= '<cEndPost>' . $this->escapeXml(mb_substr($campos['cep'], 0, 11)) . '</cEndPost>';
            $xml .= '<xCidade>' . $this->textoMaiusculo($campos['cidade']) . '</xCidade>';
            $xml .= '<xEstProvReg>' . $this->textoMaiusculo($campos['uf']) . '</xEstProvReg></endExt>';
            $xml .= '<xLgr>' . $this->textoMaiusculo($campos['logradouro']) . '</xLgr>';
            $xml .= '<nro>' . $this->textoMaiusculo($campos['numero']) . '</nro>';
            if (!empty($endereco['complemento'])) {
                $xml .= '<xCpl>' . $this->textoMaiusculo((string) $endereco['complemento']) . '</xCpl>';
            }
            return $xml . '<xBairro>' . $this->textoMaiusculo($campos['bairro']) . '</xBairro></end>';
        }

        $codigoMunicipio = $this->somenteDigitos((string) ($endereco['codigo_municipio'] ?? ''));
        $cep = $this->somenteDigitos((string) ($endereco['cep'] ?? ''));

        if (strlen($codigoMunicipio) !== 7 || strlen($cep) !== 8) {
            return '';
        }

        $xml = '<end>';
        $xml .= '<endNac>';
        $xml .= '<cMun>' . $codigoMunicipio . '</cMun>';
        $xml .= '<CEP>' . $cep . '</CEP>';
        $xml .= '</endNac>';

        if (!empty($endereco['logradouro'])) {
            $xml .= '<xLgr>' . $this->textoMaiusculo((string) $endereco['logradouro']) . '</xLgr>';
        }
        if (!empty($endereco['numero'])) {
            $xml .= '<nro>' . $this->textoMaiusculo((string) $endereco['numero']) . '</nro>';
        }
        if (!empty($endereco['complemento'])) {
            $xml .= '<xCpl>' . $this->textoMaiusculo((string) $endereco['complemento']) . '</xCpl>';
        }
        if (!empty($endereco['bairro'])) {
            $xml .= '<xBairro>' . $this->textoMaiusculo((string) $endereco['bairro']) . '</xBairro>';
        }

        $xml .= '</end>';

        return $xml;
    }

    private function textoMaiusculo(string $texto): string
    {
        return $this->escapeXml(mb_strtoupper($texto, 'UTF-8'));
    }

    private function normalizarSerie(mixed $serie): string
    {
        $serie = trim((string) ($serie ?? ''));
        return $serie !== '' ? $serie : 'DPS';
    }

    private function escapeXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
