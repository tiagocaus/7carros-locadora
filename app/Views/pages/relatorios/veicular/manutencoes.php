@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.manutencoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.manutencoes.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.manutencoes.description') ?></p>

    <!-- Filtros padrão -->
    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false, 'filialChosenServerSide' => true])

    <!-- Filtros específicos de Manutenções -->
    <div class="flex flex-wrap gap-3 mb-4 -mt-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.manutencoes.filter_status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.veicular.manutencoes.status_all') ?></option>
                <option value="C"><?= t('modules.relatorios.veicular.manutencoes.status_pending') ?></option>
                <option value="A"><?= t('modules.relatorios.veicular.manutencoes.status_in_progress') ?></option>
                <option value="F"><?= t('modules.relatorios.veicular.manutencoes.status_completed') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterOficina" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.manutencoes.filter_oficina') ?></label>
            <select id="filterOficina"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/oficinas/buscar"
                    data-chosen-placeholder="<?= t('modules.relatorios.veicular.manutencoes.oficina_all') ?>">
                <option value=""><?= t('modules.relatorios.veicular.manutencoes.oficina_all') ?></option>
            </select>
        </div>
        <div class="flex-[2] min-w-[260px] max-w-[520px]">
            <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.manutencoes.filter_veiculo') ?></label>
            <select id="filterVeiculo"
                    class="form-input-focus w-full text-sm chosen-select"
                    multiple
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/veiculos/buscar"
                    data-chosen-placeholder="<?= t('modules.relatorios.veicular.manutencoes.veiculo_all') ?>">
            </select>
        </div>
    </div>

    <!-- Exportação -->
    @include('pages.relatorios._partials.export-buttons')

    <!-- Totalizadores -->
    @include('pages.relatorios._partials.totalizadores')

    <!-- Gráfico -->
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
                    <th class="table-header"><?= t('modules.relatorios.veicular.manutencoes.col_os') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.veicular.manutencoes.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.manutencoes.col_veiculo') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.veicular.manutencoes.col_oficina') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.relatorios.veicular.manutencoes.col_data_entrada') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.relatorios.veicular.manutencoes.col_data_saida') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.manutencoes.col_dias_parado') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.manutencoes.col_desconto') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.manutencoes.col_valor') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.manutencoes.col_status') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const i18n = {
        loading: '<?= t("common.labels.loading") ?>',
        noData: '<?= t("modules.relatorios.common.no_data") ?>',
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
    };

    const API_URL = '/api/relatorios/veicular/manutencoes';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_manutencoes', label: '<?= t("modules.relatorios.veicular.manutencoes.total_manutencoes") ?>', icon: 'fa-wrench', format: 'number' },
        { key: 'custo_total', label: '<?= t("modules.relatorios.veicular.manutencoes.custo_total") ?>', icon: 'fa-money-bill-wave', format: 'currency' },
        { key: 'desconto_total', label: '<?= t("modules.relatorios.veicular.manutencoes.desconto_total") ?>', icon: 'fa-tag', format: 'currency' },
        { key: 'custo_medio', label: '<?= t("modules.relatorios.veicular.manutencoes.custo_medio") ?>', icon: 'fa-calculator', format: 'currency' },
        { key: 'dias_parados_total', label: '<?= t("modules.relatorios.veicular.manutencoes.dias_parados_total") ?>', icon: 'fa-calendar-times', format: 'number', color: 'red' },
        { key: 'custo_por_km', label: '<?= t("modules.relatorios.veicular.manutencoes.custo_por_km") ?>', icon: 'fa-road', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () =>
            ReportUtils.exportPdf(buildPdfUrl(), '<?= t("modules.relatorios.veicular.manutencoes.title") ?>')
        );
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            status: document.getElementById('filterStatus').value,
            oficina: document.getElementById('filterOficina').value,
            veiculos: getSelectedValues('filterVeiculo').join(','),
        };
    }

    function getSelectedValues(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return [];
        return Array.from(select.selectedOptions || [])
            .map(option => option.value)
            .filter(Boolean);
    }

    function buildPdfUrl() {
        const p = buildParams();
        const qs = new URLSearchParams(p).toString();
        return '/relatorios/veicular/manutencoes/pdf?' + qs;
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const result = await API.get(API_URL, buildParams());

            if (!result.success) {
                ReportUtils.showError(result.message || i18n.loadError);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (error) {
            console.error('Erro ao carregar relatório:', error);
            ReportUtils.showError(i18n.connectionError);
        }
    }

    function renderTotals(totals) {
        const container = document.getElementById('reportTotals');
        container.innerHTML = ReportUtils.buildTotalCards(totals, totalsConfig);
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
                datasets: [
                    {
                        type: 'bar',
                        label: chartData.datasets[0]?.label || 'Custo (R$)',
                        data: chartData.datasets[0]?.data || [],
                        backgroundColor: ReportUtils.COLORS_ALPHA[0],
                        borderColor: ReportUtils.COLORS[0],
                        borderWidth: 1,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: chartData.datasets[1]?.label || 'Quantidade',
                        data: chartData.datasets[1]?.data || [],
                        borderColor: ReportUtils.COLORS[1],
                        backgroundColor: ReportUtils.COLORS_ALPHA[1],
                        tension: 0.3,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        ticks: { callback: v => Currency.format(v, true) },
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
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
        tbody.innerHTML = data.map(row => {
            const statusBadge = badgeStatus(row.status);
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.os || '-'}</td>
                <td class="table-cell">${row.placa || '-'}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell hidden lg:table-cell">${row.oficina || '-'}</td>
                <td class="table-cell hidden sm:table-cell">${row.data_entrada ? DateHelper.format(row.data_entrada) : '-'}</td>
                <td class="table-cell hidden sm:table-cell">${row.data_saida ? DateHelper.format(row.data_saida) : '-'}</td>
                <td class="table-cell text-center">${row.dias_parado}</td>
                <td class="table-cell text-right">${Currency.format(row.desconto || 0, true)}</td>
                <td class="table-cell text-right font-medium">${Currency.format(row.valor, true)}</td>
                <td class="table-cell text-center">${statusBadge}</td>
            </tr>`;
        }).join('');
    }

    function badgeStatus(status) {
        const map = {
            'C': { label: '<?= t("modules.relatorios.veicular.manutencoes.status_pending") ?>', cls: 'bg-yellow-100 text-yellow-800' },
            'A': { label: '<?= t("modules.relatorios.veicular.manutencoes.status_in_progress") ?>', cls: 'bg-blue-100 text-blue-800' },
            'F': { label: '<?= t("modules.relatorios.veicular.manutencoes.status_completed") ?>', cls: 'bg-green-100 text-green-800' },
        };
        const e = map[status] || { label: status, cls: 'bg-slate-100 text-slate-800' };
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${e.cls}">${e.label}</span>`;
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        clearChosen('filterFilial');
        document.getElementById('filterStatus').value = '';
        clearChosen('filterOficina');
        clearChosen('filterVeiculo');
        ReportUtils.hideContent();
        const totals = document.getElementById('reportTotals');
        if (totals) {
            totals.innerHTML = '';
            totals.style.display = 'none';
        }
    }

    function clearChosen(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        if (select.chosenSelect) {
            select.chosenSelect.clear();
        } else if (select.multiple) {
            Array.from(select.options).forEach(option => option.selected = false);
        } else {
            select.value = '';
        }
    }

    init();
})();
</script>
@endsection
