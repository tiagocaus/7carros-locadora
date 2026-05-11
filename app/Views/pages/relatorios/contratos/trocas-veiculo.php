@extends('layouts.iframe')

@section('title', t('modules.relatorios.contratos.trocas_veiculo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.contratos.trocas_veiculo.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.contratos.trocas_veiculo.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.contratos.trocas_veiculo.col_contrato') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.trocas_veiculo.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.trocas_veiculo.col_veiculo_old') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.trocas_veiculo.col_veiculo_new') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.trocas_veiculo.col_data_troca') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.trocas_veiculo.col_motivo') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.trocas_veiculo.col_diferenca') ?></th>
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
    const API_URL = '/api/relatorios/contratos/trocas-veiculo';
    const PDF_URL = '/relatorios/contratos/trocas-veiculo/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_trocas', label: '<?= t("modules.relatorios.contratos.trocas_veiculo.qtd_trocas") ?>', icon: 'fa-exchange-alt', format: 'number' },
        { key: 'qtd_locacoes_afetadas', label: '<?= t("modules.relatorios.contratos.trocas_veiculo.qtd_locacoes_afetadas") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'media_diferenca', label: '<?= t("modules.relatorios.contratos.trocas_veiculo.media_diferenca") ?>', icon: 'fa-balance-scale', format: 'currency', colorByValue: true },
        { key: 'soma_diferenca', label: '<?= t("modules.relatorios.contratos.trocas_veiculo.soma_diferenca") ?>', icon: 'fa-dollar-sign', format: 'currency', colorByValue: true },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams({ data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value }).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.contratos.trocas_veiculo.title") ?>');
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
        const palette = ['rgba(168, 85, 247, .7)', 'rgba(59, 130, 246, .7)', 'rgba(34, 197, 94, .7)', 'rgba(250, 204, 21, .7)', 'rgba(249, 115, 22, .7)', 'rgba(239, 68, 68, .7)', 'rgba(100, 116, 139, .7)'];
        chartInstance = new Chart(ctx, {
            type: 'bar', data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.contratos.trocas_veiculo.qtd_trocas") ?>', data: c.data || [], backgroundColor: palette.slice(0, (c.labels || []).length) }] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false }, title: { display: true, text: '<?= t("modules.relatorios.contratos.trocas_veiculo.chart_title") ?>' } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tb.innerHTML = lista.map(r => {
            const dif = Number(r.diferenca) || 0;
            const corDif = dif > 0 ? 'text-green-600' : (dif < 0 ? 'text-red-600' : 'text-slate-500');
            const sinal = dif > 0 ? '+' : '';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${r.codigo || '-'}</td>
                <td class="table-cell">${r.cliente || '-'}</td>
                <td class="table-cell">
                    <span class="font-medium">${r.veiculo_old_placa || '-'}</span>
                    <span class="text-slate-500 text-xs ml-2">${r.veiculo_old_descricao || ''}</span>
                </td>
                <td class="table-cell">
                    <span class="font-medium">${r.veiculo_new_placa || '-'}</span>
                    <span class="text-slate-500 text-xs ml-2">${r.veiculo_new_descricao || ''}</span>
                </td>
                <td class="table-cell text-center">${r.data_troca ? DateHelper.format(r.data_troca) : '-'}</td>
                <td class="table-cell text-slate-600">${r.motivo || '-'}</td>
                <td class="table-cell text-right ${corDif} font-semibold">${sinal}${cf(dif)}</td>
            </tr>`;
        }).join('');
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
