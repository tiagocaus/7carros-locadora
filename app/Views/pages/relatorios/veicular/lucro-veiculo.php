@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.lucro_veiculo.title'))

@section('content')
<?php ob_start(); ?>
<div class="flex-1 min-w-[160px] max-w-[220px]">
    <label for="filterExibicao" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.lucro_veiculo.filter_exibicao') ?></label>
    <select id="filterExibicao" class="form-input-focus w-full text-sm">
        <option value="simples"><?= t('modules.relatorios.veicular.lucro_veiculo.exibicao_simples') ?></option>
        <option value="detalhado"><?= t('modules.relatorios.veicular.lucro_veiculo.exibicao_detalhado') ?></option>
        <option value="super_detalhado"><?= t('modules.relatorios.veicular.lucro_veiculo.exibicao_super_detalhado') ?></option>
    </select>
</div>
<div class="flex-1 min-w-[180px] max-w-[250px]">
    <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.veicular.lucro_veiculo.col_placa') ?></label>
    <select id="filterVeiculo"
            class="form-input-focus w-full text-sm chosen-select"
            data-chosen-type="server-side"
            data-chosen-search-url="/api/veiculos/buscar"
            data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_vehicles') ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <option value=""><?= t('modules.relatorios.common.all_vehicles') ?></option>
    </select>
</div>
<?php $veiculoFilter = ob_get_clean(); ?>
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.lucro_veiculo.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.lucro_veiculo.description') ?></p>

    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true, 'extraFiltersAfterFilial' => $veiculoFilter])
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
                    <th class="table-header"><?= t('modules.relatorios.veicular.lucro_veiculo.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.lucro_veiculo.col_veiculo') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.relatorios.veicular.lucro_veiculo.col_grupo') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_receita') ?></th>
                    <th class="table-header text-right hidden sm:table-cell"><?= t('modules.relatorios.veicular.lucro_veiculo.col_despesa') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.lucro_veiculo.col_lucro') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.lucro_veiculo.col_margem') ?></th>
                    <th class="table-header text-center js-detailed-col" style="display: none;"><?= t('modules.relatorios.veicular.lucro_veiculo.col_ocupacao') ?></th>
                    <th class="table-header text-center js-detailed-col" style="display: none;"><?= t('modules.relatorios.veicular.lucro_veiculo.col_locacoes') ?></th>
                    <th class="table-header text-center js-detailed-col" style="display: none;"><?= t('modules.relatorios.veicular.lucro_veiculo.col_manutencoes') ?></th>
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
    const i18n = {
        loading: '<?= t("common.labels.loading") ?>',
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
        receitas: '<?= t("modules.relatorios.veicular.lucro_veiculo.col_receitas_detalhe") ?>',
        despesas: '<?= t("modules.relatorios.veicular.lucro_veiculo.col_despesas_detalhe") ?>',
        data: '<?= t("modules.relatorios.veicular.lucro_veiculo.detail_data") ?>',
        descricao: '<?= t("modules.relatorios.veicular.lucro_veiculo.detail_descricao") ?>',
        valor: '<?= t("modules.relatorios.veicular.lucro_veiculo.detail_valor") ?>',
        semRegistros: '<?= t("modules.relatorios.veicular.lucro_veiculo.detail_empty") ?>',
    };
    const API_URL = '/api/relatorios/veicular/lucro-veiculo';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_veiculos', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.qtd_veiculos") ?>', icon: 'fa-car', format: 'number' },
        { key: 'receita_total', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.receita_total") ?>', icon: 'fa-arrow-up', format: 'currency', color: 'green' },
        { key: 'despesa_total', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.despesa_total") ?>', icon: 'fa-arrow-down', format: 'currency', color: 'red' },
        { key: 'lucro_total', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.lucro_total") ?>', icon: 'fa-coins', format: 'currency', colorByValue: true },
        { key: 'margem_geral', label: '<?= t("modules.relatorios.veicular.lucro_veiculo.margem_geral") ?>', icon: 'fa-percentage', format: 'percent', colorByValue: true },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/veicular/lucro-veiculo/pdf?' + qs, '<?= t("modules.relatorios.veicular.lucro_veiculo.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            veiculo: document.getElementById('filterVeiculo')?.value || '',
            grupo: document.getElementById('filterGrupo')?.value || '',
            exibicao: document.getElementById('filterExibicao')?.value || 'simples',
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
        } catch (e) {
            console.error('Erro ao carregar relatório:', e);
            ReportUtils.showError(i18n.connectionError);
        }
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
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: v => Currency.format(v, true) } } },
            },
        });
    }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const exibicao = document.getElementById('filterExibicao')?.value || 'simples';
        const isDetalhado = exibicao === 'detalhado' || exibicao === 'super_detalhado';
        const isSuperDetalhado = exibicao === 'super_detalhado';
        document.querySelectorAll('.js-detailed-col').forEach(el => {
            el.style.display = isDetalhado ? '' : 'none';
        });
        const colspan = isDetalhado ? 10 : 7;
        tbody.innerHTML = data.map(row => {
            const lucroCls = row.lucro >= 0 ? 'text-green-700' : 'text-red-700';
            const margemCls = ReportUtils.getOccupancyColor(row.margem);
            const ocupacaoCls = ReportUtils.getOccupancyColor(Number(row.ocupacao || 0));
            const detailRow = isSuperDetalhado ? renderDetailRow(row, colspan) : '';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-medium">${escapeHtml(row.placa || '-')}</td>
                <td class="table-cell hidden md:table-cell">${escapeHtml(row.veiculo || '-')}</td>
                <td class="table-cell hidden lg:table-cell">${escapeHtml(row.grupo || '-')}</td>
                <td class="table-cell text-right">${Currency.format(row.receita, true)}</td>
                <td class="table-cell text-right hidden sm:table-cell">${Currency.format(row.despesa_total, true)}</td>
                <td class="table-cell text-right font-semibold ${lucroCls}">${Currency.format(row.lucro, true)}</td>
                <td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${margemCls}">${row.margem}%</span></td>
                ${isDetalhado ? `<td class="table-cell text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${ocupacaoCls}">${row.ocupacao || 0}%</span></td>
                <td class="table-cell text-center">${Number(row.locacoes || 0).toLocaleString((window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-'))}</td>
                <td class="table-cell text-center">${Number(row.manutencoes_qtd || 0).toLocaleString((window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-'))}</td>` : ''}
            </tr>${detailRow}`;
        }).join('');
    }

    function renderDetailRow(row, colspan) {
        return `<tr class="bg-slate-50">
            <td colspan="${colspan}" class="px-4 py-3">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 text-xs">
                    ${renderDetailList(i18n.receitas, row.receitas_detalhe || [])}
                    ${renderDetailList(i18n.despesas, row.despesas_detalhe || [])}
                </div>
            </td>
        </tr>`;
    }

    function renderDetailList(title, items) {
        const rows = items.length > 0 ? items.map(item => `<tr>
            <td class="py-1 pr-2 whitespace-nowrap text-slate-500">${DateHelper.format(item.data)}</td>
            <td class="py-1 pr-2 text-slate-700">${escapeHtml(item.descricao || '-')}</td>
            <td class="py-1 text-right font-medium">${Currency.format(item.valor, true)}</td>
        </tr>`).join('') : `<tr><td colspan="3" class="py-2 text-slate-400">${i18n.semRegistros}</td></tr>`;

        return `<div>
            <div class="font-semibold text-slate-700 mb-2">${escapeHtml(title)}</div>
            <table class="w-full">
                <thead>
                    <tr class="text-slate-400">
                        <th class="text-left font-medium py-1 pr-2">${i18n.data}</th>
                        <th class="text-left font-medium py-1 pr-2">${i18n.descricao}</th>
                        <th class="text-right font-medium py-1">${i18n.valor}</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        clearChosen('filterFilial');
        clearChosen('filterVeiculo');
        clearChosen('filterGrupo');
        const exibicao = document.getElementById('filterExibicao');
        if (exibicao) exibicao.value = 'simples';
        ReportUtils.hideContent();
    }

    function clearChosen(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.chosenSelect && typeof el.chosenSelect.clear === 'function') {
            el.chosenSelect.clear();
            return;
        }
        el.value = '';
    }

    init();
})();
</script>
@endsection
