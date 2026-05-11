@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.ocorrencias.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.ocorrencias.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.ocorrencias.description') ?></p>

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
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.ocorrencias.col_data') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.ocorrencias.col_tipo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.ocorrencias.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.ocorrencias.col_locacao') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.ocorrencias.col_descricao') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.clientes.ocorrencias.col_valor') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.ocorrencias.col_status') ?></th>
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
    const API_URL = '/api/relatorios/clientes/ocorrencias';
    const PDF_URL = '/relatorios/clientes/ocorrencias/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_ocorrencias', label: '<?= t("modules.relatorios.clientes.ocorrencias.qtd_ocorrencias") ?>', icon: 'fa-exclamation-circle', format: 'number' },
        { key: 'qtd_atrasos', label: '<?= t("modules.relatorios.clientes.ocorrencias.qtd_atrasos") ?>', icon: 'fa-clock', format: 'number', color: 'yellow' },
        { key: 'qtd_inadimplencia', label: '<?= t("modules.relatorios.clientes.ocorrencias.qtd_inadimplencia") ?>', icon: 'fa-money-bill-wave', format: 'number', color: 'red' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.clientes.ocorrencias.valor_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
    ];

    const TIPO_LABEL = {
        devolucao_atrasada: '<?= t("modules.relatorios.clientes.ocorrencias.tipo_atraso") ?>',
        inadimplencia: '<?= t("modules.relatorios.clientes.ocorrencias.tipo_inadimplencia") ?>',
    };
    const TIPO_COLOR = {
        devolucao_atrasada: 'bg-yellow-100 text-yellow-700',
        inadimplencia: 'bg-red-100 text-red-700',
    };
    const STATUS_LABEL = {
        finalizada: '<?= t("modules.relatorios.clientes.ocorrencias.status_finalizada") ?>',
        pendente: '<?= t("modules.relatorios.clientes.ocorrencias.status_pendente") ?>',
    };
    const STATUS_COLOR = {
        finalizada: 'bg-slate-100 text-slate-700',
        pendente: 'bg-orange-100 text-orange-700',
    };

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.clientes.ocorrencias.title") ?>'));
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
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.clientes.ocorrencias.qtd_ocorrencias") ?>', data: c.data || [], backgroundColor: 'rgba(239, 68, 68, .7)', borderColor: 'rgb(239, 68, 68)', borderWidth: 1 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: '<?= t("modules.relatorios.clientes.ocorrencias.top10") ?>' } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tb.innerHTML = lista.map(r => {
            const tipoBadge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${TIPO_COLOR[r.tipo] || 'bg-slate-100 text-slate-700'}">${TIPO_LABEL[r.tipo] || r.tipo}</span>`;
            const stBadge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLOR[r.status] || 'bg-slate-100 text-slate-700'}">${STATUS_LABEL[r.status] || r.status}</span>`;
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center">${r.data ? DateHelper.format(r.data) : '-'}</td>
                <td class="table-cell">${tipoBadge}</td>
                <td class="table-cell font-medium">${r.cliente || '-'}</td>
                <td class="table-cell text-slate-600 text-xs">${r.locacao || '-'}</td>
                <td class="table-cell text-slate-600">${r.descricao || '-'}</td>
                <td class="table-cell text-right font-semibold">${cf(r.valor)}</td>
                <td class="table-cell text-center">${stBadge}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
