@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.cnh_vencidas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.cnh_vencidas.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.cnh_vencidas.description') ?></p>

    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.clientes.cnh_vencidas.status_filter') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="vencida"><?= t('modules.relatorios.clientes.cnh_vencidas.faixa_vencidas') ?></option>
                <option value="30">1-30 <?= t('modules.relatorios.clientes.cnh_vencidas.dias') ?></option>
                <option value="60">31-60 <?= t('modules.relatorios.clientes.cnh_vencidas.dias') ?></option>
                <option value="90">61-90 <?= t('modules.relatorios.clientes.cnh_vencidas.dias') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow"><i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?></button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2"><i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?></button>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="200"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.clientes.cnh_vencidas.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.cnh_vencidas.col_cpf') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.cnh_vencidas.col_cnh') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_validade') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_dias') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.cnh_vencidas.col_telefone') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_loc_ativa') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.cnh_vencidas.col_status') ?></th>
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
    const API_URL = '/api/relatorios/clientes/cnh-vencidas';
    const PDF_URL = '/relatorios/clientes/cnh-vencidas/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'total', label: '<?= t("modules.relatorios.clientes.cnh_vencidas.total") ?>', icon: 'fa-id-card', format: 'number' },
        { key: 'vencidas', label: '<?= t("modules.relatorios.clientes.cnh_vencidas.faixa_vencidas") ?>', icon: 'fa-exclamation-triangle', format: 'number', color: 'red' },
        { key: 'vence_30', label: '<?= t("modules.relatorios.clientes.cnh_vencidas.faixa_30") ?>', icon: 'fa-clock', format: 'number', color: 'yellow' },
        { key: 'vence_60', label: '<?= t("modules.relatorios.clientes.cnh_vencidas.faixa_60") ?>', icon: 'fa-clock', format: 'number' },
        { key: 'vence_90', label: '<?= t("modules.relatorios.clientes.cnh_vencidas.faixa_90") ?>', icon: 'fa-clock', format: 'number' },
    ];

    const STATUS_BADGE = {
        vencida: 'bg-red-100 text-red-700',
        '30': 'bg-yellow-100 text-yellow-700',
        '60': 'bg-orange-100 text-orange-700',
        '90': 'bg-slate-100 text-slate-700',
    };
    const STATUS_LABEL = {
        vencida: '<?= t("modules.relatorios.clientes.cnh_vencidas.status_vencida") ?>',
        '30': '<?= t("modules.relatorios.clientes.cnh_vencidas.status_30") ?>',
        '60': '<?= t("modules.relatorios.clientes.cnh_vencidas.status_60") ?>',
        '90': '<?= t("modules.relatorios.clientes.cnh_vencidas.status_90") ?>',
    };

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams({ status: document.getElementById('filterStatus').value, filial: document.getElementById('filterFilial').value }).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.clientes.cnh_vencidas.title") ?>');
        });
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, { status: document.getElementById('filterStatus').value, filial: document.getElementById('filterFilial').value });
            if (!r.success) { ReportUtils.showError(r.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(r.totals, totalsConfig);
            renderChart(r.chart);
            renderTable(r.data && r.data.lista ? r.data.lista : []);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels: c.labels, datasets: [{ data: c.data || [], backgroundColor: ['rgba(239, 68, 68, .7)', 'rgba(250, 204, 21, .7)', 'rgba(249, 115, 22, .7)', 'rgba(100, 116, 139, .7)'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';

        tb.innerHTML = lista.map(r => {
            const dias = Number(r.dias_para_vencer || 0);
            const labelDias = dias < 0 ? Math.abs(dias) + 'd ' + '<?= t("modules.relatorios.clientes.cnh_vencidas.atras") ?>' : dias + 'd';
            const statusBadge = `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${STATUS_BADGE[r.status] || 'bg-slate-100 text-slate-700'}">${STATUS_LABEL[r.status] || r.status}</span>`;
            const ativaBadge = r.tem_locacao_ativa ? '<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><?= t("modules.relatorios.clientes.cnh_vencidas.sim") ?></span>' : '<span class="text-slate-400 text-xs">-</span>';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${r.cliente || '-'}</td>
                <td class="table-cell text-slate-600 text-xs">${r.cpf_cnpj || ''}</td>
                <td class="table-cell text-slate-600">${r.cnh_numero || '-'}</td>
                <td class="table-cell text-center">${r.cnh_validade ? DateHelper.format(r.cnh_validade) : '-'}</td>
                <td class="table-cell text-center ${dias < 0 ? 'text-red-600 font-semibold' : ''}">${labelDias}</td>
                <td class="table-cell text-slate-600 text-xs">${r.telefone || '-'}</td>
                <td class="table-cell text-center">${ativaBadge}</td>
                <td class="table-cell text-center">${statusBadge}</td>
            </tr>`;
        }).join('');
    }

    function limpar() { document.getElementById('filterStatus').value = ''; document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
