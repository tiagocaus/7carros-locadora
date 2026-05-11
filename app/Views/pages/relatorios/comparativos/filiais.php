@extends('layouts.iframe')

@section('title', t('modules.relatorios.comparativos.filiais.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.comparativos.filiais.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.comparativos.filiais.description') ?></p>

    <!-- Filtros: só período (filial agrega todas que o usuário tem acesso) -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataInicio" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_start') ?></label>
            <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataFim" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_end') ?></label>
            <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
            </button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
                <i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?>
            </button>
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
                    <th class="table-header text-center"><?= t('modules.relatorios.comparativos.filiais.col_ranking') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.comparativos.filiais.col_filial') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.comparativos.filiais.col_cidade') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.comparativos.filiais.col_veiculos') ?></th>
                    <th class="table-header text-center hidden sm:table-cell"><?= t('modules.relatorios.comparativos.filiais.col_locacoes') ?></th>
                    <th class="table-header text-center hidden md:table-cell"><?= t('modules.relatorios.comparativos.filiais.col_contratos') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.comparativos.filiais.col_faturamento') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.comparativos.filiais.col_ticket_medio') ?></th>
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
    const API_URL = '/api/relatorios/comparativos/filiais';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_filiais', label: '<?= t("modules.relatorios.comparativos.filiais.qtd_filiais") ?>', icon: 'fa-building', format: 'number' },
        { key: 'total_veiculos', label: '<?= t("modules.relatorios.comparativos.filiais.total_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.comparativos.filiais.total_locacoes") ?>', icon: 'fa-list', format: 'number' },
        { key: 'total_contratos', label: '<?= t("modules.relatorios.comparativos.filiais.total_contratos") ?>', icon: 'fa-file-signature', format: 'number' },
        { key: 'faturamento_total', label: '<?= t("modules.relatorios.comparativos.filiais.faturamento_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
        { key: 'ticket_medio_geral', label: '<?= t("modules.relatorios.comparativos.filiais.ticket_medio_geral") ?>', icon: 'fa-receipt', format: 'currency' },
    ];

    async function init() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/comparativos/filiais/pdf?' + qs, '<?= t("modules.relatorios.comparativos.filiais.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
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
                datasets: [{
                    label: c.datasets[0].label,
                    data: c.datasets[0].data,
                    backgroundColor: ReportUtils.COLORS_ALPHA[0],
                    borderColor: ReportUtils.COLORS[0],
                    borderWidth: 1,
                }],
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
            const rankCls = row.ranking === 1 ? 'bg-yellow-100 text-yellow-800' :
                            row.ranking === 2 ? 'bg-slate-200 text-slate-800' :
                            row.ranking === 3 ? 'bg-orange-100 text-orange-800' :
                            'bg-slate-100 text-slate-600';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center"><span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold ${rankCls}">${row.ranking}º</span></td>
                <td class="table-cell font-medium">${row.filial}</td>
                <td class="table-cell hidden lg:table-cell text-slate-500">${row.cidade || '-'}</td>
                <td class="table-cell text-center">${row.veiculos}</td>
                <td class="table-cell text-center hidden sm:table-cell">${row.qtd_locacoes}</td>
                <td class="table-cell text-center hidden md:table-cell">${row.qtd_contratos}</td>
                <td class="table-cell text-right font-semibold">${Currency.format(row.faturamento, true)}</td>
                <td class="table-cell text-right hidden md:table-cell">${Currency.format(row.ticket_medio, true)}</td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
