<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Assinatura;
use App\Models\Contrato;
use App\Models\ContratoVeiculo;
use App\Models\Locacao;
use App\Models\MatrizFilial;
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
            $html = Template::render('public.assinatura.erro', [
                'titulo' => 'Documento não encontrado',
                'mensagem' => 'O contrato ou locação informado não existe ou o link está incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        $registro = $documento['registro'];
        $chave = (string) $registro['chave'];
        $assinaturaData = $documento['tipo'] === 'contrato'
            ? $this->assinatura->buscarPorContrato((int) $registro['id'], 'cliente', $chave)
            : $this->assinatura->buscarPorLocacao((int) $registro['id'], 'cliente', $chave);
        $jaAssinado = !empty($assinaturaData);

        // Buscar dados da empresa (tenant)
        $empresa = $this->buscarDadosEmpresa($chave);

        $html = Template::render('public.assinatura.index', [
            'contrato' => $documento['tipo'] === 'contrato' ? $registro : null,
            'locacao' => $documento['tipo'] === 'locacao' ? $registro : null,
            'documento' => $documento['resumo'],
            'veiculo' => $documento['veiculo'],
            'empresa' => $empresa,
            'jaAssinado' => $jaAssinado,
            'assinatura' => $assinaturaData
        ]);

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
            Response::json([
                'success' => false,
                'message' => 'Documento não encontrado'
            ], 404);
            return;
        }

        $registro = $documento['registro'];
        $tipo = $documento['tipo'];
        $tipoLabel = $documento['resumo']['tipo_label'];
        $chave = (string) $registro['chave'];

        // Verificar se ja foi assinado
        $jaAssinado = $tipo === 'contrato'
            ? $this->assinatura->contratoTemAssinatura((int) $registro['id'], $chave)
            : $this->assinatura->locacaoTemAssinatura((int) $registro['id'], $chave);

        if ($jaAssinado) {
            Response::json([
                'success' => false,
                'message' => 'Este ' . strtolower($tipoLabel) . ' já foi assinado'
            ], 400);
            return;
        }

        // Validar assinatura
        $assinaturaBase64 = $request->input('assinatura', '');
        if (empty($assinaturaBase64) || strpos($assinaturaBase64, 'data:image') !== 0) {
            Response::json([
                'success' => false,
                'message' => 'Assinatura inválida'
            ], 400);
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

        Response::json([
            'success' => true,
            'message' => $tipoLabel . ' assinado com sucesso!',
            'data' => [
                'codigo' => $codigo,
                'data_assinatura' => date('d/m/Y H:i:s'),
                'ip' => $ip
            ]
        ]);
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
                'tipo_label' => 'Contrato',
                'codigo' => $contrato['codigo'] ?? '',
                'cliente_nome' => $contrato['cliente_nome'] ?? 'N/A',
                'cliente_documento' => $contrato['cliente_cpf_cnpj'] ?? 'N/A',
                'veiculo_texto' => $veiculo
                    ? trim(($veiculo['veiculo_placa'] ?? '') . ' - ' . ($veiculo['veiculo_modelo'] ?? ''))
                    : '',
                'periodo' => $this->formatarPeriodo($contrato['data_ini'] ?? null, $contrato['data_fim'] ?? null),
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
                'tipo_label' => 'Locação',
                'codigo' => $locacao['codigo'] ?? '',
                'cliente_nome' => $locacao['cliente_nome_completo'] ?? $locacao['cliente_nome'] ?? 'N/A',
                'cliente_documento' => $locacao['cliente_cpf_cnpj'] ?? 'N/A',
                'veiculo_texto' => $locacao['veiculo_info'] ?? '',
                'periodo' => $this->formatarPeriodo(
                    $locacao['data_saida'] ?? null,
                    $locacao['data_chegada'] ?? $locacao['data_prevista'] ?? null
                ),
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

        try {
            return $callback();
        } finally {
            if ($hadChave) {
                $_SESSION['chave'] = $previousChave;
            } else {
                unset($_SESSION['chave']);
            }
        }
    }

    /**
     * Formata periodo exibido na pagina publica.
     */
    private function formatarPeriodo(?string $inicio, ?string $fim): string
    {
        return $this->formatarData($inicio) . ' a ' . $this->formatarData($fim);
    }

    /**
     * Formata data de forma tolerante a valores vazios.
     */
    private function formatarData(?string $data): string
    {
        if (empty($data)) {
            return '-';
        }

        $timestamp = strtotime($data);
        if ($timestamp === false) {
            return '-';
        }

        return date('d/m/Y', $timestamp);
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
