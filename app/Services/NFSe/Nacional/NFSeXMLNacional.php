<?php

namespace App\Services\NFSe\Nacional;

use App\Services\NFSe\NFSeXMLInterface;

/**
 * Geracao de XML Nacional - DPS (Declaracao de Prestacao de Servico)
 *
 * Formato SEFIN Nacional.
 * Namespace: http://www.sped.fazenda.gov.br/nfse
 * Versao: 1.00
 * Textos: MAIUSCULO obrigatorio
 */
class NFSeXMLNacional implements NFSeXMLInterface
{
    private const NAMESPACE = 'http://www.sped.fazenda.gov.br/nfse';
    private const VERSAO = '1.00';

    public function gerarXML(array $dados): string
    {
        $idDPS = $this->gerarIdDPS($dados);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<DPS xmlns="' . self::NAMESPACE . '" versao="' . self::VERSAO . '">';
        $xml .= '<infDPS Id="' . $idDPS . '" versao="' . self::VERSAO . '">';

        // Identificacao
        $xml .= '<tpAmb>' . ($dados['ambiente'] ?? 2) . '</tpAmb>';
        $xml .= '<dhEmi>' . $this->formatarDataISO($dados['data_emissao'] ?? date('Y-m-d\TH:i:sP')) . '</dhEmi>';
        $xml .= '<verAplic>7Carros1.0</verAplic>';
        $xml .= '<serie>' . htmlspecialchars($dados['serie'] ?? 'DPS') . '</serie>';
        $xml .= '<nDPS>' . ($dados['numero'] ?? 0) . '</nDPS>';
        $xml .= '<dCompet>' . ($dados['data_competencia'] ?? date('Y-m-d')) . '</dCompet>';
        $xml .= '<tpEmit>1</tpEmit>';
        $xml .= '<cLocEmi>' . ($dados['municipio_codigo'] ?? '') . '</cLocEmi>';

        // Prestador
        $prest = $dados['prestador'] ?? [];
        $xml .= '<prest>';
        $xml .= '<CNPJ>' . preg_replace('/\D/', '', $prest['cnpj'] ?? '') . '</CNPJ>';
        if (($prest['enviar_im'] ?? 'N') === 'S' && !empty($prest['inscricao_municipal'])) {
            $xml .= '<IM>' . preg_replace('/\D/', '', $prest['inscricao_municipal']) . '</IM>';
        }
        if (!empty($prest['telefone'])) {
            $xml .= '<fone>' . preg_replace('/\D/', '', $prest['telefone']) . '</fone>';
        }
        if (!empty($prest['email'])) {
            $xml .= '<email>' . htmlspecialchars($prest['email']) . '</email>';
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
        $cpfCnpj = preg_replace('/\D/', '', $tomador['cpf_cnpj'] ?? '');
        if (strlen($cpfCnpj) === 14) {
            $xml .= '<CNPJ>' . $cpfCnpj . '</CNPJ>';
        } elseif (strlen($cpfCnpj) === 11) {
            $xml .= '<CPF>' . $cpfCnpj . '</CPF>';
        }
        $xml .= '<xNome>' . mb_strtoupper(htmlspecialchars($tomador['nome'] ?? '')) . '</xNome>';
        $xml .= '</toma>';

        // Servico
        $servico = $dados['servico'] ?? [];
        $valores = $dados['valores'] ?? [];
        $xml .= '<serv>';
        $xml .= '<locPrest>';
        $xml .= '<cLocPrestacao>' . ($dados['municipio_codigo'] ?? '') . '</cLocPrestacao>';
        $xml .= '</locPrest>';
        $xml .= '<cServ>';

        // cTribNac baseado no tipo de tributacao
        $tribISSQN = (int) ($valores['trib_issqn'] ?? 4);
        $cTribNac = $this->mapearCTribNac($tribISSQN);
        $xml .= '<cTribNac>' . $cTribNac . '</cTribNac>';
        $xml .= '<xDescServ>' . mb_strtoupper(htmlspecialchars($servico['descricao'] ?? '')) . '</xDescServ>';

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

        // Tributos totais
        $aliquotaIBS = (float) ($valores['aliquota_ibs'] ?? 0.10);
        $aliquotaCBS = (float) ($valores['aliquota_cbs'] ?? 0.90);
        $valorIBS = (float) $valorServicos * ($aliquotaIBS / 100);
        $valorCBS = (float) $valorServicos * ($aliquotaCBS / 100);
        $valorISSTrib = $tribISSQN === 1 ? $baseCalculo * ((float) ($valores['aliquota_iss'] ?? 0) / 100) : 0;

        $xml .= '<totTrib>';
        $xml .= '<vTotTrib>';
        $xml .= '<vTotTribFed>' . number_format($valorCBS, 2, '.', '') . '</vTotTribFed>';
        $xml .= '<vTotTribEst>' . number_format($valorIBS, 2, '.', '') . '</vTotTribEst>';
        $xml .= '<vTotTribMun>' . number_format($valorISSTrib, 2, '.', '') . '</vTotTribMun>';
        $xml .= '</vTotTrib>';
        $xml .= '</totTrib>';
        $xml .= '</trib>';
        $xml .= '</valores>';

        $xml .= '</infDPS>';
        $xml .= '</DPS>';

        return $xml;
    }

    public function gerarXMLCancelamento(string $chaveAcesso, string $motivo, array $dados): string
    {
        $id = 'PRE' . preg_replace('/\D/', '', $chaveAcesso);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<pedRegEvento xmlns="' . self::NAMESPACE . '" versao="1.01">';
        $xml .= '<infPedReg Id="' . htmlspecialchars($id) . '">';
        $xml .= '<tpAmb>' . (int) ($dados['ambiente'] ?? 2) . '</tpAmb>';
        $xml .= '<chNFSe>' . htmlspecialchars($chaveAcesso) . '</chNFSe>';
        $xml .= '<dhEvento>' . date('Y-m-d\TH:i:sP') . '</dhEvento>';
        $xml .= '<tpEvento>101101</tpEvento>';
        $xml .= '<xDescEvento>Cancelamento de NFS-e</xDescEvento>';
        $xml .= '<detEvento><evCancNFSe><xJust>' . mb_strtoupper(htmlspecialchars($motivo)) . '</xJust></evCancNFSe></detEvento>';
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
            'xml_retorno' => $resposta,
            'erros' => [],
        ];

        // Tentar JSON primeiro (API Nacional retorna JSON)
        $json = json_decode($resposta, true);
        if ($json !== null) {
            if (isset($json['chNFSe']) || isset($json['nfse'])) {
                $resultado['sucesso'] = true;
                $resultado['chave_acesso'] = $json['chNFSe'] ?? $json['nfse']['chNFSe'] ?? null;
                $resultado['numero'] = $json['nNFSe'] ?? $json['nfse']['nNFSe'] ?? null;
                $resultado['codigo_verificacao'] = $json['cVerif'] ?? $json['nfse']['cVerif'] ?? null;
            } elseif (isset($json['erros']) || isset($json['erro'])) {
                $erros = $json['erros'] ?? [$json['erro'] ?? []];
                foreach ($erros as $erro) {
                    $resultado['erros'][] = [
                        'codigo' => $erro['codigo'] ?? $erro['cod'] ?? 'ERRO_DESCONHECIDO',
                        'mensagem' => $erro['mensagem'] ?? $erro['msg'] ?? $erro['descricao'] ?? '',
                    ];
                }
            }
            return $resultado;
        }

        // Tentar XML
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if ($doc->loadXML($resposta)) {
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
            } elseif (!empty($json['erros'])) {
                foreach ($json['erros'] as $erro) {
                    $resultado['erros'][] = [
                        'codigo' => $erro['codigo'] ?? 'ERRO_DESCONHECIDO',
                        'mensagem' => $erro['mensagem'] ?? '',
                    ];
                }
            }
        }

        return $resultado;
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
        $cMun = str_pad($dados['municipio_codigo'] ?? '0000000', 7, '0', STR_PAD_LEFT);
        $cnpj = preg_replace('/\D/', '', $dados['prestador']['cnpj'] ?? '');
        $tpInsc = strlen($cnpj) === 14 ? '2' : '1';
        $nInsc = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
        $serie = str_pad(substr((string) ($dados['serie'] ?? 'DPS'), 0, 5), 5, '0', STR_PAD_RIGHT);
        $nDPS = str_pad($dados['numero'] ?? '0', 15, '0', STR_PAD_LEFT);

        return 'DPS' . $cMun . $tpInsc . $nInsc . $serie . $nDPS;
    }

    /**
     * Converte NBS: 1.1101.11 -> 111011100 (9 digitos)
     */
    private function converterNBS(string $nbs): string
    {
        $limpo = str_replace('.', '', $nbs);
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

    /**
     * Formata data para ISO 8601 com timezone
     */
    private function formatarDataISO(string $data): string
    {
        try {
            $dt = new \DateTime($data);
            return $dt->format('Y-m-d\TH:i:sP');
        } catch (\Exception) {
            return date('Y-m-d\TH:i:sP');
        }
    }
}
