@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.rentabilidade.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.rentabilidade.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.rentabilidade.description') ?></p>

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
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterDimensao" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.rentabilidade.filter_dimensao') ?></label>
            <select id="filterDimensao" class="form-input-focus w-full text-sm">
                <option value="grupo"><?= t('modules.relatorios.financeiro.rentabilidade.dim_grupo') ?></option>
                <option value="veiculo"><?= t('modules.relatorios.financeiro.rentabilidade.dim_veiculo') ?></option>
                <option value="filial"><?= t('modules.relatorios.financeiro.rentabilidade.dim_filial') ?></option>
                <option value="cliente"><?= t('modules.relatorios.financeiro.rentabilidade.dim_cliente') ?></option>
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

    <!-- Tabela -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.rentabilidade.col_dimensao') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.rentabilidade.col_receita') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.rentabilidade.col_custos') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.rentabilidade.col_lucro') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.rentabilidade.col_margem') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.rentabilidade.col_participacao') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    <!-- Paginacao -->
    @include('pages.relatorios._partials.pagination')
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/financeiro/rentabilidade';
    let chartInstance = null;
    let currentPage = 1, perPage = 20;

    const totalsConfig = [
        { key: 'receita_total', label: '<?= t("modules.relatorios.financeiro.rentabilidade.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'custos_total', label: '<?= t("modules.relatorios.financeiro.rentabilidade.custos_total") ?>', icon: 'fa-minus-circle', format: 'currency', color: 'red' },
        { key: 'lucro_total', label: '<?= t("modules.relatorios.financeiro.rentabilidade.lucro_total") ?>', icon: 'fa-chart-line', format: 'currency', color: 'green' },
        { key: 'margem_media', label: '<?= t("modules.relatorios.financeiro.rentabilidade.margem_media") ?>', icon: 'fa-percentage', format: 'percent' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', () => { currentPage = 1; carregarRelatorio(); });
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/rentabilidade/pdf', '<?= t("modules.relatorios.financeiro.rentabilidade.title") ?>'));
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                dimensao: document.getElementById('filterDimensao').value,
                page: currentPage,
                perPage: perPage,
            };

            const result = await API.get(API_URL, params);

            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.renderPagination(result.pagination, (page, pp) => { currentPage = page; if (pp) perPage = pp; carregarRelatorio(); });
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
                    label: '<?= t("modules.relatorios.financeiro.rentabilidade.col_lucro") ?>',
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
            const lucro = Number(row.lucro || 0);
            const lucroColor = lucro >= 0 ? 'text-green-600' : 'text-red-600';
            const margem = Number(row.margem || 0);
            const margemColor = margem >= 20 ? 'bg-green-100 text-green-800' : (margem >= 10 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.dimensao || '-'}</td>
                <td class="table-cell text-right">${cf(row.receita)}</td>
                <td class="table-cell text-right text-red-600">${cf(row.custos)}</td>
                <td class="table-cell text-right font-medium ${lucroColor}">${cf(lucro)}</td>
                <td class="table-cell text-right">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${margemColor}">${margem}%</span>
                </td>
                <td class="table-cell text-right">${row.participacao}%</td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterDimensao').value = 'grupo';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
