@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.tempo_parado.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.tempo_parado.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.tempo_parado.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.veicular.tempo_parado.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.tempo_parado.col_veiculo') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.veicular.tempo_parado.col_grupo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.tempo_parado.col_dias_periodo') ?></th>
                    <th class="table-header text-center hidden sm:table-cell"><?= t('modules.relatorios.veicular.tempo_parado.col_dias_locados') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.tempo_parado.col_dias_parados') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.tempo_parado.col_pct') ?></th>
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
    const i18n = { loadError: '<?= t("modules.relatorios.messages.load_error") ?>', connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>' };
    const API_URL = '/api/relatorios/veicular/tempo-parado';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.veicular.tempo_parado.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'dias_periodo', label: '<?= t("modules.relatorios.veicular.tempo_parado.dias_periodo") ?>', icon: 'fa-calendar', format: 'number' },
        { key: 'dias_locados_total', label: '<?= t("modules.relatorios.veicular.tempo_parado.dias_locados_total") ?>', icon: 'fa-calendar-check', format: 'number', color: 'green' },
        { key: 'dias_parados_total', label: '<?= t("modules.relatorios.veicular.tempo_parado.dias_parados_total") ?>', icon: 'fa-calendar-times', format: 'number', color: 'red' },
        { key: 'pct_ociosidade_geral', label: '<?= t("modules.relatorios.veicular.tempo_parado.pct_ociosidade") ?>', icon: 'fa-chart-pie', format: 'percent', colorByValue: true },
        { key: 'media_dias_parado', label: '<?= t("modules.relatorios.veicular.tempo_parado.media_parado") ?>', icon: 'fa-balance-scale', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/veicular/tempo-parado/pdf?' + qs, '<?= t("modules.relatorios.veicular.tempo_parado.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            grupo: document.getElementById('filterGrupo')?.value || '',
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, buildParams());
            if (!result.success) { ReportUtils.showError(result.message || i18n.loadError); return; }
            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError(i18n.connectionError); }
    }

    function renderTotals(t) { document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, totalsConfig); }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: c.labels,
                datasets: [
                    { label: c.datasets[0].label, data: c.datasets[0].data, backgroundColor: ReportUtils.COLORS_ALPHA[1], borderColor: ReportUtils.COLORS[1], borderWidth: 1, stack: 's' },
                    { label: c.datasets[1].label, data: c.datasets[1].data, backgroundColor: ReportUtils.COLORS_ALPHA[0], borderColor: ReportUtils.COLORS[0], borderWidth: 1, stack: 's' },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const cls = row.pct_ociosidade >= 70 ? 'bg-red-100 text-red-800' :
                        row.pct_ociosidade >= 40 ? 'bg-yellow-100 text-yellow-800' :
                        'bg-green-100 text-green-800';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo}</td>
                <td class="table-cell hidden lg:table-cell">${row.grupo}</td>
                <td class="table-cell text-center">${row.dias_periodo}</td>
                <td class="table-cell text-center hidden sm:table-cell text-green-700">${row.dias_locados}</td>
                <td class="table-cell text-center text-red-700">${row.dias_parados}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}">${row.pct_ociosidade}%</span></td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        const g = document.getElementById('filterGrupo'); if (g) g.value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
