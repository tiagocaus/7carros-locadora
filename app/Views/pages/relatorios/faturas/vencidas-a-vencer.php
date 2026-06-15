@extends('layouts.iframe')

@section('title', t('modules.relatorios.faturas.vencidas_a_vencer.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.faturas.vencidas_a_vencer.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.faturas.vencidas_a_vencer.description') ?></p>

    <!-- Filtros customizados (sem periodo — relatorio eh snapshot do estado atual) -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[200px] max-w-[280px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.faturas.vencidas_a_vencer.visao') ?></label>
            <div class="inline-flex rounded-md shadow-sm w-full" role="group">
                <button type="button" id="btnVisaoVencidas" class="flex-1 py-2 px-3 text-sm font-medium border border-slate-300 rounded-l-md bg-red-600 text-white">
                    <i class="fas fa-exclamation-triangle mr-1"></i><?= t('modules.relatorios.faturas.vencidas_a_vencer.vencidas') ?>
                </button>
                <button type="button" id="btnVisaoAVencer" class="flex-1 py-2 px-3 text-sm font-medium border border-slate-300 border-l-0 rounded-r-md bg-white text-slate-700 hover:bg-slate-50">
                    <i class="fas fa-clock mr-1"></i><?= t('modules.relatorios.faturas.vencidas_a_vencer.a_vencer') ?>
                </button>
            </div>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[320px]">
            <label for="filterCliente" class="block text-xs text-slate-500 mb-1">Cliente</label>
            <select id="filterCliente" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="Todos os clientes">
                <option value="">Todos os clientes</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
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
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_fatura') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_cliente') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_vencimento') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_valor_original') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_juros_multa') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_valor_total') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_dias') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.faturas.vencidas_a_vencer.col_status') ?></th>
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
    const API_URL = '/api/relatorios/faturas/vencidas-a-vencer';
    const PDF_URL = '/relatorios/faturas/vencidas-a-vencer/pdf';
    let chartInstance = null;
    let visaoAtual = 'vencidas';

    const totalsConfigVencidas = [
        { key: 'total_vencido', label: '<?= t("modules.relatorios.faturas.vencidas_a_vencer.total_vencido") ?>', icon: 'fa-exclamation-triangle', format: 'currency', color: 'red' },
        { key: 'qtd_vencidas', label: '<?= t("modules.relatorios.faturas.vencidas_a_vencer.qtd_vencidas") ?>', icon: 'fa-file-invoice-dollar', format: 'number' },
        { key: 'total_a_vencer', label: '<?= t("modules.relatorios.faturas.vencidas_a_vencer.total_a_vencer") ?>', icon: 'fa-clock', format: 'currency' },
        { key: 'qtd_a_vencer', label: '<?= t("modules.relatorios.faturas.vencidas_a_vencer.qtd_a_vencer") ?>', icon: 'fa-calendar-alt', format: 'number' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');

        document.getElementById('btnVisaoVencidas')?.addEventListener('click', () => setVisao('vencidas'));
        document.getElementById('btnVisaoAVencer')?.addEventListener('click', () => setVisao('a_vencer'));
        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnExportPdf')?.addEventListener('click', exportarPdf);
    }

    function setVisao(v) {
        visaoAtual = v;
        const btnV = document.getElementById('btnVisaoVencidas');
        const btnA = document.getElementById('btnVisaoAVencer');
        if (v === 'vencidas') {
            btnV.classList.remove('bg-white', 'text-slate-700', 'hover:bg-slate-50');
            btnV.classList.add('bg-red-600', 'text-white');
            btnA.classList.remove('bg-blue-600', 'text-white');
            btnA.classList.add('bg-white', 'text-slate-700', 'hover:bg-slate-50');
        } else {
            btnA.classList.remove('bg-white', 'text-slate-700', 'hover:bg-slate-50');
            btnA.classList.add('bg-blue-600', 'text-white');
            btnV.classList.remove('bg-red-600', 'text-white');
            btnV.classList.add('bg-white', 'text-slate-700', 'hover:bg-slate-50');
        }
        carregarRelatorio();
    }

    function exportarPdf() {
        const params = new URLSearchParams({
            filial: document.getElementById('filterFilial').value,
            cliente: document.getElementById('filterCliente').value,
            visao: visaoAtual,
        });
        ReportUtils.exportPdf(`${PDF_URL}?${params.toString()}`, '<?= t("modules.relatorios.faturas.vencidas_a_vencer.title") ?>');
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                filial: document.getElementById('filterFilial').value,
                cliente: document.getElementById('filterCliente').value,
                visao: visaoAtual,
            };

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
        document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(totals, totalsConfigVencidas);
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

        const colorsVencidas = ['rgba(250, 204, 21, .7)', 'rgba(251, 146, 60, .7)', 'rgba(249, 115, 22, .7)', 'rgba(239, 68, 68, .7)', 'rgba(185, 28, 28, .7)', 'rgba(127, 29, 29, .7)'];
        const colorsAVencer  = ['rgba(34, 197, 94, .7)',  'rgba(59, 130, 246, .7)', 'rgba(99, 102, 241, .7)', 'rgba(168, 85, 247, .7)', 'rgba(217, 70, 239, .7)'];
        const palette = visaoAtual === 'vencidas' ? colorsVencidas : colorsAVencer;

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: visaoAtual === 'vencidas'
                        ? '<?= t("modules.relatorios.faturas.vencidas_a_vencer.total_vencido") ?>'
                        : '<?= t("modules.relatorios.faturas.vencidas_a_vencer.total_a_vencer") ?>',
                    data: chartData.data || [],
                    backgroundColor: palette.slice(0, (chartData.data || []).length),
                    borderColor: palette.slice(0, (chartData.data || []).length).map(c => c.replace('.7', '1')),
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
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
            const dias = Number(row.dias || 0);

            // Status badge
            let statusBadge;
            if (visaoAtual === 'vencidas') {
                let cor = 'bg-yellow-100 text-yellow-800';
                if (dias > 60) cor = 'bg-red-100 text-red-800';
                else if (dias > 30) cor = 'bg-orange-100 text-orange-800';
                statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cor}"><?= t('modules.relatorios.faturas.vencidas_a_vencer.atrasada') ?> ${dias}d</span>`;
            } else {
                let cor = 'bg-green-100 text-green-800';
                if (dias === 0) cor = 'bg-blue-100 text-blue-800';
                else if (dias <= 7) cor = 'bg-yellow-100 text-yellow-800';
                const labelDias = dias === 0
                    ? '<?= t("modules.relatorios.faturas.vencidas_a_vencer.hoje") ?>'
                    : `${dias}d`;
                statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cor}">${labelDias}</span>`;
            }

            const codigo = row.codigo || '-';
            const parcela = row.parcela_label && row.parcela_label !== '-' ? ` <span class="text-slate-400 text-xs">(${row.parcela_label})</span>` : '';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${codigo}${parcela}</td>
                <td class="table-cell">${row.cliente || '-'}</td>
                <td class="table-cell text-center">${row.data_venci ? DateHelper.format(row.data_venci) : '-'}</td>
                <td class="table-cell text-right">${cf(row.valor_subtotal)}</td>
                <td class="table-cell text-right ${Number(row.juros_multa) > 0 ? 'text-red-600' : 'text-slate-400'}">${cf(row.juros_multa)}</td>
                <td class="table-cell text-right font-semibold">${cf(row.valor_total)}</td>
                <td class="table-cell text-center">${dias}</td>
                <td class="table-cell text-center">${statusBadge}</td>
            </tr>`;
        }).join('');
    }

    init();
})();
</script>
@endsection
