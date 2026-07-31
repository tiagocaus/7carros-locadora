@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.evolucao_quilometragem.title'))

@section('content')
<?php ob_start(); ?>
<div class="flex-1 min-w-[180px] max-w-[250px]">
    <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.evolucao_quilometragem.vehicle') ?></label>
    <select id="filterVeiculo"
            class="form-input-focus w-full text-sm chosen-select"
            data-chosen-type="server-side"
            data-chosen-search-url="/api/veiculos/buscar"
            data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_vehicles') ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <option value=""><?= t('modules.relatorios.common.all_vehicles') ?></option>
    </select>
</div>
<div class="flex-1 min-w-[150px] max-w-[200px]">
    <label for="filterGranularidade" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.evolucao_quilometragem.granularity') ?></label>
    <select id="filterGranularidade" class="form-input-focus w-full text-sm">
        <option value="dia"><?= t('modules.relatorios.veicular.evolucao_quilometragem.gran_day') ?></option>
        <option value="semana"><?= t('modules.relatorios.veicular.evolucao_quilometragem.gran_week') ?></option>
        <option value="mes"><?= t('modules.relatorios.veicular.evolucao_quilometragem.gran_month') ?></option>
        <option value="ano"><?= t('modules.relatorios.veicular.evolucao_quilometragem.gran_year') ?></option>
    </select>
</div>
<?php $extraFilters = ob_get_clean(); ?>

<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.evolucao_quilometragem.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.evolucao_quilometragem.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true, 'extraFiltersAfterFilial' => $extraFilters])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_period') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_start') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_end') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_km') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_vehicles') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.evolucao_quilometragem.col_usages') ?></th>
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
    const i18n = {
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
    };
    const API_URL = '/api/relatorios/veicular/evolucao-quilometragem';
    const PDF_URL = '/relatorios/veicular/evolucao-quilometragem/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'km_total', label: '<?= t("modules.relatorios.veicular.evolucao_quilometragem.km_total") ?>', icon: 'fa-road', format: 'number' },
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.veicular.evolucao_quilometragem.vehicles_measured") ?>', icon: 'fa-car', format: 'number' },
        { key: 'qtd_utilizacoes', label: '<?= t("modules.relatorios.veicular.evolucao_quilometragem.usages") ?>', icon: 'fa-list', format: 'number' },
        { key: 'pico_km', label: '<?= t("modules.relatorios.veicular.evolucao_quilometragem.peak_km") ?>', icon: 'fa-chart-line', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf(PDF_URL + '?' + qs, '<?= t("modules.relatorios.veicular.evolucao_quilometragem.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            grupo: document.getElementById('filterGrupo')?.value || '',
            veiculo: document.getElementById('filterVeiculo')?.value || '',
            granularidade: document.getElementById('filterGranularidade').value,
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, buildParams());
            if (!result.success) { ReportUtils.showError(result.message || i18n.loadError); return; }
            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (error) {
            console.error(error);
            ReportUtils.showError(i18n.connectionError);
        }
    }

    function renderTotals(totals) {
        document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(totals, totalsConfig);
    }

    function renderChart(chart) {
        const container = document.getElementById('reportChartContainer');
        if (!chart?.labels?.length) { container.style.display = 'none'; return; }

        container.style.display = 'block';
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(document.getElementById('reportChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: chart.datasets[0].label,
                    data: chart.datasets[0].data,
                    borderColor: ReportUtils.COLORS[2],
                    backgroundColor: ReportUtils.COLORS_ALPHA[2],
                    borderWidth: 2,
                    pointRadius: chart.labels.length > 90 ? 0 : 3,
                    fill: true,
                    tension: 0.2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
            },
        });
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data?.length) { container.style.display = 'none'; return; }

        container.style.display = 'block';
        tbody.innerHTML = data.map(row => `
            <tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${escapeHtml(row.label)}</td>
                <td class="table-cell hidden md:table-cell">${DateHelper.format(row.data_inicio)}</td>
                <td class="table-cell hidden md:table-cell">${DateHelper.format(row.data_fim)}</td>
                <td class="table-cell text-right">${formatNumber(row.km_total)}</td>
                <td class="table-cell text-center">${formatNumber(row.qtd_veiculos)}</td>
                <td class="table-cell text-center">${formatNumber(row.qtd_utilizacoes)}</td>
            </tr>
        `).join('');
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString((window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-'));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
        })[char]);
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterGranularidade').value = 'dia';
        ['filterFilial', 'filterGrupo', 'filterVeiculo'].forEach(id => {
            const element = document.getElementById(id);
            if (!element) return;
            element.value = '';
            element.chosenSelect?.clear();
        });
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
