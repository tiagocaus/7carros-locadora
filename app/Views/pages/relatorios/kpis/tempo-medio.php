@extends('layouts.iframe')

@section('title', t('modules.relatorios.kpis.tempo_medio.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.kpis.tempo_medio.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.kpis.tempo_medio.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.kpis.tempo_medio.col_faixa') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.kpis.tempo_medio.col_quantidade') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/kpis/tempo-medio';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_operacoes', label: '<?= t("modules.relatorios.kpis.tempo_medio.total_operacoes") ?>', icon: 'fa-list', format: 'number' },
        { key: 'total_dias', label: '<?= t("modules.relatorios.kpis.tempo_medio.total_dias") ?>', icon: 'fa-calendar', format: 'number' },
        { key: 'media_dias', label: '<?= t("modules.relatorios.kpis.tempo_medio.media_dias") ?>', icon: 'fa-clock', format: 'number', color: 'green' },
        { key: 'minimo', label: '<?= t("modules.relatorios.kpis.tempo_medio.minimo") ?>', icon: 'fa-arrow-down', format: 'number' },
        { key: 'maximo', label: '<?= t("modules.relatorios.kpis.tempo_medio.maximo") ?>', icon: 'fa-arrow-up', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/kpis/tempo-medio/pdf', '<?= t("modules.relatorios.kpis.tempo_medio.title") ?>'));
        document.getElementById('btnLimpar')?.addEventListener('click', () => { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; const g = document.getElementById('filterGrupo'); if (g) g.value = ''; ReportUtils.hideContent(); });
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const params = { data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value, grupo: document.getElementById('filterGrupo')?.value || '' };
            const result = await API.get(API_URL, params);
            if (!result.success) { ReportUtils.showError(result.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (e) { ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    function renderChart(chartData) {
        const container = document.getElementById('reportChartContainer');
        if (!chartData?.labels?.length) { container.style.display = 'none'; return; }
        container.style.display = 'block';
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(document.getElementById('reportChart').getContext('2d'), {
            type: 'bar', data: { labels: chartData.labels, datasets: chartData.datasets.map((ds, i) => ({ label: ds.label, data: ds.data, backgroundColor: ReportUtils.COLORS.slice(0, ds.data.length), borderWidth: 0 })) },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data?.length) { container.style.display = 'none'; return; }
        container.style.display = 'block';
        tbody.innerHTML = data.map(row => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${row.faixa}</td>
            <td class="table-cell text-center">${row.quantidade}</td>
        </tr>`).join('');
    }

    init();
})();
</script>
@endsection
