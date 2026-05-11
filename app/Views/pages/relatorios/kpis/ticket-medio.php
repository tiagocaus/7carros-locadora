@extends('layouts.iframe')

@section('title', t('modules.relatorios.kpis.ticket_medio.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.kpis.ticket_medio.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.kpis.ticket_medio.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/kpis/ticket-medio';

    const totalsConfig = [
        { key: 'receita_total', label: '<?= t("modules.relatorios.kpis.ticket_medio.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'total_locacoes', label: '<?= t("modules.relatorios.kpis.ticket_medio.total_locacoes") ?>', icon: 'fa-car', format: 'number' },
        { key: 'total_contratos', label: '<?= t("modules.relatorios.kpis.ticket_medio.total_contratos") ?>', icon: 'fa-file-contract', format: 'number' },
        { key: 'total_operacoes', label: '<?= t("modules.relatorios.kpis.ticket_medio.total_operacoes") ?>', icon: 'fa-list', format: 'number' },
        { key: 'ticket_medio', label: '<?= t("modules.relatorios.kpis.ticket_medio.ticket_medio") ?>', icon: 'fa-receipt', format: 'currency', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/kpis/ticket-medio/pdf', '<?= t("modules.relatorios.kpis.ticket_medio.title") ?>'));
        document.getElementById('btnLimpar')?.addEventListener('click', () => { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); });
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const params = { data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value };
            const result = await API.get(API_URL, params);
            if (!result.success) { ReportUtils.showError(result.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            ReportUtils.showContent();
        } catch (e) { ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    init();
})();
</script>
@endsection
