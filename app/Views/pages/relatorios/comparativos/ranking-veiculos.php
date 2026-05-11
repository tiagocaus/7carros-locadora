@extends('layouts.iframe')

@section('title', t('modules.relatorios.comparativos.ranking_veiculos.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.comparativos.ranking_veiculos.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.comparativos.ranking_veiculos.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true])

    <!-- Filtro de critério -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterCriterio" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.comparativos.ranking_veiculos.filter_criterio') ?></label>
            <select id="filterCriterio" class="form-input-focus w-full text-sm">
                <option value="receita"><?= t('modules.relatorios.comparativos.ranking_veiculos.criterio_receita') ?></option>
                <option value="qtd_locacoes"><?= t('modules.relatorios.comparativos.ranking_veiculos.criterio_qtd_locacoes') ?></option>
                <option value="taxa_ocupacao"><?= t('modules.relatorios.comparativos.ranking_veiculos.criterio_taxa_ocupacao') ?></option>
            </select>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" style="display: none;">
        <h3 class="text-sm font-semibold text-green-700 mt-4 mb-2"><i class="fas fa-trophy mr-1"></i> <?= t('modules.relatorios.comparativos.ranking_veiculos.top10') ?></h3>
        <div class="bg-white shadow-md rounded-lg overflow-x-auto mb-4">
            <table class="w-full min-w-full divide-y divide-slate-200">
                <thead class="table-header-custom">
                    <tr>
                        <th class="table-header text-center"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_pos') ?></th>
                        <th class="table-header"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_placa') ?></th>
                        <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_veiculo') ?></th>
                        <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_grupo') ?></th>
                        <th class="table-header text-right"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_valor') ?></th>
                    </tr>
                </thead>
                <tbody id="topTableBody" class="bg-white divide-y divide-slate-200"></tbody>
            </table>
        </div>

        <h3 class="text-sm font-semibold text-red-700 mt-4 mb-2"><i class="fas fa-exclamation-circle mr-1"></i> <?= t('modules.relatorios.comparativos.ranking_veiculos.bottom10') ?></h3>
        <div class="bg-white shadow-md rounded-lg overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-slate-200">
                <thead class="table-header-custom">
                    <tr>
                        <th class="table-header text-center"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_pos') ?></th>
                        <th class="table-header"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_placa') ?></th>
                        <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_veiculo') ?></th>
                        <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_grupo') ?></th>
                        <th class="table-header text-right"><?= t('modules.relatorios.comparativos.ranking_veiculos.col_valor') ?></th>
                    </tr>
                </thead>
                <tbody id="bottomTableBody" class="bg-white divide-y divide-slate-200"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const i18n = { loadError: '<?= t("modules.relatorios.messages.load_error") ?>', connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>' };
    const API_URL = '/api/relatorios/comparativos/ranking-veiculos';
    let chartInstance = null;
    let currentCriterio = 'receita';

    const totalsConfig = [
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.comparativos.ranking_veiculos.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.comparativos.ranking_veiculos.valor_total") ?>', icon: 'fa-sigma', format: 'number' },
        { key: 'valor_maximo', label: '<?= t("modules.relatorios.comparativos.ranking_veiculos.valor_maximo") ?>', icon: 'fa-trophy', format: 'number', color: 'green' },
        { key: 'valor_medio', label: '<?= t("modules.relatorios.comparativos.ranking_veiculos.valor_medio") ?>', icon: 'fa-balance-scale', format: 'number' },
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
            ReportUtils.exportPdf('/relatorios/comparativos/ranking-veiculos/pdf?' + qs, '<?= t("modules.relatorios.comparativos.ranking_veiculos.title") ?>');
        });
    }

    function buildParams() {
        currentCriterio = document.getElementById('filterCriterio').value;
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            grupo: document.getElementById('filterGrupo')?.value || '',
            criterio: currentCriterio,
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, buildParams());
            if (!result.success) { ReportUtils.showError(result.message || i18n.loadError); return; }
            renderTotals(result.totals);
            renderChart(result.chart);
            renderTables(result.data, result.extra?.bottom10 || []);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError(i18n.connectionError); }
    }

    function fmtValor(v) {
        if (currentCriterio === 'receita') return Currency.format(v, true);
        if (currentCriterio === 'taxa_ocupacao') return Number(v).toFixed(2) + '%';
        return Number(v).toLocaleString('pt-BR');
    }

    function renderTotals(t) {
        const cfg = totalsConfig.map(c => ({...c, format: currentCriterio === 'receita' ? (c.key !== 'qtd_veiculos' ? 'currency' : 'number') : (currentCriterio === 'taxa_ocupacao' && c.key !== 'qtd_veiculos' ? 'percent' : 'number')}));
        document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, cfg);
    }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: c.labels,
                datasets: [{ label: c.datasets[0].label, data: c.datasets[0].data, backgroundColor: ReportUtils.COLORS_ALPHA[0], borderColor: ReportUtils.COLORS[0], borderWidth: 1 }],
            },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true } } },
        });
    }

    function renderTables(top, bottom) {
        const topBody = document.getElementById('topTableBody');
        const bottomBody = document.getElementById('bottomTableBody');
        const cont = document.getElementById('reportTableContainer');
        if ((!top || top.length === 0) && (!bottom || bottom.length === 0)) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';

        const renderRow = (row) => {
            const rankCls = row.ranking === 1 ? 'bg-yellow-100 text-yellow-800' :
                            row.ranking <= 3 ? 'bg-amber-100 text-amber-800' :
                            'bg-slate-100 text-slate-600';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center"><span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold ${rankCls}">${row.ranking}º</span></td>
                <td class="table-cell font-medium">${row.placa}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo}</td>
                <td class="table-cell hidden lg:table-cell">${row.grupo}</td>
                <td class="table-cell text-right font-semibold">${fmtValor(row.valor)}</td>
            </tr>`;
        };

        topBody.innerHTML = (top || []).map(renderRow).join('');
        bottomBody.innerHTML = (bottom || []).map(renderRow).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        const g = document.getElementById('filterGrupo'); if (g) g.value = '';
        document.getElementById('filterCriterio').value = 'receita';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
