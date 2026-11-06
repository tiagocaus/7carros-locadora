<?php

namespace App\Services\NFSe;

/** Regras fiscais puras, compartilhadas pela emissão e pelo histórico. */
final class NFSeContextoFiscal
{
    public const CORTE_SIMPLES = '2026-11-01';
    public const CAMPOS = [
        'tipo_emissao', 'ambiente', 'serie', 'codigo_municipio', 'codigo_servico',
        'codigo_tributacao_nacional', 'item_lista_servico', 'codigo_cnae',
        'codigo_tributacao_municipio', 'descricao_servico', 'regime_tributario',
        'reg_apuracao_sn', 'trib_issqn', 'aliquota_iss', 'preencher_ibscbs',
        'c_ind_op_ibscbs', 'cst_ibscbs', 'c_class_trib_ibscbs', 'aliquota_ibs',
        'aliquota_cbs', 'exigibilidade_iss', 'enviar_im', 'incentivo_fiscal', 'perfil_id', 'id_tentativa_anterior',
    ];

    public static function competencia(?string $valor = null): string
    {
        $hoje = new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo'));
        if ($valor === null) {
            return $hoje->format('Y-m-d');
        }
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $hoje->getTimezone());
        if (!$data || $data->format('Y-m-d') !== $valor || $data > $hoje) {
            throw new \InvalidArgumentException('Competência inválida. Informe uma data existente, até hoje, no formato AAAA-MM-DD.');
        }
        return $valor;
    }

    public static function exigeNacional(int $regime, string $competencia): bool
    {
        return $regime === 4 || ($regime === 1 && $competencia >= self::CORTE_SIMPLES);
    }

    public static function snapshot(array $config): array
    {
        return array_intersect_key($config, array_flip(self::CAMPOS));
    }

    /** Identidade histórica prevalece sobre qualquer configuração atual. */
    public static function historico(array $nota, array $config): array
    {
        $contexto = $nota['contexto_fiscal'] ?? null;
        $contexto = is_array($contexto) ? $contexto : json_decode((string) $contexto, true);
        $contexto = is_array($contexto) && $contexto !== [] ? self::snapshot($contexto) : null;
        $tipo = (string) ($nota['tipo_emissao'] ?? '');
        if (!in_array($tipo, ['nacional', 'betha', 'issnet'], true)) {
            throw new \InvalidArgumentException('Emissor histórico da NFS-e ausente ou inválido. Reconcilie o documento antes de continuar.');
        }
        if (!in_array((int) ($nota['ambiente'] ?? 0), [1, 2], true)) {
            throw new \InvalidArgumentException('Ambiente histórico da NFS-e ausente. Reconcilie o documento antes de continuar.');
        }
        // Credenciais atuais podem ser renovadas; parâmetros fiscais atuais não
        // podem preencher lacunas do documento histórico silenciosamente.
        $operacional = array_diff_key($config, array_flip(self::CAMPOS));
        if (isset($config['perfil_id'])) {
            $operacional['perfil_id'] = $config['perfil_id'];
        }
        $erros = [];
        $fiscalXML = [];
        $identidadeXML = [];
        if (in_array($tipo, ['nacional', 'betha'], true)) {
            try {
                [$fiscalXML, $identidadeXML, $errosXML] = self::extrairDPS((string) ($nota['xml_envio'] ?? ''), $tipo, $contexto);
                $erros = array_merge($erros, $errosXML);
            } catch (\InvalidArgumentException $e) {
                $erros[] = $e->getMessage();
            }
        } elseif ($contexto === null) {
            $erros[] = 'Contexto fiscal histórico do emissor municipal ausente.';
        }
        $config = array_replace($operacional, $contexto ?? $fiscalXML);
        $config['tipo_emissao'] = $tipo;
        $config['ambiente'] = (int) $nota['ambiente'];
        if (isset($identidadeXML['competencia'])) {
            $config['_competencia_xml'] = $identidadeXML['competencia'];
        }
        foreach (['tipo_emissao', 'ambiente', 'serie', 'codigo_municipio'] as $campo) {
            $persistido = $campo === 'codigo_municipio' ? ($contexto[$campo] ?? null) : ($nota[$campo] ?? $contexto[$campo] ?? null);
            $noXML = $fiscalXML[$campo] ?? null;
            if ($persistido !== null && $noXML !== null && !self::iguais($campo, $persistido, $noXML)) {
                $erros[] = 'Identidade histórica diverge do XML (' . $campo . ').';
            }
        }
        foreach (['prestador_cnpj' => 'cnpj', 'numero' => 'numero', 'data_competencia' => 'competencia'] as $campo => $xmlCampo) {
            if (!empty($nota[$campo]) && isset($identidadeXML[$xmlCampo])
                && !self::iguais($campo, $nota[$campo], $identidadeXML[$xmlCampo])) {
                $erros[] = 'Identidade histórica diverge do XML (' . $campo . ').';
            }
        }
        if ($contexto !== null && isset($fiscalXML['regime_tributario'])
            && (int) ($contexto['regime_tributario'] ?? 0) !== (int) $fiscalXML['regime_tributario']) {
            $erros[] = 'Regime tributário histórico diverge do XML.';
        }
        if (!empty($nota['serie'])) {
            $config['serie'] = (string) $nota['serie'];
        }
        // O município do XML é a identidade enviada, mesmo após mudança cadastral.
        if (!empty($fiscalXML['codigo_municipio'])) {
            $config['codigo_municipio'] = $fiscalXML['codigo_municipio'];
        }
        $config['_historico_reenvio_erro'] = implode(' ', array_unique($erros));
        return $config;
    }

    /** Reenvio não pode regenerar XML usando regime ou identidade atuais. */
    public static function validarReenvio(array $config): void
    {
        if (!empty($config['_historico_reenvio_erro'])) {
            throw new \InvalidArgumentException($config['_historico_reenvio_erro'] . ' Reconcilie a NFS-e antes de reenviar.');
        }
        foreach (['serie', 'codigo_municipio', 'regime_tributario', 'codigo_servico', 'trib_issqn'] as $campo) {
            if (!isset($config[$campo]) || (string) $config[$campo] === '') {
                throw new \InvalidArgumentException('Contexto fiscal histórico incompleto (' . $campo . '). Reconcilie a NFS-e antes de reenviar.');
            }
        }
        if (!in_array((int) $config['regime_tributario'], [1, 2, 3, 4], true)) {
            throw new \InvalidArgumentException('Regime tributário histórico ambíguo. Reconcilie a NFS-e antes de reenviar.');
        }
    }

    private static function iguais(string $campo, mixed $a, mixed $b): bool
    {
        if (in_array($campo, ['ambiente', 'numero'], true)) {
            return (string) ((int) $a) === (string) ((int) $b);
        }
        if ($campo === 'prestador_cnpj') {
            return preg_replace('/\D/', '', (string) $a) === preg_replace('/\D/', '', (string) $b);
        }
        if ($campo === 'serie' && ctype_digit((string) $a) && ctype_digit((string) $b)) {
            return ltrim((string) $a, '0') === ltrim((string) $b, '0');
        }
        return trim((string) $a) === trim((string) $b);
    }

    private static function extrairDPS(string $xml, string $tipo, ?array $contexto): array
    {
        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $doc = new \DOMDocument();
            if ($xml === '' || !$doc->loadXML($xml, LIBXML_NONET) || $doc->doctype) {
                throw new \InvalidArgumentException('XML histórico ausente ou inválido.');
            }
            $namespace = $tipo === 'nacional' ? 'http://www.sped.fazenda.gov.br/nfse' : 'http://www.betha.com.br/e-nota-dps';
            if ($doc->documentElement->localName !== 'DPS' || $doc->documentElement->namespaceURI !== $namespace) {
                throw new \InvalidArgumentException('XML histórico incompatível com o emissor original.');
            }
            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('d', $namespace);
            if ($xp->query('/d:DPS/d:infDPS')->length !== 1) {
                throw new \InvalidArgumentException('XML histórico deve conter uma única DPS.');
            }
            $valor = static function (string $caminho) use ($xp): string {
                $nos = $xp->query('/d:DPS/d:infDPS/' . $caminho);
                if ($nos->length > 1) {
                    throw new \InvalidArgumentException('XML histórico contém parâmetros fiscais duplicados.');
                }
                return $nos->length === 1 ? trim($nos->item(0)->textContent) : '';
            };
            $opcao = $valor('d:prest/d:regTrib/d:opSimpNac');
            $regime = match ($opcao) {
                '3' => 1, '2' => 4,
                '1' => in_array((int) ($contexto['regime_tributario'] ?? 0), [2, 3], true) ? (int) $contexto['regime_tributario'] : 0,
                default => 0,
            };
            $erros = [];
            if ($regime === 0) {
                $erros[] = 'Regime tributário histórico ausente ou ambíguo no XML.';
            }
            $apuracao = $valor('d:prest/d:regTrib/d:regApTribSN');
            if ($regime === 1 && !in_array($apuracao, ['1', '2', '3'], true)) {
                $erros[] = 'Apuração histórica do Simples ausente ou inválida no XML.';
            }
            $fiscal = [
                'tipo_emissao' => $tipo, 'ambiente' => $valor('d:tpAmb'),
                'serie' => $valor('d:serie'), 'codigo_municipio' => $valor('d:cLocEmi'),
                'codigo_servico' => $valor('d:serv/d:cServ/d:cNBS'),
                'codigo_tributacao_nacional' => $valor('d:serv/d:cServ/d:cTribNac'),
                'descricao_servico' => $valor('d:serv/d:cServ/d:xDescServ'),
                'regime_tributario' => $regime, 'reg_apuracao_sn' => $regime === 1 ? (int) $apuracao : null,
                'trib_issqn' => $valor('d:valores/d:trib/d:tribMun/d:tribISSQN'),
                'aliquota_iss' => $valor('d:valores/d:trib/d:tribMun/d:pAliq') ?: '0',
                'enviar_im' => $valor('d:prest/d:IM') !== '' ? 'S' : 'N',
                'preencher_ibscbs' => $xp->query('/d:DPS/d:infDPS/d:IBSCBS')->length > 0 ? 'S' : 'N',
                'c_ind_op_ibscbs' => $valor('d:IBSCBS/d:cIndOp'),
                'cst_ibscbs' => $valor('d:IBSCBS/d:valores/d:trib/d:gIBSCBS/d:CST'),
                'c_class_trib_ibscbs' => $valor('d:IBSCBS/d:valores/d:trib/d:gIBSCBS/d:cClassTrib'),
            ];
            if (!preg_match('/^[0-9]{7}$/D', $fiscal['codigo_municipio']) || $fiscal['serie'] === ''
                || !preg_match('/^[0-9]{6}$/D', $fiscal['codigo_tributacao_nacional'])
                || !preg_match('/^[0-9]{9}$/D', $fiscal['codigo_servico'])
                || !in_array($fiscal['trib_issqn'], ['1', '2', '3', '4'], true)
                || !in_array($fiscal['ambiente'], ['1', '2'], true)) {
                $erros[] = 'Parâmetros fiscais históricos incompletos ou inválidos no XML.';
            }
            if ($fiscal['preencher_ibscbs'] === 'S' && (!preg_match('/^[0-9]{6}$/D', $fiscal['c_ind_op_ibscbs'])
                || !preg_match('/^[0-9]{3}$/D', $fiscal['cst_ibscbs']) || !preg_match('/^[0-9]{6}$/D', $fiscal['c_class_trib_ibscbs']))) {
                $erros[] = 'IBS/CBS histórico incompleto no XML.';
            }
            $competencia = $valor('d:dCompet');
            try {
                self::competencia($competencia);
            } catch (\InvalidArgumentException) {
                $erros[] = 'Competência histórica ausente ou inválida no XML.';
            }
            $cnpj = $valor('d:prest/d:CNPJ');
            $numero = $valor('d:nDPS');
            if (!preg_match('/^[0-9]{14}$/D', $cnpj) || !preg_match('/^[0-9]{1,15}$/D', $numero) || (int) $numero < 1) {
                $erros[] = 'Identidade do prestador ou número histórico ausente ou inválido no XML.';
            }
            return [$fiscal, ['cnpj' => $cnpj, 'numero' => $numero, 'competencia' => $competencia], $erros];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }
}
