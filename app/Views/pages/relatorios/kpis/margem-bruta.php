@extends('layouts.iframe')

@section('title', t('modules.relatorios.kpis.margem_bruta.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.kpis.margem_bruta.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.kpis.margem_bruta.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/kpis/margem-bruta';

    const totalsConfig = [
        { key: 'receita_total', label: '<?= t("modules.relatorios.kpis.margem_bruta.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'custos_variaveis', label: '<?= t("modules.relatorios.kpis.margem_bruta.custos_variaveis") ?>', icon: 'fa-minus-circle', format: 'currency', color: 'red' },
        { key: 'margem_bruta', label: '<?= t("modules.relatorios.kpis.margem_bruta.margem_bruta") ?>', icon: 'fa-chart-line', format: 'currency', color: 'green' },
        { key: 'dias_locados', label: '<?= t("modules.relatorios.kpis.margem_bruta.dias_locados") ?>', icon: 'fa-calendar-check', format: 'number' },
        { key: 'margem_por_dia', label: '<?= t("modules.relatorios.kpis.margem_bruta.margem_por_dia") ?>', icon: 'fa-coins', format: 'currency', color: 'green' },
        { key: 'percentual_margem', label: '<?= t("modules.relatorios.kpis.margem_bruta.percentual_margem") ?>', icon: 'fa-percentage', format: 'percent' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/kpis/margem-bruta/pdf', '<?= t("modules.relatorios.kpis.margem_bruta.title") ?>'));
        document.getElementById('btnLimpar')?.addEventListener('click', () => { ReportUtils.setDefaultPeriod(); document.getElementById('filterFilial').value = ''; const g = document.getElementById('filterGrupo'); if (g) g.value = ''; ReportUtils.hideContent(); });
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const params = { data_inicio: document.getElementById('filterDataInicio').value, data_fim: document.getElementById('filterDataFim').value, filial: document.getElementById('filterFilial').value, grupo: document.getElementById('filterGrupo')?.value || '' };
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
