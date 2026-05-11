@extends('layouts.iframe')

@section('title', t('modules.relatorios.funcionarios.comissoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.funcionarios.comissoes.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.funcionarios.comissoes.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])

    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.funcionarios.comissoes.filter_status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.funcionarios.comissoes.status_all') ?></option>
                <option value="pendente"><?= t('modules.relatorios.funcionarios.comissoes.status_pendente') ?></option>
                <option value="pago"><?= t('modules.relatorios.funcionarios.comissoes.status_pago') ?></option>
                <option value="cancelado"><?= t('modules.relatorios.funcionarios.comissoes.status_cancelado') ?></option>
            </select>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <!-- Aviso quando não há dados (tabela comissoes_funcionarios vazia) -->
    <div id="reportNoDataAlert" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4 text-amber-800 text-sm" style="display: none;">
        <i class="fas fa-info-circle mr-2"></i>
        <strong><?= t('modules.relatorios.funcionarios.comissoes.no_data_title') ?></strong>
        <?= t('modules.relatorios.funcionarios.comissoes.no_data_msg') ?>
    </div>

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.funcionarios.comissoes.col_funcionario') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.funcionarios.comissoes.col_qtd') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.funcionarios.comissoes.col_valor_base') ?></th>
                    <th class="table-header text-center hidden md:table-cell"><?= t('modules.relatorios.funcionarios.comissoes.col_pct_comissao') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.funcionarios.comissoes.col_valor_comissao') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.funcionarios.comissoes.col_bonus') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.funcionarios.comissoes.col_valor_total') ?></th>
                    <th class="table-header text-right hidden lg:table-cell"><?= t('modules.relatorios.funcionarios.comissoes.col_pendente') ?></th>
                    <th class="table-header text-right hidden lg:table-cell"><?= t('modules.relatorios.funcionarios.comissoes.col_pago') ?></th>
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
    const i18n = { loadError: '<?= t("modules.relatorios.messages.load_error") ?>', connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>' };
    const API_URL = '/api/relatorios/funcionarios/comissoes';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_funcionarios', label: '<?= t("modules.relatorios.funcionarios.comissoes.qtd_funcionarios") ?>', icon: 'fa-users', format: 'number' },
        { key: 'valor_base_total', label: '<?= t("modules.relatorios.funcionarios.comissoes.valor_base_total") ?>', icon: 'fa-arrow-up', format: 'currency' },
        { key: 'valor_comissao_total', label: '<?= t("modules.relatorios.funcionarios.comissoes.valor_comissao_total") ?>', icon: 'fa-percentage', format: 'currency' },
        { key: 'bonus_total', label: '<?= t("modules.relatorios.funcionarios.comissoes.bonus_total") ?>', icon: 'fa-gift', format: 'currency' },
        { key: 'pendente_total', label: '<?= t("modules.relatorios.funcionarios.comissoes.pendente_total") ?>', icon: 'fa-clock', format: 'currency', color: 'yellow' },
        { key: 'pago_total', label: '<?= t("modules.relatorios.funcionarios.comissoes.pago_total") ?>', icon: 'fa-check-circle', format: 'currency', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/funcionarios/comissoes/pdf?' + qs, '<?= t("modules.relatorios.funcionarios.comissoes.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            status: document.getElementById('filterStatus').value,
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, buildParams());
            if (!result.success) { ReportUtils.showError(result.message || i18n.loadError); return; }
            renderTotals(result.totals);

            const noDataAlert = document.getElementById('reportNoDataAlert');
            if (!result.totals.has_data) {
                noDataAlert.style.display = 'block';
                document.getElementById('reportChartContainer').style.display = 'none';
                document.getElementById('reportTableContainer').style.display = 'none';
                document.getElementById('reportEmptyState').style.display = 'none';
                return;
            } else {
                noDataAlert.style.display = 'none';
            }

            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError(i18n.connectionError); }
    }

    function renderTotals(t) { document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, totalsConfig); }

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
                datasets: c.datasets.map((ds, i) => ({
                    label: ds.label, data: ds.data,
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    borderWidth: 1,
                })),
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => `
            <tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.funcionario}</td>
                <td class="table-cell text-center">${row.qtd}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Currency.format(row.valor_base, true)}</td>
                <td class="table-cell text-center hidden md:table-cell">${row.pct_comissao}%</td>
                <td class="table-cell text-right">${Currency.format(row.valor_comissao, true)}</td>
                <td class="table-cell text-right hidden md:table-cell">${Currency.format(row.bonus, true)}</td>
                <td class="table-cell text-right font-semibold">${Currency.format(row.valor_total, true)}</td>
                <td class="table-cell text-right hidden lg:table-cell text-yellow-700">${Currency.format(row.pendente, true)}</td>
                <td class="table-cell text-right hidden lg:table-cell text-green-700">${Currency.format(row.pago, true)}</td>
            </tr>`).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('reportNoDataAlert').style.display = 'none';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
