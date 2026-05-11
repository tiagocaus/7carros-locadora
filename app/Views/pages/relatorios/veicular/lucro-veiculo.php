@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.lucro_veiculo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.lucro_veiculo.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.lucro_veiculo.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.veicular.lucro_veiculo.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.lucro_veiculo.col_veiculo') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.veicular.lucro_veiculo.col_grupo') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_receita') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.veicular.lucro_veiculo.col_despesa') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_lucro') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_margem') ?></th>
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
    const i18n = {
        loading: '<?= t("common.labels.loading") ?>',
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
    };
    const API_URL = '/api/relatorios/veicular/lucro-veiculo';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'receita_total', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.receita_total") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'despesa_total', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.despesa_total") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'red' },
        { key: 'lucro_total', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.lucro_total") ?>', icon: 'fa-coins', format: 'currency', colorByValue: true },
        { key: 'margem_geral', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.margem_geral") ?>', icon: 'fa-percentage', format: 'percent', colorByValue: true },
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
            ReportUtils.exportPdf('/relatorios/veicular/lucro-veiculo/pdf?' + qs, '<?= t("modules.relatorios.veicular.lucro_veiculo.title") ?>');
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
        } catch (e) {
            console.error('Erro ao carregar relatório:', e);
            ReportUtils.showError(i18n.connectionError);
        }
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
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
            },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const lucroCls = row.lucro >= 0 ? 'text-green-700' : 'text-red-700';
            const margemCls = ReportUtils.getOccupancyColor(row.margem);
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa || '-'}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell hidden lg:table-cell">${row.grupo || '-'}</td>
                <td class="table-cell text-right">${Currency.format(row.receita, true)}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Currency.format(row.despesa_total, true)}</td>
                <td class="table-cell text-right font-semibold ${lucroCls}">${Currency.format(row.lucro, true)}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${margemCls}">${row.margem}%</span></td>
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
