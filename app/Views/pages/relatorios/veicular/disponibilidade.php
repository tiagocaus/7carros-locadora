@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.disponibilidade.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.disponibilidade.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.disponibilidade.description') ?></p>

    <!-- Filtros (sem período — snapshot atual) -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
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
            <label for="filterGrupo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.vehicle_group') ?></label>
            <select id="filterGrupo"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/grupos"
                    data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_groups') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_groups') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
            </button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
                <i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?>
            </button>
        </div>
    </div>

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
                    <th class="table-header"><?= t('modules.relatorios.veicular.disponibilidade.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.disponibilidade.col_veiculo') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.veicular.disponibilidade.col_grupo') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.veicular.disponibilidade.col_odometro') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.disponibilidade.col_status') ?></th>
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
    const API_URL = '/api/relatorios/veicular/disponibilidade';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_frota', label: '<?= t("modules.relatorios.veicular.disponibilidade.total_frota") ?>', icon: 'fa-car', format: 'number' },
        { key: 'disponiveis', label: '<?= t("modules.relatorios.veicular.disponibilidade.disponiveis") ?>', icon: 'fa-check', format: 'number', color: 'green' },
        { key: 'locados', label: '<?= t("modules.relatorios.veicular.disponibilidade.locados") ?>', icon: 'fa-handshake', format: 'number' },
        { key: 'reservados', label: '<?= t("modules.relatorios.veicular.disponibilidade.reservados") ?>', icon: 'fa-calendar-check', format: 'number', color: 'yellow' },
        { key: 'oficina', label: '<?= t("modules.relatorios.veicular.disponibilidade.oficina") ?>', icon: 'fa-wrench', format: 'number', color: 'red' },
        { key: 'taxa_ocupacao_atual', label: '<?= t("modules.relatorios.veicular.disponibilidade.taxa_ocupacao_atual") ?>', icon: 'fa-chart-pie', format: 'percent', colorByValue: true },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');

        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/veicular/disponibilidade/pdf?' + qs, '<?= t("modules.relatorios.veicular.disponibilidade.title") ?>');
        });

        carregar();
    }

    function buildParams() {
        return {
            filial: document.getElementById('filterFilial').value,
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
            console.error(e);
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
                datasets: [{ data: c.datasets[0].data, backgroundColor: ReportUtils.COLORS.slice(0, c.labels.length) }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const cls = row.status === 'D' ? 'bg-green-100 text-green-800' :
                        row.status === 'L' ? 'bg-blue-100 text-blue-800' :
                        row.status === 'R' ? 'bg-yellow-100 text-yellow-800' :
                        (row.status === 'O' || row.status === 'E') ? 'bg-red-100 text-red-800' :
                        'bg-slate-100 text-slate-800';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa || '-'}</td>
                <td class="table-cell hidden md:table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell hidden lg:table-cell">${row.grupo || '-'}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Number(row.odometro).toLocaleString('pt-BR')}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}">${row.status_label}</span></td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        document.getElementById('filterFilial').value = '';
        const g = document.getElementById('filterGrupo'); if (g) g.value = '';
        carregar();
    }

    init();
})();
</script>
@endsection
