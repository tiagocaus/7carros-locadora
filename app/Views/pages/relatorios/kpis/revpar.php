@extends('layouts.iframe')

@section('title', t('modules.relatorios.kpis.revpar.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.kpis.revpar.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.kpis.revpar.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.kpis.revpar.col_grupo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.kpis.revpar.col_veiculos') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.kpis.revpar.col_receita') ?></th>
                    <th class="table-header text-center hidden sm:table-cell"><?= t('modules.relatorios.kpis.revpar.col_dias') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.kpis.revpar.col_revpar') ?></th>
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
    const API_URL = '/api/relatorios/kpis/revpar';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'receita_total', label: '<?= t("modules.relatorios.kpis.revpar.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'total_veiculos', label: '<?= t("modules.relatorios.kpis.revpar.total_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'dias_disponiveis', label: '<?= t("modules.relatorios.kpis.revpar.dias_disponiveis") ?>', icon: 'fa-calendar', format: 'number' },
        { key: 'revpar', label: '<?= t("modules.relatorios.kpis.revpar.revpar") ?>', icon: 'fa-chart-bar', format: 'currency', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/kpis/revpar/pdf', '<?= t("modules.relatorios.kpis.revpar.title") ?>'));
        document.getElementById('btnLimpar')?.addEventListener('click', () => { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; const g = document.getElementById('filterGrupo'); if (g) g.value = ''; ReportUtils.hideContent(); });
    }

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
            type: 'bar',
            data: { labels: chartData.labels, datasets: chartData.datasets.map((ds, i) => ({ label: ds.label, data: ds.data, backgroundColor: ReportUtils.COLORS_ALPHA[i], borderColor: ReportUtils.COLORS[i], borderWidth: 1 })) },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data?.length) { container.style.display = 'none'; return; }
        container.style.display = 'block';
        tbody.innerHTML = data.map(row => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${row.grupo || '-'}</td>
            <td class="table-cell text-center">${row.total_veiculos}</td>
            <td class="table-cell text-right">${Currency.format(row.receita, true)}</td>
            <td class="table-cell text-center hidden sm:table-cell">${row.dias_disponiveis}</td>
            <td class="table-cell text-right font-medium">${Currency.format(row.revpar, true)}</td>
        </tr>`).join('');
    }

    init();
})();
</script>
@endsection
