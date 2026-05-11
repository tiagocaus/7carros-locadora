@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.inativos.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.inativos.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.inativos.description') ?></p>

    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.clientes.inativos.dias_minimo') ?></label>
            <input type="number" id="filterDiasMinimo" min="1" max="3650" value="180" class="form-input-focus w-full text-sm">
        </div>
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
        <canvas id="reportChart" height="240"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.clientes.inativos.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.inativos.col_cpf_cnpj') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.inativos.col_ultima_locacao') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.inativos.col_dias_inativo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.inativos.col_total_locacoes') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.inativos.col_faturamento') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.inativos.col_telefone') ?></th>
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
    const API_URL = '/api/relatorios/clientes/inativos';
    const PDF_URL = '/relatorios/clientes/inativos/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_inativos', label: '<?= t("modules.relatorios.clientes.inativos.qtd_inativos") ?>', icon: 'fa-user-slash', format: 'number', color: 'red' },
        { key: 'qtd_nunca_locaram', label: '<?= t("modules.relatorios.clientes.inativos.qtd_nunca_locaram") ?>', icon: 'fa-user-times', format: 'number' },
        { key: 'media_dias_inativo', label: '<?= t("modules.relatorios.clientes.inativos.media_dias_inativo") ?>', icon: 'fa-calendar-times', format: 'number' },
        { key: 'faturamento_perdido', label: '<?= t("modules.relatorios.clientes.inativos.faturamento_perdido") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'yellow' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams({ dias_minimo: document.getElementById('filterDiasMinimo').value || '180', filial: document.getElementById('filterFilial').value }).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.clientes.inativos.title") ?>');
        });
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, { dias_minimo: document.getElementById('filterDiasMinimo').value || '180', filial: document.getElementById('filterFilial').value });
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
            type: 'doughnut',
            data: { labels: c.labels, datasets: [{ data: c.data || [], backgroundColor: ['rgba(100, 116, 139, .7)', 'rgba(250, 204, 21, .7)', 'rgba(249, 115, 22, .7)', 'rgba(239, 68, 68, .7)', 'rgba(127, 29, 29, .7)'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tb.innerHTML = lista.map(r => {
            const ultima = r.nunca_locou
                ? `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><?= t("modules.relatorios.clientes.inativos.nunca_locou") ?></span>`
                : (r.ultima_locacao ? DateHelper.format(r.ultima_locacao) : '-');
            const dias = r.nunca_locou ? '-' : (r.dias_inativo + 'd');
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${r.cliente || '-'}</td>
                <td class="table-cell text-slate-600 text-xs">${r.cpf_cnpj || ''}</td>
                <td class="table-cell text-center">${ultima}</td>
                <td class="table-cell text-center">${dias}</td>
                <td class="table-cell text-center">${r.total_locacoes || 0}</td>
                <td class="table-cell text-right">${cf(r.faturamento)}</td>
                <td class="table-cell text-slate-600 text-xs">${r.telefone || '-'}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { document.getElementById('filterDiasMinimo').value = '180'; document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
