@extends('layouts.iframe')

@section('title', t('modules.relatorios.operacional.multas_transito.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.operacional.multas_transito.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.operacional.multas_transito.description') ?></p>

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
        <div class="flex-1 min-w-[180px] max-w-[230px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.operacional.multas_transito.status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="novo">Novo</option>
                <option value="pendente_indicacao">Pendente Indicação</option>
                <option value="indicacao_enviada">Indicação Enviada</option>
                <option value="transferido">Transferido</option>
                <option value="pago">Pago</option>
                <option value="cancelado">Cancelado</option>
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
        <canvas id="reportChart" height="220"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.multas_transito.col_data') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.multas_transito.col_veiculo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.multas_transito.col_locacao') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.multas_transito.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.multas_transito.col_descricao') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.multas_transito.col_local') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.operacional.multas_transito.col_valor') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.multas_transito.col_status') ?></th>
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
    const API_URL = '/api/relatorios/operacional/multas-transito';
    const PDF_URL = '/relatorios/operacional/multas-transito/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_multas', label: '<?= t("modules.relatorios.operacional.multas_transito.total_multas") ?>', icon: 'fa-traffic-light', format: 'number' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.operacional.multas_transito.valor_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'qtd_pagas', label: '<?= t("modules.relatorios.operacional.multas_transito.qtd_pagas") ?>', icon: 'fa-check-circle', format: 'number', color: 'green' },
        { key: 'qtd_pendentes', label: '<?= t("modules.relatorios.operacional.multas_transito.qtd_pendentes") ?>', icon: 'fa-clock', format: 'number', color: 'red' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(getParams()).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.operacional.multas_transito.title") ?>');
        });
    }

    function getParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            status: document.getElementById('filterStatus').value,
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, getParams());
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
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.operacional.multas_transito.qtd_por_tipo") ?>', data: c.data || [], backgroundColor: 'rgba(239, 68, 68, .7)', borderColor: 'rgb(239, 68, 68)', borderWidth: 1 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: '<?= t("modules.relatorios.operacional.multas_transito.top_tipos") ?>' } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tb.innerHTML = lista.map(r => {
            const pagoBadge = r.pago === 'S'
                ? '<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><?= t("modules.relatorios.operacional.multas_transito.pago") ?></span>'
                : '<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">' + (r.status_processamento || '<?= t("modules.relatorios.operacional.multas_transito.pendente") ?>') + '</span>';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center">${r.data_hora ? DateHelper.format(r.data_hora) : '-'}</td>
                <td class="table-cell"><strong>${r.placa || '-'}</strong> <span class="text-xs text-slate-500">${r.veiculo_modelo || ''}</span></td>
                <td class="table-cell text-slate-600 text-xs">${r.locacao_codigo || '-'}</td>
                <td class="table-cell">${r.cliente_nome || '-'}</td>
                <td class="table-cell text-slate-600 text-xs">${r.descricao || '-'}</td>
                <td class="table-cell text-center text-slate-500 text-xs">${[r.cidade, r.estado].filter(x=>x).join('/') || '-'}</td>
                <td class="table-cell text-right font-semibold">${cf(r.valor)}</td>
                <td class="table-cell text-center">${pagoBadge}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; document.getElementById('filterStatus').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
