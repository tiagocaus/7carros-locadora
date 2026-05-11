@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.tempo_relacionamento.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.tempo_relacionamento.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.tempo_relacionamento.description') ?></p>

    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow"><i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?></button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2"><i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?></button>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="200"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_cliente') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_desde') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_meses') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_total_locacoes') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_faturamento') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.tempo_relacionamento.col_ultima') ?></th>
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
    const API_URL = '/api/relatorios/clientes/tempo-relacionamento';
    const PDF_URL = '/relatorios/clientes/tempo-relacionamento/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_clientes', label: '<?= t("modules.relatorios.clientes.tempo_relacionamento.qtd_clientes") ?>', icon: 'fa-users', format: 'number' },
        { key: 'idade_media_meses', label: '<?= t("modules.relatorios.clientes.tempo_relacionamento.idade_media_meses") ?>', icon: 'fa-hourglass-half', format: 'number' },
        { key: 'faturamento_total', label: '<?= t("modules.relatorios.clientes.tempo_relacionamento.faturamento_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'ltv_medio', label: '<?= t("modules.relatorios.clientes.tempo_relacionamento.ltv_medio") ?>', icon: 'fa-chart-line', format: 'currency', color: 'green' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams({ filial: document.getElementById('filterFilial').value }).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.clientes.tempo_relacionamento.title") ?>');
        });
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, { filial: document.getElementById('filterFilial').value });
            if (!r.success) { ReportUtils.showError(r.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(r.totals, totalsConfig);
            renderChart(r.chart);
            renderTable(r.data && r.data.lista ? r.data.lista : []);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.clientes.tempo_relacionamento.qtd_clientes") ?>', data: c.data || [], backgroundColor: 'rgba(99, 102, 241, .7)', borderColor: 'rgb(99, 102, 241)', borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tb.innerHTML = lista.map(r => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${r.cliente || '-'}</td>
            <td class="table-cell text-center">${r.desde ? DateHelper.format(r.desde) : '-'}</td>
            <td class="table-cell text-center">${r.meses || 0}</td>
            <td class="table-cell text-center">${r.total_locacoes || 0}</td>
            <td class="table-cell text-right font-semibold">${cf(r.faturamento_lifetime)}</td>
            <td class="table-cell text-center">${r.ultima_locacao ? DateHelper.format(r.ultima_locacao) : '-'}</td>
        </tr>`).join('');
    }

    function limpar() { document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
