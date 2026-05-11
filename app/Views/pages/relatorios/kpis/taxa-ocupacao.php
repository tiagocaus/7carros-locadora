@extends('layouts.iframe')

@section('title', t('modules.relatorios.kpis.taxa_ocupacao.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.kpis.taxa_ocupacao.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.kpis.taxa_ocupacao.description') ?></p>

    <!-- Filtros -->
    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true])

    <!-- Exportação -->
    @include('pages.relatorios._partials.export-buttons')

    <!-- Totalizadores -->
    @include('pages.relatorios._partials.totalizadores')

    <!-- Gráfico -->
    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    <!-- Estado vazio -->
    @include('pages.relatorios._partials.empty-state')

    <!-- Tabela -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_placa') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_veiculo') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_grupo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_dias_locados') ?></th>
                    <th class="table-header text-center hidden sm:table-cell"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_dias_parados') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.kpis.taxa_ocupacao.col_taxa') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const i18n = {
        loading: '<?= t("common.labels.loading") ?>',
        noData: '<?= t("modules.relatorios.common.no_data") ?>',
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
    };

    const API_URL = '/api/relatorios/kpis/taxa-ocupacao';
    let chartInstance = null;

    // Totalizadores config
    const totalsConfig = [
        { key: 'total_veiculos', label: '<?= t("modules.relatorios.kpis.taxa_ocupacao.total_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'dias_disponiveis', label: '<?= t("modules.relatorios.kpis.taxa_ocupacao.dias_disponiveis") ?>', icon: 'fa-calendar', format: 'number' },
        { key: 'dias_locados', label: '<?= t("modules.relatorios.kpis.taxa_ocupacao.dias_locados") ?>', icon: 'fa-calendar-check', format: 'number', color: 'green' },
        { key: 'dias_parados', label: '<?= t("modules.relatorios.kpis.taxa_ocupacao.dias_parados") ?>', icon: 'fa-calendar-times', format: 'number', color: 'red' },
        { key: 'taxa_ocupacao', label: '<?= t("modules.relatorios.kpis.taxa_ocupacao.taxa") ?>', icon: 'fa-chart-pie', format: 'percent', colorByValue: true },
    ];

    // ===== INICIALIZAÇÃO =====

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');

        // Período padrão: mês atual
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/kpis/taxa-ocupacao/pdf', '<?= t("modules.relatorios.kpis.taxa_ocupacao.title") ?>'));
    }

    // ===== CARREGAR RELATÓRIO =====

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                grupo: document.getElementById('filterGrupo')?.value || '',
            };

            const result = await API.get(API_URL, params);

            if (!result.success) {
                ReportUtils.showError(result.message || i18n.loadError);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();

        } catch (error) {
            console.error('Erro ao carregar relatório:', error);
            ReportUtils.showError(i18n.connectionError);
        }
    }

    // ===== RENDERIZAR TOTALIZADORES =====

    function renderTotals(totals) {
        const container = document.getElementById('reportTotals');
        container.innerHTML = ReportUtils.buildTotalCards(totals, totalsConfig);
    }

    // ===== RENDERIZAR GRÁFICO =====

    function renderChart(chartData) {
        const container = document.getElementById('reportChartContainer');
        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets.map((ds, i) => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    fill: true,
                    tension: 0.3,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: v => v + '%' },
                    },
                },
            },
        });
    }

    // ===== RENDERIZAR TABELA =====

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');

        if (!data || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const taxaColor = ReportUtils.getOccupancyColor(row.taxa_ocupacao);
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa || '-'}</td>
                <td class="table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell hidden md:table-cell">${row.grupo || '-'}</td>
                <td class="table-cell text-center">${row.dias_locados}</td>
                <td class="table-cell text-center hidden sm:table-cell">${row.dias_parados}</td>
                <td class="table-cell text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${taxaColor}">${row.taxa_ocupacao}%</span>
                </td>
            </tr>`;
        }).join('');
    }

    // ===== LIMPAR FILTROS =====

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        const grupoEl = document.getElementById('filterGrupo');
        if (grupoEl) grupoEl.value = '';
        ReportUtils.hideContent();
    }

    // Iniciar
    init();
})();
</script>
@endsection
