@extends('layouts.iframe')

@section('title', t('modules.relatorios.operacional.reservas_canceladas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.operacional.reservas_canceladas.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.operacional.reservas_canceladas.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.operacional.reservas_canceladas.col_codigo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.reservas_canceladas.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.reservas_canceladas.col_veiculo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.reservas_canceladas.col_data_reserva') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.reservas_canceladas.col_prevista_saida') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.reservas_canceladas.col_antecedencia') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.operacional.reservas_canceladas.col_valor_perdido') ?></th>
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
    const API_URL = '/api/relatorios/operacional/reservas-canceladas';
    const PDF_URL = '/relatorios/operacional/reservas-canceladas/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_canceladas', label: '<?= t("modules.relatorios.operacional.reservas_canceladas.total") ?>', icon: 'fa-times-circle', format: 'number', color: 'red' },
        { key: 'valor_perdido', label: '<?= t("modules.relatorios.operacional.reservas_canceladas.valor_perdido") ?>', icon: 'fa-dollar-sign', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.operacional.reservas_canceladas.title") ?>'));
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
            type: 'doughnut',
            data: { labels: c.labels, datasets: [{ data: c.data || [], backgroundColor: ['rgba(239, 68, 68, .7)', 'rgba(249, 115, 22, .7)', 'rgba(250, 204, 21, .7)', 'rgba(100, 116, 139, .7)'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } },
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
            <td class="table-cell text-center">${r.data_reserva ? DateHelper.format(r.data_reserva) : '-'}</td>
            <td class="table-cell text-center">${r.data_prevista_saida ? DateHelper.format(r.data_prevista_saida) : '-'}</td>
            <td class="table-cell text-center">${r.antecedencia || 0}d</td>
            <td class="table-cell text-right">${cf(r.valor_perdido)}</td>
        </tr>`).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
