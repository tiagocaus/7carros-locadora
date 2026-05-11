@extends('layouts.iframe')

@section('title', t('modules.relatorios.kpis.roi_veiculo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.kpis.roi_veiculo.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.kpis.roi_veiculo.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.kpis.roi_veiculo.col_placa') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.kpis.roi_veiculo.col_veiculo') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.kpis.roi_veiculo.col_valor_compra') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.kpis.roi_veiculo.col_receita') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.kpis.roi_veiculo.col_custos') ?></th>
                    <th class="table-header text-right hidden lg:table-cell"><?= t('modules.relatorios.kpis.roi_veiculo.col_lucro') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.kpis.roi_veiculo.col_roi') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    @include('pages.relatorios._partials.pagination')
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/kpis/roi-veiculo';
    let currentPage = 1, perPage = 20;

    const totalsConfig = [
        { key: 'receita_total', label: '<?= t("modules.relatorios.kpis.receita_veiculo.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', () => { currentPage = 1; carregarRelatorio(); });
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/kpis/roi-veiculo/pdf', '<?= t("modules.relatorios.kpis.roi_veiculo.title") ?>'));
        document.getElementById('btnLimpar')?.addEventListener('click', () => { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; const g = document.getElementById('filterGrupo'); if (g) g.value = ''; ReportUtils.hideContent(); });
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const params = { data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value, grupo: document.getElementById('filterGrupo')?.value || '', page: currentPage, perPage: perPage };
            const result = await API.get(API_URL, params);
            if (!result.success) { ReportUtils.showError(result.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            renderTable(result.data);
            ReportUtils.renderPagination(result.pagination, (page, pp) => { currentPage = page; if (pp) perPage = pp; carregarRelatorio(); });
            ReportUtils.showContent();
        } catch (e) { ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data?.length) { container.style.display = 'none'; return; }
        container.style.display = 'block';
        const cf = (v) => Currency.format(v, true);
        tbody.innerHTML = data.map(row => {
            const roiColor = row.roi >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${row.placa || '-'}</td>
                <td class="table-cell">${row.veiculo || '-'}</td>
                <td class="table-cell text-right hidden md:table-cell">${cf(row.valor_compra)}</td>
                <td class="table-cell text-right hidden sm:table-cell">${cf(row.receita_total)}</td>
                <td class="table-cell text-right hidden sm:table-cell">${cf(row.custos)}</td>
                <td class="table-cell text-right hidden lg:table-cell">${cf(row.lucro_liquido)}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${roiColor}">${row.roi}%</span></td>
            </tr>`;
        }).join('');
    }

    init();
})();
</script>
@endsection
