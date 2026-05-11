@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.dre.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.dre.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.dre.description') ?></p>

    <!-- Filtros -->
    @include('pages.relatorios._partials.filters')

    <!-- Exportacao -->
    @include('pages.relatorios._partials.export-buttons')

    <!-- Totalizadores -->
    @include('pages.relatorios._partials.totalizadores')

    <!-- Estado vazio -->
    @include('pages.relatorios._partials.empty-state')

    <!-- Tabela DRE -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.dre.col_descricao') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.dre.col_valor') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.dre.col_percentual') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/financeiro/dre';

    const totalsConfig = [
        { key: 'receita_bruta', label: '<?= t("modules.relatorios.financeiro.dre.receita_bruta") ?>', icon: 'fa-dollar-sign', format: 'currency' },
        { key: 'lucro_bruto', label: '<?= t("modules.relatorios.financeiro.dre.lucro_bruto") ?>', icon: 'fa-chart-bar', format: 'currency' },
        { key: 'lucro_operacional', label: '<?= t("modules.relatorios.financeiro.dre.lucro_operacional") ?>', icon: 'fa-chart-line', format: 'currency' },
        { key: 'lucro_liquido', label: '<?= t("modules.relatorios.financeiro.dre.lucro_liquido") ?>', icon: 'fa-coins', format: 'currency', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/dre/pdf', '<?= t("modules.relatorios.financeiro.dre.title") ?>'));
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
            };

            const result = await API.get(API_URL, params);

            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderTable(result.data);
            ReportUtils.showContent();

        } catch (error) {
            console.error('Erro ao carregar relatorio:', error);
            ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>');
        }
    }

    function renderTotals(totals) {
        document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(totals, totalsConfig);
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');

        if (!data || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const cf = (v) => Currency.format(v, true);

        tbody.innerHTML = data.map(row => {
            const isHeader = row.is_header || false;
            const isSubtotal = row.is_subtotal || false;
            const indent = row.indent || 0;
            const paddingLeft = (indent * 1.5) + 1;

            let rowClass = 'hover:bg-slate-50';
            let labelClass = 'table-cell';
            let valorClass = 'table-cell text-right';
            let pctClass = 'table-cell text-right';

            if (isHeader) {
                rowClass = 'bg-slate-50';
                labelClass = 'table-cell font-bold text-slate-800';
                valorClass = 'table-cell text-right font-bold text-slate-800';
                pctClass = 'table-cell text-right font-bold text-slate-800';
            } else if (isSubtotal) {
                rowClass = 'border-t-2 border-slate-300';
                labelClass = 'table-cell font-semibold';
                valorClass = 'table-cell text-right font-semibold';
                pctClass = 'table-cell text-right font-semibold';
            }

            const valor = Number(row.valor || 0);
            const valorColor = valor > 0 ? 'text-green-600' : (valor < 0 ? 'text-red-600' : '');

            return `<tr class="${rowClass}">
                <td class="${labelClass}" style="padding-left: ${paddingLeft}rem;">${row.label || '-'}</td>
                <td class="${valorClass} ${valorColor}">${isHeader && !row.valor ? '' : cf(valor)}</td>
                <td class="${pctClass}">${row.percentual !== undefined && row.percentual !== null ? row.percentual + '%' : ''}</td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
