@extends('layouts.iframe')

@section('title', t('modules.relatorios.faturas.por_veiculo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.faturas.por_veiculo.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.faturas.por_veiculo.description') ?></p>

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
        <div class="flex-1 min-w-[170px] max-w-[220px]">
            <label for="filterModo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.faturas.por_veiculo.filter_modo') ?></label>
            <select id="filterModo" class="form-input-focus w-full text-sm">
                <option value="agrupado"><?= t('modules.relatorios.faturas.por_veiculo.modo_agrupado') ?></option>
                <option value="individualizado"><?= t('modules.relatorios.faturas.por_veiculo.modo_individualizado') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[320px]">
            <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.faturas.por_veiculo.filter_veiculo') ?></label>
            <select id="filterVeiculo" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/veiculos/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.faturas.por_veiculo.all_vehicles') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.faturas.por_veiculo.all_vehicles') ?></option>
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
            <thead id="reportTableHead" class="table-header-custom"></thead>
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
    const API_URL = '/api/relatorios/faturas/por-veiculo';
    const PDF_URL = '/relatorios/faturas/por-veiculo/pdf';
    let chartInstance = null;
    let modoAtual = 'agrupado';

    const labels = {
        vehicle: '<?= t("modules.relatorios.faturas.por_veiculo.col_veiculo") ?>',
        invoices: '<?= t("modules.relatorios.faturas.por_veiculo.col_total_faturas") ?>',
        total: '<?= t("modules.relatorios.faturas.por_veiculo.col_valor_total") ?>',
        paid: '<?= t("modules.relatorios.faturas.por_veiculo.col_pagas") ?>',
        pending: '<?= t("modules.relatorios.faturas.por_veiculo.col_pendentes") ?>',
        overdue: '<?= t("modules.relatorios.faturas.por_veiculo.col_vencidas") ?>',
        invoice: '<?= t("modules.relatorios.faturas.por_veiculo.col_fatura") ?>',
        client: '<?= t("modules.relatorios.faturas.por_veiculo.col_cliente") ?>',
        description: '<?= t("modules.relatorios.faturas.por_veiculo.col_descricao") ?>',
        dueDate: '<?= t("modules.relatorios.faturas.por_veiculo.col_vencimento") ?>',
        status: '<?= t("modules.relatorios.faturas.por_veiculo.col_status") ?>',
        statusPaid: '<?= t("modules.relatorios.faturas.por_veiculo.status_pago") ?>',
        statusPending: '<?= t("modules.relatorios.faturas.por_veiculo.status_pendente") ?>',
        statusOverdue: '<?= t("modules.relatorios.faturas.por_veiculo.status_vencida") ?>',
    };

    const totalsConfig = [
        { key: 'total_faturas', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_faturas") ?>', icon: 'fa-file-invoice-dollar', format: 'number' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.faturas.por_veiculo.valor_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'total_pago', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_pago") ?>', icon: 'fa-check-circle', format: 'currency', color: 'green' },
        { key: 'total_pendente', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_pendente") ?>', icon: 'fa-clock', format: 'currency', color: 'yellow' },
        { key: 'total_vencido', label: '<?= t("modules.relatorios.faturas.por_veiculo.total_vencido") ?>', icon: 'fa-exclamation-triangle', format: 'currency', color: 'red' },
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
        const params = new URLSearchParams({
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            modo: document.getElementById('filterModo').value,
            veiculo: document.getElementById('filterVeiculo').value,
        });
        ReportUtils.exportPdf(`${PDF_URL}?${params.toString()}`, '<?= t("modules.relatorios.faturas.por_veiculo.title") ?>');
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                modo: document.getElementById('filterModo').value,
                veiculo: document.getElementById('filterVeiculo').value,
            };
            modoAtual = params.modo === 'individualizado' ? 'individualizado' : 'agrupado';

            const result = await API.get(API_URL, params);
            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data && result.data.lista ? result.data.lista : []);
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

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: '<?= t("modules.relatorios.faturas.por_veiculo.valor_total") ?>',
                    data: chartData.data || [],
                    backgroundColor: 'rgba(59, 130, 246, .7)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, title: { display: true, text: '<?= t("modules.relatorios.faturas.por_veiculo.top10") ?>' } },
                scales: { x: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
            },
        });
    }

    function renderTable(lista) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'block';
        renderTableHeader();
        const cf = (v) => Currency.format(v, true);

        if (modoAtual === 'individualizado') {
            tbody.innerHTML = lista.map(row => {
                const placa = row.placa || '-';
                const veiculo = row.veiculo || '';
                const ano = row.ano ? ` <span class="text-slate-400 text-xs">(${row.ano})</span>` : '';
                const parcela = row.parcela_label && row.parcela_label !== '-' ? ` <span class="text-slate-400 text-xs">(${row.parcela_label})</span>` : '';
                return `<tr class="hover:bg-slate-50">
                    <td class="table-cell">
                        <span class="font-medium">${placa}</span>
                        <span class="text-slate-500 text-xs ml-2">${veiculo}${ano}</span>
                    </td>
                    <td class="table-cell">
                        <span class="font-medium">${row.codigo || '-'}</span>${parcela}
                    </td>
                    <td class="table-cell">${row.cliente || '-'}</td>
                    <td class="table-cell text-slate-600 text-sm">${row.descricao || '-'}</td>
                    <td class="table-cell text-center">${row.data_venci ? DateHelper.format(row.data_venci) : '-'}</td>
                    <td class="table-cell text-right font-semibold">${cf(row.valor_total)}</td>
                    <td class="table-cell text-center">${statusBadge(row.status)}</td>
                </tr>`;
            }).join('');
            return;
        }

        tbody.innerHTML = lista.map(row => {
            const placa = row.placa || '-';
            const veiculo = row.veiculo || '';
            const ano = row.ano ? ` <span class="text-slate-400 text-xs">(${row.ano})</span>` : '';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell">
                    <span class="font-medium">${placa}</span>
                    <span class="text-slate-500 text-xs ml-2">${veiculo}${ano}</span>
                </td>
                <td class="table-cell text-center">${row.total_faturas || 0}</td>
                <td class="table-cell text-right font-semibold">${cf(row.valor_total)}</td>
                <td class="table-cell text-right text-green-600">${cf(row.total_pago)}</td>
                <td class="table-cell text-right text-yellow-600">${cf(row.total_pendente)}</td>
                <td class="table-cell text-right ${Number(row.total_vencido) > 0 ? 'text-red-600 font-medium' : 'text-slate-400'}">${cf(row.total_vencido)}</td>
            </tr>`;
        }).join('');
    }

    function renderTableHeader() {
        const thead = document.getElementById('reportTableHead');
        if (modoAtual === 'individualizado') {
            thead.innerHTML = `<tr>
                <th class="table-header">${labels.vehicle}</th>
                <th class="table-header">${labels.invoice}</th>
                <th class="table-header">${labels.client}</th>
                <th class="table-header">${labels.description}</th>
                <th class="table-header text-center">${labels.dueDate}</th>
                <th class="table-header text-right">${labels.total}</th>
                <th class="table-header text-center">${labels.status}</th>
            </tr>`;
            return;
        }

        thead.innerHTML = `<tr>
            <th class="table-header">${labels.vehicle}</th>
            <th class="table-header text-center">${labels.invoices}</th>
            <th class="table-header text-right">${labels.total}</th>
            <th class="table-header text-right">${labels.paid}</th>
            <th class="table-header text-right">${labels.pending}</th>
            <th class="table-header text-right">${labels.overdue}</th>
        </tr>`;
    }

    function statusBadge(status) {
        if (status === 'pago') {
            return `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${labels.statusPaid}</span>`;
        }
        if (status === 'vencida') {
            return `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${labels.statusOverdue}</span>`;
        }
        return `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">${labels.statusPending}</span>`;
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterModo').value = 'agrupado';
        document.getElementById('filterVeiculo').value = '';
        document.getElementById('filterFilial')?.chosenSelect?.clear();
        document.getElementById('filterVeiculo')?.chosenSelect?.clear();
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
