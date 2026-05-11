@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.projecao_receitas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.projecao_receitas.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.projecao_receitas.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.financeiro.projecao_receitas.col_mes') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.projecao_receitas.col_confirmada') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.projecao_receitas.col_projetada') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.projecao_receitas.col_total') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/projecao-receitas';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'receita_confirmada', label: '<?= t("modules.relatorios.financeiro.projecao_receitas.receita_confirmada") ?>', icon: 'fa-check-circle', format: 'currency' },
        { key: 'receita_projetada', label: '<?= t("modules.relatorios.financeiro.projecao_receitas.receita_projetada") ?>', icon: 'fa-chart-line', format: 'currency' },
        { key: 'receita_total', label: '<?= t("modules.relatorios.financeiro.projecao_receitas.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
        { key: 'contratos_ativos', label: '<?= t("modules.relatorios.financeiro.projecao_receitas.contratos_ativos") ?>', icon: 'fa-file-contract', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/projecao-receitas/pdf', '<?= t("modules.relatorios.financeiro.projecao_receitas.title") ?>'));
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
            renderTable(result.data);
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

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: '<?= t("modules.relatorios.financeiro.projecao_receitas.col_confirmada") ?>',
                        data: chartData.confirmada || [],
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: '<?= t("modules.relatorios.financeiro.projecao_receitas.col_projetada") ?>',
                        data: chartData.projetada || [],
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.3,
                        borderDash: [5, 5],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => Currency.format(v, true) },
                    },
                },
            },
        });
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');

        if (!data || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const cf = (v) => Currency.format(v, true);

        tbody.innerHTML = data.map(row => {
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.mes || '-'}</td>
                <td class="table-cell text-right">${cf(row.confirmada)}</td>
                <td class="table-cell text-right text-blue-600">${cf(row.projetada)}</td>
                <td class="table-cell text-right font-medium text-green-600">${cf(row.total)}</td>
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
