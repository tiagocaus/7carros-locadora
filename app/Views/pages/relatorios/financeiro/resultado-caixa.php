@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.resultado_caixa.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.resultado_caixa.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.resultado_caixa.description') ?></p>

    @include('pages.relatorios._partials.filters')
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="cashDataWarning" class="hidden mb-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status"></div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.resultado_caixa.col_descricao') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.resultado_caixa.col_valor') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.resultado_caixa.col_percentual') ?></th>
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
    const API_URL = '/api/relatorios/financeiro/resultado-caixa';
    const PDF_URL = '/relatorios/financeiro/resultado-caixa/pdf';

    const totalsConfig = [
        { key: 'receita_liquida', label: '<?= t("modules.relatorios.financeiro.resultado_caixa.receita_liquida") ?>', icon: 'fa-money-bill-wave', format: 'currency' },
        { key: 'lucro_bruto', label: '<?= t("modules.relatorios.financeiro.resultado_caixa.lucro_bruto") ?>', icon: 'fa-chart-bar', format: 'currency' },
        { key: 'lucro_operacional', label: '<?= t("modules.relatorios.financeiro.resultado_caixa.lucro_operacional") ?>', icon: 'fa-chart-line', format: 'currency' },
        { key: 'lucro_liquido', label: '<?= t("modules.relatorios.financeiro.resultado_caixa.lucro_liquido") ?>', icon: 'fa-coins', format: 'currency', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregarRelatorio);
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            ReportUtils.exportPdf(PDF_URL, '<?= t("modules.relatorios.financeiro.resultado_caixa.title") ?>');
        });
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, filtros());

            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            renderWarning(result.totals);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (error) {
            console.error('Erro ao carregar resultado por caixa:', error);
            ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>');
        }
    }

    function filtros() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
        };
    }

    function renderWarning(totals) {
        const warning = document.getElementById('cashDataWarning');
        const quantidade = Number(totals?.sem_data_quantidade || 0);

        if (quantidade <= 0) {
            warning.classList.add('hidden');
            warning.textContent = '';
            return;
        }

        const template = '<?= t("modules.relatorios.financeiro.resultado_caixa.warning_sem_data") ?>';
        warning.textContent = template
            .replace('{quantidade}', String(quantidade))
            .replace('{receitas}', Currency.format(Number(totals.sem_data_receitas || 0), true))
            .replace('{despesas}', Currency.format(Number(totals.sem_data_despesas || 0), true));
        warning.classList.remove('hidden');
    }

    function renderTable(data) {
        const container = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');

        if (!Array.isArray(data) || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const type = row.type || 'value';
            const indent = Number(row.indent || 0);
            const paddingLeft = (indent * 1.5) + 1;
            const valor = Number(row.valor || 0);
            const valorColor = valor > 0 ? 'text-green-600' : (valor < 0 ? 'text-red-600' : '');
            const rowClass = type === 'header' ? 'bg-slate-50' : (type === 'subtotal' ? 'border-t-2 border-slate-300' : 'hover:bg-slate-50');
            const weight = type === 'value' ? '' : 'font-semibold text-slate-800';
            const percentual = row.percentual !== undefined && row.percentual !== null ? `${Number(row.percentual)}%` : '';

            return `<tr class="${rowClass}">
                <td class="table-cell ${weight}" style="padding-left: ${paddingLeft}rem;">${escapeHtml(row.label || '-')}</td>
                <td class="table-cell text-right ${weight} ${valorColor}">${Currency.format(valor, true)}</td>
                <td class="table-cell text-right ${weight}">${percentual}</td>
            </tr>`;
        }).join('');
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value);
        return element.innerHTML;
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('cashDataWarning').classList.add('hidden');
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
