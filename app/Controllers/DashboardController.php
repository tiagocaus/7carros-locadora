<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\FilialHelper;
use App\Models\Contrato;
use App\Models\Financeiro;
use App\Models\Locacao;
use App\Models\Veiculo;
use App\Views\Template;

/**
 * Controller do Dashboard
 *
 * Gerencia a página principal do sistema.
 * Seleciona o dashboard v1 ou v2 baseado nas permissões do usuário.
 */
class DashboardController
{
    /**
     * Exibe o dashboard principal
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $empresa = Auth::empresa();
        $stats = $this->buildStats();

        $dashboardView = $this->resolveDashboardView();

        $html = Template::render($dashboardView, [
            'user' => $user,
            'empresa' => $empresa,
            'stats' => $stats,
        ]);

        Response::html($html);
    }

    /**
     * API endpoint para dados do dashboard (auto-refresh do v2 a cada 30s).
     */
    public function stats(Request $request): void
    {
        Response::json([
            'success' => true,
            'data' => $this->buildStats(),
            'timestamp' => format_datetime(now()),
        ]);
    }

    /**
     * API endpoint para as subtabs do dashboard simples.
     */
    public function subtab(Request $request, string $tab): void
    {
        if (!Auth::can('dashboard.visualizar')) {
            Response::json([
                'success' => false,
                'message' => 'Acesso negado ao dashboard.',
            ], 403);
        }

        $allowedTabs = [
            'reservas',
            'alugados',
            'disponiveis',
            'chegada_pendente',
            'proximas_devolucoes',
        ];

        if (!in_array($tab, $allowedTabs, true)) {
            Response::json([
                'success' => false,
                'message' => 'Aba do dashboard inválida.',
            ], 400);
        }

        $chave = Auth::chave() ?? '';
        $limit = 20;
        $locacaoModel = new Locacao();

        if ($tab === 'disponiveis') {
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial', 'v');
            $data = (new Veiculo())->dashboardAvailableVehicles($chave, $limit, $filialWhere, $filialParams);
        } elseif ($tab === 'alugados') {
            [$locacaoFilialWhere, $locacaoFilialParams] = FilialHelper::whereLocacoes('l');
            $data = $locacaoModel->dashboardSimpleRented($chave, $limit, $locacaoFilialWhere, $locacaoFilialParams);

            if (Auth::can('contratos.visualizar')) {
                [$contratoFilialWhere, $contratoFilialParams] = FilialHelper::whereContratos('c');
                $contratos = (new Contrato())->dashboardSimpleRented($chave, $limit, $contratoFilialWhere, $contratoFilialParams);
                $data = array_merge($data, $contratos);
            }

            usort($data, static function (array $a, array $b): int {
                $aTime = strtotime((string) ($a['sort_at'] ?? '')) ?: PHP_INT_MAX;
                $bTime = strtotime((string) ($b['sort_at'] ?? '')) ?: PHP_INT_MAX;

                return $aTime <=> $bTime;
            });

            $data = array_slice($data, 0, $limit);
        } else {
            [$filialWhere, $filialParams] = FilialHelper::whereLocacoes('l');
            $data = match ($tab) {
                'reservas' => $locacaoModel->dashboardSimpleReservations($chave, $limit, $filialWhere, $filialParams),
                'chegada_pendente' => $locacaoModel->dashboardSimplePendingArrival($chave, $limit, $filialWhere, $filialParams),
                'proximas_devolucoes' => $locacaoModel->dashboardSimpleUpcomingReturns($chave, $limit, $filialWhere, $filialParams),
            };
        }

        Response::json([
            'success' => true,
            'tab' => $tab,
            'updated_at' => format_datetime(now()),
            'data' => $data,
        ]);
    }

    /**
     * Monta o array completo de stats consumido por v1 (server-side) e v2 (JSON).
     *
     * Estrutura corresponde ao que o JS de index2.php (linhas 485-541) espera.
     */
    private function buildStats(): array
    {
        $chave = Auth::chave() ?? '';

        $veiculoModel = new Veiculo();
        $locacaoModel = new Locacao();

        $fleet = $veiculoModel->dashboardSummary($chave);
        $operations = $locacaoModel->dashboardOperations($chave);

        $fleet['expected_revenue_today'] = $locacaoModel->dashboardExpectedRevenueToday($chave);
        $fleet['average_daily_rate'] = $fleet['total'] > 0
            ? round($fleet['expected_revenue_today'] / max($fleet['rented'], 1), 2)
            : 0.0;

        $data = [
            'fleet' => $fleet,
            'operations' => $operations,
            'reservations' => $locacaoModel->dashboardUpcomingReservations($chave, 5),
            'latest_reservations' => $locacaoModel->dashboardLatestReservations($chave, 5),
        ];

        $contractsSummary = null;
        if (Auth::can('contratos.visualizar')) {
            $contratoModel = new Contrato();
            $contractsSummary = $contratoModel->dashboardSummary($chave);
            $data['contracts'] = $contractsSummary;
        }

        $financialSummary = null;
        if (Auth::can('financeiro.visualizar')) {
            $financeiroModel = new Financeiro();
            $financialSummary = $financeiroModel->dashboardFinancialSummary($chave);
            $data['financial'] = $financialSummary;
            $data['overdue_accounts'] = $financeiroModel->dashboardOverdueAccounts($chave, 5);
            $data['upcoming_due'] = $financeiroModel->dashboardUpcomingDue($chave, 5, 30);
        }

        $data['alerts'] = $this->buildAlerts($operations, $financialSummary, $contractsSummary);

        return $data;
    }

    /**
     * Deriva alertas dos contadores ja calculados.
     * So inclui itens com dados reais — sem placeholders.
     */
    private function buildAlerts(array $operations, ?array $financial, ?array $contracts): array
    {
        $alerts = [];

        if (!empty($operations['overdue'])) {
            $alerts[] = [
                'severity' => 'critical',
                'icon' => 'fa-car',
                'message' => $operations['overdue'] . ' veículos atrasados na devolução',
            ];
        }

        if ($contracts !== null && !empty($contracts['expiring_soon'])) {
            $alerts[] = [
                'severity' => 'warning',
                'icon' => 'fa-file-contract',
                'message' => $contracts['expiring_soon'] . ' contratos vencem em 7 dias',
            ];
        }

        if ($financial !== null && !empty($financial['overdue_count'])) {
            $valor = number_format($financial['overdue_total'], 2, ',', '.');
            $alerts[] = [
                'severity' => 'critical',
                'icon' => 'fa-dollar-sign',
                'message' => 'R$ ' . $valor . ' em faturas vencidas (' . $financial['overdue_count'] . ')',
            ];
        }

        return $alerts;
    }

    /**
     * Determina qual view de dashboard renderizar baseado nas permissões do usuário
     */
    private function resolveDashboardView(): string
    {
        if (Auth::can('dashboard.completo')) {
            return 'dashboard.index2';
        }

        return 'dashboard.index';
    }
}
