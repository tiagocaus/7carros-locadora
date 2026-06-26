<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Assinatura;
use App\Models\Contrato;
use App\Models\ContratoVeiculo;
use App\Models\Locacao;
use App\Models\MatrizFilial;
use App\Helpers\CurrencyHelper;
use App\Helpers\DateHelper;
use App\Views\Template;

/**
 * Controller de Assinatura Publica
 *
 * Gerencia a pagina publica de assinatura de contratos e locacoes.
 * Nao requer autenticacao.
 */
class AssinaturaController
{
    private Contrato $contrato;
    private ContratoVeiculo $contratoVeiculo;
    private Locacao $locacao;
    private Assinatura $assinatura;
    private MatrizFilial $matrizFilial;

    public function __construct()
    {
        $this->contrato = new Contrato();
        $this->contratoVeiculo = new ContratoVeiculo();
        $this->locacao = new Locacao();
        $this->assinatura = new Assinatura();
        $this->matrizFilial = new MatrizFilial();
    }

    /**
     * Exibe a pagina publica de assinatura
     *
     * @param Request $request Request data
     * @param string $codigo Codigo do contrato ou locacao
     */
    public function view(Request $request, string $codigo): void
    {
        $documento = $this->resolverDocumento($codigo);

        if (!$documento) {
            $locale = $this->detectarLocaleNavegador() ?? 'pt_BR';
            $html = $this->comLocaleTemporario($locale, function () {
                return Template::render('public.assinatura.erro', [
                    'titulo' => t('modules.assinatura.document_not_found_title'),
                    'mensagem' => t('modules.assinatura.document_not_found_message'),
                ]);
            });
            Response::html($html, 404);
            return;
        }

        $registro = $documento['registro'];
        $chave = (string) $registro['chave'];
        $empresa = $this->buscarDadosEmpresa($chave);
        $locale = $this->resolverLocalePublico($registro, $empresa);
        $documento['resumo'] = $this->prepararResumoTraduzido($documento, $locale);

        $assinaturaData = $documento['tipo'] === 'contrato'
            ? $this->assinatura->buscarPorContrato((int) $registro['id'], 'cliente', $chave)
            : $this->assinatura->buscarPorLocacao((int) $registro['id'], 'cliente', $chave);
        $jaAssinado = !empty($assinaturaData);

        $html = $this->comChaveTemporaria($chave, function () use ($locale, $documento, $registro, $empresa, $jaAssinado, $assinaturaData) {
            return $this->comLocaleTemporario($locale, function () use ($documento, $registro, $empresa, $jaAssinado, $assinaturaData) {
                return Template::render('public.assinatura.index', [
                    'contrato' => $documento['tipo'] === 'contrato' ? $registro : null,
                    'locacao' => $documento['tipo'] === 'locacao' ? $registro : null,
                    'documento' => $documento['resumo'],
                    'veiculo' => $documento['veiculo'],
                    'empresa' => $empresa,
                    'jaAssinado' => $jaAssinado,
                    'assinatura' => $assinaturaData,
                ]);
            });
        });

        Response::html($html);
    }

    /**
     * Processa a assinatura do contrato
     *
     * @param Request $request Request data
     * @param string $codigo Codigo do contrato ou locacao
     */
    public function assinar(Request $request, string $codigo): void
    {
        $documento = $this->resolverDocumento($codigo);

        if (!$documento) {
            $locale = $this->detectarLocaleNavegador() ?? 'pt_BR';
            $payload = $this->comLocaleTemporario($locale, function () {
                return [
                    'success' => false,
                    'message' => t('modules.assinatura.document_not_found_json'),
                ];
            });
            Response::json($payload, 404);
            return;
        }

        $registro = $documento['registro'];
        $tipo = $documento['tipo'];
        $chave = (string) $registro['chave'];
        $empresa = $this->buscarDadosEmpresa($chave);
        $locale = $this->resolverLocalePublico($registro, $empresa);
        $tipoLabel = t('modules.assinatura.types.' . $tipo . '.label', [], $locale);
        $tipoLower = t('modules.assinatura.types.' . $tipo . '.lower', [], $locale);

        // Verificar se ja foi assinado
        $jaAssinado = $tipo === 'contrato'
            ? $this->assinatura->contratoTemAssinatura((int) $registro['id'], $chave)
            : $this->assinatura->locacaoTemAssinatura((int) $registro['id'], $chave);

        if ($jaAssinado) {
            $payload = $this->comLocaleTemporario($locale, function () use ($tipoLower) {
                return [
                    'success' => false,
                    'message' => t('modules.assinatura.already_signed_message', ['type' => $tipoLower]),
                ];
            });
            Response::json($payload, 400);
            return;
        }

        // Validar assinatura
        $assinaturaBase64 = $request->input('assinatura', '');
        if (empty($assinaturaBase64) || strpos($assinaturaBase64, 'data:image') !== 0) {
            $payload = $this->comLocaleTemporario($locale, function () {
                return [
                    'success' => false,
                    'message' => t('modules.assinatura.invalid_signature'),
                ];
            });
            Response::json($payload, 400);
            return;
        }

        // Obter IP e user agent do cliente
        $ip = $this->getClientIp();
        $userAgent = $request->userAgent();

        // Dados extras (latitude/longitude vem do frontend)
        $extras = [
            'user_agent' => $userAgent,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ];

        // Salvar assinatura vinculada ao tipo correto
        $this->assinatura->salvar([
            'base64' => $assinaturaBase64,
            'id_contrato' => $tipo === 'contrato' ? (int) $registro['id'] : null,
            'id_locacao' => $tipo === 'locacao' ? (int) $registro['id'] : null,
            'id_cliente' => $registro['id_cliente'] ?? null,
            'ip_address' => $ip,
            'user_agent' => $extras['user_agent'] ?? null,
            'latitude' => $extras['latitude'] ?? null,
            'longitude' => $extras['longitude'] ?? null,
            'tipo' => 'cliente',
            'chave' => $chave,
        ]);

        $payload = $this->comChaveTemporaria($chave, function () use ($locale, $tipoLabel, $codigo, $ip) {
            return $this->comLocaleTemporario($locale, function () use ($tipoLabel, $codigo, $ip) {
                return [
                    'success' => true,
                    'message' => t('modules.assinatura.success_message', ['type' => $tipoLabel]),
                    'data' => [
                        'codigo' => $codigo,
                        'data_assinatura' => format_datetime(date('Y-m-d H:i:s')),
                        'ip' => $ip,
                    ],
                ];
            });
        });

        Response::json($payload);
    }

    /**
     * Resolve um codigo publico como contrato ou locacao.
     */
    private function resolverDocumento(string $codigo): ?array
    {
        $prefixo = strtoupper(substr($codigo, 0, 1));
        $ordem = match ($prefixo) {
            'L' => ['locacao', 'contrato'],
            'C' => ['contrato', 'locacao'],
            default => ['contrato', 'locacao'],
        };

        foreach ($ordem as $tipo) {
            $documento = $tipo === 'contrato'
                ? $this->resolverContrato($codigo)
                : $this->resolverLocacao($codigo);

            if ($documento !== null) {
                return $documento;
            }
        }

        return null;
    }

    /**
     * Resolve contrato para assinatura publica.
     */
    private function resolverContrato(string $codigo): ?array
    {
        $contrato = $this->contrato->buscarPublicoPorCodigo($codigo);
        if (!$contrato) {
            return null;
        }

        $chave = (string) $contrato['chave'];
        $veiculo = $this->comChaveTemporaria($chave, function () use ($contrato) {
            return $this->contratoVeiculo->buscarAtivo((int) $contrato['id']);
        });

        return [
            'tipo' => 'contrato',
            'registro' => $contrato,
            'veiculo' => $veiculo,
            'resumo' => [
                'tipo' => 'contrato',
                'codigo' => $contrato['codigo'] ?? '',
                'cliente_nome' => $contrato['cliente_nome'] ?? '',
                'cliente_documento' => $contrato['cliente_cpf_cnpj'] ?? '',
                'veiculo_texto' => $veiculo
                    ? trim(($veiculo['veiculo_placa'] ?? '') . ' - ' . ($veiculo['veiculo_modelo'] ?? ''))
                    : '',
                'data_inicio' => $contrato['data_ini'] ?? null,
                'data_fim' => $contrato['data_fim'] ?? null,
                'valor_total' => (float) ($contrato['total_pagar'] ?? 0),
            ],
        ];
    }

    /**
     * Resolve locacao para assinatura publica.
     */
    private function resolverLocacao(string $codigo): ?array
    {
        $locacao = $this->locacao->buscarPublicoPorCodigo($codigo);
        if (!$locacao) {
            return null;
        }

        return [
            'tipo' => 'locacao',
            'registro' => $locacao,
            'veiculo' => null,
            'resumo' => [
                'tipo' => 'locacao',
                'codigo' => $locacao['codigo'] ?? '',
                'cliente_nome' => $locacao['cliente_nome_completo'] ?? $locacao['cliente_nome'] ?? '',
                'cliente_documento' => $locacao['cliente_cpf_cnpj'] ?? '',
                'veiculo_texto' => $locacao['veiculo_info'] ?? '',
                'data_inicio' => $locacao['data_saida'] ?? null,
                'data_fim' => $locacao['data_chegada'] ?? $locacao['data_prevista'] ?? null,
                'valor_total' => (float) ($locacao['total_pagar'] ?? $locacao['total_fatura'] ?? 0),
            ],
        ];
    }

    /**
     * Executa callback com a chave do documento publico e restaura a sessao.
     */
    private function comChaveTemporaria(string $chave, callable $callback)
    {
        $hadChave = isset($_SESSION['chave']);
        $previousChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;
        CurrencyHelper::clearCache();
        DateHelper::clearCache();

        try {
            return $callback();
        } finally {
            if ($hadChave) {
                $_SESSION['chave'] = $previousChave;
            } else {
                unset($_SESSION['chave']);
            }
            CurrencyHelper::clearCache();
            DateHelper::clearCache();
        }
    }

    /**
     * Executa callback com o locale publico e restaura a preferencia anterior.
     */
    private function comLocaleTemporario(string $locale, callable $callback)
    {
        $localeAnterior = current_locale();
        $hadUiLocale = isset($_SESSION['ui_locale']);
        $previousUiLocale = $_SESSION['ui_locale'] ?? null;

        set_locale($locale);

        try {
            return $callback();
        } finally {
            set_locale($localeAnterior);
            if ($hadUiLocale) {
                $_SESSION['ui_locale'] = $previousUiLocale;
            } else {
                unset($_SESSION['ui_locale']);
            }
        }
    }

    /**
     * Prepara labels e campos formatados no idioma/contexto publico.
     */
    private function prepararResumoTraduzido(array $documento, string $locale): array
    {
        $resumo = $documento['resumo'];
        $tipo = $documento['tipo'];
        $chave = (string) ($documento['registro']['chave'] ?? '');

        return $this->comChaveTemporaria($chave, function () use ($resumo, $tipo, $locale) {
            return $this->comLocaleTemporario($locale, function () use ($resumo, $tipo) {
                $resumo['tipo_label'] = t('modules.assinatura.types.' . $tipo . '.label');
                $resumo['tipo_lower'] = t('modules.assinatura.types.' . $tipo . '.lower');
                $resumo['tipo_preposicao'] = t('modules.assinatura.types.' . $tipo . '.summary_preposition');
                $resumo['tipo_demonstrativo'] = t('modules.assinatura.types.' . $tipo . '.demonstrative');
                $resumo['cliente_nome'] = $resumo['cliente_nome'] ?: t('modules.assinatura.labels.not_available');
                $resumo['cliente_documento'] = $resumo['cliente_documento'] ?: t('modules.assinatura.labels.not_available');
                $resumo['periodo'] = $this->formatarPeriodo($resumo['data_inicio'] ?? null, $resumo['data_fim'] ?? null);
                $resumo['valor_total_formatado'] = currency_format((float) ($resumo['valor_total'] ?? 0));
                return $resumo;
            });
        });
    }

    /**
     * Formata periodo exibido na pagina publica.
     */
    private function formatarPeriodo(?string $inicio, ?string $fim): string
    {
        return $this->formatarData($inicio) . ' - ' . $this->formatarData($fim);
    }

    /**
     * Formata data de forma tolerante a valores vazios.
     */
    private function formatarData(?string $data): string
    {
        if (empty($data) || strtotime($data) === false) {
            return '-';
        }

        return format_date($data);
    }

    /**
     * Resolve locale da pagina publica sem depender de usuario autenticado.
     */
    private function resolverLocalePublico(array $registro, ?array $empresa): string
    {
        $candidatos = [
            $registro['cliente_preferred_locale'] ?? null,
            $empresa['locale'] ?? null,
            $this->detectarLocaleNavegador(),
            'pt_BR',
        ];

        foreach ($candidatos as $locale) {
            if (!empty($locale) && is_locale_supported((string) $locale)) {
                return (string) $locale;
            }
        }

        return 'pt_BR';
    }

    /**
     * Detecta locale suportado a partir do Accept-Language.
     */
    private function detectarLocaleNavegador(): ?string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLanguage === '') {
            return null;
        }

        $map = [
            'pt-BR' => 'pt_BR',
            'pt-PT' => 'pt_PT',
            'pt' => 'pt_BR',
            'en-US' => 'en_US',
            'en' => 'en_US',
            'es-ES' => 'es_ES',
            'es' => 'es_ES',
            'it-IT' => 'it_IT',
            'it' => 'it_IT',
        ];

        preg_match_all('/([a-z]{2}(?:-[A-Z]{2})?)/i', $acceptLanguage, $matches);
        foreach ($matches[1] as $lang) {
            $normalized = str_replace('_', '-', $lang);
            if (isset($map[$normalized])) {
                return $map[$normalized];
            }

            $short = substr($normalized, 0, 2);
            if (isset($map[$short])) {
                return $map[$short];
            }
        }

        return null;
    }

    /**
     * Busca dados da empresa pelo chave do tenant
     *
     * @param string $chave Chave do tenant
     * @return array|null Dados da empresa
     */
    private function buscarDadosEmpresa(string $chave): ?array
    {
        return $this->matrizFilial->buscarDadosEmpresaPorChave($chave);
    }

    /**
     * Obtem o IP real do cliente
     *
     * @return string IP do cliente
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Se houver multiplos IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validar se eh um IP valido
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
