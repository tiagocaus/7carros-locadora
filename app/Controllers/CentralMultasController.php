<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Multa;
use App\Models\SerproSaldo;
use App\Models\SerproTransacao;
use App\Models\SerproIndicacao;
use App\Models\SerproConfiguracao;
use App\Models\MatrizFilial;
use App\Services\SerproSaldoService;
use App\Helpers\FilialHelper;

/**
 * Controller da Central de Multas
 *
 * Super tela que unifica todas as funcionalidades de multas:
 * dashboard com KPIs, lista com filtros avancados, saldo SERPRO,
 * acoes rapidas e integracao completa com consultas online.
 */
class CentralMultasController
{
    /**
     * Renderiza a Central de Multas
     *
     * GET /pages/central-multas
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.multas.central');
        Response::html($html);
    }

    /**
     * Retorna dados do dashboard (KPIs e resumos)
     *
     * GET /api/central-multas/dashboard
     */
    public function dashboard(Request $request): void
    {
        try {
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('m.id_matriz_filial');

            $multaModel = new Multa();

            // KPIs de multas
            $kpis = $multaModel->calcularKpis($filialWhere, $filialParams);

            // Saldo SERPRO
            $saldoService = new SerproSaldoService();
            $saldoService->inicializarSaldo();
            $saldo = $saldoService->getSaldo();

            // Resumo indicacoes
            $indicacaoModel = new SerproIndicacao();
            $resumoIndicacoes = $indicacaoModel->resumo();

            // Resumo gastos SERPRO
            $transacaoModel = new SerproTransacao();
            $resumoGastos = $transacaoModel->resumoGastos();

            // Config SERPRO
            $configModel = new SerproConfiguracao();
            $config = $configModel->buscarPorChave();
            $cnpjConfigurado = $this->cnpjConsultaOnlineValido((string) ($config['cnpj_empresa'] ?? ''));
            $cnpjStatus = $this->statusCnpjConsultaOnline($cnpjConfigurado);

            Response::json([
                'success' => true,
                'data' => [
                    'kpis' => $kpis,
                    'saldo' => $saldo,
                    'precos' => [
                        'consulta' => $saldoService->getPrecoConsulta(),
                        'evento' => $saldoService->getPrecoEvento(),
                    ],
                    'indicacoes' => $resumoIndicacoes,
                    'gastos' => $resumoGastos,
                    'config' => [
                        'auto_consulta_ativo' => (int) ($config['auto_consulta_ativo'] ?? 0),
                        'auto_eventos_ativo' => (int) ($config['auto_eventos_ativo'] ?? 0),
                        'intervalo_dias_consulta' => (int) ($config['intervalo_dias_consulta'] ?? 7),
                        'cnpj_configurado' => $cnpjConfigurado,
                        'cnpj_disponivel' => $cnpjStatus['disponivel'],
                        'cnpj_aviso' => $cnpjStatus['aviso'],
                        'ultima_consulta' => $config['ultima_consulta_em'] ?? null,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifica se existe CNPJ valido para uso na Consulta Online.
     */
    private function statusCnpjConsultaOnline(bool $cnpjConfigurado): array
    {
        if ($cnpjConfigurado) {
            return ['disponivel' => true, 'aviso' => null];
        }

        $model = new MatrizFilial();
        $empresas = $model->listar(null, [], 'tipo DESC, razao_social ASC');
        $cnpjsValidos = [];

        foreach ($empresas as $empresa) {
            $cnpj = preg_replace('/\D/', '', (string) ($empresa['cpf_cnpj'] ?? ''));
            if ($this->cnpjConsultaOnlineValido($cnpj)) {
                $cnpjsValidos[$cnpj] = true;
            }
        }

        if (count($cnpjsValidos) === 1) {
            return ['disponivel' => true, 'aviso' => null];
        }

        if (count($cnpjsValidos) > 1) {
            return [
                'disponivel' => false,
                'aviso' => t('modules.multas.central.automation.online_query_multiple_cnpjs'),
            ];
        }

        return [
            'disponivel' => false,
            'aviso' => t('modules.multas.central.automation.online_query_requires_cnpj'),
        ];
    }

    /**
     * A Consulta Online exige CNPJ; CPF nao habilita automacoes.
     */
    private function cnpjConsultaOnlineValido(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        return strlen($cnpj) === 14 && !preg_match('/^(\d)\1{13}$/', $cnpj);
    }

    /**
     * Lista multas com filtros avancados (inclui campos SERPRO)
     *
     * GET /api/central-multas/multas
     * Query: page, perPage, search, tipo, pago, origem, status_processamento, placa
     */
    public function listarMultas(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 15)));
            $search = $request->query('search', '');
            $filtroTipo = $request->query('tipo', '');
            $filtroPago = $request->query('pago', '');
            $filtroOrigem = $request->query('origem', '');
            $filtroStatus = $request->query('status_processamento', '');
            $filtroPlaca = $request->query('placa', '');
            $filtroVencimento = $request->query('vencimento', '');

            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('m.id_matriz_filial');

            $multaModel = new Multa();
            $multas = $multaModel->listarPaginadoCentral(
                $page, $perPage, $search,
                $filialWhere, $filialParams,
                $filtroTipo, $filtroPago, $filtroOrigem, $filtroStatus, $filtroPlaca, $filtroVencimento
            );

            $total = $multaModel->contarCentral(
                $search,
                $filialWhere, $filialParams,
                $filtroTipo, $filtroPago, $filtroOrigem, $filtroStatus, $filtroPlaca, $filtroVencimento
            );

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $multas,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasPrev' => $page > 1,
                    'hasNext' => $page < $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar multas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ranking de veiculos por quantidade de multas
     *
     * GET /api/central-multas/ranking-veiculos
     */
    public function rankingVeiculos(Request $request): void
    {
        try {
            $limite = min(20, max(5, (int) $request->query('limite', 10)));
            $multaModel = new Multa();

            $ranking = $multaModel->rankingVeiculos($limite);

            Response::json(['success' => true, 'data' => $ranking]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar ranking: ' . $e->getMessage(),
            ], 500);
        }
    }

}
