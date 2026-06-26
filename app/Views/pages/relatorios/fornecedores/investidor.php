@extends('layouts.iframe')

@section('title', t('modules.relatorios.fornecedores.investidor.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.fornecedores.investidor.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.fornecedores.investidor.description') ?></p>
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        <i class="fas fa-info-circle mr-1"></i><?= t('modules.relatorios.fornecedores.investidor.generated_notice') ?>
    </div>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => false, 'extraFiltersAfterFilialView' => 'pages.relatorios.fornecedores._filter-investidor'])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="280"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.fornecedores.investidor.col_investidor') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_cnpj') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.fornecedores.investidor.col_veiculos') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_valor_investido') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_receita_gerada') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_devida') ?></th>
                    <th class="table-header text-right hidden lg:table-cell"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_paga') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.fornecedores.investidor.col_saldo') ?></th>
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
    const i18n = <?= json_encode([
        'loadError' => t('modules.relatorios.messages.load_error'),
        'connectionError' => t('modules.relatorios.messages.connection_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const API_URL = '/api/relatorios/fornecedores/investidor';
    let chartInstance = null;
    const statusLabels = <?= json_encode([
        'comissao_gerada' => t('modules.relatorios.fornecedores.investidor.status_comissao_gerada'),
        'sem_fatura_paga' => t('modules.relatorios.fornecedores.investidor.status_sem_fatura_paga'),
        'grupo_sem_comissao' => t('modules.relatorios.fornecedores.investidor.status_grupo_sem_comissao'),
        'comissao_mensal_nao_gerada' => t('modules.relatorios.fornecedores.investidor.status_comissao_mensal_nao_gerada'),
        'veiculo_inativo_com_comissao' => t('modules.relatorios.fornecedores.investidor.status_veiculo_inativo_com_comissao'),
        'desconhecido' => t('modules.relatorios.fornecedores.investidor.status_desconhecido'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const statusClasses = {
        comissao_gerada: 'bg-green-100 text-green-800',
        sem_fatura_paga: 'bg-slate-100 text-slate-700',
        grupo_sem_comissao: 'bg-red-100 text-red-800',
        comissao_mensal_nao_gerada: 'bg-yellow-100 text-yellow-800',
        veiculo_inativo_com_comissao: 'bg-blue-100 text-blue-800',
        desconhecido: 'bg-slate-100 text-slate-700',
    };
    const tipoLabels = <?= json_encode([
        'percentual_locadora' => t('modules.relatorios.fornecedores.investidor.tipo_percentual_locadora'),
        'fixo_locadora' => t('modules.relatorios.fornecedores.investidor.tipo_fixo_locadora'),
        'fixo_locadora_mensal' => t('modules.relatorios.fornecedores.investidor.tipo_fixo_locadora_mensal'),
        'fixo_investidor_mensal' => t('modules.relatorios.fornecedores.investidor.tipo_fixo_investidor_mensal'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const noVehicleDetails = <?= json_encode(t('modules.relatorios.fornecedores.investidor.no_vehicle_details'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    const totalsConfig = [
        { key: 'qtd_investidores', label: '<?= t("modules.relatorios.fornecedores.investidor.qtd_investidores") ?>', icon: 'fa-handshake', format: 'number' },
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.fornecedores.investidor.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'valor_investido', label: '<?= t("modules.relatorios.fornecedores.investidor.valor_investido") ?>', icon: 'fa-coins', format: 'currency' },
        { key: 'receita_gerada', label: '<?= t("modules.relatorios.fornecedores.investidor.receita_gerada") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'comissao_devida', label: '<?= t("modules.relatorios.fornecedores.investidor.comissao_devida") ?>', icon: 'fa-clock', format: 'currency', color: 'yellow' },
        { key: 'comissao_paga', label: '<?= t("modules.relatorios.fornecedores.investidor.comissao_paga") ?>', icon: 'fa-check-circle', format: 'currency' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/fornecedores/investidor/pdf?' + qs, '<?= t("modules.relatorios.fornecedores.investidor.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            fornecedor: document.getElementById('filterFornecedor').value,
            modelo: document.getElementById('filterModelo').value,
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, buildParams());
            if (!result.success) { ReportUtils.showError(result.message || i18n.loadError); return; }
            renderTotals(result.totals);
            renderChart(result.chart);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError(i18n.connectionError); }
    }

    function renderTotals(t) { document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, totalsConfig); }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: c.labels,
                datasets: c.datasets.map((ds, i) => ({
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: ReportUtils.COLORS_ALPHA[i % ReportUtils.COLORS_ALPHA.length],
                    borderColor: ReportUtils.COLORS[i % ReportUtils.COLORS.length],
                    borderWidth: 1,
                })),
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } } },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const isDetailed = document.getElementById('filterModelo').value === 'detalhado';
        tbody.innerHTML = data.map(row => {
            const saldoCls = row.saldo > 0 ? 'text-yellow-700' : 'text-green-700';
            const veiculos = Array.isArray(row.veiculos) ? row.veiculos : [];
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">
                    ${escapeHtml(row.investidor)}
                </td>
                <td class="table-cell hidden lg:table-cell text-slate-500">${escapeHtml(row.cpf_cnpj || '-')}</td>
                <td class="table-cell text-center">${row.qtd_veiculos}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Currency.format(row.valor_investido, true)}</td>
                <td class="table-cell text-right hidden md:table-cell text-green-700">${Currency.format(row.receita_gerada, true)}</td>
                <td class="table-cell text-right hidden md:table-cell text-yellow-700">${Currency.format(row.comissao_devida, true)}</td>
                <td class="table-cell text-right hidden lg:table-cell">${Currency.format(row.comissao_paga, true)}</td>
                <td class="table-cell text-right font-bold ${saldoCls}">${Currency.format(row.saldo, true)}</td>
            </tr>
            ${isDetailed ? renderVehicleDetailsRow(veiculos) : ''}`;
        }).join('');
    }

    function renderVehicleDetailsRow(veiculos) {
        return `<tr class="bg-slate-50">
            <td colspan="8" class="p-0">
                <div class="py-3 pl-8 pr-4 md:pl-12">
                    <div class="mb-2 text-xs font-semibold uppercase text-slate-500"><?= t('modules.relatorios.fornecedores.investidor.vehicle_details') ?></div>
                    ${renderVehicleDetails(veiculos)}
                </div>
            </td>
        </tr>`;
    }

    function renderVehicleDetails(veiculos) {
        if (!veiculos.length) {
            return `<div class="text-sm text-slate-500">${escapeHtml(noVehicleDetails)}</div>`;
        }

        return `<div class="overflow-x-auto rounded-md border border-slate-200 bg-white">
            <table class="w-full min-w-[980px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left"><?= t('modules.relatorios.fornecedores.investidor.col_placa') ?></th>
                        <th class="px-3 py-2 text-left"><?= t('modules.relatorios.fornecedores.investidor.col_veiculo') ?></th>
                        <th class="px-3 py-2 text-left"><?= t('modules.relatorios.fornecedores.investidor.col_grupo') ?></th>
                        <th class="px-3 py-2 text-left"><?= t('modules.relatorios.fornecedores.investidor.col_tipo_comissao') ?></th>
                        <th class="px-3 py-2 text-right"><?= t('modules.relatorios.fornecedores.investidor.col_valor_configurado') ?></th>
                        <th class="px-3 py-2 text-right"><?= t('modules.relatorios.fornecedores.investidor.col_receita_gerada') ?></th>
                        <th class="px-3 py-2 text-right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_devida') ?></th>
                        <th class="px-3 py-2 text-right"><?= t('modules.relatorios.fornecedores.investidor.col_comissao_paga') ?></th>
                        <th class="px-3 py-2 text-left"><?= t('modules.relatorios.fornecedores.investidor.col_diagnostico') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    ${veiculos.map(v => {
                        const status = v.status_diagnostico || 'desconhecido';
                        return `<tr>
                            <td class="px-3 py-2 font-medium">${escapeHtml(v.placa || '-')}</td>
                            <td class="px-3 py-2">${escapeHtml(v.veiculo || '-')}</td>
                            <td class="px-3 py-2">${escapeHtml(v.grupo || '-')}</td>
                            <td class="px-3 py-2">${escapeHtml(tipoLabels[v.tipo_comissao] || '-')}</td>
                            <td class="px-3 py-2 text-right">${formatConfigValue(v)}</td>
                            <td class="px-3 py-2 text-right text-green-700">${Currency.format(v.receita_gerada || 0, true)}</td>
                            <td class="px-3 py-2 text-right text-yellow-700">${Currency.format(v.comissao_devida || 0, true)}</td>
                            <td class="px-3 py-2 text-right">${Currency.format(v.comissao_paga || 0, true)}</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded px-2 py-0.5 text-xs font-medium ${statusClasses[status] || statusClasses.desconhecido}">${escapeHtml(statusLabels[status] || statusLabels.desconhecido)}</span></td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>`;
    }

    function formatConfigValue(v) {
        const valor = Number(v.valor_comissao_config || 0);
        if (!v.tipo_comissao) return '-';
        if (v.tipo_comissao === 'percentual_locadora') return `${valor.toLocaleString((window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-'))}%`;
        return Currency.format(valor, true);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        clearChosen('filterFilial');
        clearChosen('filterFornecedor');
        document.getElementById('filterModelo').value = 'detalhado';
        ReportUtils.hideContent();
    }

    function clearChosen(id) {
        const select = document.getElementById(id);
        if (!select) return;
        if (select.chosenSelect && typeof select.chosenSelect.clear === 'function') {
            select.chosenSelect.clear();
            return;
        }
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    init();
})();
</script>
@endsection
