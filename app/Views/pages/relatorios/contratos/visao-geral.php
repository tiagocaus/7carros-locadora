@extends('layouts.iframe')

@section('title', t('modules.relatorios.contratos.visao_geral.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.contratos.visao_geral.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.contratos.visao_geral.description') ?></p>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_start') ?></label>
            <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_end') ?></label>
            <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.contratos.visao_geral.status_filter') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="A"><?= t('modules.relatorios.contratos.visao_geral.status_ativo') ?></option>
                <option value="F"><?= t('modules.relatorios.contratos.visao_geral.status_finalizado') ?></option>
                <option value="R"><?= t('modules.relatorios.contratos.visao_geral.status_reserva') ?></option>
                <option value="P"><?= t('modules.relatorios.contratos.visao_geral.status_pendente') ?></option>
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
        <canvas id="reportChart" height="240"></canvas>
    </div>

    <!-- Estado vazio -->
    @include('pages.relatorios._partials.empty-state')

    <!-- Tabela -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.contratos.visao_geral.col_codigo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.visao_geral.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.visao_geral.col_veiculo') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.visao_geral.col_data_saida') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.visao_geral.col_data_prevista') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.visao_geral.col_dias') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.contratos.visao_geral.col_valor_total') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.contratos.visao_geral.col_forma_pagamento') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.contratos.visao_geral.col_status') ?></th>
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
    const API_URL = '/api/relatorios/contratos/visao-geral';
    const PDF_URL = '/relatorios/contratos/visao-geral/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.contratos.visao_geral.total_locacoes") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'valor_total', label: '<?= t("modules.relatorios.contratos.visao_geral.valor_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'media_dias', label: '<?= t("modules.relatorios.contratos.visao_geral.media_dias") ?>', icon: 'fa-calendar-day', format: 'number' },
        { key: 'ticket_medio', label: '<?= t("modules.relatorios.contratos.visao_geral.ticket_medio") ?>', icon: 'fa-receipt', format: 'currency' },
    ];

    const STATUS_LABELS = {
        A: '<?= t("modules.relatorios.contratos.visao_geral.status_ativo") ?>',
        F: '<?= t("modules.relatorios.contratos.visao_geral.status_finalizado") ?>',
        R: '<?= t("modules.relatorios.contratos.visao_geral.status_reserva") ?>',
        P: '<?= t("modules.relatorios.contratos.visao_geral.status_pendente") ?>',
    };
    const STATUS_COLORS = {
        A: 'bg-green-100 text-green-700',
        F: 'bg-slate-100 text-slate-700',
        R: 'bg-blue-100 text-blue-700',
        P: 'bg-yellow-100 text-yellow-700',
    };

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', exportarPdf);
    }

    function getParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            status: document.getElementById('filterStatus').value,
        };
    }

    function exportarPdf() {
        const qs = new URLSearchParams(getParams()).toString();
        ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.contratos.visao_geral.title") ?>');
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, getParams());
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

        const colors = ['rgba(34, 197, 94, .7)', 'rgba(100, 116, 139, .7)', 'rgba(59, 130, 246, .7)', 'rgba(250, 204, 21, .7)'];

        chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.data || [],
                    backgroundColor: colors.slice(0, (chartData.labels || []).length),
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'right' }, title: { display: true, text: '<?= t("modules.relatorios.contratos.visao_geral.chart_title") ?>' } },
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
        const cf = (v) => Currency.format(v, true);

        tbody.innerHTML = lista.map(row => {
            const statusKey = row.status || '';
            const statusLabel = STATUS_LABELS[statusKey] || statusKey;
            const statusColor = STATUS_COLORS[statusKey] || 'bg-slate-100 text-slate-700';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.codigo || '-'}</td>
                <td class="table-cell">${row.cliente || '-'}</td>
                <td class="table-cell">
                    <span class="font-medium">${row.veiculo_placa || '-'}</span>
                    <span class="text-slate-500 text-xs ml-2">${row.veiculo_descricao || ''}</span>
                </td>
                <td class="table-cell text-center">${row.data_saida ? DateHelper.format(row.data_saida) : '-'}</td>
                <td class="table-cell text-center">${row.data_prevista ? DateHelper.format(row.data_prevista) : '-'}</td>
                <td class="table-cell text-center">${row.dias || 0}</td>
                <td class="table-cell text-right font-semibold">${cf(row.total_pagar)}</td>
                <td class="table-cell text-slate-600">${row.forma_pagamento || '-'}</td>
                <td class="table-cell text-center">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${statusColor}">${statusLabel}</span>
                </td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterStatus').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
