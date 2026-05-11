@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.movimentacoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.movimentacoes.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.movimentacoes.description') ?></p>

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
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterTipo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.filter_tipo') ?></label>
            <select id="filterTipo" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="R"><?= t('modules.relatorios.financeiro.movimentacoes.tipo_receita') ?></option>
                <option value="D"><?= t('modules.relatorios.financeiro.movimentacoes.tipo_despesa') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterConta" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.filter_conta') ?></label>
            <select id="filterConta"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/contas-bancarias/buscar"
                    data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.filter_status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="pago"><?= t('modules.relatorios.financeiro.movimentacoes.status_pago') ?></option>
                <option value="pendente"><?= t('modules.relatorios.financeiro.movimentacoes.status_pendente') ?></option>
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
                    <th class="table-header"><?= t('modules.relatorios.financeiro.movimentacoes.col_data') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.movimentacoes.col_tipo') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.financeiro.movimentacoes.col_categoria') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.movimentacoes.col_descricao') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.movimentacoes.col_valor') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.movimentacoes.col_status') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.financeiro.movimentacoes.col_conta') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.financeiro.movimentacoes.col_origem') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/movimentacoes';
    let chartInstance = null;
    let currentPage = 1, perPage = 20;

    const totalsConfig = [
        { key: 'total_receitas', label: '<?= t("modules.relatorios.financeiro.movimentacoes.total_receitas") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'total_despesas', label: '<?= t("modules.relatorios.financeiro.movimentacoes.total_despesas") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'red' },
        { key: 'saldo', label: '<?= t("modules.relatorios.financeiro.movimentacoes.saldo") ?>', icon: 'fa-balance-scale', format: 'currency' },
        { key: 'quantidade', label: '<?= t("modules.relatorios.financeiro.movimentacoes.quantidade") ?>', icon: 'fa-list', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', () => { currentPage = 1; carregarRelatorio(); });
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/movimentacoes/pdf', '<?= t("modules.relatorios.financeiro.movimentacoes.title") ?>'));
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                tipo: document.getElementById('filterTipo').value,
                conta: document.getElementById('filterConta').value,
                status: document.getElementById('filterStatus').value,
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
                datasets: [
                    {
                        label: '<?= t("modules.relatorios.financeiro.movimentacoes.tipo_receita") ?>',
                        data: chartData.receitas || [],
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                        borderColor: 'rgb(34, 197, 94)',
                        borderWidth: 1,
                    },
                    {
                        label: '<?= t("modules.relatorios.financeiro.movimentacoes.tipo_despesa") ?>',
                        data: chartData.despesas || [],
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: 1,
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
            const tipoBadge = row.tipo === 'R'
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"><?= t("modules.relatorios.financeiro.movimentacoes.tipo_receita") ?></span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"><?= t("modules.relatorios.financeiro.movimentacoes.tipo_despesa") ?></span>';

            const statusBadge = row.status === 'pago'
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"><?= t("modules.relatorios.financeiro.movimentacoes.status_pago") ?></span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800"><?= t("modules.relatorios.financeiro.movimentacoes.status_pendente") ?></span>';

            const valorClass = row.tipo === 'R' ? 'text-green-600' : 'text-red-600';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell">${DateHelper.format(row.data)}</td>
                <td class="table-cell">${tipoBadge}</td>
                <td class="table-cell hidden md:table-cell">${row.categoria || '-'}</td>
                <td class="table-cell">${row.descricao || '-'}</td>
                <td class="table-cell text-right font-medium ${valorClass}">${cf(row.valor)}</td>
                <td class="table-cell text-center">${statusBadge}</td>
                <td class="table-cell hidden lg:table-cell">${row.conta || '-'}</td>
                <td class="table-cell hidden lg:table-cell">${row.origem || '-'}</td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterTipo').value = '';
        document.getElementById('filterConta').value = '';
        document.getElementById('filterStatus').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
