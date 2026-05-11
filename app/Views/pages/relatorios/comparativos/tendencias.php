@extends('layouts.iframe')

@section('title', t('modules.relatorios.comparativos.tendencias.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.comparativos.tendencias.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.comparativos.tendencias.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])

    <!-- Granularidade -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterGranularidade" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.comparativos.tendencias.filter_granularidade') ?></label>
            <select id="filterGranularidade" class="form-input-focus w-full text-sm">
                <option value="dia"><?= t('modules.relatorios.comparativos.tendencias.gran_dia') ?></option>
                <option value="semana"><?= t('modules.relatorios.comparativos.tendencias.gran_semana') ?></option>
                <option value="mes" selected><?= t('modules.relatorios.comparativos.tendencias.gran_mes') ?></option>
            </select>
        </div>
    </div>

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
                    <th class="table-header"><?= t('modules.relatorios.comparativos.tendencias.col_indicador') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.comparativos.tendencias.col_tendencia') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.comparativos.tendencias.col_variacao') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.comparativos.tendencias.col_inicio') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.comparativos.tendencias.col_fim') ?></th>
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
    const API_URL = '/api/relatorios/comparativos/tendencias';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_periodos', label: '<?= t("modules.relatorios.comparativos.tendencias.qtd_periodos") ?>', icon: 'fa-calendar', format: 'number' },
        { key: 'receita_total', label: '<?= t("modules.relatorios.comparativos.tendencias.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
        { key: 'qtd_locacoes_total', label: '<?= t("modules.relatorios.comparativos.tendencias.qtd_locacoes_total") ?>', icon: 'fa-list', format: 'number' },
        { key: 'ticket_medio_geral', label: '<?= t("modules.relatorios.comparativos.tendencias.ticket_medio_geral") ?>', icon: 'fa-receipt', format: 'currency' },
        { key: 'variacao_receita_pct', label: '<?= t("modules.relatorios.comparativos.tendencias.variacao_receita_pct") ?>', icon: 'fa-chart-line', format: 'percent', colorByValue: true },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/comparativos/tendencias/pdf?' + qs, '<?= t("modules.relatorios.comparativos.tendencias.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            granularidade: document.getElementById('filterGranularidade').value,
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
            type: 'line',
            data: {
                labels: c.labels,
                datasets: c.datasets.map((ds, i) => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    fill: !ds.dashed,
                    borderDash: ds.dashed ? [5, 5] : [],
                    tension: 0.3,
                })),
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const cls = row.tendencia === 'up' ? 'bg-green-100 text-green-800' :
                        row.tendencia === 'down' ? 'bg-red-100 text-red-800' :
                        'bg-slate-100 text-slate-700';
            const arrow = row.tendencia === 'up' ? '↑ Crescimento' : (row.tendencia === 'down' ? '↓ Queda' : '→ Estável');
            const inicio = row.serie && row.serie.length > 0 ? row.serie[0] : 0;
            const fim = row.serie && row.serie.length > 0 ? row.serie[row.serie.length - 1] : 0;
            const fmt = (v) => row.is_currency ? Currency.format(v, true) : Number(v).toLocaleString('pt-BR');
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.indicador}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}">${arrow}</span></td>
                <td class="table-cell text-right font-semibold">${row.variacao_pct}%</td>
                <td class="table-cell text-right hidden sm:table-cell">${fmt(inicio)}</td>
                <td class="table-cell text-right hidden sm:table-cell">${fmt(fim)}</td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterGranularidade').value = 'mes';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
