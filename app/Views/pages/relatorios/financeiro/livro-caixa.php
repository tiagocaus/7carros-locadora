@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.livro_caixa.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.livro_caixa.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.livro_caixa.description') ?></p>

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
            <select id="filterFilial"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/matrizes-filiais/buscar"
                    data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterConta" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.filter_conta') ?></label>
            <select id="filterConta"
                    class="form-input-focus w-full text-sm chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/contas-bancarias/buscar"
                    data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
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

    <!-- Estado vazio -->
    @include('pages.relatorios._partials.empty-state')

    <!-- Tabela -->
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.livro_caixa.col_data') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.livro_caixa.col_historico') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.livro_caixa.col_entrada') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.livro_caixa.col_saida') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.livro_caixa.col_saldo') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    <!-- Paginacao -->
    @include('pages.relatorios._partials.pagination')
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/financeiro/livro-caixa';
    let currentPage = 1, perPage = 20;

    const totalsConfig = [
        { key: 'saldo_inicial', label: '<?= t("modules.relatorios.financeiro.livro_caixa.saldo_inicial") ?>', icon: 'fa-wallet', format: 'currency' },
        { key: 'total_entradas', label: '<?= t("modules.relatorios.financeiro.livro_caixa.total_entradas") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'total_saidas', label: '<?= t("modules.relatorios.financeiro.livro_caixa.total_saidas") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'red' },
        { key: 'saldo_final', label: '<?= t("modules.relatorios.financeiro.livro_caixa.saldo_final") ?>', icon: 'fa-balance-scale', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', () => { currentPage = 1; carregarRelatorio(); });
        document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => ReportUtils.exportPdf('/relatorios/financeiro/livro-caixa/pdf', '<?= t("modules.relatorios.financeiro.livro_caixa.title") ?>'));
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();

            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                conta: document.getElementById('filterConta').value,
                page: currentPage,
                perPage: perPage,
            };

            const result = await API.get(API_URL, params);

            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            renderTotals(result.totals);
            renderTable(result.data);
            ReportUtils.renderPagination(result.pagination, (page, pp) => { currentPage = page; if (pp) perPage = pp; carregarRelatorio(); });
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
            const entrada = Number(row.entrada || 0);
            const saida = Number(row.saida || 0);
            const saldo = Number(row.saldo || 0);
            const saldoColor = saldo >= 0 ? 'text-green-600' : 'text-red-600';

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell">${DateHelper.format(row.data)}</td>
                <td class="table-cell">${row.historico || '-'}</td>
                <td class="table-cell text-right ${entrada > 0 ? 'text-green-600' : ''}">${entrada > 0 ? cf(entrada) : '-'}</td>
                <td class="table-cell text-right ${saida > 0 ? 'text-red-600' : ''}">${saida > 0 ? cf(saida) : '-'}</td>
                <td class="table-cell text-right font-medium ${saldoColor}">${cf(saldo)}</td>
            </tr>`;
        }).join('');
    }

    function limparFiltros() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterConta').value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
