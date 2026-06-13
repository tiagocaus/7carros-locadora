@extends('layouts.app')

@section('title', t('modules.dashboard.title') . ' - 7Carros Locadora')

@section('content')
@php
    $fleet = $stats['fleet'] ?? [];
    $operations = $stats['operations'] ?? [];
    $contracts = $stats['contracts'] ?? null;
    $financial = $stats['financial'] ?? null;
    $fleetTotal = (int) ($fleet['total'] ?? 0);
    $available = (int) ($fleet['available'] ?? 0);
    $rented = (int) ($fleet['rented'] ?? 0);
    $reserved = (int) ($fleet['reserved'] ?? 0);
    $workshop = (int) ($fleet['workshop'] ?? 0);
    $denominator = max($fleetTotal, 1);
    $pctAvailable = round(($available / $denominator) * 100, 1);
    $pctRented = round(($rented / $denominator) * 100, 1);
    $pctReserved = round(($reserved / $denominator) * 100, 1);
    $pctWorkshop = round(($workshop / $denominator) * 100, 1);
    $utilizationRate = (float) ($fleet['utilization_rate'] ?? 0);
@endphp
<!-- Tab Content: Início (Dashboard v2 - Cockpit) -->
<div id="tab-inicio" class="tab-content active-content pl-4 pr-6 py-0" data-tab-content-id="inicio">

    <!-- Header com timestamp -->
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ t('modules.dashboard.v2.title') }}</h2>
        <div class="text-xs text-slate-400" id="dashLastUpdate">
            <i class="fas fa-sync-alt mr-1"></i>
            <span id="dashTimestamp">--</span>
            <span class="ml-2 text-slate-300">{{ t('modules.dashboard.v2.refresh.auto_refresh', ['seconds' => '30']) }}</span>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ZONA A: KPIs Principais (8 cards, 2 linhas de 4) -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <!-- KPI 1: Frota Total -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-sky-100 text-sky-600 mr-4">
                    <i class="fas fa-car fa-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.kpi.total_vehicles') }}</p>
                    <p class="text-xl font-bold text-slate-800" id="kpiFleetTotal">{{ $fleetTotal }}</p>
                </div>
            </div>
        </div>

        <!-- KPI 2: Locados Agora -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-emerald-100 text-emerald-600 mr-4">
                    <i class="fas fa-key fa-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.rented_now') }}</p>
                    <p class="text-xl font-bold text-slate-800" id="kpiRentedNow">{{ $rented }}</p>
                </div>
            </div>
        </div>

        <!-- KPI 3: Taxa de Utilização -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-amber-100 text-amber-600 mr-4">
                    <i class="fas fa-chart-pie fa-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.utilization_rate') }}</p>
                    <p class="text-xl font-bold text-slate-800" id="kpiUtilization">{{ number_format($utilizationRate, 1, ',', '.') }}%</p>
                    <div class="kpi-progress">
                        <div class="kpi-progress-fill bg-amber-500" id="kpiUtilizationBar" style="width: {{ $utilizationRate }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Diária Média (ADR) -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-violet-100 text-violet-600 mr-4">
                    <i class="fas fa-tag fa-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.average_daily_rate') }}</p>
                    <p class="text-xl font-bold text-slate-800" id="kpiADR">{{ currency_format((float) ($fleet['average_daily_rate'] ?? 0)) }}</p>
                </div>
            </div>
        </div>

        <!-- KPI 5: Receita do Mês -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                    <i class="fas fa-dollar-sign fa-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.revenue_month') }}</p>
                    <p class="text-xl font-bold text-slate-800" id="kpiRevenue">{{ $financial !== null ? currency_format((float) $financial['revenue']) : '—' }}</p>
                </div>
            </div>
        </div>

        <!-- KPI 6: A Receber Vencido -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.overdue_amount') }}</p>
                    <p class="text-xl font-bold text-red-600" id="kpiOverdue">{{ $financial !== null ? currency_format((float) $financial['overdue_total']) : '—' }}</p>
                    <p class="text-xs text-slate-400" id="kpiOverdueCount">{{ $financial !== null ? (int) $financial['overdue_count'] : 0 }} {{ t('modules.dashboard.v2.kpi.invoices') }}</p>
                </div>
            </div>
        </div>

        <!-- KPI 7: Contratos Ativos -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-cyan-100 text-cyan-600 mr-4">
                    <i class="fas fa-file-contract fa-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.active_contracts') }}</p>
                    <p class="text-xl font-bold text-slate-800" id="kpiContracts">{{ $contracts !== null ? (int) $contracts['active'] : '—' }}</p>
                    <p class="text-xs text-amber-500" id="kpiContractsExpiring">{{ $contracts !== null ? (int) $contracts['expiring_soon'] : 0 }} {{ t('modules.dashboard.v2.kpi.expiring_soon') }}</p>
                </div>
            </div>
        </div>

        <!-- KPI 8: Custo Manutenção % -->
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                    <i class="fas fa-wrench fa-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-xs text-slate-500">{{ t('modules.dashboard.v2.kpi.maintenance_cost') }}</p>
                    @php $maintCost = $financial !== null ? (float) ($financial['maintenance_cost_pct'] ?? 0) : 0; @endphp
                    <p class="text-xl font-bold text-slate-800" id="kpiMaintCost">{{ number_format($maintCost, 1, ',', '.') }}%</p>
                    <div class="kpi-progress">
                        <div class="kpi-progress-fill bg-orange-500" id="kpiMaintCostBar" style="width: {{ $maintCost }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ZONA B + E: Frota (2/3) + Alertas (1/3) -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <!-- Coluna Esquerda: Frota + Operações (2/3) -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Barra de disponibilidade -->
            <div class="kpi-card">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-sm font-semibold text-slate-800">{{ t('modules.dashboard.availability.title') }}</h4>
                    <div class="text-xs text-slate-500">{{ t('modules.dashboard.availability.total') }}: <span id="availTotal">{{ $fleetTotal }}</span></div>
                </div>
                <div class="availability-bar-container mb-2" id="availBar">
                    <div class="availability-segment" style="width: {{ $pctAvailable }}%; background-color: #66BB6A;" title="{{ t('modules.dashboard.availability.available') }}: {{ $available }}">{{ $available }}</div>
                    <div class="availability-segment" style="width: {{ $pctRented }}%; background-color: #EF5350;" title="{{ t('modules.dashboard.availability.rented') }}: {{ $rented }}">{{ $rented }}</div>
                    <div class="availability-segment" style="width: {{ $pctReserved }}%; background-color: #42A5F5;" title="{{ t('modules.dashboard.availability.reserved') }}: {{ $reserved }}">{{ $reserved }}</div>
                    <div class="availability-segment" style="width: {{ $pctWorkshop }}%; background-color: #FFEE58; color: #5D4037;" title="{{ t('modules.dashboard.availability.workshop') }}: {{ $workshop }}">{{ $workshop }}</div>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-slate-700">
                    <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#66BB6A] rounded-full mr-1.5"></span>{{ t('modules.dashboard.availability.available') }} (<span id="availAvailable">{{ $available }}</span>)</div>
                    <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#EF5350] rounded-full mr-1.5"></span>{{ t('modules.dashboard.availability.rented') }} (<span id="availRented">{{ $rented }}</span>)</div>
                    <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#42A5F5] rounded-full mr-1.5"></span>{{ t('modules.dashboard.availability.reserved') }} (<span id="availReserved">{{ $reserved }}</span>)</div>
                    <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#FFEE58] rounded-full mr-1.5 border border-slate-400"></span>{{ t('modules.dashboard.availability.workshop') }} (<span id="availWorkshop">{{ $workshop }}</span>)</div>
                </div>
            </div>

            <!-- Operações do Dia -->
            <div>
                <h4 class="text-sm font-semibold text-slate-800 mb-3">{{ t('modules.dashboard.v2.operations.title') }}</h4>
                <div class="grid grid-cols-3 gap-4">
                    <!-- Saídas Hoje -->
                    <div class="ops-card">
                        <div class="text-emerald-500 mb-1"><i class="fas fa-sign-out-alt fa-lg"></i></div>
                        <div class="ops-number text-emerald-600" id="opsDepartures">{{ (int) ($operations['departures_today'] ?? 0) }}</div>
                        <p class="text-xs text-slate-500 mt-1">{{ t('modules.dashboard.v2.operations.departures_today') }}</p>
                    </div>
                    <!-- Devoluções Hoje -->
                    <div class="ops-card">
                        <div class="text-sky-500 mb-1"><i class="fas fa-sign-in-alt fa-lg"></i></div>
                        <div class="ops-number text-sky-600" id="opsReturns">{{ (int) ($operations['returns_today'] ?? 0) }}</div>
                        <p class="text-xs text-slate-500 mt-1">{{ t('modules.dashboard.v2.operations.returns_today') }}</p>
                    </div>
                    <!-- Atrasados -->
                    <div class="ops-card border-red-200 bg-red-50/50">
                        <div class="text-red-500 mb-1"><i class="fas fa-clock fa-lg"></i></div>
                        <div class="ops-number text-red-600" id="opsOverdue">{{ (int) ($operations['overdue'] ?? 0) }}</div>
                        <p class="text-xs text-red-400 mt-1">{{ t('modules.dashboard.v2.operations.overdue_returns') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Alertas (1/3) -->
        <div class="kpi-card">
            <div class="flex justify-between items-center mb-3">
                <h4 class="text-sm font-semibold text-slate-800">
                    <i class="fas fa-bell text-amber-500 mr-1"></i>{{ t('modules.dashboard.v2.alerts.title') }}
                </h4>
                @php $alerts = $stats['alerts'] ?? []; @endphp
                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-medium" id="alertCount">{{ count($alerts) }}</span>
            </div>
            <div id="alertsList" class="space-y-0 max-h-64 overflow-y-auto">
                @if(empty($alerts))
                    <div class="text-xs text-slate-400 text-center py-4">{{ t('common.labels.no_data') }}</div>
                @else
                    @foreach($alerts as $alert)
                        @php
                            $iconColor = match($alert['severity'] ?? 'info') {
                                'critical' => 'text-red-500',
                                'warning' => 'text-amber-500',
                                default => 'text-blue-500',
                            };
                        @endphp
                        <div class="alert-item {{ $alert['severity'] ?? 'info' }}">
                            <div class="flex items-start">
                                <i class="fas {{ $alert['icon'] ?? 'fa-bell' }} {{ $iconColor }} mr-2 mt-0.5 text-xs"></i>
                                <span class="text-slate-700">{{ $alert['message'] ?? '' }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ZONA C: Reservas Próximas (2/3) + Últimas Reservas (1/3) -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <!-- Tabela Reservas Próximos 7 Dias -->
        <div class="kpi-card lg:col-span-2">
            <h4 class="text-sm font-semibold text-slate-800 mb-3">
                <i class="fas fa-calendar-alt text-sky-500 mr-1"></i>{{ t('modules.dashboard.v2.reservations.upcoming_title') }}
            </h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200">
                            <th class="pb-2 font-medium">#</th>
                            <th class="pb-2 font-medium">{{ t('modules.dashboard.v2.reservations.code') }}</th>
                            <th class="pb-2 font-medium">{{ t('modules.dashboard.v2.reservations.client') }}</th>
                            <th class="pb-2 font-medium">{{ t('modules.dashboard.v2.reservations.vehicle') }}</th>
                            <th class="pb-2 font-medium">{{ t('modules.dashboard.v2.reservations.date') }}</th>
                        </tr>
                    </thead>
                    <tbody id="reservationsTable">
                        @php $reservations = $stats['reservations'] ?? []; @endphp
                        @if(empty($reservations))
                            <tr><td colspan="5" class="py-4 text-center text-xs text-slate-400">{{ t('common.labels.no_data') }}</td></tr>
                        @else
                            @foreach($reservations as $i => $r)
                                <tr class="{{ $i < count($reservations) - 1 ? 'border-b border-slate-100 ' : '' }}hover:bg-slate-50">
                                    <td class="py-2 text-slate-400">{{ $i + 1 }}</td>
                                    <td class="py-2 font-medium text-sky-600">{{ $r['codigo'] }}</td>
                                    <td class="py-2 text-slate-700">{{ $r['cliente'] }}</td>
                                    <td class="py-2 text-slate-600">{{ $r['veiculo'] }}</td>
                                    <td class="py-2 text-slate-600">{{ $r['data'] }}</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Feed Últimas Reservas -->
        <div class="kpi-card">
            <h4 class="text-sm font-semibold text-slate-800 mb-3">
                <i class="fas fa-stream text-violet-500 mr-1"></i>{{ t('modules.dashboard.v2.reservations.latest_title') }}
            </h4>
            <div id="latestReservationsFeed" class="max-h-64 overflow-y-auto">
                @php $latestList = $stats['latest_reservations'] ?? []; @endphp
                @if(empty($latestList))
                    <div class="text-xs text-slate-400 text-center py-4">{{ t('common.labels.no_data') }}</div>
                @else
                    @foreach($latestList as $r)
                        @php
                            $badgeClass = match($r['status'] ?? 'new') {
                                'confirmed' => 'badge-confirmed',
                                'cancelled' => 'badge-cancelled',
                                default => 'badge-new',
                            };
                            $badgeLabel = match($r['status'] ?? 'new') {
                                'confirmed' => t('modules.dashboard.v2.reservations.status_confirmed'),
                                'cancelled' => t('modules.dashboard.v2.reservations.status_cancelled'),
                                default => t('modules.dashboard.v2.reservations.status_new'),
                            };
                        @endphp
                        <div class="feed-item">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs text-slate-400">{{ $r['hora'] }}</span>
                                    <span class="font-medium text-slate-700 ml-1">{{ $r['codigo'] }}</span>
                                    <span class="text-slate-500 ml-1">{{ $r['cliente'] }} - {{ $r['veiculo'] }}</span>
                                </div>
                                <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ZONA D: Resumo Financeiro (visível para quem tem permissão) -->
    <!-- ============================================================ -->
    @php $canFinanceiro = \App\Core\Auth::can('financeiro.visualizar'); @endphp
    @if($canFinanceiro)
    @php
        $finRevenue = (float) ($financial['revenue'] ?? 0);
        $finExpenses = (float) ($financial['expenses'] ?? 0);
        $finBalance = (float) ($financial['balance'] ?? 0);
        $finMax = max($finRevenue, $finExpenses, 1);
        $overdueAccounts = $stats['overdue_accounts'] ?? [];
        $upcomingList = $stats['upcoming_due'] ?? [];
        $overdueTotal = array_sum(array_column($overdueAccounts, 'valor'));
        $upcomingTotal = array_sum(array_column($upcomingList, 'valor'));
    @endphp
    <div id="financialSection">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">
            <i class="fas fa-chart-bar text-indigo-500 mr-1"></i>{{ t('modules.dashboard.v2.financial.title') }}
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Fluxo do Mês -->
            <div class="kpi-card">
                <h5 class="text-xs font-semibold text-slate-600 mb-3">{{ t('modules.dashboard.v2.financial.cash_flow') }}</h5>

                <div class="mb-3">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-500">{{ t('modules.dashboard.v2.financial.revenue') }}</span>
                        <span class="font-medium text-emerald-600" id="finRevenue">{{ currency_format($finRevenue) }}</span>
                    </div>
                    <div class="flow-bar">
                        <div class="flow-bar-fill bg-emerald-500" id="finRevenueBar" style="width: {{ round(($finRevenue / $finMax) * 100, 1) }}%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-500">{{ t('modules.dashboard.v2.financial.expenses') }}</span>
                        <span class="font-medium text-red-500" id="finExpenses">{{ currency_format($finExpenses) }}</span>
                    </div>
                    <div class="flow-bar">
                        <div class="flow-bar-fill bg-red-400" id="finExpensesBar" style="width: {{ round(($finExpenses / $finMax) * 100, 1) }}%"></div>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-2 mt-2">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-slate-600">{{ t('modules.dashboard.v2.financial.balance') }}</span>
                        <span class="font-bold {{ $finBalance >= 0 ? 'text-emerald-600' : 'text-red-600' }}" id="finBalance">{{ ($finBalance >= 0 ? '+ ' : '- ') . currency_format(abs($finBalance)) }}</span>
                    </div>
                </div>
            </div>

            <!-- Top 5 Vencidas -->
            <div class="kpi-card">
                <h5 class="text-xs font-semibold text-slate-600 mb-3">{{ t('modules.dashboard.v2.financial.top_overdue') }}</h5>
                <div id="topOverdueList" class="space-y-2">
                    @if(empty($overdueAccounts))
                        <div class="text-xs text-slate-400 text-center py-2">{{ t('common.labels.no_data') }}</div>
                    @else
                        @foreach($overdueAccounts as $acc)
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600 truncate mr-2">{{ $acc['cliente'] }}</span>
                                <span class="font-medium text-red-500 whitespace-nowrap">{{ currency_format((float) $acc['valor']) }}</span>
                            </div>
                        @endforeach
                    @endif
                    @if(!empty($overdueAccounts))
                        <div class="border-t border-slate-200 pt-2 mt-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-slate-700">Total</span>
                                <span class="text-red-600" id="finOverdueTotal">{{ currency_format($overdueTotal) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Vencem em 30 Dias -->
            <div class="kpi-card">
                <h5 class="text-xs font-semibold text-slate-600 mb-3">{{ t('modules.dashboard.v2.financial.upcoming_due') }}</h5>
                <div id="upcomingDueList" class="space-y-2">
                    @if(empty($upcomingList))
                        <div class="text-xs text-slate-400 text-center py-2">{{ t('common.labels.no_data') }}</div>
                    @else
                        @foreach($upcomingList as $item)
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600 truncate mr-2">{{ $item['descricao'] }}</span>
                                <span class="font-medium text-amber-600 whitespace-nowrap">{{ currency_format((float) $item['valor']) }}</span>
                            </div>
                        @endforeach
                    @endif
                    @if(!empty($upcomingList))
                        <div class="border-t border-slate-200 pt-2 mt-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-slate-700">Total</span>
                                <span class="text-amber-600" id="finUpcomingTotal">{{ currency_format($upcomingTotal) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@section('scripts')
<!-- ============================================================ -->
<!-- Script: Auto-refresh a cada 30 segundos -->
<!-- ============================================================ -->
<script>
(function() {
    const REFRESH_INTERVAL = 30000;
    let pollingInterval = null;
    let dashboardRequestInFlight = false;
    let dashboardRetryAfterUntil = 0;

    function updateTimestamp(ts) {
        const el = document.getElementById('dashTimestamp');
        if (el) el.textContent = ts || new Date().toLocaleString('pt-BR');
    }

    function updateKPIs(fleet, financial, contracts) {
        const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        const setWidth = (id, pct) => { const el = document.getElementById(id); if (el) el.style.width = pct + '%'; };

        if (fleet) {
            setTxt('kpiFleetTotal', fleet.total);
            setTxt('kpiRentedNow', fleet.rented);
            setTxt('kpiUtilization', fleet.utilization_rate.toFixed(1).replace('.', ',') + '%');
            setWidth('kpiUtilizationBar', fleet.utilization_rate);
            setTxt('kpiADR', 'R$ ' + Math.round(fleet.average_daily_rate));
        }

        if (financial) {
            setTxt('kpiRevenue', 'R$ ' + financial.revenue.toLocaleString('pt-BR'));
            setTxt('kpiOverdue', 'R$ ' + financial.overdue_total.toLocaleString('pt-BR'));
            setTxt('kpiOverdueCount', financial.overdue_count + ' {{ t("modules.dashboard.v2.kpi.invoices") }}');
            setTxt('kpiMaintCost', financial.maintenance_cost_pct.toFixed(1).replace('.', ',') + '%');
            setWidth('kpiMaintCostBar', financial.maintenance_cost_pct);
        }

        if (contracts) {
            setTxt('kpiContracts', contracts.active);
            setTxt('kpiContractsExpiring', contracts.expiring_soon + ' {{ t("modules.dashboard.v2.kpi.expiring_soon") }}');
        }
    }

    function updateAvailabilityBar(fleet) {
        if (!fleet) return;
        const total = fleet.total || 1;
        const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

        setTxt('availTotal', total);
        setTxt('availAvailable', fleet.available);
        setTxt('availRented', fleet.rented);
        setTxt('availReserved', fleet.reserved);
        setTxt('availWorkshop', fleet.workshop);

        const bar = document.getElementById('availBar');
        if (bar) {
            const segments = bar.querySelectorAll('.availability-segment');
            const values = [fleet.available, fleet.rented, fleet.reserved, fleet.workshop];
            segments.forEach((seg, i) => {
                const pct = ((values[i] / total) * 100).toFixed(1);
                seg.style.width = pct + '%';
                seg.textContent = values[i];
                seg.title = seg.title.replace(/\d+$/, values[i]);
            });
        }
    }

    function updateOperations(ops) {
        if (!ops) return;
        const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setTxt('opsDepartures', ops.departures_today);
        setTxt('opsReturns', ops.returns_today);
        setTxt('opsOverdue', ops.overdue);
    }

    function updateReservationsTable(reservations) {
        const tbody = document.getElementById('reservationsTable');
        if (!tbody || !reservations) return;
        tbody.innerHTML = reservations.map((r, i) =>
            '<tr class="' + (i < reservations.length - 1 ? 'border-b border-slate-100 ' : '') + 'hover:bg-slate-50">' +
                '<td class="py-2 text-slate-400">' + (i + 1) + '</td>' +
                '<td class="py-2 font-medium text-sky-600">' + escapeHtml(r.codigo) + '</td>' +
                '<td class="py-2 text-slate-700">' + escapeHtml(r.cliente) + '</td>' +
                '<td class="py-2 text-slate-600">' + escapeHtml(r.veiculo) + '</td>' +
                '<td class="py-2 text-slate-600">' + escapeHtml(r.data) + '</td>' +
            '</tr>'
        ).join('');
    }

    function updateLatestFeed(latest) {
        const feed = document.getElementById('latestReservationsFeed');
        if (!feed || !latest) return;

        const statusBadge = {
            'confirmed': '<span class="badge-confirmed">{{ t("modules.dashboard.v2.reservations.status_confirmed") }}</span>',
            'new': '<span class="badge-new">{{ t("modules.dashboard.v2.reservations.status_new") }}</span>',
            'cancelled': '<span class="badge-cancelled">{{ t("modules.dashboard.v2.reservations.status_cancelled") }}</span>'
        };

        feed.innerHTML = latest.map(r =>
            '<div class="feed-item">' +
                '<div class="flex justify-between items-start">' +
                    '<div>' +
                        '<span class="text-xs text-slate-400">' + escapeHtml(r.hora) + '</span>' +
                        '<span class="font-medium text-slate-700 ml-1">' + escapeHtml(r.codigo) + '</span>' +
                        '<span class="text-slate-500 ml-1">' + escapeHtml(r.cliente) + ' - ' + escapeHtml(r.veiculo) + '</span>' +
                    '</div>' +
                    (statusBadge[r.status] || '') +
                '</div>' +
            '</div>'
        ).join('');
    }

    function updateAlerts(alerts) {
        const list = document.getElementById('alertsList');
        const countEl = document.getElementById('alertCount');
        if (!list || !alerts) return;

        if (countEl) countEl.textContent = alerts.length;

        list.innerHTML = alerts.map(a =>
            '<div class="alert-item ' + escapeHtml(a.severity) + '">' +
                '<div class="flex items-start">' +
                    '<i class="fas ' + escapeHtml(a.icon) + ' text-xs mr-2 mt-0.5 ' +
                        (a.severity === 'critical' ? 'text-red-500' : a.severity === 'warning' ? 'text-amber-500' : 'text-blue-500') +
                    '"></i>' +
                    '<span class="text-slate-700">' + escapeHtml(a.message) + '</span>' +
                '</div>' +
            '</div>'
        ).join('');
    }

    function updateFinancial(financial, overdue, upcoming) {
        if (!document.getElementById('financialSection')) return;

        const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        const setWidth = (id, pct) => { const el = document.getElementById(id); if (el) el.style.width = pct + '%'; };

        if (financial) {
            const maxVal = Math.max(financial.revenue, financial.expenses) || 1;
            setTxt('finRevenue', 'R$ ' + financial.revenue.toLocaleString('pt-BR'));
            setTxt('finExpenses', 'R$ ' + financial.expenses.toLocaleString('pt-BR'));
            setWidth('finRevenueBar', (financial.revenue / maxVal) * 100);
            setWidth('finExpensesBar', (financial.expenses / maxVal) * 100);

            const sign = financial.balance >= 0 ? '+ ' : '- ';
            setTxt('finBalance', sign + 'R$ ' + Math.abs(financial.balance).toLocaleString('pt-BR'));
            const balEl = document.getElementById('finBalance');
            if (balEl) {
                balEl.className = financial.balance >= 0 ? 'font-bold text-emerald-600' : 'font-bold text-red-600';
            }
        }

        if (overdue) {
            const list = document.getElementById('topOverdueList');
            if (list) {
                let total = 0;
                let html = overdue.map(o => {
                    total += o.valor;
                    return '<div class="flex justify-between text-xs">' +
                        '<span class="text-slate-600 truncate mr-2">' + escapeHtml(o.cliente) + '</span>' +
                        '<span class="font-medium text-red-500 whitespace-nowrap">R$ ' + o.valor.toLocaleString('pt-BR') + '</span>' +
                    '</div>';
                }).join('');
                html += '<div class="border-t border-slate-200 pt-2 mt-1">' +
                    '<div class="flex justify-between text-xs font-semibold">' +
                        '<span class="text-slate-700">Total</span>' +
                        '<span class="text-red-600">R$ ' + total.toLocaleString('pt-BR') + '</span>' +
                    '</div></div>';
                list.innerHTML = html;
            }
        }

        if (upcoming) {
            const list = document.getElementById('upcomingDueList');
            if (list) {
                let total = 0;
                let html = upcoming.map(u => {
                    total += u.valor;
                    return '<div class="flex justify-between text-xs">' +
                        '<span class="text-slate-600 truncate mr-2">' + escapeHtml(u.descricao) + '</span>' +
                        '<span class="font-medium text-amber-600 whitespace-nowrap">R$ ' + u.valor.toLocaleString('pt-BR') + '</span>' +
                    '</div>';
                }).join('');
                html += '<div class="border-t border-slate-200 pt-2 mt-1">' +
                    '<div class="flex justify-between text-xs font-semibold">' +
                        '<span class="text-slate-700">Total</span>' +
                        '<span class="text-amber-600">R$ ' + total.toLocaleString('pt-BR') + '</span>' +
                    '</div></div>';
                list.innerHTML = html;
            }
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    async function loadDashboardData() {
        if (dashboardRequestInFlight || Date.now() < dashboardRetryAfterUntil) {
            return;
        }

        dashboardRequestInFlight = true;

        try {
            const result = await API.get('/api/dashboard/stats');
            if (result.rate_limited) {
                const retryAfter = Math.max(1, Number(result.retry_after || 1));
                dashboardRetryAfterUntil = Date.now() + (retryAfter * 1000);
                return;
            }

            if (result.success) {
                const d = result.data;
                updateKPIs(d.fleet, d.financial, d.contracts);
                updateAvailabilityBar(d.fleet);
                updateOperations(d.operations);
                updateReservationsTable(d.reservations);
                updateLatestFeed(d.latest_reservations);
                updateAlerts(d.alerts);
                updateFinancial(d.financial, d.overdue_accounts, d.upcoming_due);
                updateTimestamp(result.timestamp);
            }
        } catch (err) {
            console.error('Dashboard refresh error:', err);
        } finally {
            dashboardRequestInFlight = false;
        }
    }

    function startPolling() {
        stopPolling();
        pollingInterval = setInterval(loadDashboardData, REFRESH_INTERVAL);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
        } else {
            loadDashboardData();
            startPolling();
        }
    });

    // Refresh proativo do CSRF token (antes dos 15min de expiração)
    setInterval(async function() {
        try {
            await API.refreshCsrfToken();
        } catch (e) {
            // Se falhar, o handleResponse do api.js vai tratar no próximo poll
        }
    }, 13 * 60 * 1000);

    // Inicialização
    updateTimestamp();
    loadDashboardData();
    startPolling();
})();
</script>
@endsection
