@extends('layouts.iframe')

@section('title', t('modules.sinistros.report.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.sinistros.report.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.sinistros.report.description') ?></p>

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
                    <th class="table-header text-center"><?= t('modules.sinistros.fields.date') ?></th>
                    <th class="table-header"><?= t('modules.sinistros.fields.vehicle') ?></th>
                    <th class="table-header"><?= t('modules.sinistros.report.client') ?></th>
                    <th class="table-header"><?= t('modules.sinistros.report.link') ?></th>
                    <th class="table-header text-center"><?= t('modules.sinistros.fields.type') ?></th>
                    <th class="table-header"><?= t('modules.sinistros.fields.description') ?></th>
                    <th class="table-header text-right"><?= t('modules.sinistros.fields.estimated_value') ?></th>
                    <th class="table-header text-right"><?= t('modules.sinistros.fields.charge') ?></th>
                    <th class="table-header text-center"><?= t('modules.sinistros.fields.status') ?></th>
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
        { key: 'total_sinistros', label: '<?= t("modules.sinistros.report.total") ?>', icon: 'fa-car-crash', format: 'number' },
        { key: 'qtd_abertos', label: '<?= t("modules.sinistros.report.open") ?>', icon: 'fa-folder-open', format: 'number', color: 'yellow' },
        { key: 'qtd_concluidos', label: '<?= t("modules.sinistros.report.completed") ?>', icon: 'fa-check-circle', format: 'number' },
        { key: 'valor_cobrado', label: '<?= t("modules.sinistros.report.charged_value") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
    ];

    const TIPO_LABEL = {
        colisao: '<?= t("modules.sinistros.types.collision") ?>', furto_roubo: '<?= t("modules.sinistros.types.theft") ?>',
        incendio: '<?= t("modules.sinistros.types.fire") ?>', alagamento: '<?= t("modules.sinistros.types.flood") ?>',
        danos_terceiros: '<?= t("modules.sinistros.types.third_party") ?>', perda_total: '<?= t("modules.sinistros.types.total_loss") ?>',
        outros: '<?= t("modules.sinistros.types.other") ?>',
    };
    const TIPO_COLOR = {
        colisao: 'bg-orange-100 text-orange-700', furto_roubo: 'bg-red-100 text-red-700',
        incendio: 'bg-red-100 text-red-700', alagamento: 'bg-blue-100 text-blue-700',
        danos_terceiros: 'bg-amber-100 text-amber-700', perda_total: 'bg-red-100 text-red-700',
        outros: 'bg-slate-100 text-slate-700',
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf(PDF_URL, '<?= t("modules.sinistros.report.title") ?>'));
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
            const tipoBadge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${TIPO_COLOR[r.tipo] || 'bg-slate-100 text-slate-700'}">${escapeHtml(TIPO_LABEL[r.tipo] || r.tipo)}</span>`;
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center">${r.data ? DateHelper.format(r.data) : '-'}</td>
                <td class="table-cell"><strong>${escapeHtml(r.placa || '-')}</strong> <span class="text-xs text-slate-500">${escapeHtml(r.veiculo_modelo || '')}</span></td>
                <td class="table-cell">${escapeHtml(r.cliente_nome || '-')}</td>
                <td class="table-cell text-slate-600 text-xs">${escapeHtml(r.locacao_codigo || '-')}</td>
                <td class="table-cell text-center">${tipoBadge}</td>
                <td class="table-cell text-slate-600 text-xs">${escapeHtml(r.descricao || '-')}</td>
                <td class="table-cell text-right">${Currency.format(r.valor_estimado || 0, true)}</td>
                <td class="table-cell text-right">${Currency.format(r.valor_cobrado || 0, true)}</td>
                <td class="table-cell text-center"><span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${r.status === 'C' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}">${r.status === 'C' ? '<?= t("modules.sinistros.status.completed") ?>' : '<?= t("modules.sinistros.status.open") ?>'}</span></td>
            </tr>`;
        }).join('');
    }

    function limpar() { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
