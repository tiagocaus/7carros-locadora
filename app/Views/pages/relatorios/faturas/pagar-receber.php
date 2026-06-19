@extends('layouts.iframe')

@section('title', t('modules.relatorios.faturas.pagar_receber.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.faturas.pagar_receber.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.faturas.pagar_receber.description') ?></p>

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
        <div class="flex-1 min-w-[220px] max-w-[300px]">
            <label for="filterCliente" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.client') ?></label>
            <select id="filterCliente" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_clients') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_clients') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[300px]">
            <label for="filterFornecedor" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.supplier') ?></label>
            <select id="filterFornecedor" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/fornecedores/select" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_suppliers') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_suppliers') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[300px]">
            <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.vehicle') ?></label>
            <select id="filterVeiculo" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/veiculos/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_vehicles') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_vehicles') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[190px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all_status') ?></option>
                <option value="pago"><?= t('modules.relatorios.faturas.pagar_receber.status_pago') ?></option>
                <option value="pendente"><?= t('modules.relatorios.faturas.pagar_receber.status_pendente') ?></option>
                <option value="vencida"><?= t('modules.relatorios.faturas.pagar_receber.status_vencida') ?></option>
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

    <!-- Conteudo: 2 tabelas -->
    <div id="reportTableContainer" style="display: none;">
        <!-- Contas a Receber -->
        <div class="bg-white shadow-md rounded-lg overflow-x-auto mb-4">
            <div class="px-4 py-3 border-b bg-green-50">
                <h3 class="font-semibold text-green-700"><i class="fas fa-arrow-down mr-1"></i><?= t('modules.relatorios.faturas.pagar_receber.contas_receber') ?></h3>
            </div>
            <table class="w-full min-w-full divide-y divide-slate-200">
                <thead class="table-header-custom">
                    <tr>
                        <th class="table-header text-center"><?= t('modules.relatorios.faturas.pagar_receber.col_vencimento') ?></th>
                        <th class="table-header"><?= t('modules.relatorios.faturas.pagar_receber.col_cliente') ?></th>
                        <th class="table-header"><?= t('modules.relatorios.faturas.pagar_receber.col_descricao') ?></th>
                        <th class="table-header text-right"><?= t('modules.relatorios.faturas.pagar_receber.col_valor') ?></th>
                        <th class="table-header text-center"><?= t('modules.relatorios.faturas.pagar_receber.col_status') ?></th>
                    </tr>
                </thead>
                <tbody id="receberTableBody" class="bg-white divide-y divide-slate-200"></tbody>
            </table>
        </div>

        <!-- Contas a Pagar -->
        <div class="bg-white shadow-md rounded-lg overflow-x-auto mb-4">
            <div class="px-4 py-3 border-b bg-red-50">
                <h3 class="font-semibold text-red-700"><i class="fas fa-arrow-up mr-1"></i><?= t('modules.relatorios.faturas.pagar_receber.contas_pagar') ?></h3>
            </div>
            <table class="w-full min-w-full divide-y divide-slate-200">
                <thead class="table-header-custom">
                    <tr>
                        <th class="table-header text-center"><?= t('modules.relatorios.faturas.pagar_receber.col_vencimento') ?></th>
                        <th class="table-header"><?= t('modules.relatorios.faturas.pagar_receber.col_fornecedor') ?></th>
                        <th class="table-header"><?= t('modules.relatorios.faturas.pagar_receber.col_descricao') ?></th>
                        <th class="table-header text-right"><?= t('modules.relatorios.faturas.pagar_receber.col_valor') ?></th>
                        <th class="table-header text-center"><?= t('modules.relatorios.faturas.pagar_receber.col_status') ?></th>
                    </tr>
                </thead>
                <tbody id="pagarTableBody" class="bg-white divide-y divide-slate-200"></tbody>
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
    const API_URL = '/api/relatorios/faturas/pagar-receber';
    const PDF_URL = '/relatorios/faturas/pagar-receber/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_receber', label: '<?= t("modules.relatorios.faturas.pagar_receber.total_receber") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'green' },
        { key: 'total_pagar', label: '<?= t("modules.relatorios.faturas.pagar_receber.total_pagar") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'red' },
        { key: 'saldo', label: '<?= t("modules.relatorios.faturas.pagar_receber.saldo") ?>', icon: 'fa-balance-scale', format: 'currency', colorByValue: true },
        { key: 'qtd_receber', label: '<?= t("modules.relatorios.faturas.pagar_receber.qtd_receber") ?>', icon: 'fa-file-invoice-dollar', format: 'number' },
        { key: 'qtd_pagar', label: '<?= t("modules.relatorios.faturas.pagar_receber.qtd_pagar") ?>', icon: 'fa-receipt', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', exportarPdf);
    }

    function exportarPdf() {
        const url = `${PDF_URL}?${buildParams().toString()}`;
        const title = '<?= t("modules.relatorios.faturas.pagar_receber.title") ?>';
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'openPrintModal', url, title }, '*');
        } else {
            window.open(url, '_blank');
        }
    }

    function buildParams() {
        return new URLSearchParams({
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            cliente: document.getElementById('filterCliente').value,
            fornecedor: document.getElementById('filterFornecedor').value,
            veiculo: document.getElementById('filterVeiculo').value,
            status: document.getElementById('filterStatus').value,
        });
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const params = Object.fromEntries(buildParams().entries());

            const result = await API.get(API_URL, params);
            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTabela('receberTableBody', (result.data && result.data.receber) || [], 'receber');
            renderTabela('pagarTableBody', (result.data && result.data.pagar) || [], 'pagar');
            document.getElementById('reportTableContainer').style.display = '';
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
        if (chartInstance) chartInstance.destroy();

        const datasets = (chartData.datasets || []).map(ds => ({
            label: ds.label,
            data: ds.data || [],
            backgroundColor: ds.kind === 'entrada' ? 'rgba(34, 197, 94, .65)' : 'rgba(239, 68, 68, .65)',
            borderColor: ds.kind === 'entrada' ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)',
            borderWidth: 1,
        }));

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels: chartData.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
            },
        });
    }

    function renderTabela(tbodyId, lista, tipo) {
        const tbody = document.getElementById(tbodyId);
        const cf = (v) => Currency.format(v, true);

        if (!lista || lista.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-slate-400"><?= t('modules.relatorios.common.no_data') ?></td></tr>`;
            return;
        }

        tbody.innerHTML = lista.map(row => {
            let badge;
            if (row.status === 'pago') {
                badge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><?= t('modules.relatorios.faturas.pagar_receber.status_pago') ?></span>`;
            } else if (row.status === 'vencida') {
                badge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><?= t('modules.relatorios.faturas.pagar_receber.status_vencida') ?></span>`;
            } else {
                badge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700"><?= t('modules.relatorios.faturas.pagar_receber.status_pendente') ?></span>`;
            }

            const valorClasse = tipo === 'pagar' ? 'text-red-600' : 'text-green-600';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell text-center">${row.data_venci ? DateHelper.format(row.data_venci) : '-'}</td>
                <td class="table-cell">${row.pessoa || '-'}</td>
                <td class="table-cell text-slate-600 text-sm">${row.descricao || '-'}</td>
                <td class="table-cell text-right font-medium ${valorClasse}">${cf(row.valor_total)}</td>
                <td class="table-cell text-center">${badge}</td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        ['filterFilial', 'filterCliente', 'filterFornecedor', 'filterVeiculo'].forEach(clearChosen);
        document.getElementById('filterStatus').value = '';
        ReportUtils.hideContent();
        document.getElementById('reportTableContainer').style.display = 'none';
    }

    function clearChosen(id) {
        const select = document.getElementById(id);
        if (!select) return;
        if (select.chosenSelect && typeof select.chosenSelect.clear === 'function') {
            select.chosenSelect.clear();
            return;
        }
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    init();
})();
</script>
@endsection
