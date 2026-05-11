@extends('layouts.iframe')

@section('title', t('modules.relatorios.funcionarios.produtividade.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.funcionarios.produtividade.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.funcionarios.produtividade.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.funcionarios.produtividade.col_funcionario') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.funcionarios.produtividade.col_dias_trabalhados') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.funcionarios.produtividade.col_locacoes') ?></th>
                    <th class="table-header text-center hidden sm:table-cell"><?= t('modules.relatorios.funcionarios.produtividade.col_locacoes_dia') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.funcionarios.produtividade.col_faturamento') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.funcionarios.produtividade.col_faturamento_dia') ?></th>
                    <th class="table-header text-center hidden lg:table-cell"><?= t('modules.relatorios.funcionarios.produtividade.col_checklists') ?></th>
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
    const API_URL = '/api/relatorios/funcionarios/produtividade';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_funcionarios', label: '<?= t("modules.relatorios.funcionarios.produtividade.qtd_funcionarios") ?>', icon: 'fa-users', format: 'number' },
        { key: 'qtd_locacoes', label: '<?= t("modules.relatorios.funcionarios.produtividade.qtd_locacoes") ?>', icon: 'fa-list', format: 'number' },
        { key: 'faturamento_total', label: '<?= t("modules.relatorios.funcionarios.produtividade.faturamento_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
        { key: 'qtd_checklists', label: '<?= t("modules.relatorios.funcionarios.produtividade.qtd_checklists") ?>', icon: 'fa-clipboard-check', format: 'number' },
        { key: 'media_locacoes_funcionario', label: '<?= t("modules.relatorios.funcionarios.produtividade.media_loc") ?>', icon: 'fa-balance-scale', format: 'number' },
        { key: 'media_faturamento_funcionario', label: '<?= t("modules.relatorios.funcionarios.produtividade.media_fat") ?>', icon: 'fa-balance-scale-right', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/funcionarios/produtividade/pdf?' + qs, '<?= t("modules.relatorios.funcionarios.produtividade.title") ?>');
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
            type: 'bar',
            data: { labels: c.labels, datasets: [{ label: c.datasets[0].label, data: c.datasets[0].data, backgroundColor: ReportUtils.COLORS_ALPHA[2], borderColor: ReportUtils.COLORS[2], borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => `
            <tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.funcionario}</td>
                <td class="table-cell text-center">${row.dias_trabalhados}</td>
                <td class="table-cell text-center">${row.locacoes}</td>
                <td class="table-cell text-center hidden sm:table-cell">${row.locacoes_dia}</td>
                <td class="table-cell text-right font-semibold">${Currency.format(row.faturamento, true)}</td>
                <td class="table-cell text-right hidden md:table-cell">${Currency.format(row.faturamento_dia, true)}</td>
                <td class="table-cell text-center hidden lg:table-cell">${row.checklists}</td>
            </tr>`).join('');
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
