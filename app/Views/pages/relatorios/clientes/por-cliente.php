@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.por_cliente.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.por_cliente.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.por_cliente.description') ?></p>

    @include('pages.relatorios._partials.filters')
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
                    <th class="table-header"><?= t('modules.relatorios.clientes.por_cliente.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.por_cliente.col_cpf_cnpj') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.por_cliente.col_total_locacoes') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.por_cliente.col_primeira') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.por_cliente.col_ultima') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.por_cliente.col_faturamento') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.por_cliente.col_ticket_medio') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.por_cliente.col_dias_medio') ?></th>
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
    const API_URL = '/api/relatorios/clientes/por-cliente';
    const PDF_URL = '/relatorios/clientes/por-cliente/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_clientes', label: '<?= t("modules.relatorios.clientes.por_cliente.qtd_clientes") ?>', icon: 'fa-users', format: 'number' },
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.clientes.por_cliente.total_locacoes") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'faturamento_total', label: '<?= t("modules.relatorios.clientes.por_cliente.faturamento_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'ticket_medio', label: '<?= t("modules.relatorios.clientes.por_cliente.ticket_medio") ?>', icon: 'fa-receipt', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.clientes.por_cliente.title") ?>'));
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
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.clientes.por_cliente.faturamento_total") ?>', data: c.data || [], backgroundColor: 'rgba(34, 197, 94, .7)', borderColor: 'rgb(34, 197, 94)', borderWidth: 1 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: '<?= t("modules.relatorios.clientes.por_cliente.top10") ?>' } }, scales: { x: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } } },
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
            <td class="table-cell text-slate-600 text-xs">${r.cpf_cnpj || ''}</td>
            <td class="table-cell text-center">${r.total_locacoes || 0}</td>
            <td class="table-cell text-center">${r.primeira_locacao ? DateHelper.format(r.primeira_locacao) : '-'}</td>
            <td class="table-cell text-center">${r.ultima_locacao ? DateHelper.format(r.ultima_locacao) : '-'}</td>
            <td class="table-cell text-right font-semibold">${cf(r.faturamento_total)}</td>
            <td class="table-cell text-right">${cf(r.ticket_medio)}</td>
            <td class="table-cell text-center">${r.dias_medio || 0}</td>
        </tr>`).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
