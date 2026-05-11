@extends('layouts.iframe')

@section('title', t('modules.relatorios.comparativos.mensal_anual.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.comparativos.mensal_anual.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.comparativos.mensal_anual.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])

    <!-- Filtros do período anterior (opcionais — se vazios, calcula automaticamente) -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-amber-50 rounded-lg items-end">
        <div class="text-xs text-amber-800 font-medium w-full">
            <?= t('modules.relatorios.comparativos.mensal_anual.previous_period_hint') ?>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataInicioAnterior" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.comparativos.mensal_anual.previous_start') ?></label>
            <input type="date" id="filterDataInicioAnterior" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataFimAnterior" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.comparativos.mensal_anual.previous_end') ?></label>
            <input type="date" id="filterDataFimAnterior" class="form-input-focus w-full text-sm">
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
                    <th class="table-header"><?= t('modules.relatorios.comparativos.mensal_anual.col_indicador') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.comparativos.mensal_anual.col_anterior') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.comparativos.mensal_anual.col_atual') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.comparativos.mensal_anual.col_variacao_abs') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.comparativos.mensal_anual.col_variacao_pct') ?></th>
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
    const API_URL = '/api/relatorios/comparativos/mensal-anual';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_locacoes_atual', label: '<?= t("modules.relatorios.comparativos.mensal_anual.qtd_locacoes_atual") ?>', icon: 'fa-list', format: 'number' },
        { key: 'qtd_locacoes_anterior', label: '<?= t("modules.relatorios.comparativos.mensal_anual.qtd_locacoes_anterior") ?>', icon: 'fa-history', format: 'number' },
        { key: 'faturamento_atual', label: '<?= t("modules.relatorios.comparativos.mensal_anual.faturamento_atual") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'faturamento_anterior', label: '<?= t("modules.relatorios.comparativos.mensal_anual.faturamento_anterior") ?>', icon: 'fa-clock-rotate-left', format: 'currency' },
        { key: 'variacao_faturamento_pct', label: '<?= t("modules.relatorios.comparativos.mensal_anual.variacao_faturamento_pct") ?>', icon: 'fa-percentage', format: 'percent', colorByValue: true },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/comparativos/mensal-anual/pdf?' + qs, '<?= t("modules.relatorios.comparativos.mensal_anual.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            data_inicio_anterior: document.getElementById('filterDataInicioAnterior').value,
            data_fim_anterior: document.getElementById('filterDataFimAnterior').value,
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
                datasets: c.datasets.map((ds, i) => ({
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    borderWidth: 1,
                })),
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const cls = row.tendencia === 'up' ? 'text-green-700' : (row.tendencia === 'down' ? 'text-red-700' : 'text-slate-500');
            const arrow = row.tendencia === 'up' ? '↑' : (row.tendencia === 'down' ? '↓' : '→');
            const fmtAtual = row.is_currency ? Currency.format(row.atual, true) : Number(row.atual).toLocaleString('pt-BR');
            const fmtAnterior = row.is_currency ? Currency.format(row.anterior, true) : Number(row.anterior).toLocaleString('pt-BR');
            const fmtAbs = row.is_currency ? Currency.format(row.variacao_abs, true) : Number(row.variacao_abs).toLocaleString('pt-BR');
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.indicador}</td>
                <td class="table-cell text-right">${fmtAnterior}</td>
                <td class="table-cell text-right font-semibold">${fmtAtual}</td>
                <td class="table-cell text-right hidden sm:table-cell ${cls}">${fmtAbs}</td>
                <td class="table-cell text-center ${cls} font-semibold">${arrow} ${row.variacao_pct}%</td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterDataInicioAnterior').value = '';
        document.getElementById('filterDataFimAnterior').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
