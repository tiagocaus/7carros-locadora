@extends('layouts.iframe')

@section('title', t('modules.relatorios.faturas.por_veiculo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.faturas.por_veiculo.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.faturas.por_veiculo.description') ?></p>

    <!-- Filtros -->
    @include('pages.relatorios._partials.filters')

    <!-- Exportacao -->
    @include('pages.relatorios._partials.export-buttons')

    <!-- Totalizadores -->
    @include('pages.relatorios._partials.totalizadores')

    <!-- Grafico -->
    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    <!-- Estado vazio -->
    @include('pages.relatorios._partials.empty-state')

    <!-- Tabela -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.faturas.por_veiculo.col_veiculo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.faturas.por_veiculo.col_total_faturas') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.por_veiculo.col_valor_total') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.por_veiculo.col_pagas') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.por_veiculo.col_pendentes') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.por_veiculo.col_vencidas') ?></th>
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
    const API_URL = '/api/relatorios/faturas/por-veiculo';
    const PDF_URL = '/relatorios/faturas/por-veiculo/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_faturas', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_faturas") ?>', icon: 'fa-file-invoice-dollar', format: 'number' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.faturas.por_veiculo.valor_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'total_pago', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_pago") ?>', icon: 'fa-check-circle', format: 'currency', color: 'green' },
        { key: 'total_pendente', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_pendente") ?>', icon: 'fa-clock', format: 'currency', color: 'yellow' },
        { key: 'total_vencido', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_vencido") ?>', icon: 'fa-exclamation-triangle', format: 'currency', color: 'red' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', exportarPdf);
    }

    function exportarPdf() {
        const params = new URLSearchParams({
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
        });
        ReportUtils.exportPdf(`${PDF_URL}?${params.toString()}`, '<?= t("modules.relatorios.faturas.por_veiculo.title") ?>');
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
            };

            const result = await API.get(API_URL, params);
            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data && result.data.lista ? result.data.lista : []);
            ReportUtils.showContent();
        } catch (error) {
            console.error('Erro ao carregar relatorio:', error);
            ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>');
        }
    }

    function renderTotals(totals) {
        document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(totals, totalsConfig);
    }

    function renderChart(chartData) {
        const container = document.getElementById('reportChartContainer');
        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: '<?= t("modules.relatorios.faturas.por_veiculo.valor_total") ?>',
                    data: chartData.data || [],
                    backgroundColor: 'rgba(59, 130, 246, .7)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, title: { display: true, text: '<?= t("modules.relatorios.faturas.por_veiculo.top10") ?>' } },
                scales: { x: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
            },
        });
    }

    function renderTable(lista) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'block';
        const cf = (v) => Currency.format(v, true);

        tbody.innerHTML = lista.map(row => {
            const placa = row.placa || '-';
            const veiculo = row.veiculo || '';
            const ano = row.ano ? ` <span class="text-slate-400 text-xs">(${row.ano})</span>` : '';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell">
                    <span class="font-medium">${placa}</span>
                    <span class="text-slate-500 text-xs ml-2">${veiculo}${ano}</span>
                </td>
                <td class="table-cell text-center">${row.total_faturas || 0}</td>
                <td class="table-cell text-right font-semibold">${cf(row.valor_total)}</td>
                <td class="table-cell text-right text-green-600">${cf(row.total_pago)}</td>
                <td class="table-cell text-right text-yellow-600">${cf(row.total_pendente)}</td>
                <td class="table-cell text-right ${Number(row.total_vencido) > 0 ? 'text-red-600 font-medium' : 'text-slate-400'}">${cf(row.total_vencido)}</td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
