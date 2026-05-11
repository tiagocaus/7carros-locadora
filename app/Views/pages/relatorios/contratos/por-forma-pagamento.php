@extends('layouts.iframe')

@section('title', t('modules.relatorios.contratos.por_forma_pagamento.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.contratos.por_forma_pagamento.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.contratos.por_forma_pagamento.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_forma') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_locacoes') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_pct_locacoes') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_valor_total') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_pct_valor') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.por_forma_pagamento.col_ticket_medio') ?></th>
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
    const API_URL = '/api/relatorios/contratos/por-forma-pagamento';
    const PDF_URL = '/relatorios/contratos/por-forma-pagamento/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_formas', label: '<?= t("modules.relatorios.contratos.por_forma_pagamento.qtd_formas") ?>', icon: 'fa-credit-card', format: 'number' },
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.contratos.por_forma_pagamento.total_locacoes") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.contratos.por_forma_pagamento.valor_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'ticket_medio', label: '<?= t("modules.relatorios.contratos.por_forma_pagamento.ticket_medio") ?>', icon: 'fa-receipt', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams({ data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value }).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.contratos.por_forma_pagamento.title") ?>');
        });
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
        } catch (e) {
            console.error(e);
            ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>');
        }
    }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        const palette = ['rgba(34, 197, 94, .7)', 'rgba(59, 130, 246, .7)', 'rgba(168, 85, 247, .7)', 'rgba(250, 204, 21, .7)', 'rgba(249, 115, 22, .7)', 'rgba(239, 68, 68, .7)', 'rgba(100, 116, 139, .7)'];
        chartInstance = new Chart(ctx, {
            type: 'pie',
            data: { labels: c.labels, datasets: [{ data: c.data || [], backgroundColor: palette.slice(0, (c.labels || []).length) }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        const pf = (v) => (Number(v) || 0).toFixed(1).replace('.', ',') + '%';
        tb.innerHTML = lista.map(r => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${r.forma_pagamento || '-'}</td>
            <td class="table-cell text-center">${r.qtd_locacoes || 0}</td>
            <td class="table-cell text-center text-slate-600">${pf(r.pct_locacoes)}</td>
            <td class="table-cell text-right font-semibold">${cf(r.valor_total)}</td>
            <td class="table-cell text-center text-slate-600">${pf(r.pct_valor)}</td>
            <td class="table-cell text-right">${cf(r.ticket_medio)}</td>
        </tr>`).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
