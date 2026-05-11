@extends('layouts.iframe')

@section('title', t('modules.relatorios.comercial.taxa_conversao.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.comercial.taxa_conversao.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.comercial.taxa_conversao.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])
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
                    <th class="table-header"><?= t('modules.relatorios.comercial.taxa_conversao.col_status') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.comercial.taxa_conversao.col_qtd') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.comercial.taxa_conversao.col_faturamento') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.comercial.taxa_conversao.col_pct') ?></th>
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
    const API_URL = '/api/relatorios/comercial/taxa-conversao';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_geral', label: '<?= t("modules.relatorios.comercial.taxa_conversao.total_geral") ?>', icon: 'fa-list', format: 'number' },
        { key: 'reservas', label: '<?= t("modules.relatorios.comercial.taxa_conversao.reservas") ?>', icon: 'fa-clock', format: 'number', color: 'yellow' },
        { key: 'convertidas', label: '<?= t("modules.relatorios.comercial.taxa_conversao.convertidas") ?>', icon: 'fa-check-circle', format: 'number', color: 'green' },
        { key: 'canceladas', label: '<?= t("modules.relatorios.comercial.taxa_conversao.canceladas") ?>', icon: 'fa-times-circle', format: 'number', color: 'red' },
        { key: 'taxa_conversao', label: '<?= t("modules.relatorios.comercial.taxa_conversao.taxa") ?>', icon: 'fa-percentage', format: 'percent', colorByValue: true },
        { key: 'taxa_cancelamento', label: '<?= t("modules.relatorios.comercial.taxa_conversao.cancelamento") ?>', icon: 'fa-ban', format: 'percent', color: 'red' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/comercial/taxa-conversao/pdf?' + qs, '<?= t("modules.relatorios.comercial.taxa_conversao.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
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
            type: 'doughnut',
            data: {
                labels: c.labels,
                datasets: [{ data: c.datasets[0].data, backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#ef4444'] }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const cls = row.status === 'F' ? 'bg-green-100 text-green-800' :
                        row.status === 'A' ? 'bg-blue-100 text-blue-800' :
                        row.status === 'R' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}">${row.status_label}</span></td>
                <td class="table-cell text-center font-semibold">${row.qtd}</td>
                <td class="table-cell text-right">${Currency.format(row.faturamento, true)}</td>
                <td class="table-cell text-center">${row.pct}%</td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
