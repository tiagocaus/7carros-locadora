@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.top_clientes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.top_clientes.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.top_clientes.description') ?></p>

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
        <div class="flex-1 min-w-[140px] max-w-[180px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.clientes.top_clientes.criterio') ?></label>
            <select id="filterCriterio" class="form-input-focus w-full text-sm">
                <option value="valor"><?= t('modules.relatorios.clientes.top_clientes.crit_valor') ?></option>
                <option value="locacoes"><?= t('modules.relatorios.clientes.top_clientes.crit_locacoes') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[100px] max-w-[120px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.clientes.top_clientes.limite') ?></label>
            <select id="filterLimite" class="form-input-focus w-full text-sm">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
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
        <canvas id="reportChart" height="300"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.top_clientes.col_posicao') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.top_clientes.col_cliente') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.top_clientes.col_tipo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.top_clientes.col_locacoes') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.top_clientes.col_faturamento') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.top_clientes.col_ticket_medio') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.top_clientes.col_desde') ?></th>
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
    const API_URL = '/api/relatorios/clientes/top-clientes';
    const PDF_URL = '/relatorios/clientes/top-clientes/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_clientes', label: '<?= t("modules.relatorios.clientes.top_clientes.qtd_clientes") ?>', icon: 'fa-trophy', format: 'number' },
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.clientes.top_clientes.total_locacoes") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'faturamento_total', label: '<?= t("modules.relatorios.clientes.top_clientes.faturamento_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(getParams()).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.clientes.top_clientes.title") ?>');
        });
    }

    function getParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            criterio: document.getElementById('filterCriterio').value,
            limite: document.getElementById('filterLimite').value,
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, getParams());
            if (!r.success) { ReportUtils.showError(r.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(r.totals, totalsConfig);
            renderChart(r.chart, r.totals.criterio);
            renderTable(r.data && r.data.lista ? r.data.lista : [], r.totals.criterio);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    function renderChart(c, criterio) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels: c.labels, datasets: [{ label: criterio === 'locacoes' ? '<?= t("modules.relatorios.clientes.top_clientes.crit_locacoes") ?>' : '<?= t("modules.relatorios.clientes.top_clientes.crit_valor") ?>', data: c.data || [], backgroundColor: 'rgba(250, 204, 21, .7)', borderColor: 'rgb(250, 204, 21)', borderWidth: 1 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => criterio === 'locacoes' ? v : Currency.format(v, true) } } } },
        });
    }

    function renderTable(lista, criterio) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tb.innerHTML = lista.map(r => {
            const tipoLabel = r.tipo === 'pj' ? 'PJ' : 'PF';
            const medalha = r.posicao === 1 ? '🥇' : r.posicao === 2 ? '🥈' : r.posicao === 3 ? '🥉' : ('#' + r.posicao);
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center font-bold">${medalha}</td>
                <td class="table-cell font-medium">${r.cliente || '-'}<div class="text-xs text-slate-500">${r.cpf_cnpj || ''}</div></td>
                <td class="table-cell text-center text-xs text-slate-600">${tipoLabel}</td>
                <td class="table-cell text-center">${r.total_locacoes || 0}</td>
                <td class="table-cell text-right font-semibold">${cf(r.faturamento_total)}</td>
                <td class="table-cell text-right">${cf(r.ticket_medio)}</td>
                <td class="table-cell text-center">${r.desde ? DateHelper.format(r.desde) : '-'}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; document.getElementById('filterCriterio').value = 'valor'; document.getElementById('filterLimite').value = '10'; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
