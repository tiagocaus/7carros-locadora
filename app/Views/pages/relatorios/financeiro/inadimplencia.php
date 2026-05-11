@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.inadimplencia.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.inadimplencia.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.inadimplencia.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.financeiro.inadimplencia.col_cliente') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.inadimplencia.col_valor_vencido') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.inadimplencia.col_faturas') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.inadimplencia.col_maior_atraso') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/inadimplencia';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_a_receber', label: '<?= t("modules.relatorios.financeiro.inadimplencia.total_a_receber") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'total_vencido', label: '<?= t("modules.relatorios.financeiro.inadimplencia.total_vencido") ?>', icon: 'fa-exclamation-triangle', format: 'currency', color: 'red' },
        { key: 'taxa_inadimplencia', label: '<?= t("modules.relatorios.financeiro.inadimplencia.taxa_inadimplencia") ?>', icon: 'fa-percentage', format: 'percent', colorByValue: true, invertColor: true },
        { key: 'total_clientes', label: '<?= t("modules.relatorios.financeiro.inadimplencia.total_clientes") ?>', icon: 'fa-users', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/inadimplencia/pdf', '<?= t("modules.relatorios.financeiro.inadimplencia.title") ?>'));
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

        const agingColors = [
            'rgba(250, 204, 21, 0.7)',
            'rgba(251, 146, 60, 0.7)',
            'rgba(249, 115, 22, 0.7)',
            'rgba(239, 68, 68, 0.7)',
            'rgba(185, 28, 28, 0.7)',
        ];
        const agingBorders = [
            'rgb(250, 204, 21)',
            'rgb(251, 146, 60)',
            'rgb(249, 115, 22)',
            'rgb(239, 68, 68)',
            'rgb(185, 28, 28)',
        ];

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: '<?= t("modules.relatorios.financeiro.inadimplencia.col_valor_vencido") ?>',
                    data: chartData.data || [],
                    backgroundColor: agingColors.slice(0, (chartData.data || []).length),
                    borderColor: agingBorders.slice(0, (chartData.data || []).length),
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
            const dias = Number(row.maior_atraso || 0);
            let atrasoColor = 'bg-yellow-100 text-yellow-800';
            if (dias > 60) atrasoColor = 'bg-red-100 text-red-800';
            else if (dias > 30) atrasoColor = 'bg-orange-100 text-orange-800';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.cliente || '-'}</td>
                <td class="table-cell text-right text-red-600 font-medium">${cf(row.valor_vencido)}</td>
                <td class="table-cell text-center">${row.faturas || 0}</td>
                <td class="table-cell text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${atrasoColor}">${dias} <?= t("modules.relatorios.financeiro.inadimplencia.dias") ?></span>
                </td>
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
