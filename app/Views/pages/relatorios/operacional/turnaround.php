@extends('layouts.iframe')

@section('title', t('modules.relatorios.operacional.turnaround.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.operacional.turnaround.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.operacional.turnaround.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.operacional.turnaround.col_veiculo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.turnaround.col_locacao_anterior') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.turnaround.col_data_chegada') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.turnaround.col_proxima_locacao') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.turnaround.col_data_saida') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.turnaround.col_turnaround') ?></th>
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
    const API_URL = '/api/relatorios/operacional/turnaround';
    const PDF_URL = '/relatorios/operacional/turnaround/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_periodos', label: '<?= t("modules.relatorios.operacional.turnaround.total_periodos") ?>', icon: 'fa-sync', format: 'number' },
        { key: 'turnaround_medio_horas', label: '<?= t("modules.relatorios.operacional.turnaround.medio_horas") ?>', icon: 'fa-stopwatch', format: 'number', color: 'green' },
        { key: 'turnaround_total_horas', label: '<?= t("modules.relatorios.operacional.turnaround.total_horas") ?>', icon: 'fa-hourglass', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.operacional.turnaround.title") ?>'));
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
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.operacional.turnaround.qtd_por_faixa") ?>', data: c.data || [], backgroundColor: 'rgba(34, 197, 94, .7)', borderColor: 'rgb(34, 197, 94)', borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tb.innerHTML = lista.map(r => {
            const horas = Number(r.turnaround_horas || 0);
            const fmt = horas < 24 ? horas + 'h' : Math.floor(horas/24) + 'd ' + Math.round(horas % 24) + 'h';
            const cls = horas < 24 ? 'text-green-700' : horas < 48 ? 'text-blue-700' : horas < 168 ? 'text-yellow-700' : 'text-red-600';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell"><strong>${r.placa || '-'}</strong> <span class="text-xs text-slate-500">${r.veiculo_modelo || ''}</span></td>
                <td class="table-cell text-slate-600 text-xs">${r.locacao_anterior || '-'}</td>
                <td class="table-cell text-center">${r.data_chegada ? DateHelper.formatDateTime(r.data_chegada) : '-'}</td>
                <td class="table-cell text-slate-600 text-xs">${r.proxima_locacao || '-'}</td>
                <td class="table-cell text-center">${r.data_saida_proxima ? DateHelper.formatDateTime(r.data_saida_proxima) : '-'}</td>
                <td class="table-cell text-center font-semibold ${cls}">${fmt}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
