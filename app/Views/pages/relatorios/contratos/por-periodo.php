@extends('layouts.iframe')

@section('title', t('modules.relatorios.contratos.por_periodo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.contratos.por_periodo.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.contratos.por_periodo.description') ?></p>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_start') ?></label>
            <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_end') ?></label>
            <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[180px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.contratos.por_periodo.granularidade') ?></label>
            <select id="filterGranularidade" class="form-input-focus w-full text-sm">
                <option value="dia"><?= t('modules.relatorios.contratos.por_periodo.gran_dia') ?></option>
                <option value="semana"><?= t('modules.relatorios.contratos.por_periodo.gran_semana') ?></option>
                <option value="mes" selected><?= t('modules.relatorios.contratos.por_periodo.gran_mes') ?></option>
                <option value="trimestre"><?= t('modules.relatorios.contratos.por_periodo.gran_trimestre') ?></option>
                <option value="ano"><?= t('modules.relatorios.contratos.por_periodo.gran_ano') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
            </button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2"><i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?></button>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="240"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.contratos.por_periodo.col_periodo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.por_periodo.col_locacoes') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.por_periodo.col_dias') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.por_periodo.col_receita') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.por_periodo.col_ticket_medio') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.por_periodo.col_variacao') ?></th>
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
    const API_URL = '/api/relatorios/contratos/por-periodo';
    const PDF_URL = '/relatorios/contratos/por-periodo/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_locacoes', label: '<?= t("modules.relatorios.contratos.por_periodo.qtd_locacoes") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'dias', label: '<?= t("modules.relatorios.contratos.por_periodo.total_dias") ?>', icon: 'fa-calendar-day', format: 'number' },
        { key: 'receita', label: '<?= t("modules.relatorios.contratos.por_periodo.receita") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'ticket_medio', label: '<?= t("modules.relatorios.contratos.por_periodo.ticket_medio") ?>', icon: 'fa-receipt', format: 'currency' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', exportarPdf);
    }

    function getParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            granularidade: document.getElementById('filterGranularidade').value,
        };
    }

    function exportarPdf() {
        const qs = new URLSearchParams(getParams()).toString();
        ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.contratos.por_periodo.title") ?>');
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, getParams());
            if (!r.success) { ReportUtils.showError(r.message); return; }
            renderTotals(r.totals);
            renderChart(r.chart);
            renderTable(r.data && r.data.lista ? r.data.lista : []);
            ReportUtils.showContent();
        } catch (e) {
            console.error('Erro:', e);
            ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>');
        }
    }

    function renderTotals(t) {
        document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, totalsConfig);
    }

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
                datasets: [{
                    label: '<?= t("modules.relatorios.contratos.por_periodo.receita") ?>',
                    data: c.data || [],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, .15)',
                    fill: true,
                    tension: 0.3,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
            },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);

        tb.innerHTML = lista.map(row => {
            let varHtml = '<span class="text-slate-400">-</span>';
            if (row.variacao_pct !== null && row.variacao_pct !== undefined) {
                const v = Number(row.variacao_pct);
                const cls = v > 0 ? 'text-green-600' : (v < 0 ? 'text-red-600' : 'text-slate-500');
                const arrow = v > 0 ? '▲' : (v < 0 ? '▼' : '—');
                varHtml = `<span class="${cls}">${arrow} ${v.toFixed(2).replace('.', ',')}%</span>`;
            }
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.periodo_label || '-'}</td>
                <td class="table-cell text-center">${row.qtd_locacoes || 0}</td>
                <td class="table-cell text-center">${row.dias || 0}</td>
                <td class="table-cell text-right font-semibold">${cf(row.receita)}</td>
                <td class="table-cell text-right">${cf(row.ticket_medio)}</td>
                <td class="table-cell text-right">${varHtml}</td>
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
