@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.licenciamento.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.licenciamento.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.licenciamento.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true])

    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.licenciamento.filter_status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.veicular.licenciamento.status_all') ?></option>
                <option value="vencido"><?= t('modules.relatorios.veicular.licenciamento.status_vencido') ?></option>
                <option value="prox_30"><?= t('modules.relatorios.veicular.licenciamento.status_prox_30') ?></option>
                <option value="em_dia"><?= t('modules.relatorios.veicular.licenciamento.status_em_dia') ?></option>
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
                    <th class="table-header"><?= t('modules.relatorios.veicular.licenciamento.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.licenciamento.col_veiculo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.veicular.licenciamento.col_tipo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.veicular.licenciamento.col_vencimento') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.licenciamento.col_valor') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.licenciamento.col_status') ?></th>
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
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
    };
    const API_URL = '/api/relatorios/veicular/licenciamento';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_encargos', label: '<?= t("modules.relatorios.veicular.licenciamento.total_encargos") ?>', icon: 'fa-file-invoice', format: 'number' },
        { key: 'vencidos', label: '<?= t("modules.relatorios.veicular.licenciamento.vencidos") ?>', icon: 'fa-exclamation-triangle', format: 'number', color: 'red' },
        { key: 'prox_30', label: '<?= t("modules.relatorios.veicular.licenciamento.prox_30") ?>', icon: 'fa-clock', format: 'number', color: 'yellow' },
        { key: 'em_dia', label: '<?= t("modules.relatorios.veicular.licenciamento.em_dia") ?>', icon: 'fa-check-circle', format: 'number', color: 'green' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.veicular.licenciamento.valor_total") ?>', icon: 'fa-money-bill', format: 'currency' },
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
            ReportUtils.exportPdf('/relatorios/veicular/licenciamento/pdf?' + qs, '<?= t("modules.relatorios.veicular.licenciamento.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            grupo: document.getElementById('filterGrupo')?.value || '',
            status: document.getElementById('filterStatus').value,
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
            console.error(e);
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
            type: 'doughnut',
            data: {
                labels: c.labels,
                datasets: [{ data: c.datasets[0].data, backgroundColor: ReportUtils.COLORS.slice(0, c.labels.length) }],
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
            const cls = row.status === 'vencido' ? 'bg-red-100 text-red-800' :
                        row.status === 'prox_30' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-green-100 text-green-800';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa || '-'}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell">${row.tipo || '-'}</td>
                <td class="table-cell">${row.vencimento ? DateHelper.format(row.vencimento) : '-'}</td>
                <td class="table-cell text-right">${Currency.format(row.valor, true)}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}">${row.status_label}</span></td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        const g = document.getElementById('filterGrupo'); if (g) g.value = '';
        document.getElementById('filterStatus').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
