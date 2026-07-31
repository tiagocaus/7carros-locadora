<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\FilialHelper;
use App\Helpers\PdfHelper;
use App\Models\MatrizFilial;

/**
 * Controller base para todos os relatórios
 *
 * Fornece métodos compartilhados para:
 * - Parsing de filtros comuns (período, filial)
 * - Validação de período
 * - Filtro de filial via FilialHelper
 * - Verificação de permissão
 * - Resposta JSON padronizada
 */
abstract class BaseRelatorioController
{
    /**
     * Extrai filtros comuns do request
     *
     * @return array{data_inicio: string, data_fim: string, filial: string}
     */
    protected function parseFilters(Request $request): array
    {
        return [
            'data_inicio' => $request->query('data_inicio', ''),
            'data_fim' => $request->query('data_fim', ''),
            'filial' => $request->query('filial', ''),
        ];
    }

    /**
     * Valida que o período foi informado e é válido
     *
     * @return string|null Mensagem de erro ou null se válido
     */
    protected function validatePeriodo(string $dataInicio, string $dataFim): ?string
    {
        if (empty($dataInicio) || empty($dataFim)) {
            return t('modules.relatorios.messages.period_required');
        }

        $inicio = strtotime($dataInicio);
        $fim = strtotime($dataFim);

        if ($inicio === false || $fim === false) {
            return t('modules.relatorios.messages.invalid_dates');
        }

        if ($inicio > $fim) {
            return t('modules.relatorios.messages.start_after_end');
        }

        // Limitar a 2 anos
        $diffDias = ($fim - $inicio) / 86400;
        if ($diffDias > 730) {
            return t('modules.relatorios.messages.max_period');
        }

        return null;
    }

    /**
     * Obtém filtro de filial respeitando permissões do usuário
     *
     * @param string $coluna Nome da coluna de filial
     * @param string|null $alias Alias da tabela
     * @return array{0: string, 1: array}
     */
    protected function getFilialFilter(string $coluna = 'id_matriz_filial', ?string $alias = null): array
    {
        return FilialHelper::whereFiliais($coluna, $alias);
    }

    /**
     * Verifica permissão e retorna 403 se não autorizado
     *
     * @return bool true se autorizado
     */
    protected function checkPermission(string $permissionKey): bool
    {
        if (!Auth::can($permissionKey)) {
            Response::json([
                'success' => false,
                'message' => t('modules.relatorios.messages.no_permission')
            ], 403);
            return false;
        }
        return true;
    }

    /**
     * Valida acesso à filial selecionada pelo usuário
     *
     * @return bool true se tem acesso ou filial não foi selecionada
     */
    protected function validateFilialAccess(string $filialId): bool
    {
        if (!empty($filialId) && !FilialHelper::temAcessoFilial((int) $filialId)) {
            Response::json([
                'success' => false,
                'message' => t('modules.relatorios.messages.no_branch_access')
            ], 403);
            return false;
        }
        return true;
    }

    /**
     * Resolve o path absoluto do logo do tenant para uso em mPDF.
     *
     * Delega para PdfHelper::resolveImagePath, que garante:
     * - Path local (mPDF nao acessa URL com token HMAC)
     * - WebP convertido para JPEG temp (mPDF e ~90x mais lento processando WebP)
     * - Cleanup automatico do JPEG temp ao final do request
     */
    protected function resolveLogoPath(array $empresa): string
    {
        if (empty($empresa['chave'])) {
            return '';
        }
        return PdfHelper::resolveImagePath($empresa['logo'] ?? null, $empresa['chave']);
    }

    /**
     * Monta o contexto da empresa usado no cabecalho dos PDFs de relatorios.
     *
     * Funcionarios sem filial vinculada sao validos no sistema. Nesse caso,
     * usa a matriz do tenant e, se ela nao existir, a primeira unidade
     * disponivel. Todas as consultas permanecem tenant-scoped pelo QueryBuilder.
     *
     * @return array<string, mixed>
     */
    protected function resolveReportPdfCompany(?array $user = null): array
    {
        $user ??= Auth::user() ?? [];
        $filialModel = new MatrizFilial();
        $filialId = (int) ($user['id_matriz_filial'] ?? 0);

        $empresa = $filialId > 0 ? $filialModel->buscarPorId($filialId) : null;
        $empresa ??= $filialModel->buscarMatriz();
        $empresa ??= $filialModel->listar()[0] ?? null;

        if ($empresa === null) {
            return ['nome' => '', 'logo' => ''];
        }

        $empresa['nome'] = $empresa['nome_fantasia']
            ?? $empresa['razao_social']
            ?? '';
        $empresa['logo'] = $this->resolveLogoPath($empresa);

        return $empresa;
    }

    /**
     * Envia um PDF de relatorio e acrescenta contexto tecnico ao log em falhas.
     */
    protected function outputReportPdf(
        string $html,
        string $filename,
        array $options = [],
        string $context = ''
    ): void {
        try {
            PdfHelper::outputInline($html, $filename, $options);
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Report PDF error [%s] tenant=%s user=%s: %s: %s',
                $context !== '' ? $context : 'unknown',
                (string) (Auth::chave() ?? ''),
                (string) (Auth::id() ?? ''),
                $e::class,
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Resposta JSON padronizada para relatórios
     */
    protected function reportResponse(array $data, array $totals = [], array $chartData = []): void
    {
        Response::json([
            'success' => true,
            'data' => $data,
            'totals' => $totals,
            'chart' => $chartData,
        ]);
    }

    /**
     * Resposta JSON com paginação
     */
    protected function reportPaginatedResponse(
        array $data,
        array $totals,
        int $page,
        int $perPage,
        int $total,
        array $chartData = []
    ): void {
        Response::json([
            'success' => true,
            'data' => $data,
            'totals' => $totals,
            'chart' => $chartData,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
                'hasPrev' => $page > 1,
                'hasNext' => ($page * $perPage) < $total,
            ],
        ]);
    }
}
