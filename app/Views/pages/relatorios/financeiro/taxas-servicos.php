@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.taxas_servicos.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.taxas_servicos.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.taxas_servicos.description') ?></p>

    <!-- Filtros -->
    @include('pages.relatorios._partials.filters')

    <!-- Exportacao -->
    @include('pages.relatorios._partials.export-buttons')

    <!-- Totalizadores -->
    @include('pages.relatorios._partials.totalizadores')

    <!-- Grafico -->
    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <div class="flex justify-center">
            <div style="max-width: 400px; width: 100%;">
                <canvas id="reportChart" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Estado vazio -->
    @include('pages.relatorios._partials.empty-state')

    <!-- Tabela -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.taxas_servicos.col_nome') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.taxas_servicos.col_quantidade') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.taxas_servicos.col_valor_total') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.taxas_servicos.col_ticket_medio') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.taxas_servicos.col_percentual') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/taxas-servicos';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'receita_total', label: '<?= t("modules.relatorios.financeiro.taxas_servicos.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
        { key: 'total_cobradas', label: '<?= t("modules.relatorios.financeiro.taxas_servicos.total_cobradas") ?>', icon: 'fa-list', format: 'number' },
        { key: 'ticket_medio', label: '<?= t("modules.relatorios.financeiro.taxas_servicos.ticket_medio") ?>', icon: 'fa-receipt', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/taxas-servicos/pdf', '<?= t("modules.relatorios.financeiro.taxas_servicos.title") ?>'));
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
            type: 'pie',
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.data || [],
                    backgroundColor: ReportUtils.COLORS_ALPHA.slice(0, (chartData.data || []).length),
                    borderColor: ReportUtils.COLORS.slice(0, (chartData.data || []).length),
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = Currency.format(context.raw, true);
                                const pct = context.dataset.data.length > 0
                                    ? ((context.raw / context.dataset.data.reduce((a, b) => a + b, 0)) * 100).toFixed(1)
                                    : 0;
                                return `${context.label}: ${value} (${pct}%)`;
                            },
                        },
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
                <td class="table-cell font-medium">${row.nome || '-'}</td>
                <td class="table-cell text-right">${Number(row.quantidade || 0).toLocaleString()}</td>
                <td class="table-cell text-right font-medium text-green-600">${cf(row.valor_total)}</td>
                <td class="table-cell text-right">${cf(row.ticket_medio)}</td>
                <td class="table-cell text-right">${row.percentual}%</td>
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
