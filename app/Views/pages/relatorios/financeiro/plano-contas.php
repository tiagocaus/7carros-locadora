@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.plano_contas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.plano_contas.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.plano_contas.description') ?></p>

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
                    <th class="table-header"><?= t('modules.relatorios.financeiro.plano_contas.col_codigo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.plano_contas.col_descricao') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.plano_contas.col_tipo') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.plano_contas.col_valor') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.plano_contas.col_percentual') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/plano-contas';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_receitas', label: '<?= t("modules.relatorios.financeiro.plano_contas.total_receitas") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'total_despesas', label: '<?= t("modules.relatorios.financeiro.plano_contas.total_despesas") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'red' },
        { key: 'total_categorias', label: '<?= t("modules.relatorios.financeiro.plano_contas.total_categorias") ?>', icon: 'fa-th-list', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/plano-contas/pdf', '<?= t("modules.relatorios.financeiro.plano_contas.title") ?>'));
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
                    label: '<?= t("modules.relatorios.financeiro.plano_contas.col_valor") ?>',
                    data: chartData.data || [],
                    backgroundColor: ReportUtils.COLORS_ALPHA.slice(0, (chartData.data || []).length),
                    borderColor: ReportUtils.COLORS.slice(0, (chartData.data || []).length),
                    borderWidth: 1,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
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
            const tipoBadge = row.tipo === 'R'
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"><?= t("modules.relatorios.financeiro.movimentacoes.tipo_receita") ?></span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"><?= t("modules.relatorios.financeiro.movimentacoes.tipo_despesa") ?></span>';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.codigo || '-'}</td>
                <td class="table-cell">${row.descricao || '-'}</td>
                <td class="table-cell text-center">${tipoBadge}</td>
                <td class="table-cell text-right font-medium">${cf(row.valor)}</td>
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
