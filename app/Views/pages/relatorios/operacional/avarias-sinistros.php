@extends('layouts.iframe')

@section('title', t('modules.relatorios.operacional.avarias_sinistros.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.operacional.avarias_sinistros.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.operacional.avarias_sinistros.description') ?></p>

    @include('pages.relatorios._partials.filters')
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
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.avarias_sinistros.col_data') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.avarias_sinistros.col_veiculo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.avarias_sinistros.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.avarias_sinistros.col_locacao') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.avarias_sinistros.col_tipo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.operacional.avarias_sinistros.col_descricao') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.operacional.avarias_sinistros.col_qtd_itens') ?></th>
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
    const API_URL = '/api/relatorios/operacional/avarias-sinistros';
    const PDF_URL = '/relatorios/operacional/avarias-sinistros/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_avarias', label: '<?= t("modules.relatorios.operacional.avarias_sinistros.total_avarias") ?>', icon: 'fa-car-crash', format: 'number' },
        { key: 'qtd_leve', label: '<?= t("modules.relatorios.operacional.avarias_sinistros.tipo_leve") ?>', icon: 'fa-info-circle', format: 'number', color: 'yellow' },
        { key: 'qtd_media', label: '<?= t("modules.relatorios.operacional.avarias_sinistros.tipo_media") ?>', icon: 'fa-exclamation', format: 'number' },
        { key: 'qtd_sinistro', label: '<?= t("modules.relatorios.operacional.avarias_sinistros.tipo_sinistro") ?>', icon: 'fa-fire', format: 'number', color: 'red' },
    ];

    const TIPO_LABEL = {
        leve: '<?= t("modules.relatorios.operacional.avarias_sinistros.tipo_leve") ?>',
        media: '<?= t("modules.relatorios.operacional.avarias_sinistros.tipo_media") ?>',
        sinistro: '<?= t("modules.relatorios.operacional.avarias_sinistros.tipo_sinistro") ?>',
    };
    const TIPO_COLOR = {
        leve: 'bg-yellow-100 text-yellow-700',
        media: 'bg-orange-100 text-orange-700',
        sinistro: 'bg-red-100 text-red-700',
    };

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.operacional.avarias_sinistros.title") ?>'));
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
            data: { labels: c.labels, datasets: [{ data: c.data || [], backgroundColor: ['rgba(250, 204, 21, .7)', 'rgba(249, 115, 22, .7)', 'rgba(239, 68, 68, .7)'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tb.innerHTML = lista.map(r => {
            const tipoBadge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${TIPO_COLOR[r.tipo] || 'bg-slate-100 text-slate-700'}">${TIPO_LABEL[r.tipo] || r.tipo}</span>`;
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center">${r.data ? DateHelper.format(r.data) : '-'}</td>
                <td class="table-cell"><strong>${r.placa || '-'}</strong> <span class="text-xs text-slate-500">${r.veiculo_modelo || ''}</span></td>
                <td class="table-cell">${r.cliente_nome || '-'}</td>
                <td class="table-cell text-slate-600 text-xs">${r.locacao_codigo || '-'}</td>
                <td class="table-cell text-center">${tipoBadge}</td>
                <td class="table-cell text-slate-600 text-xs">${r.descricao || '-'}</td>
                <td class="table-cell text-center">${r.qtd_itens || 0}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
