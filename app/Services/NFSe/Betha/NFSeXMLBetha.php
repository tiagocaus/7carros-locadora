<?php

namespace App\Services\NFSe\Betha;

use App\Services\NFSe\NFSeXMLInterface;

/**
 * Geracao de XML Betha Cloud - DPS.
 */
class NFSeXMLBetha implements NFSeXMLInterface
{
    private const NAMESPACE = 'http://www.betha.com.br/e-nota-dps';
    private const VERSAO = '1.00';
    private const FISCAL_TIMEZONE = 'America/Sao_Paulo';

    public function gerarXML(array $dados): string
    {
        $idDPS = $this->gerarIdDPS($dados);
        $prest = $dados['prestador'] ?? [];
        $tomador = $dados['tomador'] ?? [];
        $servico = $dados['servico'] ?? [];
        $valores = $dados['valores'] ?? [];
        $tribISSQN = (int) ($valores['trib_issqn'] ?? 4);
        $valorServicos = number_format((float) ($valores['servicos'] ?? 0), 2, '.', '');
        $baseCalculo = max(0, (float) ($valores['base_calculo'] ?? ((float) ($valores['servicos'] ?? 0) - (float) ($valores['deducoes'] ?? 0))));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<DPS xmlns="' . self::NAMESPACE . '" versao="' . self::VERSAO . '">';
        $xml .= '<infDPS id="' . $idDPS . '">';
        $xml .= '<tpAmb>' . (int) ($dados['ambiente'] ?? 2) . '</tpAmb>';
        $xml .= '<dhEmi>' . $this->formatarDataISO($dados['data_emissao'] ?? date('Y-m-d\TH:i:sP')) . '</dhEmi>';
        $xml .= '<verAplic>7Carros v8.3</verAplic>';
        $xml .= '<serie>' . htmlspecialchars((string) ($dados['serie'] ?? '1')) . '</serie>';
        $xml .= '<nDPS>' . (int) ($dados['numero'] ?? 0) . '</nDPS>';
        $xml .= '<dCompet>' . htmlspecialchars((string) ($dados['data_competencia'] ?? date('Y-m-d'))) . '</dCompet>';
        $xml .= '<tpEmit>1</tpEmit>';
        $xml .= '<cLocEmi>' . $this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')) . '</cLocEmi>';

        $xml .= '<prest>';
        $xml .= '<CNPJ>' . $this->somenteDigitos((string) ($prest['cnpj'] ?? '')) . '</CNPJ>';
        if (($prest['enviar_im'] ?? 'N') === 'S' && !empty($prest['inscricao_municipal'])) {
            $xml .= '<IM>' . $this->somenteDigitos((string) $prest['inscricao_municipal']) . '</IM>';
        }
        if (!empty($prest['telefone'])) {
            $xml .= '<fone>' . $this->somenteDigitos((string) $prest['telefone']) . '</fone>';
        }
        if (!empty($prest['email'])) {
            $xml .= '<email>' . htmlspecialchars((string) $prest['email']) . '</email>';
        }
        $xml .= '<regTrib>';
        $xml .= '<opSimpNac>' . $this->mapearOpcaoSimples((int) ($prest['regime_tributario'] ?? 1)) . '</opSimpNac>';
        if ((int) ($prest['regime_tributario'] ?? 1) === 1) {
            $xml .= '<regApTribSN>' . (int) ($prest['reg_apuracao_sn'] ?? 1) . '</regApTribSN>';
        }
        $xml .= '<regEspTrib>0</regEspTrib>';
        $xml .= '</regTrib>';
        $xml .= '</prest>';

        $xml .= '<toma>';
        $cpfCnpj = $this->somenteDigitos((string) ($tomador['cpf_cnpj'] ?? ''));
        if (strlen($cpfCnpj) === 14) {
            $xml .= '<CNPJ>' . $cpfCnpj . '</CNPJ>';
        } elseif (strlen($cpfCnpj) === 11) {
            $xml .= '<CPF>' . $cpfCnpj . '</CPF>';
        }
        $xml .= '<xNome>' . $this->textoMaiusculo((string) ($tomador['nome'] ?? '')) . '</xNome>';
        $xml .= '</toma>';

        $xml .= '<serv>';
        $xml .= '<locPrest><cLocPrestacao>' . $this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')) . '</cLocPrestacao></locPrest>';
        $xml .= '<cServ>';
        $xml .= '<cTribNac>' . $this->mapearCTribNac($tribISSQN) . '</cTribNac>';
        $xml .= '<xDescServ>' . $this->textoMaiusculo((string) ($servico['descricao'] ?? '')) . '</xDescServ>';
        $xml .= '<cNBS>' . $this->converterNBS((string) ($servico['codigo'] ?? '1.1101.11')) . '</cNBS>';
        $xml .= '</cServ>';
        $xml .= '</serv>';

        $xml .= '<valores>';
        $xml .= '<vServPrest><vServ>' . $valorServicos . '</vServ></vServPrest>';
        $xml .= '<trib><tribMun>';
        $xml .= '<tribISSQN>' . $tribISSQN . '</tribISSQN>';
        $xml .= '<tpRetISSQN>' . (($valores['iss_retido'] ?? 'N') === 'S' ? '2' : '1') . '</tpRetISSQN>';
        if ($tribISSQN === 1) {
            $xml .= '<pAliq>' . number_format((float) ($valores['aliquota_iss'] ?? 0), 2, '.', '') . '</pAliq>';
            $xml .= '<vISSQN>' . number_format((float) ($valores['valor_iss'] ?? 0), 2, '.', '') . '</vISSQN>';
        }
        $xml .= '</tribMun>';

        $aliquotaIBS = (float) ($valores['aliquota_ibs'] ?? 0.10);
        $aliquotaCBS = (float) ($valores['aliquota_cbs'] ?? 0.90);
        $valorIBS = (float) $valorServicos * ($aliquotaIBS / 100);
        $valorCBS = (float) $valorServicos * ($aliquotaCBS / 100);
        $valorISSTrib = $tribISSQN === 1
            ? (float) ($valores['valor_iss'] ?? ($baseCalculo * ((float) ($valores['aliquota_iss'] ?? 0) / 100)))
            : 0;

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
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<RecepcionarEventoCancelamentoEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<chaveAcesso>' . htmlspecialchars($chaveAcesso) . '</chaveAcesso>';
        $xml .= '<motivo>' . $this->textoMaiusculo($motivo) . '</motivo>';
        $xml .= '</RecepcionarEventoCancelamentoEnvio>';

        return $xml;
    }

    public function parseRetorno(string $resposta): array
    {
        $resultado = $this->resultadoBase($resposta);
        $doc = $this->carregarXML($resposta);
        if (!$doc) {
            return $resultado;
        }

        $protocolo = $this->valorTag($doc, 'protocolo');
        if ($protocolo !== '') {
            $resultado['processando'] = true;
            $resultado['protocolo'] = $protocolo;
        }

        $status = $this->valorTag($doc, 'statusProcessamento');
        if ($this->statusSucesso($status)) {
            $resultado['sucesso'] = true;
            $resultado['numero'] = (int) ($this->valorTag($doc, 'numeroNotaFiscal') ?: $this->valorTag($doc, 'numeroDps'));
            $resultado['chave_acesso'] = $this->valorTag($doc, 'chaveAcesso');
            $resultado['codigo_verificacao'] = $this->valorTag($doc, 'codigoVerificacao');
        }

        $resultado['erros'] = $this->parseMensagens($doc);

        return $resultado;
    }

    public function parseRetornoCancelamento(string $resposta): array
    {
        $doc = $this->carregarXML($resposta);
        if (!$doc) {
            return ['sucesso' => false, 'mensagem' => '', 'erros' => []];
        }

        $erros = $this->parseMensagens($doc);
        $status = $this->valorTag($doc, 'statusProcessamento');

        return [
            'sucesso' => empty($erros) && ($this->statusSucesso($status) || stripos($resposta, 'sucesso') !== false),
            'mensagem' => $this->valorTag($doc, 'mensagem') ?: 'Evento de cancelamento processado.',
            'erros' => $erros,
        ];
    }

    public function parseRetornoStatus(string $resposta): array
    {
        return $this->parseRetorno($resposta);
    }

    private function resultadoBase(string $resposta): array
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

    private function carregarXML(string $xml): ?\DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok = $doc->loadXML($xml);
        libxml_clear_errors();

        return $ok ? $doc : null;
    }

    private function valorTag(\DOMDocument $doc, string $tag): string
    {
        $nodes = $doc->getElementsByTagNameNS('*', $tag);
        if ($nodes->length === 0) {
            $nodes = $doc->getElementsByTagName($tag);
        }

        return $nodes->length > 0 ? trim((string) $nodes->item(0)->nodeValue) : '';
    }

    private function parseMensagens(\DOMDocument $doc): array
    {
        $erros = [];
        $mensagens = $doc->getElementsByTagNameNS('*', 'mensagem');
        if ($mensagens->length === 0) {
            $mensagens = $doc->getElementsByTagName('mensagem');
        }

        foreach ($mensagens as $mensagem) {
            $codigo = '';
            $texto = '';
            foreach ($mensagem->childNodes as $child) {
                if ($child->localName === 'codigo') {
                    $codigo = trim((string) $child->nodeValue);
                }
                if ($child->localName === 'mensagem' || $child->localName === 'descricao') {
                    $texto = trim((string) $child->nodeValue);
                }
            }
            if ($codigo !== '' || $texto !== '') {
                $erros[] = ['codigo' => $codigo ?: 'ERRO_DESCONHECIDO', 'mensagem' => $texto];
            }
        }

        return $erros;
    }

    private function gerarIdDPS(array $dados): string
    {
        $cMun = str_pad($this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')), 7, '0', STR_PAD_LEFT);
        $cnpj = $this->somenteDigitos((string) ($dados['prestador']['cnpj'] ?? ''));
        $tpInsc = strlen($cnpj) === 14 ? '2' : '1';
        $nInsc = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
        $serie = str_pad(substr((string) ($dados['serie'] ?? '1'), 0, 5), 5, '0', STR_PAD_LEFT);
        $nDPS = str_pad((string) ($dados['numero'] ?? '0'), 15, '0', STR_PAD_LEFT);

        return 'DPS' . $cMun . $tpInsc . $nInsc . $serie . $nDPS;
    }

    private function mapearOpcaoSimples(int $regime): string
    {
        return match ($regime) {
            1 => '3',
            4 => '2',
            default => '1',
        };
    }

    private function mapearCTribNac(int $tribISSQN): string
    {
        return match ($tribISSQN) {
            1 => '010101',
            2 => '020101',
            3 => '030101',
            default => '990101',
        };
    }

    private function converterNBS(string $nbs): string
    {
        return str_pad(str_replace('.', '', $nbs), 9, '0', STR_PAD_RIGHT);
    }

    private function formatarDataISO(string $data): string
    {
        try {
            $timezone = new \DateTimeZone(self::FISCAL_TIMEZONE);
            return (new \DateTimeImmutable($data))->setTimezone($timezone)->format('Y-m-d\TH:i:sP');
        } catch (\Throwable) {
            return (new \DateTimeImmutable('now', new \DateTimeZone(self::FISCAL_TIMEZONE)))->format('Y-m-d\TH:i:sP');
        }
    }

    private function textoMaiusculo(string $texto): string
    {
        return mb_strtoupper(htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    private function statusSucesso(string $status): bool
    {
        return stripos($status, 'sucesso') !== false || stripos($status, 'autoriz') !== false;
    }
}
