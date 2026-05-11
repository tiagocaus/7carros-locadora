@extends('layouts.app')

@section('title', t('modules.dashboard.title') . ' - 7Carros Locadora')

@section('content')
@php
    $fleet = $stats['fleet'] ?? [];
    $operations = $stats['operations'] ?? [];
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
@endphp
<!-- Tab Content: Início -->
<div id="tab-inicio" class="tab-content active-content pl-4 pr-6 py-0" data-tab-content-id="inicio">
    <!-- Cards KPI (Fixos no topo) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-sky-100 text-sky-600 mr-4">
                    <i class="fas fa-car fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ t('modules.dashboard.kpi.total_vehicles') }}</p>
                    <p class="title-page">{{ $fleetTotal }}</p>
                </div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-emerald-100 text-emerald-600 mr-4">
                    <i class="fas fa-key fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ t('modules.dashboard.kpi.rented_today') }}</p>
                    <p class="title-page">{{ (int) ($operations['departures_today'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-amber-100 text-amber-600 mr-4">
                    <i class="fas fa-chart-pie fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ t('modules.dashboard.kpi.occupancy_rate') }}</p>
                    <p class="title-page">{{ number_format((float) ($fleet['utilization_rate'] ?? 0), 0) }}%</p>
                </div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ t('modules.dashboard.kpi.expected_revenue_today') }}</p>
                    <p class="title-page">{{ currency_format((float) ($fleet['expected_revenue_today'] ?? 0)) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de disponibilidade (Fixa no topo) -->
    <div class="kpi-card mb-6">
        <div class="flex justify-between items-center mb-2">
            <h4 class="text-base font-semibold text-slate-800">{{ t('modules.dashboard.availability.title') }}</h4>
            <div class="text-xs text-slate-500">{{ t('modules.dashboard.availability.total') }}: {{ $fleetTotal }}</div>
        </div>
        <div class="availability-bar-container mb-2.5">
            <div class="availability-segment" style="width: {{ $pctAvailable }}%; background-color: #66BB6A;" title="{{ t('modules.dashboard.availability.available') }}: {{ $available }}">{{ $available }}</div>
            <div class="availability-segment" style="width: {{ $pctRented }}%; background-color: #EF5350;" title="{{ t('modules.dashboard.availability.rented') }}: {{ $rented }}">{{ $rented }}</div>
            <div class="availability-segment" style="width: {{ $pctReserved }}%; background-color: #42A5F5;" title="{{ t('modules.dashboard.availability.reserved') }}: {{ $reserved }}">{{ $reserved }}</div>
            <div class="availability-segment" style="width: {{ $pctWorkshop }}%; background-color: #FFEE58; color: #5D4037;" title="{{ t('modules.dashboard.availability.workshop') }}: {{ $workshop }}">{{ $workshop }}</div>
        </div>
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-slate-700">
            <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#66BB6A] rounded-full mr-1.5"></span>{{ t('modules.dashboard.availability.available') }} ({{ $available }})</div>
            <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#EF5350] rounded-full mr-1.5"></span>{{ t('modules.dashboard.availability.rented') }} ({{ $rented }})</div>
            <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#42A5F5] rounded-full mr-1.5"></span>{{ t('modules.dashboard.availability.reserved') }} ({{ $reserved }})</div>
            <div class="flex items-center"><span class="h-2.5 w-2.5 bg-[#FFEE58] rounded-full mr-1.5 border border-slate-400"></span>{{ t('modules.dashboard.availability.workshop') }} ({{ $workshop }})</div>
        </div>
    </div>

    <!-- Sub-tabs do dashboard -->
    <div class="border-b border-slate-300">
        <nav class="flex space-x-1 -mb-px overflow-x-auto pb-1" id="inicioSubTabsNav">
            <a href="#" data-subtab-target="#inicioSubTabBuscaRapida" class="tab-inactive-main hover:tab-active-main whitespace-nowrap py-3 px-4 text-sm subtab-link">
                {{ t('modules.dashboard.tabs.quick_search') }}
            </a>
            <a href="#" data-subtab-target="#inicioSubTabReservas" class="tab-active-main whitespace-nowrap py-3 px-4 text-sm subtab-link" aria-current="page">
                {{ t('modules.dashboard.tabs.reservations') }}
            </a>
            <a href="#" data-subtab-target="#inicioSubTabAlugados" class="tab-inactive-main hover:tab-active-main whitespace-nowrap py-3 px-4 text-sm subtab-link">
                {{ t('modules.dashboard.tabs.rented') }}
            </a>
            <a href="#" data-subtab-target="#inicioSubTabDisponiveis" class="tab-inactive-main hover:tab-active-main whitespace-nowrap py-3 px-4 text-sm subtab-link">
                {{ t('modules.dashboard.tabs.available') }}
            </a>
            <a href="#" data-subtab-target="#inicioSubTabChegadaPendente" class="tab-inactive-main hover:tab-active-main whitespace-nowrap py-3 px-4 text-sm subtab-link">
                {{ t('modules.dashboard.tabs.pending_arrival') }}
            </a>
            <a href="#" data-subtab-target="#inicioSubTabProximasDevolucoes" class="tab-inactive-main hover:tab-active-main whitespace-nowrap py-3 px-4 text-sm subtab-link">
                {{ t('modules.dashboard.tabs.upcoming_returns') }}
            </a>
        </nav>
    </div>

    <!-- Conteúdo das sub-tabs -->
    <div id="inicioSubTabContentArea" class="py-5">
        <div id="inicioSubTabBuscaRapida" class="subtab-content hidden">
            <div class="min-h-32 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">
                <p class="text-slate-500">{{ t('modules.dashboard.placeholders.tab_content', ['tab' => t('modules.dashboard.tabs.quick_search')]) }}</p>
            </div>
        </div>
        <div id="inicioSubTabReservas" class="subtab-content" data-dashboard-subtab="reservas">
            <div class="min-h-32 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">
                <p class="text-slate-500">{{ t('modules.dashboard.placeholders.tab_content_will_appear', ['tab' => t('modules.dashboard.tabs.reservations')]) }}</p>
            </div>
        </div>
        <div id="inicioSubTabAlugados" class="subtab-content hidden" data-dashboard-subtab="alugados">
            <div class="min-h-32 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">
                <p class="text-slate-500">{{ t('modules.dashboard.placeholders.tab_content', ['tab' => t('modules.dashboard.tabs.rented')]) }}</p>
            </div>
        </div>
        <div id="inicioSubTabDisponiveis" class="subtab-content hidden" data-dashboard-subtab="disponiveis">
            <div class="min-h-32 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">
                <p class="text-slate-500">{{ t('modules.dashboard.placeholders.tab_content', ['tab' => t('modules.dashboard.tabs.available')]) }}</p>
            </div>
        </div>
        <div id="inicioSubTabChegadaPendente" class="subtab-content hidden" data-dashboard-subtab="chegada_pendente">
            <div class="min-h-32 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">
                <p class="text-slate-500">{{ t('modules.dashboard.placeholders.tab_content', ['tab' => t('modules.dashboard.tabs.pending_arrival')]) }}</p>
            </div>
        </div>
        <div id="inicioSubTabProximasDevolucoes" class="subtab-content hidden" data-dashboard-subtab="proximas_devolucoes">
            <div class="min-h-32 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">
                <p class="text-slate-500">{{ t('modules.dashboard.placeholders.tab_content', ['tab' => t('modules.dashboard.tabs.upcoming_returns')]) }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
