<?php

namespace App\Services\NFSe\Betha;

use App\Services\NFSe\NFSeXMLInterface;

/**
 * Geracao de XML Betha Cloud - DPS.
 */
class NFSeXMLBetha implements NFSeXMLInterface
{
    private const NAMESPACE = 'http://www.betha.com.br/e-nota-dps';
    private const VERSAO = '1.01';
    private const VERSAO_EVENTO = '1.0';
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
        $xml .= '<dhEmi>' . $this->formatarDataISO($dados['data_emissao'] ?? \App\Helpers\DateHelper::isoNow()) . '</dhEmi>';
        $xml .= '<verAplic>7Carros v8.3</verAplic>';
        $xml .= '<serie>' . htmlspecialchars((string) ($dados['serie'] ?? '1')) . '</serie>';
        $xml .= '<nDPS>' . (int) ($dados['numero'] ?? 0) . '</nDPS>';
        $xml .= '<dCompet>' . htmlspecialchars((string) ($dados['data_competencia'] ?? today())) . '</dCompet>';
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

        $xml .= '<serv>';
        $xml .= '<locPrest><cLocPrestacao>' . $this->somenteDigitos((string) ($dados['municipio_codigo'] ?? '')) . '</cLocPrestacao></locPrest>';
        $xml .= '<cServ>';
        $codigoTributacaoNacional = $this->somenteDigitos((string) ($servico['codigo_tributacao_nacional'] ?? ''));
        $xml .= '<cTribNac>' . (strlen($codigoTributacaoNacional) === 6
            ? $codigoTributacaoNacional
            : $this->mapearCTribNac($tribISSQN)) . '</cTribNac>';
        $xml .= '<xDescServ>' . $this->textoMaiusculo((string) ($servico['descricao'] ?? '')) . '</xDescServ>';
        $xml .= '<cNBS>' . $this->converterNBS(
            (string) ($servico['codigo'] ?? '1.1101.11'),
            (string) ($servico['descricao'] ?? '')
        ) . '</cNBS>';
        $xml .= '</cServ>';
        $xml .= $this->gerarComercioExterior($dados, $tomador);
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

        $aliquotaIBS = (float) ($valores['aliquota_ibs'] ?? 0);
        $aliquotaCBS = (float) ($valores['aliquota_cbs'] ?? 0);
        $valorIBS = (float) ($valores['valor_ibs'] ?? ((float) $valorServicos * ($aliquotaIBS / 100)));
        $valorCBS = (float) ($valores['valor_cbs'] ?? ((float) $valorServicos * ($aliquotaCBS / 100)));
        $valorISSTrib = $tribISSQN === 1
            ? (float) ($valores['valor_iss'] ?? ($baseCalculo * ((float) ($valores['aliquota_iss'] ?? 0) / 100)))
            : 0;

        $xml .= '<totTrib>';
        if ($valorCBS > 0 || $valorIBS > 0 || $valorISSTrib > 0) {
            $xml .= '<vTotTrib>';
            $xml .= '<vTotTribFed>' . number_format($valorCBS, 2, '.', '') . '</vTotTribFed>';
            $xml .= '<vTotTribEst>' . number_format($valorIBS, 2, '.', '') . '</vTotTribEst>';
            $xml .= '<vTotTribMun>' . number_format($valorISSTrib, 2, '.', '') . '</vTotTribMun>';
            $xml .= '</vTotTrib>';
        } else {
            $xml .= '<indTotTrib>0</indTotTrib>';
        }
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
        $chaveAcesso = $this->somenteDigitos($chaveAcesso);
        $cnpjAutor = $this->somenteDigitos((string) ($dados['prestador_cnpj'] ?? ''));
        $ambiente = (int) ($dados['ambiente'] ?? 2);
        $dhEvento = $this->formatarDataISO($dados['data_evento'] ?? \App\Helpers\DateHelper::isoNow());
        $idPedido = 'PRE' . $chaveAcesso . '101101';
        $idEvento = 'EVT' . $chaveAcesso . '101101001';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<RecepcionarEventoCancelamentoEnvio xmlns="' . self::NAMESPACE . '">';
        $xml .= '<evento versao="' . self::VERSAO_EVENTO . '">';
        $xml .= '<infEvento id="' . $idEvento . '">';
        $xml .= '<verAplic>7Carros v8.3</verAplic>';
        $xml .= '<ambGer>' . $ambiente . '</ambGer>';
        $xml .= '<nSeqEvento>1</nSeqEvento>';
        $xml .= '<dhProc>' . $dhEvento . '</dhProc>';
        $xml .= '<pedRegEvento versao="' . self::VERSAO_EVENTO . '">';
        $xml .= '<infPedReg id="' . $idPedido . '">';
        $xml .= '<chNFSe>' . $chaveAcesso . '</chNFSe>';
        $xml .= '<CNPJAutor>' . $cnpjAutor . '</CNPJAutor>';
        $xml .= '<dhEvento>' . $dhEvento . '</dhEvento>';
        $xml .= '<tpAmb>' . $ambiente . '</tpAmb>';
        $xml .= '<verAplic>7Carros v8.3</verAplic>';
        $xml .= '<e101101>';
        $xml .= '<xDesc>Cancelamento de NFS-e</xDesc>';
        $xml .= '<cMotivo>9</cMotivo>';
        $xml .= '<xMotivo>' . $this->textoMaiusculo($motivo) . '</xMotivo>';
        $xml .= '</e101101>';
        $xml .= '</infPedReg>';
        $xml .= '</pedRegEvento>';
        $xml .= '</infEvento>';
        $xml .= '</evento>';
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
            return [
                'sucesso' => false,
                'processando' => false,
                'protocolo' => null,
                'status' => '',
                'mensagem' => '',
                'erros' => [],
            ];
        }

        $erros = $this->parseMensagens($doc);
        $status = $this->valorTag($doc, 'statusProcessamento') ?: $this->valorTag($doc, 'status');
        $protocolo = $this->valorTag($doc, 'protocolo');
        $mensagemErro = $this->valorTag($doc, 'mensagemErro');
        if ($mensagemErro !== '' && empty($erros)) {
            $erros[] = ['codigo' => 'ERRO_CANCELAMENTO_BETHA', 'mensagem' => $mensagemErro];
        }

        $sucesso = empty($erros) && $this->statusSucesso($status);
        $statusNormalizado = mb_strtolower(trim($status), 'UTF-8');
        $statusFinalComErro = str_contains($statusNormalizado, 'erro')
            || str_contains($statusNormalizado, 'rejeitad')
            || str_contains($statusNormalizado, 'falha');
        if (empty($erros) && $statusFinalComErro) {
            $erros[] = [
                'codigo' => 'ERRO_CANCELAMENTO_BETHA',
                'mensagem' => $status !== '' ? $status : 'Cancelamento rejeitado pela Betha.',
            ];
        }

        // A recepcao e assincrona: protocolo sem sucesso ou erro final explicito
        // significa que a Betha aceitou o pedido e ainda vai processa-lo.
        $processando = !$sucesso && empty($erros) && $protocolo !== '';
        $mensagem = $erros[0]['mensagem']
            ?? ($status !== '' ? $status : 'Evento de cancelamento recebido pela Betha.');

        return [
            'sucesso' => $sucesso,
            'processando' => $processando,
            'protocolo' => $protocolo !== '' ? $protocolo : null,
            'status' => $status,
            'mensagem' => $mensagem,
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

    private function converterNBS(string $nbs, string $descricaoServico = ''): string
    {
        $limpo = $this->somenteDigitos($nbs);
        if ($limpo === '999999999' && $this->ehLocacaoVeiculo($descricaoServico)) {
            return '111011100';
        }

        return str_pad($limpo, 9, '0', STR_PAD_RIGHT);
    }

    private function ehLocacaoVeiculo(string $descricao): bool
    {
        $texto = mb_strtolower($descricao, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return str_contains($texto, 'locacao')
            && (str_contains($texto, 'veiculo') || str_contains($texto, 'automotor'));
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

            $xml = '<end><endExt><cPais>' . htmlspecialchars($pais, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</cPais>';
            $xml .= '<cEndPost>' . htmlspecialchars(mb_substr($campos['cep'], 0, 11), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</cEndPost>';
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

    private function gerarComercioExterior(array $dados, array $tomador): string
    {
        if (($tomador['tipo'] ?? '') !== 'ES') {
            return '';
        }

        $comercioExterior = $dados['comercio_exterior'] ?? null;
        $camposObrigatorios = [
            'mdPrestacao',
            'vincPrest',
            'tpMoeda',
            'vServMoeda',
            'mecAFComexP',
            'mecAFComexT',
            'movTempBens',
            'mdic',
        ];
        if (!is_array($comercioExterior)) {
            throw new \InvalidArgumentException('Dados de comércio exterior não informados para o tomador estrangeiro.');
        }
        foreach ($camposObrigatorios as $campo) {
            if (!array_key_exists($campo, $comercioExterior) || $comercioExterior[$campo] === '') {
                throw new \InvalidArgumentException("Campo {$campo} não informado no comércio exterior da DPS Betha.");
            }
        }

        $xml = '<comExt>';
        $xml .= '<mdPrestacao>' . (int) $comercioExterior['mdPrestacao'] . '</mdPrestacao>';
        $xml .= '<vincPrest>' . (int) $comercioExterior['vincPrest'] . '</vincPrest>';
        $xml .= '<tpMoeda>' . $this->somenteDigitos((string) $comercioExterior['tpMoeda']) . '</tpMoeda>';
        $xml .= '<vServMoeda>' . number_format((float) $comercioExterior['vServMoeda'], 2, '.', '') . '</vServMoeda>';
        $xml .= '<mecAFComexP>' . (int) $comercioExterior['mecAFComexP'] . '</mecAFComexP>';
        $xml .= '<mecAFComexT>' . (int) $comercioExterior['mecAFComexT'] . '</mecAFComexT>';
        $xml .= '<movTempBens>' . (int) $comercioExterior['movTempBens'] . '</movTempBens>';
        $xml .= '<mdic>' . (int) $comercioExterior['mdic'] . '</mdic>';

        return $xml . '</comExt>';
    }

    private function gerarIBSCBS(array $valores): string
    {
        if (($valores['preencher_ibscbs'] ?? 'N') !== 'S') {
            return '';
        }

        $cIndOp = preg_replace('/\D/', '', (string) ($valores['c_ind_op_ibscbs'] ?? '')) ?? '';
        $cst = preg_replace('/\D/', '', (string) ($valores['cst_ibscbs'] ?? '')) ?? '';
        $classTrib = preg_replace('/\D/', '', (string) ($valores['c_class_trib_ibscbs'] ?? '')) ?? '';

        if (strlen($cIndOp) !== 6 || strlen($cst) !== 3 || strlen($classTrib) !== 6) {
            throw new \InvalidArgumentException('Configuração de IBS/CBS incompleta para a DPS Betha.');
        }
        if (!str_starts_with($classTrib, $cst)) {
            throw new \InvalidArgumentException('Os 3 primeiros dígitos da classificação tributária devem ser iguais ao CST do IBS/CBS.');
        }

        $xml = '<IBSCBS>';
        $xml .= '<finNFSe>0</finNFSe>';
        $xml .= '<cIndOp>' . $cIndOp . '</cIndOp>';
        $xml .= '<indDest>0</indDest>';
        $xml .= '<valores>';
        $xml .= '<trib>';
        $xml .= '<gIBSCBS>';
        $xml .= '<CST>' . $cst . '</CST>';
        $xml .= '<cClassTrib>' . $classTrib . '</cClassTrib>';
        $xml .= '</gIBSCBS>';
        $xml .= '</trib>';
        $xml .= '</valores>';
        $xml .= '</IBSCBS>';

        return $xml;
    }

    private function textoMaiusculo(string $texto): string
    {
        return htmlspecialchars(mb_strtoupper($texto, 'UTF-8'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
