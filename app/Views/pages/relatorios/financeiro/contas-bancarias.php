@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.contas_bancarias.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.contas_bancarias.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.contas_bancarias.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.financeiro.contas_bancarias.col_conta') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.relatorios.financeiro.contas_bancarias.col_banco') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.contas_bancarias.col_entradas') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.contas_bancarias.col_saidas') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.contas_bancarias.col_saldo') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/contas-bancarias';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_entradas', label: '<?= t("modules.relatorios.financeiro.contas_bancarias.total_entradas") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'total_saidas', label: '<?= t("modules.relatorios.financeiro.contas_bancarias.total_saidas") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'red' },
        { key: 'saldo_geral', label: '<?= t("modules.relatorios.financeiro.contas_bancarias.saldo_geral") ?>', icon: 'fa-balance-scale', format: 'currency' },
        { key: 'total_contas', label: '<?= t("modules.relatorios.financeiro.contas_bancarias.total_contas") ?>', icon: 'fa-university', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/contas-bancarias/pdf', '<?= t("modules.relatorios.financeiro.contas_bancarias.title") ?>'));
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
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: '<?= t("modules.relatorios.financeiro.contas_bancarias.col_saldo") ?>',
                    data: chartData.data || [],
                    backgroundColor: (chartData.data || []).map(v => v >= 0 ? 'rgba(34, 197, 94, 0.7)' : 'rgba(239, 68, 68, 0.7)'),
                    borderColor: (chartData.data || []).map(v => v >= 0 ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)'),
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
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
            const saldo = Number(row.saldo || 0);
            const saldoColor = saldo >= 0 ? 'text-green-600' : 'text-red-600';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.conta || '-'}</td>
                <td class="table-cell hidden sm:table-cell">${row.banco || '-'}</td>
                <td class="table-cell text-right text-green-600">${cf(row.entradas)}</td>
                <td class="table-cell text-right text-red-600">${cf(row.saidas)}</td>
                <td class="table-cell text-right font-medium ${saldoColor}">${cf(saldo)}</td>
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
