@extends('layouts.iframe')

@section('title', t('modules.relatorios.operacional.devolucoes_antecipadas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_codigo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_veiculo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_prevista') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_chegada') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_dias_antecipado') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.operacional.devolucoes_antecipadas.col_valor') ?></th>
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
    const API_URL = '/api/relatorios/operacional/devolucoes-antecipadas';
    const PDF_URL = '/relatorios/operacional/devolucoes-antecipadas/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_devolucoes_antecipadas', label: '<?= t("modules.relatorios.operacional.devolucoes_antecipadas.total") ?>', icon: 'fa-undo', format: 'number' },
        { key: 'media_dias_antecipados', label: '<?= t("modules.relatorios.operacional.devolucoes_antecipadas.media_dias") ?>', icon: 'fa-calendar-day', format: 'number' },
        { key: 'total_dias_antecipados', label: '<?= t("modules.relatorios.operacional.devolucoes_antecipadas.total_dias") ?>', icon: 'fa-calendar-alt', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.operacional.devolucoes_antecipadas.title") ?>'));
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
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.operacional.devolucoes_antecipadas.qtd_por_faixa") ?>', data: c.data || [], backgroundColor: 'rgba(59, 130, 246, .7)', borderColor: 'rgb(59, 130, 246)', borderWidth: 1 }] },
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
            <td class="table-cell text-slate-600 text-xs">${r.codigo || '-'}</td>
            <td class="table-cell">${r.cliente_nome || '-'}</td>
            <td class="table-cell"><strong>${r.placa || '-'}</strong> <span class="text-xs text-slate-500">${r.veiculo_modelo || ''}</span></td>
            <td class="table-cell text-center">${r.data_prevista ? DateHelper.format(r.data_prevista) : '-'}</td>
            <td class="table-cell text-center">${r.data_chegada ? DateHelper.format(r.data_chegada) : '-'}</td>
            <td class="table-cell text-center font-semibold text-blue-700">${r.dias_antecipado || 0}d</td>
            <td class="table-cell text-right">${cf(r.total_pagar)}</td>
        </tr>`).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
