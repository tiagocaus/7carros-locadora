@extends('layouts.iframe')

@section('title', t('modules.relatorios.funcionarios.metas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.funcionarios.metas.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.funcionarios.metas.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportNoDataAlert" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4 text-amber-800 text-sm" style="display: none;">
        <i class="fas fa-info-circle mr-2"></i>
        <strong><?= t('modules.relatorios.funcionarios.metas.no_data_title') ?></strong>
        <?= t('modules.relatorios.funcionarios.metas.no_data_msg') ?>
    </div>

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.funcionarios.metas.col_funcionario') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.funcionarios.metas.col_meta_receita') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.funcionarios.metas.col_realizado_receita') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.funcionarios.metas.col_pct_receita') ?></th>
                    <th class="table-header text-center hidden md:table-cell"><?= t('modules.relatorios.funcionarios.metas.col_meta_locacoes') ?></th>
                    <th class="table-header text-center hidden md:table-cell"><?= t('modules.relatorios.funcionarios.metas.col_realizado_locacoes') ?></th>
                    <th class="table-header text-center hidden md:table-cell"><?= t('modules.relatorios.funcionarios.metas.col_pct_locacoes') ?></th>
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
    const API_URL = '/api/relatorios/funcionarios/metas';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_funcionarios', label: '<?= t("modules.relatorios.funcionarios.metas.qtd_funcionarios") ?>', icon: 'fa-users', format: 'number' },
        { key: 'meta_receita_total', label: '<?= t("modules.relatorios.funcionarios.metas.meta_receita_total") ?>', icon: 'fa-bullseye', format: 'currency' },
        { key: 'realizado_receita_total', label: '<?= t("modules.relatorios.funcionarios.metas.realizado_receita_total") ?>', icon: 'fa-check', format: 'currency', color: 'green' },
        { key: 'pct_atingimento_receita', label: '<?= t("modules.relatorios.funcionarios.metas.pct_atingimento_receita") ?>', icon: 'fa-percentage', format: 'percent', colorByValue: true },
        { key: 'meta_locacoes_total', label: '<?= t("modules.relatorios.funcionarios.metas.meta_locacoes_total") ?>', icon: 'fa-list', format: 'number' },
        { key: 'realizado_locacoes_total', label: '<?= t("modules.relatorios.funcionarios.metas.realizado_locacoes_total") ?>', icon: 'fa-list-check', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/funcionarios/metas/pdf?' + qs, '<?= t("modules.relatorios.funcionarios.metas.title") ?>');
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

            const noDataAlert = document.getElementById('reportNoDataAlert');
            if (!result.totals.has_data) {
                noDataAlert.style.display = 'block';
                document.getElementById('reportChartContainer').style.display = 'none';
                document.getElementById('reportTableContainer').style.display = 'none';
                document.getElementById('reportEmptyState').style.display = 'none';
                return;
            } else {
                noDataAlert.style.display = 'none';
            }

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
                    label: ds.label, data: ds.data,
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    borderWidth: 1,
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
            const clsR = ReportUtils.getOccupancyColor(row.pct_atingimento_receita);
            const clsL = ReportUtils.getOccupancyColor(row.pct_atingimento_locacoes);
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.funcionario}</td>
                <td class="table-cell text-right">${Currency.format(row.meta_receita, true)}</td>
                <td class="table-cell text-right font-semibold">${Currency.format(row.realizado_receita, true)}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${clsR}">${row.pct_atingimento_receita}%</span></td>
                <td class="table-cell text-center hidden md:table-cell">${row.meta_locacoes}</td>
                <td class="table-cell text-center hidden md:table-cell">${row.realizado_locacoes}</td>
                <td class="table-cell text-center hidden md:table-cell"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${clsL}">${row.pct_atingimento_locacoes}%</span></td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('reportNoDataAlert').style.display = 'none';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
