@extends('layouts.iframe')

@section('title', t('modules.relatorios.fornecedores.investidor.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.fornecedores.investidor.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.fornecedores.investidor.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])
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
                    <th class="table-header"><?= t('modules.relatorios.fornecedores.investidor.col_investidor') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_cnpj') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.fornecedores.investidor.col_veiculos') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_valor_investido') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_receita_gerada') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_devida') ?></th>
                    <th class="table-header text-right hidden lg:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_paga') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.fornecedores.investidor.col_saldo') ?></th>
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
    const API_URL = '/api/relatorios/fornecedores/investidor';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_investidores', label: '<?= t("modules.relatorios.fornecedores.investidor.qtd_investidores") ?>', icon: 'fa-handshake', format: 'number' },
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.fornecedores.investidor.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'valor_investido', label: '<?= t("modules.relatorios.fornecedores.investidor.valor_investido") ?>', icon: 'fa-coins', format: 'currency' },
        { key: 'receita_gerada', label: '<?= t("modules.relatorios.fornecedores.investidor.receita_gerada") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'comissao_devida', label: '<?= t("modules.relatorios.fornecedores.investidor.comissao_devida") ?>', icon: 'fa-clock', format: 'currency', color: 'yellow' },
        { key: 'comissao_paga', label: '<?= t("modules.relatorios.fornecedores.investidor.comissao_paga") ?>', icon: 'fa-check-circle', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/fornecedores/investidor/pdf?' + qs, '<?= t("modules.relatorios.fornecedores.investidor.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
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
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    borderWidth: 1,
                })),
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const saldoCls = row.saldo > 0 ? 'text-yellow-700' : 'text-green-700';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.investidor}</td>
                <td class="table-cell hidden lg:table-cell text-slate-500">${row.cpf_cnpj || '-'}</td>
                <td class="table-cell text-center">${row.qtd_veiculos}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Currency.format(row.valor_investido, true)}</td>
                <td class="table-cell text-right hidden md:table-cell text-green-700">${Currency.format(row.receita_gerada, true)}</td>
                <td class="table-cell text-right hidden md:table-cell text-yellow-700">${Currency.format(row.comissao_devida, true)}</td>
                <td class="table-cell text-right hidden lg:table-cell">${Currency.format(row.comissao_paga, true)}</td>
                <td class="table-cell text-right font-bold ${saldoCls}">${Currency.format(row.saldo, true)}</td>
            </tr>`;
        }).join('');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
