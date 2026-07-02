@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.despesas.title'))

@section('content')
<?php ob_start(); ?>
<div class="flex-1 min-w-[180px] max-w-[250px]">
    <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.despesas.col_placa') ?></label>
    <select id="filterVeiculo"
            class="form-input-focus w-full text-sm chosen-select"
            data-chosen-type="server-side"
            data-chosen-search-url="/api/veiculos/buscar"
            data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_vehicles') ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <option value=""><?= t('modules.relatorios.common.all_vehicles') ?></option>
    </select>
</div>
<?php $veiculoFilter = ob_get_clean(); ?>
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.despesas.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.despesas.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true, 'extraFiltersAfterFilial' => $veiculoFilter])
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
                    <th class="table-header"><?= t('modules.relatorios.veicular.despesas.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.despesas.col_veiculo') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.veicular.despesas.col_grupo') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.despesas.col_manutencao') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.veicular.despesas.col_multas') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.veicular.despesas.col_encargos') ?></th>
                    <th class="table-header text-right hidden lg:table-cell"><?= t('modules.relatorios.veicular.despesas.col_outros') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.despesas.col_total') ?></th>
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
    const API_URL = '/api/relatorios/veicular/despesas';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.veicular.despesas.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'manutencao_total', label: '<?= t("modules.relatorios.veicular.despesas.manutencao_total") ?>', icon: 'fa-wrench', format: 'currency' },
        { key: 'multas_total', label: '<?= t("modules.relatorios.veicular.despesas.multas_total") ?>', icon: 'fa-exclamation-triangle', format: 'currency', color: 'red' },
        { key: 'encargos_total', label: '<?= t("modules.relatorios.veicular.despesas.encargos_total") ?>', icon: 'fa-file-invoice', format: 'currency' },
        { key: 'outros_total', label: '<?= t("modules.relatorios.veicular.despesas.outros_total") ?>', icon: 'fa-ellipsis-h', format: 'currency' },
        { key: 'total_geral', label: '<?= t("modules.relatorios.veicular.despesas.total_geral") ?>', icon: 'fa-money-bill-wave', format: 'currency', color: 'red' },
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
            ReportUtils.exportPdf('/relatorios/veicular/despesas/pdf?' + qs, '<?= t("modules.relatorios.veicular.despesas.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            veiculo: document.getElementById('filterVeiculo')?.value || '',
            grupo: document.getElementById('filterGrupo')?.value || '',
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
        } catch (e) {
            console.error('Erro ao carregar relatório:', e);
            ReportUtils.showError(i18n.connectionError);
        }
    }

    function renderTotals(t) { document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, totalsConfig); }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: c.labels,
                datasets: [{
                    data: c.datasets[0].data,
                    backgroundColor: ReportUtils.COLORS.slice(0, c.labels.length),
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => `
            <tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa || '-'}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell hidden lg:table-cell">${row.grupo || '-'}</td>
                <td class="table-cell text-right">${Currency.format(row.manutencao, true)}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Currency.format(row.multas, true)}</td>
                <td class="table-cell text-right hidden md:table-cell">${Currency.format(row.encargos, true)}</td>
                <td class="table-cell text-right hidden lg:table-cell">${Currency.format(row.outros, true)}</td>
                <td class="table-cell text-right font-semibold text-red-700">${Currency.format(row.total, true)}</td>
            </tr>`).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        clearChosen('filterFilial');
        clearChosen('filterVeiculo');
        clearChosen('filterGrupo');
        ReportUtils.hideContent();
    }

    function clearChosen(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.chosenSelect && typeof el.chosenSelect.clear === 'function') {
            el.chosenSelect.clear();
            return;
        }
        el.value = '';
    }

    init();
})();
</script>
@endsection
