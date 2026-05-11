@extends('layouts.iframe')

@section('title', t('modules.relatorios.operacional.combustivel.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.operacional.combustivel.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.operacional.combustivel.description') ?></p>

    @include('pages.relatorios._partials.filters')
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
                    <th class="table-header"><?= t('modules.relatorios.operacional.combustivel.col_codigo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.combustivel.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.combustivel.col_veiculo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.combustivel.col_nivel_saida') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.combustivel.col_nivel_chegada') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.combustivel.col_diferenca') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.combustivel.col_data') ?></th>
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
    const API_URL = '/api/relatorios/operacional/combustivel';
    const PDF_URL = '/relatorios/operacional/combustivel/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.operacional.combustivel.total_locacoes") ?>', icon: 'fa-gas-pump', format: 'number' },
        { key: 'qtd_com_diferenca', label: '<?= t("modules.relatorios.operacional.combustivel.qtd_com_diferenca") ?>', icon: 'fa-exclamation-triangle', format: 'number', color: 'red' },
        { key: 'taxa_diferenca', label: '<?= t("modules.relatorios.operacional.combustivel.taxa_diferenca") ?>', icon: 'fa-percentage', format: 'percent' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.operacional.combustivel.title") ?>'));
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, { data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value });
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
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.operacional.combustivel.qtd_por_nivel") ?>', data: c.data || [], backgroundColor: 'rgba(99, 102, 241, .7)', borderColor: 'rgb(99, 102, 241)', borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tb.innerHTML = lista.map(r => {
            const dif = r.diferenca;
            const difTxt = dif === null || dif === undefined ? '-' : (dif > 0 ? '+' : '') + dif;
            const cls = r.tem_diferenca ? 'text-red-600 font-semibold' : 'text-slate-500';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-slate-600 text-xs">${r.codigo || '-'}</td>
                <td class="table-cell">${r.cliente_nome || '-'}</td>
                <td class="table-cell"><strong>${r.placa || '-'}</strong> <span class="text-xs text-slate-500">${r.veiculo_modelo || ''}</span></td>
                <td class="table-cell text-center text-xs">${r.nivel_saida || '-'}</td>
                <td class="table-cell text-center text-xs">${r.nivel_chegada || '-'}</td>
                <td class="table-cell text-center ${cls}">${difTxt}</td>
                <td class="table-cell text-center">${r.data_chegada ? DateHelper.format(r.data_chegada) : '-'}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
