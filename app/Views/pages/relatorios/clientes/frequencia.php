@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.frequencia.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.frequencia.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.frequencia.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.clientes.frequencia.col_cliente') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.frequencia.col_total_locacoes') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.frequencia.col_primeira') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.frequencia.col_ultima') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.frequencia.col_intervalo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.frequencia.col_classificacao') ?></th>
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
    const API_URL = '/api/relatorios/clientes/frequencia';
    const PDF_URL = '/relatorios/clientes/frequencia/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_clientes', label: '<?= t("modules.relatorios.clientes.frequencia.qtd_clientes") ?>', icon: 'fa-users', format: 'number' },
        { key: 'frequente', label: '<?= t("modules.relatorios.clientes.frequencia.classe_frequente") ?>', icon: 'fa-star', format: 'number', color: 'green' },
        { key: 'regular', label: '<?= t("modules.relatorios.clientes.frequencia.classe_regular") ?>', icon: 'fa-thumbs-up', format: 'number' },
        { key: 'esporadico', label: '<?= t("modules.relatorios.clientes.frequencia.classe_esporadico") ?>', icon: 'fa-clock', format: 'number', color: 'yellow' },
        { key: 'infrequente', label: '<?= t("modules.relatorios.clientes.frequencia.classe_infrequente") ?>', icon: 'fa-snowflake', format: 'number', color: 'red' },
        { key: 'unica', label: '<?= t("modules.relatorios.clientes.frequencia.classe_unica") ?>', icon: 'fa-user', format: 'number' },
    ];

    const CLASSE_LABEL = {
        frequente: '<?= t("modules.relatorios.clientes.frequencia.classe_frequente") ?>',
        regular: '<?= t("modules.relatorios.clientes.frequencia.classe_regular") ?>',
        esporadico: '<?= t("modules.relatorios.clientes.frequencia.classe_esporadico") ?>',
        infrequente: '<?= t("modules.relatorios.clientes.frequencia.classe_infrequente") ?>',
        unica: '<?= t("modules.relatorios.clientes.frequencia.classe_unica") ?>',
    };
    const CLASSE_COLOR = {
        frequente: 'bg-green-100 text-green-700',
        regular: 'bg-blue-100 text-blue-700',
        esporadico: 'bg-yellow-100 text-yellow-700',
        infrequente: 'bg-red-100 text-red-700',
        unica: 'bg-slate-100 text-slate-700',
    };

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.clientes.frequencia.title") ?>'));
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
            data: { labels: c.labels, datasets: [{ data: c.data || [], backgroundColor: ['rgba(34, 197, 94, .7)', 'rgba(59, 130, 246, .7)', 'rgba(250, 204, 21, .7)', 'rgba(239, 68, 68, .7)', 'rgba(100, 116, 139, .7)'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tb.innerHTML = lista.map(r => {
            const cls = r.classificacao;
            const badge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${CLASSE_COLOR[cls] || 'bg-slate-100 text-slate-700'}">${CLASSE_LABEL[cls] || cls}</span>`;
            const intervalo = r.intervalo_medio !== null && r.intervalo_medio !== undefined ? r.intervalo_medio + 'd' : '-';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${r.cliente || '-'}</td>
                <td class="table-cell text-center">${r.total_locacoes || 0}</td>
                <td class="table-cell text-center">${r.primeira ? DateHelper.format(r.primeira) : '-'}</td>
                <td class="table-cell text-center">${r.ultima ? DateHelper.format(r.ultima) : '-'}</td>
                <td class="table-cell text-center">${intervalo}</td>
                <td class="table-cell text-center">${badge}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
