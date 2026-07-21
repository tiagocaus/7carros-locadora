@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.faturamento.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.faturamento.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.faturamento.description') ?></p>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataInicio" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_start') ?></label>
            <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataFim" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_end') ?></label>
            <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/matrizes-filiais/buscar"
                    data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFormaPagamento" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.filter_forma_pagamento') ?></label>
            <select id="filterFormaPagamento"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/formas-pagamento/select"
                    data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.faturamento.filter_status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value="S" selected><?= t('modules.relatorios.financeiro.faturamento.status_paid') ?></option>
                <option value="N"><?= t('modules.relatorios.financeiro.faturamento.status_unpaid') ?></option>
                <option value="all"><?= t('modules.relatorios.financeiro.faturamento.status_all') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
            </button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2" title="<?= t('modules.relatorios.common.clear') ?>">
                <i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?>
            </button>
        </div>
    </div>

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

    <!-- Tabela: Por Origem -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto mb-4" style="display: none;">
        <h3 class="text-sm font-semibold text-slate-700 px-4 pt-3 pb-1"><?= t('modules.relatorios.financeiro.faturamento.por_origem') ?></h3>
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.faturamento.col_nome') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.faturamento.col_qtd') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.faturamento.col_valor') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.faturamento.col_percentual') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    <!-- Tabela: Por Forma de Pagamento -->
    <div id="reportTableContainerPagamento" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <h3 class="text-sm font-semibold text-slate-700 px-4 pt-3 pb-1"><?= t('modules.relatorios.financeiro.faturamento.por_forma_pagamento') ?></h3>
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.faturamento.col_nome') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.faturamento.col_qtd') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.faturamento.col_valor') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.faturamento.col_percentual') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBodyPagamento" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/financeiro/faturamento';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'faturamento_bruto', label: '<?= t("modules.relatorios.financeiro.faturamento.faturamento_bruto") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'descontos', label: '<?= t("modules.relatorios.financeiro.faturamento.descontos") ?>', icon: 'fa-tag', format: 'currency', color: 'red' },
        { key: 'faturamento_liquido', label: '<?= t("modules.relatorios.financeiro.faturamento.faturamento_liquido") ?>', icon: 'fa-chart-line', format: 'currency', color: 'green' },
        { key: 'total_lancamentos', label: '<?= t("modules.relatorios.financeiro.faturamento.total_lancamentos") ?>', icon: 'fa-list', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/faturamento/pdf', '<?= t("modules.relatorios.financeiro.faturamento.title") ?>'));
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                forma_pagamento: document.getElementById('filterFormaPagamento').value,
                status: document.getElementById('filterStatus').value,
            };

            const result = await API.get(API_URL, params);

            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTableOrigem(result.data?.por_origem || []);
            renderTablePagamento(result.data?.por_forma_pagamento || []);
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
                datasets: chartData.datasets.map((ds, i) => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    fill: true,
                    tension: 0.3,
                })),
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

    function renderTableOrigem(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');

        if (!data || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const cf = (v) => Currency.format(v, true);

        tbody.innerHTML = data.map(row => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${row.nome || '-'}</td>
            <td class="table-cell text-right">${Number(row.qtd || 0).toLocaleString()}</td>
            <td class="table-cell text-right font-medium">${cf(row.valor)}</td>
            <td class="table-cell text-right">${row.percentual}%</td>
        </tr>`).join('');
    }

    function renderTablePagamento(data) {
        const container = document.getElementById('reportTableContainerPagamento');
        const tbody = document.getElementById('reportTableBodyPagamento');

        if (!data || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const cf = (v) => Currency.format(v, true);

        tbody.innerHTML = data.map(row => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${row.nome || '-'}</td>
            <td class="table-cell text-right">${Number(row.qtd || 0).toLocaleString()}</td>
            <td class="table-cell text-right font-medium">${cf(row.valor)}</td>
            <td class="table-cell text-right">${row.percentual}%</td>
        </tr>`).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterFormaPagamento').value = '';
        document.getElementById('filterStatus').value = 'S';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
